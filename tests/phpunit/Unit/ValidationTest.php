<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Unit;

use WP_UnitTestCase;
use WC_Coupon_Restrictions_Validation;

class ValidationTest extends WP_UnitTestCase {
	/**
	 * Test comma_seperated_string_to_array function.
	 */
	public function test_comma_seperated_string_to_array() {
		$string = 'test, TEST2, test3 ';
		$result = WC_Coupon_Restrictions_Validation::comma_seperated_string_to_array( $string );
		$this->assertEquals( $result, ['TEST','TEST2','TEST3'] );
	}
}
