<?php
defined( 'ABSPATH' ) || exit;

class EOP_Settings_Portability {

	const EXPORT_ACTION      = 'eop_export_plugin_settings';
	const IMPORT_ACTION      = 'eop_import_plugin_settings';
	const IMPORT_DOCS_ACTION = 'eop_import_signature_documents';
	const NOTICE_TRANSIENT   = 'eop_portability_notice_';

	public static function init() {
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_' . self::IMPORT_DOCS_ACTION, array( __CLASS__, 'handle_documents_import' ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
		}

		$notice            = self::consume_notice();
		$settings          = class_exists( 'EOP_Settings' ) ? EOP_Settings::get_all() : array();
		$signature_docs    = class_exists( 'EOP_Settings' ) ? EOP_Settings::get_post_confirmation_signature_documents( $settings ) : array();
		$has_contract_docs = ! empty( $signature_docs );
		?>
		<div class="eop-settings-page eop-settings-page--embedded eop-portability-page">
			<?php if ( ! empty( $notice['message'] ) ) : ?>
				<div class="eop-settings-feedback eop-settings-feedback--<?php echo 'error' === ( $notice['type'] ?? '' ) ? 'error' : 'success'; ?>">
					<?php echo esc_html( $notice['message'] ); ?>
				</div>
			<?php endif; ?>

			<div class="eop-settings-sections">
				<section class="eop-settings-card">
					<h2><?php esc_html_e( 'Exportar configuracoes', EOP_TEXT_DOMAIN ); ?></h2>
					<p><?php esc_html_e( 'Baixe um pacote JSON com as configuracoes do plugin, textos, visual do PDF e documentos configurados no fluxo complementar.', EOP_TEXT_DOMAIN ); ?></p>
					<div class="eop-settings-grid">
						<div class="eop-settings-field is-full">
							<label><?php esc_html_e( 'Resumo do pacote', EOP_TEXT_DOMAIN ); ?></label>
							<ul class="eop-portability-summary-list">
								<li><?php echo esc_html( sprintf( __( 'Configuracoes gerais: %d chave(s)', EOP_TEXT_DOMAIN ), is_array( $settings ) ? count( $settings ) : 0 ) ); ?></li>
								<li><?php echo esc_html( sprintf( __( 'Documentos do contrato configurados: %d', EOP_TEXT_DOMAIN ), count( $signature_docs ) ) ); ?></li>
								<li><?php echo esc_html( $has_contract_docs ? __( 'Os documentos anexados entram no pacote quando estiverem vinculados nas configuracoes.', EOP_TEXT_DOMAIN ) : __( 'Nenhum documento anexado nas configuracoes neste momento.', EOP_TEXT_DOMAIN ) ); ?></li>
							</ul>
						</div>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eop-settings-form">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT_ACTION ); ?>" />
						<?php wp_nonce_field( self::EXPORT_ACTION ); ?>
						<?php submit_button( __( 'Baixar pacote JSON', EOP_TEXT_DOMAIN ), 'primary', 'submit', false ); ?>
					</form>
				</section>

				<section class="eop-settings-card">
					<h2><?php esc_html_e( 'Importar pacote JSON', EOP_TEXT_DOMAIN ); ?></h2>
					<p><?php esc_html_e( 'Restaure todas as configuracoes do plugin a partir de um pacote exportado, incluindo textos, identidade visual, PDF e documentos do fluxo.', EOP_TEXT_DOMAIN ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="eop-settings-form">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_ACTION ); ?>" />
						<?php wp_nonce_field( self::IMPORT_ACTION ); ?>
						<div class="eop-settings-grid">
							<div class="eop-settings-field is-full">
								<label for="eop_portability_package_file"><?php esc_html_e( 'Arquivo JSON', EOP_TEXT_DOMAIN ); ?></label>
								<input id="eop_portability_package_file" type="file" name="eop_portability_package_file" accept="application/json,.json" />
								<small class="eop-settings-help"><?php esc_html_e( 'Prefira o arquivo exportado pelo proprio plugin para preservar anexos e mapeamentos.', EOP_TEXT_DOMAIN ); ?></small>
							</div>
							<div class="eop-settings-field is-full">
								<label for="eop_portability_package_json"><?php esc_html_e( 'Ou cole o JSON manualmente', EOP_TEXT_DOMAIN ); ?></label>
								<textarea id="eop_portability_package_json" name="eop_portability_package_json" rows="10" placeholder="{ ... }"></textarea>
							</div>
						</div>
						<?php submit_button( __( 'Importar pacote', EOP_TEXT_DOMAIN ), 'secondary', 'submit', false ); ?>
					</form>
				</section>

