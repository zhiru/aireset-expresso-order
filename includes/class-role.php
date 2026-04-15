<?php
defined( 'ABSPATH' ) || exit;

class EOP_Role {

    const ROLE_SLUG = 'vendedor_expresso';

    /**
     * Create role on activation.
     */
    public static function create() {
        if ( get_role( self::ROLE_SLUG ) ) {
            return;
        }

        add_role( self::ROLE_SLUG, __( 'Vendedor Expresso', EOP_TEXT_DOMAIN ), array(
            'read'                 => true,
            'edit_shop_orders'     => true,
            'publish_shop_orders'  => true,
            'read_shop_orders'     => true,
            'read_product'         => true,
            'edit_shop_order'      => true,
            'upload_files'         => false,
        ) );
    }

    /**
     * Remove role on deactivation.
     */
    public static function remove() {
        remove_role( self::ROLE_SLUG );
    }

    /**
     * Hide all admin menus except our plugin and order list for vendedor_expresso.
     */
    public static function restrict_menus() {
        add_action( 'admin_menu', array( __CLASS__, 'hide_menus' ), 999 );
        add_action( 'login_redirect', array( __CLASS__, 'redirect_after_login' ), 10, 3 );
    }

    /**
     * Remove menu items for vendedor_expresso.
     */
    public static function hide_menus() {
        if ( ! self::is_vendedor() ) {
            return;
        }

        global $menu;
        $allowed_slugs = array(
            'aireset',
            'edit.php?post_type=shop_order',
            'woocommerce',
        );

        if ( is_array( $menu ) ) {
            foreach ( $menu as $key => $item ) {
                if ( ! in_array( $item[2], $allowed_slugs, true ) ) {
                    remove_menu_page( $item[2] );
                }
            }
        }
    }

    /**
     * Redirect vendedor_expresso to plugin page after login.
     *
     * @param string $redirect_to
     * @param string $requested_redirect
     * @param WP_User $user
     * @return string
     */
    public static function redirect_after_login( $redirect_to, $requested_redirect, $user ) {
        if ( ! is_wp_error( $user ) && in_array( self::ROLE_SLUG, (array) $user->roles, true ) ) {
            if ( ! empty( $requested_redirect ) ) {
                return $requested_redirect;
            }
            return admin_url( 'admin.php?page=eop-pedido-expresso' );
        }
        return $redirect_to;
    }

    /**
     * Check if current user is vendedor_expresso.
     *
     * @return bool
     */
    public static function is_vendedor() {
        $user = wp_get_current_user();
        return in_array( self::ROLE_SLUG, (array) $user->roles, true );
    }
}
