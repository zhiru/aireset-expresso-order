<?php
defined( 'ABSPATH' ) || exit;

class EOP_Document_Manager {

    use EOP_License_Guard;

    public static function init() {
        if ( ! self::_resolve_env_config() ) {
            return;
        }

        add_action( 'admin_post_eop_download_pdf', array( __CLASS__, 'handle_download' ) );
        add_action( 'admin_post_nopriv_eop_download_pdf', array( __CLASS__, 'handle_download' ) );
    }

    public static function get_pdf_document_url( WC_Order $order, $document_type = '', $force_download = false ) {
        $document_type = self::normalize_document_type( $document_type ? $document_type : self::detect_document_type( $order ) );

        return add_query_arg(
            array(
                'action'   => 'eop_download_pdf',
                'order_id' => $order->get_id(),
                'document' => $document_type,
                'download' => $force_download ? '1' : '0',
                '_wpnonce' => wp_create_nonce( 'eop_download_pdf_' . $order->get_id() . '_' . $document_type ),
            ),
            admin_url( 'admin-post.php' )
        );
    }

    public static function is_expresso_order( $order ) {
        return $order instanceof WC_Order && 'aireset-expresso-order' === (string) $order->get_created_via();
    }

    public static function get_document_type_for_order( WC_Order $order ) {
        return self::detect_document_type( $order );
    }

    public static function is_document_enabled( WC_Order $order, $document_type = '' ) {
        if ( ! self::is_expresso_order( $order ) ) {
            return false;
        }

        $document_type = self::normalize_document_type( $document_type ? $document_type : self::detect_document_type( $order ) );
        $settings      = self::get_pdf_settings();

        if ( 'proposal' === $document_type && 'yes' !== ( $settings['proposal_public_pdf'] ?? 'yes' ) && 'yes' === (string) $order->get_meta( '_eop_is_proposal', true ) ) {
            return 'yes' === ( $settings['proposal_enabled'] ?? 'yes' );
        }

        return 'yes' === ( $settings[ $document_type . '_enabled' ] ?? 'yes' );
    }

    public static function can_customer_download( WC_Order $order, $document_type = '' ) {
        if ( ! is_user_logged_in() || ! self::is_expresso_order( $order ) ) {
            return false;
        }

        $document_type = self::normalize_document_type( $document_type ? $document_type : self::detect_document_type( $order ) );
        $settings      = self::get_pdf_settings();
        $user_id       = get_current_user_id();
        $customer_id   = absint( $order->get_customer_id() );

        if ( $customer_id <= 0 || $customer_id !== $user_id ) {
            return false;
        }

        if ( 'proposal' === $document_type ) {
            return false;
        }

        return self::is_document_enabled( $order, $document_type ) && 'yes' === ( $settings['order_myaccount_download'] ?? 'no' );
    }

    public static function can_public_proposal_download( WC_Order $order ) {
        $settings = self::get_pdf_settings();

        return self::is_expresso_order( $order )
            && 'yes' === (string) $order->get_meta( '_eop_is_proposal', true )
            && 'yes' === ( $settings['proposal_enabled'] ?? 'yes' )
            && 'yes' === ( $settings['proposal_public_pdf'] ?? 'yes' );
    }

    public static function get_public_document_url( WC_Order $order, $force_download = false ) {
        $token = (string) $order->get_meta( '_eop_public_token', true );

        if ( '' === $token || ! self::can_public_proposal_download( $order ) ) {
            return '';
        }

        return add_query_arg(
            array(
                'action'   => 'eop_download_pdf',
                'document' => 'proposal',
                'download' => $force_download ? '1' : '0',
                'token'    => rawurlencode( $token ),
            ),
            admin_url( 'admin-post.php' )
        );
    }

    public static function get_document_record( WC_Order $order ) {
        return array(
            'order' => array(
                'url'          => self::is_document_enabled( $order, 'order' ) ? self::get_pdf_document_url( $order, 'order' ) : '',
                'generated_at' => (string) $order->get_meta( '_eop_pdf_order_generated_at', true ),
                'generated_by' => absint( $order->get_meta( '_eop_pdf_order_generated_by', true ) ),
            ),
            'proposal' => array(
                'url'          => self::is_document_enabled( $order, 'proposal' ) ? self::get_pdf_document_url( $order, 'proposal' ) : '',
                'public_url'   => self::get_public_document_url( $order ),
                'generated_at' => (string) $order->get_meta( '_eop_pdf_proposal_generated_at', true ),
                'generated_by' => absint( $order->get_meta( '_eop_pdf_proposal_generated_by', true ) ),
            ),
        );
    }

    public static function get_document_number_label( WC_Order $order, $document_type = '', $create = false ) {
        if ( ! self::is_expresso_order( $order ) ) {
            return '';
        }

        $document_type = self::normalize_document_type( $document_type ? $document_type : self::detect_document_type( $order ) );

        return self::get_document_number( $order, $document_type, (bool) $create );
    }

    public static function get_document_display_settings( $document_type = 'order' ) {
        $settings = self::get_pdf_settings();
        $prefix   = 'proposal' === self::normalize_document_type( $document_type ) ? 'proposal' : 'order';

        return array(
            'show_email'          => $settings[ $prefix . '_show_email' ] ?? 'yes',
            'show_phone'          => $settings[ $prefix . '_show_phone' ] ?? 'yes',
            'show_shipping'       => $settings[ $prefix . '_show_shipping' ] ?? 'yes',
            'show_billing'        => $settings[ $prefix . '_show_billing' ] ?? 'no',
            'show_notes'          => $settings[ $prefix . '_show_notes' ] ?? 'yes',
            'show_sku'            => $settings[ $prefix . '_show_sku' ] ?? 'yes',
            'show_quantity'       => $settings[ $prefix . '_show_quantity' ] ?? 'yes',
            'show_unit_price'     => $settings[ $prefix . '_show_unit_price' ] ?? 'yes',
            'show_discount'       => $settings[ $prefix . '_show_discount' ] ?? 'yes',
            'show_line_total'     => $settings[ $prefix . '_show_line_total' ] ?? 'yes',
            'show_total_subtotal' => $settings[ $prefix . '_show_total_subtotal' ] ?? 'yes',
            'show_total_shipping' => $settings[ $prefix . '_show_total_shipping' ] ?? 'yes',
            'show_total_discount' => $settings[ $prefix . '_show_total_discount' ] ?? 'yes',
            'show_total_total'    => $settings[ $prefix . '_show_total_total' ] ?? 'yes',
        );
    }

