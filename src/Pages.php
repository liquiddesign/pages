<?php

declare(strict_types=1);

namespace Pages;

use Nette\Application\BadRequestException;
use Nette\Application\InvalidPresenterException;
use Nette\Application\IPresenterFactory;
use Nette\Application\UI\Presenter;
use Pages\DB\IPage;
use StORM\DIConnection;
use StORM\Entity;
use StORM\Exception\NotFoundException;
use StORM\Repository;

class Pages
{
	public const VAR_CHAR = '%';
	
	private const MAPPING_PARAMETER_WILDCARD = '?';
	private const MAPPING_MODULE_WILDCARD = '*';
	
	/**
	 * @var string[]
	 */
	private array $prefetchTypes = [];
	
	/**
	 * @var string[]
	 */
	private array $mutations = [];
	
	private ?string $defaultMutation = null;
	
	/**
	 * @var \Pages\PageType[]
	 */
	private array $pageTypes = [];
	
	/**
	 * @var string[]
	 */
	private array $plinkMap = [];
	
	private \Nette\Application\IPresenterFactory $presenterFactory;
	
	private ?\Pages\DB\IPage $page = null;
	
	private ?\StORM\DIConnection $connection;
	
	/**
	 * @var callable[]
	 */
	private array $mappingMethods;
	
	private string $mappingClass;
	
	private bool $mappingThrow404;
	
	/**
	 * @var string[][]
	 */
	private array $cachedParams;
	
	/**
	 * @var mixed[]|null
	 */
	private ?array $filterInCallback;
	
	/**
	 * @var mixed[]|null
	 */
	private ?array $filterOutCallback;
	
	public function __construct(DIConnection $connection, IPresenterFactory $presenterFactory)
	{
		$this->presenterFactory = $presenterFactory;
		$this->connection = $connection;
	}
	
	public function getPage(): ?IPage
	{
		return $this->page;
	}
	
	public function setPage(IPage $page): void
	{
		$this->page = $page;
	}
	
	/**
	 * @param mixed[]|null $filterInCallback
	 */
	public function setFilterIn(?array $filterInCallback): void
	{
		$this->filterInCallback = $filterInCallback;
	}
	
	/**
	 * @return mixed[]|null
	 */
	public function getFilterInCallback(): ?array
	{
		return $this->filterInCallback;
	}
	
	/**
	 * @return mixed[]|null
	 */
	public function getFilterOutCallback(): ?array
	{
		return $this->filterOutCallback;
	}
	
	/**
	 * @param mixed[]|null $filterOutCallback
	 */
	public function setFilterOut(?array $filterOutCallback): void
	{
		$this->filterOutCallback = $filterOutCallback;
	}
	
	/**
	 * @param mixed[][] $callbacks
	 * @param string $class
	 * @param bool $throw404
	 */
	public function setMapping(array $callbacks, string $class, bool $throw404): void
	{
		$this->mappingMethods = $callbacks;
		$this->mappingClass = $class;
		$this->mappingThrow404 = $throw404;
	}
	
	/**
	 * @param string[] $mutations
	 */
	public function setMutations(array $mutations): void
	{
		$this->mutations = $mutations;
	}
	
	public function setDefaultMutation(?string $mutation): void
	{
		$this->defaultMutation = $mutation;
	}
	
	/**
	 * @return string[]
	 */
	public function getMutations(): array
	{
		return $this->mutations;
	}
	
	public function getDefaultMutation(): ?string
	{
		return $this->defaultMutation;
	}
	
	/**
	 * @param string $id
	 * @param string $name
	 * @param string $plink
	 * @param string|null $defaultMask
	 * @param string[] $templateVars
	 */
	public function addPageType(string $id, string $name, string $plink, ?string $defaultMask = null, bool $prefetch = false, array $templateVars = []): void
	{
		if (isset($this->pageTypes[$id]) || isset($this->plinkMap[$plink])) {
			throw new \Nette\DI\InvalidConfigurationException("Duplicate 'ID' ($id) or 'plink' ($plink) pageType: $id");
		}
		
		if ($prefetch) {
			$this->prefetchTypes[] = $id;
		}
		
		$this->pageTypes[$id] = new PageType($this->presenterFactory, $id, $name, $plink, $defaultMask, $templateVars);
		
		$this->plinkMap[$plink] = $id;
	}
	
