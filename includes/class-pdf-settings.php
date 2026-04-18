<?php
defined( 'ABSPATH' ) || exit;

class EOP_PDF_Settings {

    const OPTION_KEY = 'eop_pdf_settings';

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    public static function register_settings() {
        register_setting(
            'eop_pdf_settings_group',
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
                'default'           => self::get_defaults(),
            )
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

    public static function get_defaults() {
        return array(
            'display_mode'              => 'new_tab',
            'paper_size'                => 'a4',
            'template_name'             => 'simple',
            'ink_saving_mode'           => 'no',
            'test_mode'                 => 'no',
            'font_subsetting'           => 'yes',
            'extended_currency_symbol'  => 'no',
            'shop_logo_url'             => '',
            'shop_logo_height'          => '3cm',
            'shop_name'                 => get_bloginfo( 'name' ),
            'shop_address_line_1'       => '',
            'shop_address_line_2'       => '',
            'shop_city'                 => '',
            'shop_state'                => '',
            'shop_postcode'             => '',
            'shop_country'              => 'BR',
            'shop_phone'                => '',
            'shop_email'                => get_bloginfo( 'admin_email' ),
            'shop_vat_number'           => '',
            'shop_chamber_of_commerce'  => '',
            'shop_extra_1'              => '',
            'shop_extra_2'              => '',
            'shop_extra_3'              => '',
            'shop_footer'               => __( 'Documento gerado pelo Aireset Expresso Order.', EOP_TEXT_DOMAIN ),
            'order_enabled'             => 'yes',
            'order_attach_email'        => 'no',
            'order_show_shipping'       => 'yes',
            'order_show_billing'        => 'no',
            'order_show_email'          => 'yes',
            'order_show_phone'          => 'yes',
            'order_show_notes'          => 'yes',
            'order_show_sku'            => 'yes',
            'order_show_quantity'       => 'yes',
            'order_show_unit_price'     => 'yes',
            'order_show_discount'       => 'yes',
            'order_show_discounted_unit_price' => 'yes',
            'order_show_line_total'     => 'yes',
            'order_product_label'       => __( 'Produto', EOP_TEXT_DOMAIN ),
            'order_quantity_label'      => __( 'Quantidade', EOP_TEXT_DOMAIN ),
            'order_unit_price_label'    => __( 'Valor unitario', EOP_TEXT_DOMAIN ),
            'order_discount_label'      => __( 'Desconto aplicado', EOP_TEXT_DOMAIN ),
            'order_discounted_unit_price_label' => __( 'Valor unitario com desconto', EOP_TEXT_DOMAIN ),
            'order_line_total_label'    => __( 'Total', EOP_TEXT_DOMAIN ),
            'order_show_total_subtotal' => 'yes',
            'order_show_total_shipping' => 'yes',
            'order_show_total_discount' => 'yes',
            'order_show_total_total'    => 'yes',
            'order_prefix'              => 'PED-',
            'order_suffix'              => '',
            'order_padding'             => '4',
            'order_next_number'         => '1',
            'order_reset_yearly'        => 'no',
            'order_myaccount_download'  => 'no',
            'order_mark_printed'        => 'no',
            'proposal_enabled'          => 'yes',
            'proposal_attach_email'     => 'yes',
            'proposal_show_shipping'    => 'yes',
            'proposal_show_billing'     => 'no',
            'proposal_show_email'       => 'yes',
            'proposal_show_phone'       => 'yes',
            'proposal_show_notes'       => 'yes',
            'proposal_show_sku'         => 'yes',
            'proposal_show_quantity'    => 'yes',
            'proposal_show_unit_price'  => 'yes',
            'proposal_show_discount'    => 'yes',
            'proposal_show_discounted_unit_price' => 'yes',
            'proposal_show_line_total'  => 'yes',
            'proposal_product_label'    => __( 'Produto', EOP_TEXT_DOMAIN ),
            'proposal_quantity_label'   => __( 'Quantidade', EOP_TEXT_DOMAIN ),
            'proposal_unit_price_label' => __( 'Valor unitario', EOP_TEXT_DOMAIN ),
            'proposal_discount_label'   => __( 'Desconto aplicado', EOP_TEXT_DOMAIN ),
            'proposal_discounted_unit_price_label' => __( 'Valor unitario com desconto', EOP_TEXT_DOMAIN ),
            'proposal_line_total_label' => __( 'Total', EOP_TEXT_DOMAIN ),
            'proposal_show_total_subtotal' => 'yes',
            'proposal_show_total_shipping' => 'yes',
            'proposal_show_total_discount' => 'yes',
            'proposal_show_total_total'    => 'yes',
            'proposal_prefix'           => 'PROP-',
            'proposal_suffix'           => '',
            'proposal_padding'          => '4',
            'proposal_next_number'      => '1',
            'proposal_reset_yearly'     => 'no',
            'proposal_public_pdf'       => 'yes',
            'proposal_mark_printed'     => 'no',
            'edoc_enabled'              => 'no',
            'edoc_format'               => 'ubl',
            'edoc_embed_pdf'            => 'yes',
            'edoc_preview_xml'          => 'yes',
            'edoc_logging'              => 'no',
            'edoc_supplier_scheme'      => '',
            'edoc_customer_scheme'      => '',
            'edoc_network_endpoint'     => '',
            'edoc_network_eas'          => '',
            'advanced_link_access'      => 'private_nonce',
            'advanced_pretty_links'     => 'no',
            'advanced_html_output'      => 'no',
            'advanced_debug'            => 'no',
            'advanced_order_note_logs'  => 'no',
            'advanced_auto_cleanup'     => 'yes',
            'advanced_danger_zone'      => 'no',
        );
    }

    public static function sanitize_settings( $input ) {
        $defaults = self::get_defaults();
        $input    = is_array( $input ) ? $input : array();

        return array(
            'display_mode'              => in_array( $input['display_mode'] ?? '', array( 'new_tab', 'download' ), true ) ? $input['display_mode'] : $defaults['display_mode'],
            'paper_size'                => in_array( $input['paper_size'] ?? '', array( 'a4', 'letter' ), true ) ? $input['paper_size'] : $defaults['paper_size'],
            'template_name'             => in_array( $input['template_name'] ?? '', array( 'simple', 'compact', 'minimal' ), true ) ? $input['template_name'] : $defaults['template_name'],
            'ink_saving_mode'           => self::sanitize_toggle( $input['ink_saving_mode'] ?? $defaults['ink_saving_mode'] ),
            'test_mode'                 => self::sanitize_toggle( $input['test_mode'] ?? $defaults['test_mode'] ),
            'font_subsetting'           => self::sanitize_toggle( $input['font_subsetting'] ?? $defaults['font_subsetting'] ),
            'extended_currency_symbol'  => self::sanitize_toggle( $input['extended_currency_symbol'] ?? $defaults['extended_currency_symbol'] ),
            'shop_logo_url'             => esc_url_raw( $input['shop_logo_url'] ?? '' ),
            'shop_logo_height'          => sanitize_text_field( $input['shop_logo_height'] ?? $defaults['shop_logo_height'] ),
            'shop_name'                 => sanitize_text_field( $input['shop_name'] ?? $defaults['shop_name'] ),
            'shop_address_line_1'       => sanitize_text_field( $input['shop_address_line_1'] ?? '' ),
            'shop_address_line_2'       => sanitize_text_field( $input['shop_address_line_2'] ?? '' ),
            'shop_city'                 => sanitize_text_field( $input['shop_city'] ?? '' ),
            'shop_state'                => sanitize_text_field( $input['shop_state'] ?? '' ),
            'shop_postcode'             => sanitize_text_field( $input['shop_postcode'] ?? '' ),
            'shop_country'              => sanitize_text_field( $input['shop_country'] ?? $defaults['shop_country'] ),
            'shop_phone'                => sanitize_text_field( $input['shop_phone'] ?? '' ),
            'shop_email'                => sanitize_email( $input['shop_email'] ?? '' ),
            'shop_vat_number'           => sanitize_text_field( $input['shop_vat_number'] ?? '' ),
            'shop_chamber_of_commerce'  => sanitize_text_field( $input['shop_chamber_of_commerce'] ?? '' ),
            'shop_extra_1'              => sanitize_text_field( $input['shop_extra_1'] ?? '' ),
            'shop_extra_2'              => sanitize_text_field( $input['shop_extra_2'] ?? '' ),
            'shop_extra_3'              => sanitize_text_field( $input['shop_extra_3'] ?? '' ),
            'shop_footer'               => sanitize_textarea_field( $input['shop_footer'] ?? '' ),
            'order_enabled'             => self::sanitize_toggle( $input['order_enabled'] ?? $defaults['order_enabled'] ),
            'order_attach_email'        => self::sanitize_toggle( $input['order_attach_email'] ?? $defaults['order_attach_email'] ),
            'order_show_shipping'       => self::sanitize_toggle( $input['order_show_shipping'] ?? $defaults['order_show_shipping'] ),
            'order_show_billing'        => self::sanitize_toggle( $input['order_show_billing'] ?? $defaults['order_show_billing'] ),
            'order_show_email'          => self::sanitize_toggle( $input['order_show_email'] ?? $defaults['order_show_email'] ),
            'order_show_phone'          => self::sanitize_toggle( $input['order_show_phone'] ?? $defaults['order_show_phone'] ),
            'order_show_notes'          => self::sanitize_toggle( $input['order_show_notes'] ?? $defaults['order_show_notes'] ),
            'order_show_sku'            => self::sanitize_toggle( $input['order_show_sku'] ?? $defaults['order_show_sku'] ),
            'order_show_quantity'       => self::sanitize_toggle( $input['order_show_quantity'] ?? $defaults['order_show_quantity'] ),
            'order_show_unit_price'     => self::sanitize_toggle( $input['order_show_unit_price'] ?? $defaults['order_show_unit_price'] ),
            'order_show_discount'       => self::sanitize_toggle( $input['order_show_discount'] ?? $defaults['order_show_discount'] ),
            'order_show_discounted_unit_price' => self::sanitize_toggle( $input['order_show_discounted_unit_price'] ?? $defaults['order_show_discounted_unit_price'] ),
            'order_show_line_total'     => self::sanitize_toggle( $input['order_show_line_total'] ?? $defaults['order_show_line_total'] ),
            'order_product_label'       => self::sanitize_label( $input['order_product_label'] ?? $defaults['order_product_label'], $defaults['order_product_label'] ),
            'order_quantity_label'      => self::sanitize_label( $input['order_quantity_label'] ?? $defaults['order_quantity_label'], $defaults['order_quantity_label'] ),
            'order_unit_price_label'    => self::sanitize_label( $input['order_unit_price_label'] ?? $defaults['order_unit_price_label'], $defaults['order_unit_price_label'] ),
            'order_discount_label'      => self::sanitize_label( $input['order_discount_label'] ?? $defaults['order_discount_label'], $defaults['order_discount_label'] ),
            'order_discounted_unit_price_label' => self::sanitize_label( $input['order_discounted_unit_price_label'] ?? $defaults['order_discounted_unit_price_label'], $defaults['order_discounted_unit_price_label'] ),
            'order_line_total_label'    => self::sanitize_label( $input['order_line_total_label'] ?? $defaults['order_line_total_label'], $defaults['order_line_total_label'] ),
            'order_show_total_subtotal' => self::sanitize_toggle( $input['order_show_total_subtotal'] ?? $defaults['order_show_total_subtotal'] ),
            'order_show_total_shipping' => self::sanitize_toggle( $input['order_show_total_shipping'] ?? $defaults['order_show_total_shipping'] ),
            'order_show_total_discount' => self::sanitize_toggle( $input['order_show_total_discount'] ?? $defaults['order_show_total_discount'] ),
            'order_show_total_total'    => self::sanitize_toggle( $input['order_show_total_total'] ?? $defaults['order_show_total_total'] ),
            'order_prefix'              => sanitize_text_field( $input['order_prefix'] ?? '' ),
            'order_suffix'              => sanitize_text_field( $input['order_suffix'] ?? '' ),
            'order_padding'             => (string) max( 0, min( 12, absint( $input['order_padding'] ?? $defaults['order_padding'] ) ) ),
            'order_next_number'         => (string) max( 1, absint( $input['order_next_number'] ?? $defaults['order_next_number'] ) ),
            'order_reset_yearly'        => self::sanitize_toggle( $input['order_reset_yearly'] ?? $defaults['order_reset_yearly'] ),
            'order_myaccount_download'  => self::sanitize_toggle( $input['order_myaccount_download'] ?? $defaults['order_myaccount_download'] ),
            'order_mark_printed'        => self::sanitize_toggle( $input['order_mark_printed'] ?? $defaults['order_mark_printed'] ),
            'proposal_enabled'          => self::sanitize_toggle( $input['proposal_enabled'] ?? $defaults['proposal_enabled'] ),
            'proposal_attach_email'     => self::sanitize_toggle( $input['proposal_attach_email'] ?? $defaults['proposal_attach_email'] ),
            'proposal_show_shipping'    => self::sanitize_toggle( $input['proposal_show_shipping'] ?? $defaults['proposal_show_shipping'] ),
            'proposal_show_billing'     => self::sanitize_toggle( $input['proposal_show_billing'] ?? $defaults['proposal_show_billing'] ),
            'proposal_show_email'       => self::sanitize_toggle( $input['proposal_show_email'] ?? $defaults['proposal_show_email'] ),
            'proposal_show_phone'       => self::sanitize_toggle( $input['proposal_show_phone'] ?? $defaults['proposal_show_phone'] ),
            'proposal_show_notes'       => self::sanitize_toggle( $input['proposal_show_notes'] ?? $defaults['proposal_show_notes'] ),
            'proposal_show_sku'         => self::sanitize_toggle( $input['proposal_show_sku'] ?? $defaults['proposal_show_sku'] ),
            'proposal_show_quantity'    => self::sanitize_toggle( $input['proposal_show_quantity'] ?? $defaults['proposal_show_quantity'] ),
            'proposal_show_unit_price'  => self::sanitize_toggle( $input['proposal_show_unit_price'] ?? $defaults['proposal_show_unit_price'] ),
            'proposal_show_discount'    => self::sanitize_toggle( $input['proposal_show_discount'] ?? $defaults['proposal_show_discount'] ),
            'proposal_show_discounted_unit_price' => self::sanitize_toggle( $input['proposal_show_discounted_unit_price'] ?? $defaults['proposal_show_discounted_unit_price'] ),
            'proposal_show_line_total'  => self::sanitize_toggle( $input['proposal_show_line_total'] ?? $defaults['proposal_show_line_total'] ),
            'proposal_product_label'    => self::sanitize_label( $input['proposal_product_label'] ?? $defaults['proposal_product_label'], $defaults['proposal_product_label'] ),
            'proposal_quantity_label'   => self::sanitize_label( $input['proposal_quantity_label'] ?? $defaults['proposal_quantity_label'], $defaults['proposal_quantity_label'] ),
            'proposal_unit_price_label' => self::sanitize_label( $input['proposal_unit_price_label'] ?? $defaults['proposal_unit_price_label'], $defaults['proposal_unit_price_label'] ),
            'proposal_discount_label'   => self::sanitize_label( $input['proposal_discount_label'] ?? $defaults['proposal_discount_label'], $defaults['proposal_discount_label'] ),
            'proposal_discounted_unit_price_label' => self::sanitize_label( $input['proposal_discounted_unit_price_label'] ?? $defaults['proposal_discounted_unit_price_label'], $defaults['proposal_discounted_unit_price_label'] ),
            'proposal_line_total_label' => self::sanitize_label( $input['proposal_line_total_label'] ?? $defaults['proposal_line_total_label'], $defaults['proposal_line_total_label'] ),
            'proposal_show_total_subtotal' => self::sanitize_toggle( $input['proposal_show_total_subtotal'] ?? $defaults['proposal_show_total_subtotal'] ),
            'proposal_show_total_shipping' => self::sanitize_toggle( $input['proposal_show_total_shipping'] ?? $defaults['proposal_show_total_shipping'] ),
            'proposal_show_total_discount' => self::sanitize_toggle( $input['proposal_show_total_discount'] ?? $defaults['proposal_show_total_discount'] ),
            'proposal_show_total_total'    => self::sanitize_toggle( $input['proposal_show_total_total'] ?? $defaults['proposal_show_total_total'] ),
            'proposal_prefix'           => sanitize_text_field( $input['proposal_prefix'] ?? '' ),
            'proposal_suffix'           => sanitize_text_field( $input['proposal_suffix'] ?? '' ),
            'proposal_padding'          => (string) max( 0, min( 12, absint( $input['proposal_padding'] ?? $defaults['proposal_padding'] ) ) ),
            'proposal_next_number'      => (string) max( 1, absint( $input['proposal_next_number'] ?? $defaults['proposal_next_number'] ) ),
            'proposal_reset_yearly'     => self::sanitize_toggle( $input['proposal_reset_yearly'] ?? $defaults['proposal_reset_yearly'] ),
            'proposal_public_pdf'       => self::sanitize_toggle( $input['proposal_public_pdf'] ?? $defaults['proposal_public_pdf'] ),
            'proposal_mark_printed'     => self::sanitize_toggle( $input['proposal_mark_printed'] ?? $defaults['proposal_mark_printed'] ),
            'edoc_enabled'              => self::sanitize_toggle( $input['edoc_enabled'] ?? $defaults['edoc_enabled'] ),
            'edoc_format'               => in_array( $input['edoc_format'] ?? '', array( 'ubl', 'cii', 'peppol' ), true ) ? $input['edoc_format'] : $defaults['edoc_format'],
            'edoc_embed_pdf'            => self::sanitize_toggle( $input['edoc_embed_pdf'] ?? $defaults['edoc_embed_pdf'] ),
            'edoc_preview_xml'          => self::sanitize_toggle( $input['edoc_preview_xml'] ?? $defaults['edoc_preview_xml'] ),
            'edoc_logging'              => self::sanitize_toggle( $input['edoc_logging'] ?? $defaults['edoc_logging'] ),
            'edoc_supplier_scheme'      => sanitize_text_field( $input['edoc_supplier_scheme'] ?? '' ),
            'edoc_customer_scheme'      => sanitize_text_field( $input['edoc_customer_scheme'] ?? '' ),
            'edoc_network_endpoint'     => sanitize_text_field( $input['edoc_network_endpoint'] ?? '' ),
            'edoc_network_eas'          => sanitize_text_field( $input['edoc_network_eas'] ?? '' ),
            'advanced_link_access'      => in_array( $input['advanced_link_access'] ?? '', array( 'private_nonce', 'public_token', 'order_owner' ), true ) ? $input['advanced_link_access'] : $defaults['advanced_link_access'],
            'advanced_pretty_links'     => self::sanitize_toggle( $input['advanced_pretty_links'] ?? $defaults['advanced_pretty_links'] ),
            'advanced_html_output'      => self::sanitize_toggle( $input['advanced_html_output'] ?? $defaults['advanced_html_output'] ),
            'advanced_debug'            => self::sanitize_toggle( $input['advanced_debug'] ?? $defaults['advanced_debug'] ),
            'advanced_order_note_logs'  => self::sanitize_toggle( $input['advanced_order_note_logs'] ?? $defaults['advanced_order_note_logs'] ),
            'advanced_auto_cleanup'     => self::sanitize_toggle( $input['advanced_auto_cleanup'] ?? $defaults['advanced_auto_cleanup'] ),
            'advanced_danger_zone'      => self::sanitize_toggle( $input['advanced_danger_zone'] ?? $defaults['advanced_danger_zone'] ),
        );
    }

    private static function sanitize_toggle( $value ) {
        return 'yes' === sanitize_key( (string) $value ) ? 'yes' : 'no';
    }

    private static function sanitize_label( $value, $default ) {
        $label = sanitize_text_field( (string) $value );

        if ( '' === $label ) {
            return sanitize_text_field( (string) $default );
        }

        return $label;
    }
}
