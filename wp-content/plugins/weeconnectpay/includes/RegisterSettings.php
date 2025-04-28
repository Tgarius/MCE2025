<?php
// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

namespace WeeConnectPay\WordPress\Plugin\includes;

/**
 * Class responsible for registering and generating settings pages sections and fields;
 *
 * @package weeconnectpay\includes
 *
 * @since   1.0.0
 */
class RegisterSettings {

	private $callback;
	private $pages;
	private $sections;
	private $fields;


	/**
	 *  Initializes the settings related information we will need
	 *  to register them with the WordPress API.
	 *
	 * @param WeeConnectPaySettingsCallback $callback
	 *
	 * @since 1.0.0
	 */
	public function __construct( WeeConnectPaySettingsCallback $callback ) {
		$this->callback = $callback;

		$this->setPages();
		$this->setSections();
		$this->setFields();
	}

	/**
	 *  Registers the option settings with the WordPress API;
	 */
	private function registerSettings() {
		register_setting(
			'weeconnectpay_integration',
			'weeconnectpay_integration',
			array( ValidateSettings::class, 'callbackValidateOptions' )
		);
	}


	/**
	 *  Sets the pages and defines associated sections to be used to
	 *  generate sections for each page later
	 *
	 * @since 1.0.0
	 */
	private function setPages() {
		$this->pages = array(
			'weeconnectpay' => array(
				'sections' => array(
					'section_core_plugin'         => null,
					'section_plugin_integrations' => null,
				),
			),
		);
	}

	/**
	 *  Sets the sections and defines associated fields to be used to
	 *  generate fields for each section later
	 *
	 * @since 1.0.0
	 */
	private function setSections() {
		$this->sections = array(
			'section_core_plugin'         => array(
				'title'  => 'Customize Plugin Core',
				'fields' => array(
					'is_enabled'              => null,
					'clover_api_key'          => null,
					'weeconnectpay_access_token' => null,
				),
			),
			'section_plugin_integrations' => array(
				'title'  => 'Customize Plugin Integration',
				'fields' => array(
					'woocommerce_gateway_enabled'         => null,
					'woocommerce_gateway_displayed_title' => null,
					'woocommerce_gateway_displayed_desc'  => null,
				),
			),
		);
	}


	/**
	 *  Sets the fields and defines associated values to be used to
	 *  generate the rest of the information for each field later
	 *  Args(most HTML attributes should work!):
	 *      - label_for will be set to it's title on field creation;
	 *      - class will have CSS class additions depending on it's type
	 *
	 * @since 1.0.0
	 */
	private function setFields() {
		$this->fields = array(
			'woocommerce_gateway_enabled'         => array(
				'title' => 'WooCommerce Payment Gateway',
				'type'  => 'checkbox',
				'args'  => array(
					'class' => 'weeconnectpay-field weeconnectpay-checkbox weeconnectpay-checkbox-gateway-woocommerce',
					'label' => 'Toggles the WeeConnectPay Payment Gateway for WooCommerce',
				),
			),
			'woocommerce_gateway_displayed_title' => array(
				'title' => 'Title',
				'type'  => 'text',
				'args'  => array(
					'class' => 'weeconnectpay-field',
					'label' => 'This controls the title which the user sees during checkout.',
				),
			),
			'woocommerce_gateway_displayed_desc'  => array(
				'title' => 'Description',
				'type'  => 'textarea',
				'args'  => array(
					'class' => 'weeconnectpay-field',
					'label' => 'Displays the first part of the description which the users sees during checkout.',
				),
			),
			'clover_api_key'                      => array(
				'title' => 'Clover API Key',
				'type'  => 'text',
				'args'  => array(
					'class' => 'weeconnectpay-field',
					'label' => 'Your Clover API key',
				),
			),
		);
	}


	/**
	 *  Generates each page defined in this class.
	 *  for each page: passes the sections of that page to generateSections($pageSections).
	 *
	 * @since 1.0.0
	 */
	private function generatePages() {
		foreach ( $this->pages as $page => $pageContent ) {
			$this->generateSections( $pageContent['sections'], $page );
		}
	}

	/**
	 *  Generates sections for a single page;
	 *
	 * @param array $pageSections The sections to generate on the page
	 * @param string $page The current page being generated
	 *
	 * @since 1.0.0
	 */
	private function generateSections( $pageSections, string $page ) {

		// We only want the sections that were defined in the page we're being given.
		$intersectedSections = array_intersect_key( $this->sections, $pageSections );
		foreach ( $intersectedSections as $section => $sectionContent ) {
			add_settings_section(
				$section,
				$sectionContent['title'],
				array( $this->callback, $this->getSectionCallback( $section ) ),
				$page
			);
			$this->generateFields( $sectionContent['fields'], $section, $page );
		}
	}

	/**
	 * Generates fields for a single section
	 *
	 * @param array  $sectionFields The fields to generate in the section
	 * @param string $section       The current section being generated
	 * @param string $page          The current page being generated
	 */
	private function generateFields( $sectionFields, $section, $page ) {

		// We only want the fields that were defined in the section we're being given.
		$intersectedFields = array_intersect_key( $this->fields, $sectionFields );
		foreach ( $intersectedFields as $field => $fieldContent ) {
			$fieldContent['args']['id']        = $field;
			$fieldContent['args']['label_for'] = 'weeconnectpay_options_' . $field;
			add_settings_field(
				$field,
				$fieldContent['title'],
				array( $this->callback, $this->getFieldCallback( $field ) ),
				$page,
				$section,
				$fieldContent['args']
			);
		}
	}


	/**
	 * Gets the field type from a given field ID/slug
	 *
	 * @param string $fieldId The field ID/slug being requested
	 *
	 * @return  string The type of the field
	 */
	private function getFieldType( string $fieldId ): string {
		return $this->fields[ $fieldId ]['type'];
	}

	/**
	 * Gets the section callback from a given section ID/slug
	 *
	 * @param string $sectionSlug The section ID/slug being requested
	 *
	 * @return  string The name of the callback function for this section
	 */
	private function getSectionCallback( string $sectionSlug ): string {
		return WeeConnectPayUtilities::dashesToCamelCase( 'callback_' . $sectionSlug );
	}

	/**
	 * Gets the field callback from a given section ID/slug
	 * Since the field callbacks are linked with types, it needs to be defined too.
	 *
	 * @param string $fieldId The field ID/slug being requested
	 *
	 * @return  string  The name of the callback function for this field
	 */
	private function getFieldCallback( string $fieldId ): string {
		return WeeConnectPayUtilities::dashesToCamelCase( 'callback_field_' . $this->getFieldType( $fieldId ) );
	}


	/**
	 *  Define and generate everything related to the settings;
	 */
	public function run() {
		$this->registerSettings();
		$this->generatePages();
	}
}
