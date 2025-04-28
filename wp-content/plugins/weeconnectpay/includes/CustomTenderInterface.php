<?php

namespace WeeConnectPay\WordPress\Plugin\includes;

interface CustomTenderInterface {

    /**
     * Logic to be executed after an attempt at charging the custom tender
     */
    public static function chargeCreationCallback(array $data): array;

    /**
     * Logic to be executed after an attempt at refunding the custom tender charge
     */
    public static function chargeRefundCallback(array $data): array;
}