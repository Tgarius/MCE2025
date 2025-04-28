<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions
// phpcs:disable WordPress.Files.FileName.InvalidClassFileName
// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode
// phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase

use WeeConnectPay\CloverMerchant;
use WeeConnectPay\Exceptions\Codes\ExceptionCode;
use WeeConnectPay\Exceptions\WeeConnectPayException;
use WeeConnectPay\Integrations\AdminPanel;
use WeeConnectPay\Integrations\Authentication;
use WeeConnectPay\Integrations\IntegrationSettings;
use WeeConnectPay\Integrations\PaymentFields;
use WeeConnectPay\StandardizedResponse;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayAPI;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayCustomTenderHelper;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayHelper;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayOrderProcessor;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayUtilities;
use WeeConnectPay\Integrations\LogService;

if ( ! class_exists( WC_Payment_Gateway::class ) ) {
	return null;
}

/**
 * Handles the WeeConnectPay/Clover/WooCommerce payment gateway
 */
class WC_Gateway_Weeconnectpay extends WC_Payment_Gateway {
	/**
	 * Instance of the WeeConnectPay API
	 * @var WeeConnectPayAPI $api
	 */
	private WeeConnectPayAPI $api;
	/**
	 * @var string
	 */
	private string $url_api;
	/**
	 * @var string
	 */
	private string $integration_id;

    /**
	 * @return WeeConnectPayAPI
	 */
	public function getApi(): WeeConnectPayAPI {
		return $this->api;
	}

	/**
	 * @param WeeConnectPayAPI $api
	 *
	 * @return WC_Gateway_Weeconnectpay
	 */
	public function setApi( WeeConnectPayAPI $api ): WC_Gateway_Weeconnectpay {
		$this->api = $api;

		return $this;
	}

	private bool $is_debug = true;


	/**
	 * @var IntegrationSettings $integrationSettings
	 */
	private IntegrationSettings $integrationSettings;

	/**
	 * @return IntegrationSettings
	 */
	public function getIntegrationSettings(): IntegrationSettings {
		return $this->integrationSettings;
	}

	/**
	 * @param IntegrationSettings $integrationSettings
	 *
	 * @return WC_Gateway_Weeconnectpay
	 */
	public function setIntegrationSettings( IntegrationSettings $integrationSettings ): WC_Gateway_Weeconnectpay {
		$this->integrationSettings = $integrationSettings;

		return $this;
	}

	/**
	 * @updated 3.3.0
	 * @throws \WeeConnectPay\Exceptions\SettingsInitializationException
	 */
	public function __construct() {
		$this->id                 = 'weeconnectpay';
		$this->icon              = '';
		$this->has_fields        = true;
		$this->method_title      = __( 'Clover via WeeConnectPay', 'weeconnectpay' );
		$this->method_description = __(
			'Simplify online payments by adding the Clover payment option to your shopping cart. Then you will see your payments in real time on your Clover web portal.'
			, 'weeconnectpay' );
		$this->supports          = array(
			'refunds',
			'products'
        );

		$this->init_form_fields();
		$this->init_settings();

		try {
			$integration_settings = new IntegrationSettings();

			$integration_settings->getIntegrationUuid();
			// Save the integration settings as an attribute on this class
			$this->setIntegrationSettings( $integration_settings );
		} catch ( WeeConnectPayException $e ) {
			LogService::error( 'WeeConnectPay: Exception in gateway constructor. Message: ' . $e->getMessage() );
			$integration_settings = IntegrationSettings::reinitialize();
			$this->setIntegrationSettings( $integration_settings );
		}

		try {
			// Save the API as an attribute on this class
			$this->setApi( new WeeConnectPayAPI() );
		} catch ( WeeConnectPayException $e ) {
			LogService::error( 'WeeConnectPay: Exception in gateway constructor. Message: ' . $e->getMessage() );
			return StandardizedResponse::emitError( $e->toObject() );
		}

		$this->url_api        = $this->api->url_api;
		$this->integration_id = $this->integrationSettings->getIntegrationUuid();

		$this->title       = $this->get_option( 'title' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->description = $this->get_option( 'description' );

		//Runs when we update the gateway options through woocommerce -- Used for options saved using our DB structure and not WooCommerce
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'update_gateway_options'
		) );

		//Runs when we update the gateway options through woocommerce
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		// API Callback
		add_action( 'woocommerce_api_callback_' . strtolower( get_class( $this ) ), array(
			$this,
			'weeconnectpay_callback_handler'
		) );

		add_action( 'woocommerce_after_checkout_validation', array( $this, 'maybe_add_wc_notice' ), 10, 2 );
		add_action( 'woocommerce_checkout_process', array( $this, 'maybe_add_wc_notice' ), 10, 2 );

		// Runs when trying to login with Clover.
		add_action( 'woocommerce_sections_checkout',  array( $this, 'display_clover_login_notice' ) );

        // Remove the old action and add meta box instead
        add_action( 'add_meta_boxes', array( $this, 'add_weeconnectpay_charges_meta_box' ) );

