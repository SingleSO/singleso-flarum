<?php

namespace SingleSO\Auth;

use Exception;

class SingleSOException extends Exception {

	/**
	 * @var array
	 */
	protected $messages;

	/**
	 * @param array $messages
	 */
	public function __construct(array $messages) {
		$this->messages = $messages;
		parent::__construct('SingleSO Exception: ' . implode("\n", $messages));
	}

	/**
	 * @return array
	 */
	public function getMessages() {
		return $this->messages;
	}
}
