<?php

declare(strict_types=1);

namespace Pages\DB;

use Pages\Helpers;
use Pages\PageType;
use StORM\Collection;
use StORM\Entity;

/**
 * Class PageRepository
 * @extends \StORM\Repository<\Pages\DB\Page>
 */
class PageRepository extends \StORM\Repository implements IPageRepository
{
	public function isUrlAvailable(string $url, ?string $lang, ?string $notIncludePagePK = null): bool
	{
		$suffix = '';
		
		if ($lang) {
			$suffix = $this->getConnection()->getAvailableMutations()[$lang] ?? '';
		}
		
		/** @var \StORM\Collection $pages */
		$pages = $this->many()->where($lang ? "url$suffix" : 'url', $url);
		
		if ($notIncludePagePK !== null) {
			$pages->whereNot('this.uuid', $notIncludePagePK);
		}
		
		return $pages->isEmpty();
	}
	
	public function getPageByUrl(string $url, ?string $lang): ?IPage
	{
		$suffix = '';
		
		if ($lang) {
			$suffix = $this->getConnection()->getAvailableMutations()[$lang] ?? '';
		}
		
		/** @var \Pages\DB\Page $page */
		$page = $this->many($lang)->where('isOffline', false)->where($lang ? "url$suffix" : 'url', $url)->setTake(1)->first();
		
		return $page;
	}
	
	/**
	 * @param \Pages\PageType $pageType
	 * @param string|null $lang
	 * @param mixed[] $parameters
	 */
	public function getPageByTypeAndParams(PageType $pageType, ?string $lang, array $parameters = []): ?IPage
	{
		$type = $pageType->getID();
		$requiredParameters = $pageType->getRequiredParameters($parameters);
		$optionalParameters = $pageType->getOptionalParameters($parameters);
		$page = null;
		
		if ($optionalParameters) {
			$page = $this->getPageByTypeLangQuery($type, $lang, Helpers::serializeParameters($requiredParameters + $optionalParameters));
		}
		
		if (!$page) {
			$page = $this->getPageByTypeLangQuery($type, $lang, Helpers::serializeParameters($requiredParameters));
		}
		
		return $page;
	}
	
	public function getPagesUp(Page $page, ?int $levelUp = null): Collection
	{
		$divisionChar = '=';
		$currentLevel = \substr_count($page->params, $divisionChar);
		
		$pages = $this->many()
			->where('type', $page->type)
			->whereNot('this.uuid', $page->getPK())
			->where(":params LIKE CONCAT(params,'%')", ['params' => $page->params])
			->orderBy(['LENGTH(params)' => 'DESC']);
		
		if ($levelUp !== null) {
			$pages->where("(LENGTH(params) - LENGTH(REPLACE(params, '$divisionChar', ''))) = :level", ['level' => $currentLevel - $levelUp]);
		}
		
		return $pages;
	}
	
	public function getPagesDown(Page $page, ?int $levelDown = null): Collection
	{
		$divisionChar = '=';
		$currentLevel = \substr_count($page->params, $divisionChar);
		
		$pages = $this->many()
			->where('type', $page->type)
			->whereNot('this.uuid', $page->getPK())
			->where("params LIKE :params", ['params' => $page->params . '%'])
			->orderBy(['LENGTH(params)' => 'DESC']);
		
		if ($levelDown !== null) {
			$pages->where("(LENGTH(params) - LENGTH(REPLACE(params, '$divisionChar', ''))) = :level", ['level' => $currentLevel + $levelDown]);
		}
		
		return $pages;
	}
	
	public function get404Pages(PageType $pageType): Collection
	{
		$found = [];
		
		foreach ($pageType->getParameters() as $name => $paramType) {
			if (\is_subclass_of($paramType, Entity::class)) {
				/** @var \Pages\DB\Page $page */
				foreach ($this->many()->where('type', $pageType->getID())->where('params LIKE :params', ['params' => $name . '=']) as $page) {
					$parsed = $page->getParsedParameters();
					
					if (!isset($parsed[$name]) || $this->getConnection()->findRepository($paramType)->one($parsed[$name], false)) {
						continue;
					}
					
					$found[] = $page->getPK();
				}
			}
		}
		
		return $found ? $this->many()->where('this.uuid', $found) : $this->many()->where('1=0');
	}
	
	private function getPageByTypeLangQuery(string $type, ?string $lang, string $httpQuery): ?IPage
	{
		$pages = $this->many($lang)->where('type', $type)->where('isOffline', false);
		
		if ($lang) {
			$suffix = $this->getConnection()->getAvailableMutations()[$lang] ?? '';
			$pages->where("url$suffix IS NOT NULL");
		}
		
		return \Nette\Utils\Helpers::falseToNull($pages->where('params', $httpQuery)->first());
	}
}
