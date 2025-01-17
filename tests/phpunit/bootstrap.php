<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WooCommerce\Stripe
 */

require_once __DIR__ . '/../../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$wc_version = getenv( 'WC_VERSION' ) ?: 'latest';
$tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : dirname(__DIR__) . '/tmp/wordpress-tests-lib';

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
