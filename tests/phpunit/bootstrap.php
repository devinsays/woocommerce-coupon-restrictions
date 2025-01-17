<?php
/**
 * PHPUnit bootstrap file.
 */

// Constants
define('PROJECT_DIR', dirname(dirname(__DIR__)));
define('TESTS_TMP_DIR', PROJECT_DIR . '/tests/tmp');
define('TESTS_DIR', getenv('WP_TESTS_DIR') ?: TESTS_TMP_DIR . '/wordpress-tests-lib');

// Utility Functions
function parse_wc_version($version) {
    return preg_match('/\d+(\.\d+){1,2}/', $version, $match) ? $match[0] : 'latest';
}

function install_woocommerce($version, $target_dir) {
    if (!is_dir($target_dir)) {
        try {
            exec(
                sprintf(
                    '%1$s/bin/install-woocommerce.sh %2$s',
                    dirname(__DIR__),
                    escapeshellarg($version)
                ),
                $output,
                $exit
            );

            if (0 !== $exit) {
                throw new \RuntimeException("Non-zero exit code: $exit");
            }
        } catch (\Throwable $e) {
            printf("\033[0;31mUnable to install WooCommerce@%s\033[0;0m" . PHP_EOL, $version);
            printf('Run `sh tests/bin/install-woocommerce.sh %1$s` manually.' . PHP_EOL, $version);
            exit(1);
        }
    }
}

// Main Logic
$wc_version    = getenv('WC_VERSION') ?: 'latest';
$target_suffix = parse_wc_version($wc_version);
$target_dir    = TESTS_TMP_DIR . '/wordpress/wp-content/plugins/woocommerce-' . $target_suffix;

install_woocommerce($wc_version, $target_dir);

if (!file_exists(TESTS_DIR)) {
    throw new \RuntimeException("WordPress tests directory not found: " . TESTS_DIR);
}

// Load PHPUnit Polyfills
require_once PROJECT_DIR . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// Load WordPress Functions
require_once TESTS_DIR . '/includes/functions.php';

// Load Plugins.
function _manually_load_plugin() {
    require_once ABSPATH . "/wp-content/plugins/woocommerce-" . parse_wc_version(getenv('WC_VERSION') ?: 'latest') . "/woocommerce.php";
    require_once PROJECT_DIR . '/woocommerce-coupon-restrictions.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Bootstrap Tests.
require_once TESTS_DIR . '/includes/bootstrap.php';

// Load Helpers
$helpers = [
    '/helpers/class-wc-helper-coupon.php',
    '/helpers/class-wc-helper-customer.php',
    '/helpers/class-wc-helper-order.php',
    '/helpers/class-wc-helper-product.php',
    '/helpers/class-wc-helper-shipping.php',
];

foreach ($helpers as $helper) {
    require_once __DIR__ . $helper;
}
