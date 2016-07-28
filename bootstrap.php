<?php

use Illuminate\Contracts\Events\Dispatcher;
use SingleSO\Auth\Listener;
use Illuminate\Contracts\Bus\Dispatcher as Bus;

return function(Dispatcher $events, Bus $bus) {
	$events->subscribe(Listener\AddAuthRoute::class);
	$events->subscribe(Listener\AddClientAssets::class);
	$events->subscribe(Listener\AddUserEvents::class);
	$events->subscribe(Listener\AddConfigureMiddleware::class);

	$bus->pipeThrough([Listener\AddCommandHook::class]);
};
