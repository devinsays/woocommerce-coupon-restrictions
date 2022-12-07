<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Coupon;
use WC_Coupon_Restrictions_Table;
use WC_Coupon_Restrictions_Validation;

class ValidationsTest extends WP_UnitTestCase {

	/**
	 * Verify coupon checks for enhanced usage restrictions are working.
	 */
	public function test_has_enhanced_usage_restrictions() {
		$coupon = WC_Helper_Coupon::create_coupon();
		$result = WC_Coupon_Restrictions_Validation::has_enhanced_usage_restrictions( $coupon );

		// Default coupon should not have enhanced usage restrictions.
		$this->assertFalse( $result );

		$coupon->update_meta_data( 'prevent_similar_emails', 'yes' );
		$coupon->save();

		// Test with one enhanced usage restriction.
		$result = WC_Coupon_Restrictions_Validation::has_enhanced_usage_restrictions( $coupon );
		$this->assertTrue( $result );
	}

	public function tearDown() {
		// Deletes the custom table if it has been created.
		WC_Coupon_Restrictions_Table::delete_table();
	}
}
