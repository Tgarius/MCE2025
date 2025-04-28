<?php

namespace WeeConnectPay\Api\Requests;

use WeeConnectPay\Dependencies\Psr\Http\Message\ResponseInterface;
use WeeConnectPay\Api\ApiClient;
use WeeConnectPay\Api\ApiEndpoints;

class CreateCloverOrderCustomTenderChargeRequest extends ApiClient {

    /**
     * POST request for Custom Tenders
     */
    public function POST(
        string $cloverOrderUuid,
        string $customTenderLabel,
        int $amount,
        string $ipAddress
    ): ResponseInterface {
        return $this->client->post(
            ApiEndpoints::createOrderCustomTenderCharge($cloverOrderUuid),
            self::setOptions($customTenderLabel, $amount, $ipAddress)
        );
    }

    /**
     * Request options
     */
    private static function setOptions(
        string $customTenderLabel,
        int $amount,
        string $ipAddress
    ): array {
        return [
            'form_params' => self::setRequestBody($customTenderLabel, $amount, $ipAddress),
        ];
    }

    /**
     * Request body
     */
    private static function setRequestBody(
        string $customTenderLabel,
        float $amount,
        string $ipAddress
    ): array {
        return [
            'tender_label' => $customTenderLabel,
            'amount'             => $amount,
            'ip_address'         => $ipAddress,
            'integration_version' => WEECONNECT_VERSION,
        ];
    }
}
