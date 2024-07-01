<?php

declare(strict_types=1);

namespace Pages\DB;

use Base\ShopsConfig;
use Nette\Utils\Helpers;
use StORM\DIConnection;
use StORM\SchemaManager;

/**
 * Class RedirectRepository
 * @extends \StORM\Repository<\Pages\DB\Redirect>
 */
class RedirectRepository extends \StORM\Repository implements IRedirectRepository
{
	public function __construct(DIConnection $connection, SchemaManager $schemaManager, protected readonly ShopsConfig $shopsConfig)
	{
		parent::__construct($connection, $schemaManager);
	}

	public function getRedirect(string $url, ?string $mutation): ?Redirect
	{
		$redirects = $this->many()
			->where('IF(fromUrl = "/","",LOWER(fromUrl))', $url)
			->orderBy(['priority' => 'ASC', 'createdTs' => 'DESC']);
		
		if ($mutation) {
			$redirects->where('fromMutation = :mutation OR fromMutation IS NULL', ['mutation' => $mutation]);
		}

		$this->shopsConfig->filterShopsInShopEntityCollection($redirects);
			
		return Helpers::falseToNull($redirects->first());
	}
}
