<?php /** @noinspection PhpCSValidationInspection */

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.weeconnectpay.com/
 * @since             1.0.0
 * @package           WeeConnectPay
 *
 * @wordpress-plugin
 * Plugin Name:       WeeConnectPay
 * Plugin URI:        https://weeconnectpay.com/plugin?platform=wordpress
 * Description:       Integrate Clover Payments with your WooCommerce online store.
 * Tags:              clover, payments, weeconnect, e-commerce, gateway
 * Version:           3.13.12
 * Requires at least: 5.6
 * Tested Up To:      6.7.2
 * Requires PHP:      7.4
 * Author:            WeeConnectPay
 * Author URI:        https://weeconnectpay.com/
 * Contributors:      @weeconnectpay
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       weeconnectpay
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * WC requires at least: 3.0.4
 * WC tested up to: 9.7.1
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
const WEECONNECT_VERSION = '3.13.12';

define( 'WEECONNECTPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define( 'WEECONNECTPAY_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/WeeConnectPayActivator.php
 */
function activate_weeconnectpay()
{
	require_once plugin_dir_path(__FILE__) . 'includes/WeeConnectPayActivator.php';
    WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayActivator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/WeeConnectPayDeactivator.php
 */
function deactivate_weeconnectpay()
{
    require_once plugin_dir_path(__FILE__) . 'includes/WeeConnectPayDeactivator.php';
    WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayDeactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_weeconnectpay');
register_deactivation_hook(__FILE__, 'deactivate_weeconnectpay');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and site-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/WeeConnectPay.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_weeconnectpay()
{
    $plugin = WeeConnectPay\WordPress\Plugin\includes\WeeConnectPay::get_instance();
    $plugin->run();
}

run_weeconnectpay();

/**
 * WooCommerce requires this snippet being added in the main plugin file to declare certain compatibilities

 *
 * We could declare the plugin file and add it where we add all of our hooks, but for the time being we are leaving it here
 */
add_action( 'before_woocommerce_init', function() {
	/**
	 * Blocks-based checkout compatibility.
	 * Compatibility working with WooCommerce instances that use WooCommerce Checkout Blocks in their checkout page
	 * See: https://developer.woocommerce.com/2023/11/06/faq-extending-cart-and-checkout-blocks/
	 */
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}

	/**
	 * High Performance Order Storage (HPOS) compatibility.
	 * Compatibility working with WooCommerce instances that have orders that are potentially not stored in the wp_posts table
	 * See: https://developer.woocommerce.com/docs/hpos-extension-recipe-book/
	 */
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
