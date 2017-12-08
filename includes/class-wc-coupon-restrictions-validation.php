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

		// Can't validate coupon at this point unless customer is logged in.
		if ( ! is_user_logged_in() ) {
			return $valid;
		}

		// Get customer restriction type meta.
		$customer_restriction_type = $coupon->get_meta( 'customer_restriction_type', true );

		// Validate new customer restriction.
		if ( 'new' == $customer_restriction_type ) {
			$valid = self::validate_new_customer_coupon();
		}

		// Validate existing customer restriction.
		if ( 'existing' == $customer_restriction_type ) {
			$valid = self::validate_existing_customer_coupon();
		}

		// Check country restrictions
		$country_restriction = $coupon->get_meta( 'country_restriction' );
		if ( ! empty( $country_restriction ) ) {
			$valid = self::check_country_restriction_user( $coupon );
		}

		return $valid;

	}

	/**
	 * If user is logged in, validates new customer coupon.
	 *
	 * @return boolean
	 */
	public static function validate_new_customer_coupon() {

		// If current customer is an existing customer, return false.
		$current_user = wp_get_current_user();
		$customer = new WC_Customer( $current_user->ID );

		if ( $customer->get_is_paying_customer() ) {
			add_filter( 'woocommerce_coupon_error', __CLASS__ . '::validation_message_new_customer_restriction', 10, 2 );
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

		// If current customer is not an existing customer, return false.
		$current_user = wp_get_current_user();
		$customer = new WC_Customer( $current_user->ID );

		if ( ! $customer->get_is_paying_customer() ) {
			add_filter( 'woocommerce_coupon_error', __CLASS__ . '::validation_message_existing_customer_restriction', 10, 2 );
			return false;
		}

		return true;
	}

	/**
	 * If user is logged in, validates country restriction.
	 *
	 * @param object $coupon
	 * @return void
	 */
	public static function check_country_restriction_user( $coupon ) {

		$current_user = wp_get_current_user();
		$customer = new WC_Customer( $current_user->ID );

		// Get address lookup for location restrictions.
		$address_for_location_restrictions = $coupon->get_meta( 'address_for_location_restrictions', true );
		if ( ! in_array( $address_for_location_restrictions, array( 'billing', 'shipping' ) ) ) {
			$address_for_location_restrictions = 'shipping';
		}

		// Set $county to empty string as a fallback.
		$country = '';

		// If shipping address is selected, set $country to shipping_country.
		// If shipping address is selected but not present, fallback to billing_country.
		if ( 'shipping' === $address_for_location_restrictions ) {
			if ( $customer->get_shipping_country() ) {
				$country = $customer->get_shipping_country();
			} elseif ( $customer->get_billing_country() ) {
				$country = $customer->get_billing_country();
			}
		}

		// If billing address is selected, set $country to billing_country.
		if ( 'billing' === $address_for_location_restrictions && $customer->get_billing_country() ) {
			$country = $customer->get_billing_country();
		}

		// Get the allowed countries from coupon meta.
		$allowed_countries = $coupon->get_meta( 'country_restriction', true );

		if ( ! in_array( $country, $allowed_countries ) ) {
			add_filter( 'woocommerce_coupon_error', __CLASS__ . '::validation_message_country_restriction', 10, 2 );
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

		// Alter the validation message if coupon has been removed.
		if ( 100 === $err_code ) {
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

		// Alter the validation message if coupon has been removed.
		if ( 100 === $err_code ) {
			// Validation message
			$msg = __( 'Coupon removed. This coupon is only valid for existing customers.', 'woocommerce-coupon-restrictions' );
			$err = apply_filters( 'woocommerce-coupon-restrictions-removed-message', $msg );
		}

		// Return validation message.
		return $err;
	}

	/**
	 * Applies country restriction error message.
	 *
	 * @return string $err
	 */
	public static function validation_message_country_restriction( $err, $err_code ) {

		// Alter the validation message if coupon has been removed.
		if ( 100 === $err_code ) {
			// Validation message
			$msg = __( 'Coupon removed. This coupon is not valid in your country.', 'woocommerce-coupon-restrictions' );
			$err = apply_filters( 'woocommerce-coupon-restrictions-removed-message', $msg );
		}

		// Return validation message.
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

					// Get customer restriction type meta.
					$customer_restriction_type = $coupon->get_meta( 'customer_restriction_type', true );

					// Check if coupon is restricted to new customers.
					if ( 'new' == $customer_restriction_type ) {
						$valid = self::check_new_customer_coupon_checkout();
					}

					// Check if coupon is restricted to existing customers.
					if ( 'existing' == $existing_customers_restriction ) {
						$valid = self::check_existing_customer_coupon_checkout();
					}

					// Check country restrictions
					$country_restriction = $coupon->get_meta( 'country_restriction', true );
					if ( ! empty( $country_restriction ) ) {
						self::check_country_restriction_checkout( $coupon, $code );
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
				self::remove_coupon( $coupon, $code, $msg );
			}

		} else {

			// If user is not logged in, we can check against previous orders.
			$email = strtolower( $_POST['billing_email'] );
			if ( self::is_returning_customer( $email ) ) {
				self::remove_coupon( $coupon, $code, $msg );
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

		// Validation message.
		$msg = sprintf( __( 'Coupon removed. Code "%s" is only valid for existing customers.', 'woocommerce-coupon-restrictions' ), $code );

		// Check if order is for returning customer.
		if ( is_user_logged_in() ) {

			// If user is logged in, we can check for paying_customer meta.
			$current_user = wp_get_current_user();
			$customer = new WC_Customer( $current_user->ID );

			if ( ! $customer->get_is_paying_customer() ) {
				self::remove_coupon( $coupon, $code, $msg );
			}

		} else {

			// If user is not logged in, we can check against previous orders.
			$email = strtolower( $_POST['billing_email'] );
			if ( ! self::is_returning_customer( $email ) ) {
				self::remove_coupon( $coupon, $code, $msg );
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
	public static function check_country_restriction_checkout( $coupon, $code ) {

		// Get address lookup for location restrictions.
		$address_for_location_restrictions = $coupon->get_meta( 'address_for_location_restrictions', true );
		if ( ! in_array( $address_for_location_restrictions, array( 'billing', 'shipping' ) ) ) {
			$address_for_location_restrictions = 'shipping';
		}

		// Set $county to empty string as a fallback.
		$country = '';

		// If shipping address is selected, set $country to shipping_country.
		// If shipping address is selected but not present, fallback to billing_country.
		if ( 'shipping' === $address_for_location_restrictions ) {
			if ( isset( $_POST['shipping_country'] ) ) {
				$country = esc_textarea( $_POST['shipping_country'] );
			} elseif ( isset( $_POST['billing_country'] ) ) {
				$country = esc_textarea( $_POST['billing_country'] );
			}
		}

		// If billing address is selected, set $country to billing_country.
		if ( 'billing' === $address_for_location_restrictions && isset( $_POST['billing_country'] ) ) {
			$country = esc_textarea( $_POST['billing_country'] );
		}

		// Get the allowed countries from coupon meta.
		$allowed_countries = $coupon->get_meta( 'country_restriction', true );

		// If the billing/shipping country is not in an allowed country, remove it.
		if ( ! in_array( $country, $allowed_countries ) ) {

			// Allow strings "shipping" and "billing" to be translated.
			$i8n_address_type = __( 'shipping', 'woocommerce-coupon-restrictions' );
			if ( 'billing' === $address_for_location_restrictions ) {
				$i8n_address_type = __( 'billing', 'woocommerce-coupon-restrictions' );
			}

			// Validation message.
			$msg = sprintf(
				__( 'Coupon removed. Code "%s" is not valid in your %s country.', 'woocommerce-coupon-restrictions' ),
				$code,
				$i8n_address_type
			);

			self::remove_coupon( $coupon, $code, $msg );
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

		// Filter to change validation text.
		$msg = apply_filters( 'woocommerce-coupon-restrictions-removed-message-with-code', $msg, $code, $coupon );

		// Remove the coupon.
		WC()->cart->remove_coupon( $code );

		// Throw a notice to stop checkout.
		wc_add_notice( $msg, 'error' );

		// Flag totals for refresh.
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

		// If there is at least one other order by billing e-mail.
		if ( 1 === count( $customer_orders ) ) {
			return true;
		}

		// Otherwise there should not be any orders.
		return false;
	}

}

WC_Coupon_Restrictions_Validation::init();
