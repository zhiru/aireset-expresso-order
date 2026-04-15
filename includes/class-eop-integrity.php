<?php
/**
 * Integrity core for Aireset Expresso Order.
 *
 * @package Aireset\ExpressoOrder
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'EOP_Integrity_Core' ) ) {
	class EOP_Integrity_Core {

		const NOTICE_TRANSIENT = 'eop_integrity_notice';

		/** @var string */
		private $plugin_dir = '';

		/** @var int */
		private $min_license_base_size = 8000;

		/** @var string */
		private $last_issue = '';

		/** @var bool */
		private static $notice_hooked = false;

		/**
		 * @param array<string,mixed> $config Configuracao do modulo.
		 */
		public function __construct( array $config = array() ) {
			$this->plugin_dir             = (string) ( $config['plugin_dir'] ?? '' );
			$this->min_license_base_size  = isset( $config['min_license_base_size'] ) ? absint( $config['min_license_base_size'] ) : 8000;
			self::boot_notice_renderer();
		}

		/**
		 * Verifica se o core de licenca esta intacto.
		 *
		 * @param object $license_core Instancia do core.
		 * @return bool
		 */
		public function verify_sdk_core( $license_core ) {
			if ( ! is_object( $license_core ) ) {
				$this->last_issue = 'missing_license_core';
				return false;
			}

			if ( ! method_exists( $license_core, '_check_wp_plugin' ) ) {
				$this->last_issue = 'missing_check_method';
				return false;
			}

			$ref = new ReflectionClass( $license_core );
			if ( ! $ref->hasMethod( '_check_wp_plugin' ) ) {
				$this->last_issue = 'missing_reflection_method';
				return false;
			}

			if ( 'EOP_License_Core' !== $ref->getMethod( '_check_wp_plugin' )->class ) {
				$this->last_issue = 'overridden_check_method';
				return false;
			}

			$this->last_issue = '';
			return true;
		}

		/**
		 * Verifica se os arquivos essenciais da distribuicao existem.
		 *
		 * @return bool
		 */
		public function verify_distribution() {
			if ( '' === $this->plugin_dir ) {
				$this->last_issue = 'missing_plugin_dir';
				return false;
			}

			$required_files = array(
				$this->plugin_dir . '/includes/class-eop-license-base.php',
				$this->plugin_dir . '/includes/class-eop-license-manager.php',
				$this->plugin_dir . '/includes/class-eop-telemetry.php',
				$this->plugin_dir . '/includes/class-eop-integrity.php',
			);

			foreach ( $required_files as $file ) {
				if ( ! file_exists( $file ) ) {
					$this->last_issue = 'file_missing';
					return false;
				}
			}

			$base_size = filesize( $required_files[0] );
			if ( false === $base_size || $base_size < $this->min_license_base_size ) {
				$this->last_issue = 'license_core_unexpected_size';
				return false;
			}

			$this->last_issue = '';
			return true;
		}

		/**
		 * Armazena um aviso para exibicao no admin.
		 *
		 * @param string $message Mensagem.
		 * @param string $level   Nivel do notice.
		 * @return void
		 */
		public static function push_admin_notice( $message, $level = 'error' ) {
			$payload = array(
				'message' => wp_strip_all_tags( (string) $message ),
				'level'   => sanitize_key( (string) $level ),
			);

			set_transient( self::NOTICE_TRANSIENT, $payload, 10 * MINUTE_IN_SECONDS );
		}

		/**
		 * @return string
		 */
		public function get_last_issue() {
			return $this->last_issue;
		}

		/**
		 * Registra o renderer de notices.
		 *
		 * @return void
		 */
		private static function boot_notice_renderer() {
			if ( self::$notice_hooked ) {
				return;
			}

			self::$notice_hooked = true;
			add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
		}

		/**
		 * Exibe notice pendente no admin.
		 *
		 * @return void
		 */
		public static function render_admin_notice() {
			$notice = get_transient( self::NOTICE_TRANSIENT );
			if ( empty( $notice['message'] ) ) {
				return;
			}

			$level = ! empty( $notice['level'] ) ? sanitize_key( $notice['level'] ) : 'error';
			delete_transient( self::NOTICE_TRANSIENT );

			printf(
				'<div class="notice notice-%1$s"><p>%2$s</p></div>',
				esc_attr( $level ),
				esc_html( $notice['message'] )
			);
		}
	}
}
