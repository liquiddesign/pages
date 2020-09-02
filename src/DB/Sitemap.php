<?php

declare(strict_types=1);

namespace Pages\DB;

/**
 * @table
 */
class Sitemap extends \StORM\Entity
{
	/**
	 * @column{"type":"date"}
	 */
	public string $lastmod;
	
	/**
	 * @column
	 */
	public string $changefreq;
	
	/**
	 * @column
	 */
	public float $priority;
}
