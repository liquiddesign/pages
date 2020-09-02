<?php

namespace Tests;

use Nette\Routing\RouteList;
use Pages\Bridges\PagesDI;
use Pages\Pages;
use StORM\Bridges\StormDI;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Class Test
 * @package Tests
 */
class Test extends TestCase
{
	public function testList(): void
	{
		/** @var \Nette\DI\Container $container */
		$container = createContainer(CONFIGS_DIR . '/config.neon', [
			'pages' => PagesDI::class,
			'storm' => StormDI::class,
			], ['routing.router' => RouteList::class]
		);
		
		$router = $container->getService('routing.router');
		$pages = $container->getService('pages.pages');
		
		Assert::type(Pages::class, $pages);
		Assert::type(\Nette\Application\Routers\RouteList::class, $router);
	}
}

(new Test())->run();
