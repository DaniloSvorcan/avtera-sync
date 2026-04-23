<?php
/**
 * Plugin Name: Avtera Product Sync
 * Description: Sinhronizacija WooCommerce proizvoda sa Avtera XML feedom
 * Version:     1.0.0
 * Author:      Bafna
 * Text Domain: avtera-sync
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'AVTERA_SYNC_VERSION', '1.0.0' );
define( 'AVTERA_SYNC_PATH',    plugin_dir_path( __FILE__ ) );

require_once AVTERA_SYNC_PATH . 'includes/class-avtera-xml-parser.php';
require_once AVTERA_SYNC_PATH . 'includes/class-avtera-product-sync.php';
require_once AVTERA_SYNC_PATH . 'includes/class-avtera-admin.php';

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="error"><p><strong>Avtera Sync:</strong> WooCommerce mora biti aktivan.</p></div>';
        } );
        return;
    }
    new Avtera_Admin();
} );
