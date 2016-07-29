<?php

namespace SingleSO\Auth;

use Flarum\Core\User;
use Flarum\Event\UserWillBeSaved;
use Flarum\Settings\SettingsRepositoryInterface;
use SingleSO\Auth\SingleSOException;

class SingleSO {

	const CONTROLLER_PATH = '/auth/singleso';

	// 6 hours in seconds.
	const LOGOUT_TIMEOUT = 21600;

	// All settings keys with flag if required set.
	protected static $settingsAuthKeys = [
		'client_id' => true,
		'client_secret' => true,
		'endpoint_url' => true,
		'login_url' => true,
		'register_url' => true,
		'logout_url' => false,
		'global_cookie' => false,
		'redirect_uri_noprotocol' => false
	];

	// Property mappings with a conflict fallback sprintf format.
	protected static $userUnique = [
		'username' => ['username', '~user-%s~'],
		'email' => ['email', 'user-%s@0.0.0.0']
	];

	/**
	 * @param SettingsRepositoryInterface $settings
	 * @param boolean $throw
	 * @throws SingleSOException
	 * @return array
	 */
	public static function settingsAuth(
		SettingsRepositoryInterface $settings,
		$throw
	) {
		// Add all auth settings to array.
		$data = [];
		foreach (static::$settingsAuthKeys as $key=>$required) {
			$val = $settings->get('singleso-singleso-flarum.' . $key);
			// Throw exception if any required settings are missing.
			if ($required && !$val) {
				// Throw on missing values or just return null.
				if ($throw) {
					throw new SingleSOException(['Not fully configured.']);
				}
				return null;
			}
			$data[$key] = $val;
		}
		return $data;
	}

	/**
	 * @param array $error
	 * @return string
	 */
	public static function oauthErrorToString($error) {
		return sprintf(
			'Server Error: %s - %s: %s',
			array_get($error, 'status'),
			array_get($error, 'name'),
			array_get($error, 'message')
		);
	}

	/**
	 * @param string $endpoint
	 * @param array $params
	 * @return array|null
	 */
	public static function oauthRequest($endpoint, $params) {
		$result = null;
		$userAgent = 'singleso/singleso-flarum';
		$timeout = 30;

		// Prefer cURL, as allow_url_fopen may no be enabled.
		if (function_exists('curl_init')) {
			$c = curl_init();
			curl_setopt($c, CURLOPT_URL, $endpoint);
			curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($params));
			curl_setopt($c, CURLOPT_USERAGENT, 'singleso/singleso-flarum');
			curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($c, CURLOPT_POST, true);
			curl_setopt($c, CURLOPT_HEADER, false);
			curl_setopt($c, CURLOPT_CONNECTTIMEOUT, $timeout);
			curl_setopt($c, CURLOPT_TIMEOUT, $timeout);
			$result = curl_exec($c);
		}
		else {
			$c = stream_context_create([
				'http' => [
					'method' => 'POST',
					'header' => implode("\r\n", [
						'Content-type: application/x-www-form-urlencoded',
						'User-Agent: ' . $userAgent
					]) . "\r\n",
					'content' => http_build_query($params),
					'ignore_errors' => true,
					'timeout' => $timeout
				]
			]);
			$result = @file_get_contents($endpoint, false, $c);
		}

		// If a string, decode the JSON and check for array, else null.
		$result = (is_string($result) && $result) ?
			@json_decode($result, true) : null;

