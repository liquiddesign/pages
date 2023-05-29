<?php

declare(strict_types=1);

namespace Pages\DB;

use Base\Entity\ShopEntity;
use Nette\Application\ApplicationException;
use Pages\Pages;

/**
 * @table
 * @index{"name":"type_params","unique":true,"columns":["type","params"]}
 * @index{"name":"page_url_shop","unique":true,"columns":["url","fk_shop"]}
 */
class Page extends ShopEntity implements IPage
{
	public const IMAGE_DIR = 'page';
	
	/**
	 * Page url
	 * @column{"type":"varchar","mutations":true,"nullable":true}
	 */
	public ?string $url = null;
	
	/**
	 * Page type
	 * @column
	 */
	public string $type;
	
	/**
	 * @column
	 */
	public bool $isOffline = false;
	
	/**
	 * Parameters in name1=value1&name2=value2
	 * @column
	 */
	public string $params;
	
	/**
	 * Title
	 * @column{"mutations":true}
	 */
	public ?string $title = null;
	
	/**
	 * Description
	 * @column{"mutations":true}
	 */
	public ?string $description = null;
	
	/**
	 * Robots
	 * @column
	 */
	public ?string $robots = null;
	
	/**
	 * Rel canonical
	 * @column{"mutations":true}
	 */
	public ?string $canonicalUrl = null;
	
	/**
	 * @var array<string>
	 */
	private array $templateVars = [];
	
	public function getID(): string
	{
		return $this->getPK();
	}
	
	public function isAvailable(?string $lang): bool
	{
		return !$this->isOffline && $this->getValue('url', $lang) !== null;
	}
	
	public function getType(): string
	{
		return $this->type;
	}
	
	public function getParameters(): string
	{
		return $this->params;
	}
	
	/**
	 * @return array<string>|array<null>
	 */
	public function getParsedParameters(): array
	{
		$output = [];
		\parse_str($this->params, $output);
		
		return $output;
	}
	
	/**
	 * @return array<mixed>
	 */
	public function getPropertyParameters(): array
	{
		$properties = [];
		
		foreach ($this->getStructure()->getRelations() as $name => $relation) {
			if ($relation->isKeyHolder() && $value = $this->getValue($name)) {
				$properties[$name] = $value;
			}
		}
		
		return $properties;
	}
	
	public function getParsedParameter(string $name): ?string
	{
		$parameters = $this->getParsedParameters();
		
		return isset($parameters[$name]) && $parameters[$name] !== null ? $parameters[$name] : null;
	}
	
	public function getUrl(?string $lang): ?string
	{
		return $this->getValue('url', $lang);
	}
	
	/**
	 * @param array<mixed> $vars
	 * @param array<string>|null $validateNames
	 * @throws \Nette\Application\ApplicationException
	 */
	public function setTemplateVars(array $vars, ?array $validateNames): void
	{
		if ($validateNames) {
			foreach ($validateNames as $name) {
				if (!\array_key_exists($name, $vars)) {
					throw new ApplicationException("Template parameter '$name' not set");
				}
			}
		}
		
		$this->templateVars = $vars;
	}
	
	public function getTitle(?string $lang): ?string
	{
		$title = $this->getValue('title', $lang);
		
		foreach ($this->templateVars as $name => $value) {
			$title = \str_replace(Pages::VAR_CHAR . $name . Pages::VAR_CHAR, $value, $title);
		}
		
		return $title;
	}
	
	public function getDescription(?string $lang): ?string
	{
		$title = $this->getValue('description', $lang);
		
		foreach ($this->templateVars as $name => $value) {
			$title = \str_replace(Pages::VAR_CHAR . $name . Pages::VAR_CHAR, $value, $title);
		}
		
		return $title;
	}
}
