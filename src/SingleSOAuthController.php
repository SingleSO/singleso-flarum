<?php

namespace SingleSO\Auth;

use InvalidArgumentException;
use Flarum\Core\Repository\UserRepository;
use Flarum\Core\User;
use Flarum\Event\UserLoggedIn;
use Flarum\Event\UserLoggedOut;
use Flarum\Forum\UrlGenerator;
use Flarum\Foundation\Application;
use Flarum\Http\AccessToken;
use Flarum\Http\Controller\ControllerInterface;
use Flarum\Http\Rememberer;
use Flarum\Http\SessionAuthenticator;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Psr\Http\Message\ServerRequestInterface as Request;
use SingleSO\Auth\SingleSO;
use SingleSO\Auth\SingleSOException;
use SingleSO\Auth\Response\JsonpResponse;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response\RedirectResponse;

class SingleSOAuthController implements ControllerInterface {

	/**
	 * @var Application
	 */
	protected $app;

	/**
	 * @var EventsDispatcher
	 */
	protected $events;

	/**
	 * @var SettingsRepositoryInterface
	 */
	protected $settings;

	/**
	 * @var UrlGenerator
	 */
	protected $url;

	/**
	 * @var UserRepository
	 */
	protected $users;

	/**
     * @var SessionAuthenticator
     */
    protected $authenticator;

	/**
	 * @var Rememberer
	 */
	protected $rememberer;

	const SESSION_STATE_KEY = 'singleso.oauth2state';

	protected static $actions = [
		'login' => 'handleLogin',
		'register' => 'handleRegister',
		'logout' => 'handleLogout',
		'account' => 'handleAccount',
		'' => 'handleDefault'
	];

	/**
	 * @param Application $app
	 * @param EventsDispatcher $events
	 * @param SettingsRepositoryInterface $settings
	 * @param UrlGenerator $url
	 * @param UserRepository $users
	 * @param SessionAuthenticator $authenticator
	 * @param Rememberer $rememberer
	 */
	public function __construct(
		Application $app,
		EventsDispatcher $events,
		SettingsRepositoryInterface $settings,
		UrlGenerator $url,
		UserRepository $users,
		SessionAuthenticator $authenticator,
		Rememberer $rememberer
	) {
		$this->app = $app;
		$this->events = $events;
		$this->settings = $settings;
		$this->url = $url;
		$this->users = $users;
		$this->authenticator = $authenticator;
		$this->rememberer = $rememberer;
	}

	/**
	 * @param Request $request
	 * @param array $routeParams
	 * @return \Psr\Http\Message\ResponseInterface|RedirectResponse
	 */
	public function handle(Request $request, array $routeParams = []) {
		// Initialize variables and get the settings.
		$params = $request->getQueryParams();

		// Map the action to a method.
		$action = array_get($params, 'action');
		$method = array_get(static::$actions, $action ? $action : '');
		$method = $method ? $method : static::$actions[''];

		// Return response or handle the exception.
		try {
			return $this->$method($request);
		}
		catch (SingleSOException $ex) {
			return $this->handleException($ex);
		}
	}

	/**
	 * @param Request $request
	 * @throws SingleSOException
	 * @return \Psr\Http\Message\ResponseInterface|RedirectResponse
	 */
	public function handleLogin(Request $request) {
		return $this->createLoginResponse($request);
	}

	/**
	 * @param Request $request
	 * @throws SingleSOException
	 * @return \Psr\Http\Message\ResponseInterface|RedirectResponse
	 */
	public function handleRegister(Request $request) {
		return $this->createLoginResponse($request, true);
	}

