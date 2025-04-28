<?php
//phpcs:disable WordPress

namespace WeeConnectPay;

use WeeConnectPay\Exceptions\Codes\ExceptionCode;
use WeeConnectPay\Exceptions\SettingsInitializationException;
use WeeConnectPay\Exceptions\WeeConnectPayException;

abstract class Settings implements \JsonSerializable {

//	protected const WCP_ENV = 'local';
	public const DB_KEY_SUFFIX_JWT = 'jwt';
	public const DB_KEY_SUFFIX_CLOVER_MERCHANT = 'clover_merchant';
	public const DB_KEY_SUFFIX_CLOVER_EMPLOYEE = 'clover_employee';
	public const DB_KEY_SUFFIX_CLOVER_APP = 'clover_app';
	public const DB_KEY_SUFFIX_CLOVER_MERCHANT_PUBLIC_KEY = 'clover_pakms';
	public const DB_KEY_SUFFIX_CLOVER_MERCHANT_APP_SUBSCRIPTION = 'app_subscription';

	/**
	 * @var AccessToken $accessToken
	 */
	protected $accessToken;

	/**
	 * @var string $publicAccessKey
	 */
	protected $publicAccessKey;
	/**
	 * @var CloverMerchant $cloverMerchant
	 */
	protected $cloverMerchant;
	/**
	 * @var CloverEmployee $cloverEmployee
	 */
	protected $cloverEmployee;
	/**
	 * @var CloverApp $cloverApp
	 */
	protected $cloverApp;
	/**
	 * @var CloverMerchantAppSubscription $subscription
	 */
	protected $subscription;


	public function __construct() {
		// Don't overload this.
	}

