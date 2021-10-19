<?php

declare(strict_types=1);

namespace Pages\DB;

use StORM\DIConnection;

interface IPageRepository
{
	public function getConnection(): DIConnection;
	
	public function getPageByUrl(string $url, ?string $lang, bool $includeOffline = true): ?IPage;
	
	/**
	 * @param string $pageTypeId
	 * @param string|null $lang
	 * @param mixed[] $parameters
	 * @param bool $includeOffline
	 * @param bool $perfectMatch
	 */
	public function getPageByTypeAndParams(string $pageTypeId, ?string $lang, array $parameters = [], bool $includeOffline = true, bool $perfectMatch = true): ?IPage;
	
	public function isUrlAvailable(string $url, ?string $lang, ?string $notIncludePagePK = null): bool;
}
