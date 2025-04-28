<?php
//phpcs:disable WordPress

namespace WeeConnectPay;

use WeeConnectPay\Integrations\IntegrationSettings;

class AccessToken implements \JsonSerializable {
	/**
	 * @var string
	 */
	protected $token;
	/**
	 * @var array
	 */
	protected $scopes;
	/**
	 * @var string
	 */
	protected $name;
	/**
	 * @var
	 */
	protected $expiresAt;

	/**
	 * @return string
	 */
	public function getToken() {
		return $this->token;
	}

	/**
	 * @return array
	 */
	public function getScopes() {
		return $this->scopes;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return mixed
	 */
	public function getExpiresAt() {
		return $this->expiresAt;
	}

	/**
	 * AccessToken constructor.
	 *
	 * @param string $token
	 * @param array $scopes
	 * @param string $name
	 * @param $expiresAt
	 */
	public function __construct( string $token, array $scopes, string $name, $expiresAt ) {
		$this->token     = $token;
		$this->scopes    = $scopes;
		$this->name      = $name;
		$this->expiresAt = $expiresAt;
	}


	/**
	 * @inheritDoc
	 * @return array a JSON representation of this class
	 */
	public function jsonSerialize(): array {
		$objectArray = [];
		foreach ($this as $key => $value) {
			$objectArray[$key] = $value;
		}

		return $objectArray;
	}


	/**
	 * Get an initialized instance of this class from the IntegrationSettings
	 *
	 * @param IntegrationSettings $integrationSettings
	 *
	 * @return AccessToken
	 * @throws Exceptions\SettingsInitializationException
	 */
	public static function getFromIntegrationSettings( IntegrationSettings $integrationSettings ): AccessToken {
		return $integrationSettings->getAccessToken();
	}
}
