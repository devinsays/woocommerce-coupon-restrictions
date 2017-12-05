<?php
/**
 * WooCommerce Coupon Restrictions - Validation.
 *
 * @class    WC_Coupon_Restrictions_Validation
 * @author   DevPress
 * @package  WooCommerce Coupon Restrictions
 * @license  GPL-2.0+
 * @since    1.3.0
 */

if ( ! defined('ABSPATH') ) {
	exit; // Exit if accessed directly.
}

class WC_Coupon_Restrictions_Validation {

	/**
	* Initialize the class.
	*/
	public static function init() {

		// Validates coupons before checkout if customer is logged in
		add_filter( 'woocommerce_coupon_is_valid', __CLASS__ . '::validate_coupons', 10, 2 );

		// Validates coupons again during checkout validation
		add_action( 'woocommerce_after_checkout_validation', __CLASS__ . '::check_customer_coupons', 1 );

	}

	/**
	 * Validates coupon when added (if possible due to log in state).
	 *
	 * @return boolean $valid
	 */
	public static function validate_coupons( $valid, $coupon ) {

		// If coupon already marked invalid, no sense in moving forward.
		if ( ! $valid ) {
			return $valid;
		}

		// Can't validate e-mail at this point unless customer is logged in.
		if ( ! is_user_logged_in() ) {
			return $valid;
		}

		// Validate new customer restriction
		$new_customers_restriction = $coupon->get_meta( 'new_customers_only', true );
		if ( 'yes' == $new_customers_restriction ) {
			$valid = $this->validate_new_customer_coupon();
		}

		// Validate existing customer restriction
		$existing_customers_restriction = $coupon->get_meta( 'existing_customers_only', true );
		if ( 'yes' == $existing_customers_restriction ) {
			$valid = $this->validate_existing_customer_coupon();
		}

		return $valid;

	}

	/**
	 * If user is logged in, validates new customer coupon.
	 *
	 * @return boolean
	 */
	public static function validate_new_customer_coupon() {

		// If current customer is an existing customer, return false
		$current_user = wp_get_current_user();
		$customer = new WC_Customer( $current_user->ID );

		if ( $customer->get_is_paying_customer() ) {
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_new_customer_restriction' ), 10, 2 );
			return false;
		}

		return true;
	}

	/**
	 * If user is logged in, validates existing cutomer coupon.
	 *
	 * @return boolean
	 */
	public static function validate_existing_customer_coupon() {

		// If current customer is not an existing customer, return false
		$current_user = wp_get_current_user();
		$customer = new WC_Customer( $current_user->ID );

		if ( ! $customer->get_is_paying_customer() ) {
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_existing_customer_restriction' ), 10, 2 );
			return false;
		}

		return true;
	}

	/**
	 * Applies new customer coupon error message.
	 *
	 * @return string $err
	 */
	public static function validation_message_new_customer_restriction( $err, $err_code ) {

		// Alter the validation message if coupon has been removed
		if ( 100 == $err_code ) {
			// Validation message
			$msg = __( 'Coupon removed. This coupon is only valid for new customers.', 'woocommerce-coupon-restrictions' );
			$err = apply_filters( 'woocommerce-coupon-restrictions-removed-message', $msg );
		}

		// Return validation message
		return $err;
	}

	/**
	 * Applies existing customer coupon error message.
	 *
	 * @return string $err
	 */
	public static function validation_message_existing_customer_restriction( $err, $err_code ) {

		// Alter the validation message if coupon has been removed
		if ( 100 == $err_code ) {
			// Validation message
			$msg = __( 'Coupon removed. This coupon is only valid for existing customers.', 'woocommerce-coupon-restrictions' );
			$err = apply_filters( 'woocommerce-coupon-restrictions-removed-message', $msg );
		}

		// Return validation message
		return $err;
	}

	/**
	 * Check user coupons (now that we have billing email). If a coupon is invalid, add an error.
	 *
	 * @param array $posted
	 */
	public static function check_customer_coupons( $posted ) {

		if ( ! empty( WC()->cart->applied_coupons ) ) {

			foreach ( WC()->cart->applied_coupons as $code ) {

				$coupon = new WC_Coupon( $code );

				if ( $coupon->is_valid() ) {

					// Check if coupon is restricted to new customers.
					$new_customers_restriction = $coupon->get_meta( 'new_customers_only', true );

					if ( 'yes' === $new_customers_restriction ) {
						$this->check_new_customer_coupon_checkout( $coupon, $code );
					}

					// Check if coupon is restricted to existing customers.
					$existing_customers_restriction = $coupon->get_meta( 'existing_customers_only', true );
					if ( 'yes' === $existing_customers_restriction ) {
						$this->check_existing_customer_coupon_checkout( $coupon, $code );
					}

					// Check country restrictions
					$shipping_country_restriction = $coupon->get_meta( 'shipping_country_restriction', true );
					if ( ! empty( $shipping_country_restriction ) ) {
						$this->check_shipping_country_restriction_checkout( $coupon, $code );
					}

				}
			}
		}
	}

