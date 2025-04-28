<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'KF' );

/** Database username */
define( 'DB_USER', 'knightfashion' );

/** Database password */
define( 'DB_PASSWORD', 'Knight@2025' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '/hy4=% 0Q},f0KYfJmZpqa(DjFIgS~ewYwz)co69o)MezXBk*Ay?>U;(z]0s(nSz' );
define( 'SECURE_AUTH_KEY',  ']FW~yovdw!DvVTAo%(wMSSiwM~_cvaG@7cgp-AYhgddj?(Gp#8zCAIFq+zxK<_5G' );
define( 'LOGGED_IN_KEY',    'sbRZ#V(+7$l+!<LsT!8]4}_ k5vcB+zO]Fla<HM<YE$hJBFx1ke<q^HGXd>xY[E(' );
define( 'NONCE_KEY',        'aFSVf{HYui-zT)%o=G:Q^x)7Jj-IYmw%<unl3zoADh{SdIe/N0xBc_$v2h2zeQ0=' );
define( 'AUTH_SALT',        'qT^T(AO3R<fU7}rI00)/6p&5!3A$CJlP4JIH_QB#htS?h4!{,Wqho_PRe)=>0{%]' );
define( 'SECURE_AUTH_SALT', ':xLmE9yJG?-Ym0]:F.O-J7kM[[2WQ}Q4tx3f}joDTTd8m/yx/]*HV`yr]l?`gqEq' );
define( 'LOGGED_IN_SALT',   '+%u>{F }V}~,n@_ML>.aJt{bTYl/spzxGYOvmEj2V~<CHyIgw-wkjbWBZiwH|9dn' );
define( 'NONCE_SALT',       '&8T-I*vnwLdg>yFz-;A!F]UmIfd%n#d.Jx$8KUCS5 VRn$z$ :O^k]rD<kiY-$)`' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
