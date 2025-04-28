<?php

namespace WeeConnectPay\Api\Requests;

use WeeConnectPay\Dependencies\Psr\Http\Message\ResponseInterface;
use WeeConnectPay\Api\ApiClient;
use WeeConnectPay\Api\ApiEndpoints;

class RefundCloverChargeRequest extends ApiClient {

    /**
     * POST request for Custom Tenders
     */
    public function POST(
        string $cloverChargeUuid,
        string $reason,
        string $externalReferenceId,
        int $amount
    ): ResponseInterface {
        return $this->client->post(
            ApiEndpoints::refundCharge($cloverChargeUuid),
            self::setOptions($reason, $externalReferenceId, $amount)
        );
    }

    /**
     * Request options
     */
    private static function setOptions(
        string $reason,
        string $externalReferenceId,
        int $amount
    ): array {
        return [
            'form_params' => self::setRequestBody($reason, $externalReferenceId, $amount),
        ];
    }

    /**
     * Request body
     */
    private static function setRequestBody(
        string $reason,
        string $externalReferenceId,
        int $amount
    ): array {
        return [
            'reason'             => $reason,
            'external_reference' => $externalReferenceId,
            'amount'             => $amount,
        ];
    }
}
