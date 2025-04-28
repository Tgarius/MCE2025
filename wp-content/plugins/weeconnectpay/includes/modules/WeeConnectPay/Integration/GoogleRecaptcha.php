<?php

namespace WeeConnectPay\Integrations;


class GoogleRecaptcha {

	public static function isEnabledAndReady(): bool {
		$integrationSettings = new IntegrationSettings();

		// Check that it is enabled
		$isEnabled = $integrationSettings->getGoogleRecaptchaOrDefault();
		if ( ! $isEnabled ) {
			return false;
		}

		// Check for site key
		$siteKey = $integrationSettings->getGoogleRecaptchaSiteKeyOrDefault();
		if ( ! $siteKey ) {
			LogService::error('Google Recaptcha Site Key is not set.');
			return false;
		}

		// Check for secret key
		$secretKey = $integrationSettings->getGoogleRecaptchaSecretKeyOrDefault();
		if ( ! $secretKey ) {
			LogService::error('Google Recaptcha Secret Key is not set.');
			return false;
		}

		// Check for minimum human score key
		$minimumHumanScore = $integrationSettings->getGoogleRecaptchaMinimumHumanScoreThresholdOrDefault();
		if ( ! $minimumHumanScore ) {
			LogService::error('Google Recaptcha Minimum Human Score Threshold is not set.');
			return false;
		}

		return true;
	}

	/**
	 * Get the Google reCAPTCHA SDK source URL
	 *
	 * @return string
	 */
	public static function getSdkSrc(): string {
		$integrationSetting = new IntegrationSettings();
		$recaptchaSiteKey = $integrationSetting->getGoogleRecaptchaSiteKeyOrDefault();

		return "https://www.google.com/recaptcha/api.js?render=$recaptchaSiteKey";
	}

	/**
	 * Check if a recaptcha token contains error JSON
	 * 
	 * @param string $recaptchaToken
	 * @since 3.6.0
	 * @return bool
	 */
	public static function tokenContainsErrorJson(string $recaptchaToken): bool {
		$cleanedRecaptchaToken = stripslashes($recaptchaToken);
		$exceptionCheck = json_decode( $cleanedRecaptchaToken );

		if ( isset( $exceptionCheck->exception ) ) {
			LogService::error("Google Recaptcha token contains error JSON: $recaptchaToken");
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Extract error message from a recaptcha token
	 * 
	 * @param string $recaptchaToken
	 * @since 3.6.0
	 * @return string
	 */
	public static function extractErrorMessageFromToken(string $recaptchaToken): string {
		$cleanedRecaptchaToken = stripslashes($recaptchaToken);
		$exceptionCheck = json_decode( $cleanedRecaptchaToken );

		return $exceptionCheck->exception ?? '';
	}
}
