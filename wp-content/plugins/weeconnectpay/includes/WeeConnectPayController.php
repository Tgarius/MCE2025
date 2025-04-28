<?php

namespace WeeConnectPay\WordPress\Plugin\includes;

use WeeConnectPay\CloverApp;
use WeeConnectPay\CloverMerchant;
use WeeConnectPay\Exceptions\SettingsInitializationException;
use WeeConnectPay\Exceptions\WeeConnectPayException;
use WeeConnectPay\Integrations\IntegrationSettings;
use WeeConnectPay\Settings;
use WeeConnectPay\StandardizedResponse;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Response;
use WeeConnectPay\Integrations\Logger;
use WeeConnectPay\Integrations\LogService;

class WeeConnectPayController extends WP_REST_Controller {

	protected $namespace = 'weeconnectpay/v1';
	private $wp_env;
	private $url_api;

	public function __construct() {
		$this->wp_env = WeeConnectPayUtilities::get_wp_env();

		switch ( $this->wp_env ) {
            case 'gitpod':
				if (!defined('GITPOD_WCP_BACKEND_WORKSPACE_URL')) {
					LogService::error('GITPOD_WCP_BACKEND_WORKSPACE_URL is not defined in the gitpod environment');
					throw new WeeConnectPayException('GITPOD_WCP_BACKEND_WORKSPACE_URL is not defined');
				}	
                $this->url_api = GITPOD_WCP_BACKEND_WORKSPACE_URL;
                break;
			case 'local':
			case 'development':
				// Do dev stuff
				$this->url_api = 'https://weeconnect-api.test';
				break;
			case 'staging':
				// Do staging stuff
				$this->url_api = 'https://apidev.weeconnectpay.com';
				break;
			case 'production':
			default:
				// Do production stuff
				$this->url_api = 'https://api.weeconnectpay.com';
		}
	}

	public function register_routes() {
		// Plugin Registration request from our API
		$this->registerIntegrationRegistrationRoute();

		// Plugin dependency check
		$this->registerPluginDependencyCheckRoute();
		$this->getPluginSettings();
	}

