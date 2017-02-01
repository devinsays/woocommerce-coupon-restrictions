=== WooCommerce New Customer Coupons ===

Contributors: @downstairsdev
Tags: woocommerce, coupon
Requires at least: 4.6.1
Tested up to: 4.6.1
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 2.6.0
WC tested up to: 2.6.8

== Description ==

This extension allows you to create coupons that can be restricted to new customers or existing customers. After installing, look for the "New Customers Only" option in the coupon usage restrictions screen.

If a customer is logged in, the plugin will verify that they haven't purchased anything from the site before allowing the coupon to be applied. For customers that aren't logged in, the coupon verification runs right before checkout once the e-mail address has been entered.

The plugin is fully translatable. Developers can also use filters to modify any notices displayed during coupon validation.

== Additional Notes ==

Customers are considered "new customers" if they have not yet spent money on the site and/or do not have an order that has been marked complete or processing. This can lead to some edge cases where a customer could use the coupon several times before an order is marked complete. Avoid this by setting the "Usage limit per user" to 1.

This extension checks e-mail addresses of existing customers by converting to all lower-case and doing a strict string match. It will only flag e-mail addresses that have an exact match.

== Changelog ==

= 1.2.0 =

* Fix: Compatibility updates for WooCommerce 2.6.8

= 1.1.0 =

* Fix: Coupons without the new customer restriction were improperly marked invalid for logged in users.
* Fix: Filter woocommerce_coupon_is_valid in addition to woocommerce_coupon_error.
* Update: Add unit tests.

= 1.0.0 =

* Initial release.