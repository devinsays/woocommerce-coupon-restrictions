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
		add_filter( 'woocommerce_coupon_is_valid', __CLASS__ . '::validate_coupons_before_checkout', 10, 2 );

		// Validates coupons again during checkout validation
		add_action( 'woocommerce_after_checkout_validation', __CLASS__ . '::validate_coupons_at_checkout', 1 );

	}

	/**
	 * Validates coupon if customer session data is available.
	 *
	 * @return boolean $valid
	 */
	public static function validate_coupons_before_checkout( $valid, $coupon ) {

		// If coupon already marked invalid, no sense in moving forward.
		if ( ! $valid ) {
			return $valid;
		}

		// Get the customer data from the session.
		$session = WC()->session->get( 'customer' );

		// If session data is not available, the coupon should remain valid.
		// We do additional validation at checkout.
		if ( ! $session ) {
			return $valid;
		}

		// Validate customer restrictions.
		$valid = self::session_validate_customer_restrictions( $coupon, $session );

		// Validate location restrictions.
		$valid = self::session_validate_location_restrictions( $coupon, $session );

		return $valid;
	}

	/**
	 * Validates customer restrictions.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param string $email
	 * @return boolean
	 */
	function session_validate_customer_restrictions( $coupon, $session ) {

		// If email address isn't available, coupon remains valid.
		if ( ! isset( $session['email'] ) ) {
			return true;
		}

		// @TODO Email sanitization.
		$email = strtolower( $session['email'] );

		// Validate new customer restriction.
		if ( false === self::validate_new_customer_restriction( $coupon, $email ) ) {
			add_filter( 'woocommerce_coupon_error', __CLASS__ . '::validation_message_new_customer_restriction', 10, 2 );
			return false;
		}

		// Validate existing customer restriction.
		if ( false === self::validate_existing_customer_restriction( $coupon, $email ) ) {
			add_filter( 'woocommerce_coupon_error', __CLASS__ . '::validation_message_existing_customer_restriction', 10, 2 );
			return false;
		}

		return true;
	}

	/**
	 * Validates new customer restriction.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param string $email
	 * @return boolean
	 */
	function validate_new_customer_restriction( $coupon, $email ) {
		$customer_restriction_type = $coupon->get_meta( 'customer_restriction_type', true );
		if ( 'new' == $customer_restriction_type ) :
			if ( self::is_returning_customer( $email ) ) {
				return false;
			}
		endif;

		return true;
	}

	/**
	 * Validates existing customer restriction.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param string $email
	 * @return boolean
	 */
	function validate_existing_customer_restriction( $coupon, $email ) {
		$customer_restriction_type = $coupon->get_meta( 'customer_restriction_type', true );
		if ( 'existing' == $customer_restriction_type ) :
			if ( self::is_returning_customer( $email ) ) {
				return false;
			}
		endif;

		return true;
	}

	/**
	 * Validates location restrictions.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param string $email
	 * @return boolean
	 */
	function session_validate_location_restrictions( $coupon, $session ) {

		// If location restrictions aren't set, coupon is valid.
		if ( 'yes' != $coupon->get_meta( 'location_restrictions' ) ) {
			return true;
		}

		// Get the address type used for location restrictions (billing or shipping).
		$address = self::get_address_type_for_restriction( $coupon );

		if ( 'shipping' == $address && isset( $session['shipping_country'] ) ) {
			$country_validation = self::validate_country_restriction( $coupon, $session['shipping_country'] );
		}

		if ( 'shipping' == $address && isset( $session['shipping_postcode'] ) ) {
			$zipcode_validation = self::validate_postcode_restriction( $coupon, $session['shipping_postcode'] );
		}

		if ( 'billing' == $address && isset( $session['billing_country'] ) ) {
			$country_validation = self::validate_country_restriction( $coupon, $session['billing_country'] );
		}

		if ( 'billing' == $address && isset( $session['billing_postcode'] ) ) {
			$country_validation = self::validate_postcode_restriction( $coupon, $session['billing_postcode'] );
		}

		if ( false === $country_validation ) {
			add_filter( 'woocommerce_coupon_error', __CLASS__ . '::validation_message_country_restriction', 10, 2 );
		}

		if ( false === $zipcode_validation ) {
			add_filter( 'woocommerce_coupon_error', __CLASS__ . '::validation_message_zipcode_restriction', 10, 2 );
		}

		// Coupon is not valid if country or postcode validation failed.
		if ( false === $country_validation || false === $zipcode_validation ) {
			return false;
		}

		// Coupon passed all validation, return true.
		return true;

	}

	/**
	 * Validates country restriction.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param string $country
	 * @return boolean
	 */
	function validate_country_restriction( $coupon, $country ) {
		// Get the allowed countries from coupon meta.
		$allowed_countries = $coupon->get_meta( 'country_restriction', true );

		if ( ! in_array( $country, $allowed_countries ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validates postcode restriction.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param string $country
	 * @return boolean
	 */
	function validate_postcode_restriction( $coupon, $postcode ) {

		// Get the allowed postcodes from coupon meta.
		$postcode_restriction = $coupon->get_meta( 'postcode_restriction', true );
		$postcode_array = explode( ',', $postcode_restriction );
		$postcode_array = array_map( 'trim', $postcode_array );

		// Converting the string to uppercase so postcode comparison is not case sensitive.
		$postcode_array = array_map( 'strtoupper', $postcode_array );

		if ( ! in_array( strtoupper( $postcode ), $postcode_array ) ) {
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
		$err = self::coupon_error_message( 'new-customer', $err, $err_code );
		return $err;
	}

	/**
	 * Applies existing customer coupon error message.
	 *
	 * @return string $err
	 */
	public static function validation_message_existing_customer_restriction( $err, $err_code ) {
		$err = self::coupon_error_message( 'existing-customer', $err, $err_code );
		return $err;
	}

	/**
	 * Applies country restriction error message.
	 *
	 * @return string $err
	 */
	public static function validation_message_country_restriction( $err, $err_code ) {
		$err = self::coupon_error_message( 'country', $err, $err_code );
		return $err;
	}

	/**
	 * Applies zip code restriction error message.
	 *
	 * @return string $err
	 */
	public static function validation_message_zipcode_restriction($err, $err_code) {
		$err = self::coupon_error_message( 'zipcode', $err, $err_code );
		return $err;
	}

	/**
	 * Validation message helper.
	 *
	 * @return string
	 */
	public static function get_validation_message( $key ) {

		if ( $key === 'new-customer' ) {
			return __( 'Sorry, this coupon is only valid for new customers.', 'woocommerce-coupon-restrictions' );
		}

		if ( $key === 'existing-customer' ) {
			return __( 'Sorry, this coupon is only valid for existing customers.', 'woocommerce-coupon-restrictions' );
		}

		if ( $key === 'existing-customer' ) {
			return __( 'Sorry, this coupon is only valid for existing customers.', 'woocommerce-coupon-restrictions' );
		}

		if ( $key === 'country' ) {
			return __( 'Sorry, this coupon is not valid in your country.', 'woocommerce-coupon-restrictions' );
		}

		if ( $key === 'zipcode' ) {
			return __( 'Sorry, this coupon is not valid in your zip code.', 'woocommerce-coupon-restrictions' );
		}

	}

	/**
	 * Validation message helper.
	 *
	 * @return string
	 */
	public static function coupon_error_message( $key, $err, $err_code ) {

		// Alter the validation message if coupon has been removed.
		if ( 100 === $err_code ) {
			$msg = self::get_validation_message( $key );
			$err = apply_filters( 'woocommerce-coupon-restrictions-removed-message', $msg );
		}

		// Return validation message.
		return $err;

	}

	/**
	 * Additional validation at checkout ensures coupon is valid with $posted checkout data.
	 *
	 * @param array $posted
	 */
	public static function validate_coupons_at_checkout( $posted ) {

		if ( ! empty( WC()->cart->applied_coupons ) ) :
			foreach ( WC()->cart->applied_coupons as $code ) :

				$coupon = new WC_Coupon( $code );

				if ( $coupon->is_valid() ) :
					self::checkout_validate_new_customer_restriction( $coupon, $code, $posted );
					self::checkout_existing_customer_coupon_checkout( $coupon, $code, $posted );
					self::checkout_location_restrictions( $coupon, $code, $posted );
				endif;

			endforeach;
		endif;
	}

	/**
	 * Validates new customer coupon on checkout.
	 *
	 * @param object $coupon
	 * @param string $code
	 * @return void
	 */
	public static function checkout_validate_new_customer_restriction( $coupon, $code ) {

		// Validation message.
		$msg = sprintf( __( 'Sorry, coupon code "%s" is only valid for new customers.', 'woocommerce-coupon-restrictions' ), $code );

		// @TODO Email sanitization.
		$email = strtolower( $posted['billing_email'] );

		$valid = self::validate_new_customer_restriction( $coupon, $email );

		if ( false == $valid ) {
			self::remove_coupon( $coupon, $code, $msg );
		}

	}

	/**
	 * Validates existing customer coupon on checkout.
	 *
	 * @param object $coupon
	 * @param string $code
	 * @return void
	 */
	public static function checkout_validate_existing_customer_restriction( $coupon, $code ) {

		// Validation message.
		$msg = sprintf( __( 'Sorry, coupon code "%s" is only valid for existing customers.', 'woocommerce-coupon-restrictions' ), $code );

		// @TODO Email sanitization.
		$email = strtolower( $posted['billing_email'] );

		$valid = self::validate_existing_customer_restriction( $coupon, $email );

		if ( false == $valid ) {
			self::remove_coupon( $coupon, $code, $msg );
		}

	}

	/**
	 * Validates existing customer coupon on checkout.
	 *
	 * @param object $coupon
	 * @param string $code
	 * @return void
	 */
	public static function checkout_location_restrictions( $coupon, $code ) {

		// If location restrictions aren't set, return.
		if ( 'yes' != $coupon->get_meta( 'location_restrictions' ) ) {
			return;
		}

		// Check country restrictions.
		$country_restriction = $coupon->get_meta( 'country_restriction', true );
		if ( ! empty( $country_restriction ) ) {
			self::check_country_restriction_checkout( $coupon, $code );
		}

		// Check postcode restrictions.
		$postcode_restriction = $coupon->get_meta( 'postcode_restriction', true );
		if ( ! empty( $postcode_restriction ) ) {
			self::check_postcode_restriction_checkout( $coupon, $code );
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
		$address_for_location_restrictions = self::get_address_type_for_restriction( $coupon );

		// Gets customer country.
		if ( 'shipping' === $address_for_location_restrictions && isset( $_POST['shipping_country'] ) ) {
			// If shipping country is selected and set, use it.
			$country = esc_textarea( $_POST['shipping_country'] );
		} elseif ( isset( $_POST['billing_country'] ) ) {
			// Otherwise if a billing country is set, let's use that.
			$country = esc_textarea( $_POST['billing_country'] );
		} else {
			// If no customer data is set, we'll use a blank string as a fallback.
			$country = '';
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
				__( 'Sorry, coupon code "%s" is not valid in your %s country.', 'woocommerce-coupon-restrictions' ),
				$code,
				$i8n_address_type
			);

			self::remove_coupon( $coupon, $code, $msg );
		}

	}

	/**
	 * Validates postcode restrictions on checkout.
	 *
	 * @param object $coupon
	 * @param string $code
	 * @return void
	 */
	public static function check_postcode_restriction_checkout( $coupon, $code ) {

		// Get address lookup for location restrictions.
		$address_for_location_restrictions = self::get_address_type_for_restriction( $coupon );

		// Gets customer postcode.
		if ( 'shipping' === $address_for_location_restrictions && isset( $_POST['shipping_postcode'] ) ) {
			// If shipping postcode is selected and set, use it.
			$postcode = esc_textarea( $_POST['shipping_postcode'] );
		} elseif ( isset( $_POST['billing_postcode'] ) ) {
			// Otherwise if a billing postcode is set, let's use that.
			$postcode = esc_textarea( $_POST['billing_postcode'] );
		} else {
			// If no customer data is set, we'll use a blank string as a fallback.
			$postcode = '';
		}

		// Get the allowed postcode from coupon meta.
		$postcode_restriction = $coupon->get_meta( 'postcode_restriction', true );
		$postcode_array = explode( ',', $postcode_restriction );
		$postcode_array = array_map( 'trim', $postcode_array );

		// Converting the string uppercase so postcode comparison is not case sensitive.
		$postcode_array = array_map( 'strtoupper', $postcode_array );

		if ( ! in_array( strtoupper( $postcode ), $postcode_array ) ) {
			// Allow strings "shipping" and "billing" to be translated.
			$i8n_address_type = __( 'shipping', 'woocommerce-coupon-restrictions' );
			if ( 'billing' === $address_for_location_restrictions ) {
				$i8n_address_type = __( 'billing', 'woocommerce-coupon-restrictions' );
			}

			// Validation message.
			$msg = sprintf(
				__( 'Sorry, coupon code "%s" is not valid in your %s zip code.', 'woocommerce-coupon-restrictions' ),
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
	 * @param string $msg
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

		// Checks if there is an account associated with the $email.
		$user = get_user_by( 'email', $email );

		// If there is a user account, we can check if customer is_paying_customer.
		if ( $user ) :
			$customer = new WC_Customer( $user->ID );
			if ( $customer->get_is_paying_customer() ) {
				return true;
			}
		endif;

		// If there isn't a user account, we can check against orders.
		$customer_orders = wc_get_orders( array(
			'status' => array( 'wc-processing', 'wc-completed' ),
			'email'  => $email,
			'limit'  => 1
		) );

		// If there is at least one order, customer is returning.
		if ( 1 === count( $customer_orders ) ) {
			return true;
		}

		// If we've gotten to this point, the customer must be new.
		return false;
	}

	/**
	 * Returns whether coupon address restriction applies to 'shipping' or 'billing'.
	 *
	 * @param object $coupon
	 * @return string
	 */
	public static function get_address_type_for_restriction( $coupon ) {
		$address_type = $coupon->get_meta( 'address_for_location_restrictions', true );
		if ( ! in_array( $address_type, array( 'billing', 'shipping' ) ) ) {
			return 'shipping';
		}
		return $address_type;
	}

}

WC_Coupon_Restrictions_Validation::init();
