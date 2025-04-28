<?php
// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/ParogDev
 * @since      1.0.0
 *
 * @package    WeeConnectPay
 * @subpackage WeeConnectPay/admin
 */

namespace WeeConnectPay\WordPress\Plugin\admin;

use Exception;
use WC_Order;
use WeeConnectPay\WordPress\Plugin\includes\RegisterSettings;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayController;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayHelper;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPaySettingsCallback;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayUtilities;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayWooProductImport;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WeeConnectPay
 * @subpackage WeeConnectPay/admin
 * @author     ParogDev <integration@cspaiement.com>
 */
class WeeConnectPayAdmin {


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
	 * An instance of the WeeConnectPaySettingsCallback class
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var WeeConnectPaySettingsCallback Class holding settings callback functions
	 */
	private $settingsCallback;

	/**
	 * An instance of the RegisterSettings class
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var RegisterSettings
	 */
	private $settings;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $pluginName The name of this plugin.
	 * @param string $version The version of this plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct( string $pluginName, string $version ) {
		$this->pluginName       = $pluginName;
		$this->version          = $version;
		$this->settingsCallback = new WeeConnectPaySettingsCallback();
		$this->settings         = new RegisterSettings( $this->settingsCallback );
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueueStyles() {

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

//		 PostProcess CSS
		      wp_enqueue_style(
		          $this->pluginName,
		          plugin_dir_url( __FILE__ ) . 'css/weeconnectpay-admin.css',
		          array(),
		          time(),
		          'all'
		      );

	}

	/**
	 * Register the JavaScript for the admin area.
	 * @updated  2.0.4
	 * @since    1.0.0
	 */
	public function enqueueScripts() {

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

		wp_enqueue_script(
			$this->pluginName,
			plugin_dir_url( __FILE__ ) . 'js/weeconnectpay-admin.js',
			array( 'jquery' ),
			$this->version,
			false
		);
	}

	/**
	 * Add the top level menu for the admin area
	 *
	 * @since   1.0.0
	 */
	public function addToplevelMenu() {
		add_menu_page(
			'WeeConnectPay Settings',
			'WeeConnectPay',
			'manage_options',
			'weeconnectpay',
			array( $this, 'loadAdminPageContent' ),
			'dashicons-admin-generic',
			null
		);
	}

	/**
	 * Adds plugin action links.
	 *
	 * @param  $actions
	 *
	 * @return array
	 * @since 1.0.1
	 */
	public function pluginActionLinks( $actions ): array {
		$woocommerce_settings_url = WeeConnectPayUtilities::getSettingsURL();
		$plugin_links             = array(
			"<a href=$woocommerce_settings_url>" . __( 'WooCommerce Settings', 'weeconnectpay' ) . '</a>',
		);

		return array_merge( $plugin_links, $actions );
	}


	/**
	 * Ajax init_weeconnectpay_import handler
	 */
	public function init_weeconnectpay_import() {
		$import = new WeeConnectPayWooProductImport();
		$import->process_import();
		wp_send_json_success( 'It works' );
	}

	/**
	 * Load the admin page partial
	 *
	 * @since   1.0.0
	 * @noinspection PhpIncludeInspection
	 */
	public function loadAdminPageContent() {
		require_once plugin_dir_path( __FILE__ ) . 'partials/weeconnectpayAdminDisplay.php';
	}


	/**
	 * Registers the settings
	 *
	 * @since 1.0.0
	 */
	public function registerSettings() {
		$registerSettings = new RegisterSettings( $this->settingsCallback );
		$registerSettings->run();
	}

	public function register_routes() {
			$WeeConnectPayController = new WeeConnectPayController();
			$WeeConnectPayController->register_routes();
	}

	/**
	 *  Adds search additional fields to query when using the WooCommerce order search bar
	 *
	 * @since 2.2.0
	 * @param array $search_fields
	 *
	 * @return array
	 */
	function added_wc_search_fields( array $search_fields ): array {
		$search_fields[] = 'weeconnectpay_clover_order_uuid';
		$search_fields[] = 'weeconnectpay_clover_payment_uuid';
		return $search_fields;
	}


	/**
	 * Since we do not deal with orders but simply the ordering of an array, this can be used for both hpos and legacy.
	 * The name is specified since we do use a different hook for it in each case.
	 *
	 * @param $columns
	 *
	 * @return array
	 * @since 3.9.0
	 */
	function add_custom_orders_column_card_brand_hpos_and_legacy($columns): array {
		$reordered_columns = array();

		foreach( $columns as $key => $column){
			$reordered_columns[$key] = $column;

			if( $key ===  'shipping_address' ){
				// Inserting after "Total" column
				$reordered_columns['card-brand'] = __( 'Card Brand','weeconnectpay');
			}
		}
		return $reordered_columns;
	}


	/**
	 * Populates the "Card Brand" column with data from the order if available.
	 *
	 * @param $column
	 * @param $order
	 *
	 * @return void
	 * @since 3.9.0
	 */
	public function populate_custom_orders_column_card_brand_hpos($column, $order): void {
		$this->populate_custom_orders_column_card_brand_wrapper( $column, $order );
	}


	/**
	 * Populates the "Card Brand" column with data from the order if available.
	 *
	 * @param $column
	 * @param $post_id
	 *
	 * @return void
	 * @since 3.9.0
	 */
	public function populate_custom_orders_column_card_brand_legacy($column, $post_id): void {
		$order = wc_get_order( $post_id );

		$this->populate_custom_orders_column_card_brand_wrapper( $column, $order );
	}

	/**
	 * Wrapper to ensure that if we change anything, it will be changed for both legacy and hpos versions
	 * @param $column
	 * @param $order
	 *
	 * @return void
	 * @since 3.9.0
	 */
	protected function populate_custom_orders_column_card_brand_wrapper( $column, $order ): void {
		if ( $order instanceof WC_Order && $column === 'card-brand' ) {
			// Get custom order metadata
			$cardBrand = $order->get_meta( 'weeconnectpay_card_brand' );
			if ( ! empty( $cardBrand ) ) {
				echo esc_html( $cardBrand );
			}
		}
	}

    public static function weeconnectpay_refund_charge_callback()
    {
        // Check nonce for security.
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'weeconnectpay_refund_charge_nonce')) {
            wp_send_json_error('Invalid nonce.');
        }

