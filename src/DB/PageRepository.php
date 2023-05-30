<?php

declare(strict_types=1);

namespace Pages\DB;

use Base\DB\Shop;
use Pages\Helpers;
use Pages\Pages;
use StORM\Collection;
use StORM\DIConnection;
use StORM\Entity;
use StORM\Meta\Relation;
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
	
	public function isUrlAvailable(string $url, ?string $lang, ?string $notIncludePagePK = null, Shop|null $selectedShop = null): bool
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

		if ($selectedShop) {
			$pages->where('this.fk_shop', $selectedShop->getPK());
		}
		
		return $pages->isEmpty();
	}

	public function getPageByUrl(string $url, ?string $lang, bool $includeOffline = true, Shop|null $selectedShop = null, bool $filterOnlySelectedShop = false): ?IPage
	{
		$suffix = '';
		
		if ($lang) {
			$suffix = $this->getConnection()->getAvailableMutations()[$lang] ?? '';
		}
		
		$collection = $this->many($lang)->where($lang ? "url$suffix" : 'url', $url)->setTake(1);

		if ($selectedShop) {
			if ($filterOnlySelectedShop) {
				$collection->where('this.fk_shop', $selectedShop->getPK());
			} else {
				$collection->where('this.fk_shop = :shop OR this.fk_shop IS NULL', ['shop' => $selectedShop->getPK()]);
			}
		}
		
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
	 * @param array<mixed> $parameters
	 * @param bool $includeOffline
	 * @param bool $perfectMatch
	 */
	public function getPageByTypeAndParams(
		string $pageTypeId,
		?string $lang,
		array $parameters = [],
		bool $includeOffline = true,
		bool $perfectMatch = true,
		Shop|null $selectedShop = null,
		bool $filterOnlySelectedShop = false,
	): ?IPage {
		$pageType = $this->pages->getPageType($pageTypeId);
		
		$relationWhere = $this->mapProperties($parameters, true, true);
		
		$type = $pageType->getID();
		$requiredParameters = $pageType->getRequiredParameters($parameters);
		$optionalParameters = $pageType->getOptionalParameters($parameters);
		$page = null;
		
		if ($optionalParameters) {
			$page = $this->getPageByTypeLangQuery($type, $lang, $requiredParameters + $optionalParameters, $relationWhere, $includeOffline, $selectedShop, $filterOnlySelectedShop);
			
			if ($perfectMatch) {
				return $page;
			}
		}
		
		if (!$page) {
			$page = $this->getPageByTypeLangQuery($type, $lang, $requiredParameters, $relationWhere, $includeOffline, $selectedShop, $filterOnlySelectedShop);
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
			->where('params LIKE :params', ['params' => $page->params . '%'])
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
	
	/**
	 * Synchronize page unique indexes
	 * @param array<mixed>|object $values
	 * @param ?array<mixed> $parameters
	 * @param array<string>|array<\StORM\Literal>|null $updateProps
	 * @param bool|null $filterByColumns
	 * @param bool|null $ignore
	 * @param array<mixed> $checkKeys
	 * @param array<mixed> $primaryKeyNames
	 * @throws \StORM\Exception\NotFoundException
	 * @return \Pages\DB\Page
	 */
	public function syncPage(
		$values,
		?array $parameters = [],
		?array $updateProps = null,
		?bool $filterByColumns = false,
		?bool $ignore = null,
		array $checkKeys = [],
		array $primaryKeyNames = []
	): Entity {
		if ($parameters !== null) {
			if (\is_object($values)) {
				$values = \StORM\Helpers::toArrayRecursive($values);
			}
			
			$values += $this->mapProperties($parameters, false);
			$values['params'] = Helpers::serializeParameters($parameters);
		}
		
		return $this->syncOne($values, $updateProps, $filterByColumns, $ignore, $checkKeys, $primaryKeyNames);
	}

	/**
	 * Returns mapped array by entity relations
	 * @param array<mixed> $parameters
	 * @param bool $unset
	 * @return array<mixed>
	 */
	protected function mapProperties(array &$parameters, bool $keys = true, bool $unset = false): array
	{
		$map = [];
		
		foreach ($parameters as $k => $v) {
			if (\is_string($k) && $this->getStructure()->getRelation($k) instanceof Relation && $this->getStructure()->getRelation($k)->isKeyHolder()) {
				$map[$keys ? $this->getStructure()->getRelation($k)->getSourceKey() : $k] = $v;
				
				if ($unset) {
					unset($parameters[$k]);
				}
			}
		}
		
		return $map;
	}
	
	/**
	 * @param string $type
	 * @param string|null $lang
	 * @param array<mixed> $params
	 * @param array<mixed> $relationWhere
	 * @param bool $includeOffline
	 */
	private function getPageByTypeLangQuery(
		string $type,
		?string $lang,
		array $params,
		array $relationWhere,
		bool $includeOffline = true,
		Shop|null $selectedShop = null,
		bool $filterOnlySelectedShop = false,
	): ?IPage {
		$httpQuery = Helpers::serializeParameters($params);
		
		$pages = $this->many($lang)->where('type', $type);
		
		if (!$includeOffline) {
			$pages->where('isOffline', false);
		}
		
		if ($lang) {
			$suffix = $this->getConnection()->getAvailableMutations()[$lang] ?? '';
			$pages->where("url$suffix IS NOT NULL");
		}
		
		if ($relationWhere) {
			$pages->whereMatch($relationWhere);
		}
		
		if (\count($params) > 1) {
			$pages->where(":query LIKE CONCAT(params, '%')", ['query' => $httpQuery])
				->orderBy(["(LENGTH(params) - LENGTH(REPLACE(params,'&','')))" => 'DESC']);
		} else {
			$pages->where('params', $httpQuery);
		}

		if ($selectedShop) {
			if ($filterOnlySelectedShop) {
				$pages->where('this.fk_shop', $selectedShop->getPK());
			} else {
				$pages->where('this.fk_shop = :shop OR this.fk_shop IS NULL', ['shop' => $selectedShop->getPK()]);
			}
		}
		
		return \Nette\Utils\Helpers::falseToNull($pages->first());
	}
}
