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
                'https://aireset.com.br/wp-content/logo_para_clientes/icone-preto.png',
                3
            );
            $submenu['aireset'] = isset( $submenu['aireset'] ) ? $submenu['aireset'] : array();

            add_action( 'admin_head', 'aireset_parent_menu_icon_css' );
            function aireset_parent_menu_icon_css() {
                echo '<style>#toplevel_page_aireset .wp-menu-image img{width:24px!important;height:auto!important;padding:6px 0 0!important;}</style>';
            }
            if ( isset( $menu[4] ) ) {
                unset( $menu[4] );
            }
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
            return 'settings-general-config';
        }

        return 'new-order';
    }

    public static function get_available_views() {
        $views = array(
            'new-order' => current_user_can( 'edit_shop_orders' ),
            'orders'    => current_user_can( 'edit_shop_orders' ),
            'pdf'       => current_user_can( 'edit_shop_orders' ),
            'settings-store-info'        => current_user_can( 'manage_options' ),
            'settings-general-config'    => current_user_can( 'manage_options' ),
            'settings-confirmation-flow' => current_user_can( 'manage_options' ),
            'settings-order-link-style'  => current_user_can( 'manage_options' ),
            'settings-proposal-link-style' => current_user_can( 'manage_options' ),
            'settings-texts'             => current_user_can( 'manage_options' ),
            'documentation'              => current_user_can( 'manage_options' ),
            'license'                    => current_user_can( 'manage_options' ),
        );

        return array_keys( array_filter( $views ) );
    }

    public static function normalize_view( $view ) {
        $view = sanitize_key( (string) $view );

        $legacy_map = array(
            'settings'        => 'settings-general-config',
            'settings-styles' => 'settings-order-link-style',
        );

        if ( isset( $legacy_map[ $view ] ) ) {
            $view = $legacy_map[ $view ];
        }

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

    public static function get_view_urls() {
        $urls = array();

        foreach ( self::get_available_views() as $view ) {
            $args = array();

            if ( 'pdf' === $view ) {
                $args['pdf_tab'] = class_exists( 'EOP_PDF_Admin_Page' ) ? EOP_PDF_Admin_Page::get_current_tab() : 'display';
            }

            $urls[ $view ] = self::get_view_url( $view, $args );
        }

        return $urls;
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

        $wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : EOP_VERSION;

        // Select2 (shipped with WooCommerce).
        wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), $wc_version );
        wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', array( 'jquery' ), $wc_version, true );

        wp_enqueue_style( 'eop-admin', EOP_PLUGIN_URL . 'assets/css/admin.css', array( 'select2' ), EOP_VERSION );
        wp_enqueue_style( 'eop-frontend', EOP_PLUGIN_URL . 'assets/css/frontend.css', array(), EOP_VERSION );
        wp_enqueue_style( 'eop-pdf-admin', EOP_PLUGIN_URL . 'assets/css/pdf-admin.css', array( 'eop-admin' ), EOP_VERSION );
        wp_enqueue_script( 'eop-admin', EOP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'select2' ), EOP_VERSION, true );

        $font_css_path = ABSPATH . 'wp-content/plugins/checkout-aireset-master/backend/assets/css/jquery.fontselect.css';
        $font_js_path  = ABSPATH . 'wp-content/plugins/checkout-aireset-master/backend/assets/js/jquery.fontselect.js';

        wp_enqueue_media();
        wp_enqueue_style( 'eop-coloris', EOP_PLUGIN_URL . 'assets/css/coloris.min.css', array(), EOP_VERSION );
        wp_enqueue_style(
            'eop-settings-admin',
            EOP_PLUGIN_URL . 'assets/css/settings-admin.css',
            array( 'eop-admin', 'eop-coloris' ),
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

        wp_enqueue_script( 'eop-coloris', EOP_PLUGIN_URL . 'assets/js/coloris.min.js', array(), EOP_VERSION, true );

        if ( function_exists( 'wp_enqueue_editor' ) ) {
            wp_enqueue_editor();
        }

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
            array_filter( array( 'jquery', 'select2', 'eop-coloris', 'media-editor', 'media-upload', 'wp-editor', file_exists( $font_js_path ) ? 'eop-fontselect' : '' ) ),
            EOP_VERSION,
            true
        );

        wp_localize_script(
            'eop-settings-admin',
            'eop_settings_vars',
            EOP_Settings::get_settings_admin_localization( file_exists( $font_js_path ) )
        );

        wp_localize_script( 'eop-admin', 'eop_vars', array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'eop_nonce' ),
            'rest_url'      => esc_url_raw( rest_url( trailingslashit( apply_filters( 'eop_post_confirmation_rest_namespace', 'aireset-expresso-order/v1' ) ) ) ),
            'rest_nonce'    => wp_create_nonce( 'wp_rest' ),
            'cache_namespace' => EOP_VERSION,
            'discount_mode' => EOP_Settings::get( 'discount_mode', 'both' ),
            'initial_view'  => self::normalize_view( isset( $_GET['view'] ) ? wp_unslash( $_GET['view'] ) : '' ),
            'view_url_base' => self::get_view_url(),
            'view_urls'     => self::get_view_urls(),
            'i18n'          => array(
                'search_product'   => __( 'Buscar produto por nome ou SKU...', EOP_TEXT_DOMAIN ),
                'no_items'         => __( 'Nenhum produto adicionado.', EOP_TEXT_DOMAIN ),
                'label_price'      => __( 'Preco', EOP_TEXT_DOMAIN ),
                'label_quantity'   => __( 'Qtd', EOP_TEXT_DOMAIN ),
                'label_discount'   => __( 'Desconto', EOP_TEXT_DOMAIN ),
                'label_discounted_unit_price' => __( 'Valor c/ desconto', EOP_TEXT_DOMAIN ),
                'label_subtotal'   => __( 'Subtotal', EOP_TEXT_DOMAIN ),
                'default_discount_placeholder_percent' => __( '10', EOP_TEXT_DOMAIN ),
                'default_discount_placeholder_fixed'   => __( '10,00', EOP_TEXT_DOMAIN ),
                'default_discount_placeholder_both'    => __( '10 ou 10%', EOP_TEXT_DOMAIN ),
                'default_discount_help_percent'        => __( 'Informe somente porcentagem (%).', EOP_TEXT_DOMAIN ),
                'default_discount_help_fixed'          => __( 'Informe somente valor fixo (R$).', EOP_TEXT_DOMAIN ),
                'default_discount_help_both'           => __( 'Aceita valor fixo ou porcentagem.', EOP_TEXT_DOMAIN ),
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
                'loading'          => __( 'Carregando...', EOP_TEXT_DOMAIN ),
                'error'            => __( 'Erro ao criar pedido. Tente novamente.', EOP_TEXT_DOMAIN ),
                'success'          => __( 'Pedido criado com sucesso!', EOP_TEXT_DOMAIN ),
                'submit_label'     => __( 'Finalizar e Gerar PDF', EOP_TEXT_DOMAIN ),
                'edit_title'       => __( 'Editando pedido', EOP_TEXT_DOMAIN ),
                'edit_submit'      => __( 'Salvar alteracoes', EOP_TEXT_DOMAIN ),
                'edit_loaded'      => __( 'Pedido carregado no painel para edicao.', EOP_TEXT_DOMAIN ),
                'edit_error'       => __( 'Nao foi possivel abrir este pedido para edicao.', EOP_TEXT_DOMAIN ),
                'edit_cancel'      => __( 'Edicao cancelada.', EOP_TEXT_DOMAIN ),
                'nav_new_order'    => __( 'Novo pedido', EOP_TEXT_DOMAIN ),
                'nav_orders'       => __( 'Pedidos', EOP_TEXT_DOMAIN ),
                'nav_pdf'          => __( 'PDF', EOP_TEXT_DOMAIN ),
                'nav_settings'     => __( 'Configuracoes', EOP_TEXT_DOMAIN ),
                'nav_license'      => __( 'Licenca', EOP_TEXT_DOMAIN ),
                'orders_loading'   => __( 'Carregando pedidos...', EOP_TEXT_DOMAIN ),
                'orders_error'     => __( 'Nao foi possivel carregar os pedidos agora.', EOP_TEXT_DOMAIN ),
                'orders_empty'     => __( 'Nenhum pedido encontrado para este filtro.', EOP_TEXT_DOMAIN ),
                'orders_previous'  => __( 'Anterior', EOP_TEXT_DOMAIN ),
                'orders_next'      => __( 'Proxima', EOP_TEXT_DOMAIN ),
                'orders_of'        => __( 'pedido(s) encontrado(s)', EOP_TEXT_DOMAIN ),
                'orders_created_by' => __( 'Vendedor', EOP_TEXT_DOMAIN ),
                'orders_public'    => __( 'Link do cliente', EOP_TEXT_DOMAIN ),
                'orders_pdf'       => __( 'PDF', EOP_TEXT_DOMAIN ),
                'orders_edit'      => __( 'Editar aqui', EOP_TEXT_DOMAIN ),
                'orders_flow_title' => __( 'Fluxo complementar', EOP_TEXT_DOMAIN ),
                'orders_flow_contract' => __( 'Contrato', EOP_TEXT_DOMAIN ),
                'orders_flow_fields' => __( 'Campos', EOP_TEXT_DOMAIN ),
                'orders_flow_attachment' => __( 'Anexo', EOP_TEXT_DOMAIN ),
                'orders_flow_products' => __( 'Produtos', EOP_TEXT_DOMAIN ),
                'orders_flow_uploaded' => __( 'Enviado', EOP_TEXT_DOMAIN ),
                'orders_flow_optional' => __( 'Opcional', EOP_TEXT_DOMAIN ),
                'post_flow_loading' => __( 'Carregando dados complementares da proposta...', EOP_TEXT_DOMAIN ),
                'post_flow_unavailable_edit' => __( 'O resumo complementar aparece quando um pedido existente entra em modo de edicao.', EOP_TEXT_DOMAIN ),
                'post_flow_not_available' => __( 'Este pedido nao esta usando o fluxo complementar da proposta.', EOP_TEXT_DOMAIN ),
                'post_flow_stage_inactive' => __( 'Inativo', EOP_TEXT_DOMAIN ),
                'post_flow_pending' => __( 'Pendente', EOP_TEXT_DOMAIN ),
                'post_flow_contract_pending' => __( 'Aceite contratual pendente.', EOP_TEXT_DOMAIN ),
                'post_flow_contract_done' => __( 'Aceite registrado.', EOP_TEXT_DOMAIN ),
                'post_flow_documents_empty' => __( 'Nenhum dado do pedido preenchido no WooCommerce ate agora.', EOP_TEXT_DOMAIN ),
                'post_flow_signature_documents_empty' => __( 'Nenhum documento para assinatura foi gerado ainda.', EOP_TEXT_DOMAIN ),
                'post_flow_attachment_missing' => __( 'Nenhum anexo registrado.', EOP_TEXT_DOMAIN ),
                'post_flow_attachment_done' => __( 'Anexo registrado com sucesso.', EOP_TEXT_DOMAIN ),
                'post_flow_products_empty' => __( 'Nenhuma personalizacao registrada ate agora.', EOP_TEXT_DOMAIN ),
                'post_flow_open_public' => __( 'Abrir link publico', EOP_TEXT_DOMAIN ),
                'post_flow_download_pdf' => __( 'Baixar PDF complementar', EOP_TEXT_DOMAIN ),
                'post_flow_stat_stage' => __( 'Etapa atual', EOP_TEXT_DOMAIN ),
                'post_flow_stat_documents' => __( 'Dados do pedido', EOP_TEXT_DOMAIN ),
                'post_flow_stat_attachment' => __( 'Anexo', EOP_TEXT_DOMAIN ),
                'post_flow_stat_products' => __( 'Produtos', EOP_TEXT_DOMAIN ),
                'post_flow_summary_ready' => __( 'Payload estruturado pronto para PDF, admin e integracoes futuras.', EOP_TEXT_DOMAIN ),
                'post_flow_completed_at' => __( 'Concluido em', EOP_TEXT_DOMAIN ),
                'post_flow_locked' => __( 'Bloqueado', EOP_TEXT_DOMAIN ),
                'post_flow_customized' => __( 'Personalizado', EOP_TEXT_DOMAIN ),
                'focus_mode_enter' => __( 'Ocultar interface do WordPress', EOP_TEXT_DOMAIN ),
                'focus_mode_exit' => __( 'Voltar a mostrar interface do WordPress', EOP_TEXT_DOMAIN ),
                'focus_mode_label_enter' => __( 'Modo foco', EOP_TEXT_DOMAIN ),
                'focus_mode_label_exit' => __( 'Sair do foco', EOP_TEXT_DOMAIN ),
                'lazy_loading_view' => __( 'Carregando tela...', EOP_TEXT_DOMAIN ),
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
        $pdf_children = array(
            array(
                'key'   => 'eop-view-pdf-display',
                'label' => __( 'Configuracoes de exibicao', EOP_TEXT_DOMAIN ),
                'icon'  => 'dashicons-admin-home',
                'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'display' ) ),
                'query' => array(
                    'page'    => 'eop-pedido-expresso',
                    'view'    => 'pdf',
                    'pdf_tab' => 'display',
                ),
            ),
        );

        $items = array(
            array(
                'key'   => 'eop-view-new-order',
                'label' => __( 'Novo pedido', EOP_TEXT_DOMAIN ),
                'icon'  => 'dashicons-cart',
                'url'   => self::get_view_url( 'new-order' ),
                'query' => array(
                    'page' => 'eop-pedido-expresso',
                    'view' => 'new-order',
                ),
            ),
            array(
                'key'   => 'eop-view-orders',
                'label' => __( 'Pedidos', EOP_TEXT_DOMAIN ),
                'icon'  => 'dashicons-list-view',
                'url'   => self::get_view_url( 'orders' ),
                'query' => array(
                    'page' => 'eop-pedido-expresso',
                    'view' => 'orders',
                ),
            ),
            array(
                'key'   => 'eop-view-pdf',
                'label' => __( 'PDF', EOP_TEXT_DOMAIN ),
                'icon'  => 'dashicons-media-document',
                'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'display' ) ),
                'query' => array(
                    'page'    => 'eop-pedido-expresso',
                    'view'    => 'pdf',
                    'pdf_tab' => 'display',
                ),
                'children' => $pdf_children,
            ),
        );

        if ( current_user_can( 'manage_options' ) ) {
            $general_children = array(
                array(
                    'key'   => 'eop-view-settings-store-info',
                    'label' => __( 'Informacoes sobre a loja', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-store',
                    'url'   => self::get_view_url( 'settings-store-info' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-store-info',
                    ),
                ),
                array(
                    'key'   => 'eop-view-settings-general-config',
                    'label' => __( 'Configuracoes Gerais', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-admin-settings',
                    'url'   => self::get_view_url( 'settings-general-config' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-general-config',
                    ),
                ),
                array(
                    'key'   => 'eop-view-settings-confirmation-flow',
                    'label' => __( 'Fluxo de Confirmacao', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-yes-alt',
                    'url'   => self::get_view_url( 'settings-confirmation-flow' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-confirmation-flow',
                    ),
                ),
                array(
                    'key'   => 'eop-view-settings-order-link-style',
                    'label' => __( 'Estilo do Link do Pedido', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-art',
                    'url'   => self::get_view_url( 'settings-order-link-style' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-order-link-style',
                    ),
                ),
                array(
                    'key'   => 'eop-view-settings-proposal-link-style',
                    'label' => __( 'Estilo do Link de Proposta', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-format-image',
                    'url'   => self::get_view_url( 'settings-proposal-link-style' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-proposal-link-style',
                    ),
                ),
                array(
                    'key'   => 'eop-view-settings-texts',
                    'label' => __( 'Textos', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-edit-large',
                    'url'   => self::get_view_url( 'settings-texts' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-texts',
                    ),
                ),
            );

            $pdf_children = array_merge(
                $pdf_children,
                array(
                    array(
                        'key'   => 'eop-view-pdf-order-settings',
                        'label' => __( 'Configuracoes do Pedido', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-media-text',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'order-settings' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'order-settings',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-order-columns',
                        'label' => __( 'Colunas do Pedido', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-editor-table',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'order-columns' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'order-columns',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-order-texts',
                        'label' => __( 'Textos do Pedido', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-edit-page',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'order-texts' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'order-texts',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-order-style',
                        'label' => __( 'Estilo do Pedido', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-art',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'order-style' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'order-style',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-proposal-settings',
                        'label' => __( 'Configuracoes da Proposta', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-media-default',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'proposal-settings' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'proposal-settings',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-proposal-columns',
                        'label' => __( 'Colunas da Proposta', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-editor-table',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'proposal-columns' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'proposal-columns',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-proposal-texts',
                        'label' => __( 'Textos da Proposta', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-edit-page',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'proposal-texts' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'proposal-texts',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-proposal-style',
                        'label' => __( 'Estilo da Proposta', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-art',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'proposal-style' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'proposal-style',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-edocuments',
                        'label' => __( 'Documentos eletronicos', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-media-spreadsheet',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'edocuments' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'edocuments',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-advanced',
                        'label' => __( 'Avancado', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-admin-tools',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'advanced' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'advanced',
                        ),
                    ),
                )
            );

            $items = array_merge(
                array(
                    array(
                        'key'   => 'eop-view-general',
                        'label' => __( 'Geral', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-admin-generic',
                        'url'   => self::get_view_url( 'settings-general-config' ),
                        'query' => array(
                            'page' => 'eop-pedido-expresso',
                            'view' => 'settings-general-config',
                        ),
                        'children' => $general_children,
                    ),
                ),
                $items,
                array(
                    array(
                        'key'   => 'eop-view-documentation',
                        'label' => __( 'Documentacao', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-book-alt',
                        'url'   => self::get_view_url( 'documentation' ),
                        'query' => array(
                            'page' => 'eop-pedido-expresso',
                            'view' => 'documentation',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-license',
                        'label' => __( 'Licenca', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-admin-network',
                        'url'   => self::get_view_url( 'license' ),
                        'query' => array(
                            'page' => 'eop-pedido-expresso',
                            'view' => 'license',
                        ),
                    ),
                )
            );

            foreach ( $items as $index => $item ) {
                if ( 'eop-view-pdf' === $item['key'] ) {
                    $items[ $index ]['children'] = $pdf_children;
                    break;
                }
            }
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
