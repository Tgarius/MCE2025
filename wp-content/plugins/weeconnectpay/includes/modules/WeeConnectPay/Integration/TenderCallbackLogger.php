<?php

namespace WeeConnectPay\Integration;

use WeeConnectPay\WordPress\Plugin\includes\CustomTenderInterface;
use WeeConnectPay\Integrations\LogService;

class TenderCallbackLogger implements CustomTenderInterface
{
    /**
     * @inheritDoc
     */
    public static function chargeCreationCallback(array $data): array
    {
        LogService::info('chargeCreationCallback called with data: ' . json_encode($data));
        return $data;
    }

    /**
     * @inheritDoc
     */
    public static function chargeRefundCallback(array $data): array
    {
        LogService::info('chargeRefundCallback called with data: ' . json_encode($data));
        return $data;
    }
}