	/**
	 * @param Request $request
	 * @throws SingleSOException
	 * @return \Psr\Http\Message\ResponseInterface|RedirectResponse|JsonResponse|JsonpResponse
	 */
	public function handleLogout(Request $request) {
		// Load settings or fail.
		$authSettings = SingleSO::settingsAuth($this->settings, true);

		// Check for the logout token parameter, if present handle logout.
		$params = $request->getQueryParams();
		if (array_get($params, 'token')) {
			return $this->createLogoutTokenResponse($request);
		}

		// Sanity check for the logout URL.
		$logout_url = $authSettings['logout_url'];
		if (!$logout_url) {
			throw new SingleSOException(['Not configured for logout.']);
		}

		// Get any supplied redirect.
		$redirect = array_get($params, 'redirect');

		// Setup state with a random token, add redirect if specified.
		$session = $request->getAttribute('session');
		$state = $this->sessionStateCreate($session, $redirect);

		// Create the redirect parameters.
		$ssoParams = [
			'client_id' => $authSettings['client_id'],
			'redirect_uri' => $this->getRedirectURI(),
			'state' => $state
		];

		// Get the Flarum user if authenticated.
		// If a managed user, create and add token.
		// This will enable logout even if main session is lost.
		$user_id = $session ? $session->get('user_id') : null;
		$user = $user_id ? User::find($user_id) : null;
		if ($user && isset($user->singleso_id)) {
			$ssoParams['token'] = SingleSO::logoutTokenCreate(
				$user->singleso_id,
				$authSettings['client_secret']
			);
		}

		// Redirect to logout URL.
		return new RedirectResponse(
			SingleSO::addParams($logout_url, $ssoParams)
		);
	}

	/**
	 * @param Request $request
	 * @throws SingleSOException
	 * @return \Psr\Http\Message\ResponseInterface|RedirectRespons
	 */
	public function handleAccount(Request $request) {
		// Load settings or fail.
		$authSettings = SingleSO::settingsAuth($this->settings, true);

		// Redirect to login URL which will take to the account.
		return new RedirectResponse($authSettings['login_url']);
	}

	/**
	 * @param Request $request
	 * @throws SingleSOException
	 * @return \Psr\Http\Message\ResponseInterface|RedirectResponse
	 */
	public function handleDefault(Request $request) {
		$params = $request->getQueryParams();

		// If code parameter, must be the auth callback.
		if (array_get($params, 'code')) {
			return $this->createCodeResponse($request);
		}

		// If just state then handle it.
		if (array_get($params, 'state')) {
			return $this->createStateResponse($request);
		}

		// Otherwise an invalid request.
		throw new SingleSOException(['Invalid request.']);
	}

	/**
	 * @param Request $request
	 * @param boolean $register
	 * @throws SingleSOException
	 * @return \Psr\Http\Message\ResponseInterface|RedirectResponse
	 */
	public function createLoginResponse(Request $request, $register = false) {
		// If current user is already logged in, just redirect now.
		$actor = $request->getAttribute('actor');
		if (!$actor->isGuest()) {
			return new RedirectResponse(
				$this->expandRedirect(array_get($params, 'redirect'))
			);
		}

		// Load settings or fail.
		$authSettings = SingleSO::settingsAuth($this->settings, true);

		// Get parameters.
		$params = $request->getQueryParams();
		$redirect = array_get($params, 'redirect');

		// If the register action, show registration page, else show login.
		$target = $authSettings[$register ? 'register_url' : 'login_url'];

		// Store state in session.
		$session = $request->getAttribute('session');
		$state = $this->sessionStateCreate($session, $redirect);

		// Setup parameters.
		$ssoParams = [
			'redirect_uri' => $this->getRedirectURI(),
			'client_id' => $authSettings['client_id'],
			'scope' => 'user email profile',
			'state' => $state
		];

		// Construct URL and redirect.
		return new RedirectResponse(SingleSO::addParams($target, $ssoParams));
	}

