<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace WeeConnectPay\WordPress\Plugin\includes;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Exception;
use WC_Gateway_Weeconnectpay;
use WeeConnectPay\Exceptions\SettingsInitializationException;
use WeeConnectPay\Integrations\GoogleRecaptcha;
use WeeConnectPay\Integrations\IntegrationSettings;
use WeeConnectPay\Integrations\LogService;
//error_log( 'in WooCommerce Blocks gateway class file' );

	class WeeConnectPayMethod extends AbstractPaymentMethodType {

		/**
		 * @var $name string
		 */
		protected $name = 'weeconnectpay';

		/**
		 * Constructor
		 */
		public function __construct() {
		}

		public function initialize(): void {
		}

		public function is_active(): bool {
//			return $this->gateway->is_available();
			return true;
		}

		public function get_payment_method_script_handles(): array {


			$cloverSdkScriptName     = 'weeconnectpay-clover-sdk-js';
			$paymentFieldsBlocks = 'weeconnectpay-blocks-payment-fields-js';

			$cloverSdkUrl            = ( WeeConnectPayUtilities::get_wp_env() === 'production' ) ? 'https://checkout.clover.com/sdk.js' : 'https://checkout.sandbox.dev.clover.com/sdk.js';
			wp_register_script( $cloverSdkScriptName, $cloverSdkUrl, [], null, true ); // not versioned because we don't control the versioning of this script
			wp_register_script( $paymentFieldsBlocks, WEECONNECTPAY_PLUGIN_URL . 'payment-fields-blocks/assets/js/frontend/blocks.js', array($cloverSdkScriptName),wp_rand(), true );




			$paymentFieldsStyleName = 'weeconnectpay-payment-fields-css';
			wp_register_style( $paymentFieldsStyleName, WEECONNECTPAY_PLUGIN_URL . 'site/css/weeconnect-public.css', array(), WEECONNECT_VERSION );
			wp_enqueue_style( $paymentFieldsStyleName, '', array(), WEECONNECT_VERSION );


			$googleRecaptchaScriptHandle = 'weeconnectpay-google-recaptcha';
			if (GoogleRecaptcha::isEnabledAndReady()) {
				// No dependencies, the only time we use it is onPaymentSetup, which gives a chance for EVERYTHING to load
				wp_register_script( $googleRecaptchaScriptHandle, GoogleRecaptcha::getSdkSrc(), [],WEECONNECT_VERSION, true );
				return [ $cloverSdkScriptName, $googleRecaptchaScriptHandle, $paymentFieldsBlocks ];
			} else {
				return [ $cloverSdkScriptName, $paymentFieldsBlocks ];
			}

		}

//		public function get_payment_method_script_handles_for_admin(): array {
//			return [];
//		}

		public function get_payment_method_data(): array {

			$integrationSettings = new IntegrationSettings();

			try {
				$gatewayWcSettingsData = array(
					'clover'          => [
						'pakms'         => $integrationSettings->getPublicAccessKey(),
						'locale' => WeeConnectPayUtilities::getLocale(),
                        'merchantId' => $integrationSettings->getCloverMerchant()->getUuid()
					],
					'woocommerce'     => [
						'gateway' => [
							'supports' => [
								'products',
								'refunds'
							],
							'title'    => $integrationSettings->getWoocommerceGatewayTitle()
						]
					],
					'wordpress'       => [
						'locale' => WeeConnectPayUtilities::getLocale(),
					],
					'googleRecaptcha' => [
						'isEnabled' => $integrationSettings->getGoogleRecaptchaOrDefault(),
						'siteKey'   => $integrationSettings->getGoogleRecaptchaSiteKeyOrDefault()
					]
				);

//				error_log( 'gatewayWcSettingsData: '.json_encode( $gatewayWcSettingsData) );

				return $gatewayWcSettingsData;

			} catch ( SettingsInitializationException $e ) {
				LogService::error('SettingsInitializationException in get_payment_method_data: ' . $e->getMessage());

				return [];
			} catch ( Exception $exception ) {
				LogService::error('Exception in get_payment_method_data: ' . $exception->getMessage());

				return [];
			}
		}
	}

