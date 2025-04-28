<?php

namespace WeeConnectPay\Api\Requests;

use WeeConnectPay\Dependencies\GuzzleHttp\Exception\GuzzleException;
use WeeConnectPay\Dependencies\Psr\Http\Message\ResponseInterface;
use WeeConnectPay\Api\ApiClient;
use WeeConnectPay\Api\ApiEndpoints;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayUtilities;

class CreateCloverOrderChargeRequest extends ApiClient {

	/**
	 * POST request
	 */
	public function POST(string $cloverOrderUuid, string $tokenizedCard, string $ipAddress, ?int $amount): ResponseInterface {
		return $this->client->post( ApiEndpoints::createOrderCharge($cloverOrderUuid), self::setOptions($tokenizedCard, $ipAddress, $amount));
	}

    /**
     * @param string $tokenizedCard
     * @param string $ipAddress
     * @param int|null $amount
     * @return array
     * @updated 3.4.0
     */
	private static function setOptions(string $tokenizedCard, string $ipAddress, ?int $amount): array {
		$options['form_params'] = self::setRequestBody( $tokenizedCard, $ipAddress , $amount);

		return $options;
	}

    /**
     * Set the request body
     *
     * @param string $tokenizedCard
     * @param string $ipAddress
     * @param int|null $amount
     * @return array
     */
	private static function setRequestBody(string $tokenizedCard, string $ipAddress, ?int $amount): array {

		return [
			'tokenized_card' => $tokenizedCard,
			'ip_address' => $ipAddress,
            'integration_version' => WEECONNECT_VERSION,
            'amount' => $amount // If not specified, will pay the total of the order -- IMPORTANT (THIS VARIABLE MISSING CREATES A LOT OF CLOVER-SIDE "EDGE CASES")
		];
	}
}
