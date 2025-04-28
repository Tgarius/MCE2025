<?php
//phpcs:disable WordPress
//phpcs:disable Generic.Arrays.DisallowShortArraySyntax

namespace WeeConnectPay\Exceptions;

use Throwable;
use WeeConnectPay\Exceptions\Codes\ExceptionCode;

class CustomerCreationException extends WeeConnectPayException {

	public function __construct( $message = 'An error occurred while trying to create or retrieve a customer with Clover.', $code = ExceptionCode::CUSTOMER_CREATION_EXCEPTION, Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}

}
