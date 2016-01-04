# WooCommerce Coupon Code Unit Tests

## About These Tests

These tests rely on the same framework as the core WooCommerce unit tests.

* Ensures is_returning_customer works correctly.
* Ensures a standard coupon applies correctly for new customer.
* Ensures a standard coupon applies correctly for an existing customer.
* Ensures a that a coupon with "new customer restriction" does not apply correct for an existing customer.

## Initial Setup

* Set up your WordPress install and include "WooCommerce" and "WooCommerce New Customer Coupons" in the plugins directory.
* Follow the instructions for running the base WooCommerce unit tests, but use the "WooCommerce New Customer Coupons" directory as the base directory: https://github.com/woothemes/woocommerce/tree/master/tests
