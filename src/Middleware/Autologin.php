<?php

namespace SingleSO\Auth\Middleware;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Foundation\Application;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SingleSO\Auth\SingleSO;
use Zend\Diactoros\Response\RedirectResponse;
use Zend\Stratigility\MiddlewareInterface;

class Autologin implements MiddlewareInterface {

	/**
	 * @var Application
	 */
	protected $app;

	/**
	 * @var SettingsRepositoryInterface
	 */
	protected $settings;

	/**
	 * @param Application $app
	 * @param SettingsRepositoryInterface $settings
	 */
	public function __construct(
		Application $app,
		SettingsRepositoryInterface $settings
	) {
		$this->app = $app;
		$this->settings = $settings;
	}

	/**
	 * {@inheritdoc}
	 */
	public function __invoke(
		Request $request,
		Response $response,
		callable $out = null
	) {
		do {
			// Check if a guest.
			$actor = $request->getAttribute('actor');
			if (!$actor->isGuest()) {
				break;
			}

			// Check for the global cookie setting.
			$authSettings = SingleSO::settingsAuth($this->settings, false);
			if (!$authSettings) {
				break;
			}

			// Check if the cookie is configured.
			$globalCookie = $authSettings['global_cookie'];
			if (!$globalCookie) {
				break;
			}

			// Check if that cookie is set.
			$cookies = $request->getCookieParams();
			if (!isset($cookies[$globalCookie])) {
				break;
			}

			// Get current request path.
			// And URL hash is unfortunately unavailable.
			// Such data will be discarded on auto-login.
			$requestUri = $request->getUri();
			$requestPath = $requestUri->getPath();

			// Ignore if the controller path, avoid infinite redirect.
			if (strpos($requestPath, SingleSO::CONTROLLER_PATH) === 0) {
				break;
			}

			// Get any query parameters.
			$query = $requestUri->getQuery();

			// Create the redirect path, preserve ? even if no query.
			$params = $request->getQueryParams();
			$redirect = $requestPath . (
				$query ?
					'?' . $query :
					(
						isset($_SERVER['REQUEST_URI']) &&
						strpos($_SERVER['REQUEST_URI'], '?') !== false ?
							'?' : ''
					)
			);

			// Create the login path.
			$loginPath = rtrim($this->app->url(), '/') .
				SingleSO::CONTROLLER_PATH . '/login';

			// Create the redirect target, include return redirect parameters.
			$target = SingleSO::addParams(
				$loginPath,
				['redirect' => $redirect]
			);

			// Take over the response, redirect to login URL.
			return new RedirectResponse($target);
		} while (false);

		return $out ? $out($request, $response) : $response;
	}
}
