<?php

declare(strict_types=1);

namespace Pages;

use Base\ShopsConfig;
use Nette;
use Nette\Application\Helpers;
use Nette\Application\UI\Presenter;
use Nette\Caching\Cache;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use Pages\DB\IPageRepository;
use StORM\Repository;

class Router implements \Nette\Routing\Router
{
	public const CACHE_INDEX = self::class . '::pages';

	/**
	 * @var array<\Pages\DB\IPage>|array<null>
	 */
	private array $outCache;
	
	/**
	 * @var array<\Pages\DB\IPage>|array<null>
	 */
	private array $inCache = [];

	private Nette\Caching\Cache $cache;
	
	public function __construct(
		private readonly Pages $pages,
		private readonly IPageRepository $pageRepository,
		private readonly ShopsConfig $shopsConfig,
		Nette\Caching\Storage $storage,
		private readonly string $mutationParameter = 'lang',
		private readonly bool $cacheEnabled = false,
	) {
		$this->cache = new Nette\Caching\Cache($storage);
	}
	
	/**
	 * Maps HTTP request to an array.
	 * @param \Nette\Http\IRequest $httpRequest
	 * @return ?array<string>
	 */
	public function match(Nette\Http\IRequest $httpRequest): ?array
	{
		$url = $httpRequest->getUrl();
		
		$urlParams = $httpRequest->getQuery();
		$mutations = $this->pages->getMutations();
		$defaultMutation = $this->pages->getDefaultMutation();
		
		// parsing url
		$pageUrl = (string) Strings::substring($url->getPath(), Strings::length($url->getBasePath()));
		$prefixLang = \strtok($pageUrl, '/');
		$hasLangPrefix = $prefixLang !== false
			&& $prefixLang !== $defaultMutation
			&& Arrays::contains($mutations, $prefixLang);

		// Bez jazykového prefixu v URL rozhoduje aktivní mutace spojení, ne výchozí mutace aplikace.
		// Multi-shop instalace obsluhuje každou jazykovou verzi na vlastní doméně, takže tam prefix
		// nikdy není a napevno použitá výchozí mutace by hledala `url_<default>` — zatímco odkazy se
		// staví z `url_<aktivní>`. Ta asymetrie znamená 404 na každou nedefaultní jazykovou verzi.
		$lang = $hasLangPrefix ? $prefixLang : $this->getRequestMutation($mutations, $defaultMutation);
		
		// filter IN
		if ($filterInCallback = $this->pages->getFilterInCallback()) {
			$pageUrl = \call_user_func_array($filterInCallback, [$pageUrl]);
			
			if ($pageUrl === null) {
				return null;
			}
		}
		
		// strip lang prefix — jen když v URL opravdu je; mutace odvozená ze spojení žádný neubírá
		if ($hasLangPrefix) {
			$pageUrl = (string) Strings::substring($pageUrl, Strings::length((string) $prefixLang) + 1);
		}
		
		// try get by url
		$selectedShop = $this->shopsConfig->getSelectedShop();
		$cacheIndex = ($selectedShop?->getPK() ?? '') . '/' . $lang . '/' . $pageUrl;
		$page = $this->inCache[$cacheIndex] ?? $this->pageRepository->getPageByUrl($pageUrl, $lang, false, $selectedShop);
		$this->inCache[$cacheIndex] = $page;
		
		if ($page === null || !$page->isAvailable($lang)) {
			return null;
		}
		
		// set page and get page type
		$this->pages->setPage($page);
		$pageType = $this->pages->getPageType($page->getType());
		
		if ($pageType === null) {
			return null;
		}
		
		// merge all parameters
		[$presenter, $action] = Helpers::splitName($pageType->getPlink());
		
		$parameters = [
				Presenter::PRESENTER_KEY => $presenter,
				Presenter::ACTION_KEY => $action,
			] + $urlParams + $page->getParsedParameters() + $page->getPropertyParameters();
		
		$parameters = $this->pages->mapParameters($parameters);
		
		if ($lang) {
			$parameters[$this->mutationParameter] = $lang;
		}
		
		return $parameters;
	}
	
