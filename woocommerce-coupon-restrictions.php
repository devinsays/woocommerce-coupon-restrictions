<?php
/**
 * Plugin Name: WooCommerce Coupon Restrictions
 * Plugin URI: http://woocommerce.com/products/woocommerce-coupon-restrictions/
 * Description: Adds additional coupon restrictions. Coupons can be restricted to new customers, existing customers, or specific locations.
 * Version: 2.0.0
 * Author: WooCommerce
 * Author URI: http://woocommerce.com/
 * Developer: Devin Price
 * Developer URI: https://devpress.com
 * Text Domain: woocommerce-coupon-restrictions
 * Domain Path: /languages
 *
 * Woo: 3200406:6d7b7aa4f9565b8f7cbd2fe10d4f119a
 * WC requires at least: 4.8.1
 * WC tested up to: 7.1.0
 *
 * Copyright: © 2015-2022 DevPress.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'WC_Coupon_Restrictions' ) ) {
	class WC_Coupon_Restrictions {

		/** @var WC_Coupon_Restrictions */
		public static $instance;

		/** @var string */
		public $version = '2.0.0';

		/** @var string */
		public $required_woo = '4.8.1';

		/** @var string */
		public $plugin_path = null;

		/**
		 * Main WC_Coupon_Restrictions Instance.
		 *
		 * Ensures only one instance of the WC_Coupon_Restrictions is loaded or can be loaded.
		 *
		 * @return WC_Coupon_Restrictions - Main instance.
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Loads the plugin.
		 *
		 * @access public
		 * @since  1.3.0
		 */
		public function __construct() {
			$this->plugin_path = untrailingslashit( plugin_dir_path( __FILE__ ) );

			// Checks WooCommerce version.
			add_action( 'plugins_loaded', array( $this, 'load_plugin' ) );

			// Init hooks runs after plugins_loaded and woocommerce_loaded hooks.
			add_action( 'init', array( $this, 'init_plugin' ) );
		}

		/**
		 * Plugin base file.
		 * Used for activation hook and plugin links.
		 *
		 * @since  1.5.0
		 */
		public static function plugin_base() {
			return plugin_basename( __FILE__ );
		}

		/**
		 * Plugin asset path.
		 *
		 * @since  1.8.5
		 */
		public static function plugin_asset_path() {
			return plugin_dir_url( __FILE__ );
		}

		/**
		 * Check requirements on activation.
		 *
		 * @since  1.3.0
		 */
		public function load_plugin() {
			// Check we're running the required version of WooCommerce.
			if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, $this->required_woo, '<' ) ) {
				add_action( 'admin_notices', array( $this, 'woocommerce_compatibility_notice' ) );
				return false;
			}
		}

		/**
		 * Display a warning message if minimum version of WooCommerce check fails.
		 *
		 * @since  1.3.0
		 * @return void
		 */
		public function woocommerce_compatibility_notice() {
			echo '<div class="error"><p>' . sprintf( __( '%1$s requires at least %2$s v%3$s in order to function. Please upgrade %2$s.', 'woocommerce-coupon-restrictions' ), 'WooCommerce Coupon Restrictions', 'WooCommerce', $this->required_woo ) . '</p></div>';
		}

		/**
		 * Initialize the plugin.
		 *
		 * @since  1.3.0
		 * @return void
		 */
		public function init_plugin() {
			// Load translations.
			load_plugin_textdomain(
				'woocommerce-coupon-restrictions',
				false,
				dirname( plugin_basename( __FILE__ ) ) . '/languages/'
			);

			// Upgrade routine.
			$this->upgrade_routine();

			// Creates a custom table.
			// Only called if a coupon with enhanced usage restrictions is saved.
			require_once $this->plugin_path . '/includes/class-wc-coupon-restrictions-table.php';

			if ( is_admin() ) {
				// Onboarding actions when plugin is first installed.
				require_once $this->plugin_path . '/includes/class-wc-coupon-restrictions-onboarding.php';
				new WC_Coupon_Restrictions_Onboarding();

				// Adds fields and metadata for coupons in admin screen.
				require_once $this->plugin_path . '/includes/class-wc-coupon-restrictions-admin.php';
				new WC_Coupon_Restrictions_Admin();

				// Adds coupon meta fields.
				require_once $this->plugin_path . '/includes/class-wc-coupon-restrictions-settings.php';
				new WC_Coupon_Restrictions_Settings();

				return;
			}

			// Validation methods used for both cart and checkout validation.
			require_once $this->plugin_path . '/includes/class-wc-coupon-restrictions-validation.php';
			new WC_Coupon_Restrictions_Validation();

			// Validates coupons added to the cart.
			require_once $this->plugin_path . '/includes/class-wc-coupon-restrictions-validation-cart.php';
			new WC_Coupon_Restrictions_Validation_Cart();

			// Validates coupons on checkout.
			require_once $this->plugin_path . '/includes/class-wc-coupon-restrictions-validation-checkout.php';
			new WC_Coupon_Restrictions_Validation_Checkout();
		}

		/**
		 * Runs an upgrade routine.
		 *
		 * @since  1.3.0
		 * @return void
		 */
		public function upgrade_routine() {
			$option = get_option( 'woocommerce-coupon-restrictions', false );

			// If a previous version was installed, run any required updates.
			if ( isset( $option['version'] ) ) {
				if ( version_compare( $option['version'], '1.6.2', '<=' ) ) {
					// This setting determines how to verify new/existing customers.
					// In v1.6.2 and before the default was to check against accounts and orders.
					// In new installs, the default is to check against accounts only.
					update_option( 'coupon_restrictions_customer_query', 'accounts-orders' );
				}
			}

			// Sets a transient that triggers the onboarding notice.
			// Notice expires after one week.
			if ( false === $option ) {
				set_transient( 'woocommerce-coupon-restrictions-activated', 1, WEEK_IN_SECONDS );
			}

			// Sets the plugin version number in database.
			if ( false === $option || $this->version !== $option['version'] ) {
				update_option( 'woocommerce-coupon-restrictions', array( 'version' => $this->version ) );
			}
		}
	}
}

/**
 * Public function to access the shared instance of WC_Coupon_Restrictions.
 *
 * @since  1.5.0
 * @return class WC_Coupon_Restrictions
 */
function WC_Coupon_Restrictions() {
	 return WC_Coupon_Restrictions::instance();
}
add_action( 'plugins_loaded', 'WC_Coupon_Restrictions' );
