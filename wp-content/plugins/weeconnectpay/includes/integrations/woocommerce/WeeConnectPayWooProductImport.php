<?php
// phpcs:disable WordPress
namespace WeeConnectPay\WordPress\Plugin\includes;

require_once plugin_dir_path( __FILE__ ) . '/ProductToImport.php';

/**
 * @deprecated Doesn't seem to be used as 3.8.0, and we are implementing HPOS, which means we need to use
 *   the WooCommerce API to query post data in order to be sure we get it from the right table.
 */
class WeeConnectPayWooProductImport {

	public $import_link;
	public $products_to_import = array();
	public $api;

	/**
	 * WeeConnectPayWooProductImport constructor.
	 * @deprecated Doesn't seem to be used as 3.8.0, and we are implementing HPOS, which means we need to use
	 *   the WooCommerce API to query post data in order to be sure we get it from the right table.
	 */
	public function __construct() {
		$this->api = new WeeConnectPayAPI( 'woocommerce_weeconnectpay_settings' );
	}


	/**
	 * @return void
	 * @deprecated Doesn't seem to be used as 3.8.0, and we are implementing HPOS, which means we need to use
	 *   the WooCommerce API to query post data in order to be sure we get it from the right table.
	 */
	public function process_import() {
		// request the right file link

		$import_details = $this->api->get_expanded_merchant_inventory();

		if ( isset( $import_details->error, $import_details->error->code ) ) {

			if ( 'unauthorized_sub_tier' === $import_details->error->code ) {
				return;
			} else {
				return;
			}
		}

		if ( ! isset( $import_details, $import_details->elements ) ) {

		} else {

			foreach ( $import_details->elements as $key => $product ) {
				$wc_product = new \WC_Product();
				$wc_product->set_name( $product->name );
				$wc_product->set_regular_price( number_format( ( $product->price / 100 ), 2, '.', '' ) );
				$wc_product->set_stock_quantity( $product->itemStock->quantity );
				$wc_product->set_manage_stock( true );
				$wc_product->add_meta_data(
					'weeconnectpay_metadata',
					array(
						'clover_imported' => true,
						'clover'          => array(
							'product_id'                   => $product->id,
							'product_is_hidden'            => $product->hidden,
							'product_options'              => $product->options->elements,
							'product_name'                 => $product->name,
							// We will need this metadata as it's likely merchants will change the woocommerce names
							'product_code'                 => $product->code ?? null,
							'product_sku'                  => $product->sku ?? null,
							'product_price_type'           => $product->priceType,
							'product_is_default_tax_rates' => $product->defaultTaxRates,
							'product_tax_rates'            => $product->taxRates->elements,
							'product_modifier_groups'      => $product->modifierGroups->elements,
							'product_categories'           => $product->categories->elements,
							'product_tags'                 => $product->tags->elements,
							'product_modified_time'        => $product->modifiedTime,
						)
					)
				);
				/** @TODO: create tag if non existent in woocommerce */
				$product_tags_ids = $this->get_clover_product_tag_ids( $product->tags );
				$wc_product->set_tag_ids( $product_tags_ids );
				$wc_product->save();
				$weeconnectpay_metadata = $wc_product->get_meta_data();

			}
			wp_send_json_success( 'Import successful!!!' );
		}

	}

	private function get_clover_product_tag_ids( $tags ): array {
		$tag_array = array();
		if ( isset( $tags->elements ) ) {
			foreach ( $tags->elements as $tag ) {
				array_push( $tag_array, $tag->id );
			}
		}

		return $tag_array;
	}

