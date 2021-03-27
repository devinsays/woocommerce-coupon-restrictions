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
	* Init the class.
	*/
	public function init() {

		// Validates coupons before checkout if customer session exists.
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupons_before_checkout'), 10, 2 );

		// Validates coupons again during checkout validation.
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_coupons_after_checkout'), 1 );

	}

	/**
	 * Validates coupon if customer session data is available.
	 *
	 * @param boolean $valid
	 * @param object $coupon
	 * @return boolean
	 */
	public function validate_coupons_before_checkout( $valid, $coupon ) {

		// Pre-checkout validation can be disabled using this filter.
		$validate = apply_filters( 'woocommerce_coupon_restrictions_validate_before_checkout', true );
		if ( false === $validate ) {
			return true;
		}

		// If coupon is already marked invalid, no need for further validation.
		if ( ! $valid ) {
			return false;
		}

		// During subscription renewals there may not be a valid session.
		// If so, we'll do validation at checkout instead.
		if ( ! WC()->session ) {
			return true;
		}

		// Get customer session information.
		$session = WC()->session->get( 'customer' );

		// Customer information may not be available yet when coupon is applied.
		// If so, coupon will remain activate and we'll validate at checkout.
		if ( ! $session ) {
			return true;
		}

		// Gets the email if it is in the session and valid.
		$email = $this->get_email_from_session( $session );

		if ( $email ) :
			// Validate customer restrictions.
			$customer = $this->session_validate_customer_restrictions( $coupon, $email );
			if ( false === $customer ) {
				return false;
			}

			// Validate role restrictions.
			$role = $this->validate_role_restriction( $coupon, $email );
			if ( false === $role ) {
				add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_role_restriction' ), 10, 3 );
				return false;
			}
		endif;

		// Validate location restrictions.
		$location = $this->session_validate_location_restrictions( $coupon, $session );
		if ( false === $location ) {
			return false;
		}

		return true;
	}

	/**
	 * Gets the user email from session.
	 *
	 * @param object $session
	 * @return string | null
	 */
	public function get_email_from_session( $session ) {
		if ( ! isset( $session['email'] ) ) {
			return null;
		}

		$email = esc_textarea( strtolower( $session['email'] ) );

		if ( ! is_email( $email ) ) {
			return null;
		}

		return $email;
	}

	/**
	 * Validates customer restrictions.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param object $coupon
	 * @param string $email
	 * @return boolean
	 */
	public function session_validate_customer_restrictions( $coupon, $email ) {

		// Validate new customer restriction.
		if ( false === $this->validate_new_customer_restriction( $coupon, $email ) ) {
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_new_customer_restriction'), 10, 3 );
			return false;
		}

		// Validate existing customer restriction.
		if ( false === $this->validate_existing_customer_restriction( $coupon, $email ) ) {
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_existing_customer_restriction' ), 10, 3 );
			return false;
		}

		return true;
	}

	/**
	 * Validates new customer restriction.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param object $coupon
	 * @param string $email
	 * @return boolean
	 */
	public function validate_new_customer_restriction( $coupon, $email ) {

		$customer_restriction_type = $coupon->get_meta( 'customer_restriction_type', true );

		if ( 'new' === $customer_restriction_type ) :
			// If customer has purchases, coupon is not valid.
			if ( $this->is_returning_customer( $email ) ) {
				return false;
			}
		endif;

		return true;
	}

	/**
	 * Validates existing customer restriction.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param object $coupon
	 * @param string $email
	 * @return boolean
	 */
	public function validate_existing_customer_restriction( $coupon, $email ) {

		$customer_restriction_type = $coupon->get_meta( 'customer_restriction_type', true );

		// If customer has purchases, coupon is valid.
		if ( 'existing' === $customer_restriction_type ) :
			if ( ! $this->is_returning_customer( $email ) ) {
				return false;
			}
		endif;

		return true;
	}

	/**
	 * Validates location restrictions.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param object $coupon
	 * @param object $session
	 * @return boolean
	 */
	public function session_validate_location_restrictions( $coupon, $session ) {

		// If location restrictions aren't set, coupon is valid.
		if ( 'yes' !== $coupon->get_meta( 'location_restrictions' ) ) {
			return true;
		}

		// Defaults in case no conditions are met.
		$country_validation = true;
		$state_validation = true;
		$zipcode_validation = true;

		// Get the address type used for location restrictions (billing or shipping).
		$address = $this->get_address_type_for_restriction( $coupon );

		if ( 'shipping' === $address && isset( $session['shipping_country'] ) ) {
			$country = esc_textarea( $session['shipping_country'] );
			if ( '' !== $country ) {
				$country_validation = $this->validate_country_restriction( $coupon, $country );
			}
		}

		if ( 'shipping' === $address && isset( $session['shipping_state'] ) ) {
			$state = esc_textarea( $session['shipping_state'] );
			if ( '' !== $state ) {
				$state_validation = $this->validate_state_restriction( $coupon, $state );
			}
		}

		if ( 'shipping' === $address && isset( $session['shipping_postcode'] ) ) {
			$zipcode = esc_textarea( $session['shipping_postcode'] );
			if ( '' !== $zipcode ) {
				$zipcode_validation = $this->validate_postcode_restriction( $coupon, $zipcode );
			}
		}

		if ( 'billing' === $address && isset( $session['country'] ) ) {
			$country = esc_textarea( $session['country'] );
			if ( '' !== $country ) {
				$country_validation = $this->validate_country_restriction( $coupon, $country );
			}
		}

		if ( 'billing' === $address && isset( $session['postcode'] ) ) {
			$state = esc_textarea( $session['state'] );
			if ( '' !== $state ) {
				$state_validation = $this->validate_state_restriction( $coupon, $state );
			}
		}

		if ( 'billing' === $address && isset( $session['postcode'] ) ) {
			$zipcode = esc_textarea( $session['postcode'] );
			if ( '' !== $zipcode ) {
				$zipcode_validation = $this->validate_postcode_restriction( $coupon, $zipcode );
			}
		}

		if ( false === $country_validation ) {
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_country_restriction' ) , 10, 3 );
		}

		if ( false === $state_validation ) {
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_state_restriction' ), 10, 3 );
		}

		if ( false === $zipcode_validation ) {
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_zipcode_restriction' ), 10, 3 );
		}

		// Coupon is not valid if country, state or zipcode validation failed.
		if ( in_array( false, [$country_validation, $state_validation, $zipcode_validation] ) ) {
			return false;
		}

		// Coupon passed all validation, return true.
		return true;
	}

	/**
	 * Validates country restriction.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param object $coupon
	 * @param string $country
	 * @return boolean
	 */
	public function validate_country_restriction( $coupon, $country ) {

		// Get the allowed countries from coupon meta.
		$allowed_countries = $coupon->get_meta( 'country_restriction', true );

		// If $allowed_countries has not been set, coupon remains valid.
		if ( ! $allowed_countries ) {
			return true;
		}

		// If the customer country is not in allowed countries, return false.
		if ( ! in_array( $country, $allowed_countries ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validates state restriction.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param object $coupon
	 * @param string $state
	 * @return boolean
	 */
	public function validate_state_restriction( $coupon, $state ) {

		// Get the allowed states from coupon meta.
		$state_restriction = $coupon->get_meta( 'state_restriction', true );

		// If $state_restriction has not been set, coupon remains valid.
		if ( ! $state_restriction ) {
			return true;
		}

		$state_array = $this->comma_seperated_string_to_array( $state_restriction );

		if ( ! in_array( strtoupper( $state ), $state_array ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validates postcode restriction.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param object $coupon
	 * @param string $postcode
	 * @return boolean
	 */
	public function validate_postcode_restriction( $coupon, $postcode ) {

		// Get the allowed postcodes from coupon meta.
		$postcode_restriction = $coupon->get_meta( 'postcode_restriction', true );

		// If $postcode_restriction has not been set, coupon remains valid.
		if ( ! $postcode_restriction ) {
			return true;
		}

		$postcode_array = $this->comma_seperated_string_to_array( $postcode_restriction );

		// Wildcard check.
		if ( strpos( $postcode_restriction, '*') !== false ) {
			foreach ( $postcode_array as $restricted_postcode ) {
				if ( strpos( $restricted_postcode, '*') !== false ) {
					if ( fnmatch( $restricted_postcode, $postcode ) ) {
						return true;
					}
				}
			}
		}

		// Standard check.
		if ( ! in_array( strtoupper( $postcode ), $postcode_array ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Convert string textarea to normalized array with uppercase.
	 *
	 * @param string $string
	 * @return array $values
	 */
	public function comma_seperated_string_to_array( $string ) {
		// Converts string to array.
		$values = explode( ',', $string );
		$values = array_map( 'trim', $values );

		// Converts values to uppercase so comparison is not case sensitive.
		$values = array_map( 'strtoupper', $values );

		return $values;
	}

	/**
	 * Validates role restrictions.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param object $coupon
	 * @param string $email
	 * @return boolean
	 */
	public function validate_role_restriction( $coupon, $email ) {

		// Returns an array with all the restricted roles.
		$restricted_roles = $coupon->get_meta( 'role_restriction', true );

		// If there are no restricted roles, coupon is valid.
		if ( ! $restricted_roles ) {
			return true;
		}

		// Checks if there is an account associated with the $email.
		$user = get_user_by( 'email', $email );

		// If user account does not exist and guest role is permitted, return true.
		if ( ! $user && in_array( 'woocommerce-coupon-restrictions-guest', $restricted_roles ) ) {
			return true;
		}

		// If user account does not exist and guest role not permitted, coupon is invalid.
		if ( ! $user ) {
			return false;
		}

		$user_meta = get_userdata( $user->ID );
		$user_roles = $user_meta->roles;

		// If any the user roles do not match the restricted roles, coupon is invalid.
		if ( ! array_intersect( $user_roles, $restricted_roles ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Applies new customer coupon error message.
	 *
	 * @return string $err
	 */
	public function validation_message_new_customer_restriction( $err, $err_code, $coupon ) {
		$err = $this->coupon_error_message( 'new-customer', $err, $err_code, $coupon );
		return $err;
	}

	/**
	 * Applies existing customer coupon error message.
	 *
	 * @return string $err
	 */
	public function validation_message_existing_customer_restriction( $err, $err_code, $coupon ) {
		$err = $this->coupon_error_message( 'existing-customer', $err, $err_code, $coupon );
		return $err;
	}

	/**
	 * Applies role restriction coupon error message.
	 *
	 * @return string $err
	 */
	public function validation_message_role_restriction( $err, $err_code, $coupon ) {
		$err = $this->coupon_error_message( 'role-restriction', $err, $err_code, $coupon );
		return $err;
	}

	/**
	 * Applies country restriction error message.
	 *
	 * @return string $err
	 */
	public function validation_message_country_restriction( $err, $err_code, $coupon ) {
		$err = $this->coupon_error_message( 'country', $err, $err_code, $coupon );
		return $err;
	}

	/**
	 * Applies state code restriction error message.
	 *
	 * @return string $err
	 */
	public function validation_message_state_restriction($err, $err_code, $coupon ) {
		$err = $this->coupon_error_message( 'state', $err, $err_code, $coupon );
		return $err;
	}

	/**
	 * Applies zip code restriction error message.
	 *
	 * @return string $err
	 */
	public function validation_message_zipcode_restriction($err, $err_code, $coupon ) {
		$err = $this->coupon_error_message( 'zipcode', $err, $err_code, $coupon );
		return $err;
	}

	/**
	 * Validation message helper.
	 *
	 * @return string
	 */
	public function coupon_error_message( $key, $err, $err_code, $coupon ) {

		// Alter the validation message if coupon has been removed.
		if ( 100 === $err_code ) {
			$msg = $this->get_validation_message( $key, $coupon );
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
	public function validate_coupons_after_checkout( $posted ) {

		if ( ! empty( WC()->cart->applied_coupons ) ) :

			// If no billing email is set, we'll default to empty string.
			// WooCommerce validation should catch this before we do.
			if ( ! isset( $posted['billing_email'] ) ) {
				$posted['billing_email'] = '';
			}

			foreach ( WC()->cart->applied_coupons as $code ) :

				$coupon = new WC_Coupon( $code );

				if ( $coupon->is_valid() ) :
					$this->checkout_validate_new_customer_restriction( $coupon, $code, $posted );
					$this->checkout_validate_existing_customer_restriction( $coupon, $code, $posted );
					$this->checkout_validate_location_restrictions( $coupon, $code, $posted );
					$this->checkout_validate_role_restriction( $coupon, $code, $posted );
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
	public function checkout_validate_new_customer_restriction( $coupon, $code, $posted ) {

		$email = strtolower( $posted['billing_email'] );
		$valid = $this->validate_new_customer_restriction( $coupon, $email );

		if ( false === $valid ) {
			$msg = $this->get_validation_message( 'new-customer', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
		}

	}

	/**
	 * Validates existing customer coupon on checkout.
	 *
	 * @param object $coupon
	 * @param string $code
	 * @param array $posted
	 * @return void
	 */
	public function checkout_validate_existing_customer_restriction( $coupon, $code, $posted ) {

		$email = strtolower( $posted['billing_email'] );
		$valid = $this->validate_existing_customer_restriction( $coupon, $email );

		if ( false === $valid ) {
			$msg = $this->get_validation_message( 'existing-customer', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
		}

	}

	/**
	 * Validates new customer coupon on checkout.
	 *
	 * @param object $coupon
	 * @param string $code
	 * @return void
	 */
	public function checkout_validate_role_restriction( $coupon, $code, $posted ) {

		$email = strtolower( $posted['billing_email'] );
		$valid = $this->validate_role_restriction( $coupon, $email );

		if ( false === $valid ) {
			$msg = $this->get_validation_message( 'role-restriction', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
		}

	}

	/**
	 * Validates location restrictions.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param object $coupon
	 * @param string $code
	 * @param array $posted
	 * @return void
	 */
	public function checkout_validate_location_restrictions( $coupon, $code, $posted ) {

		// If location restrictions aren't set, coupon is valid.
		if ( 'yes' !== $coupon->get_meta( 'location_restrictions' ) ) {
			return true;
		}

		// Get the address type used for location restrictions (billing or shipping).
		$address = $this->get_address_type_for_restriction( $coupon );

		// Defaults in case no conditions are met.
		$country_validation = true;
		$state_validation = true;
		$zipcode_validation = true;

		if ( 'shipping' === $address && isset( $posted['shipping_country'] ) ) {
			$country_validation = $this->validate_country_restriction( $coupon, $posted['shipping_country'] );
		} else

		if ( 'shipping' === $address && isset( $posted['shipping_state'] ) ) {
			$state_validation = $this->validate_state_restriction( $coupon, $posted['shipping_state'] );
		}

		if ( 'shipping' === $address && isset( $posted['shipping_postcode'] ) ) {
			$zipcode_validation = $this->validate_postcode_restriction( $coupon, $posted['shipping_postcode'] );
		}

		if ( 'billing' === $address && isset( $posted['billing_country'] ) ) {
			$country_validation = $this->validate_country_restriction( $coupon, $posted['billing_country'] );
		}

		if ( 'billing' === $address && isset( $posted['billing_state'] ) ) {
			$state_validation = $this->validate_state_restriction( $coupon, $posted['billing_state'] );
		}

		if ( 'billing' === $address && isset( $posted['billing_postcode'] ) ) {
			$zipcode_validation = $this->validate_postcode_restriction( $coupon, $posted['billing_postcode'] );
		}

		if ( false === $country_validation ) {
			$msg = $this->get_validation_message( 'country', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
		}

		if ( false === $state_validation ) {
			$msg = $this->get_validation_message( 'state', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
		}

		if ( false === $zipcode_validation ) {
			$msg = $this->get_validation_message( 'zipcode', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
		}

	}

	/**
	 * Validation message helper.
	 *
	 * @param string $key
	 * @param object $coupon
	 * @return string
	 */
	public function get_validation_message( $key, $coupon ) {

		$i8n_address = array(
			'shipping' => __( 'shipping', 'woocommerce-coupon-restrictions' ),
			'billing' => __( 'billing', 'woocommerce-coupon-restrictions' )
		);

		if ( $key === 'new-customer' ) {
			return sprintf( __( 'Sorry, coupon code "%s" is only valid for new customers.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
		}

		if ( $key === 'existing-customer' ) {
			return sprintf( __( 'Sorry, coupon code "%s" is only valid for existing customers.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
		}

		if ( $key === 'role-restriction' ) {
			return sprintf( __( 'Sorry, coupon code "%s" is not valid with your customer role.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
		}

		if ( $key === 'country' ) {
			$address_type = $this->get_address_type_for_restriction( $coupon );
			$i8n_address_type = $i8n_address[$address_type];
			return sprintf( __( 'Sorry, coupon code "%s" is not valid in your %s country.', 'woocommerce-coupon-restrictions' ), $coupon->get_code(), $i8n_address_type );
		}

		if ( $key === 'state' ) {
			$address_type = $this->get_address_type_for_restriction( $coupon );
			$i8n_address_type = $i8n_address[$address_type];
			return sprintf( __( 'Sorry, coupon code "%s" is not valid in your %s state.', 'woocommerce-coupon-restrictions' ), $coupon->get_code(), $i8n_address_type );
		}

		if ( $key === 'zipcode' ) {
			$address_type = $this->get_address_type_for_restriction( $coupon );
			$i8n_address_type = $i8n_address[$address_type];
			return sprintf( __( 'Sorry, coupon code "%s" is not valid in your %s zip code.', 'woocommerce-coupon-restrictions' ), $coupon->get_code(), $i8n_address_type );
		}

		// The $key should always find a match.
		// But we'll return a default message just in case.
		return sprintf( __( 'Sorry, coupon code "%s" is not valid.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
	}

	/**
	 * Returns whether coupon address restriction applies to 'shipping' or 'billing'.
	 *
	 * @param object $coupon
	 * @return string
	 */
	public function get_address_type_for_restriction( $coupon ) {
		$address_type = $coupon->get_meta( 'address_for_location_restrictions', true );
		if ( 'billing' === $address_type ) {
			return 'billing';
		}

		return 'shipping';
	}

	/**
	 * Removes coupon and displays validation message.
	 *
	 * @param object $coupon
	 * @param string $code
	 * @param string $msg
	 * @return void
	 */
	public function remove_coupon( $coupon, $code, $msg ) {

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
	public function is_returning_customer( $email ) {

		// Checks if there is an account associated with the $email.
		$user = get_user_by( 'email', $email );

		// If there is a user account, we can check if customer is_paying_customer.
		if ( $user ) :
			$customer = new WC_Customer( $user->ID );
			if ( $customer->get_is_paying_customer() ) {
				return true;
			}
		endif;

		// If there isn't a user account or user account ! is_paying_customer
		// we can check against previous guest orders.
		// Store admin must opt-in to this because of performance concerns.
		$option = get_option( 'coupon_restrictions_customer_query', 'accounts' );
		if ( 'accounts-orders' === $option ) {

			// This query can be slow on sites with a lot of orders.
			// @todo Check if 'customer' => '' improves performance.
			$customer_orders = wc_get_orders( array(
				'status' => array( 'wc-processing', 'wc-completed' ),
				'email'  => $email,
				'limit'  => 1,
				'return' => 'ids',
			) );

			// If there is at least one order, customer is returning.
			if ( 1 === count( $customer_orders ) ) {
				return true;
			}

		}

		// If we've gotten to this point, the customer must be new.
		return false;
	}

}
