<?php

namespace WeeConnectPay\Integrations;

use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayUtilities;

class PaymentFields {


	/**
	 * Registers the needed scripts for the payment fields
	 * @since 2.0.0
     * @updated 3.6.0
	 */
	public function registerScripts(): PaymentFields {
        $cloverSdkUrl = ( WeeConnectPayUtilities::get_wp_env() === 'production' ) ? 'https://checkout.clover.com/sdk.js' : 'https://checkout.sandbox.dev.clover.com/sdk.js';
		wp_register_script( 'weeconnectpay-clover-sdk', $cloverSdkUrl, [], null, false );
		wp_register_script( 'weeconnectpay-payment-fields', WEECONNECTPAY_PLUGIN_URL . 'dist/js/payment-fields.js', array( 'weeconnectpay-clover-sdk' ), WEECONNECT_VERSION, false );
//		try {
//			wp_register_script( 'weeconnectpay-payment-fields', WEECONNECTPAY_PLUGIN_URL . 'dist/js/payment-fields.js', array( 'weeconnectpay-clover-sdk' ), random_int( 1, 10000 ), false );
//		} catch ( \Exception $e ) {
//		}

		if (GoogleRecaptcha::isEnabledAndReady()) {
			wp_register_script( 'weeconnectpay-google-recaptcha', GoogleRecaptcha::getSdkSrc() );
        }
        return $this;
	}

	/**
	 * Makes WordPress localized data available for the scripts needed in for the payment fields
	 * @since 2.0.0
	 */
	public function localizeData( array $script_data ): PaymentFields {

		wp_localize_script( 'weeconnectpay-payment-fields', 'WeeConnectPayPaymentFieldsData', $script_data );
		return $this;
	}

	/**
	 * Registers the CSS used by the payment fields
	 * @since 2.0.0
	 */
	public function registerStyles(): PaymentFields {
		wp_register_style( 'weeconnectpay-payment-fields-css', WEECONNECTPAY_PLUGIN_URL . 'site/css/weeconnect-public.css', array(), WEECONNECT_VERSION );
		return $this;
	}

	/**
	 * Enqueues the needed scripts and styles in the right order for the payment fields
	 * @since 2.0.0
	 * @updated 3.6.0
	 */
	public function enqueueVueScriptsAndStyles(): PaymentFields {
		wp_enqueue_script( 'weeconnectpay-clover-sdk');
		wp_enqueue_script( 'weeconnectpay-payment-fields', '', array( 'weeconnectpay-clover-sdk' ), WEECONNECT_VERSION );
		if (GoogleRecaptcha::isEnabledAndReady()) {
			wp_enqueue_script( 'weeconnectpay-google-recaptcha');
		}
		wp_enqueue_style( 'weeconnectpay-payment-fields-css', '', array( 'weeconnectpay-clover-sdk' ), WEECONNECT_VERSION );
		return $this;
	}


	/**
	 * Output the HTML elements required for payment processing
	 * @since 2.0.0
     * @updated 3.6.0
	 */
	public function outputElements(): PaymentFields {
		?>
        <div id="weeconnectpay-wc-fields">
            <div id="form-display-no-footer">
                <div class="top-row-wrapper">
                    <div class="form-row top-row full-width">
                        <div id="weeconnectpay-card-number" autocomplete="cc-number"
                             class="field card-number-field"></div>
                        <div class="input-errors" id="weeconnectpay-card-number-errors" role="alert"></div>
                    </div>
                </div>

                <div class="bottom-row-wrapper">
                    <div class="form-row bottom-row third-width">
                        <div id="weeconnectpay-card-date" autocomplete="cc-exp" class="field card-date-field"></div>
                        <div class="input-errors" id="weeconnectpay-card-date-errors" role="alert"></div>
                    </div>

                    <div class="form-row bottom-row third-width">
                        <div id="weeconnectpay-card-cvv" autocomplete="cc-csc" class="field card-cvv-field"></div>
                        <div class="input-errors" id="weeconnectpay-card-cvv-errors" role="alert"></div>
                    </div>

                    <div class="form-row bottom-row third-width">
                        <div id="weeconnectpay-card-postal-code" autocomplete="billing postal-code"
                             class="field card-postal-code-field"></div>
                        <div class="input-errors" id="weeconnectpay-card-postal-code-errors" role="alert"></div>
                    </div>
                </div>
                <div id="card-response" role="alert"></div>
                <div id="card-errors" role="alert"></div>
                <div class="clover-footer"></div>
            </div>
        </div>
        <div id="weeconnectpay-separator-with-text">
            OR
        </div>
        <div id="weeconnectpay-payment-request-button"
             style="width: 100%; max-width: 200px; margin: 0 auto; margin-top: 8px; margin-bottom: 8px; height: 50px; z-index: 99999;"></div>
        <input type="hidden" value="" name="token" id="wcp-token"/>
        <input type="hidden" value="" name="card-brand" id="wcp-card-brand"/>
        <input type="hidden" value="" name="card-last4" id="wcp-card-last4"/>
        <input type="hidden" value="" name="card-exp-month" id="wcp-card-exp-month"/>
        <input type="hidden" value="" name="card-exp-year" id="wcp-card-exp-year"/>
        <input type="hidden" value="" name="recaptcha-token" id="wcp-recaptcha-token"/>
        <input type="hidden" value="" name="tokenized-zip" id="wcp-tokenized-zip"/>
        <input type="hidden" value="" name="hp-feedback-required" id="wcp-hp-feedback-required" autocomplete="off"/>
        <div id="weeconnectpay-secured-by-clover">
            <div id="weeconnectpay-secured-by-lock">
                <img src="<?php echo esc_url( WEECONNECTPAY_PLUGIN_URL ) . 'site/img/lock.svg'; ?>" alt="Lock icon">
            </div>
            <div id="weeconnectpay-secured-by-display">
                <div id="weeconnectpay-secured-by-text">
                    Payment secured by
                </div>
                <img id="weeconnectpay-secured-by-img"
                     src="<?php echo esc_url( WEECONNECTPAY_PLUGIN_URL ) . 'site/img/secured-by-logos.png'; ?>"
                     alt="Secured by Clover & WeeConnectPay logos">
            </div>

        </div>
		<?php
        return $this;
	}

	/**
     *
	 * @param array $script_data
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init( array $script_data) {
		$this->outputElements()
             ->registerScripts()
		     ->localizeData( $script_data )
		     ->registerStyles()
		     ->enqueueVueScriptsAndStyles();
	}

}