	/**
	 * @param $product
	 *
	 * @return void
	 * @deprecated Doesn't seem to be used as 3.8.0, and we are implementing HPOS, which means we need to use
	 *  the WooCommerce API to query post data in order to be sure we get it from the right table. --
	 *  $wpdb->posts is not the way to go, same thing with update_post_meta
	 */
	private function create_product( $product ) {

		global $wpdb, $user_ID;

		$post_type = 'product';
		$post_data = array(
			'post_author'    => $user_ID,
			'post_date'      => current_time( 'mysql' ),
			'post_date_gmt'  => current_time( 'mysql', 1 ),
			'post_title'     => ( ! empty( $product->title ) ? $product->title : '' ),
			'post_status'    => 'publish',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'post_type'      => $post_type,
			'post_content'   => ( ! empty( $product->description ) ? $product->description : '' ),
			'post_excerpt'   => ( ! empty( $product->excerpt ) ? $product->excerpt : '' ),
			'tax_input'      => array(
				'product_type' => 'simple'
			)
		);

		$product->woocommerce_product_post->id = wp_insert_post( $post_data, true );
		if ( ! is_wp_error( $product->woocommerce_product_post->id ) ) {

			// Manually refresh the Post GUID
			// 	 * @deprecated Doesn't seem to be used as 3.8.0, and we are implementing HPOS, which means we need to use
			//	 * the WooCommerce API to query post data in order to be sure we get it from the right table.
			$wpdb->update(
				$wpdb->posts,
				array(
					'guid' => sprintf( '%s/?post_type=%s&p=%d', get_bloginfo( 'url' ), $post_type, $product->woocommerce_product_post->id )
				),
				array( 'ID' => $product->woocommerce_product_post->id )
			);

			// Set defaults
			$this->create_product_post_defaults( $product );
			// Set actual values
			$this->create_product_post_details( $product );
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $product->woocommerce_product_post->id );
			}

		} else {

			ob_start();
			var_dump( $post_data );
			$output = ob_get_contents();
			ob_end_clean();
		}

	}


	/**
	 * @param ProductToImport $product
	 *
	 * @return void
	 *
	 * @deprecated Doesn't seem to be used as 3.8.0, and we are implementing HPOS, which means we need to use
	 * the WooCommerce API to query post data in order to be sure we get it from the right table.
	 *
	 */
	public function create_product_post_defaults( ProductToImport $product ) {

		$product->set_woocommerce_defaults();

		if ( $product->woocommerce_product_post ) {
			foreach ( $product->woocommerce_product_post as $key => $default ) {
				update_post_meta( $product->woocommerce_product_post->id, $key, $default );
			}
		}
	}

	/**
	 * @param ProductToImport $product
	 *
	 * @return void
	 * @deprecated Doesn't seem to be used as 3.8.0, and we are implementing HPOS, which means we need to use
	 *     the WooCommerce API to query post data in order to be sure we get it from the right table.
	 */
	public function create_product_post_details( ProductToImport $product ) {

		$product->set_woocommerce_details();

		if ( $product->woocommerce_product_post ) {
			foreach ( $product->woocommerce_product_post as $key => $detail ) {
				update_post_meta( $product->woocommerce_product_post->id, $key, $detail );
			}
		}
	}


	public function validate_clover_csv_headers( $csv ): bool {
		return $csv[0] === 'Clover ID' &&
			   $csv[1] === 'Title' &&
			   $csv[2] === 'Currency' &&
			   $csv[3] === 'Price' &&
			   $csv[4] === 'Price Type' &&
			   $csv[5] === 'Price Unit' &&
			   $csv[6] === 'Taxable' &&
			   $csv[7] === 'Cost' &&
			   $csv[8] === 'Product Code' &&
			   $csv[9] === 'Category Name' &&
			   $csv[10] === 'Quantity';
	}

	public function validate_clover_csv_length( $csv ): bool {
		// Clover ID, Title, Currency, Price, Price Type, Price Unit, Taxable, Cost, Product Code, Category Name, Quantity
		$data_length = 11;

		return count( $csv ) === $data_length;
	}

	private function validate_clover_csv( $csv_headers ): bool {
		$is_valid_csv_length = $this->validate_clover_csv_length( $csv_headers );
		if ( ! $is_valid_csv_length ) {

			return false;
		}

		$is_valid_clover_csv_headers = $this->validate_clover_csv_headers( $csv_headers );
		if ( ! $is_valid_clover_csv_headers ) {
			return false;
		}

		return true;
	}

}

