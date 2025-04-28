<?php
// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda


namespace WeeConnectPay\WordPress\Plugin\includes;

/**
 * Class ValidateSettings
 *
 * @package WeeConnectPay\WordPress\Plugin\includes
 */
class ValidateSettings {

	/**
	 * @param array $input
	 *
	 * @return array
	 */
	private static function validateWooCommerceIntegrationRadio( array $input ): array {
		// Integration Enabled?
		$radioOptions = self::weeconnectpayOptionsRadio();

		if ( ! isset( $input['is_enabled'] ) ) {
			$input['is_enabled'] = null;
		}
		if ( ! array_key_exists( $input['is_enabled'], $radioOptions ) ) {
			$input['is_enabled'] = null;
		}

		return $input;
	}

	/**
	 * @param array $input
	 *
	 * @return array
	 */
	private static function validateSelectOptions( array $input ): array {
		// custom scheme
		$selectOptions = self::weeconnectpayOptionsSelect();

		if ( ! isset( $input['custom_scheme'] ) ) {
			$input['custom_scheme'] = null;
		}

		if ( ! array_key_exists( $input['custom_scheme'], $selectOptions ) ) {
			$input['custom_scheme'] = null;
		}

		return $input;
	}

	/**
	 * @return array
	 */
	private static function weeconnectpayOptionsSelect(): array {
		return array(
			'default'   => esc_html__( 'Default', 'weeconnectpay' ),
			'light'     => esc_html__( 'Light', 'weeconnectpay' ),
			'blue'      => esc_html__( 'Blue', 'weeconnectpay' ),
			'coffee'    => esc_html__( 'Coffee', 'weeconnectpay' ),
			'ectoplasm' => esc_html__( 'Ectoplasm', 'weeconnectpay' ),
			'midnight'  => esc_html__( 'Midnight', 'weeconnectpay' ),
			'ocean'     => esc_html__( 'Ocean', 'weeconnectpay' ),
			'sunrise'   => esc_html__( 'Sunrise', 'weeconnectpay' ),
		);
	}

	// radio field options

	/**
	 * @param array $input The options to validate
	 *
	 * @return array The validated options;
	 */
	public static function callbackValidateOptions(  $input ) {
		/** @TODO: Sanitization des options apres validation.  */
		return $input;
		$input = self::validateWooCommerceIntegrationRadio( $input );

		// custom url
		if ( isset( $input['custom_url'] ) ) {
			$input['custom_url'] = esc_url( $input['custom_url'] );
		}

		// custom title
		if ( isset( $input['custom_title'] ) ) {
			$input['custom_title'] = sanitize_text_field( $input['custom_title'] );
		}

		// custom message
		if ( isset( $input['custom_message'] ) ) {
			$input['custom_message'] = wp_kses_post( $input['custom_message'] );
		}

		// custom footer
		if ( isset( $input['custom_footer'] ) ) {
			$input['custom_footer'] = sanitize_text_field( $input['custom_footer'] );
		}

		// custom toolbar
		if ( ! isset( $input['custom_toolbar'] ) ) {
			$input['custom_toolbar'] = null;
		}

		$input['custom_toolbar'] = ( $input['custom_toolbar'] === 1 ? 1 : 0 );

		$input = self::validateSelectOptions( $input );

		return $input;
	}

	/**
	 * Returns all the radio options
	 *
	 * @return array Radio fields options
	 */
	public static function weeconnectpayOptionsRadio(): array {
		return array(

			'enable'  => esc_html__( 'Enable WooCommerce WeeConnectPay Clover Integration', 'weeconnectpay' ),
			'disable' => esc_html__( 'Disable WooCommerce WeeConnectPay Clover Integration', 'weeconnectpay' ),

		);
	}
}
