<?php
// phpcs:disable WordPress.WhiteSpace.PrecisionAlignment.Found
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://github.com/ParogDev
 * @since      1.0.0
 *
 * @package    WeeConnectPay
 * @subpackage WeeConnectPay/admin/partials
 */

use WeeConnectPay\Integrations\AdminPanel;

// Exit if file is called directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// check if user is allowed access
if ( ! current_user_can( 'manage_options' ) ) {
	return;
}
$vue_data = array(
	'pluginUrl' => WEECONNECTPAY_PLUGIN_URL,
);

$admin_panel = new AdminPanel();
$admin_panel->registerVueScripts()
            ->localizeVueScriptData( $vue_data )
            ->registerVueStyles()
            ->enqueueVueScriptsAndStyles()
            ->outputVueAppDiv();
?>


