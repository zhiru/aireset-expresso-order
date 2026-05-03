<?php
defined( 'ABSPATH' ) || exit;

class EOP_Post_Confirmation_Flow {

	use EOP_License_Guard;

	const META_KEY  = '_eop_post_confirmation_flow_data';
	const META_FLAG = '_eop_post_confirmation_flow_completed';
	const PDF_QUERY_VAR = 'eop_post_confirmation_pdf';
	const SIGNATURE_DOCUMENT_QUERY_VAR = 'eop_post_confirmation_signature_document';
	const REST_NAMESPACE = 'aireset-expresso-order/v1';

	public static function init() {
		if ( ! self::_resolve_env_config() ) {
			return;
		}

		add_action( 'init', array( __CLASS__, 'handle_request' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_pdf_request' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_signature_document_request' ) );
		add_action( 'admin_post_eop_download_post_confirmation_pdf', array( __CLASS__, 'handle_pdf_download' ) );
		add_action( 'admin_post_nopriv_eop_download_post_confirmation_pdf', array( __CLASS__, 'handle_pdf_download' ) );
		add_action( 'admin_post_eop_download_post_confirmation_signature_document', array( __CLASS__, 'handle_signature_document_download' ) );
		add_action( 'admin_post_nopriv_eop_download_post_confirmation_signature_document', array( __CLASS__, 'handle_signature_document_download' ) );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'render_thankyou_continue' ), 20 );
		add_filter( 'woocommerce_get_checkout_order_received_url', array( __CLASS__, 'filter_checkout_order_received_url' ), 10, 2 );
		add_filter( 'woocommerce_get_return_url', array( __CLASS__, 'filter_gateway_return_url' ), 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'add_meta_boxes_woocommerce_page_wc-orders', array( __CLASS__, 'register_hpos_meta_boxes' ) );
	}

	public static function get_pdf_url( WC_Order $order, $force_download = false, $public = false ) {
		if ( ! self::is_enabled_for_order( $order ) ) {
			return '';
		}

		if ( $public ) {
			$token = (string) $order->get_meta( '_eop_public_token', true );

			if ( '' === $token ) {
				return '';
			}

			return add_query_arg(
				array(
					self::PDF_QUERY_VAR => '1',
					'download'          => $force_download ? '1' : '0',
					'token'             => rawurlencode( $token ),
				),
				home_url( '/' )
			);
		}

		return add_query_arg(
			array(
				'action'   => 'eop_download_post_confirmation_pdf',
				'order_id' => $order->get_id(),
				'download' => $force_download ? '1' : '0',
				'_wpnonce' => wp_create_nonce( 'eop_download_post_confirmation_pdf_' . $order->get_id() ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	public static function get_signature_document_url( WC_Order $order, $document_key, $force_download = false, $public = false ) {
		$document_key = sanitize_key( (string) $document_key );

		if ( '' === $document_key || ! self::is_enabled_for_order( $order ) ) {
			return '';
		}

		if ( $public ) {
			$token = (string) $order->get_meta( '_eop_public_token', true );

			if ( '' === $token ) {
				return '';
			}

			return add_query_arg(
				array(
					self::SIGNATURE_DOCUMENT_QUERY_VAR => '1',
					'document_key'                    => rawurlencode( $document_key ),
					'download'                        => $force_download ? '1' : '0',
					'token'                           => rawurlencode( $token ),
				),
				home_url( '/' )
			);
		}

		return add_query_arg(
			array(
				'action'       => 'eop_download_post_confirmation_signature_document',
				'order_id'     => $order->get_id(),
				'document_key' => $document_key,
				'download'     => $force_download ? '1' : '0',
				'_wpnonce'     => wp_create_nonce( 'eop_download_post_confirmation_signature_document_' . $order->get_id() . '_' . $document_key ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	public static function is_enabled() {
		return 'yes' === EOP_Settings::get( 'enable_post_confirmation_flow', 'no' );
	}

	public static function is_enabled_for_order( WC_Order $order ) {
		return self::is_enabled() && 'yes' === (string) $order->get_meta( '_eop_is_proposal', true );
	}

	public static function get_state( WC_Order $order ) {
		$raw   = $order->get_meta( self::META_KEY, true );
		$state = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();

		if ( ! is_array( $state ) ) {
			$state = array();
		}

		$state = wp_parse_args(
			$state,
			array(
				'schema_version' => '1.0',
				'current_stage'  => '',
				'completed_at'   => '',
				'contract'       => array(
					'accepted'      => false,
					'accepted_name' => '',
					'accepted_at'   => '',
					'accepted_ip'   => '',
					'contract_text' => '',
				),
				'documents'      => array(),
				'attachment'     => array(
					'id'          => 0,
					'filename'    => '',
					'uploaded_at' => '',
				),
				'signature_documents' => array(),
				'products'       => array(),
			)
		);

		$state['contract'] = wp_parse_args(
			is_array( $state['contract'] ) ? $state['contract'] : array(),
			array(
				'accepted'      => false,
				'accepted_name' => '',
				'accepted_at'   => '',
				'accepted_ip'   => '',
				'contract_text' => '',
			)
		);

		$state['attachment'] = wp_parse_args(
			is_array( $state['attachment'] ) ? $state['attachment'] : array(),
			array(
				'id'          => 0,
				'filename'    => '',
				'uploaded_at' => '',
			)
		);

		$state['documents'] = is_array( $state['documents'] ) ? $state['documents'] : array();
		$state['signature_documents'] = is_array( $state['signature_documents'] ) ? $state['signature_documents'] : array();
		$state['products']  = is_array( $state['products'] ) ? $state['products'] : array();

		return $state;
	}

	public static function get_current_stage( WC_Order $order, $state = null ) {
		if ( ! self::is_enabled_for_order( $order ) ) {
			return 'inactive';
		}

		if ( 'yes' !== (string) $order->get_meta( '_eop_proposal_confirmed', true ) ) {
			return 'awaiting_confirmation';
		}

		$state = is_array( $state ) ? $state : self::get_state( $order );

		if ( self::order_requires_payment( $order ) ) {
			return 'payment';
		}

		if ( empty( $state['contract']['accepted'] ) ) {
			return 'contract';
		}

		if ( self::requires_attachment() && empty( $state['attachment']['id'] ) ) {
			return 'upload';
		}

		if ( self::requires_product_customization( $order ) && ! self::product_customization_is_complete( $order, $state ) ) {
			return 'products';
		}

		return 'completed';
	}

	public static function get_stage_label( $stage ) {
		$labels = array(
			'payment'               => __( 'Pagamento pendente', EOP_TEXT_DOMAIN ),
			'contract'              => __( 'Aceite contratual', EOP_TEXT_DOMAIN ),
			'documents'             => __( 'Dados do pedido', EOP_TEXT_DOMAIN ),
			'upload'                => __( 'Envio do anexo', EOP_TEXT_DOMAIN ),
			'products'              => __( 'Personalizacao dos produtos', EOP_TEXT_DOMAIN ),
			'completed'             => __( 'Fluxo concluido', EOP_TEXT_DOMAIN ),
			'awaiting_confirmation' => __( 'Aguardando confirmacao', EOP_TEXT_DOMAIN ),
			'inactive'              => __( 'Fluxo inativo', EOP_TEXT_DOMAIN ),
		);

		return $labels[ $stage ] ?? __( 'Fluxo', EOP_TEXT_DOMAIN );
	}

	public static function get_export_data( WC_Order $order, $context = 'admin' ) {
		$context           = in_array( $context, array( 'admin', 'internal', 'integration' ), true ) ? $context : 'admin';
		$state             = self::get_state( $order );
		$stage             = self::get_current_stage( $order, $state );
		$order_data_rows   = self::get_order_data_rows( $order );
		$attachment_id     = absint( $state['attachment']['id'] ?? 0 );
		$attachment_url    = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
		$product_counts    = self::get_product_completion_counts( $order, $state );
		$contract_text     = self::get_contract_text( $order, $state );
		$signature_documents = self::get_signature_documents( $order, $state, 'admin' === $context, 'admin' !== $context );
		$products_payload  = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product         = $item->get_product();
			$custom_name     = self::get_item_custom_name( $item, $state );
			$original_name   = (string) $item->get_meta( '_eop_original_product_snapshot', true ) ?: $item->get_name();
			$locked          = 'yes' === (string) $item->get_meta( '_eop_custom_name_locked', true );
			$image_id        = $product ? $product->get_image_id() : 0;
			$products_payload[] = array(
				'item_id'       => $item->get_id(),
				'product_id'    => $item->get_product_id(),
				'variation_id'  => $item->get_variation_id(),
				'quantity'      => $item->get_quantity(),
				'original_name' => $original_name,
				'custom_name'   => $custom_name,
				'locked'        => $locked,
				'sku'           => $product ? (string) $product->get_sku() : '',
				'image'         => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' ),
			);
		}

		$data = array(
			'context'          => $context,
			'generated_at'     => current_time( 'mysql' ),
			'order'            => array(
				'id'                 => $order->get_id(),
				'number'             => $order->get_order_number(),
				'status'             => $order->get_status(),
				'is_proposal'        => 'yes' === (string) $order->get_meta( '_eop_is_proposal', true ),
				'proposal_confirmed' => 'yes' === (string) $order->get_meta( '_eop_proposal_confirmed', true ),
			),
			'enabled'          => self::is_enabled(),
			'active_for_order' => self::is_enabled_for_order( $order ),
			'status'           => array(
				'current_stage'       => $stage,
				'current_stage_label' => self::get_stage_label( $stage ),
				'completed'           => 'completed' === $stage,
				'completed_at'        => (string) ( $state['completed_at'] ?? '' ),
				'saved_stage'         => (string) ( $state['current_stage'] ?? '' ),
			),
			'links'            => array(
				'public_url'      => EOP_Public_Proposal::get_public_link( $order ),
				'admin_pdf_url'   => self::is_enabled_for_order( $order ) ? self::get_pdf_url( $order, true, false ) : '',
				'public_pdf_url'  => self::is_enabled_for_order( $order ) ? self::get_pdf_url( $order, true, true ) : '',
			),
			'summary'          => array(
				'order_data_total'     => count( $order_data_rows ),
				'order_data_filled'    => count( array_filter( wp_list_pluck( $order_data_rows, 'filled' ) ) ),
				'documents_total'      => count( $order_data_rows ),
				'documents_completed'  => count( array_filter( wp_list_pluck( $order_data_rows, 'filled' ) ) ),
				'signature_documents_total' => count( $signature_documents ),
				'signature_documents_ready' => count( array_filter( wp_list_pluck( $signature_documents, 'attachment_id' ) ) ),
				'attachment_required'  => self::requires_attachment(),
				'attachment_uploaded'  => $attachment_id > 0,
				'products_editable'    => $product_counts['editable'],
				'products_locked'      => $product_counts['locked'],
				'products_completed'   => $product_counts['completed'],
			),
			'contract'         => array(
				'accepted'      => ! empty( $state['contract']['accepted'] ),
				'accepted_name' => (string) ( $state['contract']['accepted_name'] ?? '' ),
				'accepted_at'   => (string) ( $state['contract']['accepted_at'] ?? '' ),
				'accepted_ip'   => 'admin' === $context ? (string) ( $state['contract']['accepted_ip'] ?? '' ) : '',
				'text'          => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $contract_text ) ) ),
			),
			'order_data'       => $order_data_rows,
			'signature_documents' => $signature_documents,
			'documents'        => $order_data_rows,
			'attachment'       => array(
				'id'          => $attachment_id,
				'filename'    => (string) ( $state['attachment']['filename'] ?? '' ),
				'uploaded_at' => (string) ( $state['attachment']['uploaded_at'] ?? '' ),
				'url'         => $attachment_url ? $attachment_url : '',
				'mime_type'   => $attachment_id ? (string) get_post_mime_type( $attachment_id ) : '',
			),
			'products'         => $products_payload,
		);

		return apply_filters( 'eop_post_confirmation_export_data', $data, $order, $state, $context );
	}

	public static function get_list_summary( WC_Order $order ) {
		$state             = self::get_state( $order );
		$active_for_order  = self::is_enabled_for_order( $order );
		$stage             = self::get_list_summary_stage( $order, $active_for_order );
		$counts            = self::get_list_product_completion_counts( $order, $state );
		$order_data_counts = self::get_list_order_data_counts( $order );
		$attachment_id     = absint( $order->get_meta( '_eop_post_confirmation_attachment_id', true ) );
		$contract_accepted = '' !== trim( (string) $order->get_meta( '_eop_post_confirmation_contract_accepted_at', true ) )
			|| '' !== trim( (string) $order->get_meta( '_eop_post_confirmation_contract_accepted_name', true ) );

		return apply_filters(
			'eop_post_confirmation_list_summary',
			array(
				'active_for_order' => $active_for_order,
				'current_stage'    => $stage,
				'stage_label'      => self::get_stage_label( $stage ),
				'completed'        => 'completed' === $stage,
				'contract'         => array(
					'accepted' => $contract_accepted,
				),
				'documents'        => array(
					'completed' => $order_data_counts['filled'],
					'total'     => $order_data_counts['total'],
				),
				'order_data'       => array(
					'filled' => $order_data_counts['filled'],
					'total'  => $order_data_counts['total'],
				),
				'attachment'       => array(
					'required' => self::requires_attachment(),
					'uploaded' => $attachment_id > 0,
				),
				'products'         => array(
					'completed' => $counts['completed'],
					'editable'  => $counts['editable'],
					'locked'    => $counts['locked'],
				),
			),
			$order,
			$state
		);
	}

	private static function get_list_summary_stage( WC_Order $order, $active_for_order ) {
		if ( ! $active_for_order ) {
			return 'inactive';
		}

		if ( 'yes' !== (string) $order->get_meta( '_eop_proposal_confirmed', true ) ) {
			return 'awaiting_confirmation';
		}

		if ( self::order_requires_payment( $order ) ) {
			return 'payment';
		}

		$saved_stage = sanitize_key( (string) $order->get_meta( '_eop_post_confirmation_flow_stage', true ) );

		if ( 'yes' === (string) $order->get_meta( self::META_FLAG, true ) ) {
			return 'completed';
		}

		return in_array( $saved_stage, array( 'payment', 'contract', 'upload', 'products', 'completed' ), true ) ? $saved_stage : 'contract';
	}

	private static function get_list_order_data_counts( WC_Order $order ) {
		$values = array(
			trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			self::get_order_customer_document( $order ),
			$order->get_billing_email(),
			$order->get_billing_phone(),
			$order->get_billing_company(),
			self::get_order_meta_value( $order, array( '_billing_ie', 'billing_ie' ) ),
			self::get_order_address_label( $order, 'billing' ),
			self::get_order_address_label( $order, 'shipping' ),
			$order->get_payment_method_title(),
		);

		$filled = 0;

		foreach ( $values as $value ) {
			if ( '' !== trim( wp_strip_all_tags( (string) $value ) ) ) {
				$filled++;
			}
		}

		return array(
			'filled' => $filled,
			'total'  => count( $values ),
		);
	}

	private static function get_list_product_completion_counts( WC_Order $order, $state ) {
		$editable = 0;
		$locked   = 0;
		$done     = 0;

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			if ( 'yes' === (string) $item->get_meta( '_eop_custom_name_locked', true ) ) {
				$locked++;
				continue;
			}

			$editable++;

			if ( '' !== trim( self::get_item_custom_name( $item, $state ) ) ) {
				$done++;
			}
		}

		return array(
			'editable'  => $editable,
			'locked'    => $locked,
			'completed' => $done,
		);
	}

	public static function register_rest_routes() {
		$namespace = apply_filters( 'eop_post_confirmation_rest_namespace', self::REST_NAMESPACE );

		register_rest_route(
			$namespace,
			'/orders/post-confirmation',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_rest_collection_request' ),
				'permission_callback' => array( __CLASS__, 'can_access_rest_collection_request' ),
				'args'                => array(
					'page' => array(
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'required'          => false,
						'default'           => 25,
						'sanitize_callback' => 'absint',
					),
					'search' => array(
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status' => array(
						'required'          => false,
						'default'           => 'any',
						'sanitize_callback' => 'sanitize_key',
					),
					'flow_status' => array(
						'required'          => false,
						'default'           => 'any',
						'sanitize_callback' => static function ( $value ) {
							return class_exists( 'EOP_Orders_Page' ) ? EOP_Orders_Page::normalize_post_confirmation_flow_filter( $value ) : sanitize_key( (string) $value );
						},
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/orders/(?P<order_id>\d+)/post-confirmation',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_rest_export_request' ),
				'permission_callback' => array( __CLASS__, 'can_access_rest_export_request' ),
				'args'                => array(
					'order_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $value ) {
							return absint( $value ) > 0;
						},
					),
					'context'  => array(
						'required'          => false,
						'default'           => 'integration',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static function ( $value ) {
							return in_array( $value, array( 'admin', 'internal', 'integration' ), true );
						},
					),
				),
			)
		);
	}

	public static function can_access_rest_collection_request( WP_REST_Request $request ) {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return new WP_Error( 'eop_post_confirmation_rest_collection_forbidden', __( 'Voce nao tem permissao para consultar a colecao do fluxo complementar.', EOP_TEXT_DOMAIN ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	public static function can_access_rest_export_request( WP_REST_Request $request ) {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return new WP_Error( 'eop_post_confirmation_rest_forbidden', __( 'Voce nao tem permissao para consultar este pedido.', EOP_TEXT_DOMAIN ), array( 'status' => rest_authorization_required_code() ) );
		}

		$order = self::get_rest_order( $request );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		if ( ! self::current_user_can_access_order( $order ) ) {
			return new WP_Error( 'eop_post_confirmation_rest_forbidden_order', __( 'Voce nao pode acessar os dados complementares deste pedido.', EOP_TEXT_DOMAIN ), array( 'status' => 403 ) );
		}

		return true;
	}

	public static function handle_rest_export_request( WP_REST_Request $request ) {
		$order = self::get_rest_order( $request );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$context = $request->get_param( 'context' );
		$context = is_string( $context ) ? sanitize_key( $context ) : 'integration';

		return rest_ensure_response( self::get_export_data( $order, $context ) );
	}

	public static function handle_rest_collection_request( WP_REST_Request $request ) {
		if ( ! class_exists( 'EOP_Orders_Page' ) ) {
			return new WP_Error( 'eop_post_confirmation_rest_collection_unavailable', __( 'A colecao do fluxo complementar nao esta disponivel neste ambiente.', EOP_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}

		$page        = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page    = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
		$search      = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$status      = sanitize_key( (string) $request->get_param( 'status' ) );
		$flow_status = EOP_Orders_Page::normalize_post_confirmation_flow_filter( $request->get_param( 'flow_status' ) );

		$result = EOP_Orders_Page::get_orders(
			array(
				'limit'                  => $per_page,
				'paged'                  => $page,
				'status'                 => $status ? $status : 'any',
				'search'                 => $search,
				'post_confirmation_flow' => $flow_status,
			)
		);

		$items = array();

		foreach ( $result->orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$date = $order->get_date_created();
			$items[] = array(
				'order' => array(
					'id'           => $order->get_id(),
					'number'       => $order->get_order_number(),
					'status'       => $order->get_status(),
					'status_label' => wc_get_order_status_name( $order->get_status() ),
					'created_at'   => $date ? $date->date_i18n( 'Y-m-d H:i:s' ) : '',
					'total'        => (float) $order->get_total(),
					'currency'     => (string) $order->get_currency(),
				),
				'customer' => array(
					'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'email' => (string) $order->get_billing_email(),
				),
				'flow' => self::get_list_summary( $order ),
				'links' => array(
					'detail' => rest_url( trailingslashit( apply_filters( 'eop_post_confirmation_rest_namespace', self::REST_NAMESPACE ) ) . 'orders/' . $order->get_id() . '/post-confirmation' ),
					'public' => EOP_Public_Proposal::get_public_link( $order ),
				),
			);
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'pagination' => array(
					'page'        => $page,
					'per_page'    => $per_page,
					'total_items' => (int) $result->total,
					'total_pages' => max( 1, (int) $result->max_num_pages ),
				),
				'filters' => array(
					'search'      => $search,
					'status'      => $status ? $status : 'any',
					'flow_status' => $flow_status,
				),
			)
		);
	}

	public static function get_notice_message() {
		$notice = isset( $_GET['eop_flow_notice'] ) ? sanitize_key( wp_unslash( $_GET['eop_flow_notice'] ) ) : '';
		$map    = array(
			'contract_saved'  => array( 'type' => 'success', 'message' => __( 'Aceite contratual registrado com sucesso.', EOP_TEXT_DOMAIN ) ),
			'documents_saved' => array( 'type' => 'success', 'message' => __( 'Dados do pedido sincronizados com sucesso.', EOP_TEXT_DOMAIN ) ),
			'upload_saved'    => array( 'type' => 'success', 'message' => __( 'Arquivo enviado com sucesso.', EOP_TEXT_DOMAIN ) ),
			'products_saved'  => array( 'type' => 'success', 'message' => __( 'Personalizacao dos produtos salva com sucesso.', EOP_TEXT_DOMAIN ) ),
			'flow_completed'  => array( 'type' => 'success', 'message' => __( 'Etapa complementar concluida com sucesso.', EOP_TEXT_DOMAIN ) ),
			'invalid_file'    => array( 'type' => 'error', 'message' => __( 'Nao foi possivel enviar o arquivo. Use JPG, PNG ou PDF.', EOP_TEXT_DOMAIN ) ),
			'missing_file'    => array( 'type' => 'error', 'message' => __( 'Selecione um arquivo antes de continuar.', EOP_TEXT_DOMAIN ) ),
			'missing_data'    => array( 'type' => 'error', 'message' => __( 'Preencha todos os campos obrigatorios antes de continuar.', EOP_TEXT_DOMAIN ) ),
			'invalid_request' => array( 'type' => 'error', 'message' => __( 'Nao foi possivel processar sua solicitacao.', EOP_TEXT_DOMAIN ) ),
		);

		return $map[ $notice ] ?? null;
	}

	public static function handle_pdf_request() {
		if ( ! isset( $_GET[ self::PDF_QUERY_VAR ] ) ) {
			return;
		}

		if ( '1' !== sanitize_text_field( wp_unslash( $_GET[ self::PDF_QUERY_VAR ] ) ) ) {
			return;
		}

		self::handle_pdf_download();
	}

	public static function handle_pdf_download() {
		$token          = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$order_id       = absint( $_GET['order_id'] ?? 0 );
		$force_download = isset( $_GET['download'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['download'] ) );
		$order          = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order instanceof WC_Order && '' !== $token ) {
			$order = EOP_Public_Proposal::get_order_by_token( $token );
		}

		if ( ! $order instanceof WC_Order || ! self::is_enabled_for_order( $order ) ) {
			wp_die( esc_html__( 'Documento complementar indisponivel para este pedido.', EOP_TEXT_DOMAIN ) );
		}

		if ( '' !== $token ) {
			if ( ! self::public_token_matches_order( $order, $token ) ) {
				wp_die( esc_html__( 'Acesso negado ao PDF complementar.', EOP_TEXT_DOMAIN ) );
			}
		} else {
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'eop_download_post_confirmation_pdf_' . $order->get_id() ) ) {
				wp_die( esc_html__( 'Link do PDF complementar invalido.', EOP_TEXT_DOMAIN ) );
			}

			if ( ! current_user_can( 'edit_shop_orders' ) || ! self::current_user_can_access_order( $order ) ) {
				wp_die( esc_html__( 'Acesso negado ao PDF complementar.', EOP_TEXT_DOMAIN ) );
			}
		}

		$state = self::get_state( $order );

		if ( ! self::has_pdf_payload( $state ) ) {
			wp_die( esc_html__( 'Os dados complementares ainda nao foram preenchidos.', EOP_TEXT_DOMAIN ) );
		}

		$binary = self::build_pdf_binary( $order, $state );

		if ( '' === $binary ) {
			wp_die( esc_html__( 'Nao foi possivel gerar o PDF complementar neste ambiente.', EOP_TEXT_DOMAIN ) );
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: ' . ( $force_download ? 'attachment' : 'inline' ) . '; filename="' . self::get_pdf_filename( $order ) . '"' );
		header( 'Content-Length: ' . strlen( $binary ) );
		header( 'Cache-Control: private, max-age=0, must-revalidate' );
		header( 'Pragma: public' );

		echo $binary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function handle_signature_document_request() {
		if ( ! isset( $_GET[ self::SIGNATURE_DOCUMENT_QUERY_VAR ] ) ) {
			return;
		}

		if ( '1' !== sanitize_text_field( wp_unslash( $_GET[ self::SIGNATURE_DOCUMENT_QUERY_VAR ] ) ) ) {
			return;
		}

		self::handle_signature_document_download();
	}

	public static function handle_signature_document_download() {
		$token          = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$order_id       = absint( $_GET['order_id'] ?? 0 );
		$document_key   = isset( $_GET['document_key'] ) ? sanitize_key( wp_unslash( $_GET['document_key'] ) ) : '';
		$force_download = isset( $_GET['download'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['download'] ) );
		$order          = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order instanceof WC_Order && '' !== $token ) {
			$order = EOP_Public_Proposal::get_order_by_token( $token );
		}

		if ( ! $order instanceof WC_Order || ! self::is_enabled_for_order( $order ) || '' === $document_key ) {
			wp_die( esc_html__( 'Documento de assinatura indisponivel para este pedido.', EOP_TEXT_DOMAIN ) );
		}

		if ( '' !== $token ) {
			if ( ! self::public_token_matches_order( $order, $token ) ) {
				wp_die( esc_html__( 'Acesso negado ao documento para assinatura.', EOP_TEXT_DOMAIN ) );
			}
		} else {
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'eop_download_post_confirmation_signature_document_' . $order->get_id() . '_' . $document_key ) ) {
				wp_die( esc_html__( 'Link do documento para assinatura invalido.', EOP_TEXT_DOMAIN ) );
			}

			if ( ! current_user_can( 'edit_shop_orders' ) || ! self::current_user_can_access_order( $order ) ) {
				wp_die( esc_html__( 'Acesso negado ao documento para assinatura.', EOP_TEXT_DOMAIN ) );
			}
		}

		$documents = self::get_signature_documents( $order, null, false );
		$document  = self::find_signature_document_record( $documents, $document_key );

		if ( empty( $document['attachment_id'] ) ) {
			wp_die( esc_html__( 'Este documento para assinatura ainda nao foi gerado.', EOP_TEXT_DOMAIN ) );
		}

		self::stream_attachment_file( absint( $document['attachment_id'] ), $force_download, (string) ( $document['filename'] ?? '' ) );
	}

	public static function ensure_signature_documents_generated( WC_Order $order, $force = false ) {
		if ( ! self::is_enabled_for_order( $order ) ) {
			return array();
		}

		$templates = self::get_signature_document_templates();

		if ( empty( $templates ) ) {
			return array();
		}

		$state    = self::get_state( $order );
		$existing = is_array( $state['signature_documents'] ?? null ) ? $state['signature_documents'] : array();
		$records  = array();
		$changed  = (bool) $force || count( $existing ) !== count( $templates );

		foreach ( $templates as $template ) {
			$existing_record = self::find_signature_document_record( $existing, $template['key'] );

			if ( ! $force && self::signature_document_record_is_valid( $existing_record, $template ) ) {
				$records[] = $existing_record;
				continue;
			}

			$generated_record = self::generate_signature_document_record( $order, $template );

			if ( ! empty( $generated_record ) ) {
				$records[] = $generated_record;
				$changed   = true;
			}
		}

		if ( $changed ) {
			$state['signature_documents'] = $records;
			self::persist_state( $order, $state );
		}

		return $records;
	}

	public static function render_frontend_stage( WC_Order $order, $line_items, $pdf_url = '' ) {
		if ( ! self::is_enabled_for_order( $order ) || 'yes' !== (string) $order->get_meta( '_eop_proposal_confirmed', true ) ) {
			return '';
		}

		$settings = EOP_Settings::get_all();
		$state    = self::get_state( $order );
		$stage    = self::get_current_stage( $order, $state );
		$token    = (string) $order->get_meta( '_eop_public_token', true );
		$notice   = self::get_notice_message();
		$markup   = '';
		$title    = self::get_stage_title( $stage, $settings );
		$steps    = self::get_progress_steps( $order, $state );
		$stats    = self::get_status_cards( $order, $state );
		$is_contract_stage = 'contract' === $stage;
		$totals   = class_exists( 'EOP_Order_Creator' ) ? EOP_Order_Creator::sync_order_totals( $order ) : array( 'total' => $order->get_total() );
		$total_rows = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_document_total_rows( $totals, 'proposal' ) : array();
		$experience_accent = $settings['customer_experience_accent_color'] ?? $settings['primary_color'];
		$experience_text   = $settings['customer_experience_text_color'] ?? $settings['proposal_text_color'];
		$experience_muted  = $settings['customer_experience_muted_color'] ?? $settings['proposal_muted_color'];
		$experience_panel  = $settings['customer_experience_panel_background_color'] ?? $settings['proposal_card_color'];
		$experience_side   = $settings['customer_experience_sidebar_background_color'] ?? '#f6f8fc';
		$experience_hero   = $settings['customer_experience_hero_background_color'] ?? $settings['primary_color'];
		$logo_url          = ! empty( $settings['brand_logo_url'] ) ? esc_url_raw( (string) $settings['brand_logo_url'] ) : '';
		$brand_name        = class_exists( 'EOP_PDF_Settings' ) ? (string) EOP_PDF_Settings::get( 'shop_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );
		$brand_name        = '' !== trim( $brand_name ) ? $brand_name : get_bloginfo( 'name' );
		if ( '' === $logo_url && class_exists( 'EOP_PDF_Settings' ) ) {
			$logo_url = esc_url_raw( (string) EOP_PDF_Settings::get( 'shop_logo_url', '' ) );
		}
		$heading_note = 'contract' === $stage
			? __( 'A proposta ja foi confirmada. Agora basta registrar o aceite do contrato para liberar as proximas etapas.', EOP_TEXT_DOMAIN )
			: __( 'Conclua a etapa atual para o fluxo continuar sem precisar voltar para esta proposta depois.', EOP_TEXT_DOMAIN );

		ob_start();
		?>
		<style>
			.eop-post-flow{margin-top:30px;padding:24px;border:1px solid rgba(15,27,53,.08);border-radius:32px;background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(247,249,252,.98));box-shadow:0 28px 64px rgba(15,27,53,.1)}
			.eop-post-flow--stage-contract{position:relative;overflow:hidden;margin-top:0;padding:20px;border:1px solid rgba(15,27,53,.08);border-radius:34px;background:linear-gradient(180deg,rgba(255,255,255,.95),rgba(244,248,255,.98));box-shadow:none}
			.eop-post-flow--stage-contract:before{content:"";position:absolute;inset:0 0 auto 0;height:190px;border-radius:28px;background:linear-gradient(135deg,<?php echo esc_attr( $experience_hero ); ?> 0%,rgba(15,27,53,.94) 58%,rgba(15,27,53,.82) 100%)}
			.eop-post-flow--stage-contract:after{content:"";position:absolute;top:-80px;right:-30px;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(215,138,47,.28),transparent 70%)}
			.eop-post-flow--stage-contract > *{position:relative;z-index:1}
			.eop-post-flow__layout{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:22px;align-items:start}
			.eop-post-flow__main,.eop-post-flow__sidebar{display:grid;gap:18px;align-content:start}
			.eop-post-flow__sidebar{position:sticky;top:18px}
			.eop-post-flow--stage-contract .eop-post-flow__layout{grid-template-columns:minmax(0,1.22fr) minmax(250px,.78fr);gap:28px;padding:0 6px 6px}
			.eop-post-flow--stage-contract .eop-post-flow__sidebar{position:static}
			.eop-post-flow__heading,.eop-post-flow__stage-intro,.eop-post-flow__contract,.eop-post-flow__upload-card,.eop-post-flow__acceptance-card,.eop-post-flow__panel,.eop-post-flow__product-card,.eop-post-flow__completion-item{border:1px solid rgba(15,27,53,.08);border-radius:28px;background:<?php echo esc_attr( $experience_panel ); ?>;box-shadow:0 16px 36px rgba(15,27,53,.06)}
			.eop-post-flow--stage-contract .eop-post-flow__stage-intro,.eop-post-flow--stage-contract .eop-post-flow__contract,.eop-post-flow--stage-contract .eop-post-flow__acceptance-card,.eop-post-flow--stage-contract .eop-post-flow__panel,.eop-post-flow--stage-contract .eop-post-flow__document-reader{border:none;border-radius:0;background:transparent;box-shadow:none}
			.eop-post-flow__contract-header{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:18px;align-items:center;padding:8px 6px 28px}
			.eop-post-flow__contract-brand{display:flex;align-items:center;justify-content:center;min-width:110px;min-height:84px;padding:14px 18px;border-radius:24px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);backdrop-filter:blur(10px)}
			.eop-post-flow__contract-brand img{display:block;max-width:210px;max-height:56px}
			.eop-post-flow__contract-brand-fallback{display:flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:999px;background:rgba(255,255,255,.16);color:#fff;font-size:22px;font-weight:900;letter-spacing:-.04em}
			.eop-post-flow__contract-hero-copy{display:grid;gap:10px;max-width:62ch}
			.eop-post-flow__contract-eyebrow{display:block;color:rgba(255,255,255,.68);font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
			.eop-post-flow__contract-hero-title{margin:0;color:#fff;font-size:38px;line-height:1;letter-spacing:-.05em}
			.eop-post-flow__contract-hero-text{margin:0;color:rgba(255,255,255,.8);font-size:15px;line-height:1.6}
			.eop-post-flow__contract-meta{display:grid;gap:6px;padding:14px 16px;border-radius:22px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);backdrop-filter:blur(10px);text-align:right}
			.eop-post-flow__contract-meta strong{display:block;color:#fff;font-size:16px;line-height:1.2}
			.eop-post-flow__contract-meta span{display:block;color:rgba(255,255,255,.72);font-size:12px;line-height:1.4}
			.eop-post-flow__heading{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;padding:28px;background:linear-gradient(135deg,<?php echo esc_attr( $experience_hero ); ?> 0%,rgba(15,27,53,.92) 55%,rgba(15,27,53,.82) 100%);color:#fff;border-color:transparent;box-shadow:0 24px 48px rgba(15,27,53,.2)}
			.eop-post-flow__heading-copy{display:grid;gap:10px;max-width:62ch}
			.eop-post-flow__eyebrow{display:block;color:rgba(255,255,255,.72);font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
			.eop-post-flow__title{margin:0;font-size:36px;line-height:1.02;letter-spacing:-.05em;color:#fff}
			.eop-post-flow__heading-note{margin:0;color:rgba(255,255,255,.82);font-size:15px;line-height:1.6}
			.eop-post-flow__badge{display:inline-flex;align-items:center;min-height:40px;padding:0 14px;border-radius:999px;background:rgba(255,255,255,.12);color:#fff;font-size:11px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;white-space:nowrap;border:1px solid rgba(255,255,255,.16)}
			.eop-post-flow__stage-intro{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;padding:18px 20px;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(247,249,252,.98))}
			.eop-post-flow--stage-contract .eop-post-flow__stage-intro{padding:0 0 6px;background:transparent}
			.eop-post-flow__stage-copy{display:grid;gap:10px}
			.eop-post-flow__text{margin:0;color:<?php echo esc_attr( $experience_text ); ?>;font-size:24px;line-height:1.08;letter-spacing:-.04em;font-weight:800}
			.eop-post-flow__helper{margin:0;max-width:54ch;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:14px;line-height:1.55}
			.eop-post-flow__stage-pills{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px;max-width:280px}
			.eop-post-flow__stage-pill{display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:0 12px;border-radius:999px;background:rgba(215,138,47,.12);color:<?php echo esc_attr( $experience_accent ); ?>;font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;text-align:center}
			.eop-post-flow__stage-pill.is-muted{background:#f5f7fb;color:<?php echo esc_attr( $experience_text ); ?>}
			.eop-post-flow__contract,.eop-post-flow__acceptance-card,.eop-post-flow__panel{padding:20px}
			.eop-post-flow__contract{line-height:1.75;color:<?php echo esc_attr( $experience_text ); ?>}
			.eop-post-flow__contract h1,.eop-post-flow__contract h2,.eop-post-flow__contract h3{margin:0 0 12px;font-size:20px;line-height:1.15;letter-spacing:-.03em}
			.eop-post-flow__contract p{margin:0 0 12px}
			.eop-post-flow__contract p:last-child{margin-bottom:0}
			.eop-post-flow__document-reader{display:grid;gap:14px;padding:18px;border:1px solid rgba(15,27,53,.08);border-radius:28px;background:<?php echo esc_attr( $experience_panel ); ?>;box-shadow:0 16px 36px rgba(15,27,53,.06)}
			.eop-post-flow__document-reader-head{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:start}
			.eop-post-flow__document-reader-head strong{display:block;font-size:19px;line-height:1.16;letter-spacing:-.03em;color:<?php echo esc_attr( $experience_text ); ?>}
			.eop-post-flow__document-reader-head small{display:block;margin-top:8px;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:14px;line-height:1.55}
			.eop-post-flow__document-reader-frame{overflow:hidden;min-height:clamp(340px,58vh,640px);border:1px solid rgba(15,27,53,.1);border-radius:22px;background:#fff}
			.eop-post-flow__document-reader-frame iframe{display:block;width:100%;min-height:clamp(340px,58vh,640px);border:0;background:#fff}
			.eop-post-flow--stage-contract .eop-post-flow__document-reader{gap:16px;padding:0}
			.eop-post-flow--stage-contract .eop-post-flow__document-reader-head{padding:0 2px}
			.eop-post-flow--stage-contract .eop-post-flow__document-reader-frame{border-radius:30px}
			.eop-post-flow__document-reader-actions{justify-content:flex-end}
			.eop-post-flow__documents-grid,.eop-post-flow__products-grid,.eop-post-flow__completion-grid,.eop-post-flow__stats-grid,.eop-post-flow__progress-list{display:grid;gap:12px}
			.eop-post-flow__documents-grid{grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
			.eop-post-flow__actions{display:flex;flex-wrap:wrap;gap:12px}
			.eop-post-flow__actions .eop-proposal-button{flex:1 1 180px}
			.eop-post-flow__upload-card{display:grid;gap:8px;padding:16px;background:<?php echo esc_attr( $experience_side ); ?>}
			.eop-post-flow__upload-card strong{font-size:18px;line-height:1.14;letter-spacing:-.03em}
			.eop-post-flow__upload-card small{color:<?php echo esc_attr( $experience_muted ); ?>;font-size:13px;line-height:1.55}
			.eop-post-flow__acceptance-card{display:grid;gap:14px;background:linear-gradient(180deg,<?php echo esc_attr( $experience_side ); ?>,rgba(255,255,255,.98))}
			.eop-post-flow--stage-contract .eop-post-flow__acceptance-card{padding-top:20px;border-top:1px solid rgba(15,27,53,.08)}
			.eop-post-flow__acceptance-head{display:grid;gap:6px}
			.eop-post-flow__acceptance-head strong{font-size:22px;line-height:1.05;letter-spacing:-.04em}
			.eop-post-flow__acceptance-head small{color:<?php echo esc_attr( $experience_muted ); ?>;font-size:14px;line-height:1.55}
			.eop-post-flow__form{display:grid;gap:14px}
			.eop-post-flow__field{display:grid;gap:8px}
			.eop-post-flow__field span{color:<?php echo esc_attr( $experience_muted ); ?>;font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
			.eop-post-flow__field input,.eop-post-flow__field textarea{width:100%;min-height:54px;padding:14px 16px;border:1px solid rgba(15,27,53,.1);border-radius:18px;background:#fff;color:<?php echo esc_attr( $experience_text ); ?>;font:inherit;transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}
			.eop-post-flow__field input:focus,.eop-post-flow__field textarea:focus{outline:none;border-color:<?php echo esc_attr( $experience_accent ); ?>;box-shadow:0 0 0 5px rgba(215,138,47,.12);transform:translateY(-1px)}
			.eop-post-flow__checkbox{display:flex;gap:12px;align-items:flex-start;padding:14px 16px;border-radius:18px;background:<?php echo esc_attr( $experience_side ); ?>;border:1px solid rgba(15,27,53,.08);font-weight:700;line-height:1.45}
			.eop-post-flow__checkbox input{margin-top:4px;accent-color:<?php echo esc_attr( $experience_accent ); ?>;transform:scale(1.08)}
			.eop-post-flow__form--acceptance .eop-proposal-button{width:100%;min-height:54px;font-size:15px}
			.eop-post-flow__panel{background:<?php echo esc_attr( $experience_side ); ?>}
			.eop-post-flow--stage-contract .eop-post-flow__panel{padding:8px 0 0 28px;border-left:1px solid rgba(15,27,53,.08)}
			.eop-post-flow__panel-label{display:block;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
			.eop-post-flow__panel-note{margin:8px 0 0;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:13px;line-height:1.55}
			.eop-post-flow__progress-item{display:grid;grid-template-columns:44px minmax(0,1fr);gap:12px;align-items:start;padding:14px;border-radius:18px;background:rgba(255,255,255,.88);border:1px solid transparent}
			.eop-post-flow__progress-item strong{display:block;font-size:14px;line-height:1.3}
			.eop-post-flow__progress-item small{display:block;margin-top:4px;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:12px;line-height:1.45}
			.eop-post-flow__progress-item.is-current{background:rgba(215,138,47,.12);border-color:rgba(215,138,47,.2);box-shadow:0 10px 22px rgba(215,138,47,.08)}
			.eop-post-flow__progress-item.is-completed{background:#f3fbf4}
			.eop-post-flow__progress-index{display:flex;align-items:center;justify-content:center;min-height:44px;border-radius:15px;background:#e9edf5;color:<?php echo esc_attr( $experience_text ); ?>;font-size:14px;font-weight:900}
			.eop-post-flow__progress-item.is-completed .eop-post-flow__progress-index{background:#dff3e3;color:#227547}
			.eop-post-flow__progress-item.is-current .eop-post-flow__progress-index{background:<?php echo esc_attr( $experience_accent ); ?>;color:#fff}
			.eop-post-flow__stat-card{padding:14px 16px;border-radius:18px;background:rgba(255,255,255,.92);border:1px solid rgba(15,27,53,.08)}
			.eop-post-flow__stat-card span{display:block;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
			.eop-post-flow__stat-card strong{display:block;margin-top:7px;font-size:24px;line-height:1;letter-spacing:-.05em;color:<?php echo esc_attr( $experience_text ); ?>}
			.eop-post-flow__stat-card small{display:block;margin-top:6px;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:12px;line-height:1.45}
			.eop-post-flow__summary-hero{display:grid;gap:4px;padding:16px 18px;border-radius:22px;background:linear-gradient(135deg,<?php echo esc_attr( $experience_hero ); ?> 0%,rgba(15,27,53,.92) 100%);color:#fff;box-shadow:0 18px 40px rgba(15,27,53,.16)}
			.eop-post-flow__summary-hero span{display:block;color:rgba(255,255,255,.7);font-size:10px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
			.eop-post-flow__summary-hero strong{display:block;font-size:32px;line-height:1;letter-spacing:-.05em;color:#fff}
			.eop-post-flow__summary-hero p{margin:0;color:rgba(255,255,255,.82);font-size:12px;line-height:1.45}
			.eop-post-flow__summary-totals{display:grid;gap:8px}
			.eop-post-flow__summary-total-row{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid rgba(15,27,53,.08);color:<?php echo esc_attr( $experience_text ); ?>;align-items:flex-start}
			.eop-post-flow__summary-total-row:last-child{border-bottom:none;padding-bottom:0}
			.eop-post-flow__summary-total-row span:first-child{color:<?php echo esc_attr( $experience_muted ); ?>;font-size:13px;font-weight:700}
			.eop-post-flow__summary-total-row span:last-child{font-weight:800}
			.eop-post-flow__summary-value{display:grid;justify-items:end;gap:2px;text-align:right}
			.eop-post-flow__summary-value strong{font-size:16px;line-height:1.1;color:<?php echo esc_attr( $experience_text ); ?>}
			.eop-post-flow__summary-value small{font-size:12px;line-height:1.25;color:<?php echo esc_attr( $experience_muted ); ?>}
			.eop-post-flow__summary-note{margin:0;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:12px;line-height:1.5}
			.eop-post-flow__summary-actions{display:grid;gap:10px}
			.eop-post-flow__summary-actions .eop-proposal-button{width:100%}
			@media (max-width: 960px){.eop-post-flow__layout{grid-template-columns:1fr}.eop-post-flow__sidebar{position:static}.eop-post-flow__stage-intro,.eop-post-flow__document-reader-head{grid-template-columns:1fr}.eop-post-flow__stage-pills{justify-content:flex-start;max-width:none}.eop-post-flow__summary-actions{grid-template-columns:1fr}.eop-post-flow__contract-header{grid-template-columns:1fr}.eop-post-flow__contract-meta{text-align:left}.eop-post-flow--stage-contract .eop-post-flow__panel{padding:18px 0 0;border-left:none;border-top:1px solid rgba(15,27,53,.08)}}
			@media (max-width: 720px){.eop-post-flow{padding:16px;border-radius:24px}.eop-post-flow--stage-contract{padding:14px}.eop-post-flow--stage-contract:before{height:168px;border-radius:22px}.eop-post-flow__heading,.eop-post-flow__stage-intro,.eop-post-flow__contract,.eop-post-flow__acceptance-card,.eop-post-flow__panel,.eop-post-flow__document-reader{padding:16px}.eop-post-flow--stage-contract .eop-post-flow__stage-intro,.eop-post-flow--stage-contract .eop-post-flow__document-reader,.eop-post-flow--stage-contract .eop-post-flow__panel{padding:0}.eop-post-flow__heading{flex-direction:column}.eop-post-flow__title,.eop-post-flow__contract-hero-title{font-size:28px}.eop-post-flow__text{font-size:22px}.eop-post-flow__actions{display:grid;grid-template-columns:1fr 1fr}.eop-post-flow__actions .eop-proposal-button{min-width:0;flex:auto;padding:0 12px;font-size:14px}.eop-post-flow__summary-total-row{gap:10px}.eop-post-flow__summary-total-row span:first-child{font-size:12px}.eop-post-flow__summary-value strong{font-size:15px}.eop-post-flow__contract-brand{min-width:0;min-height:72px;padding:12px 14px}.eop-post-flow__contract-header{padding:4px 4px 20px}.eop-post-flow--stage-contract .eop-post-flow__layout{padding:0 4px 4px}.eop-post-flow__document-reader-actions{justify-content:stretch}}
		</style>
		<div class="eop-post-flow eop-post-flow--stage-<?php echo esc_attr( $stage ); ?>">
			<?php if ( $is_contract_stage ) : ?>
				<div class="eop-post-flow__contract-header">
					<div class="eop-post-flow__contract-brand">
						<?php if ( '' !== $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>">
						<?php else : ?>
							<span class="eop-post-flow__contract-brand-fallback"><?php echo esc_html( strtoupper( substr( $brand_name, 0, 1 ) ) ); ?></span>
						<?php endif; ?>
					</div>
					<div class="eop-post-flow__contract-hero-copy">
						<span class="eop-post-flow__contract-eyebrow"><?php esc_html_e( 'Contrato digital', EOP_TEXT_DOMAIN ); ?></span>
						<h2 class="eop-post-flow__contract-hero-title"><?php echo esc_html( $title ); ?></h2>
						<p class="eop-post-flow__contract-hero-text"><?php esc_html_e( 'Tudo acontece dentro da mesma tela: leitura, aceite e continuidade do pedido sem blocos separados.', EOP_TEXT_DOMAIN ); ?></p>
					</div>
					<div class="eop-post-flow__contract-meta">
						<strong><?php echo esc_html( $brand_name ); ?></strong>
						<span><?php echo esc_html( sprintf( __( 'Pedido #%d', EOP_TEXT_DOMAIN ), $order->get_id() ) ); ?></span>
					</div>
				</div>
			<?php endif; ?>
			<div class="eop-post-flow__layout">
				<div class="eop-post-flow__main">
					<?php if ( ! $is_contract_stage ) : ?>
						<div class="eop-post-flow__heading">
							<div class="eop-post-flow__heading-copy">
								<span class="eop-post-flow__eyebrow"><?php esc_html_e( 'Etapa complementar do pedido', EOP_TEXT_DOMAIN ); ?></span>
								<h2 class="eop-post-flow__title"><?php echo esc_html( $title ); ?></h2>
								<p class="eop-post-flow__heading-note"><?php echo esc_html( $heading_note ); ?></p>
							</div>
							<span class="eop-post-flow__badge"><?php echo esc_html( strtoupper( self::get_stage_label( $stage ) ) ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( $notice ) : ?>
						<div class="eop-notice <?php echo 'error' === $notice['type'] ? 'eop-notice-error' : 'eop-notice-success'; ?>">
							<?php echo esc_html( $notice['message'] ); ?>
						</div>
					<?php endif; ?>

					<?php if ( 'payment' === $stage ) : ?>
						<p class="eop-post-flow__text"><?php esc_html_e( 'Finalize o pagamento para liberar o contrato, o envio do anexo e a personalizacao dos produtos.', EOP_TEXT_DOMAIN ); ?></p>
						<div class="eop-post-flow__actions">
							<a class="eop-proposal-button" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php echo esc_html( EOP_Settings::get( 'proposal_pay_button_label', __( 'Ir para pagamento', EOP_TEXT_DOMAIN ) ) ); ?></a>
							<a class="eop-proposal-button eop-proposal-button--secondary" href="<?php echo esc_url( EOP_Public_Proposal::get_public_link( $order ) ); ?>"><?php esc_html_e( 'Voltar para este fluxo depois do pagamento', EOP_TEXT_DOMAIN ); ?></a>
							<?php if ( $pdf_url ) : ?>
								<a class="eop-proposal-button eop-proposal-button--secondary" href="<?php echo esc_url( $pdf_url ); ?>" download="<?php echo esc_attr( $order->get_id() . '.pdf' ); ?>"><?php esc_html_e( 'Baixar PDF da proposta', EOP_TEXT_DOMAIN ); ?></a>
							<?php endif; ?>
						</div>
					<?php elseif ( 'contract' === $stage ) : ?>
						<?php self::render_contract_form( $order, $token, $settings, $state ); ?>
					<?php elseif ( 'upload' === $stage ) : ?>
						<?php self::render_upload_form( $token, $settings, $state ); ?>
					<?php elseif ( 'products' === $stage ) : ?>
						<?php self::render_products_form( $token, $settings, $state, $line_items ); ?>
					<?php else : ?>
						<?php self::render_completion_panel( $order, $settings, $state, $line_items, $pdf_url ); ?>
					<?php endif; ?>
				</div>
				<aside class="eop-post-flow__sidebar">
					<?php if ( $is_contract_stage ) : ?>
						<?php self::render_contract_summary_panel( $order, $total_rows, $pdf_url ); ?>
					<?php else : ?>
						<?php self::render_progress_panel( $steps, $stage ); ?>
						<?php self::render_status_cards_panel( $stats ); ?>
					<?php endif; ?>
				</aside>
			</div>
		</div>
		<?php
		$markup = ob_get_clean();

		return $markup;
	}

	public static function handle_request() {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		$action = isset( $_POST['eop_post_confirmation_action'] ) ? sanitize_key( wp_unslash( $_POST['eop_post_confirmation_action'] ) ) : '';
		$token  = isset( $_POST['eop_proposal_token'] ) ? sanitize_text_field( wp_unslash( $_POST['eop_proposal_token'] ) ) : '';

		if ( '' === $action || '' === $token ) {
			return;
		}

		$order = EOP_Public_Proposal::get_order_by_token( $token );

		if ( ! $order instanceof WC_Order || ! self::is_enabled_for_order( $order ) ) {
			return;
		}

		$nonce_field = isset( $_POST['eop_post_confirmation_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['eop_post_confirmation_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce_field, 'eop_post_confirmation_' . $action ) ) {
			self::redirect_with_notice( $order, 'invalid_request' );
		}

		$current_stage = self::get_current_stage( $order );
		$allowed_map   = array(
			'contract'  => 'contract',
			'upload'    => 'upload',
			'products'  => 'products',
		);

		if ( ! isset( $allowed_map[ $action ] ) || $allowed_map[ $action ] !== $current_stage ) {
			self::redirect_with_notice( $order, 'invalid_request' );
		}

		$notice = 'invalid_request';

		switch ( $action ) {
			case 'contract':
				$notice = self::process_contract_submission( $order );
				break;
			case 'upload':
				$notice = self::process_upload_submission( $order );
				break;
			case 'products':
				$notice = self::process_products_submission( $order );
				break;
		}

		self::redirect_with_notice( $order, $notice );
	}

	public static function render_thankyou_continue( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order || ! self::is_enabled_for_order( $order ) ) {
			return;
		}

		$stage = self::get_current_stage( $order );

		if ( in_array( $stage, array( 'inactive', 'awaiting_confirmation', 'payment', 'completed' ), true ) ) {
			return;
		}

		$link = EOP_Public_Proposal::get_public_link( $order );

		if ( '' === $link ) {
			return;
		}
		?>
		<section class="woocommerce-order eop-post-flow-thankyou">
			<h2><?php esc_html_e( 'Continue a etapa complementar do pedido', EOP_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'Seu pagamento foi identificado. Use o mesmo link publico para concluir contrato, envio de anexo e personalizacao dos produtos.', EOP_TEXT_DOMAIN ); ?></p>
			<p><a class="button" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Continuar agora', EOP_TEXT_DOMAIN ); ?></a></p>
		</section>
		<?php
	}

	public static function filter_checkout_order_received_url( $order_received_url, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $order_received_url;
		}

		$redirect_url = self::get_public_return_url( $order );

		return '' !== $redirect_url ? $redirect_url : $order_received_url;
	}

	public static function filter_gateway_return_url( $return_url, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $return_url;
		}

		$redirect_url = self::get_public_return_url( $order );

		return '' !== $redirect_url ? $redirect_url : $return_url;
	}

	public static function register_meta_boxes() {
		add_meta_box(
			'eop-post-confirmation-flow',
			__( 'Fluxo complementar do cliente', EOP_TEXT_DOMAIN ),
			array( __CLASS__, 'render_admin_meta_box' ),
			'shop_order',
			'normal',
			'default'
		);
	}

	public static function register_hpos_meta_boxes() {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'woocommerce_page_wc-orders';

		add_meta_box(
			'eop-post-confirmation-flow',
			__( 'Fluxo complementar do cliente', EOP_TEXT_DOMAIN ),
			array( __CLASS__, 'render_admin_meta_box' ),
			$screen,
			'normal',
			'default'
		);
	}

	public static function render_admin_meta_box( $post_or_order_object ) {
		$order = $post_or_order_object instanceof WC_Order ? $post_or_order_object : wc_get_order( is_object( $post_or_order_object ) && isset( $post_or_order_object->ID ) ? $post_or_order_object->ID : 0 );

		if ( ! $order instanceof WC_Order || ! self::is_enabled_for_order( $order ) ) {
			echo '<p>' . esc_html__( 'O fluxo complementar esta desativado para este pedido.', EOP_TEXT_DOMAIN ) . '</p>';
			return;
		}

		$state       = self::get_state( $order );
		$stage       = self::get_current_stage( $order, $state );
		$order_data  = self::get_order_data_rows( $order );
		$signature_documents = self::get_signature_documents( $order, $state, false );
		$attachment  = absint( $state['attachment']['id'] ?? 0 );
		$custom_rows = self::get_item_customizations( $order, $state );
		?>
		<div class="eop-post-flow-admin">
			<p><strong><?php esc_html_e( 'Etapa atual', EOP_TEXT_DOMAIN ); ?>:</strong> <?php echo esc_html( self::get_stage_label( $stage ) ); ?></p>
			<p><strong><?php esc_html_e( 'Aceite contratual', EOP_TEXT_DOMAIN ); ?>:</strong> <?php echo ! empty( $state['contract']['accepted'] ) ? esc_html( $state['contract']['accepted_name'] . ' - ' . $state['contract']['accepted_at'] ) : esc_html__( 'Pendente', EOP_TEXT_DOMAIN ); ?></p>
			<p><strong><?php esc_html_e( 'Dados do pedido preenchidos', EOP_TEXT_DOMAIN ); ?>:</strong> <?php echo esc_html( count( array_filter( wp_list_pluck( $order_data, 'filled' ) ) ) . '/' . count( $order_data ) ); ?></p>
			<?php if ( ! empty( $signature_documents ) ) : ?>
				<p><strong><?php esc_html_e( 'Documentos para assinatura', EOP_TEXT_DOMAIN ); ?>:</strong></p>
				<ul>
					<?php foreach ( $signature_documents as $document ) : ?>
						<li>
							<a target="_blank" href="<?php echo esc_url( $document['admin_view_url'] ); ?>"><?php echo esc_html( $document['title'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p><strong><?php esc_html_e( 'Anexo enviado', EOP_TEXT_DOMAIN ); ?>:</strong>
				<?php if ( $attachment ) : ?>
					<a target="_blank" href="<?php echo esc_url( wp_get_attachment_url( $attachment ) ); ?>"><?php echo esc_html( get_the_title( $attachment ) ); ?></a>
				<?php else : ?>
					<?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?>
				<?php endif; ?>
			</p>
				<p><strong><?php esc_html_e( 'PDF complementar', EOP_TEXT_DOMAIN ); ?>:</strong> <a href="<?php echo esc_url( self::get_pdf_url( $order, true, false ) ); ?>"><?php esc_html_e( 'Baixar PDF', EOP_TEXT_DOMAIN ); ?></a></p>

			<?php if ( ! empty( $custom_rows ) ) : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Produto original', EOP_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Nome desejado', EOP_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Status', EOP_TEXT_DOMAIN ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $custom_rows as $row ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $row['original_name'] ); ?></strong>
									<?php if ( '' !== $row['sku'] ) : ?>
										<br /><small><?php echo esc_html( sprintf( __( 'SKU: %s', EOP_TEXT_DOMAIN ), $row['sku'] ) ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $row['custom_name'] ? $row['custom_name'] : '—' ); ?></td>
								<td><?php echo esc_html( $row['locked'] ? __( 'Bloqueado', EOP_TEXT_DOMAIN ) : __( 'Editavel', EOP_TEXT_DOMAIN ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function get_stage_title( $stage, $settings ) {
		switch ( $stage ) {
			case 'contract':
				return (string) $settings['post_confirmation_contract_title'];
			case 'upload':
				return (string) $settings['post_confirmation_upload_title'];
			case 'products':
				return (string) $settings['post_confirmation_products_title'];
			case 'completed':
				return (string) $settings['post_confirmation_completion_title'];
			default:
				return self::get_stage_label( $stage );
		}
	}

	private static function render_progress_panel( $steps, $current_stage = '' ) {
		if ( empty( $steps ) ) {
			return;
		}

		$settings    = EOP_Settings::get_all();
		$panel_label = trim( (string) ( $settings['customer_experience_progress_label'] ?? '' ) );
		$panel_note  = trim( (string) ( $settings['customer_experience_progress_note'] ?? '' ) );

		if ( '' === $panel_label ) {
			$panel_label = 'contract' === $current_stage ? __( 'Contrato e proximas etapas', EOP_TEXT_DOMAIN ) : __( 'Progresso da jornada', EOP_TEXT_DOMAIN );
		}

		if ( '' === $panel_note && 'contract' === $current_stage ) {
			$panel_note = __( 'Depois do aceite, o restante do fluxo aparece em sequencia para o cliente.', EOP_TEXT_DOMAIN );
		}
		?>
		<div class="eop-post-flow__panel eop-post-flow__panel--progress">
			<span class="eop-post-flow__panel-label"><?php echo esc_html( $panel_label ); ?></span>
			<?php if ( '' !== $panel_note ) : ?>
				<p class="eop-post-flow__panel-note"><?php echo esc_html( $panel_note ); ?></p>
			<?php endif; ?>
			<div class="eop-post-flow__progress-list">
				<?php foreach ( $steps as $index => $step ) : ?>
					<div class="eop-post-flow__progress-item is-<?php echo esc_attr( $step['status'] ); ?>">
						<span class="eop-post-flow__progress-index"><?php echo esc_html( $index + 1 ); ?></span>
						<div>
							<strong><?php echo esc_html( $step['label'] ); ?></strong>
							<small><?php echo esc_html( $step['description'] ); ?></small>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function render_status_cards_panel( $stats ) {
		if ( empty( $stats ) ) {
			return;
		}
		?>
		<div class="eop-post-flow__panel eop-post-flow__panel--stats">
			<span class="eop-post-flow__panel-label"><?php esc_html_e( 'Resumo em tempo real', EOP_TEXT_DOMAIN ); ?></span>
			<div class="eop-post-flow__stats-grid">
				<?php foreach ( $stats as $stat ) : ?>
					<div class="eop-post-flow__stat-card">
						<span><?php echo esc_html( $stat['label'] ); ?></span>
						<strong><?php echo esc_html( $stat['value'] ); ?></strong>
						<small><?php echo esc_html( $stat['description'] ); ?></small>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function render_contract_summary_panel( WC_Order $order, $total_rows, $pdf_url ) {
		$total_rows = is_array( $total_rows ) ? $total_rows : array();
		$summary_rows = array_values(
			array_filter(
				$total_rows,
				function ( $row ) {
					return empty( $row['class'] ) || 'is-grand' !== $row['class'];
				}
			)
		);
		$summary_rows = self::prepare_contract_summary_rows( $order, $summary_rows );
		?>
		<div class="eop-post-flow__panel eop-post-flow__panel--summary">
			<span class="eop-post-flow__panel-label"><?php esc_html_e( 'Resumo', EOP_TEXT_DOMAIN ); ?></span>
            <div class="eop-post-flow__summary-totals">
                <div class="eop-post-flow__summary-total-row">
					<span><?php esc_html_e( 'Valor aprovado', EOP_TEXT_DOMAIN ); ?></span>
                    <strong><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></strong>
                </div>
                <?php if ( ! empty( $summary_rows ) ) : ?>
                    <?php foreach ( $summary_rows as $row ) : ?>
                        <div class="eop-post-flow__summary-total-row">
                            <span><?php echo esc_html( $row['label'] ); ?></span>
                            <?php if ( ! empty( $row['main_value'] ) ) : ?>
                                <div class="eop-post-flow__summary-value">
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
                <?php endif; ?>
            </div>
			<p class="eop-post-flow__summary-note"><?php esc_html_e( 'Depois do aceite, as proximas etapas sao liberadas automaticamente.', EOP_TEXT_DOMAIN ); ?></p>
			<?php if ( $pdf_url ) : ?>
				<div class="eop-post-flow__summary-actions">
					<a class="eop-proposal-button eop-proposal-button--secondary" href="<?php echo esc_url( $pdf_url ); ?>" download="<?php echo esc_attr( $order->get_id() . '.pdf' ); ?>"><?php esc_html_e( 'Baixar proposta', EOP_TEXT_DOMAIN ); ?></a>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_contract_form( WC_Order $order, $token, $settings, $state ) {
		$contract_text = self::get_contract_text( $order, $state );
		$signature_documents = self::get_signature_documents( $order, $state, true );
		$default_name  = self::get_prefilled_contract_name( $order, $state );
		$primary_document = ! empty( $signature_documents ) ? $signature_documents[0] : array();
		$secondary_documents = count( $signature_documents ) > 1 ? array_slice( $signature_documents, 1 ) : array();

		?>
		<div class="eop-post-flow__stage-intro">
			<div class="eop-post-flow__stage-copy">
				<p class="eop-post-flow__text"><?php esc_html_e( 'Leia o documento e confirme o aceite.', EOP_TEXT_DOMAIN ); ?></p>
				<p class="eop-post-flow__helper"><?php esc_html_e( 'Se o visualizador do PDF falhar no celular, use os botoes abaixo para abrir ou baixar o arquivo.', EOP_TEXT_DOMAIN ); ?></p>
			</div>
		</div>
		<?php if ( ! empty( $primary_document['public_view_url'] ) ) : ?>
			<div class="eop-post-flow__document-reader">
				<div class="eop-post-flow__document-reader-head">
					<div>
						<strong><?php echo esc_html( $primary_document['title'] ); ?></strong>
						<?php if ( ! empty( $primary_document['description'] ) ) : ?>
							<small><?php echo esc_html( $primary_document['description'] ); ?></small>
						<?php endif; ?>
					</div>
					<div class="eop-post-flow__actions eop-post-flow__document-reader-actions">
						<a class="eop-proposal-button eop-proposal-button--secondary" target="_blank" rel="noopener" href="<?php echo esc_url( $primary_document['public_view_url'] ); ?>"><?php echo esc_html( $primary_document['view_label'] ); ?></a>
						<a class="eop-proposal-button eop-proposal-button--secondary" href="<?php echo esc_url( $primary_document['public_download_url'] ); ?>"><?php echo esc_html( $primary_document['button_label'] ); ?></a>
					</div>
				</div>
				<div class="eop-post-flow__document-reader-frame">
					<iframe src="<?php echo esc_url( $primary_document['public_view_url'] ); ?>#toolbar=1&navpanes=0&scrollbar=1" title="<?php echo esc_attr( $primary_document['title'] ); ?>"></iframe>
				</div>
			</div>
		<?php elseif ( '' !== trim( wp_strip_all_tags( $contract_text ) ) ) : ?>
			<div class="eop-post-flow__contract"><?php echo wp_kses_post( wpautop( $contract_text ) ); ?></div>
		<?php endif; ?>
		<?php if ( ! empty( $secondary_documents ) ) : ?>
			<div class="eop-post-flow__documents-grid">
				<?php foreach ( $secondary_documents as $document ) : ?>
					<div class="eop-post-flow__upload-card">
						<strong><?php echo esc_html( $document['title'] ); ?></strong>
						<small><?php echo esc_html( $document['description'] ? $document['description'] : __( 'Documento gerado e salvo no pedido para leitura e assinatura externa.', EOP_TEXT_DOMAIN ) ); ?></small>
						<div class="eop-post-flow__actions">
							<a class="eop-proposal-button eop-proposal-button--secondary" target="_blank" rel="noopener" href="<?php echo esc_url( $document['public_view_url'] ); ?>"><?php echo esc_html( $document['view_label'] ); ?></a>
							<a class="eop-proposal-button eop-proposal-button--secondary" href="<?php echo esc_url( $document['public_download_url'] ); ?>"><?php echo esc_html( $document['button_label'] ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="eop-post-flow__acceptance-card">
			<form method="post" class="eop-post-flow__form eop-post-flow__form--acceptance">
				<?php wp_nonce_field( 'eop_post_confirmation_contract', 'eop_post_confirmation_nonce' ); ?>
				<input type="hidden" name="eop_post_confirmation_action" value="contract" />
				<input type="hidden" name="eop_proposal_token" value="<?php echo esc_attr( $token ); ?>" />
				<label class="eop-post-flow__checkbox">
					<input type="checkbox" name="eop_contract_accept" value="yes" required />
					<span><?php echo esc_html( $settings['post_confirmation_contract_checkbox_label'] ); ?></span>
				</label>
				<button type="submit" class="eop-proposal-button"><?php echo esc_html( $settings['post_confirmation_contract_button_label'] ); ?></button>
			</form>
		</div>
		<?php
	}

	private static function prepare_contract_summary_rows( WC_Order $order, $rows ) {
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

	private static function get_prefilled_contract_name( WC_Order $order, $state = null ) {
		$state = is_array( $state ) ? $state : self::get_state( $order );
		$candidates = array(
			trim( (string) ( $state['contract']['accepted_name'] ?? '' ) ),
			trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			trim( (string) $order->get_billing_company() ),
			trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ),
			trim( (string) $order->get_shipping_company() ),
			trim( (string) self::get_order_meta_value( $order, array( '_nome_responsavel', 'nome_responsavel', '_responsavel', 'responsavel', '_representante', 'representante', '_nome_socio', 'nome_socio', '_socio', 'socio', '_company_contact', 'company_contact', '_billing_company_contact', 'billing_company_contact' ) ) ),
		);

		foreach ( $candidates as $candidate ) {
			$candidate = trim( wp_strip_all_tags( (string) $candidate ) );

			if ( '' !== $candidate ) {
				return $candidate;
			}
		}

		return '';
	}

	private static function render_upload_form( $token, $settings, $state ) {
		$attachment_id = absint( $state['attachment']['id'] ?? 0 );
		$filename      = $attachment_id ? get_the_title( $attachment_id ) : '';
		$uploaded_at   = (string) ( $state['attachment']['uploaded_at'] ?? '' );
		?>
		<p class="eop-post-flow__text"><?php echo esc_html( $settings['post_confirmation_upload_description'] ); ?></p>
		<?php if ( $attachment_id ) : ?>
			<div class="eop-post-flow__upload-card">
				<strong><?php echo esc_html( $filename ); ?></strong>
				<small><?php echo esc_html( $uploaded_at ? sprintf( __( 'Arquivo atual enviado em %s.', EOP_TEXT_DOMAIN ), $uploaded_at ) : __( 'Arquivo atual ja registrado no pedido.', EOP_TEXT_DOMAIN ) ); ?></small>
			</div>
		<?php endif; ?>
		<form method="post" enctype="multipart/form-data" class="eop-post-flow__form">
			<?php wp_nonce_field( 'eop_post_confirmation_upload', 'eop_post_confirmation_nonce' ); ?>
			<input type="hidden" name="eop_post_confirmation_action" value="upload" />
			<input type="hidden" name="eop_proposal_token" value="<?php echo esc_attr( $token ); ?>" />
			<label class="eop-post-flow__field">
				<span><?php echo esc_html( $settings['post_confirmation_upload_field_label'] ); ?></span>
				<input type="file" name="eop_post_confirmation_attachment" accept=".jpg,.jpeg,.png,.pdf" required />
			</label>
			<?php if ( $attachment_id ) : ?>
				<p class="eop-post-flow__text"><a target="_blank" href="<?php echo esc_url( wp_get_attachment_url( $attachment_id ) ); ?>"><?php esc_html_e( 'Baixar anexo atual', EOP_TEXT_DOMAIN ); ?></a></p>
			<?php endif; ?>
			<button type="submit" class="eop-proposal-button"><?php echo esc_html( $settings['post_confirmation_upload_button_label'] ); ?></button>
		</form>
		<?php
	}

	private static function render_products_form( $token, $settings, $state, $line_items ) {
		$counts = self::get_product_completion_counts_from_line_items( $line_items, $state );
		?>
		<p class="eop-post-flow__text"><?php echo esc_html( $settings['post_confirmation_products_description'] ); ?></p>
		<p class="eop-post-flow__helper"><?php echo esc_html( sprintf( __( '%1$d itens editaveis, %2$d bloqueados e %3$d personalizados ate agora.', EOP_TEXT_DOMAIN ), $counts['editable'], $counts['locked'], $counts['completed'] ) ); ?></p>
		<form method="post" class="eop-post-flow__form eop-post-flow__form--products">
			<?php wp_nonce_field( 'eop_post_confirmation_products', 'eop_post_confirmation_nonce' ); ?>
			<input type="hidden" name="eop_post_confirmation_action" value="products" />
			<input type="hidden" name="eop_proposal_token" value="<?php echo esc_attr( $token ); ?>" />
			<div class="eop-post-flow__products-grid">
				<?php foreach ( $line_items as $line_item ) : ?>
					<?php
					$item      = $line_item['item'];
					$product   = $line_item['product'];
					$item_id   = $item->get_id();
					$locked    = self::is_product_locked( $product );
					$image_url = $product ? wp_get_attachment_image_url( $product->get_image_id(), 'medium' ) : '';
					$sku       = $product ? (string) $product->get_sku() : '';
					$value     = self::get_item_custom_name( $item, $state );

					if ( ! $image_url ) {
						$image_url = wc_placeholder_img_src( 'medium' );
					}
					?>
					<div class="eop-post-flow__product-card<?php echo $locked ? ' is-locked' : ''; ?>">
						<div class="eop-post-flow__product-media">
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $item->get_name() ); ?>" />
						</div>
						<div class="eop-post-flow__product-copy">
							<strong><?php echo esc_html( $item->get_name() ); ?></strong>
							<?php if ( '' !== $sku ) : ?>
								<span><?php echo esc_html( sprintf( __( 'SKU: %s', EOP_TEXT_DOMAIN ), $sku ) ); ?></span>
							<?php endif; ?>
						</div>
						<label class="eop-post-flow__field">
							<span><?php esc_html_e( 'Nome desejado', EOP_TEXT_DOMAIN ); ?></span>
							<input type="text" name="eop_product_name[<?php echo esc_attr( $item_id ); ?>]" value="<?php echo esc_attr( $value ); ?>" <?php echo $locked ? 'disabled' : 'required'; ?> />
							<?php if ( $locked ) : ?>
								<small><?php esc_html_e( 'Este produto esta bloqueado para alteracao de nome.', EOP_TEXT_DOMAIN ); ?></small>
							<?php endif; ?>
						</label>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="submit" class="eop-proposal-button"><?php echo esc_html( $settings['post_confirmation_products_button_label'] ); ?></button>
		</form>
		<?php
	}

	private static function render_completion_panel( WC_Order $order, $settings, $state, $line_items, $pdf_url ) {
		$flow_pdf_url = self::get_pdf_url( $order, true, true );
		?>
		<p class="eop-post-flow__text"><?php echo esc_html( $settings['post_confirmation_completion_description'] ); ?></p>
		<p class="eop-post-flow__helper"><?php esc_html_e( 'Seu contrato, os dados do pedido, o anexo e a personalizacao dos produtos ja foram consolidados para a equipe.', EOP_TEXT_DOMAIN ); ?></p>
		<?php if ( ! empty( $line_items ) ) : ?>
			<div class="eop-post-flow__completion-grid">
				<?php foreach ( $line_items as $line_item ) : ?>
					<?php
					$item        = $line_item['item'];
					$product     = $line_item['product'];
					$image_url   = $product ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '';
					$custom_name = self::get_item_custom_name( $item, $state );

					if ( ! $image_url ) {
						$image_url = wc_placeholder_img_src( 'thumbnail' );
					}
					?>
					<div class="eop-post-flow__completion-item">
						<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $item->get_name() ); ?>" />
						<div>
							<strong><?php echo esc_html( $item->get_name() ); ?></strong>
							<p><?php echo esc_html( $custom_name ? $custom_name : $item->get_name() ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="eop-post-flow__actions">
			<?php if ( $flow_pdf_url ) : ?>
				<a class="eop-proposal-button" href="<?php echo esc_url( $flow_pdf_url ); ?>" download="<?php echo esc_attr( self::get_pdf_filename( $order ) ); ?>"><?php esc_html_e( 'Baixar PDF complementar', EOP_TEXT_DOMAIN ); ?></a>
			<?php endif; ?>
			<?php if ( $pdf_url ) : ?>
				<a class="eop-proposal-button eop-proposal-button--secondary" href="<?php echo esc_url( $pdf_url ); ?>" download="documento-<?php echo esc_attr( time() ); ?>.pdf"><?php esc_html_e( 'Baixar PDF da proposta', EOP_TEXT_DOMAIN ); ?></a>
			<?php endif; ?>
			<a class="eop-proposal-button eop-proposal-button--secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Voltar para o site', EOP_TEXT_DOMAIN ); ?></a>
		</div>
		<?php
	}

	private static function process_contract_submission( WC_Order $order ) {
		$name   = isset( $_POST['eop_contract_name'] ) ? sanitize_text_field( wp_unslash( $_POST['eop_contract_name'] ) ) : '';
		$accept = isset( $_POST['eop_contract_accept'] ) ? sanitize_text_field( wp_unslash( $_POST['eop_contract_accept'] ) ) : '';

		if ( '' === $name || 'yes' !== $accept ) {
			return 'missing_data';
		}

		$state                       = self::get_state( $order );
		$state['contract']['accepted']      = true;
		$state['contract']['accepted_name'] = $name;
		$state['contract']['accepted_at']   = current_time( 'mysql' );
		$state['contract']['accepted_ip']   = self::get_client_ip();
		$state['contract']['contract_text'] = self::build_contract_documents_snapshot( $order );

		self::persist_state( $order, $state );
		$order->add_order_note( sprintf( __( 'Aceite contratual registrado por %s.', EOP_TEXT_DOMAIN ), $name ) );
		$order->save();

		return 'contract_saved';
	}

	private static function process_upload_submission( WC_Order $order ) {
		if ( empty( $_FILES['eop_post_confirmation_attachment']['name'] ) ) {
			return 'missing_file';
		}

		$attachment_id = self::store_upload( $_FILES['eop_post_confirmation_attachment'] );

		if ( is_wp_error( $attachment_id ) ) {
			return 'invalid_file';
		}

		$state                            = self::get_state( $order );
		$state['attachment']['id']        = $attachment_id;
		$state['attachment']['filename']  = get_the_title( $attachment_id );
		$state['attachment']['uploaded_at'] = current_time( 'mysql' );

		$stage = self::persist_state( $order, $state );

		return 'completed' === $stage ? 'flow_completed' : 'upload_saved';
	}

	private static function process_products_submission( WC_Order $order ) {
		$values = isset( $_POST['eop_product_name'] ) ? wp_unslash( $_POST['eop_product_name'] ) : array();
		$values = is_array( $values ) ? $values : array();
		$state  = self::get_state( $order );

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			/** @var WC_Order_Item_Product $item */
			$product = $item->get_product();
			$item_id = $item->get_id();
			$locked  = self::is_product_locked( $product );
			$sku     = $product ? (string) $product->get_sku() : '';

			$state['products'][ $item_id ] = array(
				'locked'        => $locked,
				'original_name' => $item->get_name(),
				'sku'           => $sku,
				'custom_name'   => $locked ? '' : '',
			);

			if ( $locked ) {
				$item->update_meta_data( '_eop_custom_name_locked', 'yes' );
				$item->update_meta_data( '_eop_original_product_snapshot', $item->get_name() );
				$item->save();
				continue;
			}

			$value = isset( $values[ $item_id ] ) ? sanitize_text_field( $values[ $item_id ] ) : '';

			if ( '' === $value ) {
				return 'missing_data';
			}

			$state['products'][ $item_id ]['custom_name'] = $value;

			$item->update_meta_data( '_eop_custom_product_name', $value );
			$item->update_meta_data( '_eop_custom_name_locked', 'no' );
			$item->update_meta_data( '_eop_original_product_snapshot', $item->get_name() );
			$item->save();
		}

		$stage = self::persist_state( $order, $state );

		return 'completed' === $stage ? 'flow_completed' : 'products_saved';
	}

	private static function persist_state( WC_Order $order, $state ) {
		$stage                  = self::get_current_stage( $order, $state );
		$state['current_stage'] = $stage;

		if ( 'completed' === $stage && empty( $state['completed_at'] ) ) {
			$state['completed_at'] = current_time( 'mysql' );
		}

		$order->update_meta_data( self::META_KEY, wp_json_encode( $state ) );
		$order->update_meta_data( self::META_FLAG, 'completed' === $stage ? 'yes' : 'no' );
		$order->update_meta_data( '_eop_post_confirmation_flow_stage', $stage );

		if ( ! empty( $state['contract']['accepted_at'] ) ) {
			$order->update_meta_data( '_eop_post_confirmation_contract_accepted_at', $state['contract']['accepted_at'] );
		}

		if ( ! empty( $state['contract']['accepted_name'] ) ) {
			$order->update_meta_data( '_eop_post_confirmation_contract_accepted_name', $state['contract']['accepted_name'] );
		}

		if ( ! empty( $state['contract']['accepted_ip'] ) ) {
			$order->update_meta_data( '_eop_post_confirmation_contract_accepted_ip', $state['contract']['accepted_ip'] );
		}

		if ( ! empty( $state['contract']['contract_text'] ) ) {
			$order->update_meta_data( '_eop_post_confirmation_contract_text', wp_kses_post( $state['contract']['contract_text'] ) );
		}

		if ( ! empty( $state['attachment']['id'] ) ) {
			$order->update_meta_data( '_eop_post_confirmation_attachment_id', absint( $state['attachment']['id'] ) );
		}

		$order->save();

		return $stage;
	}

	private static function redirect_with_notice( WC_Order $order, $notice ) {
		$redirect = add_query_arg( 'eop_flow_notice', sanitize_key( $notice ), EOP_Public_Proposal::get_public_link( $order ) );

		wp_safe_redirect( $redirect );
		exit;
	}

	private static function order_requires_payment( WC_Order $order ) {
		return 'yes' === EOP_Settings::get( 'enable_checkout_confirmation', 'no' ) && method_exists( $order, 'needs_payment' ) && $order->needs_payment();
	}

	private static function requires_attachment() {
		return 'yes' === EOP_Settings::get( 'post_confirmation_require_attachment', 'yes' );
	}

	private static function requires_product_customization( WC_Order $order ) {
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();

			if ( ! self::is_product_locked( $product ) ) {
				return true;
			}
		}

		return false;
	}

	private static function product_customization_is_complete( WC_Order $order, $state ) {
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			/** @var WC_Order_Item_Product $item */
			$product = $item->get_product();

			if ( self::is_product_locked( $product ) ) {
				continue;
			}

			if ( '' === trim( self::get_item_custom_name( $item, $state ) ) ) {
				return false;
			}
		}

		return true;
	}

	private static function get_item_custom_name( WC_Order_Item_Product $item, $state ) {
		$item_id = $item->get_id();
		$value   = (string) $item->get_meta( '_eop_custom_product_name', true );

		if ( '' !== $value ) {
			return $value;
		}

		return (string) ( $state['products'][ $item_id ]['custom_name'] ?? '' );
	}

	private static function get_item_customizations( WC_Order $order, $state ) {
		$rows = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			$rows[]  = array(
				'original_name' => (string) $item->get_meta( '_eop_original_product_snapshot', true ) ?: $item->get_name(),
				'custom_name'   => self::get_item_custom_name( $item, $state ),
				'locked'        => 'yes' === (string) $item->get_meta( '_eop_custom_name_locked', true ),
				'sku'           => $product ? (string) $product->get_sku() : '',
			);
		}

		return $rows;
	}

	private static function get_progress_steps( WC_Order $order, $state ) {
		$current_stage = self::get_current_stage( $order, $state );
		$steps         = array();

		if ( self::order_requires_payment( $order ) || 'payment' === $current_stage ) {
			$steps[] = array(
				'key'         => 'payment',
				'label'       => self::get_stage_label( 'payment' ),
				'description' => __( 'Pagamento para liberar o restante do fluxo.', EOP_TEXT_DOMAIN ),
			);
		}

		$steps[] = array(
			'key'         => 'contract',
			'label'       => self::get_stage_label( 'contract' ),
			'description' => __( 'Aceite do contrato e identificacao do cliente.', EOP_TEXT_DOMAIN ),
		);

		if ( self::requires_attachment() || 'upload' === $current_stage ) {
			$steps[] = array(
				'key'         => 'upload',
				'label'       => self::get_stage_label( 'upload' ),
				'description' => __( 'Envio do arquivo solicitado pela equipe.', EOP_TEXT_DOMAIN ),
			);
		}

		if ( self::requires_product_customization( $order ) || 'products' === $current_stage ) {
			$steps[] = array(
				'key'         => 'products',
				'label'       => self::get_stage_label( 'products' ),
				'description' => __( 'Definicao do nome desejado para os itens liberados.', EOP_TEXT_DOMAIN ),
			);
		}

		$steps[] = array(
			'key'         => 'completed',
			'label'       => self::get_stage_label( 'completed' ),
			'description' => __( 'Resumo final, documentos e proximo repasse interno.', EOP_TEXT_DOMAIN ),
		);

		$reached_current = false;

		foreach ( $steps as $index => $step ) {
			$status = 'upcoming';

			if ( 'completed' === $current_stage ) {
				$status = 'completed' === $step['key'] ? 'current' : 'completed';
			} elseif ( $step['key'] === $current_stage ) {
				$status          = 'current';
				$reached_current = true;
			} elseif ( ! $reached_current ) {
				$status = 'completed';
			}

			$steps[ $index ]['status'] = $status;
		}

		return $steps;
	}

	private static function get_status_cards( WC_Order $order, $state ) {
		$order_data_rows = self::get_order_data_rows( $order );
		$documents_total = count( $order_data_rows );
		$documents_done  = count( array_filter( wp_list_pluck( $order_data_rows, 'filled' ) ) );
		$product_counts  = self::get_product_completion_counts( $order, $state );
		$attachment_id   = absint( $state['attachment']['id'] ?? 0 );

		return array(
			array(
				'label'       => __( 'Contrato', EOP_TEXT_DOMAIN ),
				'value'       => ! empty( $state['contract']['accepted'] ) ? __( 'Registrado', EOP_TEXT_DOMAIN ) : __( 'Pendente', EOP_TEXT_DOMAIN ),
				'description' => ! empty( $state['contract']['accepted_name'] ) ? (string) $state['contract']['accepted_name'] : __( 'Aguardando aceite do cliente.', EOP_TEXT_DOMAIN ),
			),
			array(
				'label'       => __( 'Dados do pedido', EOP_TEXT_DOMAIN ),
				'value'       => $documents_total > 0 ? $documents_done . '/' . $documents_total : __( 'Nao se aplica', EOP_TEXT_DOMAIN ),
				'description' => $documents_total > 0 ? __( 'Dados aproveitados diretamente do cadastro do pedido.', EOP_TEXT_DOMAIN ) : __( 'Nenhum dado preenchido no pedido ate agora.', EOP_TEXT_DOMAIN ),
			),
			array(
				'label'       => __( 'Anexo', EOP_TEXT_DOMAIN ),
				'value'       => $attachment_id ? __( 'Enviado', EOP_TEXT_DOMAIN ) : ( self::requires_attachment() ? __( 'Pendente', EOP_TEXT_DOMAIN ) : __( 'Opcional', EOP_TEXT_DOMAIN ) ),
				'description' => $attachment_id ? (string) ( $state['attachment']['filename'] ?? __( 'Arquivo registrado.', EOP_TEXT_DOMAIN ) ) : __( 'Status do arquivo solicitado.', EOP_TEXT_DOMAIN ),
			),
			array(
				'label'       => __( 'Produtos', EOP_TEXT_DOMAIN ),
				'value'       => $product_counts['completed'] . '/' . $product_counts['editable'],
				'description' => $product_counts['locked'] > 0 ? sprintf( __( '%d item(ns) bloqueado(s) para edicao.', EOP_TEXT_DOMAIN ), $product_counts['locked'] ) : __( 'Todos os itens podem ser personalizados.', EOP_TEXT_DOMAIN ),
			),
		);
	}

	private static function get_product_completion_counts( WC_Order $order, $state ) {
		$editable = 0;
		$locked   = 0;
		$done     = 0;

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();

			if ( self::is_product_locked( $product ) ) {
				$locked++;
				continue;
			}

			$editable++;

			if ( '' !== trim( self::get_item_custom_name( $item, $state ) ) ) {
				$done++;
			}
		}

		return array(
			'editable'  => $editable,
			'locked'    => $locked,
			'completed' => $done,
		);
	}

	private static function get_product_completion_counts_from_line_items( $line_items, $state ) {
		$editable = 0;
		$locked   = 0;
		$done     = 0;

		foreach ( $line_items as $line_item ) {
			$item    = $line_item['item'];
			$product = $line_item['product'];

			if ( self::is_product_locked( $product ) ) {
				$locked++;
				continue;
			}

			$editable++;

			if ( $item instanceof WC_Order_Item_Product && '' !== trim( self::get_item_custom_name( $item, $state ) ) ) {
				$done++;
			}
		}

		return array(
			'editable'  => $editable,
			'locked'    => $locked,
			'completed' => $done,
		);
	}

	private static function has_pdf_payload( $state ) {
		return ! empty( $state['contract']['accepted'] )
			|| ! empty( array_filter( $state['documents'] ?? array() ) )
			|| ! empty( $state['attachment']['id'] )
			|| ! empty( $state['products'] );
	}

	private static function public_token_matches_order( WC_Order $order, $token ) {
		$expected = (string) $order->get_meta( '_eop_public_token', true );

		return '' !== $expected && hash_equals( $expected, (string) $token );
	}

	private static function get_rest_order( WP_REST_Request $request ) {
		$order_id = absint( $request->get_param( 'order_id' ) );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'eop_post_confirmation_rest_order_not_found', __( 'Pedido nao encontrado.', EOP_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		return $order;
	}

	private static function current_user_can_access_order( WC_Order $order ) {
		if ( ! class_exists( 'EOP_Role' ) || ! EOP_Role::is_vendedor() ) {
			return true;
		}

		$user_id    = get_current_user_id();
		$creator_id = absint( $order->get_meta( '_eop_created_by' ) );

		if ( $creator_id && $creator_id === $user_id ) {
			return true;
		}

		$post = get_post( $order->get_id() );

		return ( $post && (int) $post->post_author === $user_id );
	}

	private static function get_pdf_filename( WC_Order $order ) {
		return sanitize_file_name( 'complemento-proposta-' . $order->get_id() . '.pdf' );
	}

	private static function build_pdf_binary( WC_Order $order, $state ) {
		$html = self::get_pdf_html( $order, $state );

		if ( '' === $html ) {
			return '';
		}

		$binary = self::maybe_build_dompdf_pdf( $html );

		if ( '' !== $binary ) {
			return $binary;
		}

		return self::maybe_build_headless_pdf( $html, self::get_pdf_filename( $order ) );
	}

	private static function get_pdf_html( WC_Order $order, $state ) {
		$settings        = EOP_Settings::get_all();
		$font_css        = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $settings['font_family'] ?? '' ) : "'Segoe UI', sans-serif";
		$company_name    = trim( (string) $settings['pdf_company_name'] );
		$company_doc     = trim( (string) $settings['pdf_company_document'] );
		$company_address = trim( (string) $settings['pdf_company_address'] );
		$footer_note     = trim( (string) $settings['pdf_footer_note'] );
		$customer_name   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$customer_email  = (string) $order->get_billing_email();
		$customer_phone  = (string) $order->get_billing_phone();
		$order_data_rows = self::get_order_data_rows( $order );
		$custom_rows     = self::get_item_customizations( $order, $state );
		$attachment_id   = absint( $state['attachment']['id'] ?? 0 );
		$attachment_name = $attachment_id ? get_the_title( $attachment_id ) : '';
		$attachment_date = (string) ( $state['attachment']['uploaded_at'] ?? '' );
		$contract_text   = self::get_contract_text( $order, $state );
		$stage_label     = self::get_stage_label( self::get_current_stage( $order, $state ) );

		ob_start();
		?>
		<!doctype html>
		<html>
		<head>
			<meta charset="utf-8">
			<style>
				@page { size: A4; margin: 16mm; }
				body { margin: 0; color: #172033; background: #ffffff; font-family: <?php echo esc_html( $font_css ); ?>; font-size: 13px; line-height: 1.55; }
				* { box-sizing: border-box; }
				h1,h2,h3,p { margin: 0; }
				.eop-flow-pdf { display: grid; gap: 18px; }
				.eop-flow-pdf__hero { padding: 22px; border-radius: 20px; background: linear-gradient(145deg, #0f1d4d 0%, #172e7a 100%); color: #ffffff; }
				.eop-flow-pdf__hero strong { display: block; margin-bottom: 8px; font-size: 11px; letter-spacing: .14em; text-transform: uppercase; opacity: .76; }
				.eop-flow-pdf__hero h1 { font-size: 28px; line-height: 1.1; }
				.eop-flow-pdf__hero p { margin-top: 10px; font-size: 14px; color: rgba(255,255,255,.84); }
				.eop-flow-pdf__meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
				.eop-flow-pdf__card { padding: 16px 18px; border: 1px solid #dbe3f0; border-radius: 18px; background: #fff; }
				.eop-flow-pdf__card span { display: block; margin-bottom: 6px; color: #5b6474; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
				.eop-flow-pdf__card strong { font-size: 16px; }
				.eop-flow-pdf__section { padding: 18px; border: 1px solid #e4e9f6; border-radius: 18px; background: #fbfcff; }
				.eop-flow-pdf__section h2 { margin-bottom: 10px; font-size: 16px; }
				.eop-flow-pdf__section p + p { margin-top: 10px; }
				.eop-flow-pdf__table { width: 100%; border-collapse: collapse; }
				.eop-flow-pdf__table th, .eop-flow-pdf__table td { padding: 10px 12px; border-bottom: 1px solid #e4e9f6; text-align: left; vertical-align: top; }
				.eop-flow-pdf__table th { color: #5b6474; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; }
				.eop-flow-pdf__footer { color: #5b6474; font-size: 12px; }
			</style>
		</head>
		<body>
			<div class="eop-flow-pdf">
				<div class="eop-flow-pdf__hero">
					<strong><?php esc_html_e( 'Documento complementar do cliente', EOP_TEXT_DOMAIN ); ?></strong>
					<h1><?php echo esc_html( $company_name ?: get_bloginfo( 'name' ) ); ?></h1>
					<p><?php echo esc_html( sprintf( __( 'Pedido #%1$d | Etapa atual: %2$s', EOP_TEXT_DOMAIN ), $order->get_id(), $stage_label ) ); ?></p>
				</div>

				<div class="eop-flow-pdf__meta">
					<div class="eop-flow-pdf__card">
						<span><?php esc_html_e( 'Cliente', EOP_TEXT_DOMAIN ); ?></span>
						<strong><?php echo esc_html( $customer_name ?: __( 'Nao informado', EOP_TEXT_DOMAIN ) ); ?></strong>
						<?php if ( $customer_email ) : ?><p><?php echo esc_html( $customer_email ); ?></p><?php endif; ?>
						<?php if ( $customer_phone ) : ?><p><?php echo esc_html( $customer_phone ); ?></p><?php endif; ?>
					</div>
					<div class="eop-flow-pdf__card">
						<span><?php esc_html_e( 'Empresa', EOP_TEXT_DOMAIN ); ?></span>
						<strong><?php echo esc_html( $company_name ?: get_bloginfo( 'name' ) ); ?></strong>
						<?php if ( $company_doc ) : ?><p><?php echo esc_html( $company_doc ); ?></p><?php endif; ?>
						<?php if ( $company_address ) : ?><p><?php echo esc_html( $company_address ); ?></p><?php endif; ?>
					</div>
				</div>

				<div class="eop-flow-pdf__section">
					<h2><?php esc_html_e( 'Aceite contratual', EOP_TEXT_DOMAIN ); ?></h2>
					<p><strong><?php esc_html_e( 'Responsavel pelo aceite:', EOP_TEXT_DOMAIN ); ?></strong> <?php echo esc_html( (string) ( $state['contract']['accepted_name'] ?: __( 'Nao informado', EOP_TEXT_DOMAIN ) ) ); ?></p>
					<p><strong><?php esc_html_e( 'Registrado em:', EOP_TEXT_DOMAIN ); ?></strong> <?php echo esc_html( (string) ( $state['contract']['accepted_at'] ?: __( 'Pendente', EOP_TEXT_DOMAIN ) ) ); ?></p>
					<?php if ( '' !== trim( wp_strip_all_tags( $contract_text ) ) ) : ?>
						<div><?php echo wp_kses_post( wpautop( $contract_text ) ); ?></div>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $order_data_rows ) ) : ?>
					<div class="eop-flow-pdf__section">
						<h2><?php esc_html_e( 'Dados do e-commerce', EOP_TEXT_DOMAIN ); ?></h2>
						<table class="eop-flow-pdf__table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Campo', EOP_TEXT_DOMAIN ); ?></th>
									<th><?php esc_html_e( 'Valor', EOP_TEXT_DOMAIN ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $order_data_rows as $document ) : ?>
									<tr>
										<td><?php echo esc_html( $document['label'] ); ?></td>
										<td><?php echo nl2br( esc_html( (string) ( $document['value'] ?? '—' ) ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

				<div class="eop-flow-pdf__section">
					<h2><?php esc_html_e( 'Anexo enviado', EOP_TEXT_DOMAIN ); ?></h2>
					<p><strong><?php esc_html_e( 'Arquivo:', EOP_TEXT_DOMAIN ); ?></strong> <?php echo esc_html( $attachment_name ?: __( 'Nao enviado', EOP_TEXT_DOMAIN ) ); ?></p>
					<p><strong><?php esc_html_e( 'Registrado em:', EOP_TEXT_DOMAIN ); ?></strong> <?php echo esc_html( $attachment_date ?: __( 'Pendente', EOP_TEXT_DOMAIN ) ); ?></p>
				</div>

				<?php if ( ! empty( $custom_rows ) ) : ?>
					<div class="eop-flow-pdf__section">
						<h2><?php esc_html_e( 'Personalizacao dos produtos', EOP_TEXT_DOMAIN ); ?></h2>
						<table class="eop-flow-pdf__table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Produto original', EOP_TEXT_DOMAIN ); ?></th>
									<th><?php esc_html_e( 'SKU', EOP_TEXT_DOMAIN ); ?></th>
									<th><?php esc_html_e( 'Nome desejado', EOP_TEXT_DOMAIN ); ?></th>
									<th><?php esc_html_e( 'Status', EOP_TEXT_DOMAIN ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $custom_rows as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row['original_name'] ); ?></td>
										<td><?php echo esc_html( $row['sku'] ?: '—' ); ?></td>
										<td><?php echo esc_html( $row['custom_name'] ?: '—' ); ?></td>
										<td><?php echo esc_html( $row['locked'] ? __( 'Bloqueado', EOP_TEXT_DOMAIN ) : __( 'Editavel', EOP_TEXT_DOMAIN ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

				<?php if ( $footer_note ) : ?>
					<div class="eop-flow-pdf__footer"><?php echo esc_html( $footer_note ); ?></div>
				<?php endif; ?>
			</div>
		</body>
		</html>
		<?php

		return ob_get_clean();
	}

	private static function maybe_build_dompdf_pdf( $html ) {
		if ( ! self::ensure_reference_dompdf_loaded() ) {
			return '';
		}

		if ( ! class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Dompdf' ) || ! class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Options' ) ) {
			return '';
		}

		$runtime_paths = self::get_pdf_runtime_paths();

		if ( empty( $runtime_paths['temp'] ) || empty( $runtime_paths['fonts'] ) ) {
			return '';
		}

		try {
			$options = array(
				'tempDir'                 => $runtime_paths['temp'],
				'fontDir'                 => $runtime_paths['fonts'],
				'fontCache'               => $runtime_paths['fonts'],
				'isRemoteEnabled'         => true,
				'isHtml5ParserEnabled'    => true,
				'isPhpEnabled'            => false,
				'defaultFont'             => 'Helvetica',
				'chroot'                  => array_values( array_unique( array_map( 'wp_normalize_path', array_filter( array( WP_CONTENT_DIR, WP_PLUGIN_DIR, EOP_PLUGIN_DIR, $runtime_paths['base'] ) ) ) ) ),
			);

			$dompdf = new \WPO\IPS\Vendor\Dompdf\Dompdf( new \WPO\IPS\Vendor\Dompdf\Options( $options ) );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();

			return (string) $dompdf->output();
		} catch ( Throwable $throwable ) {
			return '';
		}
	}

	private static function maybe_build_headless_pdf( $html, $filename ) {
		$browser       = self::get_browser_pdf_executable();
		$runtime_paths = self::get_pdf_runtime_paths();

		if ( '' === $browser || empty( $runtime_paths['temp'] ) ) {
			return '';
		}

		$html_file = trailingslashit( $runtime_paths['temp'] ) . wp_generate_uuid4() . '.html';
		$pdf_file  = trailingslashit( $runtime_paths['temp'] ) . sanitize_file_name( $filename );

		if ( false === file_put_contents( $html_file, $html ) ) {
			return '';
		}

		@unlink( $pdf_file );

		$command = sprintf(
			'%s --headless --disable-gpu --run-all-compositor-stages-before-draw --virtual-time-budget=2000 --print-to-pdf=%s --print-to-pdf-no-header %s',
			escapeshellarg( $browser ),
			escapeshellarg( $pdf_file ),
			escapeshellarg( self::path_to_file_url( $html_file ) )
		);

		exec( $command, $output, $status );

		@unlink( $html_file );

		if ( 0 !== (int) $status || ! file_exists( $pdf_file ) ) {
			@unlink( $pdf_file );
			return '';
		}

		$binary = (string) file_get_contents( $pdf_file );
		@unlink( $pdf_file );

		return $binary;
	}

	private static function ensure_reference_dompdf_loaded() {
		if ( class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Dompdf' ) && class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Options' ) ) {
			return true;
		}

		$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce-pdf-invoices-packing-slips/';
		$autoloads  = array(
			$plugin_dir . 'vendor/autoload.php',
			$plugin_dir . 'vendor/strauss/autoload.php',
		);

		foreach ( $autoloads as $autoload ) {
			if ( file_exists( $autoload ) ) {
				require_once $autoload;
			}
		}

		return class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Dompdf' ) && class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Options' );
	}

	private static function get_pdf_runtime_paths() {
		$upload_dir = wp_upload_dir();

		if ( empty( $upload_dir['basedir'] ) ) {
			return array();
		}

		$base_dir = trailingslashit( $upload_dir['basedir'] ) . 'eop-post-confirmation-pdf';
		$temp_dir = trailingslashit( $base_dir ) . 'tmp';
		$font_dir = trailingslashit( $base_dir ) . 'fonts';

		if ( ! wp_mkdir_p( $temp_dir ) || ! wp_mkdir_p( $font_dir ) ) {
			return array();
		}

		return array(
			'base'  => $base_dir,
			'temp'  => $temp_dir,
			'fonts' => $font_dir,
		);
	}

	private static function get_browser_pdf_executable() {
		$candidates = array(
			'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
			'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
			'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
			'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
			'/usr/bin/google-chrome',
			'/usr/bin/chromium',
			'/usr/bin/chromium-browser',
			'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
		);

		foreach ( $candidates as $candidate ) {
			if ( '' !== $candidate && file_exists( $candidate ) && is_executable( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	private static function path_to_file_url( $path ) {
		$normalized = wp_normalize_path( (string) $path );

		if ( preg_match( '/^[A-Za-z]:\//', $normalized ) ) {
			return 'file:///' . str_replace( ' ', '%20', $normalized );
		}

		return 'file://' . str_replace( ' ', '%20', $normalized );
	}

	private static function is_product_locked( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		$locked = self::get_locked_product_tokens();
		$tokens = array_filter(
			array(
				(string) $product->get_id(),
				(string) $product->get_parent_id(),
				(string) $product->get_sku(),
			)
		);

		foreach ( $tokens as $token ) {
			if ( in_array( strtolower( trim( $token ) ), $locked, true ) ) {
				return true;
			}
		}

		return false;
	}

	private static function get_locked_product_tokens() {
		$raw = (string) EOP_Settings::get( 'post_confirmation_locked_products', '' );
		$raw = strtolower( $raw );
		$raw = str_replace( array( "\r", "\n", ';' ), ',', $raw );

		return array_values( array_unique( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) ) );
	}

	private static function get_signature_document_templates() {
		if ( ! class_exists( 'EOP_Settings' ) ) {
			return array();
		}

		if ( method_exists( 'EOP_Settings', 'get_post_confirmation_contract_documents' ) ) {
			$documents = EOP_Settings::get_post_confirmation_contract_documents();
		} elseif ( method_exists( 'EOP_Settings', 'get_post_confirmation_signature_documents' ) ) {
			$documents = EOP_Settings::get_post_confirmation_signature_documents();
		} else {
			return array();
		}

		return array_values(
			array_filter(
				$documents,
				static function ( $document ) {
					if ( ! is_array( $document ) ) {
						return false;
					}

					if ( 'attachment' === ( $document['source_type'] ?? 'editor' ) ) {
						return ! empty( $document['attachment_id'] );
					}

					return '' !== trim( wp_strip_all_tags( (string) ( $document['body'] ?? '' ) ) );
				}
			)
		);
	}

	public static function get_admin_contract_preview_payload( $settings = array() ) {
		$settings  = is_array( $settings ) ? wp_parse_args( $settings, EOP_Settings::get_defaults() ) : EOP_Settings::get_all();
		$documents = EOP_Settings::get_post_confirmation_contract_documents( $settings );
		$primary   = ! empty( $documents ) ? self::get_signature_document_preview_payload( $documents[0] ) : array();

		return array(
			'document_count'       => count( $documents ),
			'additional_documents' => max( 0, count( $documents ) - 1 ),
			'primary_document'     => $primary,
		);
	}

	private static function get_signature_document_preview_payload( $template ) {
		$template      = is_array( $template ) ? $template : array();
		$attachment_id = absint( $template['attachment_id'] ?? 0 );
		$mime_type     = $attachment_id ? self::get_attachment_mime_type( $attachment_id ) : '';
		$preview_type  = 'empty';
		$preview_url   = '';
		$preview_html  = '';

		if ( 'attachment' === ( $template['source_type'] ?? 'editor' ) && $attachment_id ) {
			if ( 'application/pdf' === $mime_type ) {
				$preview_type = 'pdf';
				$preview_url  = (string) wp_get_attachment_url( $attachment_id );
			} else {
				$extracted_text = self::extract_text_from_attachment( $attachment_id );

				if ( '' !== $extracted_text ) {
					$preview_type = 'text';
					$preview_html = wpautop( esc_html( $extracted_text ) );
				}
			}
		} elseif ( '' !== trim( wp_strip_all_tags( (string) ( $template['body'] ?? '' ) ) ) ) {
			$preview_type = 'text';
			$preview_html = wpautop( wp_kses_post( (string) $template['body'] ) );
		}

		return array(
			'title'        => (string) ( $template['title'] ?? '' ),
			'description'  => (string) ( $template['description'] ?? '' ),
			'view_label'   => (string) ( $template['view_label'] ?? __( 'Visualizar PDF', EOP_TEXT_DOMAIN ) ),
			'button_label' => (string) ( $template['button_label'] ?? __( 'Baixar PDF', EOP_TEXT_DOMAIN ) ),
			'preview_type' => $preview_type,
			'preview_url'  => $preview_url,
			'preview_html' => $preview_html,
		);
	}

	private static function get_signature_documents( WC_Order $order, $state = null, $include_urls = false, $ensure_generated = true ) {
		$state = is_array( $state ) ? $state : self::get_state( $order );
		$documents = is_array( $state['signature_documents'] ?? null ) ? $state['signature_documents'] : array();

		if ( $ensure_generated && 'yes' === (string) $order->get_meta( '_eop_proposal_confirmed', true ) ) {
			$documents = self::ensure_signature_documents_generated( $order );
		}

		if ( empty( $documents ) && $include_urls ) {
			$documents = self::get_signature_document_placeholder_records();
		}

		$documents = array_values(
			array_filter(
				$documents,
				static function ( $document ) use ( $include_urls ) {
					return $include_urls || ! empty( $document['attachment_id'] );
				}
			)
		);

		if ( ! $include_urls ) {
			return $documents;
		}

		return array_map(
			function ( $document ) use ( $order ) {
				$document_key = sanitize_key( (string) ( $document['key'] ?? '' ) );

				$document['admin_view_url']     = self::get_signature_document_url( $order, $document_key, false, false );
				$document['admin_download_url'] = self::get_signature_document_url( $order, $document_key, true, false );
				$document['public_view_url']    = self::get_signature_document_url( $order, $document_key, false, true );
				$document['public_download_url'] = self::get_signature_document_url( $order, $document_key, true, true );

				return $document;
			},
			$documents
		);
	}

	private static function get_signature_document_placeholder_records() {
		$templates = self::get_signature_document_templates();

		if ( empty( $templates ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $template ) {
						$record = array(
							'key'          => sanitize_key( (string) ( $template['key'] ?? '' ) ),
							'title'        => (string) ( $template['title'] ?? '' ),
							'description'  => (string) ( $template['description'] ?? '' ),
							'source_type'  => (string) ( $template['source_type'] ?? 'editor' ),
							'button_label' => (string) ( $template['button_label'] ?? __( 'Baixar PDF', EOP_TEXT_DOMAIN ) ),
							'view_label'   => (string) ( $template['view_label'] ?? __( 'Visualizar PDF', EOP_TEXT_DOMAIN ) ),
							'attachment_id' => 0,
							'filename'     => '',
						);

						if ( '' === $record['key'] ) {
							return array();
						}

						if ( 'attachment' === $record['source_type'] ) {
							$attachment_id = absint( $template['attachment_id'] ?? 0 );

							if ( $attachment_id > 0 ) {
								$record['source_attachment_id'] = $attachment_id;
								$record['filename']             = wp_basename( (string) get_attached_file( $attachment_id ) );
							}
						}

						return $record;
					},
					$templates
				),
				static function ( $record ) {
					return ! empty( $record['key'] );
				}
			)
		);
	}

	private static function find_signature_document_record( $documents, $document_key ) {
		$document_key = sanitize_key( (string) $document_key );

		foreach ( (array) $documents as $document ) {
			if ( $document_key === sanitize_key( (string) ( $document['key'] ?? '' ) ) ) {
				return is_array( $document ) ? $document : array();
			}
		}

		return array();
	}

	private static function signature_document_record_is_valid( $record, $template ) {
		if ( empty( $record ) || empty( $record['attachment_id'] ) ) {
			return false;
		}

		$attachment_id = absint( $record['attachment_id'] );
		$file_path     = get_attached_file( $attachment_id );
		$template_hash = self::get_signature_document_template_hash( $template );

		return ! empty( $file_path )
			&& file_exists( $file_path )
			&& (string) ( $record['template_hash'] ?? '' ) === $template_hash;
	}

	private static function generate_signature_document_record( WC_Order $order, $template ) {
		$template_hash = self::get_signature_document_template_hash( $template );
		$record        = array(
			'key'           => sanitize_key( (string) ( $template['key'] ?? '' ) ),
			'title'         => (string) ( $template['title'] ?? '' ),
			'description'   => (string) ( $template['description'] ?? '' ),
			'source_type'   => (string) ( $template['source_type'] ?? 'editor' ),
			'button_label'  => (string) ( $template['button_label'] ?? __( 'Baixar PDF', EOP_TEXT_DOMAIN ) ),
			'view_label'    => (string) ( $template['view_label'] ?? __( 'Visualizar PDF', EOP_TEXT_DOMAIN ) ),
			'generated_at'  => current_time( 'mysql' ),
			'template_hash' => $template_hash,
		);

		if ( 'attachment' === $record['source_type'] ) {
			$attachment_id = absint( $template['attachment_id'] ?? 0 );
			$file_path     = $attachment_id ? get_attached_file( $attachment_id ) : '';
			$mime_type     = $attachment_id ? self::get_attachment_mime_type( $attachment_id ) : '';

			if ( ! $attachment_id || empty( $file_path ) || ! file_exists( $file_path ) ) {
				return array();
			}

			if ( 'application/pdf' !== $mime_type ) {
				$extracted_text = self::extract_text_from_attachment( $attachment_id );

				if ( '' === $extracted_text ) {
					return array();
				}

				$html = self::get_signature_document_html(
					$order,
					array_merge(
						(array) $template,
						array(
							'source_type' => 'editor',
							'body'        => nl2br( esc_html( $extracted_text ) ),
						)
					)
				);
				$binary = self::build_signature_document_binary( $order, $template, $html );
				$generated_attachment_id = '' !== $binary ? self::store_generated_signature_document( $order, $template, $binary ) : 0;

				if ( ! $generated_attachment_id ) {
					return array();
				}

				$record['attachment_id']        = $generated_attachment_id;
				$record['source_attachment_id'] = $attachment_id;
				$record['filename']             = wp_basename( (string) get_attached_file( $generated_attachment_id ) );

				return $record;
			}

			$record['attachment_id']        = $attachment_id;
			$record['source_attachment_id'] = $attachment_id;
			$record['filename']             = wp_basename( $file_path );

			return $record;
		}

		$html = self::get_signature_document_html( $order, $template );

		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return array();
		}

		$binary = self::build_signature_document_binary( $order, $template, $html );

		if ( '' === $binary ) {
			return array();
		}

		$attachment_id = self::store_generated_signature_document( $order, $template, $binary );

		if ( ! $attachment_id ) {
			return array();
		}

		$record['attachment_id'] = $attachment_id;
		$record['filename']      = wp_basename( (string) get_attached_file( $attachment_id ) );

		return $record;
	}

	private static function get_signature_document_template_hash( $template ) {
		return md5( wp_json_encode( $template ) );
	}

	private static function get_signature_document_html( WC_Order $order, $template ) {
		$settings        = EOP_Settings::get_all();
		$font_css        = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $settings['font_family'] ?? '' ) : "'Segoe UI', sans-serif";
		$company_name    = trim( (string) ( $settings['pdf_company_name'] ?? '' ) );
		$company_doc     = trim( (string) ( $settings['pdf_company_document'] ?? '' ) );
		$company_address = trim( (string) ( $settings['pdf_company_address'] ?? '' ) );
		$title           = (string) ( $template['title'] ?? '' );
		$description     = (string) ( $template['description'] ?? '' );
		$body            = self::resolve_signature_document_template( $order, (string) ( $template['body'] ?? '' ) );

		ob_start();
		?>
		<!doctype html>
		<html>
		<head>
			<meta charset="utf-8">
			<style>
				@page { size: A4; margin: 16mm; }
				body { margin: 0; color: #172033; background: #ffffff; font-family: <?php echo esc_html( $font_css ); ?>; font-size: 13px; line-height: 1.6; }
				* { box-sizing: border-box; }
				.eop-signature-pdf { display: grid; gap: 18px; }
				.eop-signature-pdf__hero { padding: 24px; border-radius: 20px; background: linear-gradient(145deg, #0f1d4d 0%, #172e7a 100%); color: #fff; }
				.eop-signature-pdf__hero strong { display: block; margin-bottom: 8px; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; opacity: .78; }
				.eop-signature-pdf__hero h1 { margin: 0; font-size: 28px; line-height: 1.12; }
				.eop-signature-pdf__hero p { margin: 10px 0 0; color: rgba(255,255,255,.84); }
				.eop-signature-pdf__meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
				.eop-signature-pdf__meta-card { padding: 16px 18px; border: 1px solid #dbe3f0; border-radius: 18px; }
				.eop-signature-pdf__meta-card span { display: block; margin-bottom: 6px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #5b6474; }
				.eop-signature-pdf__body { padding: 18px; border: 1px solid #e4e9f6; border-radius: 18px; background: #fbfcff; }
				.eop-signature-pdf__body h1,
				.eop-signature-pdf__body h2,
				.eop-signature-pdf__body h3 { margin: 0 0 10px; }
				.eop-signature-pdf__body p { margin: 0 0 12px; }
				.eop-signature-pdf__body ul,
				.eop-signature-pdf__body ol { margin: 0 0 12px 20px; }
			</style>
		</head>
		<body>
			<div class="eop-signature-pdf">
				<div class="eop-signature-pdf__hero">
					<strong><?php esc_html_e( 'Documento para assinatura', EOP_TEXT_DOMAIN ); ?></strong>
					<h1><?php echo esc_html( $title ); ?></h1>
					<?php if ( $description ) : ?><p><?php echo esc_html( $description ); ?></p><?php endif; ?>
				</div>
				<div class="eop-signature-pdf__meta">
					<div class="eop-signature-pdf__meta-card">
						<span><?php esc_html_e( 'Pedido', EOP_TEXT_DOMAIN ); ?></span>
						<strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>
					</div>
					<div class="eop-signature-pdf__meta-card">
						<span><?php esc_html_e( 'Empresa', EOP_TEXT_DOMAIN ); ?></span>
						<strong><?php echo esc_html( $company_name ?: get_bloginfo( 'name' ) ); ?></strong>
						<?php if ( $company_doc ) : ?><p><?php echo esc_html( $company_doc ); ?></p><?php endif; ?>
						<?php if ( $company_address ) : ?><p><?php echo esc_html( $company_address ); ?></p><?php endif; ?>
					</div>
				</div>
				<div class="eop-signature-pdf__body"><?php echo wp_kses_post( wpautop( $body ) ); ?></div>
			</div>
		</body>
		</html>
		<?php

		return ob_get_clean();
	}

	private static function resolve_signature_document_template( WC_Order $order, $template ) {
		if ( '' === $template ) {
			return '';
		}

		return strtr( (string) $template, self::get_contract_placeholder_replacements( $order ) );
	}

	private static function build_signature_document_binary( WC_Order $order, $template, $html ) {
		$filename = self::get_signature_document_filename( $order, $template );
		$binary   = self::maybe_build_dompdf_pdf( $html );

		if ( '' !== $binary ) {
			return $binary;
		}

		return self::maybe_build_headless_pdf( $html, $filename );
	}

	private static function get_signature_document_filename( WC_Order $order, $template ) {
		$key = sanitize_title( (string) ( $template['key'] ?? $template['title'] ?? 'documento' ) );

		return sanitize_file_name( 'assinatura-' . $order->get_id() . '-' . $key . '.pdf' );
	}

	private static function store_generated_signature_document( WC_Order $order, $template, $binary ) {
		$filename = self::get_signature_document_filename( $order, $template );
		$upload   = wp_upload_bits( $filename, null, $binary );

		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			return 0;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'application/pdf',
				'post_title'     => sanitize_text_field( (string) ( $template['title'] ?? $filename ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_parent'    => $order->get_id(),
			),
			$upload['file'],
			$order->get_id()
		);

		return is_wp_error( $attachment_id ) ? 0 : absint( $attachment_id );
	}

	private static function stream_attachment_file( $attachment_id, $force_download = false, $filename = '' ) {
		$file_path = get_attached_file( $attachment_id );
		$mime_type = (string) get_post_mime_type( $attachment_id );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'Arquivo nao encontrado.', EOP_TEXT_DOMAIN ) );
		}

		nocache_headers();
		header( 'Content-Type: ' . ( $mime_type ? $mime_type : 'application/octet-stream' ) );
		header( 'Content-Disposition: ' . ( $force_download ? 'attachment' : 'inline' ) . '; filename="' . sanitize_file_name( $filename ? $filename : wp_basename( $file_path ) ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: private, max-age=0, must-revalidate' );
		header( 'Pragma: public' );

		readfile( $file_path );
		exit;
	}

	private static function get_attachment_mime_type( $attachment_id ) {
		$mime_type = (string) get_post_mime_type( $attachment_id );
		$file_path = get_attached_file( $attachment_id );

		if ( '' === $mime_type && ! empty( $file_path ) ) {
			$filetype = wp_check_filetype( $file_path );
			$mime_type = (string) ( $filetype['type'] ?? '' );
		}

		return $mime_type;
	}

	private static function extract_text_from_attachment( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		$mime_type = self::get_attachment_mime_type( $attachment_id );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return '';
		}

		if ( false !== strpos( $mime_type, 'wordprocessingml.document' ) || 'docx' === strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ) ) {
			return self::extract_text_from_docx_file( $file_path );
		}

		if ( false !== strpos( $mime_type, 'msword' ) || 'doc' === strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ) ) {
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
		$command_path = self::find_cli_binary( array( 'antiword' ) );

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

	private static function get_document_fields( $settings = null ) {
		return EOP_Settings::get_post_confirmation_document_fields( is_array( $settings ) ? $settings : EOP_Settings::get_all() );
	}

	public static function get_contract_placeholder_tokens() {
		return array(
			'{site_name}',
			'{order_id}',
			'{order_number}',
			'{order_total}',
			'{billing_first_name}',
			'{billing_last_name}',
			'{billing_full_name}',
			'{billing_email}',
			'{billing_phone}',
			'{billing_company}',
			'{billing_document}',
			'{billing_cpf}',
			'{billing_cnpj}',
			'{billing_ie}',
			'{billing_address_1}',
			'{billing_address_2}',
			'{billing_number}',
			'{billing_neighborhood}',
			'{billing_city}',
			'{billing_state}',
			'{billing_postcode}',
			'{billing_country}',
			'{billing_full_address}',
			'{shipping_first_name}',
			'{shipping_last_name}',
			'{shipping_full_name}',
			'{shipping_company}',
			'{shipping_address_1}',
			'{shipping_address_2}',
			'{shipping_number}',
			'{shipping_neighborhood}',
			'{shipping_city}',
			'{shipping_state}',
			'{shipping_postcode}',
			'{shipping_country}',
			'{shipping_full_address}',
			'{payment_method}',
			'{order_date}',
		);
	}

	private static function get_public_return_url( WC_Order $order ) {
		if ( 'yes' !== EOP_Settings::get( 'enable_checkout_confirmation', 'no' ) || ! self::is_enabled_for_order( $order ) || 'yes' !== (string) $order->get_meta( '_eop_proposal_confirmed', true ) ) {
			return '';
		}

		return (string) EOP_Public_Proposal::get_public_link( $order );
	}

	private static function get_contract_text( WC_Order $order, $state = null ) {
		$state         = is_array( $state ) ? $state : self::get_state( $order );
		$contract_text = trim( (string) ( $state['contract']['contract_text'] ?? '' ) );

		if ( '' !== $contract_text ) {
			return $contract_text;
		}

		$contract_text = self::build_contract_documents_snapshot( $order );

		if ( '' !== trim( wp_strip_all_tags( $contract_text ) ) ) {
			return $contract_text;
		}

		return self::resolve_contract_template( $order );
	}

	private static function build_contract_documents_snapshot( WC_Order $order ) {
		$documents = self::get_signature_document_templates();
		$sections  = array();

		foreach ( $documents as $document ) {
			if ( 'attachment' === ( $document['source_type'] ?? 'editor' ) ) {
				continue;
			}

			$body = self::resolve_signature_document_template( $order, (string) ( $document['body'] ?? '' ) );

			if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
				continue;
			}

			$section_title = trim( (string) ( $document['title'] ?? '' ) );
			$section       = '';

			if ( '' !== $section_title ) {
				$section .= '<h3>' . esc_html( $section_title ) . '</h3>';
			}

			$section    .= $body;
			$sections[] = $section;
		}

		if ( empty( $sections ) ) {
			return '';
		}

		return implode( "\n<hr />\n", $sections );
	}

	private static function resolve_contract_template( WC_Order $order, $template = null ) {
		$template = is_string( $template ) ? $template : (string) EOP_Settings::get( 'post_confirmation_contract_body', '' );

		if ( '' === $template ) {
			return '';
		}

		return strtr( $template, self::get_contract_placeholder_replacements( $order ) );
	}

	private static function get_contract_placeholder_replacements( WC_Order $order ) {
		$billing_document     = self::get_order_customer_document( $order );
		$billing_cpf          = self::get_order_meta_value( $order, array( '_billing_cpf', 'billing_cpf' ) );
		$billing_cnpj         = self::get_order_meta_value( $order, array( '_billing_cnpj', 'billing_cnpj' ) );
		$billing_ie           = self::get_order_meta_value( $order, array( '_billing_ie', 'billing_ie' ) );
		$billing_number       = self::get_order_meta_value( $order, array( '_billing_number', 'billing_number' ) );
		$billing_neighborhood = self::get_order_meta_value( $order, array( '_billing_neighborhood', 'billing_neighborhood' ) );
		$shipping_number      = self::get_order_meta_value( $order, array( '_shipping_number', 'shipping_number' ) );
		$shipping_neighborhood = self::get_order_meta_value( $order, array( '_shipping_neighborhood', 'shipping_neighborhood' ) );
		$billing_full_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$shipping_full_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		$order_date         = $order->get_date_created();
		$replacements       = array(
			'site_name'             => get_bloginfo( 'name' ),
			'order_id'              => (string) $order->get_id(),
			'order_number'          => (string) $order->get_order_number(),
			'order_total'           => wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			'billing_first_name'    => $order->get_billing_first_name(),
			'billing_last_name'     => $order->get_billing_last_name(),
			'billing_full_name'     => $billing_full_name,
			'billing_email'         => $order->get_billing_email(),
			'billing_phone'         => $order->get_billing_phone(),
			'billing_company'       => $order->get_billing_company(),
			'billing_document'      => $billing_document,
			'billing_cpf'           => $billing_cpf,
			'billing_cnpj'          => $billing_cnpj,
			'billing_ie'            => $billing_ie,
			'billing_address_1'     => $order->get_billing_address_1(),
			'billing_address_2'     => $order->get_billing_address_2(),
			'billing_number'        => $billing_number,
			'billing_neighborhood'  => $billing_neighborhood,
			'billing_city'          => $order->get_billing_city(),
			'billing_state'         => $order->get_billing_state(),
			'billing_postcode'      => $order->get_billing_postcode(),
			'billing_country'       => $order->get_billing_country(),
			'billing_full_address'  => self::implode_address_parts(
				array(
					$order->get_billing_address_1(),
					$billing_number,
					$order->get_billing_address_2(),
					$billing_neighborhood,
					$order->get_billing_city(),
					$order->get_billing_state(),
					$order->get_billing_postcode(),
					$order->get_billing_country(),
				)
			),
			'shipping_first_name'   => $order->get_shipping_first_name(),
			'shipping_last_name'    => $order->get_shipping_last_name(),
			'shipping_full_name'    => $shipping_full_name,
			'shipping_company'      => $order->get_shipping_company(),
			'shipping_address_1'    => $order->get_shipping_address_1(),
			'shipping_address_2'    => $order->get_shipping_address_2(),
			'shipping_number'       => $shipping_number,
			'shipping_neighborhood' => $shipping_neighborhood,
			'shipping_city'         => $order->get_shipping_city(),
			'shipping_state'        => $order->get_shipping_state(),
			'shipping_postcode'     => $order->get_shipping_postcode(),
			'shipping_country'      => $order->get_shipping_country(),
			'shipping_full_address' => self::implode_address_parts(
				array(
					$order->get_shipping_address_1(),
					$shipping_number,
					$order->get_shipping_address_2(),
					$shipping_neighborhood,
					$order->get_shipping_city(),
					$order->get_shipping_state(),
					$order->get_shipping_postcode(),
					$order->get_shipping_country(),
				)
			),
			'payment_method'        => $order->get_payment_method_title(),
			'order_date'            => $order_date ? wc_format_datetime( $order_date ) : '',
		);

		$prepared = array();
		$charset  = get_bloginfo( 'charset' );

		foreach ( $replacements as $key => $value ) {
			$sanitized_value = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, $charset ? $charset : 'UTF-8' );
			$prepared[ '{' . $key . '}' ]   = $sanitized_value;
			$prepared[ '{{' . $key . '}}' ] = $sanitized_value;
		}

		return $prepared;
	}

	private static function implode_address_parts( $parts ) {
		$parts = array_filter(
			array_map(
				static function ( $part ) {
					return trim( wp_strip_all_tags( (string) $part ) );
				},
				(array) $parts
			)
		);

		return implode( ', ', $parts );
	}

	private static function get_order_data_rows( WC_Order $order ) {
		$rows = array(
			array(
				'key'   => 'billing_full_name',
				'label' => __( 'Cliente', EOP_TEXT_DOMAIN ),
				'value' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			),
			array(
				'key'   => 'billing_document',
				'label' => __( 'CPF / CNPJ', EOP_TEXT_DOMAIN ),
				'value' => self::get_order_customer_document( $order ),
			),
			array(
				'key'   => 'billing_email',
				'label' => __( 'E-mail', EOP_TEXT_DOMAIN ),
				'value' => $order->get_billing_email(),
			),
			array(
				'key'   => 'billing_phone',
				'label' => __( 'Telefone', EOP_TEXT_DOMAIN ),
				'value' => $order->get_billing_phone(),
			),
			array(
				'key'   => 'billing_company',
				'label' => __( 'Empresa', EOP_TEXT_DOMAIN ),
				'value' => $order->get_billing_company(),
			),
			array(
				'key'   => 'billing_ie',
				'label' => __( 'Inscricao estadual', EOP_TEXT_DOMAIN ),
				'value' => self::get_order_meta_value( $order, array( '_billing_ie', 'billing_ie' ) ),
			),
			array(
				'key'   => 'billing_address',
				'label' => __( 'Endereco de cobranca', EOP_TEXT_DOMAIN ),
				'value' => self::get_order_address_label( $order, 'billing' ),
			),
			array(
				'key'   => 'shipping_address',
				'label' => __( 'Endereco de entrega', EOP_TEXT_DOMAIN ),
				'value' => self::get_order_address_label( $order, 'shipping' ),
			),
			array(
				'key'   => 'payment_method',
				'label' => __( 'Forma de pagamento', EOP_TEXT_DOMAIN ),
				'value' => $order->get_payment_method_title(),
			),
		);

		return array_values(
			array_map(
				static function ( $row ) {
					$value = trim( wp_strip_all_tags( (string) ( $row['value'] ?? '' ) ) );

					return array(
						'key'    => (string) $row['key'],
						'label'  => (string) $row['label'],
						'value'  => $value,
						'filled' => '' !== $value,
					);
				},
				$rows
			)
		);
	}

	private static function get_order_customer_document( WC_Order $order ) {
		return self::get_order_meta_value( $order, array( '_billing_cnpj', 'billing_cnpj', '_billing_cpf', 'billing_cpf' ) );
	}

	private static function get_order_meta_value( WC_Order $order, $keys ) {
		foreach ( (array) $keys as $key ) {
			$value = trim( (string) $order->get_meta( $key, true ) );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	private static function get_order_address_label( WC_Order $order, $type ) {
		$is_shipping  = 'shipping' === $type;
		$number       = self::get_order_meta_value( $order, $is_shipping ? array( '_shipping_number', 'shipping_number' ) : array( '_billing_number', 'billing_number' ) );
		$neighborhood = self::get_order_meta_value( $order, $is_shipping ? array( '_shipping_neighborhood', 'shipping_neighborhood' ) : array( '_billing_neighborhood', 'billing_neighborhood' ) );
		$parts        = $is_shipping
			? array( $order->get_shipping_address_1(), $number, $order->get_shipping_address_2(), $neighborhood, $order->get_shipping_city(), $order->get_shipping_state(), $order->get_shipping_postcode(), $order->get_shipping_country() )
			: array( $order->get_billing_address_1(), $number, $order->get_billing_address_2(), $neighborhood, $order->get_billing_city(), $order->get_billing_state(), $order->get_billing_postcode(), $order->get_billing_country() );

		return self::implode_address_parts( $parts );
	}

	private static function store_upload( $file ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$uploaded = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'pdf'      => 'application/pdf',
				),
			)
		);

		if ( isset( $uploaded['error'] ) || empty( $uploaded['file'] ) ) {
			return new WP_Error( 'invalid_file', __( 'Arquivo invalido.', EOP_TEXT_DOMAIN ) );
		}

		$attachment = array(
			'post_mime_type' => sanitize_mime_type( $uploaded['type'] ),
			'post_title'     => sanitize_text_field( wp_basename( $uploaded['file'] ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $uploaded['file'] );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return new WP_Error( 'invalid_file', __( 'Nao foi possivel registrar o arquivo.', EOP_TEXT_DOMAIN ) );
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return absint( $attachment_id );
	}

	private static function get_client_ip() {
		$candidates = array(
			$_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
			$_SERVER['REMOTE_ADDR'] ?? '',
		);

		foreach ( $candidates as $candidate ) {
			$candidate = trim( explode( ',', (string) $candidate )[0] );

			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		return '';
	}
}
