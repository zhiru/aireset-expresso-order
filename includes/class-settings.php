<?php
defined( 'ABSPATH' ) || exit;

class EOP_Settings {

    const OPTION_KEY = 'eop_settings';
    private static $page_hook = '';

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_menu', array( __CLASS__, 'register_submenu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_filter( 'admin_body_class', array( __CLASS__, 'filter_admin_body_class' ) );
    }

    public static function get_defaults() {
        return array(
            'flow_mode'                   => 'proposal',
            'discount_mode'               => 'both',
            'enable_checkout_confirmation' => 'no',
            'proposal_page_id'            => 0,
            'brand_logo_url'              => '',
            'primary_color'               => '#00034b',
            'surface_color'               => '#ffffff',
            'border_color'                => '#dbe3f0',
            'proposal_background_color'   => '#f5f7ff',
            'proposal_card_color'         => '#ffffff',
            'proposal_text_color'         => '#172033',
            'proposal_muted_color'        => '#5b6474',
            'border_radius'               => '18',
            'font_family'                 => 'Montserrat:400,700',
            'panel_title'                 => 'Pedido Expresso',
            'panel_subtitle'              => 'Monte o pedido, gere a proposta e compartilhe com o cliente.',
            'proposal_title'              => 'Sua proposta esta pronta',
            'proposal_description'        => 'Revise os itens e confirme para continuar.',
            'proposal_button_label'       => 'Confirmar proposta',
            'proposal_pay_button_label'   => 'Ir para pagamento',
        );
    }

    public static function get_all() {
        return wp_parse_args( get_option( self::OPTION_KEY, array() ), self::get_defaults() );
    }

    public static function get( $key, $default = null ) {
        $settings = self::get_all();

        if ( array_key_exists( $key, $settings ) ) {
            return $settings[ $key ];
        }

        return null === $default ? '' : $default;
    }

    public static function register_settings() {
        register_setting(
            'eop_settings_group',
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
                'default'           => self::get_defaults(),
            )
        );
    }

    public static function sanitize_settings( $input ) {
        $defaults = self::get_defaults();
        $input    = is_array( $input ) ? $input : array();

        return array(
            'flow_mode'                    => in_array( $input['flow_mode'] ?? '', array( 'direct_order', 'proposal' ), true ) ? $input['flow_mode'] : $defaults['flow_mode'],
            'discount_mode'                => in_array( $input['discount_mode'] ?? '', array( 'percent', 'fixed', 'both' ), true ) ? $input['discount_mode'] : $defaults['discount_mode'],
            'enable_checkout_confirmation' => 'yes' === ( $input['enable_checkout_confirmation'] ?? 'no' ) ? 'yes' : 'no',
            'proposal_page_id'             => absint( $input['proposal_page_id'] ?? 0 ),
            'brand_logo_url'               => esc_url_raw( $input['brand_logo_url'] ?? '' ),
            'primary_color'                => self::sanitize_color( $input['primary_color'] ?? $defaults['primary_color'], $defaults['primary_color'] ),
            'surface_color'                => self::sanitize_color( $input['surface_color'] ?? $defaults['surface_color'], $defaults['surface_color'] ),
            'border_color'                 => self::sanitize_color( $input['border_color'] ?? $defaults['border_color'], $defaults['border_color'] ),
            'proposal_background_color'    => self::sanitize_color( $input['proposal_background_color'] ?? $defaults['proposal_background_color'], $defaults['proposal_background_color'] ),
            'proposal_card_color'          => self::sanitize_color( $input['proposal_card_color'] ?? $defaults['proposal_card_color'], $defaults['proposal_card_color'] ),
            'proposal_text_color'          => self::sanitize_color( $input['proposal_text_color'] ?? $defaults['proposal_text_color'], $defaults['proposal_text_color'] ),
            'proposal_muted_color'         => self::sanitize_color( $input['proposal_muted_color'] ?? $defaults['proposal_muted_color'], $defaults['proposal_muted_color'] ),
            'border_radius'                => (string) max( 0, min( 48, absint( $input['border_radius'] ?? $defaults['border_radius'] ) ) ),
            'font_family'                  => self::sanitize_font_family( $input['font_family'] ?? $defaults['font_family'], $defaults['font_family'] ),
            'panel_title'                  => sanitize_text_field( $input['panel_title'] ?? $defaults['panel_title'] ),
            'panel_subtitle'               => sanitize_textarea_field( $input['panel_subtitle'] ?? $defaults['panel_subtitle'] ),
            'proposal_title'               => sanitize_text_field( $input['proposal_title'] ?? $defaults['proposal_title'] ),
            'proposal_description'         => sanitize_textarea_field( $input['proposal_description'] ?? $defaults['proposal_description'] ),
            'proposal_button_label'        => sanitize_text_field( $input['proposal_button_label'] ?? $defaults['proposal_button_label'] ),
            'proposal_pay_button_label'    => sanitize_text_field( $input['proposal_pay_button_label'] ?? $defaults['proposal_pay_button_label'] ),
        );
    }

    private static function sanitize_color( $value, $fallback ) {
        $color = sanitize_hex_color( $value );

        return $color ? $color : $fallback;
    }

    private static function sanitize_font_family( $value, $fallback ) {
        $value = sanitize_text_field( (string) $value );
        $value = preg_replace( '/[^A-Za-z0-9,\:\+\-\s]/', '', $value );
        $value = trim( preg_replace( '/\s+/', ' ', $value ) );

        return '' !== $value ? $value : $fallback;
    }

    public static function get_font_css_family( $font_value = '' ) {
        $font_value = self::sanitize_font_family( $font_value ?: self::get( 'font_family', self::get_defaults()['font_family'] ), self::get_defaults()['font_family'] );

        if ( false !== strpos( $font_value, ',' ) && false === strpos( $font_value, ':' ) ) {
            return $font_value;
        }

        $family = preg_replace( '/:.+$/', '', $font_value );
        $family = trim( str_replace( '+', ' ', $family ) );

        if ( '' === $family ) {
            return self::get_defaults()['font_family'];
        }

        return "'" . $family . "', sans-serif";
    }

    public static function get_font_stylesheet_url( $font_value = '' ) {
        $font_value = self::sanitize_font_family( $font_value ?: self::get( 'font_family', self::get_defaults()['font_family'] ), '' );

        if ( '' === $font_value || ( false !== strpos( $font_value, ',' ) && false === strpos( $font_value, ':' ) ) ) {
            return '';
        }

        return 'https://fonts.googleapis.com/css?family=' . str_replace( '%2B', '+', rawurlencode( $font_value ) ) . '&display=swap';
    }

    public static function register_submenu() {
        self::$page_hook = add_submenu_page(
            'aireset',
            __( 'Configuracoes', EOP_TEXT_DOMAIN ),
            __( 'Configuracoes', EOP_TEXT_DOMAIN ),
            'manage_options',
            'eop-configuracoes',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function enqueue_assets( $hook_suffix ) {
        $font_css_path = ABSPATH . 'wp-content/plugins/checkout-aireset-master/backend/assets/css/jquery.fontselect.css';
        $font_js_path  = ABSPATH . 'wp-content/plugins/checkout-aireset-master/backend/assets/js/jquery.fontselect.js';
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        if ( self::$page_hook && self::$page_hook === $hook_suffix ) {
            // ok
        } elseif ( 'eop-configuracoes' !== $page ) {
            return;
        }

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

        $font_url = self::get_font_stylesheet_url();
        if ( $font_url ) {
            wp_enqueue_style( 'eop-settings-selected-font', $font_url, array(), null );
        }
    }

    public static function filter_admin_body_class( $classes ) {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        if ( 'eop-configuracoes' !== $page ) {
            return $classes;
        }

        return trim( $classes . ' eop-settings-screen' );
    }

    public static function render_page() {
        if ( class_exists( 'EOP_Admin_Page' ) ) {
            wp_safe_redirect( EOP_Admin_Page::get_view_url( 'settings' ) );
            exit;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
        }

        self::render_standalone_page();
    }

    public static function render_embedded_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
        }

        $settings = self::get_all();
        $pages    = get_pages();
        ?>
        <div class="eop-settings-page eop-settings-page--embedded">
            <form method="post" action="options.php" class="eop-settings-form">
                <?php settings_fields( 'eop_settings_group' ); ?>
                <?php if ( isset( $_GET['settings-updated'] ) && 'true' === wp_unslash( $_GET['settings-updated'] ) ) : ?>
                    <div class="eop-settings-feedback eop-settings-feedback--success">
                        <?php esc_html_e( 'Configuracoes salvas com sucesso.', EOP_TEXT_DOMAIN ); ?>
                    </div>
                <?php endif; ?>
                <div class="eop-settings-sections">
                        <section class="eop-settings-card">
                            <h2><?php esc_html_e( 'Fluxo', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Defina como a equipe comercial trabalha hoje e como o cliente recebe a proposta.', EOP_TEXT_DOMAIN ); ?></p>
                            <div class="eop-settings-grid">
                                <div class="eop-settings-field">
                                    <label for="eop_flow_mode"><?php esc_html_e( 'Modo do fluxo', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_flow_mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[flow_mode]">
                                        <option value="proposal" <?php selected( $settings['flow_mode'], 'proposal' ); ?>><?php esc_html_e( 'Proposta publica', EOP_TEXT_DOMAIN ); ?></option>
                                        <option value="direct_order" <?php selected( $settings['flow_mode'], 'direct_order' ); ?>><?php esc_html_e( 'Pedido direto', EOP_TEXT_DOMAIN ); ?></option>
                                    </select>
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_discount_mode"><?php esc_html_e( 'Modo do desconto', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_discount_mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[discount_mode]">
                                        <option value="both" <?php selected( $settings['discount_mode'], 'both' ); ?>><?php esc_html_e( 'Porcentagem e valor fixo', EOP_TEXT_DOMAIN ); ?></option>
                                        <option value="percent" <?php selected( $settings['discount_mode'], 'percent' ); ?>><?php esc_html_e( 'Somente porcentagem (%)', EOP_TEXT_DOMAIN ); ?></option>
                                        <option value="fixed" <?php selected( $settings['discount_mode'], 'fixed' ); ?>><?php esc_html_e( 'Somente valor fixo (R$)', EOP_TEXT_DOMAIN ); ?></option>
                                    </select>
                                    <small class="eop-settings-help"><?php esc_html_e( 'Define se o campo de desconto aceita porcentagem, valor fixo ou ambos.', EOP_TEXT_DOMAIN ); ?></small>
                                </div>
                                <div class="eop-settings-field">
                                    <span><?php esc_html_e( 'Liberar pagamento apos confirmacao', EOP_TEXT_DOMAIN ); ?></span>
                                    <div class="eop-settings-switch-shell">
                                        <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_checkout_confirmation]" value="<?php echo esc_attr( $settings['enable_checkout_confirmation'] ); ?>" />
                                        <button
                                            type="button"
                                            class="eop-settings-switcher<?php echo 'yes' === $settings['enable_checkout_confirmation'] ? ' is-enabled' : ''; ?>"
                                            role="switch"
                                            aria-checked="<?php echo 'yes' === $settings['enable_checkout_confirmation'] ? 'true' : 'false'; ?>"
                                            data-target-name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_checkout_confirmation]"
                                            data-enabled-value="yes"
                                            data-disabled-value="no"
                                            aria-label="<?php esc_attr_e( 'Alternar pagamento apos confirmacao', EOP_TEXT_DOMAIN ); ?>"
                                        >
                                            <span class="eop-settings-switcher__label eop-settings-switcher__label--off">Off</span>
                                            <span class="eop-settings-switcher__thumb" aria-hidden="true"></span>
                                            <span class="eop-settings-switcher__label eop-settings-switcher__label--on">On</span>
                                        </button>
                                        <span class="eop-settings-switcher__status" aria-live="polite">
                                            <?php echo 'yes' === $settings['enable_checkout_confirmation'] ? esc_html__( 'Ativado', EOP_TEXT_DOMAIN ) : esc_html__( 'Desativado', EOP_TEXT_DOMAIN ); ?>
                                        </span>
                                    </div>
                                    <small class="eop-settings-help"><?php esc_html_e( 'Mostra o botao de pagar apenas depois que o cliente confirmar a proposta.', EOP_TEXT_DOMAIN ); ?></small>
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_proposal_page"><?php esc_html_e( 'Pagina da proposta', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_proposal_page" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_page_id]">
                                        <option value="0"><?php esc_html_e( 'Selecione uma pagina', EOP_TEXT_DOMAIN ); ?></option>
                                        <?php foreach ( $pages as $page ) : ?>
                                            <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( (int) $settings['proposal_page_id'], (int) $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </section>

                        <section class="eop-settings-card">
                            <h2><?php esc_html_e( 'Visual', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Personalize a experiencia da equipe e do cliente com a identidade da marca.', EOP_TEXT_DOMAIN ); ?></p>
                            <div class="eop-settings-grid">
                                <div class="eop-settings-field is-full">
                                    <label for="eop_logo"><?php esc_html_e( 'Logo', EOP_TEXT_DOMAIN ); ?></label>
                                    <div class="eop-settings-media" data-media-role="logo">
                                        <input id="eop_logo" type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[brand_logo_url]" value="<?php echo esc_attr( $settings['brand_logo_url'] ); ?>" />
                                        <div class="eop-settings-media__preview<?php echo $settings['brand_logo_url'] ? ' has-image' : ''; ?>" data-media-preview>
                                            <?php if ( $settings['brand_logo_url'] ) : ?>
                                                <img src="<?php echo esc_url( $settings['brand_logo_url'] ); ?>" alt="<?php esc_attr_e( 'Preview da logo', EOP_TEXT_DOMAIN ); ?>" />
                                            <?php else : ?>
                                                <span class="eop-settings-media__empty"><?php esc_html_e( 'Nenhum logo selecionado ainda.', EOP_TEXT_DOMAIN ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="eop-settings-media__details">
                                            <input type="url" class="eop-settings-media__url" value="<?php echo esc_attr( $settings['brand_logo_url'] ); ?>" readonly data-media-url />
                                            <div class="eop-settings-media__actions">
                                                <button type="button" class="button button-secondary eop-settings-media__select" data-media-select>
                                                    <?php echo $settings['brand_logo_url'] ? esc_html__( 'Trocar logo', EOP_TEXT_DOMAIN ) : esc_html__( 'Selecionar logo', EOP_TEXT_DOMAIN ); ?>
                                                </button>
                                                <button type="button" class="button button-link-delete eop-settings-media__remove<?php echo $settings['brand_logo_url'] ? '' : ' is-hidden'; ?>" data-media-remove>
                                                    <?php esc_html_e( 'Remover logo', EOP_TEXT_DOMAIN ); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <small class="eop-settings-help"><?php esc_html_e( 'Use a biblioteca de midia para selecionar a logo da proposta e do painel.', EOP_TEXT_DOMAIN ); ?></small>
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_primary_color"><?php esc_html_e( 'Cor principal', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_primary_color" class="eop-color-field" type="text" data-default-color="#00034b" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[primary_color]" value="<?php echo esc_attr( $settings['primary_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_surface_color"><?php esc_html_e( 'Cor de fundo dos cards', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_surface_color" class="eop-color-field" type="text" data-default-color="#ffffff" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[surface_color]" value="<?php echo esc_attr( $settings['surface_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_border_color"><?php esc_html_e( 'Cor da borda', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_border_color" class="eop-color-field" type="text" data-default-color="#dbe3f0" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[border_color]" value="<?php echo esc_attr( $settings['border_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_radius"><?php esc_html_e( 'Radius', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_radius" type="number" min="0" max="48" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[border_radius]" value="<?php echo esc_attr( $settings['border_radius'] ); ?>" />
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_font_family"><?php esc_html_e( 'Fonte', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_font_family" class="select_font eop-font-field" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[font_family]" value="<?php echo esc_attr( $settings['font_family'] ); ?>" />
                                    <small class="eop-settings-help"><?php esc_html_e( 'Selecione uma fonte do Google no mesmo padrao usado em outros plugins Aireset.', EOP_TEXT_DOMAIN ); ?></small>
                                </div>
                            </div>
                        </section>

                        <section class="eop-settings-card">
                            <h2><?php esc_html_e( 'Visual da pagina do cliente', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Deixe a proposta publica com uma identidade propria, sem depender do visual interno do vendedor.', EOP_TEXT_DOMAIN ); ?></p>
                            <div class="eop-settings-grid">
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_background_color"><?php esc_html_e( 'Fundo da pagina', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_background_color" class="eop-color-field" type="text" data-default-color="#f5f7ff" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_background_color]" value="<?php echo esc_attr( $settings['proposal_background_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_card_color"><?php esc_html_e( 'Fundo do card', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_card_color" class="eop-color-field" type="text" data-default-color="#ffffff" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_card_color]" value="<?php echo esc_attr( $settings['proposal_card_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_text_color"><?php esc_html_e( 'Texto principal', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_text_color" class="eop-color-field" type="text" data-default-color="#172033" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_text_color]" value="<?php echo esc_attr( $settings['proposal_text_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_muted_color"><?php esc_html_e( 'Texto auxiliar', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_muted_color" class="eop-color-field" type="text" data-default-color="#5b6474" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_muted_color]" value="<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>" />
                                </div>
                            </div>
                        </section>

                        <section class="eop-settings-card">
                            <h2><?php esc_html_e( 'Textos', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Refine a narrativa do painel de vendedor e da proposta publica.', EOP_TEXT_DOMAIN ); ?></p>
                            <div class="eop-settings-grid">
                                <div class="eop-settings-field">
                                    <label for="eop_panel_title"><?php esc_html_e( 'Titulo do painel', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_panel_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[panel_title]" value="<?php echo esc_attr( $settings['panel_title'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_title"><?php esc_html_e( 'Titulo da proposta', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_title]" value="<?php echo esc_attr( $settings['proposal_title'] ); ?>" />
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_panel_subtitle"><?php esc_html_e( 'Subtitulo do painel', EOP_TEXT_DOMAIN ); ?></label>
                                    <textarea id="eop_panel_subtitle" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[panel_subtitle]"><?php echo esc_textarea( $settings['panel_subtitle'] ); ?></textarea>
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_proposal_description"><?php esc_html_e( 'Descricao da proposta', EOP_TEXT_DOMAIN ); ?></label>
                                    <textarea id="eop_proposal_description" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_description]"><?php echo esc_textarea( $settings['proposal_description'] ); ?></textarea>
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_button_label"><?php esc_html_e( 'Texto do botao da proposta', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_button_label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_button_label]" value="<?php echo esc_attr( $settings['proposal_button_label'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_pay_button_label"><?php esc_html_e( 'Texto do botao de pagamento', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_pay_button_label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_pay_button_label]" value="<?php echo esc_attr( $settings['proposal_pay_button_label'] ); ?>" />
                                </div>
                            </div>
                        </section>
                </div>
                <div class="eop-settings-submit">
                    <?php submit_button( __( 'Salvar alteracoes', EOP_TEXT_DOMAIN ), 'primary large', 'submit', false ); ?>
                </div>
            </form>
        </div>
        <?php
    }

    public static function render_standalone_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
        }
        ?>
        <div class="wrap eop-settings-page">
            <div class="eop-settings-shell">
                <aside class="eop-settings-panel">
                    <h1><?php esc_html_e( 'Pedido Expresso', EOP_TEXT_DOMAIN ); ?></h1>
                    <p><?php esc_html_e( 'Controle o comportamento do fluxo comercial, a proposta publica e a identidade visual sem depender do tema ativo.', EOP_TEXT_DOMAIN ); ?></p>
                    <ul>
                        <li><?php esc_html_e( 'Escolha entre proposta publica ou pedido direto', EOP_TEXT_DOMAIN ); ?></li>
                        <li><?php esc_html_e( 'Ligue checkout somente quando fizer sentido', EOP_TEXT_DOMAIN ); ?></li>
                        <li><?php esc_html_e( 'Ajuste logo, cor, fonte, radius e textos', EOP_TEXT_DOMAIN ); ?></li>
                    </ul>
                </aside>

                <?php self::render_embedded_page(); ?>
            </div>
        </div>
        <?php
    }
}
