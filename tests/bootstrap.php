<?php // @codingStandardsIgnoreLine

use Tracy\Debugger;

\define('TEMP_DIR', __DIR__ . '/temp/' . \getmypid());
\define('CONFIGS_DIR', __DIR__ . '/configs');
\define('ENTITIES_DIR', __DIR__ . '/DB');

@\mkdir(\dirname(\TEMP_DIR));
@\mkdir(\TEMP_DIR);

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @param string $config
 * @param string $tempDir
 * @param string[] $extensions
 * @param string[] $loadDefinitions
 * @return \Nette\DI\Container
 */
function createContainer(string $config, array $extensions = [], array $loadDefinitions = []): \Nette\DI\Container
{
	$loader = new \Nette\DI\ContainerLoader(\TEMP_DIR, true);
	
	$class = $loader->load(static function (\Nette\DI\Compiler $compiler) use ($config, $extensions, $loadDefinitions): void {
		$compiler->loadConfig($config);
		
		foreach ($loadDefinitions as $name => $class) {
			$compiler->loadDefinitionsFromConfig([$name => $class]);
		}
		
		foreach ($extensions as $name => $class) {
			$compiler->addExtension($name, new $class());
		}
	});
	
	return new $class();
}

foreach (\glob(\ENTITIES_DIR . '/*.php') as $file) {
	require_once $file;
}

Debugger::enable();
Tester\Environment::setup();
Tester\Helpers::purge(\TEMP_DIR);
