<?php

declare(strict_types=1);

namespace Pages;

use Nette;
use Nette\Application\UI\Presenter;

final class Helpers
{
	use Nette\StaticClass;
	
	public const QUERY_SEPARATOR = '&';
	private const MODULE_KEY = 'module';
	
	/**
	 * @param mixed[] $params
	 */
	public static function getFullPresenterName(array $params): string
	{
		return (isset($params[self::MODULE_KEY]) ? $params[self::MODULE_KEY] . ':' : '') . $params[Presenter::PRESENTER_KEY];
	}
	
	/**
	 * @param mixed[] $params
	 */
	public static function getModuleName(array $params): string
	{
		return isset($params[self::MODULE_KEY]) ? \Nette\Application\Helpers::splitName($params[self::MODULE_KEY])[1] : \Nette\Application\Helpers::splitName($params[Presenter::PRESENTER_KEY])[0];
	}
	
	/**
	 * @param mixed[] $parameters
	 */
	public static function serializeParameters(array $parameters): string
	{
		foreach ($parameters as $name => $value) {
			$parameters[$name] = (string) $value;
		}
		
		if (\count($parameters) > 1) {
			\ksort($parameters);
		}
		
		return \http_build_query($parameters) . ($parameters ? self::QUERY_SEPARATOR : '');
	}
	
	public static function getPresenterMethod(string $presenterClass, string $actionName): ?string
	{
		$actionMethod = Presenter::formatActionMethod(\ucfirst($actionName));
		$renderMethod = Presenter::formatRenderMethod(\ucfirst($actionName));
		
		return \method_exists($presenterClass, $actionMethod) ? $actionMethod : (\method_exists($presenterClass, $renderMethod) ? $renderMethod : null);
	}
}
