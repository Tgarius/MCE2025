<?php

namespace WeeConnectPay\Api\Requests;

use WeeConnectPay\Dependencies\GuzzleHttp\Exception\GuzzleException;
use WeeConnectPay\Dependencies\Psr\Http\Message\ResponseInterface;
use WeeConnectPay\Api\ApiClient;
use WeeConnectPay\Api\ApiEndpoints;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayUtilities;

/**
 * @updated 3.7.3
 */
class FindOrCreateWoocommerceIntegrationRequest extends ApiClient {

	/**
	 * POST request
	 */
	public function POST(): ResponseInterface {
		return $this->client->post( ApiEndpoints::woocommerceIntegration(), self::setRequestBody() );
	}

	/**
	 * Set the request body
	 * @return array
	 */
	private static function setRequestBody(): array {
		global $wpdb, $wp_version;

		$domain                   = sanitize_url( $_SERVER['SERVER_NAME']);
		$woocommerce_settings_url = WeeConnectPayUtilities::getSettingsURL();

		// Quick fix for plugins_url sometimes containing whitespaces and newlines
		if (site_url() !== null){
			$plugins_url = preg_replace('/\s+/', '', site_url());
		} else {
			$plugins_url = null;
		}

		return [
			'domain'             => $domain,
			'integrable'         => [
				'type'    => 'woocommerce',
				'version' => defined( 'WC_VERSION' ) ? WC_VERSION : '0',
			],
			'integrable_backend' => [
				'type'         => 'wordpress',
				'home_url'     => home_url() ?? null,
				'site_url'     => site_url() ?? null,
				'settings_url' => $woocommerce_settings_url ?? null,
				'plugins_url'  => $plugins_url,
				'db_prefix'    => $wpdb->prefix ?? null,
				'version'      => $wp_version ?? '0',
			],
		];
	}
}
