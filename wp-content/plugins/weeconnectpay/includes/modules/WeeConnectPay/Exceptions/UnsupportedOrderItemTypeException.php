<?php
//phpcs:disable WordPress
//phpcs:disable Generic.Arrays.DisallowShortArraySyntax

namespace WeeConnectPay\Exceptions;

use Throwable;
use WeeConnectPay\Exceptions\Codes\ExceptionCode;

class UnsupportedOrderItemTypeException extends WeeConnectPayException {

	public function __construct( $message = 'The order contains an item type not yet supported by WeeConnectPay. Contact us if you see this message.', $code = ExceptionCode::UNSUPPORTED_ORDER_ITEM_TYPE, Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}

}
