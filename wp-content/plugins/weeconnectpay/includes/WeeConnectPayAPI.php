<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions
// phpcs:disable WordPress.Files.FileName.InvalidClassFileName
// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode
// phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

namespace WeeConnectPay\WordPress\Plugin\includes;

use WC_Order;
use WC_Order_Item;
use WeeConnectPay\Exceptions\Codes\ExceptionCode;
use WeeConnectPay\Exceptions\MissingDependencyException;
use WeeConnectPay\Exceptions\MissingStateException;
use WeeConnectPay\Exceptions\UnsupportedOrderItemTypeException;
use WeeConnectPay\Exceptions\WeeConnectPayException;
use WeeConnectPay\Integrations\Authentication;
use WeeConnectPay\Integrations\IntegrationSettings;
use WeeConnectPay\Integrations\PaymentFields;
use WeeConnectPay\Settings;
use WeeConnectPay\StandardizedResponse;
use WP_Error;
use WeeConnectPay\Integrations\Logger;
use WeeConnectPay\Integrations\LogService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 *  Class responsible for external calls to our API.
 *
 * @package WeeConnectPay\WordPress\Plugin\includes
 */
class WeeConnectPayAPI {

	/**
	 * Allows for quick toggling of debug logging on any environment. Set to false for production.
	 * @var bool
	 */
	private $is_debug = true;
	/**
	 * Determines if we should connect to the sandbox. Set to false for production.
	 * @var bool
	 */
	private $is_sandbox = true;
	/**
	 * Determines if we should connect to a locally hosted dev environment of our API.
	 * @var bool
	 */
	private $is_dev_sandbox = true;
	/**
	 * Determines if we should connect with a publicly exposed (ngrok) local WP install to the staging API server.
	 * @var bool
	 */
	public $is_local_sandbox_to_staging;
	/**
	 * Publicly exposed (ngrok) WordPress URL.
	 * @var string
	 */
	public $local_sandbox_ngrok_url;
	/**
	 * JSON Web Token - For authenticating calls to protected API endpoints.
	 * @var string
	 */
	private $jwt;
	/**
	 * URL of our API.
	 * @var string
	 */
	public $url_api;
	/**
	 * Settings array for our plugin.
	 * @var array
	 */
	public $settings;

	public $wp_env;

	/**
	 * Integration Settings Class.
	 * @var IntegrationSettings
	 */
	public $integrationSettings;

	const LOCAL_API_URL = "https://weeconnect-api.test";
	const DEV_API_URL = "";
	const STAGING_API_URL = "https://apidev.weeconnectpay.com";
	const PRODUCTION_API_URL = "https://api.weeconnectpay.com";

	/**
	 * @updated 1.4.1
	 */
	public function __construct() {
		$this->url_api = self::getApiDomain();
		$this->integrationSettings = new IntegrationSettings();
		$this->wp_env = WeeConnectPayUtilities::get_wp_env();
	}

//	public function get_checkout_url( $payment_processor ) {
//		return $this->url_api . "/v1/$payment_processor[pp_type]/order/$payment_processor[uuid]/checkout";
//	}




	/**
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function prepare_clover_discounts( WC_Order $order ) {

		$discount        = $order->get_total_discount();
		$clover_discount = array();

		if ( $discount > 0 ) {

			$discount_name = 'Coupons : - ';
			foreach ( $order->get_coupons() as $coupon_name ) {
				$discount_name .= wc_clean( $coupon_name ) . '-';
			}

			$clover_discount = array(
				'value' => $discount,
				'type'  => 'amount',
				'name'  => $discount_name,
			);
		}

		return $clover_discount;
	}

	/**
	 * @param WC_Order $order
	 * @param string $payment_processing_source
	 * @param string $payment_processing_source_type
	 *
	 * @return array
	 */
	public function prepare_internal_woocommerce_order( WC_Order $order, string $payment_processing_source, string $payment_processing_source_type ): array {
		return array(
			'woocommerce_order_id'           => $order->get_id(),
			'amount'                         => round( $order->get_total() * 100.00 ), // In cents
			'thank_you_url'                  => $this->get_woocommerce_thank_you_url( $order ),
			'callback_url'                   => $this->get_woocommerce_callback_url(),
			'payment_processing_source'      => $payment_processing_source,
			'payment_processing_source_type' => $payment_processing_source_type,
			'status'                         => $order->get_status(),
		);
	}