	/**
	 * @return \Pages\PageType[]
	 */
	public function getPageTypes(): array
	{
		return $this->pageTypes;
	}
	
	/**
	 * @return string[]
	 */
	public function getPrefetchTypes(): array
	{
		return $this->prefetchTypes;
	}
	
	public function getTypeByPlink(string $plink): ?PageType
	{
		if (isset($this->plinkMap[$plink])) {
			return $this->pageTypes[$this->plinkMap[$plink]] ?? null;
		}
		
		return null;
	}
	
	public function getPageType(string $id): ?PageType
	{
		return $this->pageTypes[$id] ?? null;
	}
	
	/**
	 * @param mixed[] $params
	 * @return mixed[]
	 * @throws \Nette\Application\BadRequestException
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function mapParameters(array $params): array
	{
		try {
			$presenter = \Pages\Helpers::getFullPresenterName($params);
			$module = \Pages\Helpers::getModuleName($params);
			
			foreach ($this->getParametersOfEntityType($presenter, $params[Presenter::ACTION_KEY]) as $name => $class) {
				if (!isset($params[$name])) {
					continue;
				}
				
				$repository = $this->connection->findRepository($class);
				// test if params is actually page itself
				$pageItself = $this->page && \get_class($this->page) === $class && $params[$name] === $this->page->getID();
				$params[$name] = $pageItself ? $this->page : $this->callMapMethod(\strtolower($module), $repository, (string)$params[$name]);
			}
		} catch (NotFoundException $exception) {
			if ($this->mappingThrow404) {
				throw new BadRequestException("Entity '$class' ID '$params[$name]' not found");
			}
			
			throw $exception;
		} catch (InvalidPresenterException $exception) {
			if ($this->mappingThrow404) {
				throw new BadRequestException("Presenter '$presenter' not  found");
			}
			
			throw $exception;
		}
		
		return $params;
	}
	
	/**
	 * @param mixed[] $params
	 * @return mixed[]
	 */
	public function unmapParameters(array $params): array
	{
		foreach (\array_keys($this->getParametersOfEntityType(\Pages\Helpers::getFullPresenterName($params), $params[Presenter::ACTION_KEY])) as $name) {
			if (!isset($params[$name])) {
				continue;
			}
			
			$params[$name] = (string) $params[$name];
		}
		
		return $params;
	}
	
	/**
	 * @param string $presenter
	 * @param string $action
	 * @return string[]
	 * @throws \Nette\Application\InvalidPresenterException
	 * @throws \ReflectionException
	 */
	private function getParametersOfEntityType(string $presenter, string $action): array
	{
		$key = "$presenter:$action";
		
		if (!isset($this->cachedParams[$key])) {
			$this->cachedParams[$key] = [];
			
			$presenterClass = $this->presenterFactory->getPresenterClass($presenter);
			$method = \Pages\Helpers::getPresenterMethod($presenterClass, $action);
			
			if (!$method) {
				return [];
			}
			
			$rm = new \ReflectionMethod($presenterClass, $method);
			
			foreach ($rm->getParameters() as $p) {
				if (!$p->getType() || !\is_subclass_of($p->getType()->getName(), $this->mappingClass)) {
					continue;
				}
				
				$name = $p->getName();
				$this->cachedParams[$key][$name] = $p->getType()->getName();
			}
		}
		
		return $this->cachedParams[$key];
	}
	
	/**
	 * @param string $module
	 * @param \StORM\Repository $repository
	 * @param string $parameter
	 * @throws \StORM\Exception\NotFoundException
	 */
	private function callMapMethod(string $module, Repository $repository, string $parameter): Entity
	{
		$wildcard = self::MAPPING_PARAMETER_WILDCARD;
		$callback = $this->mappingMethods[self::MAPPING_MODULE_WILDCARD];
		
		if ($module && isset($this->mappingMethods[$module])) {
			$callback = $this->mappingMethods[$module];
		}
		
		if (isset($callback[1]) && \is_array($callback[1])) {
			$callback[1] = \array_map(static function ($value) use ($wildcard, $parameter) {
				return $value === $wildcard ? $parameter : $value;
			}, $callback[1]);
		}
		
		return \call_user_func_array([$repository, $callback[0]], $callback[1] ?? []);
	}
}
