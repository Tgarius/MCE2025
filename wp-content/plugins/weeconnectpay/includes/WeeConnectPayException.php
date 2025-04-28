<?php

namespace WeeConnectPay\WordPress\Plugin\includes;

use Exception;

/** @TODO: Check for ABSPATH on each file + empty index.php in each folder */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WeeConnectPayException
 * @package WeeConnectPay\WordPress\Plugin\includes
 */
class WeeConnectPayException extends Exception {

	/** @var string sanitized/localized error message */
	private $localized_message;

	/**
	 * @var string
	 */
	private $error_context;

	/**
	 * @var string
	 */
	private $error_type;

	/**
	 * @var string
	 */
	private $payload_as_string;

	public function __construct( $payload, $localized_message = '' ) {

		// Get Error context
		if ( isset( $payload->error->context ) ) {
			$this->error_context = strtolower( $payload->error->context );
		} elseif ( isset( $payload->message ) && ! isset( $payload->error ) ) {
			$this->error_context = 'message_only';
		} else {
			$this->error_context = 'unknown';
		}

		// Handle it for each error context
		// Plugin
		if ( $this->error_context === 'plugin' ) {
			if ( isset( $payload->error->code ) ) {
				$this->error_type = strtolower( $payload->error->type );
				// ...
			} else {
				// ...
			}
		}

		// WeeConnectPay API
		if ( $this->error_context === 'weeconnectpay_api' ) {
			if ( isset( $payload->error->type ) ) {
				$this->error_type = strtolower( $payload->error->type );
				// ...
			} else {
				// ...
			}
		}

		// Clover
		if ( $this->error_context === 'clover' ) {
			if ( isset( $payload->error->type ) ) {
				$this->error_type = strtolower( $payload->error->type );
				// ...
			} else {
				// ...
			}
		}

		// Message only
		if ( $this->error_context === 'message_only' ) {
			if ( isset( $payload->message ) ) {
				$this->message = $payload->message;
				// ...
			} else {
				// ...
			}
		}

		// Unknown
		if ( $this->error_context === 'unknown' ) {
			if ( gettype( $payload ) === 'string' ) {
				$this->payload_as_string = $payload;
			} elseif ( gettype( $payload ) === 'object' || gettype( $payload ) === 'array' ) {
				$this->payload_as_string = json_encode( $payload );
			}
		}

		// Maybe one day!
		$this->localized_message = $localized_message;

		if ( isset( $payload->message ) ) {
			parent::__construct( $payload->message );
		} elseif ( isset( $payload->error->message ) ) {
			parent::__construct( $payload->error->message );
		} else {
			// ...
		}
	}

	public function getLocalizedMessage() {
		return $this->localized_message;
	}

	/**
	 * Wrapper for any admin notice error conditions we might have, including individual plugin settings;
	 * @return bool
	 */
	public function shouldDisplayAdminNoticeError() {
		// Since we're using admin_notice validate that the user is admin.
		if ( is_admin() ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Makes a function name out of the error context and code for WP to call.
	 * @return string
	 */
	public function getErrorFunctionString() {
		$context   = $this->error_context;
		$type      = $this->error_type;
		$it_exists = method_exists( self::class, $context . '_' . $type );
		if ( $it_exists ) {
			return $context . '_' . $type;
		} else {
			return 'unhandled_error';
		}
	}
	// ERROR DISPLAY SETTINGS -- WeeConnectPay API
	// unsupported_http_verb


	// ERROR DISPLAY SETTINGS -- Clover
	// too_many_requests


	// ERROR DISPLAY SETTINGS -- Unhandled
	public static function unhandled_error() {
		$class   = 'notice notice-error';
		$message = __( 'We meant to show you a meaningful error but something went wrong. Please contact us.', 'weeconnectpay' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	// ERROR DISPLAY SETTINGS -- Plugin
	public static function plugin_api_key_not_set_error() {
		$class   = 'notice notice-error';
		$message = __( 'A Clover API key is needed to use this plugin.', 'weeconnectpay' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	public static function weeconnectpay_api_api_key_not_found_error() {
		$class   = 'notice notice-error';
		$message = __( 'WeeConnectPay could not find an associated account with the provided Clover API Key. Log in the WeeConnectPay app through the Clover app market to resolve this.', 'weeconnectpay' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

}

