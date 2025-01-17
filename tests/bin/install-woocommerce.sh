TMPDIR="tests/tmp"
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress/}

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

install_woocommerce() {
	# Check if WP_CORE_DIR exists
    if [ ! -d "$WP_CORE_DIR" ]; then
        echo "Error: WordPress has not been installed yet. Please run `bash tests/bin/install-wp-tests.sh`."
        return
    fi

    # Check if a version is provided
    WC_VERSION=${1:-"latest"}

    # Install WooCommerce plugin
    if [ -d "$WP_CORE_DIR/wp-content/plugins/woocommerce-$WC_VERSION" ]; then
        echo "WooCommerce $WC_VERSION is already installed."
        return
    fi

    # Set download URL based on version
    if [ "$WC_VERSION" = "latest" ]; then
        DOWNLOAD_URL="https://downloads.wordpress.org/plugin/woocommerce.zip"
    else
        DOWNLOAD_URL="https://downloads.wordpress.org/plugin/woocommerce.$WC_VERSION.zip"
    fi

    echo "Downloading WooCommerce version: $WC_VERSION..."
    download $DOWNLOAD_URL $TMPDIR/woocommerce.zip
    if [ ! -f $TMPDIR/woocommerce.zip ]; then
        echo "Error: Failed to download WooCommerce version $WC_VERSION."
        exit 1
    fi

    echo "Extracting WooCommerce zip file..."
    unzip -q $TMPDIR/woocommerce.zip -d $TMPDIR/

    # Check if the extracted directory exists
    if [ -d "$TMPDIR/woocommerce" ]; then
        # Rename the extracted directory
        mv "$TMPDIR/woocommerce" "$TMPDIR/woocommerce-$WC_VERSION"
        echo "Renamed WooCommerce directory to woocommerce-$WC_VERSION."
    else
        echo "Error: Extracted WooCommerce directory not found."
        exit 1
    fi

    # Move the renamed directory to the plugin directory
    mv "$TMPDIR/woocommerce-$WC_VERSION" "$WP_CORE_DIR/wp-content/plugins/"
    echo "WooCommerce version $WC_VERSION installed successfully."
}

install_woocommerce
