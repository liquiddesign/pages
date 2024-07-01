<?php

declare(strict_types=1);

namespace Pages\DB;

use Base\Entity\ShopEntity;

/**
 * @table
 * @index{"name":"redirect_url","unique":true,"columns":["fromUrl","fromMutation", "fk_shop"]}
 */
class Redirect extends ShopEntity
{
	/**
	 * @column
	 */
	public string $fromUrl;
	
	/**
	 * @column
	 */
	public ?string $fromMutation;
	
	/**
	 * @column
	 */
	public string $toUrl;
	
	/**
	 * @column
	 */
	public ?string $toMutation;
	
	/**
	 * @column
	 */
	public int $priority = 10;
	
	/**
	 * @column{"type":"timestamp","default":"CURRENT_TIMESTAMP"}
	 */
	public string $createdTs;
}
