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

    public static function get_order_by_token( $token ) {
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
        $flow_enabled    = class_exists( 'EOP_Post_Confirmation_Flow' ) && EOP_Post_Confirmation_Flow::is_enabled_for_order( $order );
        $current_flow_stage = $confirmed && $flow_enabled ? EOP_Post_Confirmation_Flow::get_current_stage( $order ) : '';
        $current_flow_label = $current_flow_stage ? EOP_Post_Confirmation_Flow::get_stage_label( $current_flow_stage ) : '';
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
            .eop-proposal-wrap{max-width:<?php echo esc_attr( $max_width ); ?>px;margin:32px auto;padding:0 16px 40px;font-family:<?php echo esc_attr( $font_css ); ?>;font-size:<?php echo esc_attr( $base_font_size ); ?>px;color:<?php echo esc_attr( $settings['proposal_text_color'] ); ?>}
            .eop-proposal-card{position:relative;overflow:hidden;background:linear-gradient(180deg,rgba(255,255,255,.99),rgba(245,247,255,.98));border:1px solid <?php echo esc_attr( $settings['border_color'] ); ?>;border-radius:<?php echo esc_attr( max( 26, (int) $settings['border_radius'] ) ); ?>px;padding:34px;box-shadow:0 24px 64px rgba(23,32,51,.1)}
            .eop-proposal-card::before{content:"";position:absolute;inset:0 0 auto 0;height:180px;background:radial-gradient(circle at top right,rgba(255,255,255,.24),transparent 34%),linear-gradient(135deg,rgba(5,10,118,.08) 0%,rgba(0,3,75,.02) 62%,rgba(63,102,255,.08) 100%)}
            .eop-proposal-card > *{position:relative;z-index:1}
            .eop-proposal-header{display:grid;grid-template-columns:auto minmax(0,1fr);align-items:start;gap:24px;margin-bottom:30px}
            .eop-proposal-header.has-no-logo{justify-content:flex-start}
            .eop-proposal-brand{display:flex;align-items:center;justify-content:center;min-width:120px;padding:18px 20px;border-radius:24px;background:rgba(255,255,255,.86);border:1px solid rgba(255,255,255,.74);box-shadow:0 18px 38px rgba(23,32,51,.06)}
            .eop-proposal-header__content{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:start}
            .eop-proposal-header__copy{display:grid;gap:12px;max-width:680px}
            .eop-proposal-header__copy-top{display:flex;flex-wrap:wrap;gap:10px}
            .eop-proposal-logo{max-height:64px;max-width:220px}
            .eop-proposal-status,.eop-proposal-stage{display:inline-flex;align-items:center;min-height:38px;padding:0 14px;border-radius:999px;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
            .eop-proposal-status{background:#eef4ff;color:<?php echo esc_attr( $settings['primary_color'] ); ?>}
            .eop-proposal-stage{background:rgba(5,10,118,.08);color:<?php echo esc_attr( $settings['proposal_text_color'] ); ?>}
            .eop-proposal-title{margin:0;font-size:<?php echo esc_attr( $title_font_size ); ?>px;line-height:1.02;letter-spacing:-.045em}
            .eop-proposal-text{margin:0;max-width:56ch;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>;font-size:16px;line-height:1.65}
            .eop-proposal-header__spotlight{display:grid;gap:8px;min-width:220px;padding:20px 22px;border:1px solid rgba(219,226,255,.94);border-radius:24px;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(240,244,255,.94));box-shadow:0 18px 36px rgba(23,32,51,.08)}
            .eop-proposal-header__spotlight span{color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>;font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
            .eop-proposal-header__spotlight strong{font-size:34px;line-height:1;letter-spacing:-.05em;color:<?php echo esc_attr( $settings['proposal_text_color'] ); ?>}
            .eop-proposal-header__spotlight small{color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>;font-size:13px;line-height:1.45}
            .eop-proposal-overview{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(280px,.75fr);gap:22px;align-items:start}
            .eop-proposal-overview__main,.eop-proposal-overview__side{display:grid;gap:18px}
            .eop-proposal-overview__side{position:sticky;top:18px}
            .eop-proposal-section,.eop-proposal-summary-card{padding:22px;border:1px solid rgba(219,226,255,.84);border-radius:24px;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(247,249,255,.94));box-shadow:0 16px 34px rgba(23,32,51,.06)}
            .eop-proposal-section__head{display:flex;justify-content:space-between;gap:12px;align-items:flex-end;margin-bottom:18px}
            .eop-proposal-section__eyebrow{display:block;margin-bottom:6px;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>;font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
            .eop-proposal-section__head h2,.eop-proposal-summary-card h2{margin:0;font-size:24px;line-height:1.06;letter-spacing:-.04em}
            .eop-proposal-summary-card h2{margin-bottom:14px}
            .eop-proposal-meta{display:grid;gap:12px}
            .eop-proposal-meta p{display:grid;gap:6px;margin:0;padding:14px 16px;border:1px solid <?php echo esc_attr( $settings['border_color'] ); ?>;border-radius:18px;background:rgba(255,255,255,.8)}
            .eop-proposal-meta strong{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>}
            .eop-proposal-items{display:grid;gap:14px}
            .eop-proposal-item{display:grid;grid-template-columns:96px minmax(0,1fr) auto;gap:18px;align-items:center;padding:18px;border:1px solid <?php echo esc_attr( $settings['border_color'] ); ?>;border-radius:<?php echo esc_attr( max( 18, (int) $settings['border_radius'] ) ); ?>px;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(245,247,255,.72))}
            .eop-proposal-item__media{width:96px;height:96px;border-radius:22px;overflow:hidden;background:#fff;border:1px solid <?php echo esc_attr( $settings['border_color'] ); ?>;display:flex;align-items:center;justify-content:center}
            .eop-proposal-item__media img{display:block;width:100%;height:100%;object-fit:cover}
            .eop-proposal-item__body{min-width:0}
            .eop-proposal-item__name{margin:0 0 8px;font-size:22px;line-height:1.2}
            .eop-proposal-item__meta{display:flex;flex-wrap:wrap;gap:10px 16px;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>;font-size:14px}
            .eop-proposal-item__pill{display:inline-flex;align-items:center;min-height:32px;padding:0 12px;border-radius:999px;background:#f1f4ff;color:<?php echo esc_attr( $settings['primary_color'] ); ?>;font-weight:700;line-height:1.2;white-space:nowrap}
            .eop-proposal-item__summary{display:grid;gap:6px;min-width:150px;justify-items:end;text-align:right}
            .eop-proposal-item__summary span{font-size:13px;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>;text-transform:uppercase;letter-spacing:.08em;font-weight:700}
            .eop-proposal-item__summary strong{font-size:28px;line-height:1;color:<?php echo esc_attr( $settings['proposal_text_color'] ); ?>}
            .eop-proposal-notes{padding:18px;border:1px solid <?php echo esc_attr( $settings['border_color'] ); ?>;border-radius:20px;background:rgba(255,255,255,.82)}
            .eop-proposal-notes span{display:block;margin-bottom:8px;color:<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
            .eop-proposal-notes p{margin:0}
            .eop-proposal-totals{display:grid;gap:2px}
            .eop-proposal-total{display:flex;justify-content:space-between;padding:8px 0}
            .eop-proposal-total.is-grand{font-size:20px;font-weight:700;border-top:2px solid <?php echo esc_attr( $settings['primary_color'] ); ?>;margin-top:8px;padding-top:12px}
            .eop-proposal-button{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 22px;border:none;border-radius:<?php echo esc_attr( $settings['border_radius'] ); ?>px;background:<?php echo esc_attr( $settings['primary_color'] ); ?>;color:#fff;text-decoration:none;font-weight:700;cursor:pointer}
            .eop-proposal-actions{display:flex;flex-wrap:wrap;gap:12px}
            .eop-proposal-actions form{display:flex;width:100%}
            .eop-proposal-actions .eop-proposal-button{width:100%}
            .eop-proposal-note{margin:0;padding:14px 16px;border-radius:18px;background:#ecfdf5;border:1px solid #bbf7d0;color:#166534}
            @media (max-width: 980px){.eop-proposal-header__content,.eop-proposal-overview{grid-template-columns:1fr}.eop-proposal-header__spotlight,.eop-proposal-overview__side{position:static}.eop-proposal-actions .eop-proposal-button{width:auto}}
            @media (max-width: 720px){.eop-proposal-wrap{font-size:15px;padding:0 10px 30px}.eop-proposal-card{padding:22px;border-radius:26px}.eop-proposal-card::before{height:150px}.eop-proposal-header{grid-template-columns:1fr}.eop-proposal-brand{justify-content:flex-start}.eop-proposal-title{font-size:<?php echo esc_attr( max( 26, $title_font_size - 8 ) ); ?>px}.eop-proposal-item{grid-template-columns:1fr}.eop-proposal-item__media{width:84px;height:84px}.eop-proposal-item__summary{justify-items:start;text-align:left}.eop-proposal-section,.eop-proposal-summary-card{padding:18px}}
        </style>
        <div class="eop-proposal-wrap<?php echo $confirmed ? ' is-confirmed' : ''; ?>">
            <div class="eop-proposal-card">
                <div class="eop-proposal-header <?php echo $has_logo ? 'has-logo' : 'has-no-logo'; ?>">
                    <?php if ( $has_logo ) : ?>
                        <div class="eop-proposal-brand">
                            <img class="eop-proposal-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="">
                        </div>
                    <?php endif; ?>
                    <div class="eop-proposal-header__content">
                        <div class="eop-proposal-header__copy">
                            <div class="eop-proposal-header__copy-top">
                                <div class="eop-proposal-status">
                                    <?php echo esc_html( $confirmed ? __( 'Proposta confirmada', EOP_TEXT_DOMAIN ) : __( 'Aguardando confirmacao', EOP_TEXT_DOMAIN ) ); ?>
                                </div>
                                <?php if ( $current_flow_label ) : ?>
                                    <div class="eop-proposal-stage"><?php echo esc_html( sprintf( __( 'Etapa atual: %s', EOP_TEXT_DOMAIN ), $current_flow_label ) ); ?></div>
                                <?php endif; ?>
                            </div>
                            <h1 class="eop-proposal-title"><?php echo esc_html( $settings['proposal_title'] ); ?></h1>
                            <p class="eop-proposal-text"><?php echo esc_html( $settings['proposal_description'] ); ?></p>
                        </div>
                        <div class="eop-proposal-header__spotlight">
                            <span><?php esc_html_e( 'Total aprovado', EOP_TEXT_DOMAIN ); ?></span>
                            <strong><?php echo wp_kses_post( wc_price( $totals['total'] ?? $order->get_total() ) ); ?></strong>
                            <small><?php esc_html_e( 'Revise os itens e conclua a etapa atual para liberar o restante da jornada.', EOP_TEXT_DOMAIN ); ?></small>
                        </div>
                    </div>
                </div>

                <div class="eop-proposal-overview">
                    <div class="eop-proposal-overview__main">
                        <section class="eop-proposal-section">
                            <div class="eop-proposal-section__head">
                                <div>
                                    <span class="eop-proposal-section__eyebrow"><?php esc_html_e( 'Resumo do pedido', EOP_TEXT_DOMAIN ); ?></span>
                                    <h2><?php esc_html_e( 'Itens aprovados', EOP_TEXT_DOMAIN ); ?></h2>
                                </div>
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
                        </section>

                        <?php if ( $show_notes && '' !== $customer_note ) : ?>
                            <section class="eop-proposal-section">
                                <div class="eop-proposal-notes">
                                    <span><?php esc_html_e( 'Observacoes', EOP_TEXT_DOMAIN ); ?></span>
                                    <p><?php echo esc_html( $customer_note ); ?></p>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if ( '1' === $confirm_state ) : ?>
                            <p class="eop-proposal-note"><?php esc_html_e( 'Proposta confirmada com sucesso.', EOP_TEXT_DOMAIN ); ?></p>
                        <?php endif; ?>
                    </div>

                    <aside class="eop-proposal-overview__side">
                        <section class="eop-proposal-summary-card">
                            <h2><?php esc_html_e( 'Contexto do pedido', EOP_TEXT_DOMAIN ); ?></h2>
                            <div class="eop-proposal-meta">
                                <p><strong><?php esc_html_e( 'Pedido', EOP_TEXT_DOMAIN ); ?></strong><span>#<?php echo esc_html( $order->get_id() ); ?></span></p>
                                <p><strong><?php esc_html_e( 'Cliente', EOP_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( $customer_name ); ?></span></p>
                                <?php if ( $show_email && $order->get_billing_email() ) : ?>
                                    <p><strong><?php esc_html_e( 'E-mail', EOP_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( $order->get_billing_email() ); ?></span></p>
                                <?php endif; ?>
                                <?php if ( $show_phone && $order->get_billing_phone() ) : ?>
                                    <p><strong><?php esc_html_e( 'Telefone', EOP_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( $order->get_billing_phone() ); ?></span></p>
                                <?php endif; ?>
                            </div>
                        </section>

                        <?php if ( ! empty( $total_rows ) ) : ?>
                            <section class="eop-proposal-summary-card">
                                <h2><?php esc_html_e( 'Resumo financeiro', EOP_TEXT_DOMAIN ); ?></h2>
                                <div class="eop-proposal-totals">
                                    <?php foreach ( $total_rows as $row ) : ?>
                                        <div class="eop-proposal-total <?php echo esc_attr( $row['class'] ); ?>">
                                            <span><?php echo esc_html( $row['label'] ); ?></span>
                                            <span><?php echo wp_kses_post( $row['value'] ); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if ( ! $confirmed ) : ?>
                            <section class="eop-proposal-summary-card">
                                <h2><?php esc_html_e( 'Confirmacao da proposta', EOP_TEXT_DOMAIN ); ?></h2>
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
                            </section>
                        <?php elseif ( ! $flow_enabled && ( $payment_url || $pdf_url ) ) : ?>
                            <section class="eop-proposal-summary-card">
                                <h2><?php esc_html_e( 'Acoes rapidas', EOP_TEXT_DOMAIN ); ?></h2>
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
                            </section>
                        <?php endif; ?>
                    </aside>
                </div>

                <?php if ( $confirmed && $flow_enabled ) : ?>
                    <?php echo EOP_Post_Confirmation_Flow::render_frontend_stage( $order, $line_items, $pdf_url ); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}
