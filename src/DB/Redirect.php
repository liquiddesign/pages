<?php

declare(strict_types=1);

namespace Pages\DB;

/**
 * @table
 * @index{"name":"redirect_url","unique":true,"columns":["fromUrl","fromMutation"]}
 */
class Redirect extends \StORM\Entity
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