	/**
	 * Not currently used
	 */
	public function registerPluginDependencyCheckRoute() {
		$namespace = 'weeconnectpay/v1';
		$path      = 'integration/dependencies';

		register_rest_route(
			$namespace,
			'/' . $path,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_integration_dependencies' ),
					'permission_callback' => array( $this, 'get_integration_dependencies_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Registers the plugin settings route
	 *
	 * @return void
	 */
	public function getPluginSettings() {
		$namespace = 'weeconnectpay/v1';
		$path      = 'plugin/settings';

		register_rest_route(
			$namespace,
			'/' . $path,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_plugin_settings' ),
					'permission_callback' => array( $this, 'get_plugin_settings_permissions_check' ),
				),
			)
		);
	}

	public function get_plugin_settings_permissions_check( $request ): bool {
		return true;
	}

	/**
	 * @return object StandardizedResponse::emitError | StandardizedResponse::emitSuccess
	 */
	public function get_plugin_settings() {
		try {
			$integrationSettings = new IntegrationSettings();
			// TODO: Make a settings response object

			// Should either return the login page settings OR the full settings we need to display.
			$merchant = CloverMerchant::getFromIntegrationSettings( $integrationSettings);
			$app = CloverApp::getFromIntegrationSettings( $integrationSettings);
			return StandardizedResponse::emitSuccess( json_decode(json_encode( ['app' => $app, 'merchant' => $merchant])));
		} catch ( WeeConnectPayException $e ) {
			return StandardizedResponse::emitError( $e->toObject() );
		}
	}


	public function get_integration_dependencies_permissions_check( $request ): bool {
		return true;
	}

	/**
	 * @deprecated
	 * @return object StandardizedResponse::emitError | StandardizedResponse::emitSuccess
	 */
	public function get_integration_dependencies(): object {
		try {
			$integration_settings = new IntegrationSettings();
			//$integration_settings->retrieve();
			return $integration_settings->validateDependencies();
		} catch ( WeeConnectPayException $e ) {
			return StandardizedResponse::emitError( $e->toObject() );
		}
	}

	public function set_plugin_configs_permissions_check( $request ): bool {
		return true;
	}

	/**
	 * @param $request
	 *
	 * @updated 3.7.0
	 * @return object|WP_Error
	 */
	public function set_plugin_configs( $request ) {

		$body = json_decode( $request->get_body() );

		// get values needed
		if ( ! isset( $body->integration_id, $body->auth_hash ) ) {
			return new WP_Error( 'invalid_request', 'the request is missing required parameters', array( 'status' => 400 ) );
		}

		$response = wp_remote_post(
			$this->url_api . "/v1/integration/$body->integration_id/token",
			array(
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( array( 'auth_hash' => $body->auth_hash ) ),
				'timeout' => 60,
			)
		);

		$payload = json_decode( wp_remote_retrieve_body( $response ) ); // stringified json sent from our API, decode it.


		// Check payload
		$validationResult = $this->validate_set_plugin_config_payload( $payload );
		if ( $validationResult !== true ) {
			if ( $validationResult instanceof WP_Error ) {
				return $validationResult;
			} else {
				LogService::error('An unexpected error has occurred while validating the set_plugin_config payload.');
				return new WP_Error('internal_error', 'An unexpected error has occurred while validating the set_plugin_config payload.', array('status' => 500 ) );
			}
		}


		$integration_settings = new IntegrationSettings();
		try {
			$integration_settings->saveAfterAuth( $payload );

			return StandardizedResponse::emitSuccess( (object) ['message' => 'Settings were properly saved after authenticating.']);
		} catch ( SettingsInitializationException $exception ) {

			return StandardizedResponse::emit( $exception->toObject() );
		} catch ( \Throwable $exception ) {

			return StandardizedResponse::emit( $exception );
		}

		//		return new WP_REST_Response( $is_success, $is_success? 200 : 500 );
	}


	/**
	 * @param $payload
	 *
	 * @return true|WP_Error
	 * @since 3.7.0
	 */
	function validate_set_plugin_config_payload($payload) {
		$required_fields = [
			'uuid' => false,
			'token' => false,
			'clover_merchant' => ['name', 'uuid'],
			'clover_app' => ['name', 'uuid'],
			'clover_employee' => ['name', 'uuid'],
			'integrable_backend' => ['db_prefix']
		];

		if ( empty($payload->integration) ){
			$error_message = 'Missing field: integration field is missing entirely';
			LogService::error("WeeConnectPay Plugin Authentication Error: $error_message");
			return new WP_Error('invalid_response', $error_message, array('status' => 400));
		} else {
			$integration = $payload->integration;
		}

		foreach ($required_fields as $field => $subfields) {
			if (!isset($integration->$field)) {
				$error_message = sprintf('Missing field: integration.%s', $field);
				LogService::error("WeeConnectPay Plugin Authentication Error: $error_message");
				return new WP_Error('invalid_response', $error_message, array('status' => 400));
			}

			$value = $integration->$field;
			if (is_array($subfields)) {
				foreach ($subfields as $subfield) {
					if (!isset($value->$subfield)) {
						$error_message = sprintf('Missing field: integration.%s.%s', $field, $subfield);
						LogService::error("WeeConnectPay Plugin Authentication Error: $error_message");
						return new WP_Error('invalid_response', $error_message, array('status' => 400));
					}
					if (empty($value->$subfield)) {
						$error_message = sprintf('Empty field: integration.%s.%s', $field, $subfield);
						LogService::error("WeeConnectPay Plugin Authentication Error: $error_message");
						return new WP_Error('invalid_response', $error_message, array('status' => 400));
					}
				}
			} else {
				if (empty($value)) {
					$error_message = sprintf('Empty field: integration.%s', $field);
					LogService::error("WeeConnectPay Plugin Authentication Error: $error_message");
					return new WP_Error('invalid_response', $error_message, array('status' => 400));
				}
			}
		}
		return true;
	}



	private function registerIntegrationRegistrationRoute(): void {
		$namespace = 'weeconnectpay/v1';
		$path      = 'plugin/register';

		register_rest_route(
			$namespace,
			'/' . $path,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'set_plugin_configs' ),
					'permission_callback' => array( $this, 'set_plugin_configs_permissions_check' ),
				),

			)
		);
	}

}
