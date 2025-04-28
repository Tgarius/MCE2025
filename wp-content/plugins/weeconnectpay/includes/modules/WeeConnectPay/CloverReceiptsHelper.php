<?php

namespace WeeConnectPay;

use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayUtilities;

class CloverReceiptsHelper
{
    const RECEIPT_TYPES = [
        'ORDER' => 'order',
        'CHARGE' => 'charge'
    ];

    public static function getEnvCloverReceiptsDomain(): string
    {
        switch (WeeConnectPayUtilities::get_wp_env()) {
            case 'production':
                return 'www.clover.com';
            case 'staging':
            case 'development':
            default:
                return 'sandbox.dev.clover.com';
        }
    }

    public static function getEnvReceiptUrl(string $uuid, string $receiptType): string
    {
        $domain = self::getEnvCloverReceiptsDomain();

        switch ($receiptType) {
            case self::RECEIPT_TYPES['ORDER']:
                return "https://{$domain}/r/{$uuid}/";
            case self::RECEIPT_TYPES['CHARGE']:
                return "https://{$domain}/tx/p/{$uuid}/";
            default:
                return '#';
        }
    }
}