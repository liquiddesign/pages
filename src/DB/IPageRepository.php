<?php

declare(strict_types=1);

namespace Pages\DB;

use Pages\PageType;

interface IPageRepository
{
	public function getPageByUrl(string $url, ?string $lang): ?IPage;
	
	/**
	 * @param \Pages\PageType $pageType
	 * @param string|null $lang
	 * @param mixed[] $parameters
	 */
	public function getPageByTypeAndParams(PageType $pageType, ?string $lang, array $parameters = []): ?IPage;
	
	public function isUrlAvailable(string $url, ?string $lang, ?string $notIncludePagePK = null): bool;
}
