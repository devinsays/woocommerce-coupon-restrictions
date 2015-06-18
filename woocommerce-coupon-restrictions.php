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

		// Validates new restrictions
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon' ), 10, 2 );

	}

	/**
	 * Function description
	 *
	 * @return void
	 */
	function new_customers_only() {

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
	function coupon_options_save( $post_id ) {

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
	function validate_coupon( $valid, $coupon ) {

		$new_customers = get_post_meta( $coupon->id, 'new_customers_only', true );

		if ( $valid && 'yes' === $new_customers ) :

			// If user is logged in, we can check if they are a paying customer
			$customer = wp_get_current_user();
			if ( isset( $customer->ID ) ) {

				$paying_customer = get_user_meta( $customer->ID, 'paying_customer', true );
				if ( $paying_customer != '' && absint( $paying_customer ) > 0 ) {

					// Customer has previous purchases, coupon not valid
					// return false;
				}

			} else  {
			}
		endif;

		// echo WC()->customer->city;
		// echo WC()->session->get( 'customer' )->billing_email;

		echo WC()->session->get( 'billing_email' );

		// $data = WC()->session->get( 'customer' );
		//var_dump( $data );

		return $valid;

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