<?php

namespace WeeConnectPay\Integrations;

use WeeConnectPay\Dependencies\GuzzleHttp\Client;
use WeeConnectPay\Dependencies\GuzzleHttp\Exception\RequestException;

class RecaptchaVerifier {
	private $httpClient;
	private $baseUrl;
	private $secretKey;

	public function __construct() {
		// Configuration values for Guzzle HTTP client
		$guzzleConfig = [
			// Add any Guzzle configuration options here if needed.
		];

		$this->httpClient = new Client($guzzleConfig);;
		$this->baseUrl = 'https://www.google.com';
		$this->secretKey = (new IntegrationSettings)->getGoogleRecaptchaSecretKeyOrDefault();
	}

	public function verifyToken($token, $remoteip = null) {
		$url = $this->baseUrl . '/recaptcha/api/siteverify';

		try {
			$response = $this->httpClient->post($url, [
				'form_params' => [
					'secret' => $this->secretKey,
					'response' => $token,
					'remoteip' => $remoteip,
				],
			]);

			$responseData = json_decode($response->getBody(), true);
			LogService::debug('responseData from recaptcha: ' . json_encode($responseData));

			if ($responseData['success'] === true) {
				$challengeTimestamp = $responseData['challenge_ts'];
				$hostname = $responseData['hostname'];
				$score = $responseData['score'];
				$action = $responseData['action'];

				// Log the challenge timestamp and hostname if needed.


				return $responseData;
			} else {
				// Handle specific error codes if present.
				if (isset($responseData['error-codes'])) {
					LogService::error('Error codes in Google reCAPTCHA verify response: ' . json_encode($responseData['error-codes']));
					// Handle error codes.
				}

				return $responseData; // Verification failed.
			}
		} catch (RequestException $e) {
			// Handle Guzzle request exceptions.
			LogService::error('Exception with recaptcha backend call: ' . $e->getMessage());
			// Log or return an appropriate result.
			return ['exception' => $e->getMessage()];
		}
	}
}
