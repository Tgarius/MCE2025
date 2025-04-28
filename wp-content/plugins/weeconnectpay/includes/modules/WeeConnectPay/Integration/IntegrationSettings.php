<?php
/** @noinspection PhpIncludeInspection */

/* phpcs:disable WordPress
 * phpcs:disable Generic.Arrays.DisallowShortArraySyntax */


namespace WeeConnectPay\Integrations;

use WeeConnectPay\Integrations\LogService;

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Api/ApiClient.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Api/ApiEndpoints.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Api/Requests/VerifyAuthenticationRequest.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Api/Requests/CreateCloverOrderChargeRequest.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Api/Requests/CreateCloverOrderCustomTenderChargeRequest.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Api/Requests/CreateCloverCustomerRequest.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Api/Requests/RefundCloverChargeRequest.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'StandardizedResponse.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Exceptions/Codes/ExceptionCode.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Exceptions/WeeConnectPayException.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Exceptions/CustomerCreationException.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Exceptions/UnsupportedOrderItemTypeException.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Exceptions/SettingsInitializationException.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Exceptions/MissingDependencyException.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Exceptions/MissingStateException.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Exceptions/InsufficientDependencyVersionException.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Exceptions/IntegrationPermissionsException.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'AccessToken.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'CloverApp.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'CloverCountry.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'CloverEmployee.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'CloverMerchant.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'CloverMerchantAppSubscription.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Currency.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Dependency.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Validators/DependencyValidator.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Settings.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Integration/Authentication.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Integration/DismissibleNewFeatureNotice.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Integration/RecaptchaVerifier.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Integration/GoogleRecaptcha.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'CloverReceiptsHelper.php';


use WeeConnectPay\{AccessToken,
	CloverApp,
	CloverCountry,
	CloverEmployee,
	CloverMerchant,
	CloverMerchantAppSubscription,
	Currency,
	Dependencies\GuzzleHttp\Exception\RequestException,
	Dependency,
	Exceptions\Codes\ExceptionCode,
	Exceptions\InsufficientDependencyVersionException,
	Exceptions\MissingDependencyException,
	Exceptions\SettingsInitializationException,
	Exceptions\WeeConnectPayException,
	Settings,
	StandardizedResponse,
	WordPress\Plugin\includes\WeeConnectPayAPI,
	WordPress\Plugin\includes\WeeConnectPayUtilities};

class IntegrationSettings extends Settings implements \JsonSerializable {

	public const PLUGIN_OPTION_PREFIX = 'weeconnectpay_';
	public const DB_KEY_WOOCOMMERCE_INTEGRATION = 'woocommerce_weeconnectpay_settings';
	public const DB_KEY_SUFFIX_INTEGRATION_UUID = 'integration_uuid';
	public const DB_KEY_SUFFIX_INTEGRATION_WPDB_PREFIX = 'wpdb_prefix';
	public const DB_KEY_SUFFIX_INTEGRATION_SITE_URL = 'site_url';
	public const DB_KEY_SUFFIX_PLATFORM_TYPE = 'platform_type';
	public const DB_KEY_SUFFIX_POST_TOKENIZATION_VERIFICATION = 'post_tokenization_verification';
	public const DB_KEY_SUFFIX_POST_TOKENIZATION_VERIFICATION_FEATURE_NOTICE = 'post_tokenization_verification_new_feature_notice';
	public const DB_KEY_SUFFIX_CHECKOUT_BLOCKS_FEATURE_NOTICE = 'checkout_blocks_new_feature_notice';

	public const DB_KEY_SUFFIX_GOOGLE_RECAPTCHA = 'google_recaptcha';
	public const DB_KEY_SUFFIX_GOOGLE_RECAPTCHA_FEATURE_NOTICE = 'google_recaptcha_new_feature_notice';
	public const DB_KEY_SUFFIX_GOOGLE_RECAPTCHA_SITE_KEY = 'google_recaptcha_site_key';
	public const DB_KEY_SUFFIX_GOOGLE_RECAPTCHA_SECRET_KEY = 'google_recaptcha_secret_key';
	public const DB_KEY_SUFFIX_GOOGLE_RECAPTCHA_MINIMUM_HUMAN_SCORE_THRESHOLD = 'google_recaptcha_minimum_human_score_threshold';
	public const DB_KEY_SUFFIX_HONEYPOT_FIELD = 'honeypot_field';
	public const DB_KEY_SUFFIX_DEBUG_MODE = 'debug_mode';
	public const INTEGRATION_NAME = Dependency::WORDPRESS;

	/**
	 * External dependencies with their minimum version for the integration to run properly
	 *
	 * @since 1.3.9
	 * @updated 1.4.3
	 */
	public const DEPENDENCIES = [
		Dependency::PHP         => [ 7, 2, 0 ],
		Dependency::WORDPRESS   => [ 5, 4, 0 ],
		Dependency::WOOCOMMERCE => [ 3, 0, 4 ]
	];


	/**
	 * @var bool $isWoocommerceGatewayEnabled
	 */
	public $isWoocommerceGatewayEnabled = false; // WP Specific, WooCommerce Specific

