<?php

namespace WeeConnectPay\Integrations;

use Exception;
use WeeConnectPay\Dependency;
use WeeConnectPay\Exceptions\Codes\ExceptionCode;
use WeeConnectPay\Exceptions\InsufficientDependencyVersionException;
use WeeConnectPay\Exceptions\MissingDependencyException;
use WeeConnectPay\Exceptions\WeeConnectPayException;
use WeeConnectPay\StandardizedResponse;
use WeeConnectPay\WordPress\Plugin\includes\WeeConnectPayUtilities;

class DependencyChecker {

	/**
	 * External dependencies with their minimum version for the integration to run properly
	 *
	 * @since 1.3.9
	 * @updated 1.4.3
	 */
	public const DEPENDENCIES = [
		Dependency::PHP         => [ 7, 2, 0 ],
		Dependency::WORDPRESS   => [ 5, 4, 0 ],
		Dependency::WOOCOMMERCE => [ 3, 0, 4 ],
		Dependency::SSL         => [ 0, 0, 0 ], // Quick fix for dependencies without a version to respect type safety
		Dependency::PERMALINK   => [ 0, 0, 0 ], // Quick fix for dependencies without a version to respect type safety
        Dependency::PHP_INTL    => [ 0, 0, 0 ], // Quick fix for dependencies without a version to respect type safety
        Dependency::WPDB_PREFIX => [ 0, 0, 0 ], // Quick fix for dependencies without a version to respect type safety
	];

	/**
	 * Admin notice closure using the dependency exception message
	 *
	 * @param Exception $exception
	 *
	 * @return void
	 * @since 1.3.9
	 * @updated 3.2.1
	 */
	public function notify( Exception $exception ) {
		add_action( 'admin_notices', function () use ( $exception ) {
			self::adminNoticeCallback( $exception );
		} );
	}

	/**
	 *
	 * @param Exception $exception
	 *
	 * @return void
	 * @since 1.3.9
	 * @updated 3.2.1
	 */
	public static function adminNoticeCallback( Exception $exception ) {
        $allowed_html = array(
            'a' => array(
                'href' => array(),
            ),
            'br' => array(),
            'code' => array(),
            'strong' => array(),
            'p' => array()
        );

        if ($exception instanceof WeeConnectPayException) {
            $extraInstructions = $exception->getExtraInstructions();
        } else {
            $extraInstructions = '';
        }

        echo '<div class="notice notice-error">
			    <p><b>WeeConnectPay</b></p>
	            <p>' . esc_html($exception->getMessage()) . '</p>';

        if ($extraInstructions) {
            echo '<p>' . wp_kses($extraInstructions, $allowed_html) . '</p>';
        }

        echo '</div>';

	}

	/**
	 * @throws MissingDependencyException
	 * @throws InsufficientDependencyVersionException
	 */
	public function validateOnRun(): void {
		foreach ( self::DEPENDENCIES as $dependencyName => $minVerArray ) {
			$dependency = new Dependency( $dependencyName, $minVerArray );
			$dependency->validate();
		}
	}
}
