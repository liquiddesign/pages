<?php

declare(strict_types=1);

namespace Pages;

use Base\ShopsConfig;
use Nette;
use Nette\Application\Helpers;
use Nette\Application\UI\Presenter;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use Pages\DB\IPageRepository;
use StORM\Repository;

class Router implements \Nette\Routing\Router
{
	/**
	 * @var array<\Pages\DB\IPage>|array<null>
	 */
	private array $outCache;
	
	/**
	 * @var array<\Pages\DB\IPage>|array<null>
	 */
	private array $inCache = [];
	
	public function __construct(
		private readonly Pages $pages,
		private readonly IPageRepository $pageRepository,
		private readonly ShopsConfig $shopsConfig,
		private readonly string $mutationParameter = 'lang'
	) {
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
		$lang = \strtok($pageUrl, '/');
		
		if (!Arrays::contains($mutations, $lang) || $lang === $defaultMutation) {
			$lang = $defaultMutation;
		}
		
		// filter IN
		if ($filterInCallback = $this->pages->getFilterInCallback()) {
			$pageUrl = \call_user_func_array($filterInCallback, [$pageUrl]);
			
			if ($pageUrl === null) {
				return null;
			}
		}
		
		// strip lang prefix
		if ($lang !== $defaultMutation) {
			$pageUrl = (string) Strings::substring($pageUrl, Strings::length($lang) + 1);
		}
		
		// try get by url
		$cacheIndex = $lang . $pageUrl;
		$page = $this->inCache[$cacheIndex] ?? $this->pageRepository->getPageByUrl($pageUrl, $lang, false, $this->shopsConfig->getSelectedShop());
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
		$lang = $defaultLang ? ($params[$this->mutationParameter] ?? $defaultLang) : null;
		
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
			
			$this->outCache[$cacheIndex] = $this->pageRepository->getPageByTypeAndParams($pageType->getID(), $lang, $params, false, false, $this->shopsConfig->getSelectedShop());
		}
		
		$page = $this->outCache[$cacheIndex];
		
		if (!$page || !$page->isAvailable($lang)) {
			return null;
		}
		
		$params = \array_diff_key($params, $page->getParsedParameters() + $page->getPropertyParameters());
		$pageUrl = $page->getUrl($lang);
		$hasLangPrefix = $lang && $lang !== $defaultLang;
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
}