        // Verify current user permissions.
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions.');
        }

        // Get and validate the order, charge ID, and refund amount.
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $charge_id = isset($_POST['charge_id']) ? sanitize_text_field(wp_unslash($_POST['charge_id'])) : '';
        // Refund amount here is expected as raw cents.
        $refund_amount = isset($_POST['refund_amount']) ? absint($_POST['refund_amount']) : 0;

        if (!$order_id || empty($charge_id) || !$refund_amount) {
            wp_send_json_error('Missing order ID, charge ID, or refund amount.');
        }

        // Load the order.
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found.');
        }

        try {
            // Process the refund via the static helper function.
            // Using default reason 'requested_by_customer' and an empty externalReferenceId here.
            $refundData = WeeConnectPayHelper::processCloverChargeRefund($charge_id, $refund_amount);

            // Only update the order meta if the refund was successful.
            WeeConnectPayHelper::updateCreditCardChargeToRefunded($order, $charge_id);

            // Create a WooCommerce refund.
            // Convert the refund amount from cents to dollars for WooCommerce.
            $refund = wc_create_refund([
                'amount'    => wc_format_decimal($refund_amount / 100, 2),
                'reason'    => 'Refund for charge ' . $charge_id,
                'order_id'  => $order_id,
            ]);
            if (is_wp_error($refund)) {
                throw new Exception('Refund creation failed: ' . $refund->get_error_message());
            }

            // Add refund notes using the helper
            WeeConnectPayHelper::addRefundNotesToOrder($order, [
                'result' => 'success',
                'data' => $refundData
            ], 'Refund for charge ' . $charge_id);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }

        // Return the refund data (from Clover) only if the refund was successful.
        wp_send_json_success($refundData);
    }

    /**
     * Handle log download requests
     * 
     * @since 3.12.6
     * @access public
     */
    public function handle_log_download(): void {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (isset($_POST['weeconnectpay_download_logs'])) {
            $filters = [
                'level' => isset($_GET['log_level']) ? sanitize_text_field($_GET['log_level']) : '',
                'date_from' => isset($_GET['log_date_from']) ? sanitize_text_field($_GET['log_date_from']) : '',
                'date_to' => isset($_GET['log_date_to']) ? sanitize_text_field($_GET['log_date_to']) : '',
            ];

            $logger = new \WeeConnectPay\Integrations\Logger();
            $filepath = $logger->downloadLogs($filters);
            if ($filepath && file_exists($filepath)) {
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
                header('Content-Length: ' . filesize($filepath));
                readfile($filepath);
                unlink($filepath); // Clean up the temporary file
                exit;
            }
        }
    }

}
