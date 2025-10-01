<?php
/**
 * WooCommerce Coupon Restrictions - Validation Checkout.
 *
 * @package  WooCommerce Coupon Restrictions
 * @since    2.0.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Coupon_Restrictions_Validation_Checkout {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Validates coupons again during checkout validation.
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_coupons_after_checkout' ), 1 );
	}

	/**
	 * Additional validation at checkout ensures coupon is valid with $posted checkout data.
	 *
	 * @param array $posted
	 * @return void
	 */
	public function validate_coupons_after_checkout( $posted ) {
		if ( empty( WC()->cart->applied_coupons ) ) {
			return;
		}

		// If no billing email is set, we'll default to empty string.
		// WooCommerce validation should catch this before we do.
		if ( ! isset( $posted['billing_email'] ) ) {
			$posted['billing_email'] = '';
		}

		foreach ( WC()->cart->applied_coupons as $code ) {
			$coupon = new WC_Coupon( $code );

			$discounts = new WC_Discounts( WC()->cart );
			if ( ! wc_coupons_enabled() || ! $discounts->is_coupon_valid( $coupon ) ) {
				continue;
			}

			$this->validate_new_customer_restriction( $coupon, $code, $posted );
			$this->validate_existing_customer_restriction( $coupon, $code, $posted );
			$this->validate_location_restrictions( $coupon, $code, $posted );
			$this->validate_role_restriction( $coupon, $code, $posted );

			if ( WC_Coupon_Restrictions_Validation::has_enhanced_usage_restrictions( $coupon ) ) {
				// Default behavior is to return a generic "usage limit exceeded" message if any of the enhanced restrictions fail.
				// Since the message is the same for each validation, we can return as soon as one of them fails.
				// However, if this default is filtered, then we won't return early so that each unique validation message will display.
				$combine_enhanced_restriction_validation = apply_filters( 'wcr_combine_enhanced_restrictions_validation', true );
				$enhanced_restriction_validates          = true;

				$enhanced_restriction_validates = $this->validate_similar_emails_restriction( $coupon, $code, $posted );
				if ( false === $enhanced_restriction_validates && $combine_enhanced_restriction_validation ) {
					continue;
				}

				$enhanced_restriction_validates = $this->validate_usage_limit_per_shipping_address( $coupon, $code, $posted );
				if ( false === $enhanced_restriction_validates && $combine_enhanced_restriction_validation ) {
					continue;
				}

				$this->validate_usage_limit_per_ip( $coupon, $code );
			}
		}
	}

	/**
	 * Validates new customer coupon on checkout.
	 *
	 * @param WC_Coupon $coupon
	 * @param string $code
	 * @param array $posted
	 * @return void
	 */
	public function validate_new_customer_restriction( $coupon, $code, $posted ) {
		$email = strtolower( $posted['billing_email'] );
		$valid = WC_Coupon_Restrictions_Validation::new_customer_restriction( $coupon, $email );

		if ( false === $valid ) {
			$msg = WC_Coupon_Restrictions_Validation::message( 'new-customer', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
		}
	}

	/**
	 * Validates existing customer coupon on checkout.
	 *
	 * @param WC_Coupon $coupon
	 * @param string $code
	 * @param array $posted
	 * @return void
	 */
	public function validate_existing_customer_restriction( $coupon, $code, $posted ) {
		$email = strtolower( $posted['billing_email'] );
		$valid = WC_Coupon_Restrictions_Validation::existing_customer_restriction( $coupon, $email );

		if ( false === $valid ) {
			$msg = WC_Coupon_Restrictions_Validation::message( 'existing-customer', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
		}
	}

	/**
	 * Validates role restriction on checkout.
	 *
	 * @param WC_Coupon $coupon
	 * @param string $code
	 * @param array $posted
	 * @return void
	 */
	public function validate_role_restriction( $coupon, $code, $posted ) {
		$email = strtolower( $posted['billing_email'] );
		$valid = WC_Coupon_Restrictions_Validation::role_restriction( $coupon, $email );

		if ( false === $valid ) {
			$msg = WC_Coupon_Restrictions_Validation::message( 'role-restriction', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
		}
	}

	/**
	 * Validates location restrictions.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param WC_Coupon $coupon
	 * @param string $code
	 * @param array $posted
	 * @return void
	 */
	public function validate_location_restrictions( $coupon, $code, $posted ) {
		// If location restrictions aren't set, coupon is valid.
		if ( 'yes' !== $coupon->get_meta( 'location_restrictions' ) ) {
			return true;
		}

		// Get the address type used for location restrictions (billing or shipping).
		$address = WC_Coupon_Restrictions_Validation::get_address_type_for_restriction( $coupon );

		// Defaults in case no conditions are met.
		$country_validation = true;
		$state_validation   = true;
		$zipcode_validation = true;

		if ( 'shipping' === $address && isset( $posted['shipping_country'] ) ) {
			$country_validation = WC_Coupon_Restrictions_Validation::country_restriction( $coupon, $posted['shipping_country'] );
		} elseif ( 'shipping' === $address && isset( $posted['shipping_state'] ) ) {
			$state_validation = WC_Coupon_Restrictions_Validation::state_restriction( $coupon, $posted['shipping_state'] );
		}

		if ( 'shipping' === $address && isset( $posted['shipping_postcode'] ) ) {
			$zipcode_validation = WC_Coupon_Restrictions_Validation::postcode_restriction( $coupon, $posted['shipping_postcode'] );
		}

		if ( 'billing' === $address && isset( $posted['billing_country'] ) ) {
			$country_validation = WC_Coupon_Restrictions_Validation::country_restriction( $coupon, $posted['billing_country'] );
		}

		if ( 'billing' === $address && isset( $posted['billing_state'] ) ) {
			$state_validation = WC_Coupon_Restrictions_Validation::state_restriction( $coupon, $posted['billing_state'] );
		}

		if ( 'billing' === $address && isset( $posted['billing_postcode'] ) ) {
			$zipcode_validation = WC_Coupon_Restrictions_Validation::postcode_restriction( $coupon, $posted['billing_postcode'] );
		}

		if ( false === $country_validation ) {
			$msg = WC_Coupon_Restrictions_Validation::message( 'country', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
		}

		if ( false === $state_validation ) {
			$msg = WC_Coupon_Restrictions_Validation::message( 'state', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
		}

		if ( false === $zipcode_validation ) {
			$msg = WC_Coupon_Restrictions_Validation::message( 'zipcode', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
		}
	}

	/**
	 * Validates similar emails restriction.
	 *
	 * @param WC_Coupon $coupon
	 * @param string $code
	 * @param array $posted
	 * @return bool Returns true if validation passes, false otherwise.
	 */
	public function validate_similar_emails_restriction( $coupon, $code, $posted ) {
		$coupon_usage_limit = $coupon->get_usage_limit_per_user();
		if ( ! $coupon_usage_limit ) {
			return true;
		}

		if ( 'yes' !== $coupon->get_meta( 'prevent_similar_emails' ) ) {
			return true;
		}

		$email = $posted['billing_email'];

		// Filter allows lookup against a different stored coupon code if necessary.
		$lookup_code = apply_filters( 'wcr_validate_similar_emails_restriction_lookup_code', $code );

		$count = WC_Coupon_Restrictions_Table::get_similar_email_usage( $lookup_code, $email );

		if ( $count >= $coupon_usage_limit ) {
			$msg = WC_Coupon_Restrictions_Validation::message( 'similar-email-usage', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
			return false;
		}

		return true;
	}

	/**
	 * Validates usage limit per shipping address.
	 *
	 * @param WC_Coupon $coupon
	 * @param string $code
	 * @param array $posted
	 * @return bool Returns true if validation passes, false otherwise.
	 */
	public function validate_usage_limit_per_shipping_address( $coupon, $code, $posted ) {
		$limit = $coupon->get_meta( 'usage_limit_per_shipping_address' );
		if ( ! $limit ) {
			return true;
		}

		// Filter allows lookup against a different stored coupon code if necessary.
		$lookup_code = apply_filters( 'wcr_validate_usage_limit_per_shipping_address_lookup_code', $code );

		$count = WC_Coupon_Restrictions_Table::get_shipping_address_usage( $lookup_code, $posted );
		if ( $count >= $limit ) {
			$msg = WC_Coupon_Restrictions_Validation::message( 'usage-limit-per-shipping-address', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
			return false;
		}

		return true;
	}

	/**
	 * Validates usage limit per IP address.
	 *
	 * @param WC_Coupon $coupon
	 * @param string $code
	 * @return bool Returns true if validation passes, false otherwise.
	 */
	public function validate_usage_limit_per_ip( $coupon, $code ) {
		$limit = $coupon->get_meta( 'usage_limit_per_ip_address' );
		if ( ! $limit ) {
			return true;
		}

		// Filter allows lookup against a different stored coupon code if necessary.
		$lookup_code = apply_filters( 'wcr_validate_usage_limit_per_ip_address_lookup_code', $code );

		$count = WC_Coupon_Restrictions_Table::get_ip_address_usage( $lookup_code );
		if ( $count >= $limit ) {
			$msg = WC_Coupon_Restrictions_Validation::message( 'usage-limit-per-ip-address', $coupon );
			$this->remove_coupon( $coupon, $code, $msg );
			return false;
		}

		return true;
	}

	/**
	 * Removes coupon and displays a validation message.
	 *
	 * @param WC_Coupon $coupon
	 * @param string $code
	 * @param string $msg
	 * @return void
	 */
	public function remove_coupon( $coupon, $code, $msg ) {
		// Filter to change validation text.
		$msg = apply_filters( 'woocommerce_coupon_restrictions_removed_message_with_code', $msg, $code, $coupon );

		// Remove the coupon.
		WC()->cart->remove_coupon( $code );

		// Throw a notice to stop checkout.
		wc_add_notice( $msg, 'error' );

		// Flag totals for refresh.
		WC()->session->set( 'refresh_totals', true );
	}
}