    public static function get_document_item_columns( $document_type = 'order' ) {
        $config  = self::get_document_display_settings( $document_type );
        $columns = array();

        if ( 'yes' === $config['show_quantity'] ) {
            $columns[] = array(
                'key'   => 'quantity',
                'label' => __( 'Quantidade', EOP_TEXT_DOMAIN ),
            );
        }

        if ( 'yes' === $config['show_unit_price'] ) {
            $columns[] = array(
                'key'   => 'unit_price',
                'label' => __( 'Valor unitario', EOP_TEXT_DOMAIN ),
            );
        }

        if ( 'yes' === $config['show_discount'] ) {
            $columns[] = array(
                'key'   => 'discount',
                'label' => __( 'Desconto aplicado', EOP_TEXT_DOMAIN ),
            );
        }

        if ( 'yes' === $config['show_line_total'] ) {
            $columns[] = array(
                'key'   => 'line_total',
                'label' => __( 'Total', EOP_TEXT_DOMAIN ),
            );
        }

        return $columns;
    }

    public static function get_document_total_rows( $totals, $document_type = 'order' ) {
        $config = self::get_document_display_settings( $document_type );
        $rows   = array();

        if ( 'yes' === $config['show_total_subtotal'] ) {
            $rows[] = array(
                'key'   => 'subtotal',
                'label' => __( 'Subtotal', EOP_TEXT_DOMAIN ),
                'raw'   => (float) $totals['items_subtotal'],
                'value' => wc_price( (float) $totals['items_subtotal'] ),
                'class' => '',
            );
        }

        if ( 'yes' === $config['show_total_shipping'] ) {
            $rows[] = array(
                'key'   => 'shipping',
                'label' => __( 'Frete', EOP_TEXT_DOMAIN ),
                'raw'   => (float) $totals['shipping_total'],
                'value' => wc_price( (float) $totals['shipping_total'] ),
                'class' => '',
            );
        }

        if ( 'yes' === $config['show_total_discount'] ) {
            $rows[] = array(
                'key'   => 'discount',
                'label' => __( 'Desconto', EOP_TEXT_DOMAIN ),
                'raw'   => (float) $totals['discount_total'] * -1,
                'value' => wc_price( (float) $totals['discount_total'] * -1 ),
                'class' => '',
            );
        }

        if ( 'yes' === $config['show_total_total'] ) {
            $rows[] = array(
                'key'   => 'total',
                'label' => __( 'Total', EOP_TEXT_DOMAIN ),
                'raw'   => (float) $totals['grand_total'],
                'value' => wc_price( (float) $totals['grand_total'] ),
                'class' => 'is-grand',
            );
        }

        return $rows;
    }

    public static function get_cached_pdf_path( WC_Order $order, $document_type = '', $force_regenerate = false ) {
        $document_type = self::normalize_document_type( $document_type ? $document_type : self::detect_document_type( $order ) );

        if ( ! self::is_document_enabled( $order, $document_type ) ) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit( $upload_dir['basedir'] ) . 'eop-pdf';

        if ( ! wp_mkdir_p( $base_dir ) ) {
            return '';
        }

        $version = (string) $order->get_meta( '_eop_pdf_' . $document_type . '_generated_at', true );
        $stamp   = '' !== $version ? sanitize_file_name( str_replace( array( ':', ' ' ), '-', $version ) ) : 'draft';
        $path    = trailingslashit( $base_dir ) . sanitize_file_name( $document_type . '-' . $order->get_id() . '-' . $stamp . '.pdf' );

        if ( ! $force_regenerate && file_exists( $path ) ) {
            return $path;
        }

        $binary = self::maybe_build_browser_pdf_document( $order, $document_type );

        if ( '' === $binary ) {
            $binary = self::build_pdf_document( $order, $document_type );
        }
        $result = file_put_contents( $path, $binary );

        if ( false === $result ) {
            return '';
        }

        return $path;
    }

    public static function get_recent_orders( $limit = 20 ) {
        return wc_get_orders(
            array(
                'limit'       => max( 1, absint( $limit ) ),
                'status'      => array_keys( wc_get_order_statuses() ),
                'created_via' => 'aireset-expresso-order',
                'orderby'     => 'date',
                'order'       => 'DESC',
            )
        );
    }

    public static function get_preview_order( $order_id = 0 ) {
        $order_id = absint( $order_id );

        if ( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order instanceof WC_Order && 'aireset-expresso-order' === (string) $order->get_created_via() ) {
                return $order;
            }
        }

        $orders = self::get_recent_orders( 1 );

        if ( empty( $orders[0] ) || ! $orders[0] instanceof WC_Order ) {
            return null;
        }

