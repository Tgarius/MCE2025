<?php

//phpcs:disable WordPress
namespace WeeConnectPay;

class CloverCountry implements \JsonSerializable {

	/**
	 * @var string $name
	 */
	protected $name;

	/**
	 * @var string $alpha2
	 */
	protected $alpha2;

	/**
	 * @var string $alpha3
	 */
	protected $alpha3;

	/**
	 * CloverCountry constructor.
	 *
	 * @param string $name
	 * @param string $alpha2
	 * @param string $alpha3
	 */
	public function __construct( string $name, string $alpha2, string $alpha3 ) {
		$this->name   = $name;
		$this->alpha2 = $alpha2;
		$this->alpha3 = $alpha3;
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
