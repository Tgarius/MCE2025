<?php

/**
 * The site-facing functionality of the plugin.
 *
 * @link       https://github.com/ParogDev
 * @since      1.0.0
 *
 * @package    WeeConnectPay
 * @subpackage WeeConnectPay/site
 */

namespace WeeConnectPay\WordPress\Plugin\site;


use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WeeConnectPay\Exceptions\InsufficientDependencyVersionException;
use WeeConnectPay\Exceptions\MissingDependencyException;
use WeeConnectPay\Integrations\DependencyChecker;
use WeeConnectPay\Integrations\DismissibleNewFeatureNotice;
use WeeConnectPay\Integrations\IntegrationSettings;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayUtilities;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayMethod;
use WeeConnectPay\Integrations\LogService;

/**
 * The site-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the site-facing stylesheet and JavaScript.
 *
 * @package    WeeConnectPay
 * @subpackage WeeConnectPay/site
 * @author     ParogDev <integration@cspaiement.com>
 */
class WeeConnectPayPublic
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $pluginName The ID of this plugin.
     */
    private $pluginName;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $pluginName The name of the plugin.
     * @param string $version    The version of this plugin.
     *
     * @since    1.0.0
     */
    public function __construct($pluginName, $version)
    {
        $this->pluginName = $pluginName;
        $this->version    = $version;
    }

    /**
     * Register the stylesheets for the site-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueueStyles()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in WeeConnectPayLoader as all of the hooks are defined
         * in that particular class.
         *
         * The WeeConnectPayLoader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

//        wp_enqueue_style(
//            $this->pluginName,
//            plugin_dir_url(__FILE__) . 'css/weeconnectpay-site.css',
//            array(),
//            $this->version,
//            'all'
//        );
    }

    /**
     * Register the JavaScript for the site-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueueScripts()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in WeeConnectPayLoader as all of the hooks are defined
         * in that particular class.
         *
         * The WeeConnectPayLoader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

//        wp_enqueue_script(
//            $this->pluginName,
//            plugin_dir_url(__FILE__) . 'js/weeconnectpay-site.js',
//            array( 'jquery' ),
//            $this->version,
//            false
//        );
    }

	/**
	 * @return void
	 * @updated 3.5.0
	 */
	public function initWeeconnectpayGateway()
    {
	    $dependencyChecker = new DependencyChecker;
	    try {
		    $dependencyChecker->validateOnRun();

		    /**
		     * The class responsible for defining the WooCommerce WeeConnectPay gateway
		     */
		    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/integrations/woocommerce/WC_Gateway_Weeconnectpay.php';

	    } catch (InsufficientDependencyVersionException | MissingDependencyException $exception) {
			// This only works without transients because the admin notice hook we used hasn't fired yet
		    $dependencyChecker->notify( $exception);
	    }

	    $settings_url = WeeConnectPayUtilities::getSettingsURL();
	    $upgrade_notice = /** @lang HTML */
		    '<div style="border: 1px solid #ccc; background-color: #f9f9f9; padding: 20px; margin: 20px 0 0 0;">
		        <h2 style="font-size: 20px; color: #333; margin-bottom: 10px;">' . esc_html__( 'Exciting Update from WeeConnectPay!', 'weeconnectpay' ) . '</h2>
		        <p style="font-size: 16px; color: #666; margin-bottom: 20px;">' . esc_html__( 'Introducing a New Feature: Block-based checkout!', 'weeconnectpay' ) . '</p>
		        <p style="font-size: 16px; color: #666; margin-bottom: 20px;">' . esc_html__( 'Your WeeConnectPay plugin is now compatible with WooCommerce\'s new Block-based checkout. Enjoy seamless payments with the latest features.', 'weeconnectpay' ) . '</p>
		        <p style="font-size: 16px; color: #666; margin-bottom: 20px;">' . esc_html__( 'If you encounter any issues, please contact us at ','weeconnectpay') . '<a href="mailto:support@weeconnectpay.com">' . esc_html__( 'support@weeconnectpay.com', 'weeconnectpay' ) . '</a>' . esc_html__( ' and we will be happy to help. Thank you!', 'weeconnectpay' ) .'</p>		  
		    </div>';
	    $fraudPreventionNotice = new DismissibleNewFeatureNotice( IntegrationSettings::PLUGIN_OPTION_PREFIX . IntegrationSettings::DB_KEY_SUFFIX_CHECKOUT_BLOCKS_FEATURE_NOTICE, $upgrade_notice );

    }

	/**
	 * Adds the gateway class name as a string to the array of gateways WooCommerce should load.
	 * Checks if the gateway exist first in case it was not loaded.
	 *
	 * @updated 1.4.4
	 * @since 1.0.0
	 * @param $methods
	 *
	 * @return mixed
	 */
	public function addWeeconnectpayGateway($methods)
    {
		if( class_exists( 'WC_Gateway_Weeconnectpay')){
			$methods[] = 'WC_Gateway_Weeconnectpay';
		}

        return $methods;
    }

	/**
	 * Adds Clover as a domain to exclude when bundling scripts with SiteGround Optimizer. Used by a SiteGround Optimizer hook.
	 *
	 * @param array $exclude_list
	 * @since 3.2.6
	 * @return array
	 */
	function excludeExternalScriptsFromSiteGroundCombine( array $exclude_list ): array {
		$exclude_list[] = 'clover.com';

		return $exclude_list;
	}

	/**
	 * Removes the 'x_woocommerce_add_submit_spinner' elements from being inserted.
	 * The fact that it has a script INSIDE the constantly changing SVG creates a race condition
	 * where after making a list of all the elements, this specific element SRC is now undefined instead of an empty string.
	 * This, coupled with an oversight on Clover's part where they do not check if the src exists before using indexOf, breaks their [Clover] SDK.
	 * The element is unused and hidden.
	 *
	 * @return void
	 */
	function remove_sdk_breaking_pro_theme_hidden_spinner() {
		remove_action('woocommerce_review_order_after_submit', 'x_woocommerce_add_submit_spinner', 10);
	}

	public function addWeeconnectpayGatewayBlockSupport() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			// Think 5 times before moving this next line
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/integrations/woocommerce/WeeConnectPayMethod.php';

			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( PaymentMethodRegistry $payment_method_registry ) {
					$container = \Automattic\WooCommerce\Blocks\Package::container();


					$container->register(
						'weeconnectpay',
						function() {
							return new WeeConnectPayMethod();
						}
					);

					try {
						$wcpContainerClass = $container->get( 'weeconnectpay' );
						// Honestly, this whole try-catch block is probably irrelevant now
//						error_log( "wcpContainerClass: " . json_encode( $wcpContainerClass ) );
					} catch (\Throwable $exception) {
						LogService::error(sprintf("EXCEPTION THROWN wcpContainerClass: %s", json_encode($exception->getMessage())));
					}

					$hasBeenRegistered = $payment_method_registry->register(
						$container->get( 'weeconnectpay' )
					);

//					error_log( 'Has the gateway been registered? '.json_encode( $hasBeenRegistered) );

				}
			);
		}
	}
}