		// Return array or null.
		return is_array($result) ? $result : null;
	}

	/**
	 * @param string $endpoint
	 * @param array $params
	 * @throws SingleSOException
	 * @return string
	 */
	public static function getOauthToken($endpoint, $params) {
		$url = $endpoint . '/token';
		$data = static::oauthRequest($url, $params);
		if (!is_array($data)) {
			throw new SingleSOException(['Invalid response for: /token']);
		}
		// Get the access token and check if exists.
		$access_token = array_get($data, 'access_token');
		if (!$access_token) {
			throw new SingleSOException([
				static::oauthErrorToString($data)
			]);
		}
		return $access_token;
	}

	/**
	 * @param string $endpoint
	 * @param array $params
	 * @throws SingleSOException
	 * @return string
	 */
	public static function getOauthUser($endpoint, $params) {
		$url = $endpoint . '/user';
		$data = static::oauthRequest($url, $params);
		if (!is_array($data)) {
			throw new SingleSOException(['Invalid response for: /user']);
		}
		// Check for minimal properties.
		if (!isset($data['id'], $data['username'], $data['email'])) {
			throw new SingleSOException([
				static::oauthErrorToString($data)
			]);
		}
		return $data;
	}

	/**
	 * @param string $endpoint
	 * @param array $params
	 * @throws SingleSOException
	 * @return array
	 */
	public static function getOauthUserInfo($endpoint, $params) {
		// Use code to access auth token.
		$token = static::getOauthToken($endpoint, $params);
		// Then use the token to access user data.
		return static::getOauthUser($endpoint, [
			'access_token' => $token
		]);
	}

	/*
	 * @param int $userid
	 * @param string $secret
	 * @param string $token
	 * @return boolean
	 */
	public static function logoutTokenVerify($userid, $secret, $token) {
		$parts = explode('|', $token, 3);
		$token_expires = (int)array_get($parts, 0);
		$token_user = (int)array_get($parts, 1);
		$token_hash = array_get($parts, 2);
		if (!$token_hash) {
			return false;
		}
		if (!$userid || !$token_user || $token_user !== $userid) {
			return false;
		}
		if ($token_expires < time()) {
			return false;
		}
		// Hash them all together.
		$hashed = static::logoutTokenCreateHash(
			$userid,
			$secret,
			$token_expires
		);
		// Sanity check.
		if (!$hashed) {
			return false;
		}
		// Compare the hashes using the best option available.
		return function_exists('hash_equals') ?
			hash_equals($token_hash, $hashed) :
			($token_hash === $hashed);
	}

	/*
	 * @param int $userid
	 * @param string $secret
	 * @return string
	 */
	public static function logoutTokenCreate($userid, $secret) {
		// Create the expiration time.
		$expires = time() + static::LOGOUT_TIMEOUT;
		return $expires . '|' . $userid . '|' . static::logoutTokenCreateHash(
			$userid,
			$secret,
			$expires
		);
	}

	/*
	 * @param int $userid
	 * @param string $secret
	 * @return string
	 */
	public static function logoutTokenCreateHash($userid, $secret, $expires) {
		return hash('sha512', $expires . '|' . $userid . '|' . $secret);
	}

	/*
	 * @param array $userInfo
	 * @param EventsDispatcher $events
	 * @param User|null $actor
	 * @return User
	 */
	public static function ensureUser($userInfo, $events, $actor) {
		// Get the user that has this ID if it already exists.
		$user = User::where(['singleso_id' => $userInfo['id']])->first();

		// Change any dupes to keep the unique table column integrity.
		foreach (static::$userUnique as $k=>$v) {
			// If user is new or property is different, then check for dupe.
			if (!$user || $user->$k !== $userInfo[$v[0]]) {
				// Check for user with dupe property.
				$dupe = User::where([$k => $userInfo[$v[0]]])->first();
				// If conflict, rename to unique until next login.
				if ($dupe) {
					// If the account is local only, throw exception.
					if (!isset($dupe->singleso_id)) {
						throw new SingleSOException([
							sprintf('Local account "%s" conflict.', $k)
						]);
					}
					$dupe->$k = sprintf($v[1], $dupe->id);
					$dupe->save();
				}
			}
		}

		// If user exists, check for changes, and save if different.
		if ($user) {
			$changed = false;
			foreach (static::$userUnique as $k=>$v) {
				if ($user->$k !== $userInfo[$v[0]]) {
					$user->$k = $userInfo[$v[0]];
					$changed = true;
				}
			}
			if ($changed) {
				$user->save();
			}
		}
		// Otherwise must create new user with empty password.
		else {
			$user = User::register(
				$userInfo['username'],
				$userInfo['email'],
				''
			);

			// Add the unique ID and activate.
			$user->singleso_id = $userInfo['id'];
			$user->activate();

			// Set an internal flag, trigger event, and remove the flag.
			$user->_singleso_registered_user = true;
			$events->fire(
				new UserWillBeSaved(
					$user,
					$actor,
					[
						'attributes' => [
							'username' => $userInfo['username'],
							'email' => $userInfo['email'],
							'password' => ''
						]
					]
				)
			);
			unset($user->_singleso_registered_user);

			// Save to the database.
			$user->save();
		}

		return $user;
	}

	/*
	 * @param int $bytes
	 * @return string
	 */
	public static function randStr($bytes) {
		return str_random($bytes);
	}

	/*
	 * @param string $url
	 * @param array $params
	 * @return string
	 */
	public static function addParams($url, $params) {
		return $url .
			(strpos($url, '?') === false ? '?' : '&') .
			http_build_query($params);
	}

	/*
	 * @param string $base
	 * @param string $path
	 * @return string|null
	 */
	public static function safePath($base, $path) {
		// Trim any slash from base URL.
		$base = rtrim($base, '/');

		if ($path) {
			// Ensure no sketchy directory traversal in the URL.
			if (
				strpos($path, '../') !== false ||
				strpos($path, '..\\') !== false
			) {
				$path = null;
			}
		}

		// Create full redirect URL, defaulting to base URL.
		return $path ? $base . $path : $base;
	}
}
