<?php

declare(strict_types=1);

namespace Pages\DB;

/**
 * @table
 */
class PageTemplate extends \StORM\Entity
{
	/**
	 * Název
	 * @column{"unique":true}
	 */
	public string $name;

	/**
	 * Titulek
	 * @column{"mutations":true}
	 */
	public ?string $title = null;

	/**
	 * Popis
	 * @column{"mutations":true}
	 */
	public ?string $description = null;

	/**
	 * Page type
	 * @column
	 */
	public string $type;
}
