<?php

declare(strict_types=1);

namespace Pages;

use Nette\Http\Request;
use Nette\Http\Response;
use Pages\DB\IRedirectRepository;
use Pages\DB\Redirect;

class Redirector
{
	private \Pages\DB\IRedirectRepository $redirectRepository;
	
	private \Nette\Http\Response $httpResponse;
	
	private \Nette\Http\Request $httpRequest;
	
	/**
	 * @var bool[]
	 */
	private array $redirectCache;
	
	private \Pages\Pages $pages;
	
	public function __construct(Pages $pages, Response $httpResponse, Request $httpRequest, IRedirectRepository $redirectRepository)
	{
		$this->httpResponse = $httpResponse;
		$this->httpRequest = $httpRequest;
		$this->redirectRepository = $redirectRepository;
		$this->pages = $pages;
	}
	
	public function handleRedirect(\Nette\Application\Application $application): void
	{
		$url = $this->httpRequest->getUrl();
		
		$pageUrl = (string) \substr($url->getPath(), \strlen($url->getBasePath()));
		$lang = \strtok($pageUrl, '/');
		
		if (!\in_array($lang, $this->pages->getMutations()) || $lang === $this->pages->getDefaultMutation()) {
			$lang = $this->pages->getDefaultMutation();
		}
		
		if (!isset($this->redirectCache["$lang-$pageUrl"]) && $redirect = $this->redirectRepository->getRedirect($pageUrl, $lang)) {
			// @phpstan-ignore-next-line
			$application->onShutdown($application);
			$this->httpResponse->redirect($this->generateRedirectUrl($redirect, $this->httpRequest, $this->pages->getDefaultMutation()));
			exit;
		}
		
		return;
	}
	
	private function generateRedirectUrl(Redirect $redirect, \Nette\Http\IRequest $request, ?string $defaultMutation): string
	{
		$toMutation = $redirect->toMutation ?: $redirect->fromMutation;
		$toUrl = $redirect->toUrl === '/' ? '' : $redirect->toUrl;
		$url = $request->getUrl();
		$path = $url->getBasePath() . ($toMutation !== null && $toMutation !== $defaultMutation ? ($toUrl ? "$toMutation/" : $toMutation) : '') . $toUrl;
		
		$redirectUrl = new \Nette\Http\Url();
		$redirectUrl->setScheme($url->getScheme());
		$redirectUrl->setHost($url->getAuthority());
		$redirectUrl->setPath($path);
		$redirectUrl->appendQuery($request->getQuery());
		$redirectUrl->setFragment($url->getFragment());
		
		return (string) $redirectUrl;
	}
}
