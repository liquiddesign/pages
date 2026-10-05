<?php

declare(strict_types=1);

namespace Pages;

use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use Pages\DB\IRedirectRepository;
use Pages\DB\Redirect;

class Redirector
{
	private \Pages\DB\IRedirectRepository $redirectRepository;
	
	private \Nette\Http\Response $httpResponse;
	
	private \Nette\Http\Request $httpRequest;
	
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
		
		$pageUrl = (string) Strings::substring($url->getPath(), Strings::length($url->getBasePath()));
		$lang = \strtok($pageUrl, '/');
		$pageUrl = Strings::lower($pageUrl);
		$query = $url->getQuery();
		
		if (!Arrays::contains($this->pages->getMutations(), $lang) || $lang === $this->pages->getDefaultMutation()) {
			$lang = $this->pages->getDefaultMutation();
		}
		
		// A redirect whose source carries the query string (`list?page=4`) wins, as before.
		$redirect = $query !== '' ? $this->redirectRepository->getRedirect($pageUrl . '?' . Strings::lower($query), $lang) : null;
		$passedQuery = [];
		
		// Parameters a link picks up on the way (`utm_*`, `fbclid`, `gclid`) must not bypass a redirect set
		// for the path. They were not part of the match, so they travel on to the target.
		if (!$redirect) {
			$redirect = $this->redirectRepository->getRedirect($pageUrl, $lang);
			$passedQuery = $this->httpRequest->getQuery();
		}

		if ($redirect) {
			Arrays::invoke($application->onShutdown, $application);
			$this->httpResponse->redirect($this->generateRedirectUrl($redirect, $this->httpRequest, $this->pages->getDefaultMutation(), $passedQuery), 301);

			exit;
		}
		
		return;
	}
	
	/**
	 * Only `http://` and `https://` count as absolute; anything else (`javascript:`, `//host`, a path)
	 * stays a path on the current domain, as before.
	 */
	public static function isAbsoluteUrl(string $url): bool
	{
		return Strings::match($url, '~^https?://[^/\s]~i') !== null;
	}
	
	/**
	 * @param array<mixed> $query parameters of the request passed on to the target
	 */
	private function generateRedirectUrl(Redirect $redirect, \Nette\Http\IRequest $request, ?string $defaultMutation, array $query = []): string
	{
		// An absolute target (another domain or subdomain) is sent as it is. Gluing it to the path of the
		// current host produced e.g. `https://www.abel.cz/https://np.abel.cz`.
		if (self::isAbsoluteUrl($redirect->toUrl)) {
			if (!$query) {
				return $redirect->toUrl;
			}
			
			$redirectUrl = new \Nette\Http\Url($redirect->toUrl);
			$redirectUrl->appendQuery($query);
			
			return (string) $redirectUrl;
		}
		
		$toMutation = $redirect->toMutation ?: $redirect->fromMutation;
		$toUrl = $redirect->toUrl === '/' ? '' : $redirect->toUrl;
		$url = $request->getUrl();
		$path = $url->getBasePath() . ($toMutation !== null && $toMutation !== $defaultMutation ? ($toUrl ? "$toMutation/" : $toMutation) : '') . $toUrl;
		
		$redirectUrl = new \Nette\Http\Url();
		$redirectUrl->setScheme($url->getScheme());
		$redirectUrl->setHost($url->getAuthority());
		$redirectUrl->setPath($path);
		$redirectUrl->setFragment($url->getFragment());
		$redirectUrl->setQuery($query);
		
		return (string) $redirectUrl;
	}
}
