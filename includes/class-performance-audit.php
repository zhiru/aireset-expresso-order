<?php
defined( 'ABSPATH' ) || exit;

class EOP_Performance_Audit {

	/**
	 * Build a request metrics payload for baseline auditing.
	 *
	 * @param string $scope Measurement scope.
	 * @param array  $extra Extra context.
	 * @return array<string,mixed>
	 */
	public static function get_request_metrics( $scope, $extra = array() ) {
		global $wpdb;

		$started_at = defined( 'EOP_REQUEST_START' ) ? (float) EOP_REQUEST_START : microtime( true );
		$metrics    = array(
			'scope'          => sanitize_key( (string) $scope ),
			'php_ms'         => round( max( 0, microtime( true ) - $started_at ) * 1000, 2 ),
			'memory_mb'      => round( memory_get_usage( true ) / 1048576, 2 ),
			'peak_memory_mb' => round( memory_get_peak_usage( true ) / 1048576, 2 ),
			'queries'        => function_exists( 'get_num_queries' ) ? (int) get_num_queries() : 0,
			'includes'       => count( get_included_files() ),
			'timestamp'      => function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' ),
		);

		if ( $wpdb instanceof wpdb ) {
			$metrics['db_host'] = isset( $wpdb->dbhost ) ? sanitize_text_field( (string) $wpdb->dbhost ) : '';
		}

		if ( ! empty( $extra ) ) {
			$metrics = array_merge( $metrics, $extra );
		}

		return $metrics;
	}

	/**
	 * Summarize enqueued assets for the current screen.
	 *
	 * @param array<int,string> $style_handles Styles to inspect.
	 * @param array<int,string> $script_handles Scripts to inspect.
	 * @return array<string,mixed>
	 */
	public static function summarize_assets( $style_handles, $script_handles ) {
		$styles  = self::collect_asset_entries( 'style', $style_handles );
		$scripts = self::collect_asset_entries( 'script', $script_handles );

		return array(
			'styles' => array(
				'count'       => count( $styles ),
				'total_bytes' => array_sum( wp_list_pluck( $styles, 'bytes' ) ),
				'items'       => $styles,
			),
			'scripts' => array(
				'count'       => count( $scripts ),
				'total_bytes' => array_sum( wp_list_pluck( $scripts, 'bytes' ) ),
				'items'       => $scripts,
			),
			'total_bytes' => array_sum( wp_list_pluck( $styles, 'bytes' ) ) + array_sum( wp_list_pluck( $scripts, 'bytes' ) ),
		);
	}

	/**
	 * @param string            $type Asset type.
	 * @param array<int,string> $handles Handles to inspect.
	 * @return array<int,array<string,mixed>>
	 */
	private static function collect_asset_entries( $type, $handles ) {
		global $wp_scripts, $wp_styles;

		$registry = 'script' === $type ? $wp_scripts : $wp_styles;
		/** @var array<string,object> $registered */
		$registered = ( is_object( $registry ) && isset( $registry->registered ) && is_array( $registry->registered ) )
			? $registry->registered
			: array();
		$items    = array();

		if ( ! is_array( $handles ) ) {
			return $items;
		}

		foreach ( $handles as $handle ) {
			$handle = (string) $handle;

			if ( '' === $handle || empty( $registered[ $handle ] ) ) {
				continue;
			}

			$asset = $registered[ $handle ];
			$src   = isset( $asset->src ) ? (string) $asset->src : '';
			$bytes = self::resolve_asset_size( $src );

			$items[] = array(
				'handle' => $handle,
				'src'    => $src,
				'bytes'  => $bytes,
				'local'  => $bytes > 0,
			);
		}

		return $items;
	}

	/**
	 * @param string $src Asset source URL.
	 * @return int
	 */
	private static function resolve_asset_size( $src ) {
		$path = self::resolve_local_path_from_src( $src );

		if ( '' === $path || ! file_exists( $path ) ) {
			return 0;
		}

		return (int) filesize( $path );
	}

	/**
	 * @param string $src Asset source URL.
	 * @return string
	 */
	private static function resolve_local_path_from_src( $src ) {
		if ( '' === $src ) {
			return '';
		}

		$normalized_src = strtok( $src, '?' );
		$parsed_src     = wp_parse_url( $normalized_src );

		if ( empty( $parsed_src['path'] ) ) {
			return '';
		}

		$path = wp_normalize_path( (string) $parsed_src['path'] );
		$home = wp_parse_url( home_url(), PHP_URL_PATH );

		if ( is_string( $home ) && '' !== $home && 0 === strpos( $path, wp_normalize_path( $home ) ) ) {
			$path = substr( $path, strlen( wp_normalize_path( $home ) ) );
		}

		$path = ltrim( $path, '/' );

		if ( '' === $path ) {
			return '';
		}

		return wp_normalize_path( trailingslashit( ABSPATH ) . $path );
	}
}