				<section class="eop-settings-card">
					<h2><?php esc_html_e( 'Importar documentos do fluxo', EOP_TEXT_DOMAIN ); ?></h2>
					<p><?php esc_html_e( 'Envie PDF, DOC, DOCX ou TXT para criar documentos no fluxo complementar. Quando houver texto extraivel, o conteudo entra direto como texto editavel.', EOP_TEXT_DOMAIN ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="eop-settings-form">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_DOCS_ACTION ); ?>" />
						<?php wp_nonce_field( self::IMPORT_DOCS_ACTION ); ?>
						<div class="eop-settings-grid">
							<div class="eop-settings-field is-full">
								<label for="eop_portability_documents"><?php esc_html_e( 'Arquivos para importar', EOP_TEXT_DOMAIN ); ?></label>
								<input id="eop_portability_documents" type="file" name="eop_portability_documents[]" accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,.pdf,.doc,.docx,.txt" multiple />
								<small class="eop-settings-help"><?php esc_html_e( 'PDF entra como arquivo anexado. DOC, DOCX e TXT tentam virar texto editavel automaticamente.', EOP_TEXT_DOMAIN ); ?></small>
							</div>
						</div>
						<?php submit_button( __( 'Importar documentos', EOP_TEXT_DOMAIN ), 'secondary', 'submit', false ); ?>
					</form>
				</section>
			</div>
		</div>
		<?php
	}

	public static function handle_export() {
		self::ensure_admin_request( self::EXPORT_ACTION );

		$payload  = self::build_export_payload();
		$filename = 'aireset-expresso-order-config-' . gmdate( 'Y-m-d-H-i-s' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_bloginfo( 'charset' ) );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public static function handle_import() {
		self::ensure_admin_request( self::IMPORT_ACTION );

		$json = self::get_import_json_payload();

		if ( is_wp_error( $json ) ) {
			self::redirect_with_notice( 'error', $json->get_error_message() );
		}

		$payload = json_decode( $json, true );

		if ( ! is_array( $payload ) ) {
			self::redirect_with_notice( 'error', __( 'O pacote informado nao possui um JSON valido.', EOP_TEXT_DOMAIN ) );
		}

		if ( empty( $payload['plugin'] ) || 'aireset-expresso-order' !== $payload['plugin'] ) {
			self::redirect_with_notice( 'error', __( 'O pacote nao pertence ao Aireset Expresso Order.', EOP_TEXT_DOMAIN ) );
		}

		$settings     = is_array( $payload['settings'] ?? null ) ? $payload['settings'] : array();
		$pdf_settings = is_array( $payload['pdf_settings'] ?? null ) ? $payload['pdf_settings'] : array();
		$media_map    = self::import_media_payload( is_array( $payload['media'] ?? null ) ? $payload['media'] : array() );

		$settings = self::apply_imported_media_to_settings( $settings, $media_map );
		$pdf_settings = self::apply_imported_media_to_pdf_settings( $pdf_settings, $media_map );

		if ( class_exists( 'EOP_Settings' ) ) {
			$settings = EOP_Settings::sanitize_settings( $settings );
			update_option( EOP_Settings::OPTION_KEY, $settings );
		}

		if ( class_exists( 'EOP_PDF_Settings' ) ) {
			$pdf_settings = EOP_PDF_Settings::sanitize_settings( $pdf_settings );
			update_option( EOP_PDF_Settings::OPTION_KEY, $pdf_settings );
		}

		self::redirect_with_notice( 'success', __( 'Pacote importado com sucesso.', EOP_TEXT_DOMAIN ) );
	}

	public static function handle_documents_import() {
		self::ensure_admin_request( self::IMPORT_DOCS_ACTION );

		$files = self::normalize_uploaded_files( $_FILES['eop_portability_documents'] ?? array() );

		if ( empty( $files ) ) {
			self::redirect_with_notice( 'error', __( 'Selecione ao menos um arquivo para importar.', EOP_TEXT_DOMAIN ) );
		}

		if ( ! class_exists( 'EOP_Settings' ) ) {
			self::redirect_with_notice( 'error', __( 'As configuracoes do plugin nao estao disponiveis neste carregamento.', EOP_TEXT_DOMAIN ) );
		}

		$settings  = EOP_Settings::get_all();
		$documents = EOP_Settings::get_post_confirmation_signature_documents( $settings );
		$imported  = 0;

		foreach ( $files as $file ) {
			if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $file['tmp_name'] ) ) {
				continue;
			}

			$filename   = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
			$title      = sanitize_text_field( preg_replace( '/\.[^.]+$/', '', $filename ) );
			$mime_type  = self::detect_file_mime_type( $file );
			$extension  = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			$body       = self::extract_text_from_file( (string) $file['tmp_name'], $mime_type, $extension );

			if ( '' !== $body ) {
				$documents[] = self::build_editor_document_record( $title, $body, count( $documents ) + 1 );
				++$imported;
				continue;
			}

			$attachment_id = self::store_uploaded_file_as_attachment( $file, $title );

			if ( is_wp_error( $attachment_id ) ) {
				continue;
			}

			$documents[] = self::build_attachment_document_record( $title, (int) $attachment_id, count( $documents ) + 1 );
			++$imported;
		}

		if ( 0 === $imported ) {
			self::redirect_with_notice( 'error', __( 'Nenhum documento valido foi importado.', EOP_TEXT_DOMAIN ) );
		}

		$settings['post_confirmation_signature_documents'] = $documents;
		$settings['post_confirmation_contract_body']       = '';
		update_option( EOP_Settings::OPTION_KEY, EOP_Settings::sanitize_settings( $settings ) );

		self::redirect_with_notice(
			'success',
			sprintf(
				/* translators: %d: quantity of imported documents. */
				__( '%d documento(s) importado(s) para o fluxo complementar.', EOP_TEXT_DOMAIN ),
				$imported
			)
		);
	}

	private static function build_export_payload() {
		$settings     = class_exists( 'EOP_Settings' ) ? EOP_Settings::get_all() : array();
		$pdf_settings = class_exists( 'EOP_PDF_Settings' ) ? EOP_PDF_Settings::get_all() : array();

		return array(
			'schema_version' => 1,
			'plugin'         => 'aireset-expresso-order',
			'plugin_version' => defined( 'EOP_VERSION' ) ? EOP_VERSION : '',
			'exported_at'    => gmdate( 'c' ),
			'site_url'       => home_url( '/' ),
			'settings'       => $settings,
			'pdf_settings'   => $pdf_settings,
			'media'          => self::build_media_export_payload( $settings, $pdf_settings ),
		);
	}

	private static function build_media_export_payload( $settings, $pdf_settings ) {
		$media = array();

		$brand_logo = self::export_local_media_from_url( (string) ( $settings['brand_logo_url'] ?? '' ), 'brand_logo' );
		if ( ! empty( $brand_logo ) ) {
			$media[] = $brand_logo;
		}

		$shop_logo = self::export_local_media_from_url( (string) ( $pdf_settings['shop_logo_url'] ?? '' ), 'shop_logo' );
		if ( ! empty( $shop_logo ) ) {
			$media[] = $shop_logo;
		}

		$signature_documents = class_exists( 'EOP_Settings' ) ? EOP_Settings::get_post_confirmation_signature_documents( $settings ) : array();

		foreach ( $signature_documents as $document ) {
			if ( 'attachment' !== ( $document['source_type'] ?? 'editor' ) ) {
				continue;
			}

			$attachment_id = absint( $document['attachment_id'] ?? 0 );
			if ( $attachment_id <= 0 ) {
				continue;
			}

			$item = self::export_attachment_by_id( $attachment_id );
			if ( empty( $item ) ) {
				continue;
			}

			$item['role']         = 'signature_document';
			$item['document_key'] = sanitize_title( (string) ( $document['key'] ?? '' ) );
			$media[]              = $item;
		}

		return $media;
	}

	private static function export_local_media_from_url( $url, $role ) {
		$url = esc_url_raw( $url );

		if ( '' === $url || ! function_exists( 'attachment_url_to_postid' ) ) {
			return array();
		}

		$attachment_id = attachment_url_to_postid( $url );
		if ( $attachment_id <= 0 ) {
			return array();
		}

		$item = self::export_attachment_by_id( $attachment_id );
		if ( empty( $item ) ) {
			return array();
		}

		$item['role'] = $role;

		return $item;
	}

	private static function export_attachment_by_id( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );

		if ( empty( $file_path ) || ! is_readable( $file_path ) ) {
			return array();
		}

		$contents = file_get_contents( $file_path );
		if ( false === $contents ) {
			return array();
		}

		return array(
			'attachment_id' => absint( $attachment_id ),
			'title'         => sanitize_text_field( get_the_title( $attachment_id ) ),
			'filename'      => wp_basename( $file_path ),
			'mime_type'     => (string) get_post_mime_type( $attachment_id ),
			'content_base64'=> base64_encode( $contents ),
		);
	}

	private static function get_import_json_payload() {
		if ( ! empty( $_FILES['eop_portability_package_file']['tmp_name'] ) ) {
			$contents = file_get_contents( (string) $_FILES['eop_portability_package_file']['tmp_name'] );

			if ( false === $contents || '' === $contents ) {
				return new WP_Error( 'package_read_failed', __( 'Nao foi possivel ler o arquivo JSON enviado.', EOP_TEXT_DOMAIN ) );
			}

			return (string) $contents;
		}

		$raw = isset( $_POST['eop_portability_package_json'] ) ? wp_unslash( $_POST['eop_portability_package_json'] ) : '';
		$raw = trim( (string) $raw );

		if ( '' === $raw ) {
			return new WP_Error( 'package_missing', __( 'Envie um arquivo JSON ou cole o conteudo do pacote.', EOP_TEXT_DOMAIN ) );
		}

		return $raw;
	}

	private static function import_media_payload( $media_items ) {
		$brand_logo_map         = 0;
		$shop_logo_map          = 0;
		$signature_document_map = array();

		if ( ! is_array( $media_items ) ) {
			return array(
				'brand_logo'         => $brand_logo_map,
				'shop_logo'          => $shop_logo_map,
				'signature_document' => $signature_document_map,
			);
		}

		foreach ( $media_items as $item ) {
			if ( ! is_array( $item ) || empty( $item['content_base64'] ) || empty( $item['filename'] ) ) {
				continue;
			}

			$contents = base64_decode( (string) $item['content_base64'], true );
			if ( false === $contents || '' === $contents ) {
				continue;
			}

			$attachment_id = self::store_bits_as_attachment(
				(string) $item['filename'],
				$contents,
				(string) ( $item['mime_type'] ?? '' ),
				(string) ( $item['title'] ?? '' )
			);

			if ( is_wp_error( $attachment_id ) ) {
				continue;
			}

			$role = sanitize_key( (string) ( $item['role'] ?? '' ) );

			if ( 'brand_logo' === $role ) {
				$brand_logo_map = (int) $attachment_id;
				continue;
			}

			if ( 'shop_logo' === $role ) {
				$shop_logo_map = (int) $attachment_id;
				continue;
			}

			if ( 'signature_document' === $role ) {
				$document_key = sanitize_title( (string) ( $item['document_key'] ?? '' ) );
				if ( '' !== $document_key ) {
					$signature_document_map[ $document_key ] = (int) $attachment_id;
				}
			}
		}

		return array(
			'brand_logo'         => $brand_logo_map,
			'shop_logo'          => $shop_logo_map,
			'signature_document' => $signature_document_map,
		);
	}

	private static function apply_imported_media_to_settings( $settings, $media_map ) {
		$settings = is_array( $settings ) ? $settings : array();

		if ( ! empty( $media_map['brand_logo'] ) ) {
			$settings['brand_logo_url'] = wp_get_attachment_url( (int) $media_map['brand_logo'] );
		}

		if ( ! empty( $settings['post_confirmation_signature_documents'] ) && is_array( $settings['post_confirmation_signature_documents'] ) ) {
			foreach ( $settings['post_confirmation_signature_documents'] as $index => $document ) {
				if ( ! is_array( $document ) ) {
					continue;
				}

				$document_key = sanitize_title( (string) ( $document['key'] ?? '' ) );
				if ( 'attachment' !== ( $document['source_type'] ?? 'editor' ) ) {
					continue;
				}

				if ( '' !== $document_key && ! empty( $media_map['signature_document'][ $document_key ] ) ) {
					$settings['post_confirmation_signature_documents'][ $index ]['attachment_id'] = (int) $media_map['signature_document'][ $document_key ];
					continue;
				}

				$settings['post_confirmation_signature_documents'][ $index ]['attachment_id'] = 0;
			}
		}

		return $settings;
	}

	private static function apply_imported_media_to_pdf_settings( $settings, $media_map ) {
		$settings = is_array( $settings ) ? $settings : array();

		if ( ! empty( $media_map['shop_logo'] ) ) {
			$settings['shop_logo_url'] = wp_get_attachment_url( (int) $media_map['shop_logo'] );
		}

		return $settings;
	}

	private static function build_editor_document_record( $title, $body, $index ) {
		$title = '' !== $title ? $title : sprintf( __( 'Documento %d', EOP_TEXT_DOMAIN ), $index );

		return array(
			'key'           => sanitize_title( $title ) ?: 'documento-' . $index,
			'title'         => $title,
			'description'   => __( 'Importado automaticamente na area de exportar e importar.', EOP_TEXT_DOMAIN ),
			'source_type'   => 'editor',
			'body'          => wpautop( esc_html( $body ) ),
			'attachment_id' => 0,
			'button_label'  => __( 'Baixar PDF', EOP_TEXT_DOMAIN ),
			'view_label'    => __( 'Visualizar PDF', EOP_TEXT_DOMAIN ),
		);
	}

	private static function build_attachment_document_record( $title, $attachment_id, $index ) {
		$title = '' !== $title ? $title : sprintf( __( 'Documento %d', EOP_TEXT_DOMAIN ), $index );

		return array(
			'key'           => sanitize_title( $title ) ?: 'documento-' . $index,
			'title'         => $title,
			'description'   => __( 'Importado automaticamente na area de exportar e importar.', EOP_TEXT_DOMAIN ),
			'source_type'   => 'attachment',
			'body'          => '',
			'attachment_id' => absint( $attachment_id ),
			'button_label'  => __( 'Baixar PDF', EOP_TEXT_DOMAIN ),
			'view_label'    => __( 'Visualizar PDF', EOP_TEXT_DOMAIN ),
		);
	}

	private static function normalize_uploaded_files( $files ) {
		$normalized = array();

		if ( empty( $files ) || ! is_array( $files ) ) {
			return $normalized;
		}

		if ( ! is_array( $files['name'] ?? null ) ) {
			return array( $files );
		}

		foreach ( array_keys( $files['name'] ) as $index ) {
			$normalized[] = array(
				'name'     => $files['name'][ $index ] ?? '',
				'type'     => $files['type'][ $index ] ?? '',
				'tmp_name' => $files['tmp_name'][ $index ] ?? '',
				'error'    => $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
				'size'     => $files['size'][ $index ] ?? 0,
			);
		}

		return $normalized;
	}

	private static function detect_file_mime_type( $file ) {
		$filename  = (string) ( $file['name'] ?? '' );
		$tmp_name  = (string) ( $file['tmp_name'] ?? '' );
		$mime_type = (string) ( $file['type'] ?? '' );

		if ( '' !== $filename && function_exists( 'wp_check_filetype_and_ext' ) ) {
			$check = wp_check_filetype_and_ext( $tmp_name, $filename );

			if ( ! empty( $check['type'] ) ) {
				return (string) $check['type'];
			}
		}

		return $mime_type;
	}

	private static function extract_text_from_file( $file_path, $mime_type, $extension ) {
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return '';
		}

		$extension = strtolower( (string) $extension );

		if ( in_array( $extension, array( 'txt', 'text' ), true ) || false !== strpos( $mime_type, 'text/plain' ) ) {
			$contents = file_get_contents( $file_path );

			return false === $contents ? '' : self::normalize_extracted_document_text( $contents );
		}

		if ( 'docx' === $extension || false !== strpos( $mime_type, 'wordprocessingml.document' ) ) {
			return self::extract_text_from_docx_file( $file_path );
		}

		if ( 'doc' === $extension || false !== strpos( $mime_type, 'msword' ) ) {
			return self::extract_text_from_doc_file( $file_path );
		}

		return '';
	}

	private static function extract_text_from_docx_file( $file_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $file_path ) ) {
			return '';
		}

		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $xml || '' === $xml ) {
			return '';
		}

		$text = str_replace( array( '</w:p>', '</w:tr>', '</w:tc>' ), array( "\n\n", "\n", ' ' ), $xml );
		$text = preg_replace( '/<w:tab[^>]*\/>/i', "\t", (string) $text );
		$text = wp_strip_all_tags( $text );

		return self::normalize_extracted_document_text( $text );
	}

	private static function extract_text_from_doc_file( $file_path ) {
		$command_path = self::find_cli_binary( array( 'antiword', 'C:\\msys64\\mingw64\\bin\\antiword.exe', 'C:\\Program Files\\antiword\\antiword.exe' ) );

		if ( '' === $command_path ) {
			return '';
		}

		$output = array();
		$return = 0;
		exec( escapeshellarg( $command_path ) . ' ' . escapeshellarg( $file_path ) . ' 2>&1', $output, $return );

		if ( 0 !== $return || empty( $output ) ) {
			return '';
		}

		return self::normalize_extracted_document_text( implode( "\n", $output ) );
	}

	private static function find_cli_binary( $candidates ) {
		$paths = array_filter( explode( PATH_SEPARATOR, (string) getenv( 'PATH' ) ) );

		foreach ( (array) $candidates as $candidate ) {
			if ( is_file( $candidate ) && is_readable( $candidate ) ) {
				return $candidate;
			}

			foreach ( $paths as $path ) {
				$path = rtrim( (string) $path, DIRECTORY_SEPARATOR );

				foreach ( array( $path . DIRECTORY_SEPARATOR . $candidate, $path . DIRECTORY_SEPARATOR . $candidate . '.exe' ) as $binary ) {
					if ( is_file( $binary ) && is_readable( $binary ) ) {
						return $binary;
					}
				}
			}
		}

		return '';
	}

	private static function normalize_extracted_document_text( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, get_bloginfo( 'charset' ) ? get_bloginfo( 'charset' ) : 'UTF-8' );
		$text = preg_replace( '/\r\n|\r/', "\n", $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/ ?\n ?/', "\n", $text );

		return trim( wp_strip_all_tags( (string) $text ) );
	}

	private static function store_uploaded_file_as_attachment( $file, $title = '' ) {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$uploaded = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
			)
		);

		if ( ! empty( $uploaded['error'] ) || empty( $uploaded['file'] ) ) {
			return new WP_Error( 'upload_failed', __( 'Nao foi possivel enviar um dos arquivos selecionados.', EOP_TEXT_DOMAIN ) );
		}

		return self::insert_attachment_record( $uploaded['file'], $uploaded['url'], $uploaded['type'] ?? '', $title );
	}

	private static function store_bits_as_attachment( $filename, $contents, $mime_type = '', $title = '' ) {
		$uploaded = wp_upload_bits( sanitize_file_name( $filename ), null, $contents );

		if ( ! empty( $uploaded['error'] ) || empty( $uploaded['file'] ) ) {
			return new WP_Error( 'upload_bits_failed', __( 'Nao foi possivel restaurar um dos arquivos do pacote.', EOP_TEXT_DOMAIN ) );
		}

		return self::insert_attachment_record( $uploaded['file'], $uploaded['url'], $mime_type, $title );
	}

	private static function insert_attachment_record( $file_path, $url, $mime_type = '', $title = '' ) {
		$filetype = wp_check_filetype( wp_basename( $file_path ), null );

		$attachment = array(
			'post_mime_type' => '' !== $mime_type ? $mime_type : ( $filetype['type'] ?? 'application/octet-stream' ),
			'post_title'     => '' !== $title ? sanitize_text_field( $title ) : sanitize_text_field( preg_replace( '/\.[^.]+$/', '', wp_basename( $file_path ) ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $file_path );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return new WP_Error( 'attachment_insert_failed', __( 'Nao foi possivel registrar um dos arquivos importados.', EOP_TEXT_DOMAIN ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
		if ( ! empty( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		return absint( $attachment_id );
	}

	private static function ensure_admin_request( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
		}

		check_admin_referer( $action );
	}

	private static function set_notice( $type, $message ) {
		set_transient(
			self::NOTICE_TRANSIENT . get_current_user_id(),
			array(
				'type'    => 'error' === $type ? 'error' : 'success',
				'message' => sanitize_text_field( $message ),
			),
			MINUTE_IN_SECONDS
		);
	}

	private static function consume_notice() {
		$key    = self::NOTICE_TRANSIENT . get_current_user_id();
		$notice = get_transient( $key );
		delete_transient( $key );

		return is_array( $notice ) ? $notice : array();
	}

	private static function redirect_with_notice( $type, $message ) {
		self::set_notice( $type, $message );

		$target = class_exists( 'EOP_Admin_Page' )
			? EOP_Admin_Page::get_view_url( 'export-import' )
			: add_query_arg(
				array(
					'page' => 'eop-pedido-expresso',
					'view' => 'export-import',
				),
				admin_url( 'admin.php' )
			);

		wp_safe_redirect( $target );
		exit;
	}
}
