<?php
/**
 * Telemetry core for Aireset Expresso Order.
 *
 * @package Aireset\ExpressoOrder
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'EOP_Telemetry_Core' ) ) {
	class EOP_Telemetry_Core {

		/** @var string */
		private $server_host = '';

		/** @var string */
		private $product_id = '';

		/** @var string */
		private $product_base = '';

		/** @var string */
		private $version = '';

		/** @var string */
		private $domain = '';

		/** @var callable|null */
		private $encoder;

		/**
		 * @param array<string,mixed> $config Configuracao do modulo.
		 */
		public function __construct( array $config = array() ) {
			$this->server_host  = (string) ( $config['server_host'] ?? '' );
			$this->product_id   = (string) ( $config['product_id'] ?? '' );
			$this->product_base = (string) ( $config['product_base'] ?? '' );
			$this->version      = (string) ( $config['version'] ?? '' );
			$this->domain       = (string) ( $config['domain'] ?? '' );
			$this->encoder      = isset( $config['encoder'] ) && is_callable( $config['encoder'] ) ? $config['encoder'] : null;
		}

		/**
		 * Envia um evento ao servidor de telemetria com rate limit por evento.
		 *
		 * @param string               $event   Nome do evento.
		 * @param array<string,mixed>  $payload Dados adicionais.
		 * @param array<string,int>    $args    Rate limit em segundos.
		 * @return bool
		 */
		public function report_event( $event, array $payload = array(), array $args = array() ) {
			$event = sanitize_key( (string) $event );
			if ( '' === $event || '' === $this->server_host || '' === $this->product_id ) {
				return false;
			}

			$rate_limit = isset( $args['rate_limit'] ) ? max( 0, absint( $args['rate_limit'] ) ) : 12 * HOUR_IN_SECONDS;
			$cache_key  = 'eop_tm_' . hash( 'crc32b', $event . '|' . $this->domain );

			if ( $rate_limit > 0 && get_transient( $cache_key ) ) {
				return false;
			}

			$request_payload = array_merge(
				array(
					'event'        => $event,
					'domain'       => $this->domain,
					'product_id'   => $this->product_id,
					'product_base' => $this->product_base,
					'version'      => $this->version,
					'timestamp'    => time(),
					'wp_version'   => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '',
				),
				$payload
			);

			$body = wp_json_encode( $request_payload );
			if ( false === $body ) {
				return false;
			}

			if ( is_callable( $this->encoder ) ) {
				$body = call_user_func( $this->encoder, $body );
			}

			wp_remote_post(
				trailingslashit( rtrim( $this->server_host, '/' ) ) . 'product/status-report/' . rawurlencode( $this->product_id ),
				array(
					'body'      => $body,
					'timeout'   => 5,
					'blocking'  => false,
					'sslverify' => true,
				)
			);

			if ( $rate_limit > 0 ) {
				set_transient( $cache_key, 1, $rate_limit );
			}

			return true;
		}
	}
}