	public function get_clover_order_total_from_products( $clover_order ) {
		$total = 0;
		foreach ( $clover_order['items'] as $item ) {
			$is_clover_imported_product = isset( $item['id'] );
			if ( $is_clover_imported_product ) {
				// multiply amount by quantity, add taxes if any
				$item_subtotal = $item['amount'] * $item['quantity'];

				$item_combined_tax_rates = 0;
				$item_taxes              = 0;
				if ( isset( $item['tax_rates'] ) ) {
					foreach ( $item['tax_rates'] as $index => $tax_rate ) {
						$item_combined_tax_rates += $tax_rate->rate; // Get each tax rate for this item
					}
					$formatted_combined_tax_rates = ( $item_combined_tax_rates / 10000000 );
					$item_taxes = $formatted_combined_tax_rates * $item_subtotal; // Process the combined tax rates to the item_subtotal
				} else {

				}

				$total += (int) round( $item_subtotal + $item_taxes ); // Add the combined taxes for the item total -- We only type cast here as a tax workaround, not in the actual calculation done by the back-end.
			} else {
				$total += $item['amount'];
			}
		}
		return $total;
	}


	/**
	 * @throws UnsupportedOrderItemTypeException
	 */
	public function format_clover_order_items( WC_Order $order ) {

		$items          = $this->get_items_as_clover_order_items( $order );
        $fees           = $this->get_fees_as_clover_order_items( $order );
		$tax_items      = array();
		$shipping_items = $this->get_shipping_as_clover_items( $order );

        if ( count( $fees ) > 0 ) {
            $items = array_merge( $items, $fees );
        }

		if ( count( $shipping_items ) > 0 ) {
			$items = array_merge( $items, $shipping_items );
		}


		return $items;
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function get_taxes_as_clover_items( WC_Order $order ): array {
		$tax_items = array();

		foreach ( $order->get_taxes() as $tax ) {
			// Make tax item array
			array_push(
				$tax_items,
				array(
					'type'        => 'tax',
					'amount'      => round( ( (float) $tax->get_tax_total() + (float) $tax->get_shipping_tax_total() ) * 100 ),
					'description' => $tax->get_label(),
					'currency'    => strtolower( $order->get_currency() ),
					'quantity'    => 1,
				)
			);
		}

		return $tax_items;
	}

	/**
	 * @param WC_Order_Item $item
	 *
	 * @return string
	 */
	public function get_individual_item_tax( WC_Order_Item $item ) {
		return $item->get_total_tax();
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return array
	 * @throws UnsupportedOrderItemTypeException
	 */
	public function get_items_as_clover_order_items( WC_Order $order ): array {
		$items = array();

		$clover_inventory_items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			// Clover Inventory Import changes
			$clover_inventory_items[ $item_id ] = $this->set_clover_item_tax_rates_for_meta( $item );

			array_push( $items, $this->get_clover_formatted_item( $item, $order ) );
		}

		return $items;
	}

	public function set_clover_item_tax_rates_for_meta( WC_Order_Item $item ) {
		$item_tax_rates = array();

		// Check for import
		$product             = $item->get_product();
		$weeconnectpay_metadata = json_decode( json_encode( $product->get_meta( 'weeconnectpay_metadata' ) ) ); // Because PHP is a primitive language
		if ( isset( $weeconnectpay_metadata ) ) {
			if ( isset( $weeconnectpay_metadata->clover_imported ) ) {
				if ( isset( $weeconnectpay_metadata->clover->product_id, $weeconnectpay_metadata->clover->product_tax_rates ) ) {
					//die();
					$item_tax_rates = array();
				}
			}
		}

		return $item_tax_rates;
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function get_shipping_as_clover_items( WC_Order $order ): array {
		$shipping_item  = array();
		$shipping_total = WeeConnectPayHelper::safe_amount_to_cents_int( $order->get_shipping_total() ) + WeeConnectPayHelper::safe_amount_to_cents_int( $order->get_shipping_tax() );

		if ( $shipping_total > 0 ) {
			array_push(
				$shipping_item,
				array(
					'amount'      => $shipping_total,
					// Only if we have shipping as a line item -- Edge case otherwise
					'description' => $order->get_meta( 'weeconnectpay_shipping_line_item_name' ),
					'currency'    => strtolower( $order->get_currency() ),
					'quantity'    => 1,
				)
			);
		}

		return $shipping_item;
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	public function get_woocommerce_thank_you_url( WC_Order $order ) {
		return str_replace( 'http:', 'https:', $order->get_checkout_order_received_url() );
	}

	/**
	 * @return string|string[]
	 */
	public function get_woocommerce_callback_url() {
		$permalink = get_option('permalink_structure');
		$pattern = "/^\/index\.php/";
		$prefix = preg_match( $pattern, $permalink) ? '/index.php/' : '/';
		$no_query_url = strtok(home_url( $prefix ) , '?');
		return str_replace( 'http:', 'https:', $no_query_url . 'wc-api/callback_wc_gateway_weeconnectpay' );
	}

	/**
	 * @param WC_Order_Item $item
	 * @param WC_Order $order
	 *
	 * @return array
	 * @throws UnsupportedOrderItemTypeException
	 */
	public function get_clover_formatted_item( \WC_Order_Item $item, WC_Order $order ): array {

		if (!method_exists($item,'get_total')){
			throw new UnsupportedOrderItemTypeException();
		}
		$active_price = $item->get_total(); // The product active raw price after discount
		$amount       = WeeConnectPayHelper::safe_amount_to_cents_int( $active_price );

		if ( $item->get_tax_status() === 'taxable' ) {
			$amount += WeeConnectPayHelper::safe_amount_to_cents_int( $this->get_individual_item_tax( $item ) );
		}
		$formatted_amount = (int) $amount;

		$item_array = array(
			'type'        => 'sku',
			'amount'      => $formatted_amount,
			'description' => WeeConnectPayHelper::name_and_qty_as_clover_line_desc( $item->get_name(), $item->get_quantity() ),
			'currency'    => strtolower( $order->get_currency() ),
			'quantity'    => 1,
		);

		if (!method_exists($item,'get_product')){
			throw new UnsupportedOrderItemTypeException();
		}
		// Check for import
		$product             = $item->get_product();
		$weeconnectpay_metadata = json_decode( json_encode( $product->get_meta( 'weeconnectpay_metadata' ) ) ); // Because PHP is a primitive language
		if ( isset( $weeconnectpay_metadata ) && '' !== $weeconnectpay_metadata ) {
			if ( true === $weeconnectpay_metadata->clover_imported ) {
				if ( isset( $weeconnectpay_metadata->clover->product_id ) ) {

					$item_array = array(
						'type'        => 'sku',
						'amount'      => WeeConnectPayHelper::safe_amount_to_cents_int( $item->get_total() / $item->get_quantity() ),
						// The product active raw price after discount,
						'description' => $item->get_name(),
						'currency'    => strtolower( $order->get_currency() ),
						'quantity'    => $item->get_quantity(),
						'id'          => $weeconnectpay_metadata->clover->product_id,
						'tax_rates'   => $weeconnectpay_metadata->clover->product_tax_rates,
					);
				} else {
				}
			} else {
			}
		}

		return $item_array;
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return array[]
	 * @throws MissingStateException
	 *
	 * @updated 2.0.6
	 */
	public function prepare_clover_shipping_info( WC_Order $order ): array {

		// Fallback for missing "optional" county for GB
		if ( !$order->get_shipping_state() ) {
			throw new MissingStateException(
					__( 'A shipping address state, county or province is required for this gateway.', 'weeconnectpay' ),
				ExceptionCode::MISSING_SHIPPING_STATE
			);
		}

		return array(
			'shipping' => array(
				'address' => array(
					'city'        => $order->get_shipping_city(),
					'country'     => $order->get_shipping_country(),
					'line1'       => $order->get_shipping_address_1(),
					'line2'       => $order->get_shipping_address_2(),
					'postal_code' => $order->get_shipping_postcode(),
					'state'       => $order->get_shipping_state(),

				),
				'name'    => $order->get_formatted_shipping_full_name(),
				'phone'   => $order->get_billing_phone(),
			),
		);
	}


	public function orders_totals_matches( $woocommerce_order_total, $clover_order_total ) {
		// We don't want to cast anything because of binary representation is imprecise.
		return $woocommerce_order_total === $clover_order_total;
	}

	/**
	 * @param array $clover_order
	 * @param int $amount_in_cents
	 *
	 * @return bool
	 */
	public function is_matching_amounts( array $clover_order, int $amount_in_cents ): bool {
		$clover_total = $this->get_clover_order_total_from_products( $clover_order );

		if ( $this->orders_totals_matches( $amount_in_cents, $clover_total ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Prepare the order payload, we also use this order to validate amounts beforehand
	 *
	 * @param WC_Order $order
	 * @param string $customerId
	 *
	 * @return array
	 * @throws MissingStateException
	 * @throws UnsupportedOrderItemTypeException
	 */
	public function prepare_clover_order( WC_Order $order, string $customerId): array {
		$clover_discounts   = $this->prepare_clover_discounts( $order );
		$clover_order_items = $this->format_clover_order_items( $order );

		$clover_order = array(
			'items'    => $clover_order_items,
			'currency' => strtolower( $order->get_currency() ),
			'email'    => $order->get_billing_email() ?? null,
			'customer' => $customerId
		);
		if ( $order->has_shipping_address() ) {
			$shipping_info = $this->prepare_clover_shipping_info( $order );
			$clover_order  = array_merge( $clover_order, $shipping_info );
		}

		return $clover_order;
	}

	// Since we want to use the settings class as a single source of truth, we should access it directly and not as a local instance of itself.
//	/**
//	 * @param IntegrationSettings $integrationSettings
//	 *
//	 * @return WeeConnectPayAPI
//	 */
//	public function setIntegrationSettings( IntegrationSettings $integrationSettings ): WeeConnectPayAPI {
//		$this->integrationSettings = $integrationSettings;
//
//		return $this;
//	}
//
//	/**
//	 * @return IntegrationSettings
//	 */
//	public function getIntegrationSettings(): IntegrationSettings {
//		return $this->integrationSettings;
//	}

	private function api_get( $url, $args = array() ) {
		$args = $this->set_request_args( $args );

		$response = wp_remote_get( $url, $args );

		try {
			return $this->handle_response( $response );
		} catch ( WeeConnectPayException $e ) {
			if ( is_admin() /*$e->shouldDisplayAdminNoticeError()*/ ) {
				return new WP_Error( 'wc-order', $e->getMessage()/*__( 'Order has been already refunded', 'weeconnectpay' )*/ );
			}
			return false;
		} catch ( \Exception $e ){
			// In case an exception other than one of ours happen,
			// we still want to end the refund attempt properly
			return false;
		}
	}

	private function api_post( $url, $data ) {
		$args     = $this->set_request_args( $data );
		$response = wp_remote_post( $url, $args );

		try {
			return $this->handle_response( $response );
		} catch ( WeeConnectPayException $e ) {
			if ( is_admin() /*$e->shouldDisplayAdminNoticeError()*/ ) {

				return new WP_Error( 'wc-order', $e->getMessage()/*__( 'Order has been already refunded', 'weeconnectpay' )*/ );
			}

			return false;
		}
	}

	/**
	 * @param $data
	 *
	 * @return array
	 */
	private function set_request_args( $data = null ) {

		return array(
			'headers' => array(
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => "Bearer {$this->integrationSettings->getAccessToken()->getToken()}",
				'Integration-Uuid' => $this->integrationSettings->getIntegrationUuid(),
			),
			'body'    => $data,
			'timeout' => 60,
		);
	}

	/**
	 * @param $response
	 *
	 * @return bool|mixed Returns an actual response or false if an error occurs.
	 * @throws WeeConnectPayException
	 */
	private function handle_response( $response ) {
		if ( is_wp_error( $response ) ) {
			if ( $this->is_debug ) {
				LogService::debug('WP Error in API response: ' . json_encode($response));
			}

			return false;
		}
		$response_content = json_decode( wp_remote_retrieve_body( $response ) );
		$http_code        = wp_remote_retrieve_response_code( $response );
		if ( is_array( $response ) ) {
			if ( 200 === $http_code || 201 === $http_code ) {
				return $response_content;
			} else {
				if ( isset( $response_content->error, $response_content->error->message ) ) {
					throw new WeeConnectPayException( $response_content->error->message);
				} else {
					return false;
				}
			}
		}
		return false;
	}

	public function create_order( $clover_order ) {

		$endpoint = $this->url_api . '/v1/woocommerce/orders';
		$body     = $clover_order;

		$response = wp_remote_post(
			$endpoint,
			array(
				'method'      => 'POST',
				'timeout'     => 60,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
					'Authorization' => "Bearer {$this->integrationSettings->getAccessToken()->getToken()}",
					'Integration-Uuid' => $this->integrationSettings->getIntegrationUuid(),

				),
				'body'        => wp_json_encode( $body ),
				'cookies'     => array(),
			)
		);

		// Response Body
		$body = wp_remote_retrieve_body( $response );

		// On success (i.e. `$body` is a valid JSON string), `$response_data` would be an array
		return ( ! is_wp_error( $response ) ) ? json_decode( $body, true ) : null;

	}

	public function refund_woocommerce_order( $refund_payload ) {

		$endpoint = $this->url_api . '/v1/woocommerce/orders/refund';

		$response = $this->api_post( $endpoint, json_encode( $refund_payload ) );

		return $response;
	}

	public function set_integration_name_from_option_name( $integration_option_name ): string {
		if ( 'woocommerce_weeconnectpay_settings' === $integration_option_name ) {
			return 'woocommerce';
		} else {
			return 'unknown';
		}
	}

	/**
	 * @param WC_Order $order
	 * @param string $customerId
	 *
	 * @return mixed|null
	 * @throws MissingStateException
	 * @throws UnsupportedOrderItemTypeException|WeeConnectPayException
     * @updated 3.11.1
	 */
	public function prepare_order_for_payment( WC_Order $order, string $customerId) {
		$payment_processing_source      = 'clover';
		$payment_processing_source_type = 'ecom';

			if ( 'clover' === $payment_processing_source && 'ecom' === $payment_processing_source_type ) {

				// If taxes are included -- To help with future order manipulations
				$order->add_meta_data( 'weeconnectpay_tax_included', true );
				$order->add_meta_data( 'weeconnectpay_merged_qty', true );
				// To be able to properly refund shipping as line items if it is and keep a normal refund flow otherwise.
				$order->add_meta_data( 'weeconnectpay_shipping_as_clover_line_item', true );
				// To be able to fetch the name regardless of localization -- for refunds etc
				$order->add_meta_data( 'weeconnectpay_shipping_line_item_name', 'Shipping Fees' );
				$order->save_meta_data();

				$amount_in_cents = WeeConnectPayHelper::safe_amount_to_cents_int( $order->get_total() );

				$woocommerce_internal_order = $this->prepare_internal_woocommerce_order( $order, $payment_processing_source, $payment_processing_source_type );

				$clover_order = $this->prepare_clover_order( $order, $customerId );
				
				// DEBUG START

				$allCustomTenders = WeeConnectPayCustomTenderHelper::getCustomTenders($order);
				LogService::debug('All custom tenders: ' . json_encode($allCustomTenders));
				// DEBUG END

                // If Freebees has a custom tender in this order -- We can calculate the amount they are taking and see if it matches
                $freebeesTotalDifference = WeeConnectPayCustomTenderHelper::getCustomTendersPendingTotal($order, 'Freebees');
                LogService::debug('Freebees total difference: ' . $freebeesTotalDifference);
				if ($freebeesTotalDifference > 0) {
                    // Add the total of the Freebees custom tender to the amount in cent, since they already deducted it
                    $amountToChargeToCreditCard = $amount_in_cents + $freebeesTotalDifference;
					LogService::debug('Total amount ($order->total + pending custom tenders) for the Clover order should be: ' . $amountToChargeToCreditCard);
                    $is_matching_amounts = $this->is_matching_amounts( $clover_order, $amountToChargeToCreditCard );
                } else {
                    $is_matching_amounts = $this->is_matching_amounts( $clover_order, $amount_in_cents );
                }

                if ( $is_matching_amounts ) {
					$order_creation_response = $this->create_order(
						array(
							'pp_order'               => $clover_order,
							'woocommerce_order_info' => $woocommerce_internal_order,
							'pp_order_id'            => $order->get_meta( 'weeconnectpay_clover_order_uuid' ),
						)
					);

					if ( ! isset( $order_creation_response['id'] ) ) {
						return null;
					} else {
						return $order_creation_response;
					}
				} else {
                    $clover_total = $this->get_clover_order_total_from_products($clover_order); // Amount in cents
                    $difference_in_cents = $clover_total - $amount_in_cents;
                    $difference_in_dollars = WeeConnectPayUtilities::centsToDollars(abs($difference_in_cents));

                    // Create structured comparison data
                    $comparison = [
                        'difference' => [
                            'amount' => WeeConnectPayUtilities::centsToDollars(abs($difference_in_cents)),
                            'cents' => $difference_in_cents
                        ],
                        'woocommerce' => [
                            'order_id' => $order->get_id(),
                            'subtotal' => $order->get_subtotal(),
                            'tax_total' => $order->get_total_tax(),
                            'shipping_total' => $order->get_shipping_total(),
                            'shipping_tax' => $order->get_shipping_tax(),
                            'discount_total' => $order->get_total_discount(),
                            'fees' => array_map(function($fee) {
                                return [
                                    'name' => $fee->get_name(),
                                    'amount' => $fee->get_total(),
                                    'tax' => $fee->get_total_tax(),
                                    'total' => $fee->get_total() + $fee->get_total_tax(),
                                    'tax_status' => $fee->get_tax_status()
                                ];
                            }, array_values($order->get_fees())),
                            'final_total' => $order->get_total()
                        ],
                        'clover' => [
                            'items' => $clover_order['items'],
                            'final_total' => WeeConnectPayUtilities::centsToDollars($clover_total)
                        ]
                    ];

                    // Create log message (single line for better log readability)
                    $logMessage = sprintf(
                        'Amount mismatch - Difference: $%s | WC Order #%d (Subtotal: $%s, Tax: $%s, Total: $%s) | Clover (Subtotal includes $%s tax, Total: $%s)',
                        WeeConnectPayUtilities::centsToDollars(abs($difference_in_cents)),
                        $order->get_id(),
                        number_format((float)$order->get_subtotal(), 2),
                        number_format((float)$order->get_total_tax(), 2),
                        number_format((float)$order->get_total(), 2),
                        number_format((float)$order->get_total_tax(), 2),
                        WeeConnectPayUtilities::centsToDollars($clover_total)
                    );

                    // Create detailed message for exception
                    $detailedMessage = $this->formatComparisonForException($comparison);

                    LogService::warning("Line item and order total amounts do not match: " . $logMessage);
                    LogService::debug("Detailed comparison: " . json_encode($comparison));

                    throw new WeeConnectPayException(
                        $logMessage,
                        ExceptionCode::ORDER_LINE_ITEM_TOTAL_MISMATCH
                    );
				}
			} else {
				// woocommerce but not clover ecom
				return null;
			}
	}

	public function get_expanded_merchant_inventory() {

		$endpoint = $this->url_api . '/v1/clover/merchants/current/expanded_items';

		return $this->api_get( $endpoint );
	}

	/**
	 * Retrieves the API domain based on the environment
	 *
	 * @since 1.4.0
	 * @return string
	 */
	public static function getApiDomain(): string {
		$wp_env = WeeConnectPayUtilities::get_wp_env();

		switch ( $wp_env ) {
            case 'gitpod':
                return GITPOD_WCP_BACKEND_WORKSPACE_URL ?? 'GITPOD_URL_NOT_SET';
			case 'local':
			case 'development':
				return self::LOCAL_API_URL;
			case 'staging':
				return self::STAGING_API_URL;
			case 'production':
			default:
				return self::PRODUCTION_API_URL;
		}
	}

    private function get_fees_as_clover_order_items(WC_Order $order): array
    {
        $fees = $order->get_fees();
        $feeArr = [];

        foreach ($fees as $fee) {

            $amount = WeeConnectPayHelper::safe_amount_to_cents_int($fee->get_total());

            // If the fee is set as taxable we'll include the price in the line item
            if ($fee->get_tax_status() === 'taxable') {
                $amount += WeeConnectPayHelper::safe_amount_to_cents_int($fee->get_total_tax());
            }

            $item = array(
                'type' => 'sku',
                'amount' => $amount,
                'description' => WeeConnectPayHelper::name_and_qty_as_clover_line_desc($fee->get_name(), $fee->get_quantity()),
                'currency' => strtolower($order->get_currency()),
                'quantity' => 1,
            );

            $feeArr[] = $item;
        }

        return $feeArr;
    }

    /**
     * Formats the comparison data into a detailed message for the exception
     * 
     * @param array $comparison The comparison data structure
     * @return string
     */
    private function formatComparisonForException(array $comparison): string 
    {
        $message = "There is a difference of $" . $comparison['difference']['amount'] . " between the amounts.\n\n";
        
        // WooCommerce breakdown
        $message .= "WooCommerce Order (#" . $comparison['woocommerce']['order_id'] . ") Breakdown:\n";
        $message .= "- Subtotal: $" . number_format((float)$comparison['woocommerce']['subtotal'], 2) . "\n";
        $message .= "- Tax: $" . number_format((float)$comparison['woocommerce']['tax_total'], 2) . "\n";
        
        if ($comparison['woocommerce']['shipping_total'] > 0) {
            $message .= "- Shipping: $" . number_format((float)$comparison['woocommerce']['shipping_total'], 2) . "\n";
            if ($comparison['woocommerce']['shipping_tax'] > 0) {
                $message .= "- Shipping Tax: $" . number_format((float)$comparison['woocommerce']['shipping_tax'], 2) . "\n";
            }
        }
        
        if ($comparison['woocommerce']['discount_total'] > 0) {
            $message .= "- Discount: -$" . number_format((float)$comparison['woocommerce']['discount_total'], 2) . "\n";
        }
        
        $message .= "= Final Total: $" . number_format((float)$comparison['woocommerce']['final_total'], 2) . "\n\n";

        // Clover breakdown
        $message .= "Clover Order Breakdown:\n";
        foreach ($comparison['clover']['items'] as $item) {
            $message .= "- " . $item['description'] . "\n";
            $message .= "  • Amount: $" . WeeConnectPayUtilities::centsToDollars($item['amount']) . "\n";
            if (isset($item['quantity']) && $item['quantity'] > 1) {
                $message .= "  • Quantity: " . $item['quantity'] . "\n";
            }
        }
        $message .= "= Final Total (includes $" . number_format((float)$comparison['woocommerce']['tax_total'], 2) . " tax): $" . $comparison['clover']['final_total'] . "\n\n";

        $message .= "Note: All Clover amounts include tax to prevent rounding discrepancies.\n";
        
        return $message;
    }
}
