<?php

declare(strict_types=1);

namespace Pages\DB;

use Latte\Loaders\StringLoader;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\TemplateFactory;
use Nette\Http\Request;
use Nette\Utils\FileSystem;
use StORM\Collection;
use StORM\DIConnection;
use StORM\SchemaManager;

/**
 * Class PageRepository
 * @extends \StORM\Repository<\Pages\DB\PageTemplate>
 */
class PageTemplateRepository extends \StORM\Repository implements IPageTemplateRepository
{
	private ?TemplateFactory $templateFactory;

	private LinkGenerator $linkGenerator;
	
	/**
	 * @var string[]
	 */
	private array $importTemplates = [];
	
	/**
	 * @var string[]
	 */
	private array $importPath = [];

	private string $baseUrl;

	public function __construct(
		DIConnection $connection,
		SchemaManager $schemaManager,
		Request $request,
		LinkGenerator $linkGenerator,
		?TemplateFactory $templateFactory = null
	) {
		parent::__construct($connection, $schemaManager);

		$this->templateFactory = $templateFactory;
		$this->linkGenerator = $linkGenerator;

		$this->baseUrl = $request->getUrl()->getBaseUrl();
	}
	
	/**
	 * @param bool $includeHidden
	 * @param string|null $type
	 * @return string[]
	 */
	public function getArrayForSelect(bool $includeHidden = true, ?string $type = null): array
	{
		$collection = $this->getCollection($includeHidden);

		if ($type) {
			$collection->where('type', $type);
		}

		return $collection->toArrayOf('name');
	}

	public function getCollection(bool $includeHidden = false): Collection
	{
		unset($includeHidden);
		
		$collection = $this->many();

		return $collection->orderBy(['name']);
	}

	public function setImportTemplates(array $templates, array $path): void
	{
		$this->importPath = $path;
		$this->importTemplates = $templates;
	}

	public function updateDatabaseTemplates(array $params = []): void
	{
		$template = $this->createTemplate();

		$parsedPath = \explode(\DIRECTORY_SEPARATOR, __DIR__);
		$rootLevel = \count($parsedPath) - \array_search('src', $parsedPath);

		if (\file_exists(\dirname(__DIR__, $rootLevel) . '/vendor/autoload.php')) {
			require \dirname(__DIR__, $rootLevel) . '/vendor/autoload.php';
		} else {
			$rootLevel = \count($parsedPath) - \array_search('vendor', $parsedPath);
		}

		$path = \dirname(__DIR__, $rootLevel) . \DIRECTORY_SEPARATOR;

		foreach (\array_keys($this->importPath) as $key) {
			$path .= $key;
			$path .= \DIRECTORY_SEPARATOR;
		}

		foreach ($this->importTemplates as $item) {
			$pageTemplate = new \ArrayObject([], \ArrayObject::ARRAY_AS_PROPS);
			$fileContent = FileSystem::read($path . $item . '.latte');
			$template->render($fileContent, $params + ['pageTemplate' => $pageTemplate, 'baseUrl' => $this->baseUrl]);

			/** @phpstan-ignore-next-line */
			$item = $this->one($pageTemplate->uuid);

			if ($item === null) {
				$this->createOne($pageTemplate->getArrayCopy());
			} else {
				$item->update($pageTemplate->getArrayCopy());
			}
		}
	}

	private function createTemplate(): \Nette\Bridges\ApplicationLatte\Template
	{
		/** @var \Nette\Bridges\ApplicationLatte\Template $template */
		$template = $this->templateFactory->createTemplate();
		$template->getLatte()->addProvider('uiControl', $this->linkGenerator);
		$template->getLatte()->setLoader(new StringLoader());

		return $template;
	}
}
