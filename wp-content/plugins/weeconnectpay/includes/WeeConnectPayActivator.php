<?php

/**
 * Fired during plugin activation
 *
 * @link       https://github.com/ParogDev
 * @since      1.0.0
 *
 * @package    WeeConnectPay
 * @subpackage WeeConnectPay/includes
 */

namespace WeeConnectPay\WordPress\Plugin\includes;

use WeeConnectPay\Exceptions\InsufficientDependencyVersionException;
use WeeConnectPay\Exceptions\MissingDependencyException;
use WeeConnectPay\Integrations\DependencyChecker;
use WeeConnectPay\Integrations\IntegrationSettings;
use WeeConnectPay\Integrations\LogService;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    WeeConnectPay
 * @subpackage WeeConnectPay/includes
 * @author     ParogDev <integration@cspaiement.com>
 */
class WeeConnectPayActivator {


	/**
	 * Activates the plugin.
	 *
	 * Used when the admin dashboard calls activation, whether it is from the installation UI or the plugin dashboard.
	 *
	 * @since    1.0.0
	 * @updated  3.7.0
	 */
	public static function activate() {
		// Validate Dependencies when initializing plugin
		$dependencyChecker = new DependencyChecker;
		try {
			$dependencyChecker->validateOnRun();
		} catch (InsufficientDependencyVersionException | MissingDependencyException $exception) {
			LogService::error(sprintf("WeeConnectPay initialization error: %s", json_encode($exception->getMessage())));
			$dependencyChecker->notify( $exception);
		}

		// Initializes plugin
		IntegrationSettings::maybeFirstTimeInit();
	}
}
