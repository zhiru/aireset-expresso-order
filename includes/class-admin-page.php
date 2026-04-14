<?php
defined( 'ABSPATH' ) || exit;

class EOP_Admin_Page {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    /**
     * Register admin menu page.
     */
    public static function register_page() {
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
        wp_enqueue_script( 'eop-admin', EOP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'select2' ), EOP_VERSION, true );

        wp_localize_script( 'eop-admin', 'eop_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'eop_nonce' ),
            'i18n'     => array(
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
            ),
        ) );
    }

    /**
     * Render the admin page.
     */
    public static function render_page() {
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=eop-configuracoes' ) );
        exit;
    }
}
