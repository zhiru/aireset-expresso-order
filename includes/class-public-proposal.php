<?php
defined( 'ABSPATH' ) || exit;

class EOP_Public_Proposal {

    public static function init() {
        add_shortcode( 'expresso_order_proposal', array( __CLASS__, 'render_shortcode' ) );
        add_action( 'init', array( __CLASS__, 'handle_confirmation' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_public_assets' ) );
    }

    public static function maybe_enqueue_public_assets() {
        $token = '';

        if ( isset( $_GET['eop_proposal'] ) ) {
            $token = sanitize_text_field( wp_unslash( $_GET['eop_proposal'] ) );
        } elseif ( isset( $_GET['eop_token'] ) ) {
            $token = sanitize_text_field( wp_unslash( $_GET['eop_token'] ) );
        }

        if ( '' === $token ) {
            return;
        }

        $order = self::get_order_by_token( $token );

        if ( ! $order ) {
            return;
        }

        $settings = EOP_Settings::get_all();
        $font_url = method_exists( 'EOP_Settings', 'get_font_stylesheet_url' ) ? EOP_Settings::get_font_stylesheet_url( $settings['customer_experience_font_family'] ?? $settings['font_family'] ?? '' ) : '';

        if ( $font_url ) {
            wp_enqueue_style( 'eop-selected-font', $font_url, array(), null );
        }

        wp_enqueue_style( 'eop-frontend', EOP_PLUGIN_URL . 'assets/css/frontend.css', array(), EOP_VERSION );
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
        $is_flow_focus = $confirmed && $flow_enabled && in_array( $current_flow_stage, array( 'payment', 'contract', 'upload', 'products', 'completed' ), true );
        $document_config = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_document_display_settings( 'proposal' ) : array();
        $item_columns    = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_document_item_columns( 'proposal' ) : array();
        $item_labels     = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_document_item_labels( 'proposal' ) : array();
        $total_rows      = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_document_total_rows( $totals, 'proposal' ) : array();
        $total_rows      = self::prepare_total_rows_for_display( $order, $total_rows );
        $show_sku        = 'yes' === ( $document_config['show_sku'] ?? 'yes' );
        $show_email      = 'yes' === ( $document_config['show_email'] ?? 'yes' );
        $show_phone      = 'yes' === ( $document_config['show_phone'] ?? 'yes' );
        $show_notes      = 'yes' === ( $document_config['show_notes'] ?? 'yes' );
        $has_logo        = ! empty( $logo_url );
        $customer_name   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ?: __( 'Nao informado', EOP_TEXT_DOMAIN );
        $experience_font_value = $settings['customer_experience_font_family'] ?? $settings['font_family'];
        $experience_font_css   = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $experience_font_value ) : $font_css;
        $experience_font_url   = method_exists( 'EOP_Settings', 'get_font_stylesheet_url' ) ? EOP_Settings::get_font_stylesheet_url( $experience_font_value ) : $font_url;
        $base_font_size  = max( 13, absint( $settings['customer_experience_text_size'] ?? $settings['proposal_text_size'] ?? 16 ) );
        $title_font_size = max( 24, absint( $settings['customer_experience_title_size'] ?? $settings['proposal_title_size'] ?? 40 ) );
        $max_width       = max( 720, absint( $settings['proposal_max_width'] ?? 1120 ) );
        $customer_note   = trim( (string) $order->get_customer_note() );
        $experience_bg       = $settings['customer_experience_background_color'] ?? $settings['proposal_background_color'];
        $experience_hero_bg  = $settings['customer_experience_hero_background_color'] ?? $settings['primary_color'];
        $experience_panel_bg = $settings['customer_experience_panel_background_color'] ?? $settings['proposal_card_color'];
        $experience_side_bg  = $settings['customer_experience_sidebar_background_color'] ?? '#f6f8fc';
        $experience_accent   = $settings['customer_experience_accent_color'] ?? $settings['primary_color'];
        $experience_text     = $settings['customer_experience_text_color'] ?? $settings['proposal_text_color'];
        $experience_muted    = $settings['customer_experience_muted_color'] ?? $settings['proposal_muted_color'];
        $experience_eyebrow  = trim( (string) ( $settings['customer_experience_eyebrow'] ?? '' ) );
        $experience_title    = trim( (string) ( $settings['customer_experience_title'] ?? '' ) );
        $experience_desc     = trim( (string) ( $settings['customer_experience_description'] ?? '' ) );
        $total_label         = trim( (string) ( $settings['customer_experience_total_label'] ?? '' ) );
        $total_note          = trim( (string) ( $settings['customer_experience_total_note'] ?? '' ) );
        $items_eyebrow       = trim( (string) ( $settings['customer_experience_items_eyebrow'] ?? '' ) );
        $items_title         = trim( (string) ( $settings['customer_experience_items_title'] ?? '' ) );
        $summary_eyebrow     = trim( (string) ( $settings['customer_experience_summary_eyebrow'] ?? '' ) );
        $summary_title       = trim( (string) ( $settings['customer_experience_summary_title'] ?? '' ) );
        $financial_eyebrow   = trim( (string) ( $settings['customer_experience_financial_eyebrow'] ?? '' ) );
        $financial_title     = trim( (string) ( $settings['customer_experience_financial_title'] ?? '' ) );
        $actions_eyebrow     = trim( (string) ( $settings['customer_experience_actions_eyebrow'] ?? '' ) );
        $actions_title       = trim( (string) ( $settings['customer_experience_actions_title'] ?? '' ) );
        $show_line_total = (bool) array_filter(
            $item_columns,
            function ( $column ) {
                return isset( $column['key'] ) && 'line_total' === $column['key'];
            }
        );

        $items_eyebrow     = self::normalize_customer_experience_copy( $items_eyebrow, 'Resumo visual', '' );
        $items_title       = self::normalize_customer_experience_copy( $items_title, 'O que esta incluso', 'Itens' );
        $financial_eyebrow = self::normalize_customer_experience_copy( $financial_eyebrow, 'Fechamento', '' );
        $financial_title   = self::normalize_customer_experience_copy( $financial_title, 'Resumo financeiro', 'Resumo' );

        ob_start();
        ?>
        <?php if ( ! wp_style_is( 'eop-frontend', 'enqueued' ) && ! wp_style_is( 'eop-frontend', 'done' ) ) : ?>
            <link rel="stylesheet" href="<?php echo esc_url( EOP_PLUGIN_URL . 'assets/css/frontend.css?ver=' . EOP_VERSION ); ?>">
        <?php endif; ?>
        <?php if ( $experience_font_url ) : ?>
            <link rel="stylesheet" href="<?php echo esc_url( $experience_font_url ); ?>">
        <?php endif; ?>
        <style>
            body{background:<?php echo esc_attr( $experience_bg ); ?>}
            .eop-proposal-wrap{max-width:<?php echo esc_attr( $max_width ); ?>px;margin:32px auto;padding:0 16px 46px;font-family:<?php echo esc_attr( $experience_font_css ); ?>;font-size:<?php echo esc_attr( $base_font_size ); ?>px;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-card{display:grid;gap:24px}
            .eop-proposal-hero{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1.15fr) minmax(280px,.85fr);gap:24px;padding:34px;border-radius:<?php echo esc_attr( max( 28, (int) $settings['border_radius'] + 8 ) ); ?>px;background:linear-gradient(135deg,<?php echo esc_attr( $experience_hero_bg ); ?> 0%,rgba(15,27,53,.95) 58%,rgba(15,27,53,.82) 100%);box-shadow:0 28px 68px rgba(15,27,53,.24)}
            .eop-proposal-hero::before{content:"";position:absolute;inset:auto -10% -25% auto;width:340px;height:340px;border-radius:50%;background:radial-gradient(circle,rgba(215,138,47,.28),transparent 70%)}
            .eop-proposal-hero > *{position:relative;z-index:1}
            .eop-proposal-hero__main,.eop-proposal-hero__aside{display:grid;gap:18px;align-content:start}
            .eop-proposal-brandline{display:flex;align-items:flex-start;gap:18px}
            .eop-proposal-brand{display:flex;align-items:center;justify-content:center;min-width:110px;min-height:90px;padding:16px 18px;border-radius:26px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);backdrop-filter:blur(10px)}
            .eop-proposal-logo{max-height:60px;max-width:210px}
            .eop-proposal-hero__copy{display:grid;gap:12px;max-width:640px}
            .eop-proposal-hero__top{display:flex;flex-wrap:wrap;gap:10px}
            .eop-proposal-status,.eop-proposal-stage{display:inline-flex;align-items:center;min-height:38px;padding:0 14px;border-radius:999px;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
            .eop-proposal-status{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.14)}
            .eop-proposal-stage{background:rgba(215,138,47,.16);color:#fff;border:1px solid rgba(215,138,47,.2)}
            .eop-proposal-eyebrow{display:block;color:rgba(255,255,255,.68);font-size:11px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}
            .eop-proposal-title{margin:0;font-size:<?php echo esc_attr( $title_font_size ); ?>px;line-height:.98;letter-spacing:-.05em;color:#fff}
            .eop-proposal-text{margin:0;max-width:58ch;color:rgba(255,255,255,.8);font-size:16px;line-height:1.7}
            .eop-proposal-hero__aside{padding:24px;border-radius:28px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);backdrop-filter:blur(10px)}
            .eop-proposal-hero__aside-label{color:rgba(255,255,255,.64);font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
            .eop-proposal-hero__aside strong{font-size:44px;line-height:.95;letter-spacing:-.06em;color:#fff}
            .eop-proposal-hero__aside p{margin:0;color:rgba(255,255,255,.76);line-height:1.65}
            .eop-proposal-hero__meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:4px}
            .eop-proposal-hero__meta-item{padding:14px 16px;border-radius:20px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1)}
            .eop-proposal-hero__meta-item span{display:block;color:rgba(255,255,255,.62);font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
            .eop-proposal-hero__meta-item strong{display:block;margin-top:7px;font-size:18px;line-height:1.2;color:#fff}
            .eop-proposal-overview{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(300px,.85fr);gap:24px;align-items:start}
            .eop-proposal-overview__main,.eop-proposal-overview__side{display:grid;gap:18px}
            .eop-proposal-overview__side{position:sticky;top:18px}
            .eop-proposal-section,.eop-proposal-summary-card{padding:24px;border-radius:28px;border:1px solid rgba(15,27,53,.08);box-shadow:0 18px 38px rgba(15,27,53,.08)}
            .eop-proposal-section{background:<?php echo esc_attr( $experience_panel_bg ); ?>}
            .eop-proposal-summary-card{background:<?php echo esc_attr( $experience_side_bg ); ?>}
            .eop-proposal-section__head{display:flex;justify-content:space-between;gap:12px;align-items:flex-end;margin-bottom:18px}
            .eop-proposal-section__eyebrow{display:block;margin-bottom:6px;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
            .eop-proposal-section__head h2,.eop-proposal-summary-card h2{margin:0;font-size:26px;line-height:1.06;letter-spacing:-.04em;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-summary-card__eyebrow{display:block;margin-bottom:8px;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
            .eop-proposal-meta{display:grid;gap:12px}
            .eop-proposal-meta p{display:grid;gap:6px;margin:0;padding:14px 16px;border:1px solid rgba(15,27,53,.08);border-radius:18px;background:rgba(255,255,255,.82)}
            .eop-proposal-meta strong{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:<?php echo esc_attr( $experience_muted ); ?>}
            .eop-proposal-items{display:grid;gap:14px}
            .eop-proposal-item{display:grid;grid-template-columns:108px minmax(0,1fr) auto;gap:18px;align-items:center;padding:18px;border:1px solid rgba(15,27,53,.08);border-radius:24px;background:linear-gradient(180deg,rgba(255,255,255,.99),rgba(246,248,252,.92))}
            .eop-proposal-item__media{width:108px;height:108px;border-radius:24px;overflow:hidden;background:#fff;border:1px solid rgba(15,27,53,.08);display:flex;align-items:center;justify-content:center}
            .eop-proposal-item__media img{display:block;width:100%;height:100%;object-fit:cover}
            .eop-proposal-item__body{min-width:0}
            .eop-proposal-item__name{margin:0 0 8px;font-size:23px;line-height:1.18;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-item__meta{display:flex;flex-wrap:wrap;gap:8px 12px;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:14px}
            .eop-proposal-item__pill{display:inline-flex;align-items:center;min-height:32px;padding:0 12px;border-radius:999px;background:rgba(215,138,47,.12);color:<?php echo esc_attr( $experience_accent ); ?>;font-weight:700;line-height:1.2;white-space:nowrap;max-width:100%}
            .eop-proposal-item__pill--discount{display:grid;gap:2px;align-items:flex-start;padding:9px 12px;white-space:normal;border-radius:18px}
            .eop-proposal-item__pill-main{font-size:13px;line-height:1.2}
            .eop-proposal-item__pill-sub{font-size:12px;line-height:1.25;color:<?php echo esc_attr( $experience_muted ); ?>}
            .eop-proposal-item__summary{display:grid;gap:6px;min-width:150px;justify-items:end;text-align:right}
            .eop-proposal-item__summary span{font-size:13px;color:<?php echo esc_attr( $experience_muted ); ?>;text-transform:uppercase;letter-spacing:.08em;font-weight:700}
            .eop-proposal-item__summary strong{font-size:30px;line-height:1;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-notes{padding:18px;border:1px solid rgba(15,27,53,.08);border-radius:22px;background:rgba(255,255,255,.82)}
            .eop-proposal-notes span{display:block;margin-bottom:8px;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
            .eop-proposal-notes p{margin:0;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-totals{display:grid;gap:2px}
            .eop-proposal-total{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:9px 0;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-total.is-grand{font-size:20px;font-weight:800;border-top:2px solid <?php echo esc_attr( $experience_accent ); ?>;margin-top:8px;padding-top:12px}
            .eop-proposal-total__value{display:grid;justify-items:end;gap:2px;text-align:right}
            .eop-proposal-total__value strong{font-size:15px;line-height:1.1;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-total__value small{font-size:12px;line-height:1.25;color:<?php echo esc_attr( $experience_muted ); ?>}
            .eop-proposal-button{display:inline-flex;align-items:center;justify-content:center;min-height:50px;padding:0 22px;border:none;border-radius:<?php echo esc_attr( $settings['border_radius'] ); ?>px;background:<?php echo esc_attr( $experience_accent ); ?>;color:#fff;text-decoration:none;font-weight:700;cursor:pointer;box-shadow:0 16px 30px rgba(215,138,47,.2)}
            .eop-proposal-button--secondary{background:#fff;color:<?php echo esc_attr( $experience_text ); ?>;box-shadow:none;border:1px solid rgba(15,27,53,.12)}
            .eop-proposal-actions{display:flex;flex-wrap:wrap;gap:12px}
            .eop-proposal-actions form{display:flex;width:100%}
            .eop-proposal-actions .eop-proposal-button{width:100%}
            .eop-proposal-note{margin:0;padding:14px 16px;border-radius:18px;background:#ecfdf5;border:1px solid #bbf7d0;color:#166534}
            .eop-proposal-wrap.is-flow-focus{max-width:<?php echo esc_attr( min( $max_width, 1040 ) ); ?>px;padding-bottom:34px}
            .eop-proposal-wrap.is-flow-focus .eop-proposal-card{gap:0}
            @media (max-width: 980px){.eop-proposal-hero,.eop-proposal-overview{grid-template-columns:1fr}.eop-proposal-overview__side{position:static}.eop-proposal-actions .eop-proposal-button{width:auto}}
            @media (max-width: 720px){.eop-proposal-wrap{font-size:15px;padding:0 10px 30px}.eop-proposal-hero,.eop-proposal-section,.eop-proposal-summary-card{padding:20px;border-radius:24px}.eop-proposal-brandline{flex-direction:column}.eop-proposal-hero__meta{grid-template-columns:1fr}.eop-proposal-title{font-size:<?php echo esc_attr( max( 28, $title_font_size - 10 ) ); ?>px}.eop-proposal-item{grid-template-columns:1fr}.eop-proposal-item__media{width:88px;height:88px}.eop-proposal-item__summary{justify-items:start;text-align:left}.eop-proposal-item__meta{gap:8px}.eop-proposal-total{gap:10px}.eop-proposal-total__value strong{font-size:14px}}
        </style>
        <div class="eop-proposal-wrap<?php echo $confirmed ? ' is-confirmed' : ''; ?><?php echo $is_flow_focus ? ' is-flow-focus' : ''; ?>">
            <div class="eop-proposal-card">
                <?php if ( $is_flow_focus ) : ?>
                    <?php echo EOP_Post_Confirmation_Flow::render_frontend_stage( $order, $line_items, $pdf_url ); ?>
                <?php else : ?>
                <div class="eop-proposal-hero">
                    <div class="eop-proposal-hero__main">
                        <div class="eop-proposal-brandline">
                            <?php if ( $has_logo ) : ?>
                                <div class="eop-proposal-brand">
                                    <img class="eop-proposal-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="">
                                </div>
                            <?php endif; ?>
                            <div class="eop-proposal-hero__copy">
                                <div class="eop-proposal-hero__top">
                                    <div class="eop-proposal-status">
                                        <?php echo esc_html( $confirmed ? __( 'Proposta confirmada', EOP_TEXT_DOMAIN ) : __( 'Aguardando confirmacao', EOP_TEXT_DOMAIN ) ); ?>
                                    </div>
                                    <?php if ( $current_flow_label ) : ?>
                                        <div class="eop-proposal-stage"><?php echo esc_html( sprintf( __( 'Etapa atual: %s', EOP_TEXT_DOMAIN ), $current_flow_label ) ); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ( '' !== $experience_eyebrow ) : ?>
                                    <span class="eop-proposal-eyebrow"><?php echo esc_html( $experience_eyebrow ); ?></span>
                                <?php endif; ?>
                                <h1 class="eop-proposal-title"><?php echo esc_html( '' !== $experience_title ? $experience_title : $settings['proposal_title'] ); ?></h1>
                                <p class="eop-proposal-text"><?php echo esc_html( '' !== $experience_desc ? $experience_desc : $settings['proposal_description'] ); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="eop-proposal-hero__aside">
                        <span class="eop-proposal-hero__aside-label"><?php echo esc_html( '' !== $total_label ? $total_label : __( 'Total aprovado', EOP_TEXT_DOMAIN ) ); ?></span>
                        <strong><?php echo wp_kses_post( wc_price( $totals['total'] ?? $order->get_total() ) ); ?></strong>
                        <p><?php echo esc_html( '' !== $total_note ? $total_note : __( 'Revise os itens e conclua a etapa atual para liberar o restante da jornada.', EOP_TEXT_DOMAIN ) ); ?></p>
                        <div class="eop-proposal-hero__meta">
                            <div class="eop-proposal-hero__meta-item">
                                <span><?php esc_html_e( 'Pedido', EOP_TEXT_DOMAIN ); ?></span>
                                <strong>#<?php echo esc_html( $order->get_id() ); ?></strong>
                            </div>
                            <div class="eop-proposal-hero__meta-item">
                                <span><?php esc_html_e( 'Cliente', EOP_TEXT_DOMAIN ); ?></span>
                                <strong><?php echo esc_html( $customer_name ); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="eop-proposal-overview">
                    <div class="eop-proposal-overview__main">
                        <section class="eop-proposal-section">
                            <div class="eop-proposal-section__head">
                                <div>
                                    <?php if ( '' !== $items_eyebrow ) : ?>
                                        <span class="eop-proposal-section__eyebrow"><?php echo esc_html( $items_eyebrow ); ?></span>
                                    <?php endif; ?>
                                    <h2><?php echo esc_html( '' !== $items_title ? $items_title : __( 'Itens', EOP_TEXT_DOMAIN ) ); ?></h2>
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
                                                            <div class="eop-proposal-item__pill eop-proposal-item__pill--discount">
                                                                <strong class="eop-proposal-item__pill-main"><?php echo esc_html( $column['label'] . ': ' . number_format_i18n( $line_item['discount_percent'], abs( $line_item['discount_percent'] - round( $line_item['discount_percent'] ) ) < 0.01 ? 0 : 2 ) . '%' ); ?></strong>
                                                                <small class="eop-proposal-item__pill-sub"><?php echo esc_html( wp_strip_all_tags( wc_price( $line_item['discount_per_unit'] ) ) . ' / ' . __( 'un.', EOP_TEXT_DOMAIN ) ); ?></small>
                                                            </div>
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
                            <span class="eop-proposal-summary-card__eyebrow"><?php echo esc_html( '' !== $summary_eyebrow ? $summary_eyebrow : __( 'Contexto do pedido', EOP_TEXT_DOMAIN ) ); ?></span>
                            <h2><?php echo esc_html( '' !== $summary_title ? $summary_title : __( 'Contexto do pedido', EOP_TEXT_DOMAIN ) ); ?></h2>
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
                                <?php if ( '' !== $financial_eyebrow ) : ?>
                                    <span class="eop-proposal-summary-card__eyebrow"><?php echo esc_html( $financial_eyebrow ); ?></span>
                                <?php endif; ?>
                                <h2><?php echo esc_html( '' !== $financial_title ? $financial_title : __( 'Resumo', EOP_TEXT_DOMAIN ) ); ?></h2>
                                <div class="eop-proposal-totals">
                                    <?php foreach ( $total_rows as $row ) : ?>
                                        <div class="eop-proposal-total <?php echo esc_attr( $row['class'] ); ?>">
                                            <span><?php echo esc_html( $row['label'] ); ?></span>
                                            <?php if ( ! empty( $row['main_value'] ) ) : ?>
                                                <div class="eop-proposal-total__value">
                                                    <strong><?php echo esc_html( $row['main_value'] ); ?></strong>
                                                    <?php if ( ! empty( $row['sub_value'] ) ) : ?>
                                                        <small><?php echo esc_html( $row['sub_value'] ); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else : ?>
                                                <span><?php echo wp_kses_post( $row['value'] ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if ( ! $confirmed ) : ?>
                            <section class="eop-proposal-summary-card">
                                <span class="eop-proposal-summary-card__eyebrow"><?php echo esc_html( '' !== $actions_eyebrow ? $actions_eyebrow : __( 'Confirmacao da proposta', EOP_TEXT_DOMAIN ) ); ?></span>
                                <h2><?php echo esc_html( '' !== $actions_title ? $actions_title : __( 'Confirmacao da proposta', EOP_TEXT_DOMAIN ) ); ?></h2>
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
                                <span class="eop-proposal-summary-card__eyebrow"><?php echo esc_html( '' !== $actions_eyebrow ? $actions_eyebrow : __( 'Acoes rapidas', EOP_TEXT_DOMAIN ) ); ?></span>
                                <h2><?php echo esc_html( '' !== $actions_title ? $actions_title : __( 'Acoes rapidas', EOP_TEXT_DOMAIN ) ); ?></h2>
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
                <?php endif; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    private static function normalize_customer_experience_copy( $value, $legacy, $replacement ) {
        $value = trim( (string) $value );

        return $legacy === $value ? $replacement : $value;
    }

    private static function prepare_total_rows_for_display( WC_Order $order, $rows ) {
        $rows           = is_array( $rows ) ? $rows : array();
        $subtotal_value = (float) $order->get_subtotal();

        foreach ( $rows as $index => $row ) {
            if ( ! is_array( $row ) || 'discount' !== ( $row['key'] ?? '' ) ) {
                continue;
            }

            $discount_amount = abs( (float) ( $row['raw'] ?? 0 ) );
            $discount_percent = $subtotal_value > 0 ? ( $discount_amount / $subtotal_value ) * 100 : 0;
            $rows[ $index ]['main_value'] = number_format_i18n( $discount_percent, abs( $discount_percent - round( $discount_percent ) ) < 0.01 ? 0 : 2 ) . '%';
            $rows[ $index ]['sub_value']  = wp_strip_all_tags( wc_price( (float) ( $row['raw'] ?? 0 ) ) );
        }

        return $rows;
    }
}
