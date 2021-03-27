# WooCommerce Coupon Restrictions [![Build Status](https://travis-ci.org/devinsays/woocommerce-coupon-restrictions.svg?branch=master)](https://travis-ci.org/devinsays/woocommerce-coupon-restrictions)

* Contributors: @downstairsdev
* Tags: woocommerce, coupon
* Requires at least: 4.9.0
* Tested up to: 5.7
* Requires PHP: 7.0
* Stable tag: 1.8.3
* License: GPLv3 or later License
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
* WC requires at least: 3.9.3
* WC tested up to: 5.1.0

## Description

This extension allows you to create coupons with addition restriction options:

**New customer restriction**: Verifies that the customer does not have an account with completed purchases before applying the coupon. The extension can also verify that a customer doesn't have completed guest orders if when that setting is selected.

**Existing customer restriction**: Verifies that the custom does have an account with completed purchases before applying a coupon.

**User role restriction**: Limits a coupon to specific user roles. If you have custom roles for wholesale customers, affiliates, or vips, you can provide coupons that will only work for them.

**Country restriction**: Allows you to restrict a coupon to specific countries. Restriction can be applied to shipping or billing country.

**State restriction**: Allows you to restrict a coupon to specific states. Restriction can be applied to shipping or billing country.

**Zip code restriction**: Allows you to restrict a coupon to specific zip codes or postal codes. Can be applied to the shipping or the billing address.

The plugin is fully translatable. Developers can also use filters to modify any notices displayed during coupon validation.

## Additional Notes

Customers are considered "new customers" if they do not have a user account with completed purchases.

If your site allows guest checkouts, you can also verify if a customer has completed a guest checkout order previously. To enable this, select "Verify new customers by checking against user accounts and all guest orders" under "WooCommerce > Settings > General". However, this setting is not recommended for sites with more than 10,000 orders as this verification query takes additional time to run. Instead, it's recommended to create customer accounts in the background during checkout.

E-mail addresses, zip code, and state restrictions are not case sensitive.

A customer must meet all requirements if multiple restrictions are set. For instance, if a "New Customer" and "Country Restriction" are set, a customer must meet both restrictions in order to checkout with the coupon.

## Unit Tests

PHPUnit, Composer and WP-CLI are required for running unit tests.

Set up instructions:
https://github.com/devinsays/woocommerce-coupon-restrictions/wiki/Unit-Tests

## Compatibility

* Requires WooCommerce 3.9.0 or later.

## Changelog

**1.8.3**

* Enhancement: Adds "Guest (No Account)" option to the roles restriction.

**1.8.2**

* Enhancement: Reduces use of coupon meta by only storing non-default values.
* Update: Tested to WooCommerce  4.8.0.
* Update: PHP 7.0 or higher required.
* Update: WooCommerce 3.9.0 or higher required.

**1.8.1 (06.14.20)**

* Enhancement: Add all countries easily for country restriction.
* Enhancement: Improved automated testing suite.
* Update: Tested to WooCommerce 4.2.0.

**1.8.0 (09.25.19)**

* Enhancement: Adds feature to restrict coupon by user role.
* Enhancement: Zip code restrictions now allow wildcard matches.
* Enhancement: Filter to skip pre-checkout validation.
* Bugfix: If user is logged in and has no orders associated with their account but does have previous guest orders, those guest orders will now be checked to verify if customer is a new customer when "check guest orders" is selected.

**1.7.2 (03.12.19)**

* Bugfix: Fixes 500 when saving coupons in specific server environments.

**1.7.1 (03.03.19)**

* Enhancement: Adds feature to restrict coupon by state.

**1.7.0 (02.11.19)**

* Update: New setting for new/existing customer verification method. Defaults to account check.
* Fix: Resolves bug applying coupon when there is no session (subscription renewals). Props @giantpeach.

**1.6.2 (07.17.18)**

* Fix: PHP5.6 compatibility fixes for onboarding script.

**1.6.1 (06.21.18)**

* Update: Use WooCommerce data store methods for saving and reading coupon meta.
* Update: WC_Coupon_Restrictions() now returns shared instance of class rather than singleton.
* Fix: Display onboarding notice on initial activation.
* Fix: If the session data is blank for country or zipcode, a coupon with location restrictions will now apply until session or checkout has data to validate it.

**1.6.0 (06.15.18)**

* Enhancement: Coupon validation now uses stored session data.
* Enhancement: Checkout validation now uses $posted data.
* Update: Additional unit and integration tests.
* Update: Returns a main instance of the class to avoid the need for globals.

**1.5.0 (05.17.18)**

* Update: Improve coupon validation messages.
* Update: Use "Zip code" as default label.
* Update: Improve customer restriction UX. Use radio buttons rather than select.
* Update: Adds "Location Restrictions" checkbox. Additional options display when checked.
* Update: Country restriction only permits selection of countries that shop currently sells to.
* Update: New onboarding flow that shows where the new coupon options are located.
* Fix: Bug with new customer coupon validation at checkout.

**1.4.1 (02.15.18)**

* Update: Remove upgrade routine.

**1.4.0 (12.27.17)**

* Enhancement: Adds option to restrict location based on shipping or billing address.
* Enhancement: Adds option to restrict to postal code or zip code.
* Update: Use WooCommerce order wrapper to fetch orders.
* Update: Organize plugin into multiple classes.
* Update: Upgrade script for sites using earlier version of plugin.
* Update: Unit test framework added.

**1.3.0 (01.31.17)**

* Enhancement: Adds option to restrict to existing customers.
* Enhancement: Adds option to restrict by shipping country.
* Update: Compatibility updates for WooCommerce 2.7.0.

**1.2.0 (11.25.16)**

* Update: Compatibility updates for WooCommerce 2.6.8.

**1.1.0 (12.28.15)**

* Fix: Coupons without the new customer restriction were improperly marked invalid for logged in users.
* Fix: Filter woocommerce_coupon_is_valid in addition to woocommerce_coupon_error.

**1.0.0 (06.18.15)**

* Initial release.
