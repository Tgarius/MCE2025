<?php

//phpcs:disable WordPress
//phpcs:disable Generic.Arrays.DisallowShortArraySyntax
namespace WeeConnectPay\Exceptions;

use Throwable;
use function Automattic\Jetpack\load_3rd_party;

class WeeConnectPayException extends \Exception {

    protected $extraInstructions = '';

    // Setting $extraInstructions after the 3 defaults -- It's a bit more of a pain to define, but will be more stable until everybody moves to PHP 8+
	public function __construct( $message, $code = 0, Throwable $previous = null, $extraInstructions = '' ) {
		parent::__construct( $message, $code, $previous );
        $this->extraInstructions = $extraInstructions;
	}

    public function getExtraInstructions() {
        return $this->extraInstructions;
    }

	// custom string representation of object
	public function __toString() {
		return __CLASS__ . ":[{$this->getFile()}:{$this->getLine()}][{$this->code}]: {$this->message}\n";
	}

	public function toObject(): \stdClass {
		$obj                    = new \stdClass;
		$obj->message           = $this->getMessage();
		$obj->code              = $this->getCode();
        $obj->extraInstructions = $this->getExtraInstructions();
		$obj->type              = 'exception';

		return $obj;
	}

	public function toJson() {
		return json_encode(
			[
				'result' => 'error',
				'data'   => [
					'error' => [
						'message' => $this->message,
                        'extra_instructions' => $this->extraInstructions,
					]
				]
			]
		);
	}
}
