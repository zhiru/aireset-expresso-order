<?php
defined( 'ABSPATH' ) || exit;

class EOP_Public_Proposal {

    public static function init() {
        add_shortcode( 'expresso_order_proposal', array( __CLASS__, 'render_shortcode' ) );
        add_action( 'init', array( __CLASS__, 'handle_confirmation' ) );
    }

    public static function create_public_token( WC_Order $order ) {
        $token = wp_generate_password( 24, false, false );

        $order->update_meta_data( '_eop_public_token', $token );
        $order->update_meta_data( '_eop_is_proposal', 'yes' );
        $order->update_meta_data( '_eop_proposal_confirmed', 'no' );

        return $token;
    }

    public static function get_public_link( WC_Order $order ) {
        $page_id = class_exists( 'EOP_Page_Installer' ) ? EOP_Page_Installer::get_page_id( 'proposal' ) : absint( EOP_Settings::get( 'proposal_page_id', 0 ) );
        $token   = (string) $order->get_meta( '_eop_public_token', true );

        if ( ! $page_id || '' === $token ) {
            return '';
        }

        return add_query_arg(
            array(
                'eop_proposal' => rawurlencode( $token ),
            ),
            get_permalink( $page_id )
        );
    }

    public static function render_shortcode() {
        $token = isset( $_GET['eop_proposal'] ) ? sanitize_text_field( wp_unslash( $_GET['eop_proposal'] ) ) : '';

        if ( '' === $token ) {
            return '<div class="eop-notice eop-notice-error">' . esc_html__( 'Proposta nao encontrada.', EOP_TEXT_DOMAIN ) . '</div>';
        }

        $order = self::get_order_by_token( $token );

        if ( ! $order ) {
            return '<div class="eop-notice eop-notice-error">' . esc_html__( 'Link de proposta invalido ou expirado.', EOP_TEXT_DOMAIN ) . '</div>';
        }

        return self::render_proposal_page( $order );
    }

    public static function handle_confirmation() {
        if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            return;
        }

