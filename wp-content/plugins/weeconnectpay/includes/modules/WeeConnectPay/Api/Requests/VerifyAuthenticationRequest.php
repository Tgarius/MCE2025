<?php

namespace WeeConnectPay\Api\Requests;

use WeeConnectPay\Dependencies\GuzzleHttp\Exception\GuzzleException;
use WeeConnectPay\Dependencies\Psr\Http\Message\ResponseInterface;
use WeeConnectPay\Api\ApiClient;
use WeeConnectPay\Api\ApiEndpoints;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayUtilities;

class VerifyAuthenticationRequest extends ApiClient {

	/**
	 * GET request
	 */
	public function GET(string $integrationId): ResponseInterface {
		return $this->client->get( ApiEndpoints::verifyIntegrationAuthentication($integrationId));
	}
}
