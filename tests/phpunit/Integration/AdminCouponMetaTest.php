<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Coupon;
use WC_Coupon_Restrictions_Admin;

class AdminCouponMetaTest extends WP_UnitTestCase {

	/**
	 * These options are global to the store, so they are reverted here rather
	 * than at the end of a test method. A failed assertion aborts the method
	 * before any inline cleanup runs, which would leave every following test
	 * in the suite running against a misconfigured store.
	 */
	public function tear_down() {
		delete_option( 'woocommerce_allowed_countries' );
		delete_option( 'woocommerce_all_except_countries' );

		$_POST = array();

		parent::tear_down();
	}

	/**
	 * Stores using "Sell to all countries, except for…" should not see
	 * excluded countries as selectable restriction options.
	 */
	public function test_shop_countries_respects_all_except_setting() {
		update_option( 'woocommerce_allowed_countries', 'all_except' );
		update_option( 'woocommerce_all_except_countries', array( 'US', 'FR' ) );

		$shop_countries = WC_Coupon_Restrictions_Admin::shop_countries();

		$this->assertNotContains( 'US', $shop_countries );
		$this->assertNotContains( 'FR', $shop_countries );
		$this->assertContains( 'DE', $shop_countries );
	}

	/**
	 * Saving coupon options without the usage limit fields posted
	 * (e.g. programmatic saves) should not raise PHP warnings.
	 */
	public function test_coupon_options_save_without_posted_fields_produces_no_warnings() {
		$coupon = WC_Helper_Coupon::create_coupon( 'nopostfields' );

		$_POST = array();

		// With convertWarningsToExceptions enabled, an "Undefined array key"
		// warning fails this test.
		WC_Coupon_Restrictions_Admin::coupon_options_save( $coupon->get_id(), $coupon );

		$this->assertSame( '', $coupon->get_meta( 'usage_limit_per_shipping_address' ) );
		$this->assertSame( '', $coupon->get_meta( 'usage_limit_per_ip_address' ) );

		$coupon->delete();
	}
}
