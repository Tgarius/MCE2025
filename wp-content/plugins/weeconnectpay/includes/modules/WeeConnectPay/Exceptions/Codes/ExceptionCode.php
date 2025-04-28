<?php
/* phpcs:disable WordPress
 * phpcs:disable Generic.Arrays.DisallowShortArraySyntax */
namespace WeeConnectPay\Exceptions\Codes;

class ExceptionCode {
	public const UNHANDLED_EXCEPTION               = 0;
	public const MISSING_DEPENDENCY                = 1;
	public const DEPENDENCY_VERSION_INSUFFICIENT   = 2;
	public const SETTINGS_INITIALIZATION_EXCEPTION = 3;
	public const SETTINGS_RETRIEVAL_EXCEPTION	   = 4;
	public const SETTINGS_MISSING_INTEGRATION_UUID = 5;
	public const SETTINGS_UPDATE_EXCEPTION         = 6;
	public const INTEGRATION_PERMISSIONS_EXCEPTION = 7;
	public const MISSING_SHIPPING_STATE            = 8;
	public const CUSTOMER_CREATION_EXCEPTION       = 9;
	public const STANDARDIZED_RESPONSE_EXCEPTION   = 10;
	public const INVALID_JSON_EXCEPTION            = 11;
	public const ORDER_LINE_ITEM_TOTAL_MISMATCH    = 12;
	public const UNSUPPORTED_ORDER_ITEM_TYPE       = 13;

}
