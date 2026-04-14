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
        $page_id = absint( EOP_Settings::get( 'proposal_page_id', 0 ) );
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
        $totals        = EOP_Order_Creator::sync_order_totals( $order );
        $settings      = EOP_Settings::get_all();
        $logo_url      = $settings['brand_logo_url'];
        $font_url      = method_exists( 'EOP_Settings', 'get_font_stylesheet_url' ) ? EOP_Settings::get_font_stylesheet_url( $settings['font_family'] ) : '';
        $font_css      = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $settings['font_family'] ) : "'Segoe UI', sans-serif";
        $confirmed     = 'yes' === $order->get_meta( '_eop_proposal_confirmed', true );
        $line_items    = $order->get_items();
        $button_label  = $settings['proposal_button_label'];
        $pay_label     = ! empty( $settings['proposal_pay_button_label'] ) ? $settings['proposal_pay_button_label'] : __( 'Ir para pagamento', EOP_TEXT_DOMAIN );
        $confirm_state = isset( $_GET['eop_confirmed'] ) ? sanitize_text_field( wp_unslash( $_GET['eop_confirmed'] ) ) : '';
        $can_pay       = 'yes' === EOP_Settings::get( 'enable_checkout_confirmation', 'no' ) && method_exists( $order, 'needs_payment' ) && $order->needs_payment();
        $payment_url   = $can_pay ? $order->get_checkout_payment_url() : '';

        ob_start();
        ?>
        <?php if ( $font_url ) : ?>
            <link rel="stylesheet" href="<?php echo esc_url( $font_url ); ?>">
        <?php endif; ?>
        <style>
            body{background:<?php echo esc_attr( $settings['proposal_background_color'] ); ?>}
            .eop-proposal-wrap{max-width:960px;margin:32px auto;padding:0 16px;font-family:<?php echo esc_attr( $font_css ); ?>;color:<?php echo esc_attr( $settings['proposal_text_color'] ); ?>}
            .eop-proposal-card{background:<?php echo esc_attr( $settings['proposal_card_color'] ); ?>;border:1px solid <?php echo esc_attr( $settings['border_color'] ); ?>;border-radius:<?php echo esc_attr( $settings['border_radius'] ); ?>px;padding:28px;box-shadow:0 10px 30px rgba(23,32,51,.06)}
            .eop-proposal-header{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:24px}
            .eop-proposal-logo{max-height:56px;max-width:180px}
            .eop-proposal-title{margin:0 0 6px;font-size:32px;line-height:1.1}
            .eop-proposal-text{margin:0;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>}
            .eop-proposal-status{display:inline-block;margin-top:16px;padding:10px 14px;border-radius:999px;background:#eef4ff;color:<?php echo esc_attr( $settings['primary_color'] ); ?>;font-weight:700}
            .eop-proposal-items{display:grid;gap:16px;margin-top:24px}
            .eop-proposal-item{display:grid;grid-template-columns:96px minmax(0,1fr) auto;gap:18px;align-items:center;padding:18px;border:1px solid <?php echo esc_attr( $settings['border_color'] ); ?>;border-radius:<?php echo esc_attr( max( 18, (int) $settings['border_radius'] ) ); ?>px;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(245,247,255,.72))}
            .eop-proposal-item__media{width:96px;height:96px;border-radius:22px;overflow:hidden;background:#fff;border:1px solid <?php echo esc_attr( $settings['border_color'] ); ?>;display:flex;align-items:center;justify-content:center}
            .eop-proposal-item__media img{display:block;width:100%;height:100%;object-fit:cover}
            .eop-proposal-item__body{min-width:0}
            .eop-proposal-item__name{margin:0 0 8px;font-size:22px;line-height:1.2}
            .eop-proposal-item__meta{display:flex;flex-wrap:wrap;gap:10px 16px;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>;font-size:14px}
            .eop-proposal-item__pill{display:inline-flex;align-items:center;min-height:32px;padding:0 12px;border-radius:999px;background:#f1f4ff;color:<?php echo esc_attr( $settings['primary_color'] ); ?>;font-weight:700}
            .eop-proposal-item__summary{display:grid;gap:6px;min-width:150px;justify-items:end;text-align:right}
            .eop-proposal-item__summary span{font-size:13px;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>;text-transform:uppercase;letter-spacing:.08em;font-weight:700}
            .eop-proposal-item__summary strong{font-size:28px;line-height:1;color:<?php echo esc_attr( $settings['proposal_text_color'] ); ?>}
            .eop-proposal-totals{margin-top:24px;margin-left:auto;max-width:320px}
            .eop-proposal-total{display:flex;justify-content:space-between;padding:8px 0}
            .eop-proposal-total.is-grand{font-size:20px;font-weight:700;border-top:2px solid <?php echo esc_attr( $settings['primary_color'] ); ?>;margin-top:8px;padding-top:12px}
            .eop-proposal-button{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 22px;border:none;border-radius:<?php echo esc_attr( $settings['border_radius'] ); ?>px;background:<?php echo esc_attr( $settings['primary_color'] ); ?>;color:#fff;text-decoration:none;font-weight:700;cursor:pointer}
            .eop-proposal-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}
            .eop-proposal-note{margin-top:18px;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>}
            @media (max-width: 720px){.eop-proposal-header{flex-direction:column;align-items:flex-start}.eop-proposal-title{font-size:26px}.eop-proposal-item{grid-template-columns:1fr}.eop-proposal-item__media{width:84px;height:84px}.eop-proposal-item__summary{justify-items:start;text-align:left}}
        </style>
        <div class="eop-proposal-wrap">
            <div class="eop-proposal-card">
                <div class="eop-proposal-header">
                    <div>
                        <?php if ( $logo_url ) : ?>
                            <img class="eop-proposal-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="">
                        <?php endif; ?>
                    </div>
                    <div>
                        <h1 class="eop-proposal-title"><?php echo esc_html( $settings['proposal_title'] ); ?></h1>
                        <p class="eop-proposal-text"><?php echo esc_html( $settings['proposal_description'] ); ?></p>
                        <div class="eop-proposal-status">
                            <?php echo esc_html( $confirmed ? __( 'Proposta confirmada', EOP_TEXT_DOMAIN ) : __( 'Aguardando confirmacao', EOP_TEXT_DOMAIN ) ); ?>
                        </div>
                    </div>
                </div>

                <div class="eop-proposal-meta">
                    <p><strong><?php esc_html_e( 'Pedido', EOP_TEXT_DOMAIN ); ?>:</strong> #<?php echo esc_html( $order->get_id() ); ?></p>
                    <p><strong><?php esc_html_e( 'Cliente', EOP_TEXT_DOMAIN ); ?>:</strong> <?php echo esc_html( trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ?: __( 'Nao informado', EOP_TEXT_DOMAIN ) ); ?></p>
                </div>

                <div class="eop-proposal-items">
                    <?php foreach ( $line_items as $item ) : ?>
                        <?php
                        $product   = $item->get_product();
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
                                <div class="eop-proposal-item__meta">
                                    <span class="eop-proposal-item__pill">
                                        <?php
                                        printf(
                                            /* translators: %s: quantity */
                                            esc_html__( 'Quantidade: %s', EOP_TEXT_DOMAIN ),
                                            esc_html( $item->get_quantity() )
                                        );
                                        ?>
                                    </span>
                                    <?php if ( $product && $product->get_sku() ) : ?>
                                        <span>
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
                            </div>
                            <div class="eop-proposal-item__summary">
                                <span><?php esc_html_e( 'Subtotal', EOP_TEXT_DOMAIN ); ?></span>
                                <strong><?php echo wp_kses_post( wc_price( $item->get_total() ) ); ?></strong>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="eop-proposal-totals">
                    <div class="eop-proposal-total"><span><?php esc_html_e( 'Subtotal', EOP_TEXT_DOMAIN ); ?></span><span><?php echo wp_kses_post( wc_price( (float) $totals['items_subtotal'] ) ); ?></span></div>
                    <div class="eop-proposal-total"><span><?php esc_html_e( 'Frete', EOP_TEXT_DOMAIN ); ?></span><span><?php echo wp_kses_post( wc_price( (float) $totals['shipping_total'] ) ); ?></span></div>
                    <div class="eop-proposal-total"><span><?php esc_html_e( 'Desconto', EOP_TEXT_DOMAIN ); ?></span><span><?php echo wp_kses_post( wc_price( (float) $totals['discount_total'] * -1 ) ); ?></span></div>
                    <div class="eop-proposal-total is-grand"><span><?php esc_html_e( 'Total', EOP_TEXT_DOMAIN ); ?></span><span><?php echo wp_kses_post( wc_price( (float) $totals['grand_total'] ) ); ?></span></div>
                </div>

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
                    </div>
                <?php elseif ( $payment_url ) : ?>
                    <div class="eop-proposal-actions">
                        <a class="eop-proposal-button" href="<?php echo esc_url( $payment_url ); ?>">
                            <?php echo esc_html( $pay_label ); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}
