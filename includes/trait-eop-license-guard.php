<?php
/**
 * Integrity and license guard for Aireset Expresso Order.
 *
 * @package Aireset\ExpressoOrder
 */

defined( 'ABSPATH' ) || exit;

trait EOP_License_Guard {

	/**
	 * Verifica se o ambiente pode carregar os modulos protegidos.
	 *
	 * @return bool
	 */
	private static function _resolve_env_config() {
		if ( ! class_exists( 'EOP_License_Core' ) ) {
			self::_push_integrity_notice( __( 'Aireset Expresso Order: o core de licenca nao foi carregado corretamente.', 'aireset-expresso-order' ) );
			return false;
		}

		$core = EOP_License_Core::get_instance();
		if ( empty( $core ) ) {
			return false;
		}

		$integrity = method_exists( $core, 'get_integrity_core' ) ? $core->get_integrity_core() : null;
		if ( $integrity instanceof EOP_Integrity_Core && ! $integrity->verify_distribution() ) {
			self::_flag_env_status( 'distribution_' . $integrity->get_last_issue() );
			self::_push_integrity_notice( __( 'Aireset Expresso Order: arquivos essenciais do sistema de licenca/integridade estao ausentes ou incompletos.', 'aireset-expresso-order' ) );
			return false;
		}

		if ( ! $core->verify_class_integrity() ) {
			self::_flag_env_status( 'integrity_fail' );
			self::_push_integrity_notice( __( 'Aireset Expresso Order: a verificacao de integridade do core de licenca falhou.', 'aireset-expresso-order' ) );
			return false;
		}

		return $core->quick_validate( static::class );
	}

	/**
	 * Reporta estado do ambiente usando o modulo de telemetria.
	 *
	 * @param string $code Codigo do evento.
	 * @return void
	 */
	private static function _flag_env_status( $code ) {
		if ( ! class_exists( 'EOP_License_Core' ) ) {
			return;
		}

		$core = EOP_License_Core::get_instance();
		if ( empty( $core ) || ! method_exists( $core, 'get_telemetry_core' ) ) {
			return;
		}

		$telemetry = $core->get_telemetry_core();
		if ( ! $telemetry instanceof EOP_Telemetry_Core ) {
			return;
		}

		$telemetry->report_event(
			'environment_status',
			array(
				'code'   => sanitize_key( (string) $code ),
				'caller' => static::class,
			),
			array(
				'rate_limit' => 24 * HOUR_IN_SECONDS,
			)
		);
	}

	/**
	 * Verifica se os modulos essenciais existem na distribuicao.
	 *
	 * @return bool
	 */
	private static function _prefetch_module_state() {
		if ( ! class_exists( 'EOP_License_Core' ) ) {
			return false;
		}

		$core = EOP_License_Core::get_instance();
		if ( empty( $core ) || ! method_exists( $core, 'get_integrity_core' ) ) {
			return false;
		}

		$integrity = $core->get_integrity_core();
		if ( ! $integrity instanceof EOP_Integrity_Core ) {
			return false;
		}

		$is_valid = $integrity->verify_distribution();
		if ( ! $is_valid ) {
			self::_flag_env_status( 'distribution_' . $integrity->get_last_issue() );
		}

		return $is_valid;
	}

	/**
	 * Confirma se a sessao atual esta autorizada a usar o plugin.
	 *
	 * @return bool
	 */
	private static function _validate_session_tokens() {
		if ( ! class_exists( 'EOP_License_Manager' ) ) {
			self::_flag_env_status( 'manager_missing' );
			return false;
		}

		return EOP_License_Manager::is_valid();
	}

	/**
	 * Armazena um aviso para exibicao no admin.
	 *
	 * @param string $message Mensagem do aviso.
	 * @return void
	 */
	private static function _push_integrity_notice( $message ) {
		if ( class_exists( 'EOP_Integrity_Core' ) ) {
			EOP_Integrity_Core::push_admin_notice( $message, 'error' );
		}
	}
}
