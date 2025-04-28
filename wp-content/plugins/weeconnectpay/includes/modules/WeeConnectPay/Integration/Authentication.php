<?php

namespace WeeConnectPay\Integrations;

use WeeConnectPay\Dependencies\GuzzleHttp\Exception\ClientException;
use WeeConnectPay\Dependencies\GuzzleHttp\Exception\GuzzleException;
use WC_Admin_Settings;
use WeeConnectPay\Api\ApiEndpoints;
use WeeConnectPay\Api\Requests\VerifyAuthenticationRequest;
use WeeConnectPay\Dependencies\GuzzleHttp\Exception\RequestException;
use WeeConnectPay\Exceptions\Codes\ExceptionCode;
use WeeConnectPay\Exceptions\IntegrationPermissionsException;
use WeeConnectPay\Exceptions\SettingsInitializationException;
use WeeConnectPay\Exceptions\WeeConnectPayException;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayAPI;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayUtilities;
use WP_Error;
use WeeConnectPay\Utils\LogService;

class Authentication {

	/**
	 * Helps us create or retrieve the handle for authentication callback ( Integration ID ).
	 * @throws WeeConnectPayException
	 * @since 1.4.0
	 * @updated 3.7.3
	 *
	 */
	public static function fetchIntegrationId(): string {

		$wp_env = WeeConnectPayUtilities::get_wp_env();

		switch ( $wp_env ) {
            case 'gitpod':
                $url_api = GITPOD_WCP_BACKEND_WORKSPACE_URL ?? 'GITPOD_URL_NOT_SET';
                break;
			case 'local':
			case 'development':
				// Do dev stuff
				$url_api = 'https://weeconnect-api.test';
				break;
			case 'staging':
				// Do staging stuff
				$url_api = 'https://apidev.weeconnectpay.com';
				break;
			case 'production':
			default:
				// Do production stuff
				$url_api = 'https://api.weeconnectpay.com';
		}

		global $wpdb, $wp_version;

		$domain = sanitize_url( $_SERVER['SERVER_NAME']);
		$woocommerce_settings_url = WeeConnectPayUtilities::getSettingsURL();

		// Endpoint to create or update the integration and get the UUID back
		$endpoint = $url_api . '/v1/integrations/woocommerce';

		// Quick fix for plugins_url sometimes containing whitespaces and newlines
		if (plugins_url() !== null){
			$plugins_url = preg_replace('/\s+/', '', plugins_url());
		} else {
			$plugins_url = null;
		}

		$body =
			array(
				'domain'             => $domain,
				'integrable'         => array(
					'type'    => 'woocommerce',
					'version' => defined( 'WC_VERSION' ) ? WC_VERSION : '0',
				),
				'integrable_backend' => array(
					'type'         => 'wordpress',
					'home_url'     => home_url(),
					'site_url'     => site_url(),
					'settings_url' => $woocommerce_settings_url ?? null,
					'plugins_url'  => $plugins_url ?? null,
					'db_prefix'    => $wpdb->prefix ?? null,
					'version'      => $wp_version ?? '0',
				),
			);


		$response = wp_remote_post(
			$endpoint,
			array(
				'method'      => 'POST',
				'timeout'     => 60,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'        => wp_json_encode( $body ),
				'cookies'     => array(),
			)
		);

		// 422 = WeeConnectPay validation was not passed
//		$code = wp_remote_retrieve_response_code( $response);
//		error_log( 'response code: ' . json_encode( $code) );


		// Response Body
		$body                 = wp_remote_retrieve_body( $response );
		$response_body_object = json_decode( $body );

		if ( isset( $response_body_object->errors ) ) {
			LogService::error('Integration pre-authentication request contains errors: ' . json_encode($response_body_object->errors));
			throw new WeeConnectPayException( 'Integration pre-authentication request contains errors. See response: ' . json_encode( $response_body_object->errors ) );
		}

		if ( ! isset( $response_body_object->integration_id ) ) {
			LogService::error('Integration pre-authentication response is missing an integration ID. Full response: ' . json_encode($response_body_object));
			throw new WeeConnectPayException( 'Integration pre-authentication response is missing an integration ID. See full response: ' . json_encode( $response_body_object ) );
		}

		return $response_body_object->integration_id;
	}

	/**
	 * Verify that the integration is authenticated properly.
	 *
	 * @param string $integrationUuid
	 *
	 * @return bool
	 * @throws RequestException
	 */
	public static function isValid( string $integrationUuid ): bool {

		try {
			( new VerifyAuthenticationRequest() )->GET( $integrationUuid );

			return true;
		} catch ( ClientException $exception ) {
			return false;
		}
	}
}
