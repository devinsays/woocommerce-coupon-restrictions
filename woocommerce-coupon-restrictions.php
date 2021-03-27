<?php
/**
 * Plugin Name: WooCommerce Coupon Restrictions
 * Plugin URI: http://woocommerce.com/products/woocommerce-coupon-restrictions/
 * Description: Adds additional coupon restrictions. Coupons can be restricted to new customers, existing customers, or specific locations.
 * Version: 1.8.3
 * Author: WooCommerce
 * Author URI: http://woocommerce.com/
 * Developer: Devin Price
 * Developer URI: https://devpress.com
 * Text Domain: woocommerce-coupon-restrictions
 * Domain Path: /languages
 *
 * Woo: 3200406:6d7b7aa4f9565b8f7cbd2fe10d4f119a
 * WC requires at least: 3.9.0
 * WC tested up to: 5.1.0
 *
 * Copyright: © 2015-2021 DevPress.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'WC_Coupon_Restrictions' ) ) :
class WC_Coupon_Restrictions {

	/**
	 * Plugin Version.
	 *
	 * @access public
	 * @static
	 * @since  1.4.0
	 */
	public $version = '1.8.1';

	/**
	 * Required WooCommerce Version.
	 *
	 * @access public
	 * @since  1.4.0
	 */
	public $required_woo = '3.7.0';

	/**
	 * Instance of WC_Coupon_Restrictions_Validation.
	 *
	 * @var WC_Coupon_Restrictions_Validation
	 */
	public $validation = null;

	/**
	 * Instance of WC_Coupon_Restrictions_Onboarding.
	 *
	 * @var WC_Coupon_Restrictions_Validation
	 */
	public $onboarding = null;

	/**
	 * Instance of WC_Coupon_Restrictions_Admin.
	 *
	 * @var WC_Coupon_Restrictions_Validation
	 */
	public $admin = null;

	/**
	 * Instance of WC_Coupon_Restrictions_Settings.
	 *
	 * @var WC_Coupon_Restrictions_Settings
	 */
	public $settings = null;

	/**
	 * Plugin path.
	 *
	 * @access public
	 * @since  1.3.0
	 */
	public static function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Plugin base file.
	 * Used for activation hook and plugin links.
	 *
	 * @access public
	 * @since  1.5.0
	 */
	public static function plugin_base() {
		return plugin_basename( __FILE__ );
	}

	/**
	 * Loads the plugin.
	 *
	 * @access public
	 * @since  1.3.0
	 */
	public function __construct() {

		// Loads plugin classes.
		$this->includes();

		// Checks WooCommerce version.
		add_action( 'plugins_loaded', array( $this, 'load_plugin' ) );

		// Init hooks runs after plugins_loaded and woocommerce_loaded hooks.
		add_action( 'init', array( $this, 'init_plugin' ) );

	}

	/**
	 * Check requirements on activation.
	 *
	 * @access public
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
	 * @access public
	 * @since  1.3.0
	 * @return void
	 */
	public function woocommerce_compatibility_notice() {
		echo '<div class="error"><p>' . sprintf( __( '%1$s requires at least %2$s v%3$s in order to function. Please upgrade %2$s.', 'woocommerce-coupon-restrictions' ), 'WooCommerce Coupon Restrictions', 'WooCommerce', $this->required_woo ) . '</p></div>';
	}

	/**
	 * Includes classes that implement coupon restrictions.
	 *
	 * @access public
	 * @since  1.3.0
	 * @return void
	 */
	public function includes() {

		if ( is_admin() ) {

			// Onboarding actions when plugin is first installed.
			require_once $this->plugin_path() . '/includes/class-wc-coupon-restrictions-onboarding.php';
			$this->onboarding = new WC_Coupon_Restrictions_Onboarding();

			// Adds fields and metadata for coupons in admin screen.
			require_once $this->plugin_path() . '/includes/class-wc-coupon-restrictions-admin.php';
			$this->admin = new WC_Coupon_Restrictions_Admin();

			// Adds global coupon settings.
			require_once $this->plugin_path() . '/includes/class-wc-coupon-restrictions-settings.php';
			$this->settings = new WC_Coupon_Restrictions_Settings();

		} else {

			// Validates coupons.
			require_once $this->plugin_path() . '/includes/class-wc-coupon-restrictions-validation.php';
			$this->validation = new WC_Coupon_Restrictions_Validation();

		}

	}

	/**
	 * Initialize the plugin.
	 *
	 * @access public
	 * @since  1.3.0
	 * @return void
	 */
	public function init_plugin() {
		// Load translations.
		load_plugin_textdomain(
			'woocommerce-coupon-restrictions',
			false,
			dirname( plugin_basename(__FILE__) ) . '/languages/'
		);

		// Upgrade routine.
		$this->upgrade_routine();

		// Inits classes.
		if ( is_admin() ) {
			$this->onboarding->init();
			$this->admin->init();
			$this->settings->init();
		} else {
			$this->validation->init();
		}

	}

	/**
	 * Runs an upgrade routine.
	 *
	 * @access public
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
endif;

/**
 * Returns a shared instance of WC_Coupon_Restrictions.
 *
 * @since  1.6.1
 * @return class WC_Coupon_Restrictions
 */
class WC_Coupon_Restrictions_Factory {

	public static function create() {
		// Will only be declared once per session, if it hasn't been declared before.
		static $plugin = null;

		if ( null === $plugin ) {
			$plugin = new WC_Coupon_Restrictions();
		}

		return $plugin;
	}

}

/**
 * Public function to access the shared instance of WC_Coupon_Restrictions.
 *
 * @since  1.5.0
 * @return class WC_Coupon_Restrictions_Factory
 */
function WC_Coupon_Restrictions() {
	return WC_Coupon_Restrictions_Factory::create();
}
add_action( 'plugins_loaded', 'WC_Coupon_Restrictions' );
