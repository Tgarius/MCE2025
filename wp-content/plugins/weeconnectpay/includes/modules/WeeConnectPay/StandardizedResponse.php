<?php

//phpcs:disable WordPress
//phpcs:disable Generic.Arrays.DisallowShortArraySyntax
namespace WeeConnectPay;

use WeeConnectPay\Exceptions\StandardizedResponseException;
use WeeConnectPay\Exceptions\WeeConnectPayException;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayUtilities;

class StandardizedResponse {

	const SUCCESS = 'success';
	const ERROR = 'error';

	public static function emitSuccess( object $data ): object {
		return self::emit( $data, StandardizedResponse::SUCCESS );
	}

	public static function emitError( object $data ): object {
		return self::emit( $data, 'error' );
	}

	public static function emit( object $data, string $type = StandardizedResponse::ERROR ): object {
		return (object) [
			'result' => $type,
			'data'   => self::emitData( $data, $type )
		];

	}

	public static function emitData( object $data, string $type ) {
		if ( $type !== StandardizedResponse::SUCCESS ) {
			$message = $data->message ?? __( 'A malformed response was received. Our team has been notified. We are sorry for the inconvenience.' );

			return [
				'error' => [
					'message' => $message
				]
			];
		} else {
			return $data;
		}
	}

	/**
	 * Used to validate incoming API responses
	 *
	 * @param string $response
	 *
	 * @return void
	 * @throws Exceptions\WeeConnectPayException
	 */
	public static function validate(string $response) {
		// Check for valid json
		try {
			$validatedJson = WeeConnectPayUtilities::jsonValidate( $response );
		} catch ( WeeConnectPayException $exception ) {
			Throw new StandardizedResponseException('An error occurred while processing an API response: '.$exception->getMessage(),null,$exception);
		}

		$responseAsArray = json_decode( $validatedJson, true );

		// Check if the result doesn't match one of the 2 types of results we have
		if ( $responseAsArray['result'] !== StandardizedResponse::SUCCESS
		     && $responseAsArray['result'] !== StandardizedResponse::ERROR ) {
			Throw new StandardizedResponseException('An error occurred while processing an API response. API call result value not found or not one of the allowed values.');
		}

		// Check that data exists
		if (!isset($responseAsArray['data'])){
			Throw new StandardizedResponseException('An error occurred while processing an API response. API call data value not found.');
		}
	}
}