	/**
	 * @param Request $request
	 * @throws SingleSOException
	 * @return \Psr\Http\Message\ResponseInterface|JsonResponse|JsonpResponse
	 */
	public function createLogoutTokenResponse(Request $request) {
		$params = $request->getQueryParams();

		// Get the user session.
		$session = $request->getAttribute('session');

		// Get the Flarum user if authenticated.
		$user_id = $session ? $session->get('user_id') : null;
		$user = $user_id ? User::find($user_id) : null;

		// Success flag.
		$success = 0;
		$message = null;

		// Flag to logout user.
		$logout = false;

		// If there a managed user, possibly log out.
		if ($user && isset($user->singleso_id)) {
			// Load settings, check success.
			$authSettings = SingleSO::settingsAuth($this->settings, false);
			if (!$authSettings) {
				$message = 'Invalid configuration.';
			}
			else {
				// Verify token.
				if (!SingleSO::logoutTokenVerify(
					$user->singleso_id,
					$authSettings['client_secret'],
					array_get($params, 'token')
				)) {
					$message = 'Invalid token.';
				}
				else {
					// Remember to do logout.
					$logout = true;

					// User is logged out.
					$success = 1;
				}
			}
		}
		else {
			// No user to logout.
			$success = -1;
		}

		// Create the response data.
		$responseData = ['success' => $success];
		if ($message) {
			$responseData['message'] = $message;
		}
		$response = null;

		// Get the JSONP callback if present.
		$callback = array_get($params, 'callback');

		// Try to create response or convert failure to catchable exception.
		try {
			// If a JSONP callback, use JSONP, else JSON.
			$response = $callback ?
				new JsonpResponse($responseData, $callback) :
				new JsonResponse($responseData);
		}
		catch (InvalidArgumentException $ex) {
			throw new SingleSOException([$ex->getMessage() . '.']);
		}

		// Logout the current user if set to do.
		if ($logout) {
			// Remember the state after destroying session.
			$sessionData = $this->sessionStateGet($session);

			// Trigger the actual logout.
			$this->authenticator->logOut($session);
			$user->accessTokens()->delete();
			$this->events->fire(new UserLoggedOut($user));
			$response = $this->rememberer->forget($response);

			// Set the state back on the new session if existed.
			if ($sessionData) {
				$this->sessionStateSet($session, $sessionData);
			}
		}

		return $response;
	}

	/**
	 * @param Request $request
	 * @throws SingleSOException
	 * @return \Psr\Http\Message\ResponseInterface|RedirectResponse
	 */
	public function createCodeResponse(Request $request) {
		$session = $request->getAttribute('session');

		// Load settings or fail.
		$authSettings = SingleSO::settingsAuth($this->settings, true);

		// Get parameters.
		$params = $request->getQueryParams();
		$code = array_get($params, 'code');
		$state = array_get($params, 'state');

		// Get the state from the URL or fail.
		if (!$state) {
			throw new SingleSOException(['No state parameter supplied.']);
		}

		// Check the state against the session and remove or throw.
		$stateData = $this->sessionStateValid($session, $state);
		$this->sessionStateRemove($session);

		// Get user info from supplied token.
		$userInfo = SingleSO::getOauthUserInfo(
			$authSettings['endpoint_url'],
			[
				'code' => $code,
				'client_id' => $authSettings['client_id'],
				'client_secret' => $authSettings['client_secret'],
				'redirect_uri' => $this->getRedirectURI()
			],
			$authSettings['endpoint_ip_forced'] ?
				$authSettings['endpoint_ip_forced'] : null
		);

		// Ensure a user for the info.
		$actor = $request->getAttribute('actor');
		$user = SingleSO::ensureUser($userInfo, $this->events, $actor);

		// Create the redirect response, with redirect from state if set.
		$response = new RedirectResponse($this->expandRedirect($stateData));

		// Authenticate user on the current session.
		$session = $request->getAttribute('session');
		$this->authenticator->logIn($session, $user->id);

		// Generate remember me token (3600 is the time Flarum uses).
		$token = AccessToken::generate($user->id, 3600);
		$token->save();

		// Trigger the login event.
		$this->events->fire(new UserLoggedIn($user, $token));

		// Attach the token as a remember me cookie unless using auto-login.
		// If using auto-login, let the auth server handled remembering.
		if (!$authSettings['global_cookie']) {
			$response = $this->rememberer->remember($response, $token);
		}

		// Return the redirect response.
		return $response;
	}

