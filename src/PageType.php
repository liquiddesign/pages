<?php

declare(strict_types=1);

namespace Pages;

use Nette\Application\Helpers;
use Nette\Application\InvalidPresenterException;
use Nette\Application\IPresenterFactory;

class PageType
{
	private string $id;
	
	private string $name;
	
	private string $plink;
	
	/**
	 * @var array<mixed>|null
	 */
	private ?array $requiredParameters = null;
	
	/**
	 * @var array<mixed>|null
	 */
	private ?array $optionalParameters = null;
	
	/**
	 * @var array<mixed>|null
	 */
	private ?array $parameters = null;
	
	private \Nette\Application\IPresenterFactory $presenterFactory;
	
	private ?string $defaultMask;
	
	/**
	 * @var array<string>
	 */
	private array $templateVarNames;
	
	/**
	 * PageType constructor.
	 * @param \Nette\Application\IPresenterFactory $presenterFactory
	 * @param string $id
	 * @param string $name
	 * @param string $plink
	 * @param string|null $defaultMask
	 * @param array<string> $templateVarNames
	 */
	public function __construct(IPresenterFactory $presenterFactory, string $id, string $name, string $plink, ?string $defaultMask, array $templateVarNames)
	{
		$this->id = $id;
		$this->name = $name;
		$this->plink = $plink;
		$this->presenterFactory = $presenterFactory;
		$this->defaultMask = $defaultMask;
		$this->templateVarNames = $templateVarNames;
	}
	
	public function getID(): string
	{
		return $this->id;
	}
	
	public function getName(): string
	{
		return $this->name;
	}
	
	public function getPlink(): string
	{
		return $this->plink;
	}
	
	public function getDefaultMask(): ?string
	{
		return $this->defaultMask;
	}
	
	/**
	 * @param array<mixed>|null $fillParams
	 * @return array<mixed>
	 */
	public function getRequiredParameters(?array $fillParams = null): array
	{
		if ($this->requiredParameters === null) {
			[$this->requiredParameters, $this->optionalParameters] = $this->getActionParameters();
		}
		
		if ($fillParams === null) {
			return $this->requiredParameters;
		}
		
		return $this->fillParameters(\array_keys($this->requiredParameters), $fillParams);
	}
	
	/**
	 * @param array<mixed>|null $fillParams
	 * @return array<mixed>
	 */
	public function getOptionalParameters(?array $fillParams = null): array
	{
		if ($this->optionalParameters === null) {
			[$this->requiredParameters, $this->optionalParameters] = $this->getActionParameters();
		}
		
		if ($fillParams === null) {
			return $this->optionalParameters;
		}
		
		return $this->fillParameters(\array_keys($this->optionalParameters), $fillParams);
	}
	
	/**
	 * @param array<mixed>|null $fillParams
	 * @return array<mixed>
	 */
	public function getParameters(?array $fillParams = null): array
	{
		if ($fillParams === null) {
			if ($this->parameters !== null) {
				return $this->parameters;
			}
			
			return $this->parameters = $this->getRequiredParameters() + $this->getOptionalParameters();
		}
		
		return $this->getRequiredParameters($fillParams) + $this->getOptionalParameters($fillParams);
	}
	
	/**
	 * @return array<string>
	 */
	public function getTemplateVarNames(): array
	{
		return $this->templateVarNames;
	}
	
	/**
	 * @return array<array<string>>
	 */
	private function getActionParameters(): array
	{
		$parameters = [[], []];
		
		try {
			[$presenter, $action] = Helpers::splitName($this->plink);
			
			$presenterClass = $this->presenterFactory->getPresenterClass($presenter);
			$method = \Pages\Helpers::getPresenterMethod($presenterClass, $action);
			
			if (!$method) {
				return $parameters;
			}
			
			$rm = new \ReflectionMethod($presenterClass, $method);
			
			foreach ($rm->getParameters() as $p) {
				$name = $p->getName();
				/** @var \ReflectionNamedType|null $type */
				$type = $p->getType();
				$type = $type ? $type->getName() : $type;
				$p->allowsNull() ? $parameters[1][$name] = $type : $parameters[0][$name] = $type;
			}
		} catch (\ReflectionException $x) {
			return $parameters;
		} catch (InvalidPresenterException $x) {
			return $parameters;
		}
		
		return $parameters;
	}
	
	/**
	 * @param array<mixed> $names
	 * @param array<mixed> $values
	 * @return array<mixed>
	 */
	private function fillParameters(array $names, array $values): array
	{
		$params = [];
		
		foreach ($names as $name) {
			if (!\array_key_exists($name, $values)) {
				continue;
			}
			
			$params[$name] = $values[$name];
		}
		
		return $params;
	}
}
