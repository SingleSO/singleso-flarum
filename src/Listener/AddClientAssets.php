<?php

namespace SingleSO\Auth\Listener;

use Flarum\Event\ConfigureClientView;
use Flarum\Foundation\Application;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use SingleSO\Auth\SingleSO;

class AddClientAssets {

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
	 * @param Dispatcher $events
	 */
	public function subscribe(Dispatcher $events) {
		$events->listen(ConfigureClientView::class, [$this, 'addAssets']);
	}

	/**
	 * @param ConfigureClientView $event
	 */
	public function addAssets(ConfigureClientView $event) {
		if ($event->isForum()) {
			// Check that the settings are configured before taking over login.
			$authSettings = SingleSO::settingsAuth($this->settings, false);
			if ($authSettings) {
				// Register the forum script.
				$event->addAssets([
					__DIR__ . '/../../js/forum/dist/extension.js'
				]);
				$event->addBootstrapper('singleso/singleso-flarum/main');

				// Register some settings for the extension.
				$view = $event->view;
				$actor = $view->getActor();

				// Is the viewing user a guest.
				$guest = (bool)$actor->isGuest();

				// Is the user a manged user.
				$managed = (bool)(!$guest && isset($actor->singleso_id));

				// Logout hook if has logout URL, and is managed user.
				$logout = (bool)$authSettings['logout_url'];

				// Register the extension settings.
				$view->setVariable('singleso-singleso-flarum', [
					'controller' => SingleSO::CONTROLLER_PATH,
					'logout' => $logout,
					'managed' => $managed,
					'guest' => $guest
				]);

				// JavaScript could also do the auto-login redirect.
				// Advantages:
				// - Preserve the URL hash (only used on the admin panel?).
				// - Potentially JSONP checking instead of cookie.
				// Disadvantages:
				// - Error pages not handled (pages that require login).
				// - Slower to do the login redirect.
				// - Requires JavaScript (Flarum already requires it).
				// Choosing to use middelware for the auto-login cookie.

				// do {
				// 	// Check if a guest.
				// 	if (!$guest) {
				// 		break;
				// 	}
				//
				// 	// Check if global login cookie configured.
				// 	$globalCookie = $authSettings['global_cookie'];
				// 	if (!$globalCookie) {
				// 		break;
				// 	}
				//
				// 	// Check if request contains the cookie.
				// 	$request = $view->getRequest();
				// 	$cookies = $request->getCookieParams();
				// 	if (!isset($cookies[$globalCookie])) {
				// 		break;
				// 	}
				//
				// 	// If all checks passed, inject the inline script.
				// 	$view->addHeadString(
				// 		'<script>' . $this->autoLoginScript() . '</script>',
				// 		'singleso-singleso-flarum-autologin'
				// 	);
				// } while(false);
			}
		}
		if ($event->isAdmin()) {
			// Register admin panel script.
			$event->addAssets([
				__DIR__ . '/../../js/admin/dist/extension.js'
			]);
			$event->addBootstrapper('singleso/singleso-flarum/main');

			$view = $event->view;

			// Register the extension settings.
			$view->setVariable('singleso-singleso-flarum', [
				'controller' => SingleSO::CONTROLLER_PATH
			]);
		}
	}

	protected function autoLoginScript() {
		// Get source, remiving any extra semicolons.
		$src = trim(file_get_contents(
			__DIR__ . '/../../js/autologin/dist/main.js'
		), ';');

		// Special variables to replace.
		$find = [
			'___BASE___',
			'___PATH___'
		];

		// Values to replace with.
		$repl = [
			rtrim($this->app->url(), '/'),
			SingleSO::CONTROLLER_PATH . '/login'
		];
		// JSON encode with minimal extra slashes.
		foreach ($repl as $k=>$v) {
			$repl[$k] = str_replace(
				'</',
				'<\/',
				json_encode($v, JSON_UNESCAPED_SLASHES)
			);
		}

		// Return the transformed source.
		return str_replace($find, $repl, $src);
	}
}
