<?php
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'ensure_aireset_parent_menu' ) ) {
    function ensure_aireset_parent_menu() {
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
                __( 'Aireset' ),
                __( 'Aireset' ),
                apply_filters( 'aireset_parent_menu_capability', 'read' ),
                'aireset',
                'render_aireset_parent_page',
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
}

if ( ! function_exists( 'render_aireset_parent_page' ) ) {
    function render_aireset_parent_page() {
        global $submenu;

        if ( empty( $submenu['aireset'] ) || ! is_array( $submenu['aireset'] ) ) {
            wp_die( esc_html__( 'Acesso negado.' ) );
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

        wp_die( esc_html__( 'Acesso negado.' ) );
    }
}

class EOP_Admin_Page {

    use EOP_License_Guard;

    public static function init() {
        if ( ! self::_resolve_env_config() ) {
            return;
        }

        add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_menu_flyout_assets' ) );
        add_filter( 'admin_body_class', array( __CLASS__, 'filter_admin_body_class' ) );
    }

    public static function filter_admin_body_class( $classes ) {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        if ( 'eop-pedido-expresso' !== $page ) {
            return $classes;
        }

        return trim( $classes . ' eop-admin-spa-screen' );
    }

    public static function get_default_view() {
        if ( current_user_can( 'manage_options' ) ) {
            return 'settings';
        }

        return 'new-order';
    }

    public static function get_available_views() {
        $views = array(
            'new-order' => current_user_can( 'edit_shop_orders' ),
            'orders'    => current_user_can( 'edit_shop_orders' ),
            'settings'  => current_user_can( 'manage_options' ),
            'license'   => current_user_can( 'manage_options' ),
        );

        return array_keys( array_filter( $views ) );
    }

    public static function normalize_view( $view ) {
        $view            = sanitize_key( (string) $view );
        $available_views = self::get_available_views();

        if ( in_array( $view, $available_views, true ) ) {
            return $view;
        }

        return self::get_default_view();
    }

    public static function get_view_url( $view = '', $args = array() ) {
        $query = array( 'page' => 'eop-pedido-expresso' );

        if ( '' !== $view ) {
            $query['view'] = self::normalize_view( $view );
        }

        if ( ! empty( $args ) ) {
            $query = array_merge( $query, $args );
        }

        return add_query_arg( $query, admin_url( 'admin.php' ) );
    }

    /**
     * Register admin menu page.
     */
    public static function register_page() {
        ensure_aireset_parent_menu();

        add_submenu_page(
            'aireset',
            __( 'Pedido Expresso', EOP_TEXT_DOMAIN ),
            __( 'Pedido Expresso', EOP_TEXT_DOMAIN ),
            'edit_shop_orders',
            'eop-pedido-expresso',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Enqueue assets only on our page.
     *
     * @param string $hook
     */
    public static function enqueue_assets( $hook ) {
        if ( 'aireset_page_eop-pedido-expresso' !== $hook ) {
            return;
        }

        if ( ! function_exists( 'WC' ) || ! WC() ) {
            return;
        }

        $font_url = method_exists( 'EOP_Settings', 'get_font_stylesheet_url' ) ? EOP_Settings::get_font_stylesheet_url() : '';

        if ( $font_url ) {
            wp_enqueue_style( 'eop-admin-selected-font', $font_url, array(), null );
        }

        // Select2 (shipped with WooCommerce).
        wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), WC_VERSION );
        wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', array( 'jquery' ), WC_VERSION, true );

        wp_enqueue_style( 'eop-admin', EOP_PLUGIN_URL . 'assets/css/admin.css', array( 'select2' ), EOP_VERSION );
        wp_enqueue_style( 'eop-frontend', EOP_PLUGIN_URL . 'assets/css/frontend.css', array(), EOP_VERSION );
        wp_enqueue_script( 'eop-admin', EOP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'select2' ), EOP_VERSION, true );

        if ( current_user_can( 'manage_options' ) ) {
            $font_css_path = ABSPATH . 'wp-content/plugins/checkout-aireset-master/backend/assets/css/jquery.fontselect.css';
            $font_js_path  = ABSPATH . 'wp-content/plugins/checkout-aireset-master/backend/assets/js/jquery.fontselect.js';

            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_media();
            wp_enqueue_style(
                'eop-settings-admin',
                EOP_PLUGIN_URL . 'assets/css/settings-admin.css',
                array( 'wp-color-picker' ),
                EOP_VERSION
            );

            if ( file_exists( $font_css_path ) ) {
                wp_enqueue_style(
                    'eop-fontselect',
                    content_url( 'plugins/checkout-aireset-master/backend/assets/css/jquery.fontselect.css' ),
                    array(),
                    EOP_VERSION
                );
            }

            wp_enqueue_script( 'wp-color-picker' );

            if ( file_exists( $font_js_path ) ) {
                wp_enqueue_script(
                    'eop-fontselect',
                    content_url( 'plugins/checkout-aireset-master/backend/assets/js/jquery.fontselect.js' ),
                    array( 'jquery' ),
                    EOP_VERSION,
                    true
                );
            }

            wp_enqueue_script(
                'eop-settings-admin',
                EOP_PLUGIN_URL . 'assets/js/settings-admin.js',
                array_filter( array( 'jquery', 'wp-color-picker', 'media-editor', 'media-upload', file_exists( $font_js_path ) ? 'eop-fontselect' : '' ) ),
                EOP_VERSION,
                true
            );

            wp_localize_script(
                'eop-settings-admin',
                'eop_settings_vars',
                array(
                    'has_fontselect'   => file_exists( $font_js_path ),
                    'font_placeholder' => __( 'Escolha uma fonte Google', EOP_TEXT_DOMAIN ),
                    'media_title'      => __( 'Selecionar logo', EOP_TEXT_DOMAIN ),
                    'media_button'     => __( 'Usar esta imagem', EOP_TEXT_DOMAIN ),
                    'remove_logo'      => __( 'Remover logo', EOP_TEXT_DOMAIN ),
                    'select_logo'      => __( 'Selecionar logo', EOP_TEXT_DOMAIN ),
                    'change_logo'      => __( 'Trocar logo', EOP_TEXT_DOMAIN ),
                    'no_logo'          => __( 'Nenhum logo selecionado ainda.', EOP_TEXT_DOMAIN ),
                )
            );
        }

        wp_localize_script( 'eop-admin', 'eop_vars', array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'eop_nonce' ),
            'discount_mode' => EOP_Settings::get( 'discount_mode', 'both' ),
            'initial_view'  => self::normalize_view( isset( $_GET['view'] ) ? wp_unslash( $_GET['view'] ) : '' ),
            'view_url_base' => self::get_view_url(),
            'i18n'          => array(
                'search_product'   => __( 'Buscar produto por nome ou SKU...', EOP_TEXT_DOMAIN ),
                'no_results'       => __( 'Nenhum resultado', EOP_TEXT_DOMAIN ),
                'confirm_remove'   => __( 'Remover este item?', EOP_TEXT_DOMAIN ),
                'missing_products' => __( 'Adicione ao menos um produto.', EOP_TEXT_DOMAIN ),
                'missing_customer' => __( 'Preencha os dados do cliente (nome e CPF/CNPJ).', EOP_TEXT_DOMAIN ),
                'shipping_calculate' => __( 'Buscar opcoes de frete', EOP_TEXT_DOMAIN ),
                'shipping_hide'      => __( 'Fechar entrega e frete', EOP_TEXT_DOMAIN ),
                'shipping_loading'   => __( 'Calculando frete...', EOP_TEXT_DOMAIN ),
                'shipping_missing'   => __( 'Preencha CEP, endereco, cidade e numero para calcular o frete.', EOP_TEXT_DOMAIN ),
                'shipping_select'    => __( 'Selecione uma opcao de frete.', EOP_TEXT_DOMAIN ),
                'shipping_summary_default' => __( 'Clique para calcular com o endereco do cliente.', EOP_TEXT_DOMAIN ),
                'shipping_summary_pending' => __( 'Preencha o endereco e escolha uma opcao de frete.', EOP_TEXT_DOMAIN ),
                'shipping_summary_ready'   => __( 'Escolha a opcao de frete que melhor atende o cliente.', EOP_TEXT_DOMAIN ),
                'shipping_panel_hint'      => __( 'Comece pelo CEP. O sistema tenta preencher o endereco automaticamente.', EOP_TEXT_DOMAIN ),
                'shipping_postcode_loading' => __( 'Buscando endereco pelo CEP...', EOP_TEXT_DOMAIN ),
                'shipping_postcode_found'   => __( 'Endereco encontrado. Confira o numero e o complemento.', EOP_TEXT_DOMAIN ),
                'shipping_postcode_not_found' => __( 'Nao encontramos esse CEP. Preencha o endereco manualmente.', EOP_TEXT_DOMAIN ),
                'shipping_postcode_invalid' => __( 'Digite um CEP valido com 8 numeros.', EOP_TEXT_DOMAIN ),
                'shipping_postcode_error'   => __( 'Nao foi possivel buscar o CEP agora. Continue manualmente.', EOP_TEXT_DOMAIN ),
                'shipping_rates_found'      => __( 'Opcoes encontradas. Escolha a melhor para o cliente.', EOP_TEXT_DOMAIN ),
                'processing'       => __( 'Processando...', EOP_TEXT_DOMAIN ),
                'error'            => __( 'Erro ao criar pedido. Tente novamente.', EOP_TEXT_DOMAIN ),
                'success'          => __( 'Pedido criado com sucesso!', EOP_TEXT_DOMAIN ),
                'submit_label'     => __( 'Finalizar e Gerar PDF', EOP_TEXT_DOMAIN ),
                'nav_new_order'    => __( 'Novo pedido', EOP_TEXT_DOMAIN ),
                'nav_orders'       => __( 'Pedidos', EOP_TEXT_DOMAIN ),
                'nav_settings'     => __( 'Configuracoes', EOP_TEXT_DOMAIN ),
                'nav_license'      => __( 'Licenca', EOP_TEXT_DOMAIN ),
            ),
        ) );
    }

    public static function enqueue_menu_flyout_assets() {
        if ( ! is_admin() ) {
            return;
        }

        wp_enqueue_style(
            'aireset-admin-flyout',
            EOP_PLUGIN_URL . 'assets/css/admin-menu-flyout.css',
            array(),
            EOP_VERSION
        );

        wp_enqueue_script(
            'aireset-admin-flyout',
            EOP_PLUGIN_URL . 'assets/js/admin-menu-flyout.js',
            array(),
            EOP_VERSION,
            true
        );

        $current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        $items = array(
            array(
                'key'   => 'eop-pedidos',
                'label' => __( 'Pedidos', EOP_TEXT_DOMAIN ),
                'icon'  => 'dashicons-list-view',
                'url'   => self::get_view_url( 'orders' ),
            ),
        );

        if ( current_user_can( 'manage_options' ) ) {
            array_unshift(
                $items,
                array(
                    'key'   => 'eop-configuracoes',
                    'label' => __( 'Configuracoes', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-admin-generic',
                    'url'   => self::get_view_url( 'settings' ),
                )
            );

            $items[] = array(
                'key'   => 'eop-license',
                'label' => __( 'Licenca', EOP_TEXT_DOMAIN ),
                'icon'  => 'dashicons-admin-network',
                'url'   => self::get_view_url( 'license' ),
            );
        }

        $config = array(
            'currentPage' => $current_page,
            'anchorPage'  => 'eop-pedido-expresso',
            'menuRoot'    => 'toplevel_page_aireset',
            'title'       => __( 'Pedido Expresso', EOP_TEXT_DOMAIN ),
            'items'       => $items,
        );

        wp_add_inline_script(
            'aireset-admin-flyout',
            'window.airesetAdminFlyouts=window.airesetAdminFlyouts||[];'
            . 'window.airesetAdminFlyouts.push(' . wp_json_encode( $config ) . ');',
            'before'
        );
    }

    /**
     * Render the admin page.
     */
    public static function render_page() {
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
        }

        include EOP_PLUGIN_DIR . 'templates/admin-page.php';
    }
}
