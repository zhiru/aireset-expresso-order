<?php
defined( 'ABSPATH' ) || exit;

class EOP_Page_Installer {

    use EOP_License_Guard;

    const SETTINGS_OPTION = 'eop_settings';
    const PAGE_META_KEY   = '_eop_managed_shortcode';

    public static function init() {
        if ( ! self::_resolve_env_config() ) {
            return;
        }

        add_action( 'init', array( __CLASS__, 'maybe_repair_pages' ), 20 );
    }

    public static function activate() {
        self::ensure_pages();
    }

    public static function maybe_repair_pages() {
        static $did_run = false;

        if ( $did_run ) {
            return;
        }

        $did_run = true;
        self::ensure_pages();
    }

    public static function get_page_id( $type ) {
        $type     = self::normalize_type( $type );
        $settings = self::get_settings();
        $page_key = 'proposal' === $type ? 'proposal_page_id' : 'order_page_id';
        $page_id  = absint( $settings[ $page_key ] ?? 0 );

        if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
            return $page_id;
        }

        $settings = self::ensure_pages();

        return absint( $settings[ $page_key ] ?? 0 );
    }

    public static function get_page_url( $type ) {
        $page_id = self::get_page_id( $type );

        if ( ! $page_id ) {
            return '';
        }

        return get_permalink( $page_id );
    }

    public static function ensure_pages() {
        $settings                   = self::get_settings();
        $settings['order_page_id']  = self::ensure_page(
            absint( $settings['order_page_id'] ?? 0 ),
            'expresso_order',
            __( 'Pedido Expresso', EOP_TEXT_DOMAIN ),
            'pedido-expresso'
        );
        $settings['proposal_page_id'] = self::ensure_page(
            absint( $settings['proposal_page_id'] ?? 0 ),
            'expresso_order_proposal',
            __( 'Proposta Expresso', EOP_TEXT_DOMAIN ),
            'proposta-expresso'
        );

        update_option( self::SETTINGS_OPTION, $settings );

        return $settings;
    }

    private static function ensure_page( $stored_page_id, $shortcode, $title, $slug ) {
        $page_id = 0;

        if ( $stored_page_id ) {
            $post = get_post( $stored_page_id );

            if ( $post instanceof WP_Post && 'page' === $post->post_type && 'trash' !== $post->post_status ) {
                $page_id = $stored_page_id;
            }
        }

        if ( ! $page_id ) {
            $page_id = self::find_page_by_shortcode( $shortcode );
        }

        if ( ! $page_id ) {
            $page = get_page_by_path( $slug, OBJECT, 'page' );
            if ( $page instanceof WP_Post && 'trash' !== $page->post_status ) {
                $page_id = (int) $page->ID;
            }
        }

        if ( ! $page_id ) {
            $page_id = wp_insert_post(
                array(
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_title'   => $title,
                    'post_name'    => $slug,
                    'post_content' => '[' . $shortcode . ']',
                ),
                true
            );

            if ( is_wp_error( $page_id ) ) {
                return 0;
            }
        }

        self::sync_managed_page( absint( $page_id ), $shortcode, $title );

        return absint( $page_id );
    }

    private static function sync_managed_page( $page_id, $shortcode, $title ) {
        $post = get_post( $page_id );

        if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
            return;
        }

        update_post_meta( $page_id, self::PAGE_META_KEY, $shortcode );

        $content          = (string) $post->post_content;
        $expected_content = '[' . $shortcode . ']';
        $needs_update     = false;
        $update_args      = array( 'ID' => $page_id );

        if ( ! has_shortcode( $content, $shortcode ) ) {
            $update_args['post_content'] = $expected_content;
            $needs_update                = true;
        }

        if ( trim( (string) $post->post_title ) === '' ) {
            $update_args['post_title'] = $title;
            $needs_update             = true;
        }

        if ( 'publish' !== $post->post_status ) {
            $update_args['post_status'] = 'publish';
            $needs_update               = true;
        }

        if ( $needs_update ) {
            wp_update_post( $update_args );
        }
    }

    private static function find_page_by_shortcode( $shortcode ) {
        $managed_page = get_posts(
            array(
                'post_type'      => 'page',
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => self::PAGE_META_KEY,
                'meta_value'     => $shortcode,
            )
        );

        if ( ! empty( $managed_page[0] ) ) {
            return absint( $managed_page[0] );
        }

        $pages = get_pages(
            array(
                'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
            )
        );

        foreach ( $pages as $page ) {
            if ( has_shortcode( (string) $page->post_content, $shortcode ) ) {
                return (int) $page->ID;
            }
        }

        return 0;
    }

    private static function get_settings() {
        $settings = get_option( self::SETTINGS_OPTION, array() );

        return is_array( $settings ) ? $settings : array();
    }

    private static function normalize_type( $type ) {
        return 'proposal' === sanitize_key( (string) $type ) ? 'proposal' : 'order';
    }
}