	/**
	 * Validates new customer coupon on checkout.
	 *
	 * @param object $coupon
	 * @param string $code
	 * @return void
	 */
	public static function check_new_customer_coupon_checkout( $coupon, $code ) {

		// Validation message
		$msg = sprintf( __( 'Coupon removed. Code "%s" is only valid for new customers.', 'woocommerce-coupon-restrictions' ), $code );

		// Check if order is for returning customer
		if ( is_user_logged_in() ) {

			// If user is logged in, we can check for paying_customer meta.
			$current_user = wp_get_current_user();
			$customer = new WC_Customer( $current_user->ID );

			if ( $customer->get_is_paying_customer() ) {
				$this->remove_coupon( $coupon, $code, $msg );
			}

		} else {

			// If user is not logged in, we can check against previous orders.
			$email = strtolower( $_POST['billing_email'] );
			if ( $this->is_returning_customer( $email ) ) {
				$this->remove_coupon( $coupon, $code, $msg );
			}

		}
	}

	/**
	 * Validates existing customer coupon on checkout.
	 *
	 * @param object $coupon
	 * @param string $code
	 * @return void
	 */
	public static function check_existing_customer_coupon_checkout( $coupon, $code ) {

		// Validation message
		$msg = sprintf( __( 'Coupon removed. Code "%s" is only valid for existing customers.', 'woocommerce-coupon-restrictions' ), $code );

		// Check if order is for returning customer
		if ( is_user_logged_in() ) {

			// If user is logged in, we can check for paying_customer meta.
			$current_user = wp_get_current_user();
			$customer = new WC_Customer( $current_user->ID );

			if ( ! $customer->get_is_paying_customer() ) {
				$this->remove_coupon( $coupon, $code, $msg );
			}

		} else {

			// If user is not logged in, we can check against previous orders.
			$email = strtolower( $_POST['billing_email'] );
			if ( ! $this->is_returning_customer( $email ) ) {
				$this->remove_coupon( $coupon, $code, $msg );
			}

		}
	}

	/**
	 * Validates country restrictions on checkout.
	 *
	 * @param object $coupon
	 * @param string $code
	 * @return void
	 */
	public static function check_shipping_country_restriction_checkout( $coupon, $code ) {

		// Validation message
		$msg = sprintf( __( 'Coupon removed. Code "%s" is not valid in your shipping country.', 'woocommerce-coupon-restrictions' ), $code );

		if ( isset( $_POST['shipping_country'] ) ) {
			// Get shipping country if it exists
			$country = esc_textarea( $_POST['shipping_country'] );
		} elseif ( isset( $_POST['billing_country'] ) ) {
			// Some sites don't have separate billing vs shipping option
			// In that case we use the billing_country
			$country = esc_textarea( $_POST['billing_country'] );
		} else {
			// Fallback if we can't determine shipping or billing country
			$country = '';
		}

		// Get the allowed countries from coupon meta
		$allowed_countries = $coupon->get_meta( 'shipping_country_restriction', true );

		if ( ! in_array( $country, $allowed_countries ) ) {
			$this->remove_coupon( $coupon, $code, $msg );
		}

	}

	/**
	 * Removes coupon and displays validation message.
	 *
	 * @param object $coupon
	 * @param string $code
	 * @return void
	 */
	public static function remove_coupon( $coupon, $code, $msg ) {

		// Filter to change validation text
		$msg = apply_filters( 'woocommerce-coupon-restrictions-removed-message-with-code', $msg, $code, $coupon );

		// Remove the coupon
		WC()->cart->remove_coupon( $code );

		// Throw a notice to stop checkout
		wc_add_notice( $msg, 'error' );

		// Flag totals for refresh
		WC()->session->set( 'refresh_totals', true );

	}

	/**
	 * Checks if e-mail address has been used previously for a purchase.
	 *
	 * @param string $email of customer
	 * @return boolean
	 */
	public static function is_returning_customer( $email ) {

		$customer_orders = wc_get_orders( array(
			'status' => array( 'wc-processing', 'wc-completed' ),
			'email'  => $email,
			'limit'  => 1
		) );

		// If there is at least one other order by billing e-mail
		if ( 1 === count( $customer_orders ) ) {
			return true;
		}

		// Otherwise there should not be any orders
		return false;
	}

}

WC_Coupon_Restrictions_Validation::init();
