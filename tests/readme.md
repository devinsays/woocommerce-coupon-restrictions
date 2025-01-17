Running tests requires composer, wp-cli and phpunit to be installed.

# About the tests

You can install the testing directory anywhere on your system. You don't need to set up a site, though that can be helpful if you're making changes to the plugin and want to test actual functionality.

If you already have a working WordPress install and just want to install the plugin and unit tests, skip to step #2.

### 1. Create a WordPress install

```
mkdir woocommerce
cd woocommerce
wp core download
```

### 2. Install the plugin files

Run this from the base directory of your WordPress install.

```
git clone https://github.com/devinsays/woocommerce-coupon-restrictions wp-content/plugins/woocommerce-coupon-restrictions
cd wp-content/plugins/woocommerce-coupon-restrictions
```

### 3. Create a database for running tests and install core WordPress unit tests

You'll be creating a new database to runs the tests. The contents of the database will be deleted after each run. Use your local database username/password to set up the database.

```
bash tests/bin/install-wp-tests.sh {dbname} {dbuser} {dbpass}
```

Example:

```
bash tests/bin/install-wp-tests.sh woocommerce_test root root
```

### 4. Install the test dependencies

```
composer install
```

### 5. Run the tests

If you have phpunit installed globally, you can just run it from the directory:

```
phpunit
```

Otherwise, you can run the version of phpunit that composer installs:

```
vendor/phpunit/phpunit/phpunit -c phpunit.xml
```

Once the test runs the terminal output how many tests passed/failed. If you have no output, it means the test didn't run properly. In that case, check your PHP logs.

### 6. Test against specific versions of WooCommerce

Tests run by default against the latest version of WooCommerce. To test a specific version of WooCommerce, set an environment variable before running your test:

```
WC_VERSION=9.4.0 phpunit
```
