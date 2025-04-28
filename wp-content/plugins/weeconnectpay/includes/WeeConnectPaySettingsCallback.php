<?php
// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

namespace WeeConnectPay\WordPress\Plugin\includes;

/**
 * Class WeeConnectPaySettingsCallback
 */
class WeeConnectPaySettingsCallback {

	public $options;

	public function __construct() {
		$this->options = get_option( 'weeconnectpay_integration', $this->optionsDefault() );
	}


	/**
	 *  Sets the plugin fields default values
	 *  In the event that there is no plugin settings saved to the DB
	 *
	 * @return array
	 */
	public function optionsDefault(): array {
		return array(
			'custom_url'     => 'https://wordpress.org/',
			'custom_title'   => 'Powered by WordPress',
			'custom_style'   => 'disable',
			'custom_message' => '<p class="custom-message">My custom message</p>',
			'custom_footer'  => 'Special message for users',
			'custom_toolbar' => false,
			'custom_scheme'  => 'default',
		);
	}



	/**
	 * @param $input
	 *
	 * @return mixed
	 *
	 */
	public function validateOptions( $input ) {
		return $input;
	}

	public function callbackSectionPluginIntegrations() {
		echo '<p>These settings enable you to customize the different integrations provided by WeeConnectPay.</p>';
	}


	public function callbackSectionCorePlugin() {
		echo '<p>These settings enable you to customize WeeConnectPay.</p>';
	}


	/**
	 * @param $args
	 */
	public function callbackFieldText( $args ) {
		$id    = isset( $args['id'] ) ? $args['id'] : '';
		$label = isset( $args['label'] ) ? $args['label'] : '';

		$value = isset( $this->options[ $id ] ) ? sanitize_text_field( $this->options[ $id ] ) : '';

		echo '<input id="weeconnectpay_options_' . esc_attr($id) . '" name="weeconnectpay_options[' . esc_attr($id) . ']" 
                     type="text" size="40" value="' . esc_textarea($value) . '"><br />';
		echo '<label for="weeconnectpay_options_' . esc_attr($id) . '">' . esc_html($label) . '</label>';
	}

	/**
	 * @param $args
	 */
	public function callbackFieldRadio( $args ) {
		$id = isset( $args['id'] ) ? $args['id'] : '';
		//$label = isset($args['label']) ? $args['label'] : '';

		$selectedOption = isset( $this->options[ $id ] ) ? sanitize_text_field( $this->options[ $id ] ) : '';

		$radioOptions = array(

			'enable'  => 'Enable WooCommerce WeeConnectPay Clover Integration',
			'disable' => 'Disable WooCommerce WeeConnectPay Clover Integration',

		);

		foreach ( $radioOptions as $value => $label ) {
			$checked = checked( $selectedOption === $value, true, false );

			echo '<label><input name="weeconnectpay_options[' . esc_attr($id) . ']" 
                                type="radio" value="' . esc_textarea($value) . '" ' . esc_attr($checked) . '> ';
			echo '<span>' . esc_html($label) . '</span></label><br />';
		}
	}


	/**
	 * @param $args
	 */
	public function callbackFieldTextarea( $args ) {
		$id    = isset( $args['id'] ) ? $args['id'] : '';
		$label = isset( $args['label'] ) ? $args['label'] : '';

		$allowedTags = wp_kses_allowed_html( 'post' );

		$value = isset( $this->options[ $id ] ) ? wp_kses( stripslashes_deep( $this->options[ $id ] ), $allowedTags ) : '';

		echo '<textarea id="weeconnectpay_options_' . esc_attr($id) . '" name="weeconnectpay_options[' . esc_attr($id) . ']" 
                        rows="5" cols="50">' . esc_textarea($value) . '</textarea><br />';
		echo '<label for="weeconnectpay_options_' . esc_attr($id) . '">' . esc_html( $label) . '</label>';
	}


	// callback: checkbox field

	/**
	 * @param $args
	 */
	public function callbackFieldCheckbox( $args ) {
		$id    = isset( $args['id'] ) ? $args['id'] : '';
		$label = isset( $args['label'] ) ? $args['label'] : '';

		$checked = isset( $this->options[ $id ] ) ? checked( $this->options[ $id ], 1, false ) : '';

		echo /**@lang HTML */ '<input id="weeconnectpay_options_' . esc_attr($id) . '" name="weeconnectpay_options[' . esc_attr($id) . ']" 
                     type="checkbox" value="1" ' . esc_attr($checked) . ' >';
		echo '<label for="weeconnectpay_options_' . esc_attr($id) . '">' . esc_html($label) . '</label>';
	}


	// callback: select field


	/**
	 * @param $args
	 */
	public function callbackFieldSelect( $args ) {
		$id    = isset( $args['id'] ) ? $args['id'] : '';
		$label = isset( $args['label'] ) ? $args['label'] : '';

		$selectedOption = isset( $this->options[ $id ] ) ? sanitize_text_field( $this->options[ $id ] ) : '';

		$selectOptions = array(

			'default'   => 'Default',
			'light'     => 'Light',
			'blue'      => 'Blue',
			'coffee'    => 'Coffee',
			'ectoplasm' => 'Ectoplasm',
			'midnight'  => 'Midnight',
			'ocean'     => 'Ocean',
			'sunrise'   => 'Sunrise',

		);

		echo /**@lang HTML */ '<select id="weeconnectpay_options_' . esc_attr($id) . '" name="weeconnectpay_options[' . esc_attr($id) . ']">';

		foreach ( $selectOptions as $value => $option ) {
			$selected = selected( $selectedOption === $value, true, false );

			echo '<option value="' . esc_attr($value) . '" ' . esc_attr($selected) . '>' . esc_html($option) . '</option>';
		}

		echo '</select> <label for="weeconnectpay_options_' . esc_attr($id) . '">' . esc_html($label) . '</label>';
	}
}
