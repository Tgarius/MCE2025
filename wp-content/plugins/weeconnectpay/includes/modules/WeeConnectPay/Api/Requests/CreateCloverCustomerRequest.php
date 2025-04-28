<?php

namespace WeeConnectPay\Api\Requests;

use WeeConnectPay\Api\ApiClient;
use WeeConnectPay\Api\ApiEndpoints;
use WeeConnectPay\Dependencies\Psr\Http\Message\ResponseInterface;

class CreateCloverCustomerRequest extends ApiClient {

	/**
	 * POST request
	 */
	public function POST( array $customerData ): ResponseInterface {
		return $this->client->post( ApiEndpoints::createCustomer(), self::setOptions( $customerData ) );
	}

	private static function setOptions( array $customerData ): array {
		$options['json'] = self::setRequestBody( $customerData );

		return $options;
	}

	/**
	 * Set the request body
	 *
	 * @param array $customerData
	 *
	 * @return array
	 */
	private static function setRequestBody( array $customerData ): array {

		return $customerData;
	}
}
