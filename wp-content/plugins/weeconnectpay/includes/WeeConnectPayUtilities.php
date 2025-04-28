<?php
// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase


namespace WeeConnectPay\WordPress\Plugin\includes;

use WeeConnectPay\Exceptions\Codes\ExceptionCode;
use WeeConnectPay\Exceptions\WeeConnectPayException;
use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Class WeeConnectPayUtilities
 * holds static utility functions to parse and return modified results
 *
 * @package WeeConnectPay\WordPress\Plugin\includes
 */
class WeeConnectPayUtilities {


	public static function simplifyPostalCode($postalCode): string {
		$postalCode = str_replace(' ', '', $postalCode);
		// Remove all non-alphanumeric characters (except spaces) using regular expression
		$postalCode = preg_replace('/[^a-zA-Z0-9]/', '', $postalCode);

		return strtoupper($postalCode);
	}

	public static function formatPostalCode($postalCode): string {
		// Simplify the postal code first
		$postalCode = self::simplifyPostalCode($postalCode);

		// Check if it is a valid US or Canadian postal code
		if (preg_match('/^[A-Z]\d[A-Z]\d[A-Z]\d$/', $postalCode)) {
			// If it's a Canadian postal code, format it as 'ANA NAN'
			return substr($postalCode, 0, 3) . ' ' . substr($postalCode, 3, 3);
		} elseif (preg_match('/^\d{5}$/', $postalCode)) {
			// If it's a US postal code, format it as 'NNNNN'
			return $postalCode;
		} else {
			// If it's neither a valid US nor Canadian postal code, return the original simplified postal code
			return $postalCode;
		}
	}

	/**
	 *
	 * @param string $string snake_case string to be turned into camelCase
	 * @param bool $returnPascalCase Option to return CamelCase instead of pascalCase
	 *
	 * @return string|string[]
	 */
	public static function dashesToCamelCase( string $string, $returnPascalCase = false ) {
		$str = str_replace( '_', '', ucwords( $string, '_' ) );

		if ( ! $returnPascalCase ) {
			$str = lcfirst( $str );
		}

		return $str;
	}

	/**
	 * @param $orderId
	 *
	 * @return string
	 * @since 1.0.0
	 * @deprecated Doesn't seem to be used as 3.8.0, and we are implementing HPOS, which means we need to use
	 * the WooCommerce API to query post data in order to be sure we get it from the right table.
	 */
	public static function getWooCommerceOrderInstructions( $orderId ): string {
		$orderId = intval( $orderId );
		// Doesn't seem to be used as of 3.8.0, and we are implementing HPOS -- Should use wc_get_order instead to be HPOS compliant
		$post    = get_post( $orderId );

		if ( ! $post ) {
			return '';
		}

		return $post->post_excerpt;
	}


	/**
	 * Checks if wp_get_environment_type exists first. If not, returns production, otherwise returns the WP_ENVIRONMENT_TYPE constant. ( local/development, staging or production ( default ) only )
	 * @return string
	 */
	public static function get_wp_env(): string {

		$wp_env = null;

        if (getenv('GITPOD_WORKSPACE_URL')) {
            $wp_env = 'gitpod';
        } else if ( function_exists( 'wp_get_environment_type' ) ) {
			$wp_env = wp_get_environment_type();
		}

		switch ( $wp_env ) {
            case 'gitpod':
                return 'gitpod';

			case 'local':
			case 'development':
				return 'development';

			case 'staging':
				return 'staging';

			case 'production':
			default:
				return 'production';
		}
	}

	/**
	 * Get WeeConnectPay settings URL
	 */
	public static function getSettingsURL() {
		return admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=weeconnectpay';
	}

	/**
	 * Converts a version string ( major, minor, patch -- Everything else will be excluded ) into an unsafe array of numbers.
	 *
	 * @param string $version
	 *
	 * @return array
	 * @example "5.9-alpha-52003" ---> [5,9]
	 * @since 1.3.9
	 */
	public static function stringVersionToArray( string $version ): array {
		return explode( '.', $version, 3 );
	}

