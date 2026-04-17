<?php
defined( 'ABSPATH' ) || exit;

class EOP_WC_PDF_Integration {

    use EOP_License_Guard;

    public static function init() {
        if ( ! self::_resolve_env_config() ) {
            return;
        }

        add_filter( 'woocommerce_email_attachments', array( __CLASS__, 'filter_email_attachments' ), 10, 4 );
        add_filter( 'woocommerce_admin_order_actions', array( __CLASS__, 'filter_admin_order_actions' ), 10, 2 );
        add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'filter_my_account_actions' ), 10, 2 );
        add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'render_order_details_downloads' ) );
        add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
        add_action( 'add_meta_boxes_woocommerce_page_wc-orders', array( __CLASS__, 'register_hpos_meta_boxes' ) );
        add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'filter_classic_order_columns' ), 20 );
        add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_classic_order_column' ), 20, 2 );
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'filter_hpos_order_columns' ), 20 );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_hpos_order_column' ), 20, 2 );
        add_filter( 'woocommerce_shop_order_search_fields', array( __CLASS__, 'filter_search_fields' ) );
        add_filter( 'woocommerce_order_table_search_query_meta_keys', array( __CLASS__, 'filter_search_fields' ) );
    }

    public static function filter_email_attachments( $attachments, $email_id, $order, $email ) {
        if ( ! $order instanceof WC_Order || ! EOP_Document_Manager::is_expresso_order( $order ) ) {
            return $attachments;
        }

        $document_type = EOP_Document_Manager::get_document_type_for_order( $order );
        $settings      = EOP_PDF_Settings::get_all();

        if ( 'yes' !== ( $settings[ $document_type . '_attach_email' ] ?? 'no' ) || ! EOP_Document_Manager::is_document_enabled( $order, $document_type ) ) {
            return $attachments;
        }

        $path = EOP_Document_Manager::get_cached_pdf_path( $order, $document_type );

        if ( '' !== $path ) {
            $attachments[] = $path;
        }

        return array_values( array_unique( array_filter( $attachments ) ) );
    }

    public static function filter_admin_order_actions( $actions, $order ) {
        if ( ! $order instanceof WC_Order || ! EOP_Document_Manager::is_expresso_order( $order ) ) {
            return $actions;
        }

        if ( EOP_Document_Manager::is_document_enabled( $order, 'order' ) ) {
            $actions['eop_order_pdf'] = array(
                'url'    => EOP_Document_Manager::get_pdf_document_url( $order, 'order' ),
                'name'   => __( 'Abrir PDF do pedido', EOP_TEXT_DOMAIN ),
                'action' => 'view eop-order-pdf',
            );
        }

        if ( EOP_Document_Manager::is_document_enabled( $order, 'proposal' ) ) {
            $actions['eop_proposal_pdf'] = array(
                'url'    => EOP_Document_Manager::get_pdf_document_url( $order, 'proposal' ),
                'name'   => __( 'Abrir PDF da proposta', EOP_TEXT_DOMAIN ),
                'action' => 'view eop-proposal-pdf',
            );
        }

        return $actions;
    }

    public static function filter_my_account_actions( $actions, $order ) {
        if ( ! $order instanceof WC_Order || ! EOP_Document_Manager::can_customer_download( $order, 'order' ) ) {
            return $actions;
        }

        $actions['eop_order_pdf'] = array(
            'url'  => EOP_Document_Manager::get_pdf_document_url( $order, 'order' ),
            'name' => __( 'Baixar PDF', EOP_TEXT_DOMAIN ),
        );

        return $actions;
    }

    public static function render_order_details_downloads( $order ) {
        if ( ! $order instanceof WC_Order || ! EOP_Document_Manager::can_customer_download( $order, 'order' ) ) {
            return;
        }
        ?>
        <section class="woocommerce-order-downloads eop-order-downloads">
            <h2><?php esc_html_e( 'Documentos PDF', EOP_TEXT_DOMAIN ); ?></h2>
            <p>
                <a class="button" href="<?php echo esc_url( EOP_Document_Manager::get_pdf_document_url( $order, 'order' ) ); ?>">
                    <?php esc_html_e( 'Baixar PDF do pedido', EOP_TEXT_DOMAIN ); ?>
                </a>
            </p>
        </section>
        <?php
    }

    public static function register_meta_boxes() {
        add_meta_box(
            'eop-pdf-documents',
            __( 'Documentos PDF', EOP_TEXT_DOMAIN ),
            array( __CLASS__, 'render_order_meta_box' ),
            'shop_order',
            'side',
            'default'
        );
    }

    public static function register_hpos_meta_boxes() {
        $screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'woocommerce_page_wc-orders';

        add_meta_box(
            'eop-pdf-documents',
            __( 'Documentos PDF', EOP_TEXT_DOMAIN ),
            array( __CLASS__, 'render_order_meta_box' ),
            $screen,
            'side',
            'default'
        );
    }

    public static function render_order_meta_box( $post_or_order_object ) {
        $order = $post_or_order_object instanceof WC_Order ? $post_or_order_object : wc_get_order( is_object( $post_or_order_object ) && isset( $post_or_order_object->ID ) ? $post_or_order_object->ID : 0 );

        if ( ! $order instanceof WC_Order || ! EOP_Document_Manager::is_expresso_order( $order ) ) {
            echo '<p>' . esc_html__( 'Nenhum documento PDF nativo disponivel para este pedido.', EOP_TEXT_DOMAIN ) . '</p>';
            return;
        }

        $record = EOP_Document_Manager::get_document_record( $order );
        ?>
        <div class="eop-order-pdf-box">
            <?php if ( ! empty( $record['order']['url'] ) ) : ?>
                <p><a class="button button-primary" target="_blank" href="<?php echo esc_url( $record['order']['url'] ); ?>"><?php esc_html_e( 'Abrir PDF do pedido', EOP_TEXT_DOMAIN ); ?></a></p>
            <?php endif; ?>
            <?php if ( ! empty( $record['proposal']['url'] ) ) : ?>
                <p><a class="button" target="_blank" href="<?php echo esc_url( $record['proposal']['url'] ); ?>"><?php esc_html_e( 'Abrir PDF da proposta', EOP_TEXT_DOMAIN ); ?></a></p>
            <?php endif; ?>
            <?php if ( ! empty( $record['proposal']['public_url'] ) ) : ?>
                <p><a class="button" target="_blank" href="<?php echo esc_url( $record['proposal']['public_url'] ); ?>"><?php esc_html_e( 'Abrir PDF publico', EOP_TEXT_DOMAIN ); ?></a></p>
            <?php endif; ?>
            <?php if ( ! empty( $record['order']['generated_at'] ) || ! empty( $record['proposal']['generated_at'] ) ) : ?>
                <p><small><?php esc_html_e( 'Ultima geracao registrada no proprio pedido.', EOP_TEXT_DOMAIN ); ?></small></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function filter_classic_order_columns( $columns ) {
        return self::inject_document_column( $columns );
    }

    public static function render_classic_order_column( $column, $post_id ) {
        if ( 'eop_pdf_document' !== $column ) {
            return;
        }

        $order = wc_get_order( $post_id );
        self::render_document_column_markup( $order );
    }

    public static function filter_hpos_order_columns( $columns ) {
        return self::inject_document_column( $columns );
    }

    public static function render_hpos_order_column( $column, $order ) {
        if ( 'eop_pdf_document' !== $column ) {
            return;
        }

        self::render_document_column_markup( $order instanceof WC_Order ? $order : wc_get_order( $order ) );
    }

    public static function filter_search_fields( $fields ) {
        $fields   = is_array( $fields ) ? $fields : array();
        $fields[] = '_eop_order_document_number';
        $fields[] = '_eop_proposal_document_number';

        return array_values( array_unique( $fields ) );
    }

    private static function inject_document_column( $columns ) {
        $updated = array();

        foreach ( $columns as $key => $label ) {
            $updated[ $key ] = $label;

            if ( 'order_total' === $key || 'wc_actions' === $key ) {
                $updated['eop_pdf_document'] = __( 'Documento PDF', EOP_TEXT_DOMAIN );
            }
        }

        if ( ! isset( $updated['eop_pdf_document'] ) ) {
            $updated['eop_pdf_document'] = __( 'Documento PDF', EOP_TEXT_DOMAIN );
        }

        return $updated;
    }

    private static function render_document_column_markup( $order ) {
        if ( ! $order instanceof WC_Order || ! EOP_Document_Manager::is_expresso_order( $order ) ) {
            echo '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        $document_type = EOP_Document_Manager::get_document_type_for_order( $order );
        $record        = EOP_Document_Manager::get_document_record( $order );
        $url           = $record[ $document_type ]['url'] ?? '';
        $number        = EOP_Document_Manager::get_document_number_label( $order, $document_type, false );

        if ( '' === $number ) {
            echo '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        if ( $url ) {
            echo '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $number ) . '</a>';
            return;
        }

        echo esc_html( $number );
    }
}
