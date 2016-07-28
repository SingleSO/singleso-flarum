<?php

namespace SingleSO\Auth\Listener;

use Illuminate\Contracts\Events\Dispatcher;
use Flarum\Core\Exception\ValidationException;
use Flarum\Event\UserWillBeSaved;

class AddUserEvents {

	/**
	 * Properties that should not be changed directly by a user.
	 *
	 * @var array
	 */
	protected $preventChanging = [
		'username',
		'email',
		'password'
	];

	const USER_EDIT_WARNED = '_singleso.usereditwarned';

	/**
	 * @param Dispatcher $events
	 */
	public function subscribe(Dispatcher $events) {
		$events->listen(UserWillBeSaved::class, [$this, 'whenUserWillBeSaved']);
	}

	/**
	 * @param UserWillBeSaved $event
	 */
	public function whenUserWillBeSaved(UserWillBeSaved $event) {
		// Check if the admin edited properties that they should not.
		$user = $event->user;

		// Ignore accounts currently being registered by this extension.
		if (isset($user->_singleso_registered_user)) {
			return;
		}

		// Prevent any non-admin editing, like registration.
		if (!$event->actor->isAdmin()) {
			throw new ValidationException([
				'SingleSO: Direct user registration and editing disabled.'
			]);
		}

		// Sanity check.
		if (!isset($user->id)) {
			throw new ValidationException([
				'SingleSO: Current user missing ID.'
			]);
		}

		// Get the current user data from the database, and check for success.
		$userClass = get_class($user);
		$currentUser = $userClass::where('id', $user->id)->first();
		if (!$currentUser) {
			throw new ValidationException([
				'SingleSO: Current user data failed to load.'
			]);
		}

		// Check that required properties remain unchanged.
		$changedProps = [];
		foreach ($this->preventChanging as $prop) {
			if ($user->$prop !== $currentUser->$prop) {
				$changedProps[] = $prop;
			}
		}

		// If some paroperties have changed, check if admin has been warned.
		if (!empty($changedProps)) {
			// Check if the user has been warned.
			$ignoreWarning = false;

			// No access to request session, so use the native API.
			@session_start();

			// If they have already been warned, possibly do it anyway.
			if (isset($_SESSION[static::USER_EDIT_WARNED])) {
				// Parse and remove existing warning data.
				$warnData = explode('|', $_SESSION[static::USER_EDIT_WARNED]);
				unset($_SESSION[static::USER_EDIT_WARNED]);
				$warnUser = isset($warnData[0]) ? (int)$warnData[0] : null;
				$warnTime = isset($warnData[1]) ? (int)$warnData[1] : null;
				$warnProps = isset($warnData[2]) ? $warnData[2] : null;

				// Only allow if same user, props, and within 5 minutes.
				if (
					isset($warnUser, $warnTime, $warnProps) &&
					$warnUser === (int)$user->id &&
					$warnProps === implode(',', $changedProps) &&
					$warnTime + 60 * 5 > time()
				) {
					$ignoreWarning = true;
				}
			}

			// If not already warned, remember and show warning.
			if (!$ignoreWarning) {
				$_SESSION[static::USER_EDIT_WARNED] = implode('|', [
					$user->id,
					time(),
					implode(',', $changedProps)
				]);
				throw new ValidationException([
					'SingleSO: These fields should not normally be directly edited to avoid synchronization issues:',
					implode(', ', $changedProps),
					'Retry to ignore this warning and edit anyway.'
				]);
			}
		}
	}
}
