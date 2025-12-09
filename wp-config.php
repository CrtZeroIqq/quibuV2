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
define( 'DB_NAME', 'wp_quibu' );

/** Database username */
define( 'DB_USER', 'seidgc' );

/** Database password */
define( 'DB_PASSWORD', 'T&9mQv#X4sZ!pJ2uF@7kR1wL' );

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
define( 'AUTH_KEY',         '}x9Fb=e_HV:&G_X1nyN#mX!^toVEC/=fo?x_^JDl69AkhrE(&T<,&:^<rD`BVRr<' );
define( 'SECURE_AUTH_KEY',  ';saC(rpqX]ET3DSCD6T_T#.qiIyHZ?lB~9|s2pBzSSbLjT*Lnh4&Q1>gt7n%<`Ag' );
define( 'LOGGED_IN_KEY',    '`$2#@V412j6;c*j-1hnb5B1UyISts-- A`Afw}[[T!o>7K&JZx/(W;Pm!:){wD/E' );
define( 'NONCE_KEY',        'UT93ABC&b@EGh,@.TPoJ7n?LhXQMMh)Bp&EK$%5vCQScz3;ayR*M[2NtNK-{P%s~' );
define( 'AUTH_SALT',        ' >4:$L+u &UXO`@rGLe 93hWT68=[gQyn&htB$P2*%v6RZ;;}v*j$p1/fI(cMvV$' );
define( 'SECURE_AUTH_SALT', 'Sy<I2d,P0Ex)IqE^{oL+[24*.>`W^mC@8<eAdT5cd!->{Btq&s:W@<C${4gsg-O#' );
define( 'LOGGED_IN_SALT',   '$=`t5)5z`RNHX}ApkJN tg5$ynFvVkaGz%,U*6>_}),99DEQ!t},Wb;2Q>F$FV:w' );
define( 'NONCE_SALT',       'VME=ji0RT5cA`YQ#(b!YV[T?}&-Iq+w-cn:kh^sywrfL;M~{fHGWFClO 9n]@z(G' );

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
define('FS_METHOD', 'direct');

define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}


/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
