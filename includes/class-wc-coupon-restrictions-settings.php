<?php
/**
 * WooCommerce Coupon Restrictions - Settings.
 *
 * @class    WC_Coupon_Restrictions_Settings
 * @author   DevPress
 * @package  WooCommerce Coupon Restrictions
 * @license  GPL-2.0+
 * @since    1.7.0
 */

if ( ! defined('ABSPATH') ) {
	exit; // Exit if accessed directly.
}

class WC_Coupon_Restrictions_Settings {

	/**
	* Init the class.
	*/
	public function init() {

		// Filters the settings in the "General Tab".
		add_filter( 'woocommerce_general_settings', array( $this, 'coupon_restrictions_settings' ) );

	}

	/**
	* Adds our coupon restriction settings.
	*/
	public function coupon_restrictions_settings( $settings ) {

		$coupon_restrictions = array(
			'title'    => __( 'Coupon Restrictions', 'woocommerce' ),
			'id'       => 'coupon_restrictions_customer_query',
			'default'  => 'accounts',
			'type'     => 'radio',
			'desc_tip' => __( 'If you\'re restricting any coupons to new customers, we recommend requiring a user account for each customer. Checking against orders can be slow for sites with more than 10,000 orders.', 'woocommerce' ),
			'options'  => array(
				'accounts' => __( 'Verify new customers by checking against user accounts.', 'woocommerce' ),
				'accounts-orders'  => __( 'Verify new customers by checking against user accounts and all guest orders.', 'woocommerce' ),
			),
		);

		$filtered_settings = array();

		foreach ( $settings as $setting ) {
			$filtered_settings[] = $setting;
			if ( 'woocommerce_calc_discounts_sequentially' === $setting['id'] ) {
				$filtered_settings[] = $coupon_restrictions;
			}
		}

		return $filtered_settings;
	}
}
