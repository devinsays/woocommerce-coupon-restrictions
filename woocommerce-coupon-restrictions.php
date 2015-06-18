<?php
/**
 * Plugin Name:       WooCommerce Coupon Restrictions
 * Plugin URI:        http://github.com/devinsays/woocommerce-coupon-restrictions
 * Description:       Adds additional coupon restriction options for WooCommerce.
 * Version:           1.0.0
 * Author:            Devin Price
 * Author URI:        http://wptheming.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wc-coupon-restrictions
 * Domain Path:       /languages
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'WC_Coupon_Restrictions' ) ) :

class WC_Coupon_Restrictions {

	/**
	* Construct the plugin.
	*/
	public function __construct() {

		add_action( 'plugins_loaded', array( $this, 'init' ) );

	}

	/**
	* Initialize the plugin.
	*/
	public function init() {

		// Adds metabox to usage restriction fields
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'new_customers_only' ) );

		// Saves the metabox
		add_action( 'woocommerce_coupon_options_save', array( $this, 'coupon_options_save' ) );

		// Validates coupons before checkout if use is logged in
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon' ), 10, 2 );

		// Validates coupons again during checkout validation
		// add_action( 'woocommerce_after_checkout_validation', array( $this, 'check_customer_coupons' ), 1 );

	}

	/**
	 * Function description
	 *
	 * @return void
	 */
	public function new_customers_only() {

		echo '<div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id' => 'new_customers_only',
				'label' => __( 'New customers only', 'wc-coupon-restrictions' ),
				'description' => __( 'Verifies that customer e-mail address has not been used previously.', 'wc-coupon-restrictions' )
			)
		);

		echo '</div>';

	}

	/**
	 * Function description
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
	 * Function description
	 *
	 * @return void
	 */
	public function validate_coupon( $valid, $coupon ) {

		return $valid;

	}

	/**
	 * Check user coupons (now that we have billing email). If a coupon is invalid, add an error.
	 *
	 * @param array $posted
	 */
	public function check_customer_coupons( $posted ) {

		if ( ! empty( $this->applied_coupons ) ) {
			foreach ( $this->applied_coupons as $code ) {
				$coupon = new WC_Coupon( $code );
				if ( $coupon->is_valid() ) {
					$new_customers = get_post_meta( $coupon->id, 'new_customers_only', true );

					// Finally! Check if coupon is restricted to new customers.
					if ( 'yes' === $new_customers ) {

						// Check if order is for returning customer
						if ( is_user_logged_in() ) {

							// If user is logged in, we can check for paying_customer meta.
							$current_user = wp_get_current_user();
							$paying_customer = get_user_meta( $current_user->ID, 'paying_customer', true );
							if ( $paying_customer != '' && absint( $paying_customer ) > 0 ) {
								// Returning customer
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
	 * Checks if e-mail has been used before for a purchase
	 *
	 * @returns boolean
	 */
	function remove_coupon_returning_customer( $coupon, $code ) {

		// Add validation message
		// $coupon->add_coupon_message( 100 );

		// Remove the coupon
		// WC()->WC_Cart->remove_coupon( $code );

		// Flag totals for refresh
		// WC()->session->set( 'refresh_totals', true );

	}

	/**
	 * Checks if e-mail has been used before for a purchase
	 *
	 * @returns boolean
	 */
	function is_returning_customer( $email ) {
		$customer_orders = get_posts( array(
			'post_type'   => 'shop_order',
		    'meta_key'    => '_billing_email',
		    'post_status' => 'publish',
		    'post_status' => array( 'wc-processing', 'wc-completed' ),
		    'meta_value'  => $billing_email,
		    'post_type'   => 'shop_order',
		    'numberposts' => 2,
		    'cache_results' => false,
		    'no_found_rows' => true,
		    'fields' => 'ids'
		) );
		// If there is at least one other order by billing e-mail
		if ( 2 == count( $customer_orders ) ) {
			return true;
		}
		// Otherwise there should only be 1 order
		return false;
	}

}

$WC_Coupon_Restrictions = new WC_Coupon_Restrictions( __FILE__ );

endif;

/*

@TODO:

Load textdomain
Generate .pot file

*/