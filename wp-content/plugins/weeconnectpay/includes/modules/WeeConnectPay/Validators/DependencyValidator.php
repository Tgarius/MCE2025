<?php

/* phpcs:disable WordPress
 * phpcs:disable Generic.Arrays.DisallowShortArraySyntax */
namespace WeeConnectPay\Validators;

use WeeConnectPay\Dependency;
use WeeConnectPay\Exceptions\InsufficientDependencyVersionException;
use WeeConnectPay\Exceptions\MissingDependencyException;
use WeeConnectPay\Exceptions\Codes\ExceptionCode;

class DependencyValidator {


	/**
	 * @var Dependency
	 */
	private $dependency;

	/**
	 * DependencyValidator constructor.
	 *
	 * @param Dependency $dependency
	 */
	public function __construct( Dependency $dependency ) {
		$this->dependency = $dependency;
	}


	/**
     * @updated 3.11.1
	 * @param array $versionToValidate
	 *
	 * @return bool
	 * @throws InsufficientDependencyVersionException
	 * @throws MissingDependencyException
	 */
	public function validate( array $versionToValidate ): bool {

        // GitPod config check -- Will not include it as a dependency, but we still shouldn't run the plugin if its active and fails this check
        if (getenv('GITPOD_WORKSPACE_URL')) {
            if (!defined('GITPOD_WCP_BACKEND_WORKSPACE_URL')) {
                throw new MissingDependencyException(
                    sprintf(
                        __( '%1$s constant does not seem to be set, which is required for this integration to work properly when using GitPod. Integration disabled.', 'weeconnectpay' ),
                        'GITPOD_WCP_BACKEND_WORKSPACE_URL'
                    ),
                    ExceptionCode::MISSING_DEPENDENCY,
                    null
                );
            }
        }

		if ( $versionToValidate === null || count( $versionToValidate ) <= 0 ) {

            $extraInstructions = '';
            if ($this->dependency->name === Dependency::PERMALINK) {
                $permalinkPageUrl = admin_url('options-permalink.php');
                $localizedPlainPermalinkSettingName = __('Plain', 'weeconnectpay');
                $localizedPermalinkPageHyperlinkText = __('the permalinks page', 'weeconnectpay');
                $localizedPermalinkPageHyperlink = "<a href='$permalinkPageUrl'>$localizedPermalinkPageHyperlinkText</a>";
                $extraInstructions .= sprintf(
                    __('You can resolve this issue by changing your WordPress permalinks settings to something other than "%1$s" on %2$s.', 'weeconnectpay'),
                    $localizedPlainPermalinkSettingName,
                    $localizedPermalinkPageHyperlink
                );
            }

            if ($this->dependency->name === Dependency::WPDB_PREFIX) {
                $dependencyMissingErrorMessage = __('It appears that your WordPress database prefix is either not set or is set to an empty string. This is against WordPress security standards, and as a result, WeeConnectPay has been disabled.', 'weeconnectpay');
                $extraInstructions .= __('To resolve this issue, please set a valid prefix in your WordPress database configuration. In a standard WordPress installation, this is done by defining the <code>$table_prefix</code> variable in the <code>wp-config.php</code> file. Having an empty or missing prefix can lead to security vulnerabilities, so it’s crucial to ensure this is configured correctly. <p><strong>Warning:</strong> Changing the <code>$table_prefix</code> value may trigger the WordPress installation wizard. This can give the appearance that your site’s data is missing, but your content is still safe in the database tables without the prefix. We strongly recommend consulting with your web developer before making this change to avoid any confusion or potential issues.</p>', 'weeconnectpay');

                throw new MissingDependencyException(
                    sprintf(
                        $dependencyMissingErrorMessage,
                        $extraInstructions
                    ),
                    ExceptionCode::MISSING_DEPENDENCY,
                    null,
                    $extraInstructions
                );
            }


			throw new MissingDependencyException(
				sprintf(
                    __( '%1$s does not seem to be active, which is required for this integration to work properly. Integration disabled.', 'weeconnectpay' ),
					$this->dependency->name,
                    $extraInstructions
				),
				ExceptionCode::MISSING_DEPENDENCY,
                null,
                $extraInstructions
			);
		}

		$version_to_validate_string = $this->versionArrayToString( $versionToValidate );
		$dependency_version_string  = $this->versionArrayToString( $this->dependency->minVer );

		if ( ! $this->versionIsSufficient( $version_to_validate_string, $dependency_version_string ) ) {
			throw new InsufficientDependencyVersionException(
				sprintf(
					__( '%1$s version %2$s or higher is required for this integration to work. Your current version is %3$s. Integration disabled.' , 'weeconnectpay'),
					$this->dependency->name,
					$dependency_version_string,
					$version_to_validate_string
				),
				ExceptionCode::DEPENDENCY_VERSION_INSUFFICIENT
			);
		} else {
			return true;
		}
	}

	public function versionIsSufficient( string $actualVer, string $dependencyVer ): bool {
		return version_compare( $actualVer, $dependencyVer, '>=' );
	}

	/**
	 * @param array $versionArray
	 *
	 * @return string
	 */
	public function versionArrayToString( array $versionArray ): string {
		return implode( '.', $versionArray );
	}
}
