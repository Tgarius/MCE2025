<?php
// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase


/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * site-facing side of the site and the admin area.
 *
 * @link       https://github.com/ParogDev
 * @since      1.0.0
 *
 * @package    WeeConnectPay
 * @subpackage WeeConnectPay/includes
 */

namespace WeeConnectPay\WordPress\Plugin\includes;

use WeeConnectPay\WordPress\Plugin\admin\WeeConnectPayAdmin;
use WeeConnectPay\WordPress\Plugin\site\WeeConnectPayPublic;

if ( ! class_exists( 'WeeConnectPay' ) ) {


	/**
	 * The core plugin class.
	 *
	 * This is used to define internationalization, admin-specific hooks, and
	 * site-facing site hooks.
	 *
	 * Also maintains the unique identifier of this plugin as well as the current
	 * version of the plugin.
	 *
	 * @since      1.0.0
	 * @package    WeeConnectPay
	 * @subpackage WeeConnectPay/includes
	 * @author     ParogDev <integration@cspaiement.com>
	 */
	class WeeConnectPay {


		/**
		 * The unique instance of the plugin.
		 *
		 * @var WeeConnectPay
		 */
		private static $instance;

		/**
		 * Gets an instance of our plugin.
		 *
		 * @return WeeConnectPay
		 */
		public static function get_instance(): WeeConnectPay {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}


		/**
		 * The loader that's responsible for maintaining and registering all hooks that power
		 * the plugin.
		 *
		 * @since    1.0.0
		 * @access   protected
		 * @var      WeeConnectPayLoader $loader Maintains and registers all hooks for the plugin.
		 */
		protected $loader;

		/**
		 * The unique identifier of this plugin.
		 *
		 * @since    1.0.0
		 * @access   protected
		 * @var      string $pluginName The string used to uniquely identify this plugin.
		 */
		protected $pluginName;

		/**
		 * The current version of the plugin.
		 *
		 * @since    1.0.0
		 * @access   protected
		 * @var      string $version The current version of the plugin.
		 */
		protected $version;

		/**
		 * Define the core functionality of the plugin.
		 *
		 * Set the plugin name and the plugin version that can be used throughout the plugin.
		 * Load the dependencies, define the locale, and set the hooks for the admin area and
		 * the site-facing side of the site.
		 *
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 *
		 * @since    1.0.0
		 */
		private function __construct() {
			/** @TODO: Automate or auto-check semantic version incrementation */
			if ( defined( 'WEECONNECT_VERSION' ) ) {
				$this->version = WEECONNECT_VERSION;
			} else {
				$this->version = '1.0.0';
			}
			$this->pluginName = 'weeconnectpay';

			$this->loadDependencies();
			$this->setLocale();
			$this->defineAdminHooks();
			$this->definePublicHooks();
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {
		}

		/**
		 * Empty unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		public function __wakeup() {
		}

		/**
		 * Load the required dependencies for this plugin.
		 *
		 * Include the following files that make up the plugin:
		 *
		 * - WeeConnectPayLoader. Orchestrates the hooks of the plugin.
		 * - WeeConnectPayI18N. Defines internationalization functionality.
		 * - WeeConnectPayAdmin. Defines all hooks for the admin area.
		 * - WeeConnectPayPublic. Defines all hooks for the site side of the site.
		 *
		 * Create an instance of the loader which will be used to register the hooks
		 * with WordPress.
		 *
		 * @updated      1.4.5
		 * @since        1.0.0
		 * @access       private
		 */
		private function loadDependencies() {
			/**
			 * Composer Autoloader - All vendor dependencies
			 */
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'vendor/autoload.php';
			require_once plugin_dir_path( dirname( __FILE__ ) ) .'packages/GuzzleHttp/functions_include.php';
			require_once plugin_dir_path( dirname( __FILE__ ) ) .'packages/GuzzleHttp/Psr7/functions_include.php';
			require_once plugin_dir_path( dirname( __FILE__ ) ) .'packages/GuzzleHttp/Promise/functions_include.php';

			/**
			 * Utility class - All static functions ( String manipulation, etc )
			 */
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/WeeConnectPayUtilities.php';
			/**
			 * Helper class - All static functions ( String manipulation, etc )
			 */
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/WeeConnectPayHelper.php';
            /**
             *  Helper Class for Custom Tenders - All static functions
             */
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/WeeConnectPayCustomTenderHelper.php';
            /**
             * Callback Interface - Used to allow third party to safely implement a callback for Clover custom tender manipulation logic
             */
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/CustomTenderInterface.php';

            /**
             * The class responsible for the plugin settings
             * @since 2.0.0
             */
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/modules/WeeConnectPay/Integration/IntegrationSettings.php';

            /**
             * The class responsible for logging functionality
             */
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/modules/WeeConnectPay/Integration/Logger.php';

            /**
             * The class responsible for centralized logging service
             * Core dependency that provides static access to logging functionality
             * 
             * @since 3.13.0
             */
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/modules/WeeConnectPay/Integration/LogService.php';

            /**
             * The class responsible for handling log-related REST API endpoints
             */
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/WeeConnectPayLogController.php';

            /**
             * Dependency Checker
             */
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/modules/WeeConnectPay/Integration/DependencyChecker.php';

            /**
             * The class responsible for setting validation
             */
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/ValidateSettings.php';

			/**
			 * The class responsible for defining our own exceptions
			 */
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/WeeConnectPayException.php';

			/**
			 * The class responsible for defining settings callbacks
			 */
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/WeeConnectPaySettingsCallback.php';

			/**
			 * The class responsible for registering settings
			 */
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/RegisterSettings.php';

			/**
			 * The class responsible for controlling custom REST endpoints
			 */
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/WeeConnectPayController.php';

			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/modules/WeeConnectPay/Integration/AdminPanel.php';
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/modules/WeeConnectPay/Integration/PaymentFields.php';


			/**
			 * The class responsible for orchestrating the actions and filters of the
			 * core plugin.
			 */
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/WeeConnectPayLoader.php';

			/**
			 * The class responsible for defining internationalization functionality
			 * of the plugin.
			 */
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/WeeConnectPayI18n.php';

			// Commenting out deprecated import class as of 3.8.0 since it is unused and not HPOS compliant
			// require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/integrations/woocommerce/WeeConnectPayWooProductImport.php';

			/**
			 * The class responsible for defining all actions that occur in the admin area.
			 */
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/WeeConnectPayAdmin.php';

			/**
			 * The class responsible for defining all actions that occur in the site-facing
			 * side of the site.
			 */
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'site/WeeConnectPayPublic.php';

			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/WeeConnectPayAPI.php';

            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/integrations/woocommerce/WeeConnectPayOrderProcessor.php';

			$this->loader = new WeeConnectPayLoader();
		}

		/**
		 * Define the locale for this plugin for internationalization.
		 *
		 * Uses the WeeConnectPayI18N class in order to set the domain and to register the hook
		 * with WordPress.
		 *
		 * @since    1.0.0
		 * @access   private
		 */
		private function setLocale() {
			$pluginI18n = new WeeConnectPayI18N();

			$this->loader->addAction( 'init', $pluginI18n, 'loadPluginTextdomain' );
		}

		/**
		 * Register all the hooks related to the admin area functionality
		 * of the plugin.
		 *
		 * @updated  3.9.0
		 * @since    1.0.0
		 * @access   private
		 */
		private function defineAdminHooks() {
			$pluginAdmin = new WeeConnectPayAdmin(
				$this->getPluginName(),
				$this->getVersion()
			);

			$this->loader->addAction( 'admin_enqueue_scripts', $pluginAdmin, 'enqueueStyles' );
			$this->loader->addAction( 'admin_enqueue_scripts', $pluginAdmin, 'enqueueScripts' );

			// admin_menu hook to add custom page
			//$this->loader->addAction( 'admin_menu', $pluginAdmin, 'addToplevelMenu' );
			// plugin options for settings page ( admin_init fires only in the admin area )
			$this->loader->addAction( 'admin_init', $pluginAdmin, 'registerSettings' );

			// WP REST API plugin routes
			$this->loader->addAction( 'rest_api_init', $pluginAdmin, 'register_routes' );

			// Ajax Refund for WooCommerce order edit page
			$this->loader->addAction( 'wp_ajax_weeconnectpay_refund_charge', $pluginAdmin, 'weeconnectpay_refund_charge_callback' );

			// WooCommerce's settings action link in the plugins page - plugin folder must be named appropriately
			$this->loader->addFilter( 'plugin_action_links_' . $this->getPluginName() . '/' . $this->getPluginName() . '.php', $pluginAdmin, 'pluginActionLinks' );


			// WooCommerce Order search added fields to search in
			$this->loader->addFilter( 'woocommerce_shop_order_search_fields', $pluginAdmin, 'added_wc_search_fields' );

			// This would have been a good time to use isHighPerformanceOrderStorageEnabled but this is too early in initialisation to reliably use it
			// [HPOS compliant] WooCommerce Orders Page add card brand as a column
			$this->loader->addFilter( 'manage_woocommerce_page_wc-orders_columns', $pluginAdmin, 'add_custom_orders_column_card_brand_hpos_and_legacy');

			// [HPOS compliant] WooCommerce Orders Page populate card brand column value with order data
			$this->loader->addFilter( 'manage_woocommerce_page_wc-orders_custom_column', $pluginAdmin, 'populate_custom_orders_column_card_brand_hpos', 10,2);

			// [LEGACY / NOT HPOS compliant] WooCommerce Orders Page add card brand as a column
			$this->loader->addFilter( 'manage_edit-shop_order_columns', $pluginAdmin, 'add_custom_orders_column_card_brand_hpos_and_legacy');

			// [LEGACY / NOT HPOS compliant] WooCommerce Orders Page populate card brand column value with order data
			$this->loader->addFilter( 'manage_shop_order_posts_custom_column', $pluginAdmin, 'populate_custom_orders_column_card_brand_legacy', 10, 2);

			// Added handle_log_download hook
			$this->loader->addAction( 'admin_init', $pluginAdmin, 'handle_log_download' );

			// Register REST API routes for logs
			$logController = new WeeConnectPayLogController();
			$this->loader->addAction('rest_api_init', $logController, 'register_routes');
		}

		/**
		 * Register all the hooks related to the site-facing functionality
		 * of the plugin.
		 *
		 * @since    1.0.0
		 * @updated  3.3.0
		 * @access   private
		 */
		private function definePublicHooks() {
			$pluginPublic = new WeeConnectPayPublic( $this->getPluginName(), $this->getVersion() );
			// Register WooCommerce Payment Blocks support
			$this->loader->addAction( 'woocommerce_blocks_payment_method_type_registration', $pluginPublic, 'addWeeconnectpayGatewayBlockSupport' ,5);

			$this->loader->addAction( 'wp_enqueue_scripts', $pluginPublic, 'enqueueStyles' );
			$this->loader->addAction( 'wp_enqueue_scripts', $pluginPublic, 'enqueueScripts' );

			// Load the gateway class that extends WooCommerce payment gateways.
			$this->loader->addAction( 'plugins_loaded', $pluginPublic, 'initWeeconnectpayGateway' );
			// Register the gateway with WooCommerce
			$this->loader->addAction( 'woocommerce_payment_gateways', $pluginPublic, 'addWeeconnectpayGateway' );
			// Exclude some external scripts from being bundled by SiteGround
			$this->loader->addAction( 'sgo_javascript_combine_excluded_external_paths', $pluginPublic, 'excludeExternalScriptsFromSiteGroundCombine');
			// Removes 'Pro' Theme elements that break the Clover SDK. See PHPDoc of the function for more explanation
			$this->loader->addAction( 'wp_loaded', $pluginPublic, 'remove_sdk_breaking_pro_theme_hidden_spinner');

		}

		/**
		 * Run the loader to execute all the hooks with WordPress.
		 *
		 * @since    1.0.0
		 * @updated  1.3.9
		 */
		public function run() {
				$this->loader->run();
		}

		/**
		 * The name of the plugin used to uniquely identify it within the context of
		 * WordPress and to define internationalization functionality.
		 *
		 * @return    string    The name of the plugin.
		 * @since     1.0.0
		 */
		public function getPluginName(): string {
			return $this->pluginName;
		}

		/**
		 * The reference to the class that orchestrates the hooks with the plugin.
		 *
		 * @return    WeeConnectPayLoader    Orchestrates the hooks of the plugin.
		 * @since     1.0.0
		 */
		public function getLoader(): WeeConnectPayLoader {
			return $this->loader;
		}

		/**
		 * Retrieve the version number of the plugin.
		 *
		 * @return    string    The version number of the plugin.
		 * @since     1.0.0
		 */
		public function getVersion(): string {
			return $this->version;
		}
	}
}
