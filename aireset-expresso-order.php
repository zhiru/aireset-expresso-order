<?php
/**
 * Plugin Name: Aireset - Expresso Order
 * Description: Pedido expresso para vendedores com busca de cliente, inclusao rapida de produtos e geracao de pedido no WooCommerce.
 * Version: 1.1.24
 * Author: Aireset Agencia Web
 * Author URI: https://aireset.com.br
 * Requires Plugins: woocommerce
 * Text Domain: aireset-expresso-order
 * Domain Path: /languages
 * License: Proprietary
 * License URI: https://aireset.com.br/termos-de-uso
 */

defined( 'ABSPATH' ) || exit;

define( 'EOP_VERSION', '1.1.24' );
define( 'EOP_PLUGIN_FILE', __FILE__ );
define( 'EOP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EOP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EOP_TEXT_DOMAIN', 'aireset-expresso-order' );

function eop_ensure_aireset_parent_menu() {
	static $cleanup_hooked = false;
	global $admin_page_hooks, $menu, $submenu;

	$parent_exists = isset( $admin_page_hooks['aireset'] );

	if ( ! $parent_exists && is_array( $menu ) ) {
		foreach ( $menu as $menu_item ) {
			if ( isset( $menu_item[2] ) && 'aireset' === $menu_item[2] ) {
				$parent_exists = true;
				break;
			}
		}
	}

	if ( ! $parent_exists ) {
		add_menu_page(
			__( 'Aireset', EOP_TEXT_DOMAIN ),
			__( 'Aireset', EOP_TEXT_DOMAIN ),
			apply_filters( 'aireset_parent_menu_capability', 'read' ),
			'aireset',
			'eop_render_aireset_parent_page',
			'dashicons-screenoptions',
			58
		);
		$submenu['aireset'] = isset( $submenu['aireset'] ) ? $submenu['aireset'] : array();
	}

	if ( ! $cleanup_hooked ) {
		$cleanup_hooked = true;
		add_action(
			'admin_menu',
			function () {
				remove_submenu_page( 'aireset', 'aireset' );
			},
			99999
		);
	}

	return $parent_exists;
}

function eop_render_aireset_parent_page() {
	global $submenu;

	if ( empty( $submenu['aireset'] ) || ! is_array( $submenu['aireset'] ) ) {
		wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
	}

	foreach ( $submenu['aireset'] as $item ) {
		$page_slug  = isset( $item[2] ) ? (string) $item[2] : '';
		$capability = isset( $item[1] ) ? (string) $item[1] : 'read';

		if ( '' === $page_slug || 'aireset' === $page_slug || ! current_user_can( $capability ) ) {
			continue;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . $page_slug ) );
		exit;
	}

	wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
}

/* License gate */
require_once EOP_PLUGIN_DIR . 'includes/class-eop-license-manager.php';
EOP_License_Manager::get_instance( __FILE__ );

if ( ! EOP_License_Manager::is_valid() ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Aireset Expresso Order: ative sua licenca para usar o plugin.', 'aireset-expresso-order' )
			);
		}
	);
	return;
}
/* /License gate */

require_once EOP_PLUGIN_DIR . 'includes/trait-eop-license-guard.php';
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
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Aireset Expresso Order requer WooCommerce ativo.', EOP_TEXT_DOMAIN );
				echo '</p></div>';
			}
		);
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
