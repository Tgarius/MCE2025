<?php

//phpcs:disable WordPress
namespace WeeConnectPay;

use WeeConnectPay\Integrations\IntegrationSettings;

class CloverApp implements \JsonSerializable {

	/**
	 * @var string
	 */
	protected $name;
	/**
	 * @var string
	 */
	protected $uuid;

	/**
	 * CloverApp constructor.
	 *
	 * @param string $name
	 * @param string $uuid
	 */
	public function __construct( string $name, string $uuid ) {
		$this->name = $name;
		$this->uuid = $uuid;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getUuid(): string {
		return $this->uuid;
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
	 * @return CloverApp
	 * @throws Exceptions\SettingsInitializationException
	 */
	public static function getFromIntegrationSettings( IntegrationSettings $integrationSettings ): CloverApp {
		return $integrationSettings->getCloverApp();
	}
}
