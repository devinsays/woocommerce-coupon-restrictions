<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Framework;
/**
 * WooCommerce mock session handler.
 */
class MockSession extends \WC_Session {
	public function set_customer_session_cookie( $set ) {
	}
}