	/**
	 * Retrieves the Clover merchant app subscription from the integration instance, and tries to set it from the integration DB if it is unset.
	 *
	 * @return CloverMerchantAppSubscription
	 * @throws SettingsInitializationException
	 */
	public function getSubscription(): CloverMerchantAppSubscription {
		if ( ! $this->subscription ) {
			$this->subscription = get_option( $this::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_CLOVER_MERCHANT_APP_SUBSCRIPTION );
		}
		if ( $this->subscription instanceof CloverMerchantAppSubscription ) {
			return $this->subscription;
		} else {
			throw new SettingsInitializationException( "Could not retrieve a valid Clover merchant app subscription from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		}
	}

	/**
	 * @param CloverMerchantAppSubscription $subscription
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 */
	public function setSubscription( CloverMerchantAppSubscription $subscription ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_CLOVER_MERCHANT_APP_SUBSCRIPTION, $subscription, $this->subscription );
	}

	/**
	 * Retrieves the WeeConnectPay access token from the integration instance, and tries to set it from the integration DB if it is unset.
	 *
	 * @return AccessToken
	 * @throws SettingsInitializationException
	 */
	public function getAccessToken(): AccessToken {
		if ( ! $this->accessToken ) {
			$this->accessToken = get_option( $this::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_JWT );
		}

		if ( $this->accessToken instanceof AccessToken ) {
			return $this->accessToken;
		} else {
			throw new SettingsInitializationException( "Could not retrieve a valid WeeConnectPay access token from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		}

	}

	/**
	 * Verifies if we have an AccessToken to communicate with our API ( Unvalidated )
	 * @return bool
	 */
	public function accessTokenExists(): bool {
		if ( get_option( $this::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_JWT ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param AccessToken $accessToken
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 */
	public function setAccessToken( AccessToken $accessToken ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_JWT, $accessToken, $this->accessToken );
	}

	/**
	 * Retrieves the Clover merchant from the integration instance, and tries to set it from the integration DB if it is unset.
	 *
	 * @return CloverMerchant
	 * @throws SettingsInitializationException
	 */
	public function getCloverMerchant(): CloverMerchant {
		if ( ! $this->cloverMerchant ) {
			$this->cloverMerchant = get_option( $this::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_CLOVER_MERCHANT );
		}

		if ( $this->cloverMerchant instanceof CloverMerchant ) {
			return $this->cloverMerchant;

		} else {
			throw new SettingsInitializationException( "Could not retrieve a valid Clover merchant from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		}
	}

	/**
	 * @param CloverMerchant $cloverMerchant
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 */
	public function setCloverMerchant( CloverMerchant $cloverMerchant ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_CLOVER_MERCHANT, $cloverMerchant, $this->cloverMerchant );
	}

	/**
	 * Retrieves the Clover employee from the integration instance, and tries to set it from the integration DB if it is unset.
	 *
	 * @return CloverEmployee
	 * @throws SettingsInitializationException
	 */
	public function getCloverEmployee(): CloverEmployee {
		if ( ! $this->cloverEmployee ) {
			$this->cloverEmployee = get_option( $this::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_CLOVER_EMPLOYEE );
		}

		if ( $this->cloverEmployee instanceof CloverEmployee ) {
			return $this->cloverEmployee;
		} else {
			throw new SettingsInitializationException( "Could not retrieve a valid Clover employee from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		}
	}

	/**
	 * @param CloverEmployee $cloverEmployee
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 */
	public function setCloverEmployee( CloverEmployee $cloverEmployee ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_CLOVER_EMPLOYEE, $cloverEmployee, $this->cloverEmployee );
	}

	/**
	 * Retrieves the Clover app from the integration instance, and tries to set it from the integration DB if it is unset.
	 *
	 * @return CloverApp
	 * @throws SettingsInitializationException
	 */
	public function getCloverApp(): CloverApp {

		if ( ! $this->cloverApp ) {
			$this->cloverApp = get_option( $this::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_CLOVER_APP );
		}

		if ( $this->cloverApp instanceof CloverApp ) {
			return $this->cloverApp;
		} else {
			throw new SettingsInitializationException( "Could not retrieve a valid Clover app from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		}
	}

	/**
	 * @param CloverApp $cloverApp
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 */
	public function setCloverApp( CloverApp $cloverApp ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_CLOVER_APP, $cloverApp, $this->cloverApp );
	}

	/**
	 * Retrieves the Clover public access key from the integration instance, and tries to set it from the integration DB if it is unset.
	 *
	 * @return string
	 * @throws SettingsInitializationException
	 */
	public function getPublicAccessKey(): string {
		if ( ! $this->publicAccessKey ) {
			$this->publicAccessKey = get_option( $this::PLUGIN_OPTION_PREFIX . self::DB_KEY_SUFFIX_CLOVER_MERCHANT_PUBLIC_KEY );
		}

		if ( gettype($this->publicAccessKey) === 'string' ) {
			return $this->publicAccessKey;
		} else {
			throw new SettingsInitializationException( "Could not retrieve a valid Clover public access key from the database.", ExceptionCode::SETTINGS_RETRIEVAL_EXCEPTION );
		}

	}

	/**
	 * @param string $publicAccessKey
	 *
	 * @return void
	 * @throws WeeConnectPayException
	 */
	public function setPublicAccessKey( string $publicAccessKey ): void {
		$this->createOrUpdateSetting( self::DB_KEY_SUFFIX_CLOVER_MERCHANT_PUBLIC_KEY, $publicAccessKey, $this->publicAccessKey );
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
	 * Safely updates a setting in the database
	 *
	 * @param string $settingKey
	 * @param mixed $settingValue
	 * @param mixed $variableToUpdateOnSuccess Local value to update if the DB update is successful (passed by reference)
	 *
	 * @throws WeeConnectPayException
	 */
	protected function createOrUpdateSetting( string $settingKey, $settingValue, &$variableToUpdateOnSuccess = null, $default = false ): void {
		$optionString        = $this::PLUGIN_OPTION_PREFIX . $settingKey;
		$currentSettingValue = get_option( $optionString, $default );

		// Is the specific option in the DB the same as the one we're trying to update?
		// Uses equality and not identity operator as we work with objects
		if ( $currentSettingValue == $settingValue ) {
//			error_log( '$currentSettingValue == $settingValue -- option will not be updated' );
			// Setting is the same, are we working with a local variable that needs updating?
			if ( $variableToUpdateOnSuccess !== null ) {
				$variableToUpdateOnSuccess = $settingValue;
			}
		} else {
//			error_log( 'option ('.$optionString.') will  be updated with value: '.json_encode($settingValue) );

			$updated = update_option( $optionString, $settingValue );

			if ( $updated === true ) {
//				error_log( 'setting was updated!' );
				$variableToUpdateOnSuccess = $settingValue;
			} else {
				throw new WeeConnectPayException( "Failed to create or update the $settingKey setting in the database.", ExceptionCode::SETTINGS_UPDATE_EXCEPTION );
			}
		}
	}
}
