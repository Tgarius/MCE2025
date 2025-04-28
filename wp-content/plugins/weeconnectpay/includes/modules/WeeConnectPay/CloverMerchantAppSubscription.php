<?php

//phpcs:disable WordPress
namespace WeeConnectPay;

use WeeConnectPay\Integrations\IntegrationSettings;

class CloverMerchantAppSubscription implements \JsonSerializable {

	/**
	 * @var string $uuid
	 */
	protected $uuid;
	/**
	 * @var string $name
	 */
	protected $name;

	/**
	 * @var CloverCountry $country
	 */
	protected $country;

	/**
	 * @var Currency[] $acceptedCurrencies
	 */
	protected $acceptedCurrencies;

	/**
	 * CloverMerchantAppSubscription constructor.
	 *
	 * @param string $uuid
	 * @param string $name
	 * @param CloverCountry $country
	 * @param Currency[] $acceptedCurrencies
	 */
	public function __construct( string $uuid, string $name, CloverCountry $country, array $acceptedCurrencies ) {
		$this->uuid               = $uuid;
		$this->name               = $name;
		$this->country            = $country;
		$this->acceptedCurrencies = $acceptedCurrencies;
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
	 * Get an initialized instance of this class from the IntegrationSettings
	 *
	 * @param IntegrationSettings $integrationSettings
	 *
	 * @return CloverMerchantAppSubscription
	 * @throws Exceptions\SettingsInitializationException
	 */
	public static function getFromIntegrationSettings( IntegrationSettings $integrationSettings ): CloverMerchantAppSubscription {
		return $integrationSettings->getSubscription();
	}

}
