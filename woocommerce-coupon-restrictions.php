<?php
/**
 * Plugin Name: WooCommerce Coupon Restrictions
 * Plugin URI: http://github.com/devinsays/woocommerce-coupon-restrictions
 * Description: Allows for additional coupon restrictions. Coupons can be restricted to new customers, existing customers, or by country.
 * Version: 1.2.0
 * Author: DevPress
 * Author URI: https://devpress.com
 *
 * Requires at least: 4.5
 * Tested up to: 4.9.1
 * WC requires at least: 3.2.1
 * WC tested up to: 3.2.5
 *
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * Text Domain: woocommerce-coupon-restrictions
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

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
	public static $version = '1.4.0';

	/**
	 * Required WooCommerce Version
	 *
	 * @access public
	 * @since  1.4.0
	 */
	public $required_woo = '3.0.0';

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
		add_action( 'init', array( $this, 'init_plugin' ) );

		// Include required files
		add_action( 'woocommerce_loaded', array( $this, 'includes' ) );
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
		echo '<div class="error"><p>' . sprintf( __( '%1$s requires at least %2$s v%3$s in order to function. Please upgrade %2$s.', 'woocommerce-coupon-restriction' ), 'WooCommerce Coupon Restrictions', 'WooCommerce', $this->required_woo ) . '</p></div>';
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
		load_plugin_textdomain( 'woocommerce-coupon-restrictions', false, dirname( plugin_basename(__FILE__) ) . '/languages/' );

		// Upgrade routine.
		$options = get_option( 'woocommerce-coupon-restrictions', false );
		if ( false === $options ) {
			$this->upgrade_routine();
		}
	}

	/**
	 * Includes classes that implement coupon restrictions.
	 *
	 * @access public
	 * @since  1.3.0
	 * @return void
	 */
	public function includes() {

		// Adds fields and metadata for coupons in admin screen.
		if ( is_admin() ) {
			include_once( $this->plugin_path() . '/includes/class-wc-coupon-restrictions-admin.php' );
		}

		// Validates coupons on checkout.
		if ( ! is_admin() ) {
			include_once( $this->plugin_path() . '/includes/class-wc-coupon-restrictions-validation.php' );
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

		// Coupon meta keys changed between 1.3.0 and 1.4.0
		// Instead of two checkboxes there is a single select option.
		$args = array(
			'post_type'  => 'shop_coupon',
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key'     => 'existing_customers_only',
					'value'   => 'yes',
					'compare' => '='
				),
				array(
					'key'     => 'new_customers_only',
					'value'   => 'yes',
					'compare' => '='
				)
			)
		);

		// Query for all coupons that had customer restrictions set.
		$coupon_query = new WP_Query( $args );
		if ( $coupon_query->have_posts() ) {
			while( $coupon_query->have_posts() ) {
				$coupon_query->the_post();
				$existing_customer = get_post_meta( get_the_ID(), 'existing_customers_only', true );
				$new_customer = get_post_meta( get_the_ID(), 'new_customers_only', true );

				$customer_restriction_type = 'none';
				if ( 'yes' === $existing_customer && 'yes' == $new_customer ) {
					// Coupon should not be set to both.
					$customer_restriction_type = 'none';
				} elseif ( 'yes' === $existing_customer ) {
					$customer_restriction_type = 'existing';
				} elseif ( 'yes' === $new_customer ) {
					$customer_restriction_type = 'new';
				}

				error_log( get_the_ID() );

				// Update to new meta field.
				update_post_meta( get_the_ID(), 'customer_restriction_type', $customer_restriction_type );

				// Clean up.
				delete_post_meta( get_the_ID(), 'existing_customers_only' );
				delete_post_meta( get_the_ID(), 'new_customers_only' );

			}
		}
		wp_reset_postdata();

		add_option( 'woocommerce-coupon-restrictions', array( 'version' => $this->version ) );

	}

}
endif;

return WC_Coupon_Restrictions::instance();
