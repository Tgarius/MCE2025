<?php

namespace WeeConnectPay\Integrations;

class AdminPanel {

	/**
	 * Registers the needed vue scripts for the admin panel
	 * @since 2.0.0
	 */
	public function registerVueScripts(): AdminPanel {
		wp_register_script( 'weeconnectpay-app-js', WEECONNECTPAY_PLUGIN_URL . 'dist/js/app.js', array(), WEECONNECT_VERSION, false );
		wp_register_script( 'weeconnectpay-vendors-chunk-js', WEECONNECTPAY_PLUGIN_URL . 'dist/js/chunk-vendors.js', array( 'weeconnectpay-app-js' ), WEECONNECT_VERSION, false );
	    return $this;
	}

	/**
	 * Makes WordPress localized data available for the vue scripts needed in the admin panel
	 * @since 2.0.0
	 */
	public function localizeVueScriptData( array $vue_data ): AdminPanel {
		wp_localize_script( 'weeconnectpay-app-js', 'weeconnectpayVueData', $vue_data );
	    return $this;
	}

	/**
	 * Registers the needed vue CSS for the admin panel
	 * @since 2.0.0
	 */
	public function registerVueStyles(): AdminPanel {
		wp_register_style( 'weeconnectpay-app-css', WEECONNECTPAY_PLUGIN_URL . 'dist/css/app.css', array(), WEECONNECT_VERSION );
		//wp_register_style( 'weeconnectpay-vendors-chunk-css', WEECONNECTPAY_PLUGIN_URL . 'dist/css/chunk-vendors.css', array(), WEECONNECT_VERSION );
	    return $this;
	}

	/**
	 * Enqueues the needed vue scripts and styles in the right order for the admin panel
     * @since 2.0.0
	 */
	public function enqueueVueScriptsAndStyles(): AdminPanel {
		wp_enqueue_script( 'weeconnectpay-app-js' );
		wp_enqueue_script( 'weeconnectpay-vendors-chunk-js' );
		wp_enqueue_style( 'weeconnectpay-app-css' );
		wp_enqueue_style( 'weeconnectpay-vendors-chunk-css' );
	    return $this;
	}


	/**
	 * Output the HTML elements required for vue to mount
     * @since 2.0.0
	 */
	public function outputVueAppDiv(): void {
		?>
        <noscript>
            <strong>
                We're sorry but WeeConnectPay doesn't work properly without JavaScript enabled. Please enable it to
                continue.
            </strong>
        </noscript>
        <div id="weeconnectpay-app-wrapper"></div>
		<?php
	}

	public function init($vue_data) {
        // Output HTML first
        $this->outputVueAppDiv();

        // Then work with scripts
		$this->registerVueScripts()
		            ->localizeVueScriptData( $vue_data )
		            ->registerVueStyles()
		            ->enqueueVueScriptsAndStyles();
    }


}
