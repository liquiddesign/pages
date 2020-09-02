<?php

declare(strict_types=1);

namespace Pages\DB;

interface IRedirectRepository
{
	public function getRedirect(string $url, ?string $mutation): ?Redirect;
}
