<?php
/* phpcs:disable WordPress
 * phpcs:disable Generic.Arrays.DisallowShortArraySyntax */

namespace WeeConnectPay;

use WeeConnectPay\Validators\DependencyValidator;

class Dependency {

	public const WORDPRESS = 'WordPress';
	public const WOOCOMMERCE = 'WooCommerce';
	public const PHP = 'PHP';
	public const SSL = 'SSL';
	public const PERMALINK = 'Permalink URL structure';
    public const PHP_INTL = 'PHP INTL Extension';
    public const WPDB_PREFIX = 'WordPress database prefix';

    /**
	 * @var string
	 */
	public $name;
	/**
	 * @var array
	 */
	public $minVer;
	/**
	 * @var array|null
	 */
	public $maxVer;
	/**
	 * @var DependencyValidator
	 */
	public $validator;

	/**
	 * Dependency constructor.
	 *
	 * @param string $name
	 * @param array $minVer
	 * @param array|null $maxVer
	 */
	public function __construct( string $name, array $minVer, array $maxVer = null ) {
		$this->name      = $name;
		$this->minVer    = $minVer;
		$this->maxVer    = $maxVer;
		$this->validator = new DependencyValidator( $this );
	}

	/**
	 * @return bool
	 * @throws Exceptions\InsufficientDependencyVersionException
	 * @throws Exceptions\MissingDependencyException
	 */
	public function validate(): bool {
		$version = $this->getActiveVersion();
		return $this->validator->validate( $version );
	}

	public function getActiveVersion(): array {
		switch ( $this->name ) {
			case self::SSL:
				try {
					if ( is_ssl() ) {
						return [0,0,0];
					} else {
						return [];
					}
				} catch ( \Throwable $e){
					return [];
				}
			case self::PHP:
				try {
					return [
						PHP_MAJOR_VERSION,
						PHP_MINOR_VERSION,
						PHP_RELEASE_VERSION
					];
				} catch ( \Throwable $e ) {
					return [];
				}
				break;
			case self::WORDPRESS:
				try {
					global $wp_version;

					return $this->stringVersionToArray( $wp_version );
				} catch ( \Throwable $e ) {
					return [];
				}
				break;
			case self::WOOCOMMERCE:
				if ( ! class_exists( 'WooCommerce' ) ) {
					//woocommerce is not activated or installed
					return [];
				}
				try {
					if ( defined( 'WC_VERSION' ) ) {
						return $this->stringVersionToArray( WC_VERSION );
					} else {
						return [];
					}
				} catch ( \Throwable $e ) {
					return [];
				}
				break;
			case self::PERMALINK:
				$permalink = get_option('permalink_structure');
				if (!$permalink){
					return [];
				} else {
					return [0,0,0];
				}
            case self::PHP_INTL:
                try {
                    if ( extension_loaded('intl') ) {
                        return [0,0,0];
                    } else {
                        return [];
                    }
                } catch ( \Throwable $e){
                    return [];
                }
            case self::WPDB_PREFIX:
                try {
                    global $wpdb;
                    if ( !empty($wpdb->prefix) ) {
                        return [0,0,0];
                    } else {
                        return [];
                    }
                } catch ( \Throwable $e ) {
                    return [];
                }
			default:
				return [];
		}
	}

	public function stringVersionToArray(
		string $version
	): array {
		return explode( '.', $version, 3 );
	}
}
