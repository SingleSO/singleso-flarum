<?php

namespace SingleSO\Auth\Listener;

use Flarum\Event\ConfigureForumRoutes;
use Illuminate\Contracts\Events\Dispatcher;
use SingleSO\Auth\SingleSOAuthController;
use SingleSO\Auth\SingleSO;

class AddAuthRoute {

	/**
	 * @param Dispatcher $events
	 */
	public function subscribe(Dispatcher $events) {
		$events->listen(
			ConfigureForumRoutes::class,
			[$this, 'configureForumRoutes']
		);
	}

	/**
	 * @param ConfigureForumRoutes $event
	 */
	public function configureForumRoutes(ConfigureForumRoutes $event) {
		$actions = [
			'auth.singleso.action' => SingleSO::CONTROLLER_PATH . '/{action}',
			'auth.singleso.slash' => SingleSO::CONTROLLER_PATH . '/',
			'auth.singleso' => SingleSO::CONTROLLER_PATH
		];
		foreach ($actions as $k=>$v) {
			$event->get($v, $k, SingleSOAuthController::class);
		}
	}
}
