<?php
//phpcs:disable WordPress

namespace WeeConnectPay\Api;

use WeeConnectPay\Dependencies\GuzzleHttp\Client as Client;
//use GuzzleHttp\Client as Client;
use WeeConnectPay\AccessToken;
use WeeConnectPay\Integrations\IntegrationSettings;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayUtilities;

class ApiClient {
	/**
	 * @var $domain string
	 */
	private $domain;


	/**
	 * @var $client Client
	 */
	public $client;

	/**
	 * @return string
	 */
	public function getDomain(): string {
		return $this->domain;
	}

	/**
	 * @return void
	 */
	private function setDomain(): void {
		$wpEnv = WeeConnectPayUtilities::get_wp_env();

		switch ( $wpEnv ) {
            case 'gitpod':
                $domain = GITPOD_WCP_BACKEND_WORKSPACE_URL ?? 'GITPOD_URL_NOT_SET';
                break;
            case 'local':
			case 'development':
				$domain = 'https://weeconnect-api.test';
				break;
			case 'staging':
				$domain = 'https://apidev.weeconnectpay.com';
				break;
			case 'production':
			default:
				$domain = 'https://api.weeconnectpay.com';
		}
		$this->domain = $domain;
	}

	/**
	 * Api constructor. Initializes client.
	 */
	public function __construct() {
		$this->setDomain();


		$this->client = new Client( [
			'base_uri'  => $this->domain,
			'timeout'   => 60,
			'protocols' => [ 'https' ], // https only
			'allow_redirects' => false, // Our API should not redirect, and this client is exclusive to our API
			'headers'   => [
				'Authorization' => "Bearer {$this->maybeAuthKey()}",
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			]
		] );
	}

	/**
	 * Get the access token used by guzzle client or null if it doesn't exist
	 * @return string
	 */
	private function maybeAuthKey(): ?string {
		$settings = new IntegrationSettings();
		try {
			if ( $settings->accessTokenExists() ) {
				return $settings->getAccessToken()->getToken();
			} else {
				return null;
			}
		} catch (\Throwable $exception) {
			// Silently fail
			return null;
		}
	}

}
