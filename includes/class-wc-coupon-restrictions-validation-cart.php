<?php
/**
 * WooCommerce Coupon Restrictions - Validation Cart.
 *
 * @package  WooCommerce Coupon Restrictions
 * @since    2.0.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Coupon_Restrictions_Validation_Cart {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Validates coupons before checkout if customer session exists.
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupons_before_checkout' ), 10, 2 );
	}

	/**
	 * Validates coupon if customer session data is available.
	 *
	 * @param boolean $valid
	 * @param WC_Coupon $coupon
	 * @return boolean
	 */
	public function validate_coupons_before_checkout( $valid, $coupon ) {
		// If checkout validation is running, we skip the before checkout validation.
		if ( defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			return true;
		}

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

		if ( $email ) {
			// Validate customer restrictions.
			$customer = $this->validate_customer_restrictions( $coupon, $email );
			if ( false === $customer ) {
				return false;
			}

			// Validate role restrictions.
			$role = WC_Coupon_Restrictions_Validation::role_restriction( $coupon, $email );
			if ( false === $role ) {
				add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_role_restriction' ), 10, 3 );
				return false;
			}
		}

		// Validate location restrictions.
		$location = $this->validate_location_restrictions( $coupon, $session );
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
	 * @param WC_Coupon $coupon
	 * @param string $email
	 * @return boolean
	 */
	public function validate_customer_restrictions( $coupon, $email ) {
		// Validate new customer restriction.
		if ( false === WC_Coupon_Restrictions_Validation::new_customer_restriction( $coupon, $email ) ) {
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_new_customer_restriction' ), 10, 3 );
			return false;
		}

		// Validate existing customer restriction.
		if ( false === WC_Coupon_Restrictions_Validation::existing_customer_restriction( $coupon, $email ) ) {
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_existing_customer_restriction' ), 10, 3 );
			return false;
		}

		return true;
	}

	/**
	 * Validates location restrictions.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param WC_Coupon $coupon
	 * @param object $session
	 * @return boolean
	 */
	public function validate_location_restrictions( $coupon, $session ) {
		// If location restrictions aren't set, coupon is valid.
		if ( 'yes' !== $coupon->get_meta( 'location_restrictions' ) ) {
			return true;
		}

		// Defaults in case no conditions are met.
		$country_validation = true;
		$state_validation   = true;
		$zipcode_validation = true;

		// Get the address type used for location restrictions (billing or shipping).
		$address = WC_Coupon_Restrictions_Validation::get_address_type_for_restriction( $coupon );

		if ( 'shipping' === $address && isset( $session['shipping_country'] ) ) {
			$country = esc_textarea( $session['shipping_country'] );
			if ( '' !== $country ) {
				$country_validation = WC_Coupon_Restrictions_Validation::country_restriction( $coupon, $country );
			}
		}

		if ( 'shipping' === $address && isset( $session['shipping_state'] ) ) {
			$state = esc_textarea( $session['shipping_state'] );
			if ( '' !== $state ) {
				$state_validation = WC_Coupon_Restrictions_Validation::state_restriction( $coupon, $state );
			}
		}

		if ( 'shipping' === $address && isset( $session['shipping_postcode'] ) ) {
			$zipcode = esc_textarea( $session['shipping_postcode'] );
			if ( '' !== $zipcode ) {
				$zipcode_validation = WC_Coupon_Restrictions_Validation::postcode_restriction( $coupon, $zipcode );
			}
		}

		if ( 'billing' === $address && isset( $session['country'] ) ) {
			$country = esc_textarea( $session['country'] );
			if ( '' !== $country ) {
				$country_validation = WC_Coupon_Restrictions_Validation::country_restriction( $coupon, $country );
			}
		}

		if ( 'billing' === $address && isset( $session['postcode'] ) ) {
			$state = esc_textarea( $session['state'] );
			if ( '' !== $state ) {
				$state_validation = WC_Coupon_Restrictions_Validation::state_restriction( $coupon, $state );
			}
		}

		if ( 'billing' === $address && isset( $session['postcode'] ) ) {
			$zipcode = esc_textarea( $session['postcode'] );
			if ( '' !== $zipcode ) {
				$zipcode_validation = WC_Coupon_Restrictions_Validation::postcode_restriction( $coupon, $zipcode );
			}
		}

		if ( false === $country_validation ) {
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_country_restriction' ), 10, 3 );
		}

		if ( false === $state_validation ) {
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_state_restriction' ), 10, 3 );
		}

		if ( false === $zipcode_validation ) {
			add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_zipcode_restriction' ), 10, 3 );
		}

		// Coupon is not valid if country, state or zipcode validation failed.
		if ( in_array( false, array( $country_validation, $state_validation, $zipcode_validation ) ) ) {
			return false;
		}

		// Coupon passed all validation, return true.
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
		return $this->coupon_error_message( 'existing-customer', $err, $err_code, $coupon );
	}

	/**
	 * Applies role restriction coupon error message.
	 *
	 * @return string $err
	 */
	public function validation_message_role_restriction( $err, $err_code, $coupon ) {
		return $this->coupon_error_message( 'role-restriction', $err, $err_code, $coupon );
	}

	/**
	 * Applies country restriction error message.
	 *
	 * @return string $err
	 */
	public function validation_message_country_restriction( $err, $err_code, $coupon ) {
		return $this->coupon_error_message( 'country', $err, $err_code, $coupon );
	}

	/**
	 * Applies state code restriction error message.
	 *
	 * @return string $err
	 */
	public function validation_message_state_restriction( $err, $err_code, $coupon ) {
		return $this->coupon_error_message( 'state', $err, $err_code, $coupon );
	}

	/**
	 * Applies zip code restriction error message.
	 *
	 * @return string $err
	 */
	public function validation_message_zipcode_restriction( $err, $err_code, $coupon ) {
		return $this->coupon_error_message( 'zipcode', $err, $err_code, $coupon );
	}

	/**
	 * Validation message helper.
	 *
	 * @param string $key
	 * @param string $err
	 * @param int $err_code
	 * @param WC_Coupon $coupon
	 * @return string
	 */
	public function coupon_error_message( $key, $err, $err_code, $coupon ) {
		// Alter the validation message if coupon has been removed.
		if ( 100 === $err_code ) {
			$msg = WC_Coupon_Restrictions_Validation::message( $key, $coupon );

			// This filter is being deprecated in order to to use snake case convention.
			// Please use the updated `woocommerce_coupon_restrictions_removed_message` filter.
			$err = apply_filters( 'woocommerce-coupon-restrictions-removed-message', $msg );

			if ( has_filter( 'woocommerce-coupon-restrictions-removed-message' ) ) {
				_deprecated_hook(
					'woocommerce-coupon-restrictions-removed-message',
					'2.3.0',
					'woocommerce_coupon_restrictions_removed_message',
					'Use new_filter_name instead.'
				);
			}

			$err = apply_filters( 'woocommerce_coupon_restrictions_removed_message', $msg );
		}

		// Return validation message.
		return $err;
	}
}
