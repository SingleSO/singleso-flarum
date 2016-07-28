<?php

namespace SingleSO\Auth\Listener;

use Flarum\Core\Command\RequestPasswordReset;
use Flarum\Core\Exception\ValidationException;
use Flarum\Core\Repository\UserRepository;

class AddCommandHook {

	/**
	 * @var UserRepository
	 */
	protected $users;

	/**
	 * @param UserRepository $users
	 */
	public function __construct(UserRepository $users) {
		$this->users = $users;
	}

	/**
	 * @param mixed $command
	 * @param Closure $next
	 */
	public function handle($command, $next) {
		// Check if a command we want to hook.
		if ($command instanceof RequestPasswordReset) {
			// Find the user account requesting reset.
			$user = $this->users->findByEmail($command->email);

			// Only handle is user exists and is a singleso user.
			// Let the core handle unrecognized users and local only accounts.
			if ($user && isset($user->singleso_id)) {
				// Throw exception for the user to prevent reset.
				throw new ValidationException([
					'SingleSO: Direct password resetting disabled.'
				]);
			}
		}

		// Continue on.
		return $next($command);
	}
}
