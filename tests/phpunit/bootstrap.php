<?php
/**
 * Bootstrap the PHPUnit test suite(s).
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
$_bootstrap = '';

// Determine which version of WooCommerce we're testing against.
$wc_version    = getenv( 'WC_VERSION' ) ?: 'latest';
$target_suffix = preg_match( '/\d+(\.\d+){1,2}/', $wc_version, $match ) ? $match[0] : 'latest';
$target_dir    = dirname( __DIR__, 2 ) . '/vendor/woocommerce/woocommerce-src-' . $target_suffix;

// Attempt to install the given version of WooCommerce if it doesn't already exist.
if ( ! is_dir( $target_dir ) ) {
	try {
		exec(
			sprintf(
				'%1$s/bin/install-woocommerce.sh %2$s',
				dirname( __DIR__ ),
				escapeshellarg( $wc_version )
			),
			$output,
			$exit
		);

		if (0 !== $exit) {
			throw new \RuntimeException( sprintf( 'Received a non-zero exit code: %1$d', $exit ) );
		}
	} catch ( \Throwable $e ) {
		printf( "\033[0;31mUnable to install WooCommerce@%s\033[0;0m" . PHP_EOL, $wc_version );
		printf( 'Please run `sh tests/bin/install-woocommerce.sh %1$s` manually.' . PHP_EOL, $wc_version );

		exit( 1 );
	}
}

// Locate the WooCommerce test bootstrap file for this release.
$paths = [
	$target_dir . '/tests/legacy/bootstrap.php',
	$target_dir . '/tests/bootstrap.php',
];

foreach ( $paths as $path ) {
	if ( file_exists( $path ) ) {
		$_bootstrap = $path;
		break;
	}
}

if ( empty( $_bootstrap ) ) {
	echo "\033[0;31mUnable to find the the test bootstrap file for WooCommerce@{$wc_version}, aborting.\033[0;m\n";
	exit( 1 );
}

// Bootstrap the plugin on muplugins_loaded.
require_once $_tests_dir . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', function () {
	require_once dirname( __DIR__, 2 ) . '/woocommerce-coupon-restrictions.php';
} );

// Finally, start up the WP testing environment.
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once $_bootstrap;

echo esc_html( sprintf(
	/* Translators: %1$s is the WooCommerce release being loaded. */
	__( 'Using WooCommerce %1$s.', 'woocommerce-coupon-restrictions' ),
	WC_VERSION
) ) . PHP_EOL;
