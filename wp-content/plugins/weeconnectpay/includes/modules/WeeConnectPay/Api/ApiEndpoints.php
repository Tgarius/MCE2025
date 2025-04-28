<?php
/* phpcs:disable WordPress
 * phpcs:disable Generic.Arrays.DisallowShortArraySyntax */
namespace WeeConnectPay\Api;

class ApiEndpoints {

	/**
	 * Find or create a WooCommerce integration and return its UUID
	 * @return string
	 */
	public static function woocommerceIntegration(): string {
		return 'integration/woocommerce';
	}

	/**
	 * Verify that an integration is authenticated and belongs to the currently authenticated entity
	 * @param string $integrationUuid
	 *
	 * @return string
	 */
	public static function verifyIntegrationAuthentication ( string $integrationUuid ): string {
		return "/v1/integrations/$integrationUuid/verify";
	}

	/**
	 * Endpoint to create a charge on a Clover order using a tokenized card
	 * @param string $cloverOrderUuid
	 *
	 * @return string
	 * @since 2.0
	 */
	public static function createOrderCharge(string $cloverOrderUuid): string {
		return "/v1/clover/orders/$cloverOrderUuid/charge";
	}

    /**
     * Endpoint to create a charge on a Clover order using a Custom Tender like a gift card
     * @param string $cloverOrderUuid
     *
     * @return string
     * @since 3.12.2
     */
    public static function createOrderCustomTenderCharge(string $cloverOrderUuid): string {
        return "/v1/clover/orders/$cloverOrderUuid/custom-tender/charge";
    }

    /**
     * Endpoint to refund a charge
     * @param string $cloverChargeUuid
     *
     * @return string
     * @since 3.12.4
     */
    public static function refundCharge(string $cloverChargeUuid): string {
        return "/v1/clover/charges/$cloverChargeUuid/refund";
    }

	/**
	 * Endpoint to create a customer without having to tokenize a card first
	 *
	 * @return string
	 * @since 2.4.0
	 */
	public static function createCustomer(): string {
		return "/v1/clover/customers";
	}

}
