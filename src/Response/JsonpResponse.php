<?php

// Based heavily on Zend JsonResponse, which was not open to be extended.

namespace SingleSO\Auth\Response;

use InvalidArgumentException;
use Zend\Diactoros\Response\InjectContentTypeTrait;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class JsonpResponse extends Response {

	use InjectContentTypeTrait;

	/**
	 * Create a JSONP response with the given data.
	 *
	 * @param mixed $data Data to convert to JSON.
	 * @param string $callback The JSONP callback function.
	 * @param int $status Integer status code for the response; 200 by default.
	 * @param array $headers Array of headers to use at initialization.
	 * @param int $encodingOptions JSON encoding options to use.
	 * @throws InvalidArgumentException if $data pr $callback invalid.
	 */
	public function __construct(
		$data,
		$callback,
		$status = 200,
		array $headers = [],
		$encodingOptions = JsonResponse::DEFAULT_JSON_FLAGS
	) {
		$this->validateCallback($callback);
		$body = new Stream('php://temp', 'wb+');
		$body->write($callback);
		$body->write('(');
		$body->write($this->jsonEncode($data, $encodingOptions));
		$body->write(');');
		$body->rewind();

		$headers = $this->injectContentType('application/javascript', $headers);

		parent::__construct($body, $status, $headers);
	}

	/**
	 * Validate the callback function.
	 *
	 * @param string $callback
	 * @throws InvalidArgumentException if callback is invalid.
	 */
	protected function validateCallback($callback) {
		// In an invalid JavaScript global name, throw error.
		// Important to prevent XSS by funky callback name.
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $callback)) {
			throw new InvalidArgumentException('Invalid JSONP callback name');
		}
	}

	/**
	 * Encode the provided data to JSON.
	 *
	 * @param mixed $data
	 * @param int $encodingOptions
	 * @return string
	 * @throws InvalidArgumentException if unable to encode the $data to JSON.
	 */
	protected function jsonEncode($data, $encodingOptions) {
		if (is_resource($data)) {
			throw new InvalidArgumentException('Cannot JSON encode resources');
		}

		// Clear json_last_error()
		json_encode(null);

		$json = json_encode($data, $encodingOptions);

		if (JSON_ERROR_NONE !== json_last_error()) {
			throw new InvalidArgumentException(sprintf(
				'Unable to encode data to JSON in %s: %s',
				__CLASS__,
				json_last_error_msg()
			));
		}

		return $json;
	}
}