        return $orders[0];
    }

    public static function get_preview_html( WC_Order $order, $document_type = '' ) {
        $settings         = self::get_pdf_settings();
        $document_type    = self::normalize_document_type( $document_type ? $document_type : self::detect_document_type( $order ) );
        $document_config  = self::get_document_display_settings( $document_type );
        $visible_columns  = self::get_document_item_columns( $document_type );
        $line_items       = self::get_order_line_items_display_data( $order );
        $shop_name        = trim( (string) $settings['shop_name'] );
        $shop_logo_url    = trim( (string) $settings['shop_logo_url'] );
        $customer_name    = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $date             = $order->get_date_created();
        $totals           = EOP_Order_Creator::sync_order_totals( $order );
        $document_number  = self::get_document_number( $order, $document_type, false );
        $template_name    = sanitize_html_class( (string) $settings['template_name'] );
        $paper_size       = strtoupper( (string) $settings['paper_size'] );
        $ink_saving       = 'yes' === $settings['ink_saving_mode'];
        $show_email       = 'yes' === $document_config['show_email'];
        $show_phone       = 'yes' === $document_config['show_phone'];
        $show_shipping    = 'yes' === $document_config['show_shipping'];
        $show_billing     = 'yes' === $document_config['show_billing'];
        $show_notes       = 'yes' === $document_config['show_notes'];
        $show_sku         = 'yes' === $document_config['show_sku'];
        $total_rows       = self::get_document_total_rows( $totals, $document_type );
        $billing_label    = self::get_billing_label( $order );

        ob_start();
        ?>
        <div class="eop-pdf-preview eop-pdf-preview--<?php echo esc_attr( $template_name ); ?><?php echo $ink_saving ? ' is-ink-saving' : ''; ?>">
            <div class="eop-pdf-preview__sheet eop-pdf-preview__sheet--<?php echo esc_attr( strtolower( $paper_size ) ); ?>">
                <div class="eop-pdf-preview__header">
                    <div class="eop-pdf-preview__brand">
                        <?php if ( $shop_logo_url ) : ?>
                            <img class="eop-pdf-preview__logo" src="<?php echo esc_url( $shop_logo_url ); ?>" alt="">
                        <?php endif; ?>
                        <div>
                            <strong><?php echo esc_html( $shop_name ?: get_bloginfo( 'name' ) ); ?></strong>
                            <span><?php echo esc_html( self::get_shop_address_label() ); ?></span>
                        </div>
                    </div>
                    <div class="eop-pdf-preview__meta">
                        <h3><?php echo esc_html( 'proposal' === $document_type ? __( 'PROPOSTA', EOP_TEXT_DOMAIN ) : __( 'PEDIDO', EOP_TEXT_DOMAIN ) ); ?></h3>
                        <div><?php esc_html_e( 'Numero do documento:', EOP_TEXT_DOMAIN ); ?> <strong><?php echo esc_html( $document_number ); ?></strong></div>
                        <div><?php esc_html_e( 'Data:', EOP_TEXT_DOMAIN ); ?> <?php echo esc_html( $date ? $date->date_i18n( 'd/m/Y' ) : '—' ); ?></div>
                        <div><?php esc_html_e( 'Pedido WooCommerce:', EOP_TEXT_DOMAIN ); ?> #<?php echo esc_html( $order->get_id() ); ?></div>
                    </div>
                </div>

                <div class="eop-pdf-preview__summary">
                    <div>
                        <span><?php esc_html_e( 'Cliente', EOP_TEXT_DOMAIN ); ?></span>
                        <strong><?php echo esc_html( $customer_name ?: __( 'Nao informado', EOP_TEXT_DOMAIN ) ); ?></strong>
                        <?php if ( $show_email && $order->get_billing_email() ) : ?>
                            <small><?php echo esc_html( $order->get_billing_email() ); ?></small>
                        <?php endif; ?>
                        <?php if ( $show_phone && $order->get_billing_phone() ) : ?>
                            <small><?php echo esc_html( $order->get_billing_phone() ); ?></small>
                        <?php endif; ?>
                    </div>
                    <?php if ( $show_shipping ) : ?>
                        <div>
                            <span><?php esc_html_e( 'Entrega', EOP_TEXT_DOMAIN ); ?></span>
                            <strong><?php echo esc_html( self::get_shipping_label( $order ) ); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ( $show_billing && '' !== $billing_label ) : ?>
                        <div>
                            <span><?php esc_html_e( 'Cobranca', EOP_TEXT_DOMAIN ); ?></span>
                            <strong><?php echo esc_html( $billing_label ); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <table class="eop-pdf-preview__table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Produto', EOP_TEXT_DOMAIN ); ?></th>
                            <?php foreach ( $visible_columns as $column ) : ?>
                                <th><?php echo esc_html( $column['label'] ); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $line_items as $line_item ) : ?>
                            <?php
                            $item    = $line_item['item'];
                            $product = $line_item['product'];
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $item->get_name() ); ?></strong>
                                    <?php if ( $show_sku && $product && $product->get_sku() ) : ?>
                                        <small><?php printf( esc_html__( 'SKU: %s', EOP_TEXT_DOMAIN ), esc_html( $product->get_sku() ) ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ( $visible_columns as $column ) : ?>
                                    <td>
                                        <?php if ( 'quantity' === $column['key'] ) : ?>
                                            <?php echo esc_html( $line_item['quantity'] ); ?>
                                        <?php elseif ( 'unit_price' === $column['key'] ) : ?>
                                            <?php echo wp_kses_post( wc_price( $line_item['unit_price'] ) ); ?>
                                        <?php elseif ( 'discount' === $column['key'] ) : ?>
                                            <strong><?php echo esc_html( self::format_percentage( $line_item['discount_percent'] ) ); ?></strong>
                                            <small><?php echo wp_kses_post( wc_price( $line_item['discount_per_unit'] ) ); ?> / <?php esc_html_e( 'un.', EOP_TEXT_DOMAIN ); ?></small>
                                        <?php elseif ( 'line_total' === $column['key'] ) : ?>
                                            <?php echo wp_kses_post( wc_price( $line_item['line_total'] ) ); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( ! empty( $total_rows ) ) : ?>
                    <div class="eop-pdf-preview__totals">
                        <?php foreach ( $total_rows as $row ) : ?>
                            <div class="<?php echo esc_attr( $row['class'] ); ?>">
                                <span><?php echo esc_html( $row['label'] ); ?></span>
                                <strong><?php echo wp_kses_post( $row['value'] ); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ( $show_notes && $order->get_customer_note() ) : ?>
                    <div class="eop-pdf-preview__notes">
                        <span><?php esc_html_e( 'Observacoes', EOP_TEXT_DOMAIN ); ?></span>
                        <p><?php echo esc_html( $order->get_customer_note() ); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $settings['shop_footer'] ) ) : ?>
                    <div class="eop-pdf-preview__footer"><?php echo esc_html( $settings['shop_footer'] ); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    public static function handle_download() {
        $document_type = self::normalize_document_type( isset( $_GET['document'] ) ? wp_unslash( $_GET['document'] ) : '' );
        $token         = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        $order_id      = absint( $_GET['order_id'] ?? 0 );
        $force_download = isset( $_GET['download'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['download'] ) );
        $is_public     = '' !== $token;
        $order         = $is_public ? self::get_order_by_token( $token ) : wc_get_order( $order_id );

        if ( ! $order instanceof WC_Order ) {
            wp_die( esc_html__( 'Documento nao encontrado.', EOP_TEXT_DOMAIN ) );
        }

        if ( ! self::is_expresso_order( $order ) ) {
            wp_die( esc_html__( 'Documento indisponivel para este pedido.', EOP_TEXT_DOMAIN ) );
        }

        if ( $is_public ) {
            if ( 'proposal' !== $document_type || ! self::public_token_matches_order( $order, $token ) || ! self::can_public_proposal_download( $order ) ) {
                wp_die( esc_html__( 'Documento indisponivel.', EOP_TEXT_DOMAIN ) );
            }
        } else {
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

            if ( ! wp_verify_nonce( $nonce, 'eop_download_pdf_' . $order->get_id() . '_' . $document_type ) ) {
                wp_die( esc_html__( 'Link de documento invalido.', EOP_TEXT_DOMAIN ) );
            }

            if ( current_user_can( 'edit_shop_orders' ) ) {
                if ( ! self::current_user_can_access_order( $order ) ) {
                    wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
                }
            } elseif ( ! self::can_customer_download( $order, $document_type ) ) {
                wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
            }
        }

        if ( ! self::is_document_enabled( $order, $document_type ) ) {
            wp_die( esc_html__( 'Documento desativado para este pedido.', EOP_TEXT_DOMAIN ) );
        }

        self::store_document_meta( $order, $document_type, $is_public );
        self::get_document_number( $order, $document_type, true );

        $binary = self::maybe_build_browser_pdf_document( $order, $document_type );

        if ( '' === $binary ) {
            $binary = self::build_pdf_document( $order, $document_type );
        }

        $filename = self::get_document_filename( $order, $document_type );
        $disposition = $force_download ? 'attachment' : self::get_content_disposition();

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: ' . $disposition . '; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
        header( 'Content-Length: ' . strlen( $binary ) );

        echo $binary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private static function current_user_can_access_order( WC_Order $order ) {
        if ( ! class_exists( 'EOP_Role' ) || ! EOP_Role::is_vendedor() ) {
            return true;
        }

        $user_id    = get_current_user_id();
        $creator_id = absint( $order->get_meta( '_eop_created_by' ) );

        if ( $creator_id && $creator_id === $user_id ) {
            return true;
        }

        $post = get_post( $order->get_id() );

        return ( $post && (int) $post->post_author === $user_id );
    }

    private static function public_token_matches_order( WC_Order $order, $token ) {
        return 'yes' === $order->get_meta( '_eop_is_proposal', true ) && hash_equals( (string) $order->get_meta( '_eop_public_token', true ), (string) $token );
    }

    private static function get_order_by_token( $token ) {
        $orders = wc_get_orders(
            array(
                'limit'      => 1,
                'type'       => 'shop_order',
                'meta_key'   => '_eop_public_token',
                'meta_value' => $token,
                'status'     => array_keys( wc_get_order_statuses() ),
            )
        );

        if ( empty( $orders[0] ) || ! $orders[0] instanceof WC_Order ) {
            return null;
        }

        return $orders[0];
    }

    private static function detect_document_type( WC_Order $order ) {
        return 'yes' === $order->get_meta( '_eop_is_proposal', true ) ? 'proposal' : 'order';
    }

    private static function normalize_document_type( $document_type ) {
        return 'proposal' === sanitize_key( (string) $document_type ) ? 'proposal' : 'order';
    }

    private static function store_document_meta( WC_Order $order, $document_type, $is_public ) {
        $suffix = 'proposal' === $document_type ? 'proposal' : 'order';

        $order->update_meta_data( '_eop_pdf_' . $suffix . '_generated_at', current_time( 'mysql' ) );
        $order->update_meta_data( '_eop_pdf_' . $suffix . '_generated_by', get_current_user_id() );
        $order->update_meta_data( '_eop_pdf_' . $suffix . '_access', $is_public ? 'public' : 'private' );
        $order->save();
    }

    private static function get_document_filename( WC_Order $order, $document_type ) {
        return sanitize_file_name( $order->get_id() . '.pdf' );
    }

    private static function build_pdf_document( WC_Order $order, $document_type ) {
        $settings        = self::get_pdf_settings();
        $pages           = array();
        $content         = '';
        $y               = 0;
        $page_left       = 46;
        $page_right      = 549;
        $content_width   = $page_right - $page_left;
        $document_config = self::get_document_display_settings( $document_type );
        $totals          = EOP_Order_Creator::sync_order_totals( $order );
        $line_items      = self::get_order_line_items_display_data( $order );
        $total_rows      = self::get_document_total_rows( $totals, $document_type );
        $columns         = self::get_document_item_columns( $document_type );
        $date            = $order->get_date_created();
        $document_number = self::get_document_number( $order, $document_type, false );
        $company_name    = trim( (string) $settings['shop_name'] );
        $company_addr    = trim( self::get_shop_address_label() );
        $footer_note     = trim( (string) $settings['shop_footer'] );
        $customer_name   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $show_sku        = 'yes' === $document_config['show_sku'];
        $show_notes      = 'yes' === $document_config['show_notes'];
        $billing_label   = self::get_billing_label( $order );
        $show_line_total = false;

        foreach ( $columns as $column ) {
            if ( isset( $column['key'] ) && 'line_total' === $column['key'] ) {
                $show_line_total = true;
                break;
            }
        }

        $start_page = function () use ( &$pages, &$content, &$y ) {
            if ( '' !== $content ) {
                $pages[] = $content;
            }

            $content = '';
            $y       = 794;
        };

        $ensure_space = function ( $height = 18 ) use ( &$y, &$start_page ) {
            if ( $y - $height < 56 ) {
                $start_page();
            }
        };

        $estimate_text_width = function ( $text, $size, $font = 'F1' ) {
            $text   = wp_strip_all_tags( (string) $text );
            $factor = 'F2' === $font ? 0.56 : 0.5;

            return strlen( self::escape_pdf_text( $text ) ) * $size * $factor;
        };

        $draw_line = function ( $x1, $x2, $line_y, $gray = 0.82, $width = 1 ) use ( &$content ) {
            $content .= sprintf(
                "q %s G %s w %s %s m %s %s l S Q\n",
                self::pdf_number( $gray ),
                self::pdf_number( $width ),
                self::pdf_number( $x1 ),
                self::pdf_number( $line_y ),
                self::pdf_number( $x2 ),
                self::pdf_number( $line_y )
            );
        };

        $fill_rect = function ( $x, $top, $width, $height, $gray = 0.08 ) use ( &$content ) {
            $content .= sprintf(
                "q %s g %s %s %s %s re f Q\n",
                self::pdf_number( $gray ),
                self::pdf_number( $x ),
                self::pdf_number( $top - $height ),
                self::pdf_number( $width ),
                self::pdf_number( $height )
            );
        };

        $add_text_at = function ( $text, $x, $line_y, $font = 'F1', $size = 11, $align = 'left', $gray = 0.12 ) use ( &$content, $estimate_text_width ) {
            $text = trim( wp_strip_all_tags( (string) $text ) );

            if ( '' === $text ) {
                return;
            }

            $width = $estimate_text_width( $text, $size, $font );

            if ( 'right' === $align ) {
                $x -= $width;
            } elseif ( 'center' === $align ) {
                $x -= $width / 2;
            }

            $content .= sprintf(
                "q %s g BT /%s %s Tf 1 0 0 1 %s %s Tm (%s) Tj ET Q\n",
                self::pdf_number( $gray ),
                $font,
                self::pdf_number( $size ),
                self::pdf_number( $x ),
                self::pdf_number( $line_y ),
                self::escape_pdf_text( $text )
            );
        };

        $start_page();

        $document_heading = 'proposal' === $document_type ? __( 'PROPOSTA', EOP_TEXT_DOMAIN ) : __( 'PEDIDO', EOP_TEXT_DOMAIN );
        $summary_blocks   = array();

        $summary_blocks[] = array(
            'label' => __( 'Cliente', EOP_TEXT_DOMAIN ),
            'value' => $customer_name ?: __( 'Nao informado', EOP_TEXT_DOMAIN ),
            'extra' => array_filter(
                array(
                    'yes' === $document_config['show_email'] ? $order->get_billing_email() : '',
                    'yes' === $document_config['show_phone'] ? $order->get_billing_phone() : '',
                )
            ),
        );

        if ( 'yes' === $document_config['show_shipping'] ) {
            $summary_blocks[] = array(
                'label' => __( 'Entrega', EOP_TEXT_DOMAIN ),
                'value' => self::get_shipping_label( $order ),
                'extra' => array(),
            );
        }

        if ( 'yes' === $document_config['show_billing'] && '' !== $billing_label ) {
            $summary_blocks[] = array(
                'label' => __( 'Cobranca', EOP_TEXT_DOMAIN ),
                'value' => $billing_label,
                'extra' => array(),
            );
        }

        $ensure_space( 220 );

        $add_text_at( $company_name ?: get_bloginfo( 'name' ), $page_left, $y, 'F2', 20, 'left', 0.08 );
        $add_text_at( $company_addr, $page_left, $y - 24, 'F1', 11, 'left', 0.45 );

        $add_text_at( $document_heading, $page_right, $y + 2, 'F2', 25, 'right', 0.08 );
        $add_text_at( sprintf( __( 'Numero do documento: %s', EOP_TEXT_DOMAIN ), $document_number ), $page_right, $y - 24, 'F1', 11, 'right', 0.36 );
        $add_text_at( sprintf( __( 'Data: %s', EOP_TEXT_DOMAIN ), $date ? $date->date_i18n( 'd/m/Y' ) : '—' ), $page_right, $y - 40, 'F1', 11, 'right', 0.36 );
        $add_text_at( sprintf( __( 'Pedido WooCommerce: #%s', EOP_TEXT_DOMAIN ), $order->get_id() ), $page_right, $y - 56, 'F1', 11, 'right', 0.36 );

        $y -= 98;

        if ( ! empty( $summary_blocks ) ) {
            $block_count = count( $summary_blocks );
            $block_gap   = 18;
            $block_width = ( $content_width - ( $block_gap * max( 0, $block_count - 1 ) ) ) / max( 1, $block_count );
            $max_height  = 0;

            foreach ( $summary_blocks as $index => $block ) {
                $block_x    = $page_left + ( $index * ( $block_width + $block_gap ) );
                $line_y     = $y;
                $block_rows = 1 + count( $block['extra'] );

                $add_text_at( strtoupper( $block['label'] ), $block_x, $line_y, 'F2', 10, 'left', 0.42 );
                $line_y -= 22;
                $add_text_at( $block['value'], $block_x, $line_y, 'F2', 14, 'left', 0.12 );
                $line_y -= 16;

                foreach ( $block['extra'] as $extra_line ) {
                    $add_text_at( $extra_line, $block_x, $line_y, 'F1', 10, 'left', 0.45 );
                    $line_y -= 14;
                }

                $max_height = max( $max_height, 22 + ( $block_rows * 16 ) );
            }

            $y -= $max_height + 12;
        }

        $header_top    = $y;
        $header_height = 34;
        $fill_rect( $page_left, $header_top, $content_width, $header_height, 0.08 );
        $add_text_at( strtoupper( __( 'Produto', EOP_TEXT_DOMAIN ) ), $page_left + 12, $header_top - 21, 'F2', 9, 'left', 1 );

        if ( array_filter( $columns, function ( $column ) { return isset( $column['key'] ) && 'quantity' === $column['key']; } ) ) {
            $add_text_at( strtoupper( __( 'Quantidade', EOP_TEXT_DOMAIN ) ), 255, $header_top - 21, 'F2', 9, 'center', 1 );
        }

        if ( array_filter( $columns, function ( $column ) { return isset( $column['key'] ) && 'unit_price' === $column['key']; } ) ) {
            $add_text_at( strtoupper( __( 'Valor', EOP_TEXT_DOMAIN ) ), 338, $header_top - 15, 'F2', 8, 'center', 1 );
            $add_text_at( strtoupper( __( 'Unitario', EOP_TEXT_DOMAIN ) ), 338, $header_top - 26, 'F2', 8, 'center', 1 );
        }

        if ( array_filter( $columns, function ( $column ) { return isset( $column['key'] ) && 'discount' === $column['key']; } ) ) {
            $add_text_at( strtoupper( __( 'Desconto', EOP_TEXT_DOMAIN ) ), 428, $header_top - 15, 'F2', 8, 'center', 1 );
            $add_text_at( strtoupper( __( 'Aplicado', EOP_TEXT_DOMAIN ) ), 428, $header_top - 26, 'F2', 8, 'center', 1 );
        }

        if ( $show_line_total ) {
            $add_text_at( strtoupper( __( 'Total', EOP_TEXT_DOMAIN ) ), 520, $header_top - 21, 'F2', 9, 'center', 1 );
        }

        $y = $header_top - $header_height - 18;

        foreach ( $line_items as $line_item ) {
            $product      = $line_item['product'];
            $product_name = $line_item['item']->get_name();
            $name_lines   = self::wrap_text( $product_name, 24 );
            $meta_lines   = array();

            if ( array_filter( $columns, function ( $column ) { return isset( $column['key'] ) && 'quantity' === $column['key']; } ) ) {
                $meta_lines['quantity'] = (string) $line_item['quantity'];
            }

            if ( array_filter( $columns, function ( $column ) { return isset( $column['key'] ) && 'unit_price' === $column['key']; } ) ) {
                $meta_lines['unit_price'] = self::format_money( $line_item['unit_price'] );
            }

            if ( array_filter( $columns, function ( $column ) { return isset( $column['key'] ) && 'discount' === $column['key']; } ) ) {
                $meta_lines['discount_main'] = self::format_percentage( $line_item['discount_percent'] );
                $meta_lines['discount_sub']  = self::format_money( $line_item['discount_per_unit'] ) . '/' . __( 'un.', EOP_TEXT_DOMAIN );
            }

            $sku_line   = $show_sku && $product && $product->get_sku() ? sprintf( __( 'SKU: %s', EOP_TEXT_DOMAIN ), $product->get_sku() ) : '';
            $row_height = max( 26, count( $name_lines ) * 13 + ( '' !== $sku_line ? 14 : 0 ), isset( $meta_lines['discount_sub'] ) ? 26 : 14 ) + 14;

            $ensure_space( $row_height + 18 );

            $row_top = $y;
            $line_y  = $row_top;

            foreach ( $name_lines as $name_line ) {
                $add_text_at( $name_line, $page_left + 12, $line_y, 'F2', 10, 'left', 0.12 );
                $line_y -= 13;
            }

            if ( '' !== $sku_line ) {
                $add_text_at( $sku_line, $page_left + 12, $line_y - 1, 'F1', 9, 'left', 0.5 );
            }

            if ( isset( $meta_lines['quantity'] ) ) {
                $add_text_at( $meta_lines['quantity'], 255, $row_top, 'F1', 10, 'center', 0.12 );
            }

            if ( isset( $meta_lines['unit_price'] ) ) {
                $add_text_at( $meta_lines['unit_price'], 338, $row_top, 'F1', 10, 'center', 0.12 );
            }

            if ( isset( $meta_lines['discount_main'] ) ) {
                $add_text_at( $meta_lines['discount_main'], 428, $row_top, 'F2', 10, 'center', 0.12 );
            }

            if ( isset( $meta_lines['discount_sub'] ) ) {
                $add_text_at( $meta_lines['discount_sub'], 428, $row_top - 16, 'F1', 9, 'center', 0.5 );
            }

            if ( $show_line_total ) {
                $add_text_at( self::format_money( $line_item['line_total'] ), $page_right - 8, $row_top, 'F2', 10, 'right', 0.12 );
            }

            $row_bottom = $row_top - $row_height;
            $draw_line( $page_left, $page_right, $row_bottom, 0.9, 0.8 );
            $y = $row_bottom - 12;
        }

        if ( ! empty( $total_rows ) ) {
            $totals_y = $y - 2;

            foreach ( $total_rows as $row ) {
                $is_grand = 'is-grand' === $row['class'];

                if ( $is_grand ) {
                    $draw_line( 360, $page_right, $totals_y + 10, 0.12, 1.2 );
                }

                $add_text_at( $is_grand ? __( 'Total', EOP_TEXT_DOMAIN ) : $row['label'], 360, $totals_y, $is_grand ? 'F2' : 'F1', $is_grand ? 15 : 11, 'left', 0.12 );
                $add_text_at( self::format_money( $row['raw'] ?? 0 ), $page_right, $totals_y, 'F2', $is_grand ? 15 : 11, 'right', 0.12 );
                $totals_y -= $is_grand ? 24 : 18;
            }

            $y = $totals_y - 8;
        }

        if ( 'proposal' === $document_type ) {
            $confirmation = 'yes' === $order->get_meta( '_eop_proposal_confirmed', true ) ? __( 'Situacao da proposta: Confirmada', EOP_TEXT_DOMAIN ) : __( 'Situacao da proposta: Aguardando confirmacao', EOP_TEXT_DOMAIN );
            $add_text_at( $confirmation, $page_left, $y, 'F1', 10, 'left', 0.22 );
            $y -= 18;
        }

        $notes = trim( (string) $order->get_customer_note() );
        if ( $show_notes && '' !== $notes ) {
            $add_text_at( __( 'Observacoes', EOP_TEXT_DOMAIN ), $page_left, $y, 'F2', 12, 'left', 0.12 );
            $y -= 16;

            foreach ( self::wrap_text( $notes, 78 ) as $note_line ) {
                $add_text_at( $note_line, $page_left, $y, 'F1', 10, 'left', 0.22 );
                $y -= 14;
            }
        }

        if ( '' !== $footer_note ) {
            $y -= 4;

            foreach ( self::wrap_text( $footer_note, 82 ) as $footer_line ) {
                $add_text_at( $footer_line, $page_left, $y, 'F1', 9, 'left', 0.45 );
                $y -= 12;
            }
        }

        if ( '' !== $content ) {
            $pages[] = $content;
        }

        return self::render_pdf_binary( $pages );
    }

    private static function maybe_build_browser_pdf_document( WC_Order $order, $document_type ) {
        $binary = self::maybe_build_dompdf_document( $order, $document_type );

        if ( '' !== $binary ) {
            return $binary;
        }

        return self::maybe_build_headless_browser_pdf_document( $order, $document_type );
    }

    private static function maybe_build_dompdf_document( WC_Order $order, $document_type ) {
        if ( ! self::ensure_reference_dompdf_loaded() ) {
            return '';
        }

        if ( ! class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Dompdf' ) || ! class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Options' ) ) {
            return '';
        }

        $html = self::get_preview_print_document_html( $order, $document_type );

        if ( '' === $html ) {
            return '';
        }

        $settings      = self::get_pdf_settings();
        $runtime_paths = self::get_dompdf_runtime_paths();

        if ( empty( $runtime_paths['temp'] ) || empty( $runtime_paths['fonts'] ) ) {
            return '';
        }

        $options = apply_filters(
            'eop_pdf_dompdf_options',
            array(
                'tempDir'                 => $runtime_paths['temp'],
                'fontDir'                 => $runtime_paths['fonts'],
                'fontCache'               => $runtime_paths['fonts'],
                'chroot'                  => self::get_dompdf_chroot_paths( $runtime_paths ),
                'logOutputFile'           => trailingslashit( $runtime_paths['temp'] ) . 'log.htm',
                'defaultFont'             => 'dejavu sans',
                'isRemoteEnabled'         => true,
                'isHtml5ParserEnabled'    => true,
                'isFontSubsettingEnabled' => ! empty( $settings['font_subsetting'] ) && 'yes' === (string) $settings['font_subsetting'],
            ),
            $order,
            $document_type,
            $runtime_paths
        );

        try {
            $dompdf = new \WPO\IPS\Vendor\Dompdf\Dompdf( new \WPO\IPS\Vendor\Dompdf\Options( $options ) );
            $dompdf->loadHtml( $html );
            $dompdf->setPaper( isset( $settings['paper_size'] ) && 'letter' === $settings['paper_size'] ? 'letter' : 'A4', 'portrait' );
            $dompdf->render();

            $binary = $dompdf->output();

            return is_string( $binary ) ? $binary : '';
        } catch ( \Throwable $throwable ) {
            return '';
        }
    }

    private static function maybe_build_headless_browser_pdf_document( WC_Order $order, $document_type ) {
        $browser = self::get_browser_pdf_executable();

        if ( '' === $browser || ! function_exists( 'exec' ) ) {
            return '';
        }

        $html_file = wp_tempnam( 'eop-pdf-preview.html' );
        $pdf_file  = wp_tempnam( 'eop-pdf-output.pdf' );

        if ( ! $html_file || ! $pdf_file ) {
            return '';
        }

        @unlink( $pdf_file );

        $html = self::get_preview_print_document_html( $order, $document_type );

        if ( '' === $html ) {
            @unlink( $html_file );
            return '';
        }

        if ( false === file_put_contents( $html_file, $html ) ) {
            @unlink( $html_file );
            return '';
        }

        $command = sprintf(
            '%s --headless --disable-gpu --run-all-compositor-stages-before-draw --virtual-time-budget=2000 --print-to-pdf=%s --print-to-pdf-no-header %s 2>&1',
            escapeshellarg( $browser ),
            escapeshellarg( $pdf_file ),
            escapeshellarg( self::path_to_file_url( $html_file ) )
        );

        $output = array();
        $status = 1;

        @exec( $command, $output, $status );

        if ( 0 !== (int) $status || ! file_exists( $pdf_file ) ) {
            @unlink( $html_file );
            @unlink( $pdf_file );
            return '';
        }

        $binary = file_get_contents( $pdf_file );

        @unlink( $html_file );
        @unlink( $pdf_file );

        return false === $binary ? '' : $binary;
    }

    private static function get_preview_print_document_html( WC_Order $order, $document_type ) {
        $settings   = self::get_pdf_settings();
        $font_url   = method_exists( 'EOP_Settings', 'get_font_stylesheet_url' ) ? EOP_Settings::get_font_stylesheet_url( $settings['font_family'] ?? '' ) : '';
        $font_css   = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $settings['font_family'] ?? '' ) : "'Segoe UI', sans-serif";
        $paper_size = isset( $settings['paper_size'] ) && 'letter' === $settings['paper_size'] ? 'Letter' : 'A4';
        $css_path   = EOP_PLUGIN_DIR . 'assets/css/pdf-admin.css';
        $css        = file_exists( $css_path ) ? (string) file_get_contents( $css_path ) : '';
        $preview    = self::get_preview_html( $order, $document_type );

        if ( '' === $preview ) {
            return '';
        }

        $css .= "\n@page { size: {$paper_size}; margin: 12mm; }\n";
        $css .= "html, body { margin: 0; padding: 0; background: #ffffff; font-family: {$font_css}; }\n";
        $css .= "body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }\n";
        $css .= ".eop-pdf-preview { padding: 0 !important; }\n";
        $css .= ".eop-pdf-preview__sheet { max-width: none !important; width: auto !important; margin: 0 auto !important; box-shadow: none !important; }\n";
        $css .= ".eop-pdf-preview__sheet--a4 { max-width: none !important; }\n";
        $css .= ".eop-pdf-preview__sheet--letter { max-width: none !important; }\n";

        return '<!doctype html><html><head><meta charset="utf-8">'
            . ( $font_url ? '<link rel="stylesheet" href="' . esc_url( $font_url ) . '">' : '' )
            . '<style>' . $css . '</style></head><body>' . $preview . '</body></html>';
    }

    private static function ensure_reference_dompdf_loaded() {
        if ( class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Dompdf' ) && class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Options' ) ) {
            return true;
        }

        $plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce-pdf-invoices-packing-slips/';
        $autoloads  = array(
            $plugin_dir . 'vendor/autoload.php',
            $plugin_dir . 'vendor/strauss/autoload.php',
        );

        foreach ( $autoloads as $autoload ) {
            if ( file_exists( $autoload ) ) {
                require_once $autoload;
            }
        }

        return class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Dompdf' ) && class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Options' );
    }

    private static function get_dompdf_runtime_paths() {
        $upload_dir = wp_upload_dir();

        if ( empty( $upload_dir['basedir'] ) ) {
            return array();
        }

        $base_dir = trailingslashit( $upload_dir['basedir'] ) . 'eop-pdf/dompdf';
        $temp_dir = trailingslashit( $base_dir ) . 'tmp';
        $font_dir = trailingslashit( $base_dir ) . 'fonts';

        if ( ! wp_mkdir_p( $temp_dir ) || ! wp_mkdir_p( $font_dir ) ) {
            return array();
        }

        return array(
            'base'  => $base_dir,
            'temp'  => $temp_dir,
            'fonts' => $font_dir,
        );
    }

    private static function get_dompdf_chroot_paths( $runtime_paths ) {
        $upload_dir = wp_upload_dir();
        $paths      = array_filter(
            array(
                WP_CONTENT_DIR,
                WP_PLUGIN_DIR,
                EOP_PLUGIN_DIR,
                $upload_dir['basedir'] ?? '',
                $runtime_paths['base'] ?? '',
            )
        );

        return array_values( array_unique( array_map( 'wp_normalize_path', $paths ) ) );
    }

    private static function get_browser_pdf_executable() {
        $candidates = apply_filters(
            'eop_pdf_browser_binaries',
            array(
                'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
                'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
                'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
                'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
                '/usr/bin/google-chrome',
                '/usr/bin/chromium',
                '/usr/bin/chromium-browser',
                '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            )
        );

        foreach ( (array) $candidates as $candidate ) {
            $candidate = (string) $candidate;

            if ( '' !== $candidate && file_exists( $candidate ) && is_executable( $candidate ) ) {
                return $candidate;
            }
        }

        return '';
    }

    private static function path_to_file_url( $path ) {
        $normalized = wp_normalize_path( (string) $path );

        if ( preg_match( '/^[A-Za-z]:\//', $normalized ) ) {
            return 'file:///' . str_replace( ' ', '%20', $normalized );
        }

        return 'file://' . str_replace( ' ', '%20', $normalized );
    }

    public static function get_order_line_items_display_data( WC_Order $order ) {
        $rows                = array();
        $fee_discount_total  = 0.0;
        $line_total_pool     = 0.0;
        $decimals            = wc_get_price_decimals();
        $order_items         = $order->get_items( 'line_item' );
        $line_items_count    = count( $order_items );

        foreach ( $order->get_fees() as $fee ) {
            if ( (float) $fee->get_total() < 0 ) {
                $fee_discount_total += abs( (float) $fee->get_total() );
            }
        }

        foreach ( $order_items as $item ) {
            /** @var WC_Order_Item_Product $item */
            $line_total_pool += max( 0, (float) $item->get_total() );
        }

        $remaining_fee_discount = round( $fee_discount_total, $decimals );
        $remaining_line_total   = round( $line_total_pool, $decimals );
        $line_index             = 0;

        foreach ( $order_items as $item ) {
            /** @var WC_Order_Item_Product $item */
            $quantity             = max( 1, (int) $item->get_quantity() );
            $line_subtotal        = (float) $item->get_subtotal();
            $line_total           = max( 0, (float) $item->get_total() );
            $item_discount_total  = max( 0, $line_subtotal - $line_total );
            $allocated_fee_amount = 0.0;

            if ( $remaining_fee_discount > 0 && $remaining_line_total > 0 ) {
                if ( $line_index === $line_items_count - 1 ) {
                    $allocated_fee_amount = $remaining_fee_discount;
                } else {
                    $allocated_fee_amount = round( $remaining_fee_discount * ( $line_total / $remaining_line_total ), $decimals );
                }

                $allocated_fee_amount = min( $allocated_fee_amount, $remaining_fee_discount, $line_total );
            }

            $remaining_fee_discount = max( 0, round( $remaining_fee_discount - $allocated_fee_amount, $decimals ) );
            $remaining_line_total   = max( 0, round( $remaining_line_total - $line_total, $decimals ) );

            $discount_total     = round( $item_discount_total + $allocated_fee_amount, $decimals );
            $effective_total    = max( 0, round( $line_subtotal - $discount_total, $decimals ) );
            $unit_price         = $quantity > 0 ? $line_subtotal / $quantity : $line_subtotal;
            $discount_per_unit  = $quantity > 0 ? $discount_total / $quantity : $discount_total;
            $discount_percent   = $line_subtotal > 0 ? ( $discount_total / $line_subtotal ) * 100 : 0;

            $rows[] = array(
                'item'              => $item,
                'product'           => $item->get_product(),
                'quantity'          => $quantity,
                'unit_price'        => round( $unit_price, $decimals ),
                'discount_percent'  => round( $discount_percent, 2 ),
                'discount_per_unit' => round( $discount_per_unit, $decimals ),
                'discount_total'    => $discount_total,
                'line_total'        => $effective_total,
            );

            $line_index++;
        }

        return $rows;
    }

    private static function render_pdf_binary( $pages ) {
        $objects = array(
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        );

        $kids          = array();
        $object_number = 5;

        foreach ( $pages as $page_content ) {
            $page_object    = $object_number++;
            $content_object = $object_number++;
            $kids[]         = $page_object . ' 0 R';

            $objects[ $page_object ] = '<< /Type /Page /Parent 2 0 R /MediaBox [' . self::get_media_box() . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $content_object . ' 0 R >>';
            $objects[ $content_object ] = '<< /Length ' . strlen( $page_content ) . " >>\nstream\n" . $page_content . "\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids [ ' . implode( ' ', $kids ) . ' ] /Count ' . count( $kids ) . ' >>';

        ksort( $objects );

        $pdf     = "%PDF-1.4\n";
        $offsets = array( 0 );

        foreach ( $objects as $id => $object ) {
            $offsets[ $id ] = strlen( $pdf );
            $pdf           .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref_offset = strlen( $pdf );
        $max_object  = max( array_keys( $objects ) );

        $pdf .= "xref\n0 " . ( $max_object + 1 ) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ( $i = 1; $i <= $max_object; $i++ ) {
            $offset = isset( $offsets[ $i ] ) ? $offsets[ $i ] : 0;
            $pdf   .= sprintf( "%010d 00000 n \n", $offset );
        }

        $pdf .= "trailer\n<< /Size " . ( $max_object + 1 ) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xref_offset . "\n%%EOF";

        return $pdf;
    }

    private static function wrap_text( $text, $max_chars ) {
        $text      = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
        $max_chars = max( 12, absint( $max_chars ) );

        if ( '' === $text ) {
            return array( '' );
        }

        $wrapped = wordwrap( $text, $max_chars, "\n", true );

        return explode( "\n", $wrapped );
    }

    private static function format_money( $value ) {
        $formatted = wc_price( (float) $value );
        $formatted = html_entity_decode( wp_strip_all_tags( (string) $formatted ), ENT_QUOTES, 'UTF-8' );

        return trim( preg_replace( '/\s+/', ' ', $formatted ) );
    }

    private static function format_percentage( $value ) {
        $value    = max( 0, (float) $value );
        $decimals = abs( $value - round( $value ) ) < 0.01 ? 0 : 2;

        return number_format_i18n( $value, $decimals ) . '%';
    }

    private static function escape_pdf_text( $text ) {
        $text = wp_strip_all_tags( (string) $text );

        if ( function_exists( 'iconv' ) ) {
            $converted = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text );

            if ( false !== $converted ) {
                $text = $converted;
            }
        }

        $text = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\(', '\)' ), $text );
        $text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text );

        return $text;
    }

    private static function pdf_number( $number ) {
        return rtrim( rtrim( sprintf( '%.2F', (float) $number ), '0' ), '.' );
    }

    private static function get_pdf_settings() {
        return class_exists( 'EOP_PDF_Settings' ) ? EOP_PDF_Settings::get_all() : array();
    }

    private static function get_pdf_table_positions( $column_keys ) {
        $column_keys = array_values( array_filter( array_map( 'sanitize_key', (array) $column_keys ) ) );
        $count       = count( $column_keys );
        $positions   = array();

        if ( 0 === $count ) {
            return $positions;
        }

        if ( 1 === $count ) {
            $positions[ $column_keys[0] ] = 500;
            return $positions;
        }

        $start = 325;
        $end   = 515;
        $step  = ( $end - $start ) / max( 1, $count - 1 );

        foreach ( $column_keys as $index => $column_key ) {
            $positions[ $column_key ] = (int) round( $start + ( $step * $index ) );
        }

        return $positions;
    }

    private static function get_content_disposition() {
        $settings = self::get_pdf_settings();

        return isset( $settings['display_mode'] ) && 'download' === $settings['display_mode'] ? 'attachment' : 'inline';
    }

    private static function get_media_box() {
        $settings = self::get_pdf_settings();

        return isset( $settings['paper_size'] ) && 'letter' === $settings['paper_size'] ? '0 0 612 792' : '0 0 595 842';
    }

    private static function get_shop_address_label() {
        $settings = self::get_pdf_settings();

        return implode(
            ', ',
            array_filter(
                array(
                    $settings['shop_address_line_1'] ?? '',
                    $settings['shop_address_line_2'] ?? '',
                    $settings['shop_city'] ?? '',
                    $settings['shop_state'] ?? '',
                    $settings['shop_postcode'] ?? '',
                    $settings['shop_country'] ?? '',
                )
            )
        );
    }

    private static function get_shipping_label( WC_Order $order ) {
        $parts = array_filter(
            array(
                $order->get_shipping_address_1(),
                $order->get_meta( '_shipping_number', true ),
                $order->get_meta( '_shipping_neighborhood', true ),
                $order->get_shipping_city(),
                $order->get_shipping_state(),
            )
        );

        if ( empty( $parts ) ) {
            return __( 'Nao informado', EOP_TEXT_DOMAIN );
        }

        return implode( ', ', array_map( 'wp_strip_all_tags', $parts ) );
    }

    private static function get_billing_label( WC_Order $order ) {
        $parts = array_filter(
            array(
                $order->get_billing_address_1(),
                $order->get_meta( '_billing_number', true ),
                $order->get_billing_address_2(),
                $order->get_meta( '_billing_neighborhood', true ),
                $order->get_billing_city(),
                $order->get_billing_state(),
                $order->get_billing_postcode(),
            )
        );

        if ( empty( $parts ) ) {
            return '';
        }

        return implode( ', ', array_map( 'wp_strip_all_tags', $parts ) );
    }

    private static function get_document_number( WC_Order $order, $document_type, $create = false ) {
        $document_type = self::normalize_document_type( $document_type );
        $meta_key      = '_eop_' . $document_type . '_document_number';
        $current       = (string) $order->get_meta( $meta_key, true );

        if ( '' !== $current ) {
            return $current;
        }

        $settings = self::get_pdf_settings();
        $prefix   = (string) ( $settings[ $document_type . '_prefix' ] ?? '' );
        $suffix   = (string) ( $settings[ $document_type . '_suffix' ] ?? '' );
        $padding  = max( 0, absint( $settings[ $document_type . '_padding' ] ?? 0 ) );
        $next     = max( 1, absint( $settings[ $document_type . '_next_number' ] ?? $order->get_id() ) );
        $number   = $prefix . str_pad( (string) $next, $padding, '0', STR_PAD_LEFT ) . $suffix;

        if ( ! $create ) {
            return $number;
        }

        $order->update_meta_data( $meta_key, $number );
        $order->save();

        if ( class_exists( 'EOP_PDF_Settings' ) ) {
            $settings[ $document_type . '_next_number' ] = (string) ( $next + 1 );
            update_option( EOP_PDF_Settings::OPTION_KEY, $settings );
        }

        return $number;
    }
}
