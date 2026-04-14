<?php
/**
 * Plugin Name: Aireset Expresso Order
 * Description: Pedido expresso para vendedores com busca de cliente, inclusao rapida de produtos e geracao de pedido no WooCommerce.
 * Version: 1.1.17
 * Author: Aireset
 * Requires Plugins: woocommerce
 * Text Domain: aireset-expresso-order
 * Domain Path: /languages
 * License: GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

define( 'EOP_VERSION', '1.1.17' );
define( 'EOP_PLUGIN_FILE', __FILE__ );
define( 'EOP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EOP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EOP_TEXT_DOMAIN', 'aireset-expresso-order' );

require_once EOP_PLUGIN_DIR . 'includes/class-role.php';
require_once EOP_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once EOP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
require_once EOP_PLUGIN_DIR . 'includes/class-order-creator.php';
require_once EOP_PLUGIN_DIR . 'includes/class-settings.php';
require_once EOP_PLUGIN_DIR . 'includes/class-shipping-calculator.php';
require_once EOP_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once EOP_PLUGIN_DIR . 'includes/class-public-proposal.php';
require_once EOP_PLUGIN_DIR . 'includes/class-orders-page.php';

/**
 * Activation: create role.
 */
function eop_activate() {
    EOP_Role::create();
}
register_activation_hook( __FILE__, 'eop_activate' );

/**
 * Deactivation: remove role.
 */
function eop_deactivate() {
    EOP_Role::remove();
}
register_deactivation_hook( __FILE__, 'eop_deactivate' );

/**
 * Load plugin translations.
 */
function eop_load_textdomain() {
    load_plugin_textdomain( EOP_TEXT_DOMAIN, false, dirname( plugin_basename( EOP_PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'init', 'eop_load_textdomain' );

/**
 * Boot.
 */
function eop_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'Aireset Expresso Order requer WooCommerce ativo.', EOP_TEXT_DOMAIN );
            echo '</p></div>';
        } );
        return;
    }

    EOP_Admin_Page::init();
    EOP_Ajax_Handlers::init();
    EOP_Shipping_Calculator::init();
    EOP_Settings::init();
    EOP_Shortcode::init();
    EOP_Public_Proposal::init();
    EOP_Orders_Page::init();
    EOP_Role::restrict_menus();
}
add_action( 'plugins_loaded', 'eop_init' );