	/**
	 * @var string
	 */
	public $woocommerceGatewayTitle = '';


	/**
	 * @var string $wpdbPrefix
	 */
	public $wpdbPrefix; // WP specific

	/**
	 * @var string $integrationUuid
	 */
	public $integrationUuid;

	/**
	 * @var string $platformType
	 */
	public $platformType;

	/**
	 * @var string $integrationSiteUrl
	 */
	private $integrationSiteUrl;

	/**
	 * @var string $integrationSiteUrl
	 */
	private $integrationWpdbPrefix;

	/*
	 * @var bool $postTokenizationVerification
	 */
	private $postTokenizationVerification;

	/**
	 * @var bool $googleRecaptcha
	 */
	private $googleRecaptcha;

	/**
	 * @var string $googleRecaptchaSiteKey
	 */
	private $googleRecaptchaSiteKey;

	/**
	 * @var string $googleRecaptchaSecretKey
	 */
	private $googleRecaptchaSecretKey;

	/**
	 * @var float $googleRecaptchaMinimumScoreHumanThreshold
	 */
	private $googleRecaptchaMinimumHumanScoreThreshold;

	/**
	 * @var bool $honeypotField
	 */
	private $honeypotField;

	/**
	 * @var bool
	 */
	private bool $debugMode = false;

	/**
	 * @return string
	 */
	public function getPlatformType(): string {
		if ( ! $this->platformType ) {
			$this->platformType = get_option( self::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_PLATFORM_TYPE );
		}

		return $this->platformType;
	}

