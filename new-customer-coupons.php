<?php
/**
 * Plugin Name: WooCommerce New Customer Coupons
 * Plugin URI: http://github.com/devinsays/woocommerce-new-customer-coupons
 * Description: Allows coupons to be restricted to new customers or existing customers.
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
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'coupon_restrictions' ) );

		// Saves the metabox
		add_action( 'woocommerce_coupon_options_save', array( $this, 'coupon_options_save' ) );

		// Validates coupons before checkout if customer is logged in
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupons' ), 10, 2 );

		// Validates coupons again during checkout validation
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'check_customer_coupons' ), 1 );

	}

	/**
	 * Adds "new customer" restriction checkbox
	 *
	 * @return void
	 */
	public function coupon_restrictions() {

		echo '<div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id' => 'new_customers_only',
				'label' => __( 'New customers only', 'woocommerce-new-customer-coupons' ),
				'description' => __( 'Verifies customer e-mail address <b>has not</b> been used previously.', 'woocommerce-new-customer-coupons' )
			)
		);

		woocommerce_wp_checkbox(
			array(
				'id' => 'existing_customers_only',
				'label' => __( 'Existing customers only', 'woocommerce-new-customer-coupons' ),
				'description' => __( 'Verifies customer e-mail address has been used previously.', 'woocommerce-new-customer-coupons' )
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
		$existing_customers_only = isset( $_POST['existing_customers_only'] ) ? 'yes' : 'no';

		// Save meta
		update_post_meta( $post_id, 'new_customers_only', $new_customers_only );
		update_post_meta( $post_id, 'existing_customers_only', $existing_customers_only );

	}

	/**
	 * Validates coupon when added (if possible due to log in state)
	 *
	 * @return void
	 */
	public function validate_coupons( $valid, $coupon ) {

		// If coupon already marked invalid, no sense in moving forward.
		if ( ! $valid ) {
			return $valid;
		}

		// Can't validate e-mail at this point unless customer is logged in.
		if ( ! is_user_logged_in() ) {
			return $valid;
		}

		// Validate new customer restriction
		$new_customers_restriction = get_post_meta( $coupon->id, 'new_customers_only', true );
		if ( 'yes' == $new_customers_restriction ) {
			$valid = $this->validate_new_customer_coupon();
		}

		// Validate existing customer restriction
		$existing_customers_restriction = get_post_meta( $coupon->id, 'existing_customers_only', true );
		if ( 'yes' == $existing_customers_restriction ) {
			$valid = $this->validate_existing_customer_coupon();
		}

		return $valid;

	}

	/**
	 * If user is logged in, validates new customer coupon
	 *
	 * @return void
	 */
	public function validate_new_customer_coupon() {

		// If current customer is an existing customer, return false
		$current_user = wp_get_current_user();
		$customer = new WC_Customer( $current_user->ID );

		if ( $customer->is_paying_customer( $current_user->ID ) ) {
			error_log( 'is_paying_customer true' );
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_new_customer_restriction' ), 10, 2 );
			return false;
		}

		return true;
	}

	/**
	 * If user is logged in, validates existing cutomer coupon
	 *
	 * @return void
	 */
	public function validate_existing_customer_coupon() {

		// If current customer is not an existing customer, return false
		$current_user = wp_get_current_user();
		$customer = new WC_Customer( $current_user->ID );

		if ( ! $customer->is_paying_customer( $current_user->ID ) ) {
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_existing_customer_restriction' ), 10, 2 );
			return false;
		}

		return true;
	}

	/**
	 * Applies new customer coupon error message
	 *
	 * @return $err error message
	 */
	function validation_message_new_customer_restriction( $err, $err_code ) {

		// Alter the validation message if coupon has been removed
		if ( 100 == $err_code ) {
			// Validation message
			$msg = __( 'Coupon removed. This coupon is only valid for new customers.', 'woocommerce-new-customer-coupons' );
			$err = apply_filters( 'woocommerce-new-customer-coupons-removed-message', $msg );
		}

		// Return validation message
		return $err;
	}

	/**
	 * Applies existing customer coupon error message
	 *
	 * @return $err error message
	 */
	function validation_message_existing_customer_restriction( $err, $err_code ) {

		// Alter the validation message if coupon has been removed
		if ( 100 == $err_code ) {
			// Validation message
			$msg = __( 'Coupon removed. This coupon is only valid for existing customers.', 'woocommerce-new-customer-coupons' );
			$err = apply_filters( 'woocommerce-new-customer-coupons-removed-message', $msg );
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

					// Check if coupon is restricted to new customers.
					$new_customers_restriction = get_post_meta( $coupon->id, 'new_customers_only', true );
					if ( 'yes' === $new_customers_restriction ) {
						$this->check_new_customer_coupon_checkout( $coupon, $code );
					}

					// Check if coupon is restricted to existing customers.
					$existing_customers_restriction = get_post_meta( $coupon->id, 'existing_customers_only', true );
					if ( 'yes' === $existing_customers_restriction ) {
						$this->check_existing_customer_coupon_checkout( $coupon, $code );
					}

				}
			}
		}
	}

	/**
	 * Validates new customer coupon on checkout
	 *
	 * @param object $coupon
	 * @param string $code
	 */
	public function check_new_customer_coupon_checkout( $coupon, $code ) {

		// Validation message
		$msg = sprintf( __( 'Coupon removed. Code "%s" is only valid for new customers.', 'woocommerce-new-customer-coupons' ), $code );

		// Check if order is for returning customer
		if ( is_user_logged_in() ) {

			// If user is logged in, we can check for paying_customer meta.
			$current_user = wp_get_current_user();
			$customer = new WC_Customer( $current_user->ID );

			if ( $customer->is_paying_customer ) {
				$this->remove_coupon( $coupon, $code, $msg );
			}

		} else {

			// If user is not logged in, we can check against previous orders.
			$email = strtolower( $posted['billing_email'] );
			if ( $this->is_returning_customer( $email ) ) {
				$this->remove_coupon( $coupon, $code, $msg );
			}

		}
	}

	/**
	 * Validates existing customer coupon on checkout
	 *
	 * @param object $coupon
	 * @param string $code
	 */
	public function check_existing_customer_coupon_checkout( $coupon, $code ) {

		// Validation message
		$msg = sprintf( __( 'Coupon removed. Code "%s" is only valid for existing customers.', 'woocommerce-new-customer-coupons' ), $code );

		// Check if order is for returning customer
		if ( is_user_logged_in() ) {

			// If user is logged in, we can check for paying_customer meta.
			$current_user = wp_get_current_user();
			$customer = new WC_Customer( $current_user->ID );

			if ( ! $customer->is_paying_customer ) {
				$this->remove_coupon( $coupon, $code, $msg );
			}

		} else {

			// If user is not logged in, we can check against previous orders.
			$email = strtolower( $posted['billing_email'] );
			if ( ! $this->is_returning_customer( $email ) ) {
				$this->remove_coupon( $coupon, $code, $msg );
			}

		}
	}

	/**
	 * Removes coupon and displays validation message
	 *
	 * @param object $coupon
	 * @param string $code
	 */
	public function remove_coupon( $coupon, $code, $msg ) {

		// Filter to change validation text
		$msg = apply_filters( 'woocommerce-new-customer-coupons-removed-message-with-code', $msg, $code, $coupon );

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