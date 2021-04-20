<?php

declare(strict_types=1);

namespace Pages\Bridges;

use Nette\Application\Helpers;
use Nette\Routing\Route;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Pages\DB\PageRepository;
use Pages\DB\RedirectRepository;
use Pages\DB\SitemapRepository;
use Pages\Redirector;
use Pages\Router;
use StORM\Entity;

class PagesDI extends \Nette\DI\CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'types' => Expect::arrayOf(Expect::structure([
				'name' => Expect::string()->required(true),
				'plink' => Expect::string()->required(true),
				'defaultMask' => Expect::string(),
				'templateVars' => Expect::arrayOf('string'),
			]))->required(),
			'defaultRoutes' => Expect::bool(true),
			'defaultMutation' => Expect::string(null)->min(2)->max(2),
			'mutations' => Expect::arrayOf('string|array'),
			'filterIn' => Expect::array(null),
			'filterOut' => Expect::array(null),
			'redirects' => Expect::bool(true),
			'mutationParameter' => Expect::string('lang'),
			'mapping' => Expect::structure([
				'methods' => Expect::array(['*' => ['one', ['?', true]]]),
				'class' => Expect::string(Entity::class),
				'throw404' => Expect::bool(false),
			]),
		]);
	}
	
	public function loadConfiguration(): void
	{
		$config = (array) $this->getConfig();
		
		$defaultMutation = $config['defaultMutation'] ?: ($config['mutations'] ? \reset($config['mutations']) : null);
		$mutations = $config['mutations'];
		$mutationParameter = $config['mutationParameter'];
		
		$config['mapping'] = (array) $config['mapping'];
		
		/** @var \Nette\DI\ContainerBuilder $builder */
		$builder = $this->getContainerBuilder();
		
		$pages = $builder->addDefinition($this->prefix('pages'))->setType(\Pages\Pages::class);
		$pages->addSetup('setMutations', [$mutations]);
		$pages->addSetup('setDefaultMutation', [$defaultMutation]);
		$pages->addSetup('setMapping', [$config['mapping']['methods'], $config['mapping']['class'], $config['mapping']['throw404']]);
		$pages->addSetup('setFilterIn', [$config['filterIn']]);
		$pages->addSetup('setFilterOut', [$config['filterOut']]);
		
		
		$def = $builder->addDefinition($this->prefix('router'))->setType(Router::class)->setArgument('mutationParameter', $mutationParameter)->setAutowired(false);
		$builder->addDefinition($this->prefix('pageRepository'))->setType(PageRepository::class);
		$builder->addDefinition($this->prefix('redirectRepository'))->setType(RedirectRepository::class);
		
		if ($config['redirects'] && $builder->hasDefinition('application.application')) {
			$redirector = $builder->addDefinition($this->prefix('redirector'))->setType(Redirector::class);
			/** @var \Nette\DI\Definitions\ServiceDefinition $application */
			$application = $builder->getDefinition('application.application');
			$application->addSetup('$onStartup[]', [[$redirector, 'handleRedirect']]);
		}
		
		if ($builder->hasDefinition('routing.router')) {
			/** @var \Nette\DI\Definitions\ServiceDefinition $routerListDef */
			$routerListDef = $builder->getDefinition('routing.router');
		} else {
			/** @var \Nette\DI\Definitions\ServiceDefinition $routerListDef */
			$routerListDef = $builder->addDefinition('routing.router')->setType(\Nette\Application\Routers\RouteList::class);
		}
		
		$routerListDef->addSetup('add', [$def]);
		
		$langMask = '';
		
		if ($defaultMutation && $mutations) {
			$langString = \implode('|', $mutations);
			$langMask = "[<$mutationParameter=$defaultMutation $langString>/]";
		}
		
		foreach ($config['types'] as $id => $pageType) {
			$pageType = (array) $pageType;
			$pages->addSetup('addPageType', [$id, $pageType['name'], $pageType['plink'], $pageType['defaultMask'] ?? null]);
			
			if (!$config['defaultRoutes'] || !isset($pageType['defaultMask'])) {
				continue;
			}
			
			[$presenter, $action] = Helpers::splitName($pageType['plink']);
			$routerListDef->addSetup('addRoute', [$langMask . $pageType['defaultMask'], ['presenter' => $presenter, 'action' => $action, null => [
				Route::FILTER_OUT => [$pages, 'unmapParameters'],
				Route::FILTER_IN => [$pages, 'mapParameters'],
			]]]);
		}
		
		return;
	}
	
	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();
		
		$builder->getDefinition($this->prefix('pageRepository'))->addSetup('@Tracy\Bar::addPanel', [
			new \Nette\DI\Definitions\Statement(PagesTracy::class),
		]);
	}
}
