<?php

declare(strict_types=1);

namespace Pages\Bridges;

use Nette\Application\Helpers;
use Nette\Application\Routers\RouteList;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Routing\Route;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Pages\DB\PageRepository;
use Pages\DB\PageTemplateRepository;
use Pages\DB\RedirectRepository;
use Pages\Redirector;
use Pages\Router;
use StORM\Entity;

/**
 * @property \stdClass $config
 */
class PagesDI extends \Nette\DI\CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'types' => Expect::arrayOf(Expect::structure([
				'name' => Expect::string()->required(true),
				'plink' => Expect::string()->required(true),
				'lang' => Expect::string(),
				'defaultMask' => Expect::string(),
				'prefetch' => Expect::bool(false),
			]))->required(),
			'debugger' => Expect::bool(false),
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
			'templates' => Expect::structure([
				'path' => Expect::array(),
				'templates' => Expect::array(),
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
		
		$pages = $builder->addDefinition($this->prefix('pages'), new ServiceDefinition())->setType(\Pages\Pages::class);
		$pages->addSetup('setMutations', [$mutations]);
		$pages->addSetup('setDefaultMutation', [$defaultMutation]);
		$pages->addSetup('setMapping', [$config['mapping']['methods'], $config['mapping']['class'], $config['mapping']['throw404']]);
		$pages->addSetup('setFilterIn', [$config['filterIn']]);
		$pages->addSetup('setFilterOut', [$config['filterOut']]);
		
		$def = $builder->addDefinition($this->prefix('router'), new ServiceDefinition())->setType(Router::class)->setArgument('mutationParameter', $mutationParameter)->setAutowired(false);
		$builder->addDefinition($this->prefix('pageRepository'), new ServiceDefinition())->setType(PageRepository::class);
		$builder->addDefinition($this->prefix('redirectRepository'), new ServiceDefinition())->setType(RedirectRepository::class);
		$pageTemplateRepository = $builder->addDefinition($this->prefix('pageTemplateRepository'), new ServiceDefinition())->setType(PageTemplateRepository::class);
		
		$pageTemplateRepository->addSetup('setImportTemplates', [
			$config['templates']->templates,
			$config['templates']->path,
		]);
		
		if ($config['redirects'] && $builder->hasDefinition('application.application')) {
			$redirector = $builder->addDefinition($this->prefix('redirector'), new ServiceDefinition())->setType(Redirector::class);
			/** @var \Nette\DI\Definitions\ServiceDefinition $application */
			$application = $builder->getDefinition('application.application');
			$application->addSetup('$onStartup[]', [[$redirector, 'handleRedirect']]);
		}
		
		$serviceName = 'routing.router';
		
		/** @var \Nette\DI\Definitions\ServiceDefinition $routerListDef */
		$routerListDef = $builder->hasDefinition($serviceName) ? $builder->getDefinition($serviceName) : $builder->addDefinition($serviceName, new ServiceDefinition())->setType(RouteList::class);
		
		$routerListDef->addSetup('add', [$def]);
		
		$langMask = '';
		
		if ($defaultMutation && $mutations) {
			$langString = \implode('|', $mutations);
			$langMask = "[<$mutationParameter=$defaultMutation $langString>/]";
		}
		
		foreach ($config['types'] as $id => $pageType) {
			$pageType = (array) $pageType;
			$pages->addSetup('addPageType', [$id, $pageType['name'], $pageType['plink'], $pageType['defaultMask'] ?? null, $pageType['prefetch'], [], $pageType['lang']]);
			
			if (!$config['defaultRoutes'] || !isset($pageType['defaultMask'])) {
				continue;
			}
			
			[$presenter, $action] = Helpers::splitName($pageType['plink']);
			
			$options = ['presenter' => $presenter, 'action' => $action, null => [
				Route::FILTER_OUT => [$pages, 'unmapParameters'],
				Route::FILTER_IN => [$pages, 'mapParameters'],
			]];
			
			if ($pageType['lang']) {
				$langMask = $pageType['lang'] . '/';
				$options['lang'] = $pageType['lang'];
			}
			
			$routerListDef->addSetup('addRoute', [$langMask . $pageType['defaultMask'], $options]);
		}
		
		return;
	}
	
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		
		/** @var \Nette\DI\Definitions\ServiceDefinition $serviceDefinition */
		$serviceDefinition = $builder->getDefinition($this->prefix('pageRepository'));
		
		if ($this->config->debugger ?? $builder->getByType(\Tracy\Bar::class)) {
			$serviceDefinition->addSetup('@Tracy\Bar::addPanel', [
				new \Nette\DI\Definitions\Statement(PagesTracy::class),
			]);
		}
		
		return;
	}
}