	/**
	 * Cleans the version segments to avoid issues when inserting versions like "5.9-alpha-52003"
	 *
	 * @param array $version_array
	 *
	 * @return array
	 * @since 1.3.9
	 */
	public static function cleanVersionArray( array $version_array ): array {
		$clean_array = [];
		foreach ( $version_array as $version_segment ) {
			$clean_version_segment = preg_replace( '/\D.*/', '', $version_segment );
			$clean_array[]         = $clean_version_segment;
		}

		return $clean_array;
	}

	/**
	 * Converts a version string ( major, minor, patch -- Everything else will be excluded and replaced with 0 if missing ) into a safe array of numbers.
	 *
	 * @param string $version
	 *
	 * @return array
	 * @example "5.9-alpha-52003" ---> [5,9,0]
	 * @since 1.3.9
	 */
	public static function stringVersionToCleanArray( string $version ): array {
		$dirty_version_array = self::stringVersionToArray( $version );
		$version_array       = self::cleanVersionArray( $dirty_version_array );

		// Fill in the blanks for any missing version parts before returning
		return [
			$version_array[0] ?? 0,
			$version_array[1] ?? 0,
			$version_array[2] ?? 0
		];
	}

	/**
	 * Takes the current user locale, or the current WordPress locale if missing
	 * and returns one of the supported SDK locale
	 *
	 * @return string
	 * @since 2.0.0
	 */
	public static function getLocale(): string {
		$wp_locale = get_user_locale();
		switch ($wp_locale) {
			case 'en_CA':
				return 'en-CA';
			case 'fr_FR': // Fallback to the only supported FR -- They also have postal codes
			case 'fr_CA':
				return 'fr-CA';
			default:
				return 'en';
		}
	}

	/**
	 * Validates Json
	 * @throws WeeConnectPayException
	 *
	 * @since 2.6.0
	 */
	public static function jsonValidate($string)
	{
		// decode the JSON data
		$result = json_decode($string);

		// switch and check possible JSON errors
		switch (json_last_error()) {
			case JSON_ERROR_NONE:
				$error = '';
				break;
			case JSON_ERROR_DEPTH:
				$error = 'The maximum stack depth has been exceeded.';
				break;
			case JSON_ERROR_STATE_MISMATCH:
				$error = 'Invalid or malformed JSON.';
				break;
			case JSON_ERROR_CTRL_CHAR:
				$error = 'Control character error, possibly incorrectly encoded.';
				break;
			case JSON_ERROR_SYNTAX:
				$error = 'Syntax error, malformed JSON.';
				break;
			// PHP >= 5.3.3
			case JSON_ERROR_UTF8:
				$error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
				break;
			// PHP >= 5.5.0
			case JSON_ERROR_RECURSION:
				$error = 'One or more recursive references in the value to be encoded.';
				break;
			// PHP >= 5.5.0
			case JSON_ERROR_INF_OR_NAN:
				$error = 'One or more NAN or INF values in the value to be encoded.';
				break;
			case JSON_ERROR_UNSUPPORTED_TYPE:
				$error = 'A value of a type that cannot be encoded was given.';
				break;
			default:
				$error = 'Unknown JSON error occurred.';
				break;
		}

		if ($error !== '') {
			throw new WeeConnectPayException( "JSON validation exception: $error", ExceptionCode::INVALID_JSON_EXCEPTION );
		}

		// everything is OK
		return $result;
	}


	/**
	 * Determines whether HPOS is enabled. Using a wrapper here to
	 * Use when you need to query the DB directly to ensure that you query the right tables.
	 *
	 * Using a wrapper here so that if any update needs to happen we can just update the one function.
	 *
	 * @return bool
	 * @since 3.8.0
	 */
	public static function isHighPerformanceOrderStorageEnabled(): bool {
		return OrderUtil::custom_orders_table_usage_is_enabled();
	}

    /**
     * @since 3.11.1
     *
     * @param int $cents
     * @return string
     */
    public static function centsToDollars(int $cents): string
    {
        return number_format($cents / 100, 2);
    }


}
