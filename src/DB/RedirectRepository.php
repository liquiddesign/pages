<?php

declare(strict_types=1);

namespace Pages\DB;

use Nette\Utils\Helpers;

/**
 * Class RedirectRepository
 * @extends \StORM\Repository<\Pages\DB\Redirect>
 */
class RedirectRepository extends \StORM\Repository implements IRedirectRepository
{
	public function getRedirect(string $url, ?string $mutation): ?Redirect
	{
		$redirects = $this->many()
			->where('IF(fromUrl = "/","",fromUrl)', $url)
			->orderBy(['priority' => 'ASC', 'createdTs' => 'DESC']);
		
		if ($mutation) {
			$redirects->where('fromMutation = :mutation OR fromMutation IS NULL', ['mutation' => $mutation]);
		}
			
		return Helpers::falseToNull($redirects->first());
	}
}