	/**
	 * @param string $platformType
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 */
	public function setPlatformType( string $platformType ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_PLATFORM_TYPE, $platformType, $this->platformType );
	}


	/**
	 * @return bool
	 */
	public function isWoocommerceGatewayEnabled(): bool {

		if ( ! $this->isWoocommerceGatewayEnabled ) {
			$wc_wcp_options = get_option( self::DB_KEY_WOOCOMMERCE_INTEGRATION );

			if ( $wc_wcp_options && isset( $wc_wcp_options->enabled ) ) {
				$this->isWoocommerceGatewayEnabled = $wc_wcp_options->enabled;
			}
		}

		return $this->isWoocommerceGatewayEnabled;
	}

	/**
	 * @param bool $isWoocommerceGatewayEnabled
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 */
	public function setIsWoocommerceGatewayEnabled( bool $isWoocommerceGatewayEnabled ): void {

		$optionString = self::DB_KEY_WOOCOMMERCE_INTEGRATION;
		$oldOption    = get_option( $optionString );

		// Has WooCommerce saved our gateway's options?
		if ( ! $oldOption ) {
			throw new WeeConnectPayException( __( 'Failed to update the WooCommerce WeeConnectPay gateway enabled status in the database. The gateway options could not be found.', 'weeconnectpay' ), ExceptionCode::SETTINGS_UPDATE_EXCEPTION );
		}

		// Is the specific option in the DB the same as the one we're trying to update?
		if ( $oldOption->enabled === $isWoocommerceGatewayEnabled ) {
			$oldOption->enabled = $isWoocommerceGatewayEnabled;
		} else {
			$updated = update_option( $optionString, $oldOption );

			// Only update the object if we successfully updated in the DB
			if ( $updated === true ) {
				$this->isWoocommerceGatewayEnabled = $isWoocommerceGatewayEnabled;
			} else {
				throw new WeeConnectPayException( __( 'Failed to update the WooCommerce integration status in the database.', 'weeconnectpay' ), ExceptionCode::SETTINGS_UPDATE_EXCEPTION );
			}
		}
	}


	/**
	 * @return string
	 */
	public function getWoocommerceGatewayTitle(): string {

		if ( ! $this->woocommerceGatewayTitle ) {
			$wc_wcp_options = get_option( self::DB_KEY_WOOCOMMERCE_INTEGRATION );

			if ( $wc_wcp_options && isset( $wc_wcp_options->title ) ) {
				$this->woocommerceGatewayTitle = $wc_wcp_options->title;
			}
		}

		return $this->woocommerceGatewayTitle;
	}

	/**
	 * @param string $woocommerceGatewayTitle
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 */
	public function setWoocommerceGatewayTitle( string $woocommerceGatewayTitle ): void {
		$optionString = self::DB_KEY_WOOCOMMERCE_INTEGRATION;
		$oldOption    = get_option( $optionString );

		// Has WooCommerce saved our gateway's options?
		if ( ! $oldOption ) {
			throw new WeeConnectPayException( __( 'Failed to update the WooCommerce integration title in the database. The gateway options could not be found.', 'weeconnectpay' ), ExceptionCode::SETTINGS_UPDATE_EXCEPTION );
		}

		// Is the specific option in the DB the same as the one we're trying to update?
		if ( $oldOption->title === $woocommerceGatewayTitle ) {
			$this->woocommerceGatewayTitle = $woocommerceGatewayTitle;
		} else {
			$updated = update_option( $optionString, $oldOption );

			// Only update the object if we successfully updated in the DB
			if ( $updated === true ) {
				$this->woocommerceGatewayTitle = $woocommerceGatewayTitle;
			} else {
				throw new WeeConnectPayException( __( 'Failed to update the WooCommerce integration title in the database.', 'weeconnectpay' ), ExceptionCode::SETTINGS_UPDATE_EXCEPTION );
			}
		}
	}


	/**
	 * Retrieves the WeeConnectPay integration ID from the integration instance, and tries to set it from the integration DB if it is unset.
	 *
	 * @return string
	 * @throws SettingsInitializationException
	 */
	public function getIntegrationUuid(): string {
		if ( ! $this->integrationUuid ) {
			$this->integrationUuid = get_option( self::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_INTEGRATION_UUID );
		}

		if ( gettype($this->integrationUuid) === 'string' ) {
			return $this->integrationUuid;
		} else {
			throw new SettingsInitializationException( "Could not retrieve a valid WeeConnectPay integration ID from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		}
	}

	/**
	 * @param string $integrationUuid
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 */
	private function setIntegrationUuid( string $integrationUuid ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_INTEGRATION_UUID, $integrationUuid, $this->integrationUuid );
	}

	/**
	 * Retrieves the WeeConnectPay Google reCAPTCHA from the integration instance, and tries to get it from the integration DB if it is unset.
	 *
	 * @since 3.6.0
	 * @return string
	 * @throws SettingsInitializationException
	 */
	public function getGoogleRecaptcha(): string {
		if ( ! $this->googleRecaptcha ) {
			$this->googleRecaptcha = get_option( self::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_GOOGLE_RECAPTCHA, 'OPTION_NOT_FOUND');
		}

		if ( $this->googleRecaptcha === 'OPTION_NOT_FOUND' ) {
			throw new SettingsInitializationException( "Could not retrieve a valid WeeConnectPay Google reCAPTCHA setting from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		} else {
			return $this->googleRecaptcha;
		}
	}

	/**
	 * @return false|string
	 * @since 3.6.0
	 */
	public function getGoogleRecaptchaOrDefault() {
		try {
			$googleRecaptchaOrDefault = $this->getGoogleRecaptcha();
		} catch ( SettingsInitializationException $e ) {
			$googleRecaptchaOrDefault = false;
		}

		return $googleRecaptchaOrDefault;
	}

	/**
	 * @param string $googleRecaptcha
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 * @since 3.6.0
	 */
	public function setGoogleRecaptcha( string $googleRecaptcha ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_GOOGLE_RECAPTCHA, $googleRecaptcha, $this->googleRecaptcha, 'OPTION_NOT_FOUND');
	}


	/**
	 * Retrieves the WeeConnectPay Google reCAPTCHA site key from the integration instance, and tries to get it from the integration DB if it is unset.
	 *
	 * @since 3.6.0
	 * @return string
	 * @throws SettingsInitializationException
	 */
	public function getGoogleRecaptchaSiteKey(): string {
		if ( ! $this->googleRecaptchaSiteKey ) {
			$this->googleRecaptchaSiteKey = get_option( self::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_GOOGLE_RECAPTCHA_SITE_KEY, 'OPTION_NOT_FOUND');
		}

		if ( $this->googleRecaptchaSiteKey === 'OPTION_NOT_FOUND' ) {
			throw new SettingsInitializationException( "Could not retrieve a valid WeeConnectPay Google reCAPTCHA site key setting from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		} else {
			return $this->googleRecaptchaSiteKey;
		}
	}

	/**
	 * @return string
	 * @since 3.6.0
	 */
	public function getGoogleRecaptchaSiteKeyOrDefault(): string {
		try {
			$googleRecaptchaSiteKeyOrDefault = $this->getGoogleRecaptchaSiteKey();
		} catch ( SettingsInitializationException $e ) {
			$googleRecaptchaSiteKeyOrDefault = '';
		}

		return $googleRecaptchaSiteKeyOrDefault;
	}


	/**
	 * @param string $googleRecaptchaSiteKey
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 * @since 3.6.0
	 */
	public function setGoogleRecaptchaSiteKey( string $googleRecaptchaSiteKey ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_GOOGLE_RECAPTCHA_SITE_KEY, $googleRecaptchaSiteKey, $this->googleRecaptchaSiteKey, 'OPTION_NOT_FOUND');
	}



	/**
	 * Retrieves the WeeConnectPay Google reCAPTCHA secret key from the integration instance, and tries to get it from the integration DB if it is unset.
	 *
	 * @since 3.6.0
	 * @return string
	 * @throws SettingsInitializationException
	 */
	public function getGoogleRecaptchaSecretKey(): string {
		if ( ! $this->googleRecaptchaSecretKey ) {
			$this->googleRecaptchaSecretKey = get_option( self::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_GOOGLE_RECAPTCHA_SECRET_KEY, 'OPTION_NOT_FOUND');
		}

		if ( $this->googleRecaptchaSecretKey === 'OPTION_NOT_FOUND' ) {
			throw new SettingsInitializationException( "Could not retrieve a valid WeeConnectPay Google reCAPTCHA private key setting from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		} else {
			return $this->googleRecaptchaSecretKey;
		}
	}

	/**
	 * @return string
	 * @since 3.6.0
	 */
	public function getGoogleRecaptchaSecretKeyOrDefault(): string {
		try {
			$googleRecaptchaSecretKeyOrDefault = $this->getGoogleRecaptchaSecretKey();
		} catch ( SettingsInitializationException $e ) {
			$googleRecaptchaSecretKeyOrDefault = '';
		}

		return $googleRecaptchaSecretKeyOrDefault;
	}

	/**
	 * @param string $googleRecaptchaSecretKey
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 * @since 3.6.0
	 */
	public function setGoogleRecaptchaSecretKey( string $googleRecaptchaSecretKey ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_GOOGLE_RECAPTCHA_SECRET_KEY, $googleRecaptchaSecretKey, $this->googleRecaptchaSecretKey, 'OPTION_NOT_FOUND');
	}


	/**
	 * Retrieves the WeeConnectPay Google reCAPTCHA Minimum Score Human Threshold (The score we need to be at or higher to be considered human) from the integration instance, and tries to get it from the integration DB if it is unset.
	 *
	 * @since 3.6.0
	 * @return string
	 * @throws SettingsInitializationException
	 */
	public function getGoogleRecaptchaMinimumHumanScoreThreshold(): string {
		if ( ! $this->googleRecaptchaMinimumHumanScoreThreshold ) {
			$this->googleRecaptchaMinimumHumanScoreThreshold = get_option( self::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_GOOGLE_RECAPTCHA_MINIMUM_HUMAN_SCORE_THRESHOLD, 'OPTION_NOT_FOUND');
		}

		if ( $this->googleRecaptchaMinimumHumanScoreThreshold === 'OPTION_NOT_FOUND' ) {
			throw new SettingsInitializationException( "Could not retrieve a valid WeeConnectPay Google reCAPTCHA Minimum Human Score Threshold setting from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		} else {
			return $this->googleRecaptchaMinimumHumanScoreThreshold;
		}
	}

	/**
	 * @return string
	 * @since 3.6.0
	 */
	public function getGoogleRecaptchaMinimumHumanScoreThresholdOrDefault(): string {
		try {
			$googleRecaptchaMinimumHumanScoreThresholdOrDefault = $this->getGoogleRecaptchaMinimumHumanScoreThreshold();
		} catch ( SettingsInitializationException $e ) {
			$googleRecaptchaMinimumHumanScoreThresholdOrDefault = '';
		}

		return $googleRecaptchaMinimumHumanScoreThresholdOrDefault;
	}

	/**
	 * @param string $googleRecaptchaMinimumHumanScoreThreshold
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 * @since 3.6.0
	 */
	public function setGoogleRecaptchaMinimumHumanScoreThreshold( string $googleRecaptchaMinimumHumanScoreThreshold ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_GOOGLE_RECAPTCHA_MINIMUM_HUMAN_SCORE_THRESHOLD, $googleRecaptchaMinimumHumanScoreThreshold, $this->googleRecaptchaMinimumHumanScoreThreshold, 'OPTION_NOT_FOUND');
	}


	/**
	 * Retrieves the WeeConnectPay honeypot field value (Enabled or disabled) from the integration instance, and tries to get it from the integration DB if it is unset.
	 *
	 * @since 3.6.0
	 * @return string
	 * @throws SettingsInitializationException
	 */
	public function getHoneypotField(): string {
		if ( ! $this->honeypotField ) {
			$this->honeypotField = get_option( self::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_HONEYPOT_FIELD, 'OPTION_NOT_FOUND');
		}

		if ( $this->honeypotField === 'OPTION_NOT_FOUND' ) {
			throw new SettingsInitializationException( "Could not retrieve a valid WeeConnectPay Google reCAPTCHA private key setting from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		} else {
			return $this->honeypotField;
		}
	}

	/**
	 * @return string
	 * @since 3.6.0
	 */
	public function getHoneypotFieldOrDefault(): string {
		try {
			$honeypotFieldOrDefault = $this->getHoneypotField();
		} catch ( SettingsInitializationException $e ) {
			$honeypotFieldOrDefault = '';
		}

		return $honeypotFieldOrDefault;
	}

	/**
	 * @param string $honeypotField
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 * @since 3.6.0
	 */
	public function setHoneypotField( string $honeypotField ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_HONEYPOT_FIELD, $honeypotField, $this->honeypotField, 'OPTION_NOT_FOUND');
	}

	/**
	 * Retrieves the WeeConnectPay integration ID from the integration instance, and tries to set it from the integration DB if it is unset.
	 *
	 * @since 3.3.0
	 * @return string
	 * @throws SettingsInitializationException
	 */
	public function getPostTokenizationVerification(): string {
		if ( ! $this->postTokenizationVerification ) {
			$this->postTokenizationVerification = get_option( self::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_POST_TOKENIZATION_VERIFICATION, 'OPTION_NOT_FOUND');
		}

		if ( $this->postTokenizationVerification === 'OPTION_NOT_FOUND' ) {
			throw new SettingsInitializationException( "Could not retrieve a valid WeeConnectPay post-tokenization verification setting from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		} else {
			return $this->postTokenizationVerification;
		}
	}

	public function getPostTokenizationVerificationOrDefault() {
		try {
			$postTokenizationVerificationOrDefault = $this->getPostTokenizationVerification();
		} catch ( SettingsInitializationException $e ) {
			$postTokenizationVerificationOrDefault = false;
		}

		return $postTokenizationVerificationOrDefault;
	}

	/**
	 * @param string $postTokenizationVerification
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 * @since 3.3.0
	 */
	public function setPostTokenizationVerification( string $postTokenizationVerification ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_POST_TOKENIZATION_VERIFICATION, $postTokenizationVerification, $this->postTokenizationVerification, 'OPTION_NOT_FOUND');
	}

	/**
	 * Retrieves the WeeConnectPay integration Site URL from the integration instance, and tries to set it from the integration DB if it is unset.
	 *
	 * @return string
	 * @since 3.1.0
	 * @throws SettingsInitializationException
	 */
	public function getIntegrationSiteUrl(): string {
		if ( ! $this->integrationSiteUrl ) {
			$this->integrationSiteUrl = get_option( self::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_INTEGRATION_SITE_URL );
		}

		if ( gettype($this->integrationSiteUrl) === 'string' ) {
			return $this->integrationSiteUrl;
		} else {
			throw new SettingsInitializationException( "Could not retrieve a valid WeeConnectPay integration site URL from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		}
	}

	/**
	 * @param string $integrationSiteUrl
	 *
	 * @return void
	 * @since 3.1.0
	 * @throws WeeConnectPayException
	 */
	private function setIntegrationSiteUrl( string $integrationSiteUrl ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_INTEGRATION_SITE_URL, $integrationSiteUrl, $this->integrationSiteUrl );
	}

	/**
	 * Retrieves the WeeConnectPay integration WPDB Prefix from the integration instance, and tries to set it from the integration DB if it is unset.
	 *
	 * @return string
	 * @since 3.1.0
	 * @throws SettingsInitializationException
	 */
	public function getIntegrationWpdbPrefix(): string {
		if ( ! $this->integrationWpdbPrefix ) {
			$this->integrationWpdbPrefix = get_option( self::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_INTEGRATION_WPDB_PREFIX );
		}

		if ( gettype($this->integrationWpdbPrefix) === 'string' ) {
			return $this->integrationWpdbPrefix;
		} else {
			throw new SettingsInitializationException( "Could not retrieve a valid WeeConnectPay integration site URL from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		}
	}

	/**
	 * @param string $integrationWpdbPrefix
	 *
	 * @return void
	 * @since 3.1.0
	 * @throws WeeConnectPayException
	 */
	private function setIntegrationWpdbPrefix( string $integrationWpdbPrefix ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_INTEGRATION_WPDB_PREFIX, $integrationWpdbPrefix, $this->integrationWpdbPrefix );
	}


	/**
	 * @deprecated
	 * @return object StandardizedResponse::emitError | StandardizedResponse::emitSuccess
	 */
	public function validateDependencies(): object {
		try {
			foreach ( self::DEPENDENCIES as $dependencyName => $minVerArray ) {
				$dependency = new Dependency( $dependencyName, $minVerArray );

				if ( $dependency->validate() !== true ) {
					throw new WeeConnectPayException(
						sprintf(
							__( 'Something went wrong while validating the current %1$s version. %1$s is required for this integration to work properly. Integration disabled. ', 'weeconnectpay' ),
							$dependencyName
						),
						ExceptionCode::UNHANDLED_EXCEPTION
					);
				}
			}

			return StandardizedResponse::emitSuccess( (object) [ 'message' => __( 'Dependencies are ok!', 'weeconnectpay' ) ] );
		} catch ( MissingDependencyException | InsufficientDependencyVersionException | WeeConnectPayException $exception ) {
			return StandardizedResponse::emitError( $exception->toObject() );
		} catch ( \Throwable $exception ) {

			return StandardizedResponse::emitError(
				( new WeeConnectPayException(
					__( 'Something went wrong while validating one of the requirements for this integration to work properly. Integration disabled.', 'weeconnectpay' ),
					ExceptionCode::UNHANDLED_EXCEPTION
				) )->toObject()
			);
		}

	}

	/**
	 * Attempts to retrieve every setting needed for payment processing. Returns true if successful.
	 *
	 * @return bool
	 *
	 * @since 1.3.9
	 * @updated 3.1.2
	 */
	public function arePaymentProcessingSettingsReady(): bool {
		try {
			// app sub
//			$this->getSubscription();
			// clover app
			$this->getCloverApp();
			// clover emp
			$this->getCloverEmployee();
			// clover merchant
			$this->getCloverMerchant();
			// clover pakms
			$this->getPublicAccessKey();
			// integration uuid
			$this->getIntegrationUuid();
			// jwt
			$this->getAccessToken();
			// platform type
			$this->getPlatformType();
			// integration wpdb prefix
			$this->getIntegrationWpdbPrefix();
			// integration site_url
			$this->getIntegrationSiteUrl();

			return true;
		} catch ( \Throwable $exception ) {
			return false;
		}
	}

//	// Should either return the login page settings OR the full settings we need to display.
//	public function prepareAppSettingsForApi() {
//		try {
//
//		}
//	}


	/**
	 *  To be used during plugin activation.
	 *
	 * @updated 3.3.0
	 */
	public static function maybeFirstTimeInit() {
		try {
			// Since this will run when updating, we to force the new setting to register in DB here with the default value of false
			$integrationSettings = new IntegrationSettings();
			$integrationSettings->getPostTokenizationVerification();
		} catch (SettingsInitializationException $e) {
			try {
				$integrationSettings->setPostTokenizationVerification('0');
			} catch (WeeConnectPayException $e) {
				LogService::error("Error during maybeFirstTimeInit when attempting to set post-tokenization validation value: " . $e->getMessage());
			}
		}

		if ( ! ( new self )->arePaymentProcessingSettingsReady() ) {
			IntegrationSettings::reinitialize();
		}
	}

	/**
	 * @param object $object
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 */
	public function saveAfterAuth( object $object ): void {
			$this->setIntegrationUuid( $object->integration->uuid );
			$this->setPlatformType( $object->integration->integrable_type );
			//$this->setWpdbPrefix( $object->integration->hosted_platform->hostable->db_prefix );
			$this->setCloverMerchant(
				new CloverMerchant(
					$object->integration->clover_merchant->name,
					$object->integration->clover_merchant->uuid
				)
			);
			$this->setCloverEmployee(
				new CloverEmployee(
					$object->integration->clover_employee->name,
					$object->integration->clover_employee->uuid
				)
			);
			$this->setCloverApp(
				new CloverApp(
					$object->integration->clover_app->name,
					$object->integration->clover_app->uuid
				)
			);
			$this->setAccessToken(
				new AccessToken(
					$object->integration->token->accessToken,
					$object->integration->token->token->scopes,
					$object->integration->token->token->name,
					$object->integration->token->token->expires_at
				)
			);
			$this->setPublicAccessKey( $object->integration->clover_access_token->public_key->key );

		if ( $object->integration->clover_access_token->app_subscription ) {
			if ( $object->integration->clover_access_token->app_subscription->country ) {

				$country = new CloverCountry(
					$object->integration->clover_access_token->app_subscription->country->name,
					$object->integration->clover_access_token->app_subscription->country->alpha2_code,
					$object->integration->clover_access_token->app_subscription->country->alpha3_code
				);

				if ( $object->integration->clover_access_token->app_subscription->country->currencies ) {

					$acceptedCurrencies = Currency::CreateMany(
						$object->integration->clover_access_token->app_subscription->country->currencies
					);
					$this->setSubscription(
						new CloverMerchantAppSubscription(
							$object->integration->clover_access_token->app_subscription->uuid,
							$object->integration->clover_access_token->app_subscription->name,
							$country,
							$acceptedCurrencies
						)
					);

				}
			}
		}
	}

	/**
	 * Logs out completely by cleaning up our data in the database.
	 *
	 * @param bool $rememberSettings Determines whether to wipe QOL settings / settings unrelated to the authentication.
	 *
	 * @return void
	 * @since 3.1.0
	 * @updated 3.6.0
	 */
	public static function forceLogout(bool $rememberSettings = false) {
		delete_option( IntegrationSettings::PLUGIN_OPTION_PREFIX . Settings::DB_KEY_SUFFIX_CLOVER_APP);
		delete_option( IntegrationSettings::PLUGIN_OPTION_PREFIX . Settings::DB_KEY_SUFFIX_CLOVER_EMPLOYEE);
		delete_option( IntegrationSettings::PLUGIN_OPTION_PREFIX . Settings::DB_KEY_SUFFIX_CLOVER_MERCHANT);
		delete_option( IntegrationSettings::PLUGIN_OPTION_PREFIX . Settings::DB_KEY_SUFFIX_CLOVER_MERCHANT_APP_SUBSCRIPTION);
		delete_option( IntegrationSettings::PLUGIN_OPTION_PREFIX . Settings::DB_KEY_SUFFIX_CLOVER_MERCHANT_PUBLIC_KEY);
		delete_option( IntegrationSettings::PLUGIN_OPTION_PREFIX . Settings::DB_KEY_SUFFIX_JWT);
		delete_option( IntegrationSettings::PLUGIN_OPTION_PREFIX . IntegrationSettings::DB_KEY_SUFFIX_INTEGRATION_UUID);
		delete_option( IntegrationSettings::PLUGIN_OPTION_PREFIX . IntegrationSettings::DB_KEY_SUFFIX_PLATFORM_TYPE);
		delete_option( IntegrationSettings::PLUGIN_OPTION_PREFIX . IntegrationSettings::DB_KEY_SUFFIX_INTEGRATION_SITE_URL);
		delete_option( IntegrationSettings::PLUGIN_OPTION_PREFIX . IntegrationSettings::DB_KEY_SUFFIX_INTEGRATION_WPDB_PREFIX);

		// WooCommerce Options
		delete_option(IntegrationSettings::DB_KEY_WOOCOMMERCE_INTEGRATION);

		// Should technically not be used anymore... but just in case
		delete_option(IntegrationSettings::PLUGIN_OPTION_PREFIX . 'integration');

		if (!$rememberSettings){
			// 3.5.0 Postal Code+ Verification
			delete_option(IntegrationSettings::PLUGIN_OPTION_PREFIX . IntegrationSettings::DB_KEY_SUFFIX_POST_TOKENIZATION_VERIFICATION);
			// Should not be used anymore after 3.7.0 -- Replaced notice
			delete_option(IntegrationSettings::PLUGIN_OPTION_PREFIX . IntegrationSettings::DB_KEY_SUFFIX_POST_TOKENIZATION_VERIFICATION_FEATURE_NOTICE);

			// 3.6.0 Google Recaptcha
			delete_option(IntegrationSettings::PLUGIN_OPTION_PREFIX . IntegrationSettings::DB_KEY_SUFFIX_GOOGLE_RECAPTCHA);
			delete_option(IntegrationSettings::PLUGIN_OPTION_PREFIX . IntegrationSettings::DB_KEY_SUFFIX_GOOGLE_RECAPTCHA_FEATURE_NOTICE);
			delete_option(IntegrationSettings::PLUGIN_OPTION_PREFIX . IntegrationSettings::DB_KEY_SUFFIX_GOOGLE_RECAPTCHA_SITE_KEY);
			delete_option(IntegrationSettings::PLUGIN_OPTION_PREFIX . IntegrationSettings::DB_KEY_SUFFIX_GOOGLE_RECAPTCHA_SECRET_KEY);
			delete_option(IntegrationSettings::PLUGIN_OPTION_PREFIX . IntegrationSettings::DB_KEY_SUFFIX_GOOGLE_RECAPTCHA_MINIMUM_HUMAN_SCORE_THRESHOLD);

			// 3.6.0 Honeypot Field
			delete_option(IntegrationSettings::PLUGIN_OPTION_PREFIX . IntegrationSettings::DB_KEY_SUFFIX_HONEYPOT_FIELD);

			// 3.7.0 WooCommerce Blocks Checkout Support
			delete_option(IntegrationSettings::PLUGIN_OPTION_PREFIX . IntegrationSettings::DB_KEY_SUFFIX_CHECKOUT_BLOCKS_FEATURE_NOTICE);
		}
	}


	/**
	 * @inheritDoc
	 * @return array a JSON representation of this class
	 */
	public function jsonSerialize(): array {
		$objectArray = [];
		foreach ( $this as $key => $value ) {
			$objectArray[ $key ] = $value;
		}

		return $objectArray;
	}


	/**
	 * Get the redirect URL for the authentication redirect using the integration ID
	 *
	 * @since 1.4.0
	 * @return string
	 * @throws SettingsInitializationException
	 */
	public static function redirectUrl(): string {
		return WeeConnectPayAPI::getApiDomain() . '/login/clover?intent=authorize-redirect&integration_id=' . ( new IntegrationSettings )->getIntegrationUuid();
	}


	/**
	 * Checks if the authentication settings are valid
	 *
	 * @since 1.4.0
	 * @updated 3.1.0
	 * @return bool
	 * @throws RequestException
	 */
	public function isAuthValid(): bool {
		try {
			if($this->areIntegrationSettingsImported()){
				return false;
			};
			// Will throw an exception if missing -- No ID = Not logged in properly
			$integration_id = $this->getIntegrationUuid();

			if ( $this->accessTokenExists() ) {
				$authValid = Authentication::isValid( $integration_id );
				if ( $authValid ) {
					return true;
				} else {
					LogService::error('WeeConnectPay: Invalid authentication.');
					IntegrationSettings::reinitialize();
					return false;
				}
			} else {
				return false;
			}

		} catch ( WeeConnectPayException $exception ) {
			LogService::error('WeeConnectPay authentication is invalid: ' . $exception->getMessage());
			return false;
		}
	}

	/**
	 * Helps detect if the integration ID was naturally generated, or if it was imported and should request a new one
	 *
	 * @throws WeeConnectPayException
	 */
	public function areIntegrationSettingsImported(): bool {
		global $wpdb;

		// WPDB Prefix
		try {
			$integrationWpdbPrefix = $this->getIntegrationWpdbPrefix();
		} catch (SettingsInitializationException $exception) {
			LogService::error('WeeConnectPay: Exception getting integrationWpdbPrefix setting. Attempting to set it.');
			$this->setIntegrationWpdbPrefix(sanitize_text_field($wpdb->prefix));
			$integrationWpdbPrefix = $this->getIntegrationWpdbPrefix();
		}

		try {
			$integrationSiteUrl = $this->getIntegrationSiteUrl();
		} catch (SettingsInitializationException $exception) {
			LogService::error('WeeConnectPay: Exception getting integrationSiteUrl setting. Attempting to set it.');
			$this->setIntegrationSiteUrl(sanitize_url(site_url()));
			$integrationSiteUrl = $this->getIntegrationSiteUrl();
		}

		// If these 2 are not exactly the same,
		// it means the settings we have in the DB are not
		// from the same environment and need to be refreshed.
		if ( $integrationSiteUrl !== site_url() ) {
			LogService::warning('WeeConnectPay: Site URL does not match DB settings. Was this imported instead of installed properly?');
			IntegrationSettings::reinitialize();
			return true;
		}

		if ( $integrationWpdbPrefix !== $wpdb->prefix ) {
			LogService::warning('WeeConnectPay: WPDB Prefix does not match DB settings. Was this imported instead of installed properly?');
			IntegrationSettings::reinitialize();
			return true;
		}
		return false;
	}

	/**
	 * Uses force logout (Cleans the DB entries we use) and reapplies them like a fresh installation would
	 *
	 * @return IntegrationSettings
	 *
	 * @since 3.1.2
	 * @updated 3.5.0
	 */
	public static function reinitialize(): IntegrationSettings {
		LogService::warning("WeeConnectPay: Attempting to reinitialize settings. Please login with Clover to finish setting up.");
		self::forceLogout(true);
		return self::initialize();
	}

	/**
	 * Initializes the DB values needed before authentication
	 *
	 * @return IntegrationSettings
	 *
	 * @since 3.1.2
	 * @updated 3.2.2
	 */
	public static function initialize(): IntegrationSettings {
		$settings = new IntegrationSettings();

		// Integration ID
		try {
			$integration_id = Authentication::fetchIntegrationId();
		} catch (\Exception $e) {
			LogService::error('WeeConnectPay: Failed to fetch an integration ID during settings initialization. Exception message: ' . $e->getMessage());
			die("WeeConnectPay initialization error: ".$e->getMessage());
		}
		try {
			$settings->setIntegrationUuid($integration_id);
		} catch (WeeConnectPayException $e) {
			LogService::error('WeeConnectPay: Exception during settings initialization. Exception message: ' . $e->getMessage());
			die("WeeConnectPay initialization error: ".$e->getMessage());
		}
		try {
			$settings->integrationUuid = $settings->getIntegrationUuid();
		} catch (SettingsInitializationException $e) {
			LogService::error('WeeConnectPay: Exception during settings initialization. Exception message: ' . $e->getMessage());
			die("WeeConnectPay initialization error: ".$e->getMessage());
		}

		// WPDB Prefix
		global $wpdb;
		try {
			$settings->setIntegrationWpdbPrefix(sanitize_text_field($wpdb->prefix));
		} catch (WeeConnectPayException $e) {
			LogService::error('WeeConnectPay: Exception during settings initialization. Exception message: ' . $e->getMessage());
			die("WeeConnectPay initialization error: ".$e->getMessage());
		}
		try {
			$settings->integrationWpdbPrefix = $settings->getIntegrationWpdbPrefix();
		} catch (SettingsInitializationException $e) {
			LogService::error('WeeConnectPay: Exception during settings initialization. Exception message: ' . $e->getMessage());
			die("WeeConnectPay initialization error: ".$e->getMessage());
		}

		// Site URL
		try {
			$settings->setIntegrationSiteUrl(sanitize_url(site_url()));
		} catch (WeeConnectPayException $e) {
			LogService::error('WeeConnectPay: Exception during settings initialization. Exception message: ' . $e->getMessage());
			die("WeeConnectPay initialization error: ".$e->getMessage());
		}
		try {
			$settings->integrationSiteUrl = $settings->getIntegrationSiteUrl();
		} catch (SettingsInitializationException $e) {
			LogService::error('WeeConnectPay: Exception during settings initialization. Exception message: ' . $e->getMessage());
			die("WeeConnectPay initialization error: ".$e->getMessage());
		}

		return $settings;
	}

	/**
	 * Get the debug mode setting
	 * 
	 * @return bool
	 * @throws SettingsInitializationException
	 */
	public function getDebugMode(): bool {
		if (!$this->debugMode) {
			$this->debugMode = get_option(self::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_DEBUG_MODE, false);
		}

		return $this->debugMode === '1' || $this->debugMode === true;
	}

	/**
	 * Get the debug mode setting with a default value if not set
	 * 
	 * @return bool
	 */
	public function getDebugModeOrDefault(): bool {
		try {
			return $this->getDebugMode();
		} catch (SettingsInitializationException $e) {
			return false;
		}
	}

	/**
	 * Set the debug mode setting
	 * 
	 * @param string|bool $debugMode
	 * @return void
	 * @throws WeeConnectPayException
	 */
	public function setDebugMode($debugMode): void {
		$debugModeValue = is_bool($debugMode) ? ($debugMode ? '1' : '0') : $debugMode;
		$this->createOrUpdateSetting(self::DB_KEY_SUFFIX_DEBUG_MODE, $debugModeValue, $this->debugMode, '0');
	}

}
