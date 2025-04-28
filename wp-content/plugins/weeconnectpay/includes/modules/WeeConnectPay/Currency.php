<?php

//phpcs:disable WordPress
namespace WeeConnectPay;

use WeeConnectPay\Exceptions\Codes\ExceptionCode;
use WeeConnectPay\Exceptions\SettingsInitializationException;
use WeeConnectPay\Exceptions\WeeConnectPayException;

class Currency implements \JsonSerializable {
	/**
	 * @var string $code
	 */
	protected $code;

	/**
	 * @var string $name
	 */
	protected $name;

	/**
	 * @var string $symbol
	 */
	protected $symbol;

	/**
	 * Currency constructor.
	 *
	 * @param string $code
	 * @param string $name
	 * @param string $symbol
	 */
	public function __construct( string $code, string $name, string $symbol ) {
		$this->code   = $code;
		$this->name   = $name;
		$this->symbol = $symbol;
	}

	/**
	 * @throws WeeConnectPayException
	 */
	public static function CreateMany( $acceptedCurrencies ): array {
		$arr = array();
		foreach ( $acceptedCurrencies as $acceptedCurrency ) {
			array_push(
				$arr,
				new Currency(
					$acceptedCurrency->code,
					$acceptedCurrency->name,
					$acceptedCurrency->symbol
				)
			);
		}

		if (count( $arr ) <= 0) {
			throw new SettingsInitializationException( 'We could not determine the accepted currency for this merchant subscription.', ExceptionCode::SETTINGS_INITIALIZATION_EXCEPTION );
		}

		return $arr;
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
}
