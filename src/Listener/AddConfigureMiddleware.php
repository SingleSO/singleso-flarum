<?php

namespace SingleSO\Auth\Listener;

use Flarum\Event\ConfigureMiddleware;
use Flarum\Foundation\Application;
use Illuminate\Contracts\Events\Dispatcher;
use SingleSO\Auth\Middleware\Autologin;

class AddConfigureMiddleware {

	/**
	 * @var Application
	 */
	protected $app;

	/**
	 * @param Application $app
	 */
	public function __construct(Application $app) {
		$this->app = $app;
	}

	/**
	 * @param Dispatcher $events
	 */
	public function subscribe(Dispatcher $events) {
		$events->listen(ConfigureMiddleware::class, [$this, 'whenConfigureMiddleware']);
	}

	/**
	 * @param ConfigureMiddleware $event
	 */
	public function whenConfigureMiddleware(ConfigureMiddleware $event) {
		$event->pipe->pipe($event->path, $this->app->make(Autologin::class));
	}
}
