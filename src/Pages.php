<?php

declare(strict_types=1);

namespace Pages;

use Nette\Application\BadRequestException;
use Nette\Application\InvalidPresenterException;
use Nette\Application\IPresenterFactory;
use Nette\Application\UI\Presenter;
use Nette\Utils\Strings;
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
	 * @var array<string>
	 */
	private array $prefetchTypes = [];
	
	/**
	 * @var array<string>
	 */
	private array $mutations = [];
	
	private ?string $defaultMutation = null;
	
	/**
	 * @var array<\Pages\PageType>
	 */
	private array $pageTypes = [];
	
	/**
	 * @var array<string>
	 */
	private array $plinkMap = [];
	
	private \Nette\Application\IPresenterFactory $presenterFactory;
	
	private ?\Pages\DB\IPage $page = null;
	
	private ?\StORM\DIConnection $connection;
	
	/**
	 * @var array<callable>
	 */
	private array $mappingMethods;
	
	private string $mappingClass;
	
	private bool $mappingThrow404;
	
	/**
	 * @var array<array<string>>
	 */
	private array $cachedParams;
	
	/**
	 * @var array<mixed>|null
	 */
	private ?array $filterInCallback;
	
	/**
	 * @var array<mixed>|null
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
	 * @param array<mixed>|null $filterInCallback
	 */
	public function setFilterIn(?array $filterInCallback): void
	{
		$this->filterInCallback = $filterInCallback;
	}
	
	/**
	 * @return array<mixed>|null
	 */
	public function getFilterInCallback(): ?array
	{
		return $this->filterInCallback;
	}
	
	/**
	 * @return array<mixed>|null
	 */
	public function getFilterOutCallback(): ?array
	{
		return $this->filterOutCallback;
	}
	
	/**
	 * @param array<mixed>|null $filterOutCallback
	 */
	public function setFilterOut(?array $filterOutCallback): void
	{
		$this->filterOutCallback = $filterOutCallback;
	}
	
	/**
	 * @param array<array<mixed>> $callbacks
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
	 * @param array<string> $mutations
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
	 * @return array<string>
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
	 * @param array<string> $templateVars
	 */
	public function addPageType(string $id, string $name, string $plink, ?string $defaultMask = null, bool $prefetch = false, array $templateVars = [], ?string $mutation = null): void
	{
		if (isset($this->pageTypes[$id]) || isset($this->plinkMap[$plink . ($mutation ?? '')])) {
			throw new \Nette\DI\InvalidConfigurationException("Duplicate 'ID' ($id) or 'plink' ($plink) pageType: $id");
		}
		
		if ($prefetch) {
			$this->prefetchTypes[] = $id;
		}
		
		$this->pageTypes[$id] = new PageType($this->presenterFactory, $id, $name, $plink, $defaultMask, $templateVars);
		
		$this->plinkMap[$plink . ($mutation ?? '')] = $id;
	}
	
	/**
	 * @return array<\Pages\PageType>
	 */
	public function getPageTypes(): array
	{
		return $this->pageTypes;
	}
	
	/**
	 * @return array<string>
	 */
	public function getPrefetchTypes(): array
	{
		return $this->prefetchTypes;
	}
	
	public function getTypeByPlink(string $plink, ?string $mutation = null): ?PageType
	{
		if (isset($this->plinkMap[$plink . ($mutation ?? '')])) {
			return $this->pageTypes[$this->plinkMap[$plink . ($mutation ?? '')]] ?? null;
		}
		
		return null;
	}
	
	public function getPageType(string $id): ?PageType
	{
		return $this->pageTypes[$id] ?? null;
	}
	
	/**
	 * @param array<mixed> $params
	 * @return array<mixed>
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
				$params[$name] = $pageItself ? $this->page : $this->callMapMethod(Strings::lower($module), $repository, (string) $params[$name]);
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
	 * @param array<mixed> $params
	 * @return array<mixed>
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
	 * @return array<string>
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
				/** @var \ReflectionNamedType|null $type */
				$type = $p->getType();
				
				if (!$type || !\is_subclass_of($type->getName(), $this->mappingClass)) {
					continue;
				}
				
				$name = $p->getName();
				$this->cachedParams[$key][$name] = $type->getName();
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
