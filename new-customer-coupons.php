<?php
/**
 * Plugin Name: WooCommerce New Customer Coupons
 * Plugin URI: http://github.com/devinsays/woocommerce-new-customer-coupons
 * Description: Allows coupons to be restricted to new customers only.
 * Version: 1.1.0
 * Author: DevPress
 * Author URI: https://devpress.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: woocommerce-new-customer-coupons
 * Domain Path: /languages
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'WC_New_Customer_Coupons' ) ) :

class WC_New_Customer_Coupons {

	/**
	* Construct the plugin.
	*/
	public function __construct() {

		// Load translations
		load_plugin_textdomain( 'woocommerce-new-customer-coupons', false, dirname( plugin_basename(__FILE__) ) . '/languages/' );

		// Fire up the plugin!
		add_action( 'plugins_loaded', array( $this, 'init' ) );

	}

	/**
	* Initialize the plugin.
	*/
	public function init() {

		// Adds metabox to usage restriction fields
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'new_customer_restriction' ) );

		// Saves the metabox
		add_action( 'woocommerce_coupon_options_save', array( $this, 'coupon_options_save' ) );

		// Validates coupons before checkout if customer is logged in
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon' ), 10, 2 );

		// Validates coupons again during checkout validation
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'check_customer_coupons' ), 1 );

	}

	/**
	 * Adds "new customer" restriction checkbox
	 *
	 * @return void
	 */
	public function new_customer_restriction() {

		echo '<div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id' => 'new_customers_only',
				'label' => __( 'New customers only', 'woocommerce-new-customer-coupons' ),
				'description' => __( 'Verifies that customer e-mail address has not been used previously.', 'woocommerce-new-customer-coupons' )
			)
		);

		echo '</div>';

	}

	/**
	 * Saves post meta for "new customer" restriction
	 *
	 * @return void
	 */
	public function coupon_options_save( $post_id ) {

		// Sanitize meta
		$new_customers_only = isset( $_POST['new_customers_only'] ) ? 'yes' : 'no';

		// Save meta
		update_post_meta( $post_id, 'new_customers_only', $new_customers_only );

	}

	/**
	 * If user is logged in, validates coupon when added
	 *
	 * @return void
	 */
	public function validate_coupon( $valid, $coupon ) {

		// If coupon already marked invalid, no sense in moving forward.
		if ( ! $valid ) {
			return $valid;
		}

		// Can't validate e-mail at this point unless customer is logged in.
		if ( ! is_user_logged_in() ) {
			return $valid;
		}

		// If specific coupon doesn't have restriction, return valid
		$new_customers_restriction = get_post_meta( $coupon->get_id(), 'new_customers_only', true );
		if ( 'yes' !== $new_customers_restriction ) {
			return $valid;
		}

		// If current customer is an existing customer, return false
		$current_user = wp_get_current_user();
		if ( WC()->customer->get_is_paying_customer( $current_user->ID ) ) {

			add_filter( 'woocommerce_coupon_is_valid', array( $this, 'coupon_is_valid' ), 10, 2 );
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message' ), 10, 2 );
			return false;
		}

		return $valid;

	}

	/**
	 * Invalidates coupon
	 *
	 * @return boolean validity
	 */
	function invalidate_coupon( $value ) {
		return false;
	}

	/**
	 * Applies correct error message
	 *
	 * @return $err error message
	 */
	function validation_message( $err, $err_code ) {

		// Alter the validation message if coupon has been removed
		if ( 100 == $err_code ) {
			// Validation message
			$msg = __( 'Coupon removed. This coupon is only valid for new customers.', 'woocommerce-new-customer-coupons' );
			$err = apply_filters( 'wcncc-coupon-removed-message', $msg );
		}

		// Return validation message
		return $err;
	}

	/**
	 * Check user coupons (now that we have billing email). If a coupon is invalid, add an error.
	 *
	 * @param array $posted
	 */
	public function check_customer_coupons( $posted ) {

		if ( ! empty( WC()->cart->applied_coupons ) ) {

			foreach ( WC()->cart->applied_coupons as $code ) {

				$coupon = new WC_Coupon( $code );

				if ( $coupon->is_valid() ) {

					$new_customers_restriction = get_post_meta( $coupon->get_id(), 'new_customers_only', true );

					// Finally! Check if coupon is restricted to new customers.
					if ( 'yes' === $new_customers_restriction ) {

						// Check if order is for returning customer
						if ( is_user_logged_in() ) {

							// If user is logged in, we can check for paying_customer meta.
							$current_user = wp_get_current_user();
							if ( WC()->customer->get_is_paying_customer( $current_user->ID ) ) {
								$this->remove_coupon_returning_customer( $coupon, $code );
							}

						} else {

							// If user is not logged in, we can check against previous orders.
							$email = strtolower( $posted['billing_email'] );
							if ( $this->is_returning_customer( $email ) ) {
								$this->remove_coupon_returning_customer( $coupon, $code );
							}

						}
					}
				}
			}
		}

	}

	/**
	 * Removes coupon for existing customers if is restricted to new customers.
	 *
	 * @param object $coupon
	 * @param string $code
	 */
	public function remove_coupon_returning_customer( $coupon, $code ) {

		// Validation message
		$msg = sprintf( __( 'Coupon removed. Code "%s" is only valid for new customers.', 'woocommerce-new-customer-coupons' ), $code );

		// Filter to change validation text
		$msg = apply_filters( 'wcncc-coupon-removed-message-with-code', $msg, $code, $coupon );

		// Throw a notice to stop checkout
		wc_add_notice( $msg, 'error' );

		// Remove the coupon
		WC()->cart->remove_coupon( $code );

		// Flag totals for refresh
		WC()->session->set( 'refresh_totals', true );

	}

	/**
	 * Checks if e-mail address has been used previously for a purchase.
	 *
	 * @returns boolean
	 */
	public function is_returning_customer( $email ) {

		$customer_orders = get_posts( array(
			'post_type'   => 'shop_order',
		    'meta_key'    => '_billing_email',
		    'post_status' => 'publish',
		    'post_status' => array( 'wc-processing', 'wc-completed' ),
		    'meta_value'  => $email,
		    'numberposts' => 1,
		    'cache_results' => false,
		    'no_found_rows' => true,
		    'fields' => 'ids'
		) );

		// If there is at least one other order by billing e-mail
		if ( 1 == count( $customer_orders ) ) {
			return true;
		}

		// Otherwise there should not be any orders
		return false;
	}

}

new WC_New_Customer_Coupons();

endif;