        if ( empty( $_POST['eop_confirm_proposal_nonce'] ) || empty( $_POST['eop_proposal_token'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eop_confirm_proposal_nonce'] ) ), 'eop_confirm_proposal' ) ) {
            return;
        }

        $token = sanitize_text_field( wp_unslash( $_POST['eop_proposal_token'] ) );
        $order = self::get_order_by_token( $token );

        if ( ! $order ) {
            return;
        }

        $order->update_meta_data( '_eop_proposal_confirmed', 'yes' );
        $order->add_order_note( __( 'Proposta confirmada pelo cliente.', EOP_TEXT_DOMAIN ) );
        $order->save();

        $redirect = add_query_arg( 'eop_confirmed', '1', self::get_public_link( $order ) );

        wp_safe_redirect( $redirect );
        exit;
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

        if ( empty( $orders ) ) {
            return false;
        }

        return $orders[0];
    }

    private static function render_proposal_page( WC_Order $order ) {
        $totals          = EOP_Order_Creator::sync_order_totals( $order );
        $settings        = EOP_Settings::get_all();
        $logo_url        = $settings['brand_logo_url'];
        $font_url        = method_exists( 'EOP_Settings', 'get_font_stylesheet_url' ) ? EOP_Settings::get_font_stylesheet_url( $settings['font_family'] ) : '';
        $font_css        = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $settings['font_family'] ) : "'Segoe UI', sans-serif";
        $confirmed       = 'yes' === $order->get_meta( '_eop_proposal_confirmed', true );
        $line_items      = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_order_line_items_display_data( $order ) : array();
        $button_label    = $settings['proposal_button_label'];
        $pay_label       = ! empty( $settings['proposal_pay_button_label'] ) ? $settings['proposal_pay_button_label'] : __( 'Ir para pagamento', EOP_TEXT_DOMAIN );
        $confirm_state   = isset( $_GET['eop_confirmed'] ) ? sanitize_text_field( wp_unslash( $_GET['eop_confirmed'] ) ) : '';
        $can_pay         = 'yes' === EOP_Settings::get( 'enable_checkout_confirmation', 'no' ) && method_exists( $order, 'needs_payment' ) && $order->needs_payment();
        $payment_url     = $can_pay ? $order->get_checkout_payment_url() : '';
        $pdf_url         = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_public_document_url( $order, true ) : '';
        $document_config = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_document_display_settings( 'proposal' ) : array();
        $item_columns    = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_document_item_columns( 'proposal' ) : array();
        $item_labels     = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_document_item_labels( 'proposal' ) : array();
        $total_rows      = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_document_total_rows( $totals, 'proposal' ) : array();
        $show_sku        = 'yes' === ( $document_config['show_sku'] ?? 'yes' );
        $show_email      = 'yes' === ( $document_config['show_email'] ?? 'yes' );
        $show_phone      = 'yes' === ( $document_config['show_phone'] ?? 'yes' );
        $show_notes      = 'yes' === ( $document_config['show_notes'] ?? 'yes' );
        $has_logo        = ! empty( $logo_url );
        $customer_name   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ?: __( 'Nao informado', EOP_TEXT_DOMAIN );
        $base_font_size  = max( 12, absint( $settings['proposal_text_size'] ?? 16 ) );
        $title_font_size = max( 22, absint( $settings['proposal_title_size'] ?? 40 ) );
        $max_width       = max( 720, absint( $settings['proposal_max_width'] ?? 1120 ) );
        $customer_note   = trim( (string) $order->get_customer_note() );
        $show_line_total = (bool) array_filter(
            $item_columns,
            function ( $column ) {
                return isset( $column['key'] ) && 'line_total' === $column['key'];
            }
        );

        ob_start();
        ?>
        <?php if ( $font_url ) : ?>
            <link rel="stylesheet" href="<?php echo esc_url( $font_url ); ?>">
        <?php endif; ?>
        <style>
            body{background:<?php echo esc_attr( $settings['proposal_background_color'] ); ?>}
            .eop-proposal-wrap{max-width:<?php echo esc_attr( $max_width ); ?>px;margin:32px auto;padding:0 16px;font-family:<?php echo esc_attr( $font_css ); ?>;font-size:<?php echo esc_attr( $base_font_size ); ?>px;color:<?php echo esc_attr( $settings['proposal_text_color'] ); ?>}
            .eop-proposal-card{background:<?php echo esc_attr( $settings['proposal_card_color'] ); ?>;border:1px solid <?php echo esc_attr( $settings['border_color'] ); ?>;border-radius:<?php echo esc_attr( $settings['border_radius'] ); ?>px;padding:32px;box-shadow:0 16px 42px rgba(23,32,51,.08)}
            .eop-proposal-header{display:flex;align-items:flex-start;justify-content:space-between;gap:28px;margin-bottom:28px}
            .eop-proposal-header.has-no-logo{justify-content:flex-start}
            .eop-proposal-brand{flex:0 0 auto}
            .eop-proposal-header__content{max-width:640px}
            .eop-proposal-logo{max-height:64px;max-width:220px}
            .eop-proposal-title{margin:0 0 8px;font-size:<?php echo esc_attr( $title_font_size ); ?>px;line-height:1.08}
            .eop-proposal-text{margin:0;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>}
            .eop-proposal-status{display:inline-block;margin-top:16px;padding:10px 14px;border-radius:999px;background:#eef4ff;color:<?php echo esc_attr( $settings['primary_color'] ); ?>;font-weight:700}
            .eop-proposal-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:22px}
            .eop-proposal-meta p{margin:0;padding:14px 16px;border:1px solid <?php echo esc_attr( $settings['border_color'] ); ?>;border-radius:<?php echo esc_attr( max( 14, (int) $settings['border_radius'] - 2 ) ); ?>px;background:rgba(255,255,255,.58)}
            .eop-proposal-items{display:grid;gap:16px;margin-top:24px}
            .eop-proposal-item{display:grid;grid-template-columns:96px minmax(0,1fr) auto;gap:18px;align-items:center;padding:18px;border:1px solid <?php echo esc_attr( $settings['border_color'] ); ?>;border-radius:<?php echo esc_attr( max( 18, (int) $settings['border_radius'] ) ); ?>px;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(245,247,255,.72))}
            .eop-proposal-item__media{width:96px;height:96px;border-radius:22px;overflow:hidden;background:#fff;border:1px solid <?php echo esc_attr( $settings['border_color'] ); ?>;display:flex;align-items:center;justify-content:center}
            .eop-proposal-item__media img{display:block;width:100%;height:100%;object-fit:cover}
            .eop-proposal-item__body{min-width:0}
            .eop-proposal-item__name{margin:0 0 8px;font-size:22px;line-height:1.2}
            .eop-proposal-item__meta{display:flex;flex-wrap:wrap;gap:10px 16px;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>;font-size:14px}
            .eop-proposal-item__pill{display:inline-flex;align-items:center;min-height:32px;padding:0 12px;border-radius:999px;background:#f1f4ff;color:<?php echo esc_attr( $settings['primary_color'] ); ?>;font-weight:700;line-height:1.2}
            .eop-proposal-item__summary{display:grid;gap:6px;min-width:150px;justify-items:end;text-align:right}
            .eop-proposal-item__summary span{font-size:13px;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>;text-transform:uppercase;letter-spacing:.08em;font-weight:700}
            .eop-proposal-item__summary strong{font-size:28px;line-height:1;color:<?php echo esc_attr( $settings['proposal_text_color'] ); ?>}
            .eop-proposal-notes{margin-top:24px;padding:18px;border:1px solid <?php echo esc_attr( $settings['border_color'] ); ?>;border-radius:<?php echo esc_attr( max( 14, (int) $settings['border_radius'] - 2 ) ); ?>px;background:rgba(255,255,255,.7)}
            .eop-proposal-notes span{display:block;margin-bottom:8px;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
            .eop-proposal-notes p{margin:0}
            .eop-proposal-totals{margin-top:24px;margin-left:auto;max-width:320px}
            .eop-proposal-total{display:flex;justify-content:space-between;padding:8px 0}
            .eop-proposal-total.is-grand{font-size:20px;font-weight:700;border-top:2px solid <?php echo esc_attr( $settings['primary_color'] ); ?>;margin-top:8px;padding-top:12px}
            .eop-proposal-button{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 22px;border:none;border-radius:<?php echo esc_attr( $settings['border_radius'] ); ?>px;background:<?php echo esc_attr( $settings['primary_color'] ); ?>;color:#fff;text-decoration:none;font-weight:700;cursor:pointer}
            .eop-proposal-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}
            .eop-proposal-note{margin-top:18px;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>}
            @media (max-width: 720px){.eop-proposal-wrap{font-size:15px}.eop-proposal-card{padding:22px}.eop-proposal-header{flex-direction:column;align-items:flex-start}.eop-proposal-title{font-size:<?php echo esc_attr( max( 26, $title_font_size - 8 ) ); ?>px}.eop-proposal-item{grid-template-columns:1fr}.eop-proposal-item__media{width:84px;height:84px}.eop-proposal-item__summary{justify-items:start;text-align:left}}
        </style>
        <div class="eop-proposal-wrap">
            <div class="eop-proposal-card">
                <div class="eop-proposal-header <?php echo $has_logo ? 'has-logo' : 'has-no-logo'; ?>">
                    <?php if ( $has_logo ) : ?>
                        <div class="eop-proposal-brand">
                            <img class="eop-proposal-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="">
                        </div>
                    <?php endif; ?>
                    <div class="eop-proposal-header__content">
                        <h1 class="eop-proposal-title"><?php echo esc_html( $settings['proposal_title'] ); ?></h1>
                        <p class="eop-proposal-text"><?php echo esc_html( $settings['proposal_description'] ); ?></p>
                        <div class="eop-proposal-status">
                            <?php echo esc_html( $confirmed ? __( 'Proposta confirmada', EOP_TEXT_DOMAIN ) : __( 'Aguardando confirmacao', EOP_TEXT_DOMAIN ) ); ?>
                        </div>
                    </div>
                </div>

                <div class="eop-proposal-meta">
                    <p><strong><?php esc_html_e( 'Pedido', EOP_TEXT_DOMAIN ); ?>:</strong> #<?php echo esc_html( $order->get_id() ); ?></p>
                    <p><strong><?php esc_html_e( 'Cliente', EOP_TEXT_DOMAIN ); ?>:</strong> <?php echo esc_html( $customer_name ); ?></p>
                    <?php if ( $show_email && $order->get_billing_email() ) : ?>
                        <p><strong><?php esc_html_e( 'E-mail', EOP_TEXT_DOMAIN ); ?>:</strong> <?php echo esc_html( $order->get_billing_email() ); ?></p>
                    <?php endif; ?>
                    <?php if ( $show_phone && $order->get_billing_phone() ) : ?>
                        <p><strong><?php esc_html_e( 'Telefone', EOP_TEXT_DOMAIN ); ?>:</strong> <?php echo esc_html( $order->get_billing_phone() ); ?></p>
                    <?php endif; ?>
                </div>

                <div class="eop-proposal-items">
                    <?php foreach ( $line_items as $line_item ) : ?>
                        <?php
                        $item      = $line_item['item'];
                        $product   = $line_item['product'];
                        $image_url = $product ? wp_get_attachment_image_url( $product->get_image_id(), 'medium' ) : '';

                        if ( ! $image_url ) {
                            $image_url = wc_placeholder_img_src( 'medium' );
                        }
                        ?>
                        <article class="eop-proposal-item">
                            <div class="eop-proposal-item__media">
                                <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $item->get_name() ); ?>">
                            </div>
                            <div class="eop-proposal-item__body">
                                <h3 class="eop-proposal-item__name"><?php echo esc_html( $item->get_name() ); ?></h3>
                                <?php if ( ! empty( $item_columns ) || ( $show_sku && $product && $product->get_sku() ) ) : ?>
                                    <div class="eop-proposal-item__meta">
                                        <?php foreach ( $item_columns as $column ) : ?>
                                            <?php if ( 'quantity' === $column['key'] ) : ?>
                                                <span class="eop-proposal-item__pill">
                                                    <?php
                                                    printf(
                                                        '%1$s: %2$s',
                                                        esc_html( $column['label'] ),
                                                        esc_html( $line_item['quantity'] )
                                                    );
                                                    ?>
                                                </span>
                                            <?php elseif ( 'unit_price' === $column['key'] ) : ?>
                                                <span class="eop-proposal-item__pill">
                                                    <?php
                                                    printf(
                                                        '%1$s: %2$s',
                                                        esc_html( $column['label'] ),
                                                        esc_html( wp_strip_all_tags( wc_price( $line_item['unit_price'] ) ) )
                                                    );
                                                    ?>
                                                </span>
                                            <?php elseif ( 'discount' === $column['key'] ) : ?>
                                                <span class="eop-proposal-item__pill">
                                                    <?php
                                                    printf(
                                                        '%1$s: %2$s (%3$s/%4$s)',
                                                        esc_html( $column['label'] ),
                                                        esc_html( number_format_i18n( $line_item['discount_percent'], abs( $line_item['discount_percent'] - round( $line_item['discount_percent'] ) ) < 0.01 ? 0 : 2 ) . '%' ),
                                                        esc_html( wp_strip_all_tags( wc_price( $line_item['discount_per_unit'] ) ) ),
                                                        esc_html__( 'un.', EOP_TEXT_DOMAIN )
                                                    );
                                                    ?>
                                                </span>
                                            <?php elseif ( 'discounted_unit_price' === $column['key'] ) : ?>
                                                <span class="eop-proposal-item__pill">
                                                    <?php
                                                    printf(
                                                        '%1$s: %2$s',
                                                        esc_html( $column['label'] ),
                                                        esc_html( wp_strip_all_tags( wc_price( $line_item['discounted_unit_price'] ) ) )
                                                    );
                                                    ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <?php if ( $show_sku && $product && $product->get_sku() ) : ?>
                                            <span class="eop-proposal-item__pill">
                                                <?php
                                                printf(
                                                    /* translators: %s: product sku */
                                                    esc_html__( 'SKU: %s', EOP_TEXT_DOMAIN ),
                                                    esc_html( $product->get_sku() )
                                                );
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ( $show_line_total ) : ?>
                                <div class="eop-proposal-item__summary">
                                    <span><?php echo esc_html( $item_labels['line_total'] ?? __( 'Total', EOP_TEXT_DOMAIN ) ); ?></span>
                                    <strong><?php echo wp_kses_post( wc_price( $line_item['line_total'] ) ); ?></strong>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ( ! empty( $total_rows ) ) : ?>
                    <div class="eop-proposal-totals">
                        <?php foreach ( $total_rows as $row ) : ?>
                            <div class="eop-proposal-total <?php echo esc_attr( $row['class'] ); ?>">
                                <span><?php echo esc_html( $row['label'] ); ?></span>
                                <span><?php echo wp_kses_post( $row['value'] ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ( $show_notes && '' !== $customer_note ) : ?>
                    <div class="eop-proposal-notes">
                        <span><?php esc_html_e( 'Observacoes', EOP_TEXT_DOMAIN ); ?></span>
                        <p><?php echo esc_html( $customer_note ); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ( '1' === $confirm_state ) : ?>
                    <p class="eop-proposal-note"><?php esc_html_e( 'Proposta confirmada com sucesso.', EOP_TEXT_DOMAIN ); ?></p>
                <?php endif; ?>

                <?php if ( ! $confirmed ) : ?>
                    <div class="eop-proposal-actions">
                        <form method="post">
                            <?php wp_nonce_field( 'eop_confirm_proposal', 'eop_confirm_proposal_nonce' ); ?>
                            <input type="hidden" name="eop_proposal_token" value="<?php echo esc_attr( $order->get_meta( '_eop_public_token', true ) ); ?>" />
                            <button type="submit" class="eop-proposal-button"><?php echo esc_html( $button_label ); ?></button>
                        </form>
                        <?php if ( $pdf_url ) : ?>
                            <a class="eop-proposal-button" href="<?php echo esc_url( $pdf_url ); ?>" download="<?php echo esc_attr( $order->get_id() . '.pdf' ); ?>">
                                <?php esc_html_e( 'Baixar PDF', EOP_TEXT_DOMAIN ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php elseif ( $payment_url || $pdf_url ) : ?>
                    <div class="eop-proposal-actions">
                        <?php if ( $payment_url ) : ?>
                            <a class="eop-proposal-button" href="<?php echo esc_url( $payment_url ); ?>">
                                <?php echo esc_html( $pay_label ); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ( $pdf_url ) : ?>
                            <a class="eop-proposal-button" href="<?php echo esc_url( $pdf_url ); ?>" download="<?php echo esc_attr( $order->get_id() . '.pdf' ); ?>">
                                <?php esc_html_e( 'Baixar PDF', EOP_TEXT_DOMAIN ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}
