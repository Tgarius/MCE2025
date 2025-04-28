<?php
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
namespace WeeConnectPay\WordPress\Plugin\includes;

use WC_Product;
use WC_Product_Importer;

class ProductToImport {
	public $clover_id;
	public $title;
	public $currency;
	public $price;
	public $price_type;
	public $price_unit;
	public $taxable;
	public $cost;
	public $product_code;
	public $category_name;
	public $quantity;
	public $woocommerce_product_post;

	public function __construct( $params, bool $is_api_import = false ) {

		if ( $is_api_import ) {

			$this->clover_id = $params->id;
			$this->title = $params->name;
			/** @TODO: Use merchant currency for API inventory import */
			$this->currency = 'CAD';
			$this->price    = number_format( ( $params->price / 100 ), 2, '.', '' );
			//$this->price_type    = $params->priceType;
			//$this->price_unit    = $params;
			//$this->taxable       = $params[6];
			//$this->cost          = $params[7];
			//$this->product_code  = $params[8];
			//$this->category_name = $params[9];
			$this->quantity = $params->itemStock->quantity;

			// PHP <7.4 support
			$this->woocommerce_product_post = (object) array();
		}

		// Param position should be validated beforehand... But check length to make sure
		if ( count( $params ) < 11 ) {
			return;
		}
		$this->clover_id     = $params[0];
		$this->title         = $params[1];
		$this->currency      = $params[2];
		$this->price         = $params[3];
		$this->price_type    = $params[4];
		$this->price_unit    = $params[5];
		$this->taxable       = $params[6];
		$this->cost          = $params[7];
		$this->product_code  = $params[8];
		$this->category_name = $params[9];
		$this->quantity      = $params[10];

		// PHP <7.4 support
		$this->woocommerce_product_post = (object) array();

	}


	public function set_woocommerce_defaults() {

		$this->woocommerce_product_post->_regular_price         = 0;
		$this->woocommerce_product_post->_price                 = '';
		$this->woocommerce_product_post->_sale_price            = '';
		$this->woocommerce_product_post->_sale_price_dates_from = '';
		$this->woocommerce_product_post->_sale_price_dates_to   = '';
		$this->woocommerce_product_post->_sku                   = '';
		$this->woocommerce_product_post->_weight                = 0;
		$this->woocommerce_product_post->_length                = 0;
		$this->woocommerce_product_post->_width                 = 0;
		$this->woocommerce_product_post->_height                = 0;
		$this->woocommerce_product_post->_tax_status            = 'taxable';
		$this->woocommerce_product_post->_tax_class             = '';
		$this->woocommerce_product_post->_stock_status          = 'instock';
		$this->woocommerce_product_post->_visibility            = 'visible';
		$this->woocommerce_product_post->_featured              = 'no';
		$this->woocommerce_product_post->_downloadable          = 'no';
		$this->woocommerce_product_post->_virtual               = 'no';
		$this->woocommerce_product_post->_sold_individually     = '';
		$this->woocommerce_product_post->_product_attributes    = array();
		$this->woocommerce_product_post->_manage_stock          = 'no';
		$this->woocommerce_product_post->_backorders            = 'no';
		$this->woocommerce_product_post->_stock                 = '';
		$this->woocommerce_product_post->_purchase_note         = '';
		$this->woocommerce_product_post->total_sales            = 0;

		$this->woocommerce_product_post->_weeconnectpay_imported = true;
	}

	public function set_woocommerce_details() {

		$this->create_woo_product();

		//$this->woocommerce_product_post->_regular_price = $this->price;

		$this->woocommerce_product_post->_price = $this->price;
		//$this->woocommerce_product_post->_sale_price            = '';
		//$this->woocommerce_product_post->_sale_price_dates_from = '';
		//$this->woocommerce_product_post->_sale_price_dates_to   = '';
		$this->woocommerce_product_post->_sku = $this->clover_id;
		//$this->woocommerce_product_post->_weight = 0;
		//$this->woocommerce_product_post->_length = 0;
		//$this->woocommerce_product_post->_width  = 0;
		//$this->woocommerce_product_post->_height = 0;
		/* @TODO: Add taxable vs non-taxable order creation + refund support */
		$this->woocommerce_product_post->_tax_status = $this->taxable !== 'No' ? 'taxable' : '';
		//$this->woocommerce_product_post->_tax_class          = '';
		$this->woocommerce_product_post->_stock_status = $this->quantity > 0 ? 'instock' : 'outofstock';
		//$this->woocommerce_product_post->_visibility         = 'visible';
		//$this->woocommerce_product_post->_featured           = 'no';
		//$this->woocommerce_product_post->_downloadable       = 'no';
		//$this->woocommerce_product_post->_virtual            = 'no';
		//$this->woocommerce_product_post->_sold_individually  = '';
		//$this->woocommerce_product_post->_product_attributes = array();
		// Lets us manage stock through imports and webhook
		$this->woocommerce_product_post->_manage_stock = 'yes';
		//$this->woocommerce_product_post->_backorders         = 'no';
		$this->woocommerce_product_post->_stock = $this->quantity;
		//      $this->woocommerce_product_post->_stock              = number_format( $this->quantity,0, '','');
		//$this->woocommerce_product_post->_purchase_note      = '';
		//$this->woocommerce_product_post->total_sales         = 0;

		/* @TODO: Add support for products changing names in clover */
		// $this->title     = $params[1]; // Already defined at post creation
		$this->woocommerce_product_post->_clover_currency = $this->currency;

		/*      $this->price_type;
		$this->price_unit;
		$this->cost;
		$this->product_code;
		$this->category_name;*/

	}

}
