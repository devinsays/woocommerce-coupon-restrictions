# WooCommerce Coupon Restrictions ![Testing status](https://github.com/devinsays/woocommerce-coupon-restrictions/actions/workflows/php-tests.yml/badge.svg?branch=main)

-   Requires PHP: 8.0
-   WP requires at least: 6.3
-   WP tested up to: 6.8.3
-   WC requires at least: 8.6.1
-   WC tested up to: 10.2.2
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

[View full changelog](https://github.com/devinsays/woocommerce-coupon-restrictions/blob/main/changelog.txt)
