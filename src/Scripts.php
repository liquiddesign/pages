<?php

namespace Pages;

use Pages\DB\PageTemplateRepository;

class Scripts
{
	/**
	 * Trigger as event from composer
	 * @param \Composer\Script\Event $event Composer event
	 */
	public static function createTemplates(\Composer\Script\Event $event): void
	{
		$arguments = $event->getArguments();
		
		$class = $arguments[0] ?? '\App\Bootstrap';
		
		$container = \method_exists($class, 'createContainer') ? $class::createContainer() : $class::boot()->createContainer();
		
		/** @var PageTemplateRepository $templates */
		$templates = $container->getByType(PageTemplateRepository::class);
		
		$templates->updateDatabaseTemplates($arguments);
	}
}
