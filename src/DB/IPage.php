<?php

declare(strict_types=1);

namespace Pages\DB;

interface IPage
{
	public function getID(): string;
	
	public function isAvailable(?string $lang): bool;
	
	public function getParameters(): string;
	
	/**
	 * @return string[]|null[]
	 */
	public function getParsedParameters(): array;

	public function getParsedParameter(string $name): ?string;
	
	/**
	 * @return mixed[]
	 */
	public function getPropertyParameters(): array;
	
	public function getType(): string;
	
	/**
	 * @param mixed[] $vars
	 * @param string[]|null $validateNames
	 */
	public function setTemplateVars(array $vars, ?array $validateNames): void;
	
	public function getUrl(?string $lang): ?string;
	
	public function getTitle(?string $lang): ?string;
	
	public function getDescription(?string $lang): ?string;
}
