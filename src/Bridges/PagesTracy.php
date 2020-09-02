<?php

declare(strict_types=1);

namespace Pages\Bridges;

use Pages\DB\IPageRepository;

class PagesTracy implements \Tracy\IBarPanel
{
	use \Nette\SmartObject;
	
	/**
	 * Pages instance
	 * @var \Pages\Pages
	 */
	public $pages;
	
	/**
	 * Pages repository
	 * @var \Pages\DB\IPageRepository
	 */
	public $pageRepo;

	public function __construct(\Pages\Pages $pages, IPageRepository $pageRepo)
	{
		$this->pages = $pages;
		$this->pageRepo = $pageRepo;
		
		return;
	}
	
	/**
	 * Renders HTML code for storm panel
	 * @throws \Throwable
	 */
	public function getTab(): string
	{
		return self::capture(function (): void { // @codingStandardsIgnoreLine
			require __DIR__ . '/templates/Pages.panel.tab.phtml';
			
			return;
		});
	}
	
	/**
	 * Get Pages panel
	 * @throws \Throwable
	 */
	public function getPanel(): string
	{
		return self::capture(function (): void {  // @codingStandardsIgnoreLine
			require __DIR__ . '/templates/Pages.panel.phtml';
			
			return;
		});
	}
	
	/**
	 * Captures PHP output into a string.
	 * @param callable $func
	 * @throws \Throwable
	 */
	public static function capture(callable $func): string
	{
		\ob_start();
		
		try {
			$func();
			
			return \ob_get_clean();
		} catch (\Throwable $e) {
			\ob_end_clean();
			
			throw $e;
		}
	}
}
