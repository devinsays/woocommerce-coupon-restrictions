# WooCommerce Coupon Restrictions [![Build Status](https://travis-ci.org/devinsays/woocommerce-coupon-restrictions.svg?branch=master)](https://travis-ci.org/devinsays/woocommerce-coupon-restrictions)

* Contributors: @downstairsdev
* Tags: woocommerce, coupon
* Requires at least: 4.7.0
* Tested up to: 4.9.4
* Stable tag: 1.5.1
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html
* WC requires at least: 3.3.0
* WC tested up to: 3.3.5

## Description

This extension allows you to create coupons with addition restriction options:

**New customer restriction**: If a customer is logged in, the plugin will verify that they haven't purchased anything from the site before allowing the coupon to be applied. For customers that aren't logged in, the coupon verification runs right before checkout once the e-mail address has been entered.

**Existing customer restrictions**: Validates that the customer has made a purchase on the site previously before allowing the coupon to be applied.

**Country restriction**: Allows you to restrict a coupon to specific countries. Restriction can be applied to shipping or billing country.

**Zip code restriction**: Allows you to restrict a coupon to specific zip codes or postal codes. Can be applied to the shipping or the billing address.

The plugin is fully translatable. Developers can also use filters to modify any notices displayed during coupon validation.

## Additional Notes

Customers are considered "new customers" if they have not yet spent money on the site and/or do not have an order that has been marked complete or processing. This can lead to some edge cases where a customer could use the coupon several times before an order is marked complete. Avoid this by setting the "Usage limit per user" to 1.

E-mail addresses restrictions are not case sensitive, but otherwise require an exact match.

Zip code or postcode restrictions are not case sensitive.

A customer must meet all requirements if multiple restrictions are set. For instance, if a "New Customer" and "Country Restriction" are set, a customer must meet both restrictions in order to checkout with the coupon.

## Unit Tests

PHPUnit, Composer and WP-CLI are required for running unit tests.

Set up instructions:
https://github.com/devinsays/woocommerce-coupon-restrictions/wiki/Unit-Tests

## Compatibility

* Requires WooCommerce 3.2.1 or later.

## Changelog

**1.5.1 (05.19.18)**
* Integration of Github Updater Lite : https://github.com/FacetWP/github-updater-lite
* Plugin will check every 12 hours for a new build/release on https://github.com/devinsays/woocommerce-coupon-restrictions and allow updating from within Wordpress.

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
