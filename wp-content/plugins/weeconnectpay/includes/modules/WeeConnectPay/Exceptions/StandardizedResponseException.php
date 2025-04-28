<?php
//phpcs:disable WordPress
//phpcs:disable Generic.Arrays.DisallowShortArraySyntax

namespace WeeConnectPay\Exceptions;

use Throwable;
use WeeConnectPay\Exceptions\Codes\ExceptionCode;

class StandardizedResponseException extends WeeConnectPayException {

	public function __construct( $message = 'An error occurred while processing an API response.', $code = ExceptionCode::STANDARDIZED_RESPONSE_EXCEPTION, Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}

}
