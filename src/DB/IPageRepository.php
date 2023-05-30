<?php

declare(strict_types=1);

namespace Pages\DB;

use Base\DB\Shop;
use StORM\DIConnection;

interface IPageRepository
{
	public function getConnection(): DIConnection;
	
	public function getPageByUrl(string $url, ?string $lang, bool $includeOffline = true, Shop|null $selectedShop = null, bool $filterOnlySelectedShop = false): ?IPage;
	
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
	): ?IPage;
	
	public function isUrlAvailable(string $url, ?string $lang, ?string $notIncludePagePK = null, Shop|null $selectedShop = null): bool;
}