	/**
	 * @param Request $request
	 * @throws SingleSOException
	 * @return \Psr\Http\Message\ResponseInterface|RedirectResponse
	 */
	public function createStateResponse(Request $request) {
		// Get the state parameter.
		$params = $request->getQueryParams();
		$state = array_get($params, 'state');

		// Get the state data or throw.
		$session = $request->getAttribute('session');
		$stateData = $this->sessionStateValid($session, $state);

		// Expand the redirect in state and redirect to it.
		return new RedirectResponse($this->expandRedirect($stateData));
	}

	/**
	 * @param SingleSOException $ex
	 * @return \Psr\Http\Message\ResponseInterface|HtmlResponse
	 */
	public function handleException(SingleSOException $ex) {
		// Load template.
		$html = file_get_contents(__DIR__ . '/templates/error.html');

		// Template vars.
		$vars = [
			'title' => '500 Internal Server Error',
			'message' => $ex->getMessage(),
			'link_href' => $this->app->url(),
			'link_text' => 'Return Home'
		];

		// Create arrays for str_replace.
		$find = array_keys($vars);
		$repl = array_values($vars);
		foreach ($find as &$v) {
			$v = '{{' . $v . '}}';
		}
		unset($v);
		foreach ($repl as &$v) {
			$v = htmlspecialchars($v);
		}
		unset($v);

		// Replace template values and return response.
		$html = str_replace($find, $repl, $html);
		return new HtmlResponse($html, 500);
	}

	/**
	 * @param string $path
	 * @return string
	 */
	public function expandRedirect($path) {
		return SingleSO::safePath($this->app->url(), $path);
	}

	/**
	 * @return string
	 */
	public function getRedirectURI() {
		$path = $this->url->toRoute('auth.singleso');
		// Strip off the redirect protocol if so configured.
		$authSettings = SingleSO::settingsAuth($this->settings, false);
		if ($authSettings['redirect_uri_noprotocol']) {
			$path = preg_replace('/^https?:\/\//', '', $path);
		}
		return $path;
	}

	/**
	 * @param Symfony\Component\HttpFoundation\Session\Session $session
	 * @param string $token
	 * @return mixed
	 */
	public function sessionStateValid($session, $token) {
		$data = $this->sessionStateGet($session);
		if (!$data) {
			throw new SingleSOException(['No state issued for this session.']);
		}
		if (!$token || array_get($data, 'token') !== $token) {
			throw new SingleSOException(['Invalid state.']);
		}
		return array_get($data, 'value');
	}

	/**
	 * @param Symfony\Component\HttpFoundation\Session\Session $session
	 * @param mixed $value
	 * @return string
	 */
	public function sessionStateCreate($session, $value) {
		$token = SingleSO::randStr(16);
		$this->sessionStateSet($session, [
			'token' => $token,
			'value' => $value
		]);
		return $token;
	}

	/**
	 * @param Symfony\Component\HttpFoundation\Session\Session $session
	 * @return mixed
	 */
	public function sessionStateGet($session) {
		return $session->get(static::SESSION_STATE_KEY);
	}

	/**
	 * @param Symfony\Component\HttpFoundation\Session\Session $session
	 * @param mixed $value
	 */
	public function sessionStateSet($session, $value) {
		$session->set(static::SESSION_STATE_KEY, $value);
		$session->save();
	}

	/**
	 * @param Symfony\Component\HttpFoundation\Session\Session $session
	 */
	public function sessionStateRemove($session) {
		$session->remove(static::SESSION_STATE_KEY);
		$session->save();
	}
}
