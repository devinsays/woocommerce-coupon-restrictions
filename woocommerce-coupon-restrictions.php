<?php
/**
 * Plugin Name: WooCommerce Coupon Restrictions
 * Plugin URI: https://devpress.com/products/woocommerce-coupon-restrictions/
 * Description: Allows for additional coupon restrictions. Coupons can be restricted to new customers, existing customers, or by country.
 * Version: 1.5.1
 * Author: DevPress
 * Author URI: https://devpress.com
 *
 * Requires at least: 4.7.0
 * Tested up to: 4.9.5
 * WC requires at least: 3.3.0
 * WC tested up to: 3.3.5
 *
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * Text Domain: woocommerce-coupon-restrictions
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/devinsays/woocommerce-coupon-restrictions.git
 * GitHub URI: devinsays/woocommerce-coupon-restrictions
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

include( dirname( __FILE__ ) . '/github-updater.php' );

if ( ! class_exists( 'WC_Coupon_Restrictions' ) ) :
class WC_Coupon_Restrictions {

	/**
	 * @var WC_Coupon_Restrictions - The single instance of the class.
	 *
	 * @access protected
	 * @static
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	/**
	 * Plugin Version
	 *
	 * @access public
	 * @static
	 * @since  1.4.0
	 */
	public $version = '1.5.0';

	/**
	 * Required WooCommerce Version
	 *
	 * @access public
	 * @since  1.4.0
	 */
	public $required_woo = '3.3.0';

	/**
	 * Plugin path.
	 *
	 * @access public
	 * @static
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
	 * @static
	 * @since  1.5.0
	 */
	public static function plugin_base() {
		return plugin_basename( __FILE__ );
	}

	/**
	 * Main WC_Coupon_Restrictions Instance.
	 *
	 * Ensures only one instance of WC_Coupon_Restrictions is loaded or can be loaded.
	 *
	 * @access public
	 * @static
	 * @since  1.0.0
	 * @see    WC_Coupon_Restrictions()
	 * @return WC_Coupon_Restrictions - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Loads the plugin.
	 *
	 * @access public
	 * @since  1.3.0
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_plugin' ) );
		add_action( 'woocommerce_loaded', array( $this, 'includes' ) );

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
		$options = get_option( 'woocommerce-coupon-restrictions', false );
		if ( false === $options || $this->version !== $options['version'] ) {
			$this->upgrade_routine();
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
		update_option( 'woocommerce-coupon-restrictions', array( 'version' => $this->version ) );
	}

	/**
	 * Includes classes that implement coupon restrictions.
	 *
	 * @access public
	 * @since  1.3.0
	 * @return void
	 */
	public function includes() {

		// Files loaded in the dashboard only.
		if ( is_admin() ) {
			// Onboarding actions when plugin is first installed.
			include_once( $this->plugin_path() . '/includes/class-wc-coupon-restrictions-onboarding.php' );

			// Adds fields and metadata for coupons in admin screen.
			include_once( $this->plugin_path() . '/includes/class-wc-coupon-restrictions-admin.php' );
		}

		// Validates coupons on checkout.
		if ( ! is_admin() ) {
			include_once( $this->plugin_path() . '/includes/class-wc-coupon-restrictions-validation.php' );
		}

	}

}
endif;

return WC_Coupon_Restrictions::instance();
