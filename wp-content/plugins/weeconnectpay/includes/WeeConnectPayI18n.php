<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://github.com/ParogDev
 * @since      1.0.0
 *
 * @package    WeeConnectPay
 * @subpackage WeeConnectPay/includes
 */

namespace WeeConnectPay\WordPress\Plugin\includes;

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    WeeConnectPay
 * @subpackage WeeConnectPay/includes
 * @author     ParogDev <integration@cspaiement.com>
 */
class WeeConnectPayI18N
{


    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function loadPluginTextdomain()
    {

        load_plugin_textdomain(
            'weeconnectpay',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}
