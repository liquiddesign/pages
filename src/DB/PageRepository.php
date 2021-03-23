<?php

declare(strict_types=1);

namespace Pages\DB;

use Pages\Helpers;
use Pages\Pages;
use Pages\PageType;
use StORM\Collection;
use StORM\DIConnection;
use StORM\Entity;
use StORM\SchemaManager;

/**
 * Class PageRepository
 * @extends \StORM\Repository<\Pages\DB\Page>
 */
class PageRepository extends \StORM\Repository implements IPageRepository
{
	protected Pages $pages;
	
	public function __construct(DIConnection $connection, SchemaManager $schemaManager, Pages $pages)
	{
		parent::__construct($connection, $schemaManager);
		
		$this->pages = $pages;
	}
	
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
	
	public function getPageByUrl(string $url, ?string $lang, bool $includeOffline = true): ?IPage
	{
		$suffix = '';
		
		if ($lang) {
			$suffix = $this->getConnection()->getAvailableMutations()[$lang] ?? '';
		}
		
		$collection = $this->many($lang)->where($lang ? "url$suffix" : 'url', $url)->setTake(1);
		
		if (!$includeOffline) {
			$collection->where('isOffline', false);
		}
		
		/** @var \Pages\DB\Page|null $page */
		$page = $collection->first();
		
		return $page;
	}
	
	/**
	 * @param string $pageTypeId
	 * @param string|null $lang
	 * @param mixed[] $parameters
	 * @param bool $includeOffline
	 * @param bool $perfectMatch
	 * @return \Pages\DB\IPage|null
	 */
	public function getPageByTypeAndParams(string $pageTypeId, ?string $lang, array $parameters = [], bool $includeOffline = true, bool $perfectMatch = true): ?IPage
	{
		$pageType = $this->pages->getPageType($pageTypeId);
		
		$type = $pageType->getID();
		$requiredParameters = $pageType->getRequiredParameters($parameters);
		$optionalParameters = $pageType->getOptionalParameters($parameters);
		$page = null;
		
		if ($optionalParameters) {
			$page = $this->getPageByTypeLangQuery($type, $lang, Helpers::serializeParameters($requiredParameters + $optionalParameters), $includeOffline);
			
			if ($perfectMatch) {
				return $page;
			}
		}
		
		if (!$page) {
			$page = $this->getPageByTypeLangQuery($type, $lang, Helpers::serializeParameters($requiredParameters), $includeOffline);
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
	
	public function get404Pages(string $pageTypeId): Collection
	{
		$pageType = $this->pages->getPageType($pageTypeId);
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
	
	private function getPageByTypeLangQuery(string $type, ?string $lang, string $httpQuery, bool $includeOffline = true): ?IPage
	{
		$pages = $this->many($lang)->where('type', $type);
		
		if (!$includeOffline) {
			$pages->where('isOffline', false);
		}
		
		if ($lang) {
			$suffix = $this->getConnection()->getAvailableMutations()[$lang] ?? '';
			$pages->where("url$suffix IS NOT NULL");
		}
		
		return \Nette\Utils\Helpers::falseToNull($pages->where('params', $httpQuery)->first());
	}
	
	/**
	 * Sitemaps format example:
	 * [$pagetype1 => $param1, $pagetype2, $pagetype3 => $param3 ....]
	 *
	 * @param array $sitemaps
	 * @return \StORM\Collection
	 */
	public function getPagesForSitemap(array $sitemaps): Collection
	{
		$where = [];
		$vars = [];
		$i = 0;
		
		foreach ($sitemaps as $key => $value) {
			$type = \is_int($key) ? $value : $key;
			$param = \is_int($key) ? null : $value;
			$where[] = "type = :type_$i" . ($param !== null ? " AND params LIKE :param_$i" : '');
			$vars["type_$i"] = $type;
			
			if ($param !== null) {
				$vars["param_$i"] = "$param=%";
			}
			
			$i++;
		}
		
		return $this->many()->where('isOffline', false)->where(\implode(' OR ', $where), $vars);
	}
}
