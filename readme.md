# WooCommerce Coupon Restrictions ![Testing status](https://github.com/devinsays/woocommerce-coupon-restrictions/actions/workflows/php-tests.yml/badge.svg?branch=main)

-   Requires PHP: 8.0
-   WP requires at least: 6.3
-   WP tested up to: 6.8.2
-   WC requires at least: 8.6.1
-   WC tested up to: 10.1.2
-   Stable tag: 2.3.0
-   License: [GPLv3 or later License](http://www.gnu.org/licenses/gpl-3.0.html)

## Important notice

This extension does not yet support WooCommerce Checkout Blocks. Support will be added soon.

## Description

Create targeted coupons for new customers, user roles, countries or zip codes. Prevent coupon abuse with enhanced usage limits.

**New customer restriction**: Verifies that the customer does not have an account with completed purchases before applying the coupon. The extension can also verify that a customer doesn't have completed guest orders if when that setting is selected.

**Existing customer restriction**: Verifies that the custom does have an account with completed purchases before applying a coupon.

**User role restriction**: Limits a coupon to specific user roles. If you have custom roles for wholesale customers, affiliates, or vips, you can provide coupons that will only work for them.

**Country restriction**: Allows you to restrict a coupon to specific countries. Restriction can be applied to shipping or billing country.

**State restriction**: Allows you to restrict a coupon to specific states. Restriction can be applied to shipping or billing country.

**Zip code restriction**: Allows you to restrict a coupon to specific zip codes or postal codes. Can be applied to the shipping or the billing address.

**Similar emails usage limit**: Sometimes customers use different email addresses in order to avoid coupon usage limits. This option does basic checks to ensure a customer is not using an email alias with periods or a "+" to exceed the coupon usage limit.

**Shipping address usage limit**: Allows you to limit the amount of a times a coupon can be used with a specific shipping address.

**IP address usage limit**: Allows you to limit the amount of times a coupon can be used with a specific IP address.

The plugin is fully translatable. Developers can also use filters to modify any notices displayed during coupon validation.

## Additional Notes

Customers are considered "new customers" if they do not have a user account with completed purchases.

If your site allows guest checkouts, you can also verify if a customer has completed a guest checkout order previously. To enable this, select "Verify new customers by checking against user accounts and all guest orders" under "WooCommerce > Settings > General". However, this setting is not recommended for sites with more than 10,000 orders as this verification query takes additional time to run. Instead, it's recommended to create customer accounts in the background during checkout.

E-mail addresses, zip code, and state restrictions are not case sensitive.

A customer must meet all requirements if multiple restrictions are set. For instance, if a "New Customer" and "Country Restriction" are set, a customer must meet both restrictions in order to checkout with the coupon.

## Filters

**Enhanced Usage Restriction Validation Messages**

The enhanced usage restrictions all validate with a generic validation message by default: `Sorry, coupon code "%s" usage limit exceeded.`

If you would like to display distinct validation messages for each type of enhanced restriction (similar email, shipping address, IP), use the `wcr_combine_enhanced_restrictions_validation` filter.

```php
add_filter('wcr_combine_enhanced_restrictions_validation', '__return_false');
```

**Enhanced Usage Restrictions Coupon Code Filters**

The plugin provides filters that allow you to transform coupon codes during storage and lookup operations. This is useful for creating numbered coupon variants that share usage limits.

**Use Case: Unique Coupon Codes with Shared Usage Limits**

You can create multiple unique coupons like "new-customer-1", "new-customer-2", "new-customer-3" that all share the same usage restrictions. This is useful for marketing campaigns where you want to track individual coupon performance while maintaining consistent usage limits.

**Available Filters:**

1. `wcr_coupon_code_to_store_for_enhanced_usage_limits` - Transforms the coupon code when storing usage records in the database
2. `wcr_validate_similar_emails_restriction_lookup_code` - Transforms the coupon code when checking email usage limits
3. `wcr_validate_usage_limit_per_shipping_address_lookup_code` - Transforms the coupon code when checking shipping address usage limits
4. `wcr_validate_usage_limit_per_ip_address_lookup_code` - Transforms the coupon code when checking IP address usage limits

**Example Implementation:**

```php
/**
 * Transform numbered new customer coupons to share usage limits
 *
 * This example allows "new-customer-1", "new-customer-2", "new-customer-3" etc.
 * to all be stored and validated against a shared "new-customer" base code.
 */

/**
 * Transform coupon code for storage and lookup operations
 */
function transform_new_customer_coupon_code( $coupon_code ) {
    if ( strpos( $coupon_code, 'new-customer-' ) === 0 ) {
        return 'new-customer';
    }
    return $coupon_code;
}

// Storage filter - transform codes when storing usage records
add_filter( 'wcr_coupon_code_to_store_for_enhanced_usage_limits', 'transform_new_customer_coupon_code' );

// Lookup filters - transform codes when validating usage limits
add_filter( 'wcr_validate_similar_emails_restriction_lookup_code', 'transform_new_customer_coupon_code' );
add_filter( 'wcr_validate_usage_limit_per_shipping_address_lookup_code', 'transform_new_customer_coupon_code' );
add_filter( 'wcr_validate_usage_limit_per_ip_address_lookup_code', 'transform_new_customer_coupon_code' );
```

**How It Works:**

1. **Storage**: When a customer uses "new-customer-1", the usage record is stored in the database as "new-customer"
2. **Validation**: When a customer tries to use "new-customer-2", the system looks up usage records for "new-customer"
3. **Result**: All numbered variants ("new-customer-1", "new-customer-2", etc.) share the same usage limits

This approach allows you to:

-   Create unlimited unique coupon codes for tracking purposes
-   Maintain consistent usage restrictions across all variants
-   Prevent customers from bypassing limits by using different numbered codes
-   Track individual coupon performance while enforcing shared limits

**Advanced Example: Campaign-Based Grouping**

```php
/**
 * Group coupons by campaign while maintaining individual tracking
 */
function transform_campaign_coupon_codes( $coupon_code ) {
    // Group summer sale coupons: summer-sale-1, summer-sale-2, etc.
    if ( strpos( $coupon_code, 'summer-sale-' ) === 0 ) {
        return 'summer-sale';
    }

    // Group holiday coupons: holiday-2024-1, holiday-2024-2, etc.
    if ( strpos( $coupon_code, 'holiday-2024-' ) === 0 ) {
        return 'holiday-2024';
    }

    // Group new customer coupons: new-customer-social, new-customer-email, etc.
    if ( strpos( $coupon_code, 'new-customer-' ) === 0 ) {
        return 'new-customer';
    }

    return $coupon_code;
}

// Apply to all filters
add_filter( 'wcr_coupon_code_to_store_for_enhanced_usage_limits', 'transform_campaign_coupon_codes' );
add_filter( 'wcr_validate_similar_emails_restriction_lookup_code', 'transform_campaign_coupon_codes' );
add_filter( 'wcr_validate_usage_limit_per_shipping_address_lookup_code', 'transform_campaign_coupon_codes' );
add_filter( 'wcr_validate_usage_limit_per_ip_address_lookup_code', 'transform_campaign_coupon_codes' );
```

## Unit Tests

![Testing status](https://github.com/devinsays/woocommerce-coupon-restrictions/actions/workflows/php-tests.yml/badge.svg?branch=main)

PHPUnit, Composer and WP-CLI are required for running unit tests.

Local testing set up instructions:
https://github.com/devinsays/woocommerce-coupon-restrictions//blob/main/tests/readme.md

## Code Standards

The WordPress VIP minimum standards and WordPress-Extra code standards are used for this project. They will be installed via composer.

To run the code checks from the command line run: `vendor/bin/phpcs`

## Translations

-   Run `composer make-pot` to update the translation file.

## Changelog

**2.3.0 (2025-XX-XX)**

-   Enhancement: Filter to allow distinct validation messsages for each of the enhanced coupon restriction reasons.
-   Enhancement: Filters to allow shared set of enhanced coupon restrictions across multiple coupons

**2.2.3 (2025-04-25)**

-   Update: Declare compatibility with latest version of WooCommerce.
-   Update: Fix deprecated call to is_coupon_valid.
-   Update: Improve docblock documentation.
-   Update: Improve automated testing suite.

**2.2.2 (2024-02-05)**

-   Bugfix: Add explicit check for array type for meta values that require it.

**2.2.1 (2023-04-07)**

-   Bugfix: Fatal error on subscription renewal with coupon applied due to missing class.

**2.2.0 (2023-03-09)**

-   Feature: WP CLI command to add historic order data to verification table.
-   Update: Improve documentation around enhanced usage limits.
-   Update: Declare compatibility for High Performance Order Storage.

**2.1.0 (2023-02-10)**

-   Bugfix: Coupon verification records were not storing properly on checkouts with payment.
-   Bugfix: Show compatibility notice if WooCommerce is not active.

**2.0.0 (2023-01-02)**

-   Update: Major plugin rewrite.
-   Enhancement: Enhanced usage limits to help reduce coupon fraud.

**1.8.6 (2022-11-09)**

-   Update: Tested to WooCommerce 7.1.0.
-   Update: Migrate inline admin javascript to enqueued file.

**1.8.5 (2022-03-06)**

-   Bugfix: Rename translation file.
-   Update: Migrate inline javascript to file.

**1.8.4 (2022-01-12)**

-   Update: Tested to WooCommerce 6.1.0.
-   Update: WooCommerce 4.8.1 or higher required.
-   Bugfix: Display specific coupon validation messages during ajax checkout validation.

**1.8.3 (2021-03-28)**

-   Enhancement: Adds "Guest (No Account)" option to the roles restriction.

**1.8.2 (2021-01-12)**

-   Enhancement: Reduces use of coupon meta by only storing non-default values.
-   Update: Tested to WooCommerce 4.8.0.
-   Update: PHP 7.0 or higher required.
-   Update: WooCommerce 3.9.0 or higher required.

**1.8.1 (2020-06-14)**

-   Enhancement: Add all countries easily for country restriction.
-   Enhancement: Improved automated testing suite.
-   Update: Tested to WooCommerce 4.2.0.

**1.8.0 (2019-09-25)**

-   Enhancement: Adds feature to restrict coupon by user role.
-   Enhancement: Zip code restrictions now allow wildcard matches.
-   Enhancement: Filter to skip pre-checkout validation.
-   Bugfix: If user is logged in and has no orders associated with their account but does have previous guest orders, those guest orders will now be checked to verify if customer is a new customer when "check guest orders" is selected.

**1.7.2 (2019-03-12)**

-   Bugfix: Fixes 500 when saving coupons in specific server environments.

**1.7.1 (2019-03-03)**

-   Enhancement: Adds feature to restrict coupon by state.

**1.7.0 (2019-02-11)**

-   Update: New setting for new/existing customer verification method. Defaults to account check.
-   Bugfix: Resolves bug applying coupon when there is no session (subscription renewals). Props @giantpeach.

**1.6.2 (2018-07-17)**

-   Bugfix: PHP5.6 compatibility fixes for onboarding script.

**1.6.1 (2018-06-21)**

-   Update: Use WooCommerce data store methods for saving and reading coupon meta.
-   Update: WC_Coupon_Restrictions() now returns shared instance of class rather than singleton.
-   Bugfix: Display onboarding notice on initial activation.
-   Bugfix: If the session data is blank for country or zipcode, a coupon with location restrictions will now apply until session or checkout has data to validate it.

**1.6.0 (2018-06-15)**

-   Enhancement: Coupon validation now uses stored session data.
-   Enhancement: Checkout validation now uses $posted data.
-   Update: Additional unit and integration tests.
-   Update: Returns a main instance of the class to avoid the need for globals.

**1.5.0 (2018-05-17)**

-   Update: Improve coupon validation messages.
-   Update: Use "Zip code" as default label.
-   Update: Improve customer restriction UX. Use radio buttons rather than select.
-   Update: Adds "Location Restrictions" checkbox. Additional options display when checked.
-   Update: Country restriction only permits selection of countries that shop currently sells to.
-   Update: New onboarding flow that shows where the new coupon options are located.
-   Bugfix: Bug with new customer coupon validation at checkout.

**1.4.1 (2018-02-15)**

-   Update: Remove upgrade routine.

**1.4.0 (2017-12-27)**

-   Enhancement: Adds option to restrict location based on shipping or billing address.
-   Enhancement: Adds option to restrict to postal code or zip code.
-   Update: Use WooCommerce order wrapper to fetch orders.
-   Update: Organize plugin into multiple classes.
-   Update: Upgrade script for sites using earlier version of plugin.
-   Update: Unit test framework added.

**1.3.0 (2017-01-31)**

-   Enhancement: Adds option to restrict to existing customers.
-   Enhancement: Adds option to restrict by shipping country.
-   Update: Compatibility updates for WooCommerce 2.7.0.

**1.2.0 (2016-11-25)**

-   Update: Compatibility updates for WooCommerce 2.6.8.

**1.1.0 (2015-12-28)**

-   Bugfix: Coupons without the new customer restriction were improperly marked invalid for logged in users.
-   Bugfix: Filter woocommerce_coupon_is_valid in addition to woocommerce_coupon_error.

**1.0.0 (2015-06-18)**

-   Initial release.