        add_action( 'woocommerce_checkout_order_processed', array($this, 'weeconnectpay_maybe_handle_zero_total_order'), 10, 1 );
	}

    function weeconnectpay_maybe_handle_zero_total_order( $order_id ) {

        // Get the order object.
        $order = wc_get_order( $order_id );

        // Check if the order is using your payment gateway.
        // Adjust 'weeconnectpay' to your actual gateway identifier.
//        if ( 'weeconnectpay' !== $order->get_payment_method() ) {
//            return;
//        }

        // Check if the order total is 0 OR if a gift card custom tender is present.
        // For example, you might be storing a meta flag when a gift card is applied.
        $pendingCustomTenderTotal = WeeConnectPayCustomTenderHelper::getCustomTendersPendingTotal($order);

        if ($order->get_total() > 0) {
            LogService::debug('Skipping custom logic of weeconnectpay_maybe_handle_zero_total_order -- order total is > 0');
            return;
        }

        if ($pendingCustomTenderTotal <= 0) {
            LogService::debug('Skipping custom logic of weeconnectpay_maybe_handle_zero_total_order -- no pending custom tenders present');
            return;
        }


        $integrationSettings = new IntegrationSettings();
        $processor = new WeeConnectPayOrderProcessor( $integrationSettings );

        // If additional POST data is needed for processing, provide it here.
        // In many cases for zero-total orders the POST data may not be available so you can pass an empty array.
        $postData = [];

        // Run your processing routine.
        $result = $processor->processOrderPayment( $order, $postData );

        // Optionally log the result for debugging.
        LogService::info( 'WeeConnectPay processed order via woocommerce_checkout_order_processed hook: ' . json_encode( $result ) );
    }


    public function update_gateway_options() {
		// Get the value of the "Post Tokenization Verification" setting from $_POST
		$postTokenizationVerification = $_POST['woocommerce_weeconnectpay_post_tokenization_verification'] ?? '0';
		$googleRecaptchaEnabled = $_POST['woocommerce_weeconnectpay_google_recaptcha_enabled'] ?? '0';
		$googleRecaptchaSiteKey = $_POST['woocommerce_weeconnectpay_google_recaptcha_site_key'] ?? '';
		$googleRecaptchaSecretKey = $_POST['woocommerce_weeconnectpay_google_recaptcha_secret_key'] ?? '';
		$googleRecaptchaMinHumanScoreThreshold = $_POST['woocommerce_weeconnectpay_min_human_score_threshold'] ?? 0.5;
		$honeypotFieldEnabled = $_POST['woocommerce_weeconnectpay_honeypot_field_enabled'] ?? '0';
		$debugMode = $_POST['woocommerce_weeconnectpay_debug_mode'] ?? '0';

        try {
            $integrationSettings = new IntegrationSettings();

            // Post Tokenization Verification
            $integrationSettings->setPostTokenizationVerification($postTokenizationVerification);

            // Google reCAPTCHA v3 enabled?
            $integrationSettings->setGoogleRecaptcha($googleRecaptchaEnabled);

            // Google reCAPTCHA v3 Site Key
            $integrationSettings->setGoogleRecaptchaSiteKey($googleRecaptchaSiteKey);

            // Google reCAPTCHA v3 Secret Key
            $integrationSettings->setGoogleRecaptchaSecretKey($googleRecaptchaSecretKey);

            // Google reCAPTCHA v3 Minimum Score Human Threshold
            $integrationSettings->setGoogleRecaptchaMinimumHumanScoreThreshold($googleRecaptchaMinHumanScoreThreshold);

            // Honeypot Field
            $integrationSettings->setHoneypotField($honeypotFieldEnabled);

            // Debug Mode
            $integrationSettings->setDebugMode($debugMode);

        } catch (WeeConnectPayException $e) {
            LogService::error('Failed to save WooCommerce gateway options: ' . $e->getMessage());
        }
	}

	/**
	 * Override the parent admin_options method with our app.
	 *
	 * @since 1.4.0
	 * @updated 3.2.1
	 */
	public function admin_options() {
		try {
			if (!$this->integration_id) {
				LogService::info('Setting the integration id during admin_options hook');
				$this->integration_id = $this->integrationSettings->getIntegrationUuid();
				LogService::info('Finished setting the integration id during admin_options hook. ID: ' . json_encode($this->integration_id));
			}

			$isAuthenticated = $this->integrationSettings->isAuthValid();
			$vue_data = array(
				'redirectUrl' => $this->integrationSettings::redirectUrl(),
				'pluginUrl' => WEECONNECTPAY_PLUGIN_URL,
				'debugMode' => $this->integrationSettings->getDebugModeOrDefault(),
				'isAuthenticated' => $isAuthenticated,
				'restUrl' => rtrim(get_rest_url(), '/'),
				'nonce' => wp_create_nonce('wp_rest')
			);

			// GitPod support
			if (getenv('GITPOD_WORKSPACE_URL')) {
				/** @noinspection PhpUndefinedConstantInspection */
				$vue_data['gitpodBackendWorkspaceUrl'] = GITPOD_WCP_BACKEND_WORKSPACE_URL ?? 'GITPOD_URL_NOT_SET';
			}

			// Hide the save button from the payment gateway settings form if not authenticated
			if (!$isAuthenticated) {
				global $hide_save_button;
				$hide_save_button = true;
			}

			// Only show WooCommerce settings and log viewer if authenticated
			if ($isAuthenticated) {
				// Show the WooCommerce settings first until we replace it completely with the Vue app
				parent::admin_options();

				// If debug mode is enabled, show the log viewer below the WooCommerce settings
				if ($this->integrationSettings->getDebugModeOrDefault()) {
					echo '<div class="weeconnectpay-log-viewer-container" style="margin-top: 20px;">';
					echo '</div>';
				}
			}

			$admin_panel = new AdminPanel();
			$admin_panel->init($vue_data);


		} catch (Exception $e) {
			LogService::error('Exception in admin_options: ' . $e->getMessage());
		}
	}

	function maybe_add_wc_notice( $fields, WP_Error $errors = null ) {
		global $wp;

		// Reset prevent submit
		$_POST['weeconnectpay_prevent_submit'] = false;

		if ( wc_notice_count( 'error' ) !== 0 ) {
			return;
		}

		// Untouched form
		if ( isset( $_POST['weeconnectpay_prevent_submit_empty_cc_form'] ) ) {
			wc_add_notice( __( "Please enter your payment information.", 'weeconnectpay' ), 'error' );
			$_POST['weeconnectpay_prevent_submit'] = true;
		}

		// Card Number
		if ( isset( $_POST['weeconnectpay_prevent_submit_card_number_error_cc_form'] ) ) {
			wc_add_notice( __( "Please enter a valid credit card number.", 'weeconnectpay' ), 'error' );
			$_POST['weeconnectpay_prevent_submit'] = true;
		}
		if ( isset( $_POST['weeconnectpay_prevent_submit_empty_card_number_cc_form'] ) ) {
			wc_add_notice( __( "Please enter a valid credit card number.", 'weeconnectpay' ), 'error' );
			$_POST['weeconnectpay_prevent_submit'] = true;
		}

		// Expiry Date
		if ( isset( $_POST['weeconnectpay_prevent_submit_date_error_cc_form'] ) ) {
			wc_add_notice( __( "Please enter a valid credit card expiry date.", 'weeconnectpay' ), 'error' );
			$_POST['weeconnectpay_prevent_submit'] = true;
		}
		if ( isset( $_POST['weeconnectpay_prevent_submit_empty_date_cc_form'] ) ) {
			wc_add_notice( __( "Please enter a valid credit card expiry date.", 'weeconnectpay' ), 'error' );
			$_POST['weeconnectpay_prevent_submit'] = true;
		}

		// CVV
		if ( isset( $_POST['weeconnectpay_prevent_submit_cvv_error_cc_form'] ) ) {
			wc_add_notice( __( "Please enter a valid credit card CVV number.", 'weeconnectpay' ), 'error' );
			$_POST['weeconnectpay_prevent_submit'] = true;
		}
		if ( isset( $_POST['weeconnectpay_prevent_submit_empty_cvv_cc_form'] ) ) {
			wc_add_notice( __( "Please enter a valid credit card CVV number.", 'weeconnectpay' ), 'error' );
			$_POST['weeconnectpay_prevent_submit'] = true;
		}

		// Postal Code
		if ( isset( $_POST['weeconnectpay_prevent_submit_postal_code_error_cc_form'] ) ) {
			wc_add_notice( __( "Please enter a valid credit card postal code.", 'weeconnectpay' ), 'error' );
			$_POST['weeconnectpay_prevent_submit'] = true;
		}
		if ( isset( $_POST['weeconnectpay_prevent_submit_empty_postal_code_cc_form'] ) ) {
			wc_add_notice( __( "Please enter a valid credit card postal code.", 'weeconnectpay' ), 'error' );
			$_POST['weeconnectpay_prevent_submit'] = true;
		}
	}

	/**
	 * Displays a confirmation or error message while trying to connect to the Clover API.
	 *
	 * @since 3.11.1
	 * @access public
	 *
	 * @return void
	 */
	public function display_clover_login_notice() {
		// Bails out if it's not the right context.
		if ( ! isset( $_GET['section'], $_GET['weeconnectpay_status'] ) || 'weeconnectpay' !== $_GET['section'] ) {
			return;
		}

		// Defines specific status messages based on the value returned by the 'weeconnectpay_status' parameter.
		$status_messages = array(
			'connected' => __( 'The connection with Clover has been successfully established!', 'weeconnectpay' ),
			'error'     => __( 'An error occurred while trying to establish a connection with Clover, please try again in a few minutes.', 'weeconnectpay' ),
		);

		$notice_class   = in_array( $_GET['weeconnectpay_status'], array( 'connected' ) ) ? 'notice-success' : 'notice-error';
		$notice_message = $status_messages[ $_GET['weeconnectpay_status'] ] ?? $status_messages['error']; // Defaults to 'error' if the status message can't be found.
		echo '<div class="notice is-dismissible ' . sanitize_html_class( $notice_class ) . '"><p>' . esc_html( $notice_message ) . '</p></div>';
	}

	/**
	 * Generates the WeeConnectPay settings form fields for WooCommerce to use in the payment gateway settings
	 * @return void
	 * @since 1.0.0
	 * @updated 3.3.0
	 */
	public function init_form_fields() {

        $integrationSettings =  new IntegrationSettings();
		$isPostTokenizationVerificationActive = $integrationSettings->getPostTokenizationVerificationOrDefault();
        $isGoogleRecaptchaActive = $integrationSettings->getGoogleRecaptchaOrDefault();
        $googleRecaptchaSiteKey = $integrationSettings->getGoogleRecaptchaSiteKeyOrDefault();
		$googleRecaptchaSecretKey = $integrationSettings->getGoogleRecaptchaSecretKeyOrDefault();
		$isHoneypotFieldActive = $integrationSettings->getHoneypotFieldOrDefault();

		$this->form_fields = array(
			'enabled'                        => array(
				'title'       => __( 'Enable', 'weeconnectpay' ),
				'label'       => __( 'Enable payment gateway', 'weeconnectpay' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			),
			'authorize_button'               => array(
				'title' => __( 'Authorize Plugin', 'weeconnectpay' ),
				'type'  => 'authorize_button',
			),
			'title'                          => array(
				'title'       => __( 'Title', 'weeconnectpay' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'weeconnectpay' ),
				'desc_tip'    => true,
				'default'     => __( 'Payment by Credit Card', 'weeconnectpay' ),
			),
			'post_tokenization_verification' => array(
				'title'       => __( 'Post-Tokenization Verification', 'weeconnectpay' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Post-Tokenization Verification', 'weeconnectpay' ),
				'default'     => 'no',
				'description' => __( 'When enabled, additional verification will be performed after card tokenization.', 'weeconnectpay' ),
			),
			'debug_mode' => array(
				'title'       => __( 'Debug Mode', 'weeconnectpay' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Debug Mode', 'weeconnectpay' ),
				'default'     => 'no',
				'description' => __( 'When enabled, debug information will be logged and can be viewed in the logs section below.', 'weeconnectpay' ),
			),
			'google_recaptcha_enabled' => array(
				'title'       => __( 'Google reCAPTCHA', 'weeconnectpay' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Google reCAPTCHA', 'weeconnectpay' ),
				'description' => __( 'Enable Google reCAPTCHA v3 for extra security. This new reCAPTCHA is completely hidden from the customer. A score value between 0 (100% automated) and 1 (100% human) will be added in the order notes for each payment attempt.', 'weeconnectpay' ),
				'desc_tip'    => true,
				'default'     => $isGoogleRecaptchaActive ? 'yes' : 'no',
			),
			'google_recaptcha_site_key' => array(
				'type'        => 'text',
				'title'       => __( 'Google reCAPTCHA Site Key', 'weeconnectpay' ),
				'description' => __( 'Don\'t have a site key and private key for this domain? <a href="https://www.google.com/recaptcha/admin/create" target="_blank">Click here</a> to set it up.', 'weeconnectpay' ),
				'default'     => $googleRecaptchaSiteKey,
			),
			'google_recaptcha_secret_key' => array(
				'type'        => 'password',
				'title'       => __( 'Google reCAPTCHA Secret Key', 'weeconnectpay' ),
				'default'     => $googleRecaptchaSecretKey,
			),
			'min_human_score_threshold' => array(
				'title'       => __( 'Google reCAPTCHA Minimum Human Score Threshold', 'weeconnectpay' ),
				'type'        => 'number',
				'description' => __( 'Enhance order security: Set a reCAPTCHA score threshold. The recommended default value is 0.5. Orders with scores below this setting will be considered as non-human order, the status will be set as "failed" in WooCommerce and no resource will be created in your Clover account.', 'weeconnectpay' ),
				'default'     => '0.5', // You can set a default value here.
				'desc_tip'    => true,
				'custom_attributes' => array(
					'step' => '0.1', // This sets the increment step to 0.1
					'min'  => '0',   // Minimum value
					'max'  => '1',   // Maximum value
				),
			),
			'honeypot_field_enabled' => array(
				'title'       => __( 'Honeypot Fields', 'weeconnectpay' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Honeypot Fields', 'weeconnectpay' ),
				'description' => __( 'As an additional bot detection step, hidden fields that are sometimes filled by bots will be added to the form.', 'weeconnectpay' ),
				'desc_tip'    => true,
				'default'     => $isHoneypotFieldActive ? 'yes' : 'no',
			),
		);
	}

	/**
	 * Screen button Field
	 */
	public function generate_authorize_button_html() {
		//$redirect_url = $this->url_api . '/login/clover?intent=authorize-redirect&integration_id=' . $this->integration_id;
		$redirect_url = IntegrationSettings::redirectUrl();
		try {
			$clover_merchant = $this->integrationSettings->getCloverMerchant();
		} catch ( Throwable $exception ) {
			LogService::error( "Error fetching the current merchant for display in plugin settings. Message: " . $exception->getMessage() );
		}


		?>
        <tr valign="top">
            <td colspan="2" class="">
                <div>
					<?php
					if ( isset( $clover_merchant ) ) {
						if ( $clover_merchant instanceof CloverMerchant ) {
							echo "<b><div>";
							esc_html_e( "Merchant Name: ", "weeconnectpay" );
							echo "</b>";
							esc_html_e( $clover_merchant->getName() );
							echo "</div>";
							echo "<b><div>";
							esc_html_e( "Merchant ID: ", "weeconnectpay" );
							echo "</b>";
							esc_html_e( $clover_merchant->getUuid() );
							echo "</div></br>";
						}
					}
					?>
                </div>
            </td>
            <td colspan="2" class="">
                <a href="<?php echo esc_url( $redirect_url ); ?>" class="button"><?php
					//					if ( $this->authVerifyHttpCode !== 200 ) {
					//						_e( 'Authorize Plugin', 'weeconnectpay' );
					//					} else {
					esc_html_e( 'Log in as another Clover merchant or employee', 'weeconnectpay' );
					//					}
					?></a>
            </td>
        </tr>
		<?php
	}


	/* @TODO: Checks to prevent loading the gateway if it shouldn't
	 * @BODY: To check: Physical Location, Currency, etc
	 */
	public function payment_fields() {

		global $woocommerce;
		try {
			$script_data = array(
				'pakms'  => $this->integrationSettings->getPublicAccessKey(),
				'locale' => WeeConnectPayUtilities::getLocale(),
				'amount' => $woocommerce->cart->total * 100,
                'siteKey' => $this->integrationSettings->getGoogleRecaptchaSiteKeyOrDefault()
			);

			$payment_fields = new PaymentFields();
			$payment_fields->init( $script_data );
		} catch ( Exception $exception ) {
			LogService::error( 'Exception caught in payment_fields: ' . $exception->getMessage() );
			return array('result' => 'fail', 'redirect' => '');
		}
	}

	/**
	 * Tells WooCommerce whether the gateway should be available IE:( In checkout / order pay, etc ).
	 * @return bool
	 * @since 1.3.7
	 */
	public function is_available(): bool {

		// If WeeConnectPay is not enabled, return.
		if ( 'no' === $this->enabled ) {
			return false;
		}


		if ( ! ( new IntegrationSettings() )->arePaymentProcessingSettingsReady() ) {
			return false;
		}

		// SSL ( Even though plugin no longer loads without it )
		if ( ! is_ssl() ) {
			return false;
		}

		return true;
	}


	/**
	 * @inheritDoc
	 * @updated 3.12.6
	 */
	public function process_payment( $order_id ): array {
        $order = wc_get_order( $order_id );
        $processor = new WeeConnectPayOrderProcessor( $this->integrationSettings );
        return $processor->processOrderPayment( $order, $_POST );
	}




	/**
	 * iFrame payment callback handler. Called from the WeeConnectPay API server after a user attempts to pay for their order through the iFrame.
	 *
	 * @updated 3.0.0
	 * @deprecated since 3.0.0
	 */
	public function weeconnectpay_callback_handler() {
		die();
	}

	/**
	 * @param $order_id
	 * @param $amount
	 * @param $reason
	 *
	 * @updated 3.7.0
	 *
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = null ) {
		LogService::info(sprintf(
			'Initiating refund for order #%d - Amount: %.2f, Reason: %s',
			$order_id,
			$amount ?? 0,
			$reason ?? 'not provided'
		));

		/** @TODO: Add wpdb prefix on order creation call */
		// Needed for the DB prefix for multi site
		global $wpdb;
		$order = new WC_Order( $order_id );

        // Check for custom tenders in the order
        $customTenders = WeeConnectPayCustomTenderHelper::getCustomTenders($order);
        if (!empty($customTenders)) {
            $message = __('This order contains gift card or loyalty card payments. For security reasons, partial refunds are not available when multiple payment methods are used. Please use the "Refund" button in the WeeConnectPay Charges section above to process a full refund for each transaction.', 'weeconnectpay');
            LogService::info(sprintf(
                'Refund blocked - Order #%d contains custom tenders, directing user to use individual charge refunds',
                $order_id
            ));
            return new WP_Error('wc-order', $message);
        }

		$tax_included            = $order->get_meta( 'weeconnectpay_tax_included' );
		$merged_qty              = $order->get_meta( 'weeconnectpay_merged_qty' );
		$shipping_as_line_item   = null;
		$shipping_line_item_name = null;
		$shipping_item           = array();

		LogService::debug(sprintf(
			'Refund metadata for order #%d - Tax Included: %s, Merged Qty: %s',
			$order_id,
			$tax_included ? 'yes' : 'no',
			$merged_qty ? 'yes' : 'no'
		));

		// Get the WC_Order Object instance (from the order ID)
		if ( ! is_a( $order, 'WC_Order' ) ) {
			LogService::error(sprintf('Refund failed - Invalid order ID #%d (not a WC_Order object)', $order_id));
			return new WP_Error( 'wc-order', __( 'Provided ID is not a WC Order', 'weeconnectpay' ) );
		}

		// Get the Order refunds (array of refunds)
		$order_refunds = $order->get_refunds();

		// Only get the last refund order created since we're only going to process the one we just created
		if ( ! isset( $order_refunds[0] ) ) {
			LogService::error(sprintf('Refund failed - No refund order found for order #%d', $order_id));
			return new WP_Error( 'wc-order', __( 'No WC Order Refund found', 'weeconnectpay' ) );
		}
		$latest_refund = $order_refunds[0];

		// Make sure we're not trying to refund an amount that is 0 or higher
		if ( ! $amount || $amount <= 0 ) {
			LogService::error(sprintf('Refund failed - Invalid amount (%.2f) for order #%d', $amount ?? 0, $order_id));
			return new WP_Error( 'wc-order', __( 'Refund amount must be higher than 0.', 'weeconnectpay' ) );
		}

		// Make sure it's an order refund object
		if ( ! is_a( $latest_refund, 'WC_Order_Refund' ) ) {
			LogService::error(sprintf('Refund failed - Latest refund is not a WC_Order_Refund object for order #%d', $order_id));
			return new WP_Error( 'wc-order', __( 'Last created refund is not a WC Order Refund', 'weeconnectpay' ) );
		}

		// Make sure it's not already been refunded HERE ( Payment processor checks need to be done on our backend for other refund means )
		if ( 'refunded' === $latest_refund->get_status() ) {
			LogService::error(sprintf('Refund failed - Order #%d has already been refunded', $order_id));
			return new WP_Error( 'wc-order', __( 'Order has been already refunded', 'weeconnectpay' ) );
		}

		$line_items = array();
		// Potential polymorphic calls during iteration -- Better try/catch as Woocommerce "conveniently" marks the refund as complete if there's an unhandled exception.
		try {
			LogService::info(sprintf('Processing line items for refund on order #%d', $order_id));
			// Get all the line items to refund

			$undocumentedChangePrefixText = __("Due to an undocumented breaking change in the Clover API, we have temporarily disabled partial refunds.\n", 'weeconnectpay');
            $orderWillNotBeRefundedText = __('This request to refund will not be processed. Should you want to do a partial refund, you can do so through your Clover web dashboard.');
            foreach ( $latest_refund->get_items() as $item_id => $item ) {
				LogService::debug(sprintf(
					'Processing refund for line item ID: %s on order #%d',
					$item_id,
					$order_id
				));

				// Original order line item
				$refunded_item_id    = $item->get_meta( '_refunded_item_id' );
				$refunded_item       = $order->get_item( $refunded_item_id );

				LogService::debug(sprintf(
					'Line item details - Order #%d, Item ID: %s, Name: %s, Quantity: %d, Total: %.2f, Tax: %.2f',
					$order_id,
					$refunded_item_id,
					$refunded_item->get_name(),
					abs($item->get_quantity()),
					abs($item->get_total()),
					abs($item->get_total_tax())
				));

				// Check if the absolute value of refunded quantity, total, and tax match
				if (abs($item->get_quantity()) != $refunded_item->get_quantity()) {
                    // Quantity must match total quantity -- This is no longer going to be relevant with Atomic Order as we will be able to split units on Clover's end and separate taxes
					$refundErrorReasonSprintfFormat = __('To refund this line item (%s), the quantity to refund (currently %s) must be the total line item quantity (%s)');
					$refundFailureReason = sprintf(
						$refundErrorReasonSprintfFormat,
						$refunded_item->get_name(),
						abs($item->get_quantity()),
						$refunded_item->get_quantity()
					);

                    LogService::error("Refund error - Partial refunds not allowed due to mismatched line item quantity. Item ID: $refunded_item_id");
					return new WP_Error('wc-order', $undocumentedChangePrefixText . $refundFailureReason . "\n\n" . $orderWillNotBeRefundedText);
				} elseif ( WeeConnectPayHelper::safe_amount_to_cents_int( abs( $item->get_total() ) ) != WeeConnectPayHelper::safe_amount_to_cents_int( $refunded_item->get_total() ) ) {
                    // Subtotal amount must match the refund subtotal amount
                    $refundErrorReasonSprintfFormat = __('To refund this line item (%s), the amount before tax to refund (currently $%s) must be the line item total amount before tax ($%s)');
					$refundFailureReason = sprintf(
						$refundErrorReasonSprintfFormat,
						$refunded_item->get_name(),
						abs( $item->get_total() ),
						$refunded_item->get_total()
					);

					LogService::error("Refund error - Partial refunds not allowed due to mismatched line item total. Item ID: $refunded_item_id");
					return new WP_Error('wc-order', $undocumentedChangePrefixText . $refundFailureReason . "\n\n" . $orderWillNotBeRefundedText);
				} elseif (WeeConnectPayHelper::safe_amount_to_cents_int(abs($item->get_total_tax())) != WeeConnectPayHelper::safe_amount_to_cents_int($refunded_item->get_total_tax())) {
                    // Total Tax amount must match refund tax amount
					$refundErrorReasonSprintfFormat = __('To refund this line item (%s), the tax to refund (currently $%s) must be the line item total tax ($%s)');
                    $refundFailureReason = sprintf(
						$refundErrorReasonSprintfFormat,
						$refunded_item->get_name(),
	                    abs($item->get_total_tax()),
	                    $refunded_item->get_total_tax()
					);

					LogService::error("Refund error - Partial refunds not allowed due to mismatched line item tax. Item ID: $refunded_item_id");
					return new WP_Error('wc-order', $undocumentedChangePrefixText . $refundFailureReason . "\n\n" . $orderWillNotBeRefundedText);
                }





				// Order Refund line item
				$line_items[] = array(
					'refunded_quantity'    => $item->get_quantity(),
					'refunded_line_total'  => WeeConnectPayHelper::safe_amount_to_cents_int($item->get_total()),
					'refunded_total_tax'   => WeeConnectPayHelper::safe_amount_to_cents_int($item->get_total_tax()),
					'order_refund_item_id' => $item_id,
					'refunded_item'        => array(
						'line_item_id'     => $refunded_item_id,
						'line_total'       => WeeConnectPayHelper::safe_amount_to_cents_int($refunded_item->get_total()),
						'line_total_tax'   => WeeConnectPayHelper::safe_amount_to_cents_int($refunded_item->get_total_tax()),
						'line_quantity'    => $refunded_item->get_quantity(),
						'line_description' => WeeConnectPayHelper::name_and_qty_as_clover_line_desc(
							$refunded_item->get_name(),
							$refunded_item->get_quantity()
						),
					),
				);

				// Log line item details for successful inclusion
				LogService::info("Refund processed - Item ID: $refunded_item_id, Quantity: " . abs($item->get_quantity()) . ", Line Total: " . abs($item->get_total()) . ", Tax: " . $item->get_total_tax());
			}

            // Fees refund
            /** @var WC_Order_Item_Fee $fee */
            foreach ($latest_refund->get_fees() as $fee_id => $fee) {

                // Get the metadata for the refunded fee item
                $refunded_fee_id = $fee->get_meta('_refunded_item_id');

                // Retrieve all fees from the original order
                $order_fees = $order->get_fees();

                // Initialize variable to hold the original fee item
                $refunded_fee = null;

                // Loop through the order fees to find the matching fee
                foreach ($order_fees as $order_fee_id => $order_fee) {
                    if ($order_fee_id == $refunded_fee_id) {
                        $refunded_fee = $order_fee;
                        break;
                    }
                }

                if (!$refunded_fee) {
                    // Subtotal amount must match the refund subtotal amount
                    $refundErrorReasonSprintfFormat = __('Could not find the fee to refund (%s) within the original order. Please contact support@weeconnectpay.com if you are seeing this message.');
                    $refundFailureReason = sprintf(
                        $refundErrorReasonSprintfFormat,
                        $refunded_fee->get_name()
                    );

                    LogService::error("Refund error - Could not find the fee to refund (%s) within the original order. Refunded fee ID: $refunded_fee_id | Refunded fee name: {$refunded_fee->get_name()}");
                    return new WP_Error('wc-order', $undocumentedChangePrefixText . $refundFailureReason . "\n\n" . $orderWillNotBeRefundedText);
                }


                // Check if the absolute value of refunded quantity, total, and tax match -- Although quantity should never be used for fees, this is WordPress,
                // and a fee item is a child of an item, and somebody could have the brilliant idea to change the quantity of a fee, so I'm leaving it here.
                if (abs($fee->get_quantity()) != $refunded_fee->get_quantity()) {
                    // Quantity must match total quantity -- This is no longer going to be relevant with Atomic Order as we will be able to split units on Clover's end and separate taxes
                    $refundErrorReasonSprintfFormat = __('To refund this fee (%s), the quantity to refund (currently %s) must be the total fee quantity (%s)');
                    $refundFailureReason = sprintf(
                        $refundErrorReasonSprintfFormat,
                        $refunded_fee->get_name(),
                        abs($fee->get_quantity()),
                        $refunded_fee->get_quantity()
                    );

                    LogService::error("Refund error - Partial refunds not allowed due to mismatched fee quantity. Item ID: $refunded_fee_id");
                    return new WP_Error('wc-order', $undocumentedChangePrefixText . $refundFailureReason . "\n\n" . $orderWillNotBeRefundedText);

                } elseif (WeeConnectPayHelper::safe_amount_to_cents_int(abs($fee->get_total())) != WeeConnectPayHelper::safe_amount_to_cents_int($refunded_fee->get_total())) {
                    // Subtotal amount must match the refund subtotal amount
                    $refundErrorReasonSprintfFormat = __('To refund this fee (%s), the amount before tax to refund (currently $%s) must be the fee total amount before tax ($%s)');
                    $refundFailureReason = sprintf(
                        $refundErrorReasonSprintfFormat,
                        $refunded_fee->get_name(),
                        abs($fee->get_total()),
                        $refunded_fee->get_total()
                    );

                    LogService::error("Refund error - Partial refunds not allowed due to mismatched fee total. Fee ID: $refunded_fee_id ");
                    return new WP_Error('wc-order', $undocumentedChangePrefixText . $refundFailureReason . "\n\n" . $orderWillNotBeRefundedText);
                } elseif (WeeConnectPayHelper::safe_amount_to_cents_int(abs($fee->get_total_tax())) != WeeConnectPayHelper::safe_amount_to_cents_int($refunded_fee->get_total_tax())) {
                    // Total Tax amount must match refund tax amount
                    $refundErrorReasonSprintfFormat = __('To refund this fee (%s), the tax to refund (currently $%s) must be the fee total tax ($%s)');
                    $refundFailureReason = sprintf(
                        $refundErrorReasonSprintfFormat,
                        $refunded_fee->get_name(),
                        abs($fee->get_total_tax()),
                        $refunded_fee->get_total_tax()
                    );

                    LogService::error("Refund error - Partial refunds not allowed due to mismatched fee tax. Item ID: $refunded_fee_id");
                    return new WP_Error('wc-order', $undocumentedChangePrefixText . $refundFailureReason . "\n\n" . $orderWillNotBeRefundedText);
                }


                // Order Refund line fee
                $line_items[] = array(
                    'refunded_quantity' => $fee->get_quantity(),
                    'refunded_line_total' => WeeConnectPayHelper::safe_amount_to_cents_int($fee->get_total()),
                    'refunded_total_tax' => WeeConnectPayHelper::safe_amount_to_cents_int($fee->get_total_tax()),
                    'order_refund_item_id' => $fee_id,
                    'refunded_item' => array(
                        'line_item_id' => $refunded_fee_id,
                        'line_total' => WeeConnectPayHelper::safe_amount_to_cents_int($refunded_fee->get_total()),
                        'line_total_tax' => WeeConnectPayHelper::safe_amount_to_cents_int($refunded_fee->get_total_tax()),
                        'line_quantity' => $refunded_fee->get_quantity(),
                        'line_description' => WeeConnectPayHelper::name_and_qty_as_clover_line_desc(
                            $refunded_fee->get_name(),
                            $refunded_fee->get_quantity()
                        ),
                    ),
                );

                // Log line fee details for successful inclusion
                LogService::info("Refund processed - Item ID: $refunded_fee_id, Quantity: " . abs($fee->get_quantity()) . ", Line Total: " . abs($fee->get_total()) . ", Tax: " . $fee->get_total_tax());
            }


			// Add shipping if it's part of the refund request
            if ( $latest_refund->get_shipping_total() + $latest_refund->get_shipping_tax() ) {

//				$refundShippingTotal = $latest_refund->get_shipping_total();
//				$refundShippingTax = $latest_refund->get_shipping_tax();
//				$refundShippingMethod = $latest_refund->get_shipping_method();
//				$totalShippingRefunded = $latest_refund->get_total_shipping_refunded();
//				// Log details for debugging
//
//				$order->get_shipping_total();
//				$orderShippingTotal = $order->get_shipping_total();
//				$orderShippingTax = $order->get_shipping_tax();
//				$orderShippingMethod = $order->get_shipping_method();
//				$totalShippingRefunded = $order->get_total_shipping_refunded();

				$shipping_line_item_name = $order->get_meta( 'weeconnectpay_shipping_line_item_name' );
				$shipping_as_line_item   = $order->get_meta( 'weeconnectpay_shipping_as_clover_line_item' );

				LogService::info( "Refund check - Shipping name: $shipping_line_item_name, 
				            Refunded Shipping Total: " . abs( $latest_refund->get_shipping_total() ) . ", Original Shipping Total: " . $order->get_shipping_total() .",
				            Refunded Shipping Taxes: " . abs( $latest_refund->get_shipping_tax() ) . ", Original Shipping Taxes: " . $order->get_shipping_tax()
				);

			$shippingTotalToCents = WeeConnectPayHelper::safe_amount_to_cents_int(  $order->get_shipping_total()  );
			$shippingTotalRefundedToCents = WeeConnectPayHelper::safe_amount_to_cents_int( abs($latest_refund->get_shipping_total()) );

            if ( $shippingTotalToCents != $shippingTotalRefundedToCents ) {
					// Subtotal amount must match the refund subtotal amount
				$refundErrorReasonSprintfFormat = __('To refund this shipping item (%s), the amount before tax to refund (currently $%s) must be the shipping item total amount before tax ($%s)');
				$refundFailureReason = sprintf(
					$refundErrorReasonSprintfFormat,
					$shipping_line_item_name,
					abs($latest_refund->get_shipping_total()),
					$order->get_shipping_total()
				);

				LogService::error("Refund error - Partial refunds not allowed due to mismatched shipping item total. Shipping item name: $shipping_line_item_name");
				return new WP_Error('wc-order', $undocumentedChangePrefixText . $refundFailureReason . "\n\n" . $orderWillNotBeRefundedText);
			} elseif (WeeConnectPayHelper::safe_amount_to_cents_int($order->get_shipping_tax()) != WeeConnectPayHelper::safe_amount_to_cents_int(abs($latest_refund->get_shipping_tax()))) {
					// Total Tax amount must match refund tax amount
				$refundErrorReasonSprintfFormat = __('To refund this shipping item (%s), the shipping tax to refund (currently $%s) must be the shipping item total tax ($%s)');
				$refundFailureReason = sprintf(
					$refundErrorReasonSprintfFormat,
					$shipping_line_item_name,
					abs($latest_refund->get_shipping_tax()),
					$order->get_shipping_tax()
				);

				LogService::error("Refund error - Partial refunds not allowed due to mismatched shipping item tax. Shipping item name: $shipping_line_item_name");
				return new WP_Error('wc-order', $undocumentedChangePrefixText . $refundFailureReason . "\n\n" . $orderWillNotBeRefundedText);
			}

				// If there's an amount to refund related to shipping and the order to be refunded has the shipping line item name AND shipping as line item metadata
				if ( $shipping_as_line_item && $shipping_line_item_name ) {

					$shipping_line_item_amount                 = WeeConnectPayHelper::safe_amount_to_cents_int( $latest_refund->get_shipping_total() ) + WeeConnectPayHelper::safe_amount_to_cents_int( $latest_refund->get_shipping_tax() );

                    $shipping_item['refunded_shipping_amount'] = $shipping_line_item_amount;
					$shipping_item['refunded_shipping_name']   = $shipping_line_item_name;
				} else {
                    // Quick insight 2024 -- Do we really want to stop all the refund logic by returning here?
					return false;
				}
			}
		} catch ( Throwable $e ) {
			LogService::error( "DEBUG: Process refund first try/catch exception: " . $e->getMessage() );

			return false;
		}
		LogService::debug( "DEBUG: Process refund AFTER first try/catch." );

		$formatted_number = WeeConnectPayHelper::safe_amount_to_cents_int( $amount );

		$refund_payload = array(
			'clover_order_uuid'     => $order->get_meta( 'weeconnectpay_clover_order_uuid' ),
			'shipping_as_line_item' => '1' === $shipping_as_line_item,
			'tax_included'          => '1' === $tax_included,
			'merged_qty'            => '1' === $merged_qty,
			'woocommerce_order_id'  => $order_id,
			'wpdb_prefix'           => $wpdb->prefix,
			'amount'                => $formatted_number,
			'reason'                => $reason,
			'line_items'            => $line_items,
		);

		LogService::debug(sprintf(
			'Prepared refund payload for order #%d: %s',
			$order_id,
			json_encode($refund_payload)
		));

		// Add shipping item to payload if it's being refunded as a line item
		if ( isset( $shipping_item['refunded_shipping_amount'] ) ) {
			$refund_payload['shipping_item'] = $shipping_item;
			LogService::debug(sprintf(
				'Added shipping item to refund payload - Order #%d, Amount: %.2f, Name: %s',
				$order_id,
				$shipping_item['refunded_shipping_amount'] / 100,
				$shipping_item['refunded_shipping_name']
			));
		}

		$refund_response = $this->api->refund_woocommerce_order( $refund_payload );
		LogService::debug( sprintf(
			'Refund API response for order #%d: %s',
			$order_id,
			json_encode( $refund_response )
		));

		if ( $refund_response instanceof WP_Error ) {
			LogService::error(sprintf(
				'Refund API call failed for order #%d - Error: %s',
				$order_id,
				$refund_response->get_error_message()
			));
			return $refund_response;
		}

		if ( isset( $refund_response->id )
		     && isset( $refund_response->amount )
		     && isset( $refund_response->charge )
		     && isset( $refund_response->status )
		     && ( 'succeeded' === $refund_response->status )
		) {
			$formatted_refund_amount = number_format( (float) $refund_response->amount / 100, 2, '.', '' );

			LogService::info(sprintf(
				'Refund successful for order #%d - Amount: %.2f, Refund ID: %s, Charge ID: %s',
				$order_id,
				$formatted_refund_amount,
				$refund_response->id,
				$refund_response->charge
			));

			$chargeRefundNote = '<b>' . __( 'Refunded: ', 'weeconnectpay' ) . '</b>';
			$chargeRefundNote .= sprintf(
				                     __( '%1$s %2$s', 'weeconnectpay' ),
				                     $formatted_refund_amount,
				                     $order->get_currency())
			                     . '<br>';
            $chargeRefundNote .= '<b>' . __( 'Refund ID: ', 'weeconnectpay' ) . '</b>' . $refund_response->id . '<br>';
            $chargeRefundNote .= '<b>' . __( 'Charge refunded: ', 'weeconnectpay' ) . '</b>' . $refund_response->charge . '<br>';

            if ( '' !== $reason ) {
				$reason = '<b>' . __( 'Reason: ', 'weeconnectpay' ) . '</b>' . $reason;
	            $chargeRefundNote .= $reason;
			}

			$order->add_order_note( $chargeRefundNote );

			return true;
		} elseif ( isset( $refund_response->id )
		           && isset( $refund_response->amount_returned )
		           && isset( $refund_response->items )
		           && isset( $refund_response->status )
		           && ( 'returned' === $refund_response->status )
		) {
			$formatted_returned_amount = number_format( (float) $refund_response->amount_returned / 100, 2, '.', '' );

			LogService::info(sprintf(
				'Return successful for order #%d - Amount: %.2f, Return ID: %s',
				$order_id,
				$formatted_returned_amount,
				$refund_response->id
			));

			$returnString = '<b>' . __( 'Refunded: ', 'weeconnectpay' ) . '</b>';
			$returnString .= sprintf(
				                     __( '%1$s %2$s', 'weeconnectpay' ),
				                     $formatted_returned_amount,
				                     $order->get_currency())
			                     . '<br>';
			$returnString .= '<b>' . __( 'Refund ID: ', 'weeconnectpay' ) . '</b>' . $refund_response->id . '<br>';

			if ( '' !== $reason ) {
				$reason = '<b>' . __( 'Reason: ', 'weeconnectpay' ) . '</b>' . $reason;
				$returnString .= $reason;
			}

            foreach ( $refund_response->items as $item_returned ) {
				if ( isset( $item_returned->parent )
				     && isset( $item_returned->description )
				     && isset( $item_returned->amount )
				) {
					$clover_item_id                   = $item_returned->parent;
					$clover_item_returned_description = $item_returned->description ?? null;
					$formatted_return_amount          = number_format( (float) $item_returned->amount / 100, 2, '.', '' );

					LogService::debug(sprintf(
						'Returned item details - Order #%d, Item ID: %s, Description: %s, Amount: %.2f',
						$order_id,
						$clover_item_id,
						$clover_item_returned_description,
						$formatted_return_amount
					));

                    $returnString .= '<b>' . __( 'Returned clover item ID: ', 'weeconnectpay' ) . '</b>';
					$returnString .= sprintf(
						                 __( '%1$s(%2$s %3$s) - %4$s', 'weeconnectpay' ),
						                 $clover_item_id,
						                 $formatted_return_amount,
						                 $order->get_currency(),
						                 $clover_item_returned_description )
					                 . '<br>';
				}
			}
			$order->add_order_note( $returnString );

			return true;
		} else {
			LogService::error(sprintf(
				'Refund failed for order #%d - Invalid or unexpected API response',
				$order_id
			));
			return new WP_Error( 'wc-order', __( 'Order has been already refunded', 'weeconnectpay' ) );
		}
	}

    /**
     * Add WeeConnectPay Charges meta box to order page
     */
    public function add_weeconnectpay_charges_meta_box() {
        // Check if we're on the order screen
        $screen = get_current_screen();
        if (!$screen || wc_get_page_screen_id('shop-order') !== $screen->id) {
            return;
        }

        // Get the order object using WooCommerce's order factory
        $order_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if (!$order_id) {
            return;
        }

        try {
            $order = wc_get_order($order_id);
            if (!$order instanceof WC_Order) {
                return;
            }
            
            // Check if there are any charges for this order
            $charges = $order->get_meta('weeconnectpay_charges', true);
            if (!is_array($charges) || empty($charges)) {
                return;
            }

            // Add the meta box
            add_meta_box(
                'weeconnectpay_charges_meta_box',
                __('WeeConnectPay Charges', 'weeconnectpay'),
                array($this, 'display_weeconnectpay_charges_table'),
                wc_get_page_screen_id('shop-order'),
                'normal',
                'high'
            );
        } catch (Exception $e) {
            LogService::error('Failed to add WeeConnectPay charges meta box: ' . $e->getMessage());
            return;
        }
    }

    /**
     * Display the WeeConnectPay Credit Card Charges table on the WooCommerce order edit page.
     *
     * This hook adds a table of tender data (charge_id, amount with currency, card type,
     * last 4 digits, expiration date, postal code, and status) under the order details.
     * It includes a button to initiate a refund for the full charge amount if the charge is successful.
     *
     * @param WC_Order $order The order object
     */
    function display_weeconnectpay_charges_table($order) {
        if (!$order instanceof WC_Order) {
            return;
        }
        
        // Retrieve the charges saved via your helper
        $charges = $order->get_meta('weeconnectpay_charges', true);

        // Only proceed if charges exist and are an array
        if (!is_array($charges) || empty($charges)) {
            return;
        }
        ?>
        <div class="wcp-charges-container">
            <table class="wcp-charges-table widefat fixed striped">
                <thead>
                <tr>
                    <th class="wcp-charge-id"><?php esc_html_e('Charge ID', 'weeconnectpay'); ?></th>
                    <th class="wcp-amount"><?php esc_html_e('Amount', 'weeconnectpay'); ?></th>
                    <th class="wcp-card-type"><?php esc_html_e('Card Type', 'weeconnectpay'); ?></th>
                    <th class="wcp-last4"><?php esc_html_e('Last 4', 'weeconnectpay'); ?></th>
                    <th class="wcp-exp-date"><?php esc_html_e('Exp Date', 'weeconnectpay'); ?></th>
                    <th class="wcp-postal-code"><?php esc_html_e('Postal Code', 'weeconnectpay'); ?></th>
                    <th class="wcp-status"><?php esc_html_e('Status', 'weeconnectpay'); ?></th>
                    <th class="wcp-action"><?php esc_html_e('Action', 'weeconnectpay'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($charges as $charge) :
                    $display_amount = number_format($charge['amount'] / 100, 2, '.', '');
                    $raw_refund_amount = $charge['amount'];
                    ?>
                    <tr>
                        <td class="wcp-charge-id"><?php echo esc_html($charge['charge_id']); ?></td>
                        <td class="wcp-amount">
                            <?php echo esc_html(sprintf('%s $%s', $charge['currency'], $display_amount)); ?>
                        </td>
                        <td class="wcp-card-type"><?php echo esc_html($charge['card_type']); ?></td>
                        <td class="wcp-last4"><?php echo esc_html($charge['card_last4']); ?></td>
                        <td class="wcp-exp-date">
                            <?php
                            $exp_month = str_pad($charge['card_exp_month'], 2, '0', STR_PAD_LEFT);
                            $exp_year = $charge['card_exp_year'];
                            echo esc_html("{$exp_month}/{$exp_year}");
                            ?>
                        </td>
                        <td class="wcp-postal-code"><?php echo esc_html($charge['card_postal_code']); ?></td>
                        <td class="wcp-status">
                            <span class="wcp-status-<?php echo esc_attr($charge['status']); ?>">
                                <?php echo esc_html($charge['status']); ?>
                            </span>
                        </td>
                        <td class="wcp-action">
                            <?php if ('success' === $charge['status']) :
                                $refund_nonce = wp_create_nonce('weeconnectpay_refund_charge_nonce');
                                ?>
                                <button type="button" class="button refund-charge-button"
                                        data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                                        data-charge-id="<?php echo esc_attr($charge['charge_id']); ?>"
                                        data-refund-amount="<?php echo esc_attr($raw_refund_amount); ?>"
                                        data-nonce="<?php echo esc_attr($refund_nonce); ?>">
                                    <?php esc_html_e('Refund', 'weeconnectpay'); ?>
                                </button>
                            <?php else : ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                if (typeof ajaxurl === 'undefined') {
                    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                }

                $('.refund-charge-button').on('click', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var orderId = button.data('order-id');
                    var chargeId = button.data('charge-id');
                    var refundAmt = button.data('refund-amount');
                    var nonce = button.data('nonce');

                    if (!confirm('Are you sure you want to refund this charge?')) {
                        return;
                    }
                    button.prop('disabled', true);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'weeconnectpay_refund_charge',
                            order_id: orderId,
                            charge_id: chargeId,
                            refund_amount: refundAmt,
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Charge refunded successfully.');
                                button.closest('tr').find('.wcp-status span')
                                    .removeClass('wcp-status-success')
                                    .addClass('wcp-status-refunded')
                                    .text('refunded');
                                button.replaceWith('&mdash;');
                                window.location.reload();
                            } else {
                                console.error('Refund failed:', response.data);
                                alert('Refund failed: ' + response.data);
                                button.prop('disabled', false);
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('AJAX error:', textStatus, errorThrown);
                            alert('An error occurred while processing the refund.');
                            button.prop('disabled', false);
                        }
                    });
                });
            });
        </script>
        <?php
    }


	/**
	 * Used in process_payment. Handles a WeeConnectPayException to properly display a notice and return the failure array.
	 *
	 * @param WeeConnectPayException $exception
	 *
	 * @return string[]
	 * @since 2.0.6
	 * @updated 2.6.0
	 */
	protected function handleProcessPaymentException( WeeConnectPayException $exception ): array {
		if ( $exception->getCode() === ExceptionCode::MISSING_SHIPPING_STATE
		     || $exception->getCode() === ExceptionCode::CUSTOMER_CREATION_EXCEPTION
		     || $exception->getCode() === ExceptionCode::STANDARDIZED_RESPONSE_EXCEPTION
		     || $exception->getCode() === ExceptionCode::INVALID_JSON_EXCEPTION
		     || $exception->getCode() === ExceptionCode::ORDER_LINE_ITEM_TOTAL_MISMATCH
		     || $exception->getCode() === ExceptionCode::UNSUPPORTED_ORDER_ITEM_TYPE
		) {
			wc_add_notice( esc_html( $exception->getMessage() ), 'error' );
		} else {
			LogService::error( 'An unhandled exception happened with the payment processor. Message: ' . $exception->getMessage() );
		}

		return array(
			'result'   => 'fail',
			'redirect' => '',
		);
	}

	/**
	 * Used in process_payment. Handles a WeeConnectPayException to properly display a notice and return the failure array.
	 *
	 * @param WeeConnectPayException $exception
	 *
	 * @return string[]
	 * @since 2.4.0
	 */
	protected function handleCustomerCreationException( WeeConnectPayException $exception ): array {
		if ( $exception->getCode() === ExceptionCode::MISSING_SHIPPING_STATE ) {
			wc_add_notice( esc_html( $exception->getMessage() ), 'error' );
		} else {
			LogService::error( 'An unhandled exception happened while preparing the order with the payment processor.' );
		}

		return array(
			'result'   => 'fail',
			'redirect' => '',
		);
	}



	/**
	 * @return array|void
	 */
	protected function verifyAuthentication() {
		$wp_env = WeeConnectPayUtilities::get_wp_env();

		switch ( $wp_env ) {
            case 'gitpod':
                $url_api = GITPOD_WCP_BACKEND_WORKSPACE_URL ?? 'GITPOD_URL_NOT_SET';
                break;
			case 'local':
			case 'development':
				// Do dev stuff
				$url_api = 'https://weeconnect-api.test';
				break;
			case 'staging':
				// Do staging stuff
				$url_api = 'https://apidev.weeconnectpay.com';
				break;
			case 'production':
			default:
				// Do production stuff
				$url_api = 'https://api.weeconnectpay.com';
		}

		try {

			$integration_id = Authentication::fetchIntegrationId();

			$integrationSettings = new IntegrationSettings();
			if ( $integrationSettings->accessTokenExists() ) {
				$authVerifyHttpCode = Authentication::verify( $integration_id );
			} else {
				$authVerifyHttpCode = 401;
			}

		} catch ( WeeConnectPayException $exception ) {
			die( json_encode( StandardizedResponse::emitError( $exception->toObject() ) ) );
		}

		return array( $url_api, $integration_id, $authVerifyHttpCode );
	}

}