	/**
	 * Constructs absolute URL from array.
	 * @param array<string> $params
	 * @param \Nette\Http\UrlScript $refUrl
	 */
	public function constructUrl(array $params, Nette\Http\UrlScript $refUrl): ?string
	{
		$defaultLang = $this->pages->getDefaultMutation();
		$plink = $params[Presenter::PRESENTER_KEY] . ':' . $params[Presenter::ACTION_KEY];
		// if defaultLang not set ignore lang
		// Bez explicitního `lang` v parametrech se jazyk odkazu odvodí ze stejného zdroje jako v
		// `match()`, ne z výchozí mutace aplikace. Jinak by odkaz vygenerovaný na doméně jiné jazykové
		// verze dostal jazyk `default`, tedy jiný než ten, který si `match()` z požadavku odvodí — a
		// tím pádem i prefix `/<default>/`, který na té doméně nic neresolvuje.
		$lang = $defaultLang
			? ($params[$this->mutationParameter] ?? $this->getRequestMutation($this->pages->getMutations(), $defaultLang))
			: null;
		
		$pageType = $this->pages->getTypeByPlink($plink);
		
		if (!$pageType) {
			return null;
		}
		
		$params = $this->pages->unmapParameters($params);
		
		unset($params[Presenter::PRESENTER_KEY], $params[Presenter::ACTION_KEY], $params[$this->mutationParameter]);
		
		$serializedParams = \http_build_query(\array_intersect_key($params, $pageType->getParameters()));
		$cacheIndex = $pageType->getID() . $serializedParams . ($serializedParams ? '&' : '');
		
		if ($this->pageRepository instanceof Repository) {
			$outCacheCollection = $this->pageRepository->many()
				->where('type', $this->pages->getPrefetchTypes())
				->setIndex('CONCAT(this.type,this.params)');

			$this->shopsConfig->filterShopsInShopEntityCollection($outCacheCollection);

			$this->outCache ??= $outCacheCollection->toArray();
		} else {
			$this->outCache = [];
		}
		
		if (!\array_key_exists($cacheIndex, $this->outCache)) {
			if (!$serializedParams && Arrays::contains($this->pages->getPrefetchTypes(), $pageType->getID())) {
				return null;
			}

			$getPageCallback = function (&$dependencies = null) use ($pageType, $lang, $params) {
				$dependencies = [
					Cache::Tags => [self::CACHE_INDEX],
					Cache::Expire => '1 day',
				];

				return $this->pageRepository->getPageByTypeAndParams($pageType->getID(), $lang, $params, false, false, $this->shopsConfig->getSelectedShop()) ?: false;
			};

			$this->outCache[$cacheIndex] = $this->cacheEnabled
				? $this->cache->load($this->getPersistentCacheKey($cacheIndex, $lang), $getPageCallback)
				: $getPageCallback();
		}
		
		$page = $this->outCache[$cacheIndex];
		
		if ($page === null || $page === false || !$page->isAvailable($lang)) {
			return null;
		}

		$page->setParent($this->pageRepository);
		
		$params = \array_diff_key($params, $page->getParsedParameters() + $page->getPropertyParameters());
		$pageUrl = $page->getUrl($lang);
		// Prefix se přidává jen tehdy, když se jazyk odkazu liší od toho, který si `match()` odvodí
		// z požadavku sám. Na multi-shop instalaci, kde každý jazyk bydlí na vlastní doméně, je
		// aktivní mutace spojení už ta správná, takže prefix je zbytečný — a kdyby se přidal,
		// canonicalizace by každý odkaz přesměrovala na `/<mutace>/…`.
		$hasLangPrefix = $lang && $lang !== $this->getRequestMutation($this->pages->getMutations(), $defaultLang);
		$path = $refUrl->getPath() . ($hasLangPrefix ? ($pageUrl ? "$lang/" : $lang) : '') . $pageUrl;
		
		// filter OUT
		if ($filterOutCallback = $this->pages->getFilterOutCallback()) {
			$path = \call_user_func_array($filterOutCallback, [$path]);
		}
		
		$url = new \Nette\Http\Url();
		$url->setScheme($refUrl->getScheme());
		$url->setHost($refUrl->getAuthority());
		$url->setPath($path);
		$url->appendQuery(\http_build_query($params));
		$url->setFragment($refUrl->getFragment());
		
		return (string) $url;
	}

	/**
	 * Klíč persistentní cache pro jednu stránku.
	 *
	 * `$cacheIndex` sám (`typ` + serializované parametry) je index **in-request** prefetch mapy
	 * `$outCache`, která se plní už shop-filtrovanou kolekcí. Klíč Nette cache je ale sdílený napříč
	 * celou instalací, a na multi-shop instalaci (jedna aplikace, víc domén, jeden `tempDir`) je
	 * výsledek `getPageByTypeAndParams()` závislý **jak na vybraném shopu, tak na mutaci** — každá
	 * mutace má vlastní `url_<mutace>` a každý shop vlastní sadu stránek. Bez obojího v klíči určil
	 * URL pro všechny shopy ten, kdo cache nahřál první, a to až do expirace (1 den).
	 */
	private function getPersistentCacheKey(string $cacheIndex, ?string $lang): string
	{
		return self::CACHE_INDEX . '/' . ($this->shopsConfig->getSelectedShop()?->getPK() ?? '') . '/' . ($lang ?? '') . '/' . $cacheIndex;
	}

	/**
	 * Mutace pro požadavek bez jazykového prefixu v URL.
	 *
	 * Zdrojem je aktivní mutace StORM spojení — tu nastavuje aplikace podle domény/shopu ještě před
	 * routováním. Když repozitář není StORM nebo je mutace mimo nakonfigurované `mutations`, drží se
	 * výchozí mutace, takže jednojazyčné instalace se chovají přesně jako dřív.
	 * @param array<string> $mutations
	 */
	private function getRequestMutation(array $mutations, ?string $defaultMutation): ?string
	{
		if (!$this->pageRepository instanceof Repository) {
			return $defaultMutation;
		}

		$mutation = $this->pageRepository->getConnection()->getMutation();

		return $mutation !== null && Arrays::contains($mutations, $mutation) ? $mutation : $defaultMutation;
	}
}
