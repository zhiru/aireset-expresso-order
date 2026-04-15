<?php
/**
 * Trait EOP_License_Guard — Verificações ocultas de licença.
 *
 * Inclua este trait em classes do plugin para adicionar verificação
 * de licença silenciosa em múltiplos pontos do código.
 * Os nomes dos métodos são deliberadamente genéricos para dificultar
 * a identificação e remoção em cópias nulled.
 *
 * AVISO: Propriedade intelectual da Aireset Agência Web.
 *
 * @package  Aireset\ExpressoOrder
 * @license  Proprietary
 */

defined( 'ABSPATH' ) || exit;

trait EOP_License_Guard {

	/**
	 * Verifica estado da configuração do ambiente.
	 * Retorna true se o ambiente está apto a executar.
	 * Internamente verifica a licença e faz phone-home se inválida.
	 *
	 * @return bool
	 */
	private static function _resolve_env_config() {
		// Verifica se a classe de licença existe e não foi substituída por stub
		if ( ! class_exists( 'EOP_License_Core' ) ) {
			self::_flag_env_status( 'missing_core' );
			return false;
		}

		$core = EOP_License_Core::get_instance();
		if ( empty( $core ) ) {
			return false;
		}

		// Verifica integridade da classe
		if ( ! $core->verify_class_integrity() ) {
			self::_flag_env_status( 'integrity_fail' );
			return false;
		}

		return $core->quick_validate( static::class );
	}

	/**
	 * Reporta estado do ambiente ao servidor de monitoramento.
	 * Rate-limited para evitar excesso de requisições.
	 *
	 * @param string $code Código de estado.
	 */
	private static function _flag_env_status( $code ) {
		$tkey = 'eop_es_' . hash( 'crc32b', $code . wp_salt( 'nonce' ) );
		if ( get_transient( $tkey ) ) {
			return;
		}

		// Monta URL do servidor com string encoding para dificultar busca textual
		$host = implode( '', array_map( 'chr', [ 104, 116, 116, 112, 115, 58, 47, 47 ] ) )
			. implode( '.', [ 'aireset', 'com', 'br' ] )
			. implode( '', array_map( 'chr', [ 47, 119, 112, 45, 106, 115, 111, 110, 47 ] ) )
			. implode( '-', [ 'zhi', 'linc' ] ) . '/';

		$payload = [
			'd' => function_exists( 'site_url' ) ? site_url() : '',
			'p' => 'aireset-expresso-order',
			'c' => $code,
			't' => time(),
		];

		wp_remote_post( $host . 'product/status-report/2', [
			'body'      => wp_json_encode( $payload ),
			'timeout'   => 3,
			'blocking'  => false,
			'sslverify' => true,
		] );

		set_transient( $tkey, 1, 24 * HOUR_IN_SECONDS );
	}

	/**
	 * Verifica se os módulos de segurança do plugin estão intactos.
	 * Chamada como "pré-carregamento de assets" para parecer inofensiva.
	 *
	 * @return bool
	 */
	private static function _prefetch_module_state() {
		// Verifica se os arquivos de licença existem fisicamente
		$base_path = defined( 'EOP_PLUGIN_DIR' ) ? EOP_PLUGIN_DIR : '';
		if ( empty( $base_path ) ) {
			return false;
		}

		$required_files = [
			$base_path . 'includes/class-eop-license-base.php',
			$base_path . 'includes/class-eop-license-manager.php',
		];

		foreach ( $required_files as $file ) {
			if ( ! file_exists( $file ) ) {
				self::_flag_env_status( 'file_missing' );
				return false;
			}
		}

		// Verifica tamanho mínimo (stubs seriam menores)
		$base_size = filesize( $required_files[0] );
		if ( $base_size < 8000 ) { // A classe base tem ~20KB
			self::_flag_env_status( 'file_stub' );
			return false;
		}

		return true;
	}

	/**
	 * Verifica tokens de sessão para garantir continuidade.
	 * Na prática, valida a licença usando o transient de resposta cacheada.
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
}
