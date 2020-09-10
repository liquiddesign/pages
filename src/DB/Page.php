<?php

declare(strict_types=1);

namespace Pages\DB;

use Nette\Application\ApplicationException;
use Pages\Pages;

/**
 * @table
 */
class Page extends \StORM\Entity implements IPage
{
	/**
	 * Page url
	 * @column{"type":"varchar","unique":true,"mutations":true,"nullable":true}
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
	 * @column{"length":512}
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
	 * @relation
	 * @constraint
	 */
	public ?\Pages\DB\Sitemap $sitemap = null;
	
	/**
	 * @var string[]
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
	 * @return string[]
	 */
	public function getParsedParameters(): array
	{
		$output = [];
		\parse_str($this->params, $output);
		
		return $output;
	}
	
	public function getParsedParameter(string $name): ?string
	{
		$parameters = $this->getParsedParameters();
		
		return (string) $parameters[$name] ?? null;
	}
	
	public function getUrl(?string $lang): ?string
	{
		return $this->getValue('url', $lang);
	}
	
	/**
	 * @param mixed[] $vars
	 * @param string[]|null $validateNames
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
