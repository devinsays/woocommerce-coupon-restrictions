<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WooCommerce\Stripe
 */

require_once __DIR__ . '/../../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$wc_version = getenv( 'WC_VERSION' ) ?: 'latest';
$tests_dir = getenv( 'WPtests_dir' ) ? getenv( 'WPtests_dir' ) : dirname(__DIR__) . '/tmp/wordpress-tests-lib';

// Attempt to install the given version of WooCommerce if it doesn't already exist.
if ( ! is_dir( $tests_dir ) ) {
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

if ( ! file_exists( $tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

if ( PHP_VERSION_ID >= 80000 && file_exists( $tests_dir . '/includes/phpunit7/MockObject' ) ) {
	// WP Core test library includes patches for PHPUnit 7 to make it compatible with PHP8.
	require_once $tests_dir . '/includes/phpunit7/MockObject/Builder/NamespaceMatch.php';
	require_once $tests_dir . '/includes/phpunit7/MockObject/Builder/ParametersMatch.php';
	require_once $tests_dir . '/includes/phpunit7/MockObject/InvocationMocker.php';
	require_once $tests_dir . '/includes/phpunit7/MockObject/MockMethod.php';
}

// Give access to tests_add_filter() function.
require_once $tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// Load the WooCommerce plugin.
	require_once ABSPATH . '/wp-content/plugins/woocommerce/woocommerce.php';

	// Load this plugin.
	$project_dir = dirname( dirname( __DIR__ ) );
	require_once $project_dir . '/woocommerce-coupon-restrictions.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $tests_dir . '/includes/bootstrap.php';

# Load WooCommerce Helpers (https://github.com/woocommerce/woocommerce/tree/master/tests/legacy/framework/helpers)
# To keep the plugin self-contained, copy any needed helper to the `helpers/` sub-folder.
require_once __DIR__ . '/helpers/class-wc-helper-coupon.php';
require_once __DIR__ . '/helpers/class-wc-helper-customer.php';
require_once __DIR__ . '/helpers/class-wc-helper-order.php';
require_once __DIR__ . '/helpers/class-wc-helper-product.php';
require_once __DIR__ . '/helpers/class-wc-helper-shipping.php';
