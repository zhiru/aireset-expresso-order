<?php
defined( 'ABSPATH' ) || exit;

$settings            = EOP_Settings::get_all();
$font_css            = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $settings['font_family'] ) : "'Segoe UI', sans-serif";
$order_statuses      = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
$initial_view        = EOP_Admin_Page::normalize_view( isset( $_GET['view'] ) ? wp_unslash( $_GET['view'] ) : '' );
$license_manager     = class_exists( 'EOP_License_Manager' ) ? EOP_License_Manager::get_instance() : null;
$can_manage_settings = current_user_can( 'manage_options' );
$pdf_tabs            = class_exists( 'EOP_PDF_Admin_Page' ) ? EOP_PDF_Admin_Page::get_spa_nav_tabs() : array();
$current_pdf_tab     = class_exists( 'EOP_PDF_Admin_Page' ) ? EOP_PDF_Admin_Page::get_current_tab() : 'display';
$pdf_preview_args    = array(
    'document'      => isset( $_GET['document'] ) && 'proposal' === sanitize_key( wp_unslash( $_GET['document'] ) ) ? 'proposal' : 'order',
    'preview_order' => absint( $_GET['preview_order'] ?? 0 ),
);
$general_views = array(
    'settings-store-info',
    'settings-general-config',
    'settings-confirmation-flow',
    'settings-order-link-style',
    'settings-proposal-link-style',
    'settings-texts',
);
$is_general_view = in_array( $initial_view, $general_views, true );
$general_nav_items = array(
    'settings-store-info' => array(
        'label' => __( 'Informacoes sobre a loja', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-store',
    ),
    'settings-general-config' => array(
        'label' => __( 'Configuracoes Gerais', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-admin-settings',
    ),
    'settings-confirmation-flow' => array(
        'label' => __( 'Fluxo de Confirmacao', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-yes-alt',
    ),
    'settings-order-link-style' => array(
        'label' => __( 'Estilo/Visual do Link do Pedido', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-art',
    ),
    'settings-proposal-link-style' => array(
        'label' => __( 'Estilo/Visual do Link de Proposta', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-format-image',
    ),
    'settings-texts' => array(
        'label' => __( 'Textos', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-edit-large',
    ),
);
?>
<style>
    .eop-admin-spa {
        --eop-primary: <?php echo esc_attr( $settings['primary_color'] ); ?>;
        --eop-surface: <?php echo esc_attr( $settings['surface_color'] ); ?>;
        --eop-border: <?php echo esc_attr( $settings['border_color'] ); ?>;
        --eop-radius: <?php echo esc_attr( absint( $settings['border_radius'] ) ); ?>px;
        --eop-font-family: <?php echo esc_attr( $font_css ); ?>;
        font-family: <?php echo esc_attr( $font_css ); ?>;
    }
</style>

<div class="wrap eop-admin-spa eop-pdv">
    <div class="eop-admin-spa__layout">
        <aside class="eop-admin-spa__sidebar">
            <div class="eop-admin-spa__brand">
                <div class="eop-admin-spa__brand-mark">
                    <img src="<?php echo esc_url( EOP_PLUGIN_URL . 'assets/images/logo-aireset.png' ); ?>" alt="Pedido Expresso - Aireset" />
                </div>
                <div class="eop-admin-spa__brand-copy">
                    <h1><?php echo esc_html( $settings['panel_title'] ); ?></h1>
                    <?php if ( ! empty( $settings['panel_subtitle'] ) ) : ?>
                        <p><?php echo esc_html( $settings['panel_subtitle'] ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <nav class="eop-admin-spa__nav" aria-label="<?php esc_attr_e( 'Navegacao do admin do Pedido Expresso', EOP_TEXT_DOMAIN ); ?>">
                <button type="button" class="eop-pdv-nav__item eop-admin-spa-nav__item<?php echo 'new-order' === $initial_view ? ' is-active' : ''; ?>" data-eop-view-target="new-order" aria-selected="<?php echo 'new-order' === $initial_view ? 'true' : 'false'; ?>">
                    <span class="eop-admin-spa-nav__icon dashicons dashicons-cart" aria-hidden="true"></span>
                    <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'Novo pedido', EOP_TEXT_DOMAIN ); ?></span>
                </button>
                <button type="button" class="eop-pdv-nav__item eop-admin-spa-nav__item<?php echo 'orders' === $initial_view ? ' is-active' : ''; ?>" data-eop-view-target="orders" aria-selected="<?php echo 'orders' === $initial_view ? 'true' : 'false'; ?>">
                    <span class="eop-admin-spa-nav__icon dashicons dashicons-list-view" aria-hidden="true"></span>
                    <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'Pedidos', EOP_TEXT_DOMAIN ); ?></span>
                </button>
                <?php if ( $can_manage_settings ) : ?>
                    <div class="eop-admin-spa-nav__group eop-admin-spa-nav__group--general<?php echo $is_general_view ? ' is-open' : ''; ?>">
                        <button
                            type="button"
                            class="eop-pdv-nav__item eop-admin-spa-nav__item eop-admin-spa-nav__group-toggle<?php echo $is_general_view ? ' is-active' : ''; ?>"
                            data-eop-nav-toggle="general"
                            aria-selected="<?php echo $is_general_view ? 'true' : 'false'; ?>"
                            aria-expanded="<?php echo $is_general_view ? 'true' : 'false'; ?>"
                        >
                            <span class="eop-admin-spa-nav__icon dashicons dashicons-admin-generic" aria-hidden="true"></span>
                            <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'Geral', EOP_TEXT_DOMAIN ); ?></span>
                            <span class="eop-admin-spa-nav__group-arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                        </button>

                        <div class="eop-admin-spa-nav__submenu"<?php echo $is_general_view ? '' : ' hidden'; ?>>
                            <?php foreach ( $general_nav_items as $view_key => $nav_item ) : ?>
                                <button
                                    type="button"
                                    class="eop-admin-spa-nav__submenu-item<?php echo $initial_view === $view_key ? ' is-active' : ''; ?>"
                                    data-eop-view-target="<?php echo esc_attr( $view_key ); ?>"
                                >
                                    <span class="eop-admin-spa-nav__submenu-label"><?php echo esc_html( $nav_item['label'] ); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $pdf_tabs ) ) : ?>
                    <div class="eop-admin-spa-nav__group eop-admin-spa-nav__group--pdf<?php echo 'pdf' === $initial_view ? ' is-open' : ''; ?>">
                        <button
                            type="button"
                            class="eop-pdv-nav__item eop-admin-spa-nav__item eop-admin-spa-nav__group-toggle<?php echo 'pdf' === $initial_view ? ' is-active' : ''; ?>"
                            data-eop-view-target="pdf"
                            data-eop-nav-toggle="pdf"
                            aria-selected="<?php echo 'pdf' === $initial_view ? 'true' : 'false'; ?>"
                            aria-expanded="<?php echo 'pdf' === $initial_view ? 'true' : 'false'; ?>"
                        >
                            <span class="eop-admin-spa-nav__icon dashicons dashicons-media-document" aria-hidden="true"></span>
                            <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'PDF', EOP_TEXT_DOMAIN ); ?></span>
                            <span class="eop-admin-spa-nav__group-arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                        </button>

                        <div class="eop-admin-spa-nav__submenu"<?php echo 'pdf' === $initial_view ? '' : ' hidden'; ?>>
                            <?php foreach ( $pdf_tabs as $pdf_tab_key => $pdf_tab_label ) : ?>
                                <a
                                    class="eop-admin-spa-nav__submenu-item<?php echo 'pdf' === $initial_view && $current_pdf_tab === $pdf_tab_key ? ' is-active' : ''; ?>"
                                    href="<?php echo esc_url( EOP_PDF_Admin_Page::get_tab_url( $pdf_tab_key, $pdf_preview_args ) ); ?>"
                                    data-eop-pdf-tab="<?php echo esc_attr( $pdf_tab_key ); ?>"
                                >
                                    <span class="eop-admin-spa-nav__submenu-label"><?php echo esc_html( $pdf_tab_label ); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else : ?>
                    <button type="button" class="eop-pdv-nav__item eop-admin-spa-nav__item<?php echo 'pdf' === $initial_view ? ' is-active' : ''; ?>" data-eop-view-target="pdf" aria-selected="<?php echo 'pdf' === $initial_view ? 'true' : 'false'; ?>">
                        <span class="eop-admin-spa-nav__icon dashicons dashicons-media-document" aria-hidden="true"></span>
                        <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'PDF', EOP_TEXT_DOMAIN ); ?></span>
                    </button>
                <?php endif; ?>
                <?php if ( $can_manage_settings ) : ?>
                    <button type="button" class="eop-pdv-nav__item eop-admin-spa-nav__item<?php echo 'documentation' === $initial_view ? ' is-active' : ''; ?>" data-eop-view-target="documentation" aria-selected="<?php echo 'documentation' === $initial_view ? 'true' : 'false'; ?>">
                        <span class="eop-admin-spa-nav__icon dashicons dashicons-book-alt" aria-hidden="true"></span>
                        <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'Documentacao', EOP_TEXT_DOMAIN ); ?></span>
                    </button>
                    <button type="button" class="eop-pdv-nav__item eop-admin-spa-nav__item<?php echo 'license' === $initial_view ? ' is-active' : ''; ?>" data-eop-view-target="license" aria-selected="<?php echo 'license' === $initial_view ? 'true' : 'false'; ?>">
                        <span class="eop-admin-spa-nav__icon dashicons dashicons-admin-network" aria-hidden="true"></span>
                        <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'Licenca', EOP_TEXT_DOMAIN ); ?></span>
                    </button>
                <?php endif; ?>
            </nav>
        </aside>

        <div class="eop-admin-spa__content">
            <div id="eop-notices"></div>

            <section class="eop-pdv-view<?php echo 'new-order' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="new-order"<?php echo 'new-order' === $initial_view ? '' : ' hidden'; ?>>
                <input type="hidden" id="eop-edit-order-id" value="0" />

                <div class="eop-admin-view-header">
                    <div class="eop-admin-view-copy">
                        <span class="eop-admin-view-kicker"><?php esc_html_e( 'Operacao comercial', EOP_TEXT_DOMAIN ); ?></span>
                        <div class="eop-admin-view-title-row">
                            <h2 class="eop-admin-view-title">
                                <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                                <span><?php esc_html_e( 'Novo pedido', EOP_TEXT_DOMAIN ); ?></span>
                            </h2>
                        </div>
                        <p class="eop-admin-view-desc"><?php esc_html_e( 'Monte o pedido, ajuste cliente, frete e descontos sem sair do fluxo principal do painel.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                </div>

                <div class="eop-admin-view-main">

                <div class="eop-editing-banner" id="eop-editing-banner" hidden>
                    <div>
                        <strong id="eop-editing-title"><?php esc_html_e( 'Editando pedido', EOP_TEXT_DOMAIN ); ?></strong>
                        <p><?php esc_html_e( 'Voce esta ajustando um pedido existente dentro do painel.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <button type="button" class="eop-btn" id="eop-cancel-edit"><?php esc_html_e( 'Cancelar edicao', EOP_TEXT_DOMAIN ); ?></button>
                </div>

                <div class="eop-pdv-grid">
                    <div class="eop-pdv-main">
                        <div class="eop-card">
                            <h2><?php esc_html_e( 'Produtos', EOP_TEXT_DOMAIN ); ?></h2>
                            <div class="eop-item-defaults">
                                <div class="eop-item-defaults__title"><?php esc_html_e( 'Acoes em massa', EOP_TEXT_DOMAIN ); ?></div>
                                <div class="eop-field">
                                    <label for="eop-default-item-quantity"><?php esc_html_e( 'Quantidade', EOP_TEXT_DOMAIN ); ?></label>
                                    <input type="number" id="eop-default-item-quantity" min="1" step="1" value="1" inputmode="numeric" />
                                </div>
                                <div class="eop-field">
                                    <label for="eop-default-item-discount"><?php esc_html_e( 'Desconto', EOP_TEXT_DOMAIN ); ?></label>
                                    <div class="eop-item-discount-group eop-item-defaults__discount-group">
                                        <input type="text" id="eop-default-item-discount" class="eop-discount-text-input" value="" placeholder="" inputmode="decimal" />
                                        <span class="eop-item-discount-suffix" id="eop-default-item-discount-suffix" hidden></span>
                                    </div>
                                </div>
                                <div class="eop-field eop-item-defaults__action">
                                    <button type="button" id="eop-apply-item-defaults" class="eop-btn"><?php esc_html_e( 'Aplicar', EOP_TEXT_DOMAIN ); ?></button>
                                </div>
                            </div>
                            <div class="eop-field">
                                <select id="eop-product-search" style="width:100%"></select>
                            </div>

                            <div class="eop-items-list" id="eop-items-body">
                                <div class="eop-items-empty"><?php esc_html_e( 'Nenhum produto adicionado.', EOP_TEXT_DOMAIN ); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="eop-pdv-sidebar">
                        <div class="eop-card eop-accordion">
                            <button type="button" class="eop-accordion__toggle" aria-expanded="false">
                                <h2><?php esc_html_e( 'Cliente (opcional)', EOP_TEXT_DOMAIN ); ?></h2>
                                <span class="eop-accordion__icon" aria-hidden="true">+</span>
                            </button>
                            <div class="eop-accordion__body" hidden>
                                <div class="eop-field">
                                    <label for="eop-document"><?php esc_html_e( 'CPF / CNPJ', EOP_TEXT_DOMAIN ); ?></label>
                                    <div class="eop-input-group">
                                        <input type="text" id="eop-document" placeholder="000.000.000-00" autocomplete="off" />
                                        <button type="button" id="eop-search-customer" class="eop-btn"><?php esc_html_e( 'Buscar', EOP_TEXT_DOMAIN ); ?></button>
                                    </div>
                                    <span id="eop-customer-status" class="eop-status"></span>
                                </div>

                                <input type="hidden" id="eop-user-id" value="0" />

                                <div class="eop-field">
                                    <label for="eop-name"><?php esc_html_e( 'Nome', EOP_TEXT_DOMAIN ); ?></label>
                                    <input type="text" id="eop-name" />
                                </div>

                                <div class="eop-field">
                                    <label for="eop-email"><?php esc_html_e( 'E-mail', EOP_TEXT_DOMAIN ); ?></label>
                                    <input type="email" id="eop-email" />
                                </div>

                                <div class="eop-field">
                                    <label for="eop-phone"><?php esc_html_e( 'WhatsApp', EOP_TEXT_DOMAIN ); ?></label>
                                    <input type="tel" id="eop-phone" />
                                </div>
                            </div>
                        </div>

                        <div class="eop-card">
                            <div class="eop-accordion eop-totals-accordion">
                                <button type="button" class="eop-accordion__toggle eop-totals-detail-toggle" aria-expanded="false">
                                    <span><?php esc_html_e( 'Ver detalhes de pagamento', EOP_TEXT_DOMAIN ); ?></span>
                                    <span class="eop-accordion__icon" aria-hidden="true">+</span>
                                </button>
                                <div class="eop-accordion__body" hidden>
                                    <div class="eop-shipping-box">
                                        <button type="button" id="eop-shipping-toggle" class="eop-shipping-toggle" aria-expanded="false" aria-controls="eop-shipping-panel">
                                            <span class="eop-shipping-toggle__copy">
                                                <strong><?php esc_html_e( 'Entrega e frete', EOP_TEXT_DOMAIN ); ?></strong>
                                                <small id="eop-shipping-summary"><?php esc_html_e( 'Clique para calcular com o endereco do cliente.', EOP_TEXT_DOMAIN ); ?></small>
                                            </span>
                                            <span class="eop-shipping-toggle__icon" aria-hidden="true">+</span>
                                        </button>

                                        <div class="eop-shipping-panel" id="eop-shipping-panel" hidden>
                                            <div class="eop-shipping-panel__intro">
                                                <strong><?php esc_html_e( 'Como funciona', EOP_TEXT_DOMAIN ); ?></strong>
                                                <p><?php esc_html_e( 'Preencha o CEP para buscar endereco automaticamente, complete o numero e calcule as opcoes de frete.', EOP_TEXT_DOMAIN ); ?></p>
                                            </div>

                                            <div class="eop-shipping-panel__status" id="eop-shipping-address-status"></div>

                                            <div class="eop-field-row">
                                                <div class="eop-field">
                                                    <label for="eop-shipping-postcode"><?php esc_html_e( 'CEP', EOP_TEXT_DOMAIN ); ?></label>
                                                    <input type="text" id="eop-shipping-postcode" placeholder="00000-000" inputmode="numeric" />
                                                </div>
                                                <div class="eop-field">
                                                    <label for="eop-shipping-state"><?php esc_html_e( 'Estado', EOP_TEXT_DOMAIN ); ?></label>
                                                    <input type="text" id="eop-shipping-state" placeholder="SP" />
                                                </div>
                                            </div>

                                            <div class="eop-field-row">
                                                <div class="eop-field">
                                                    <label for="eop-shipping-city"><?php esc_html_e( 'Cidade', EOP_TEXT_DOMAIN ); ?></label>
                                                    <input type="text" id="eop-shipping-city" />
                                                </div>
                                                <div class="eop-field">
                                                    <label for="eop-shipping-number"><?php esc_html_e( 'Numero', EOP_TEXT_DOMAIN ); ?></label>
                                                    <input type="text" id="eop-shipping-number" />
                                                </div>
                                            </div>

                                            <div class="eop-field">
                                                <label for="eop-shipping-address"><?php esc_html_e( 'Endereco', EOP_TEXT_DOMAIN ); ?></label>
                                                <input type="text" id="eop-shipping-address" />
                                            </div>

                                            <div class="eop-field-row">
                                                <div class="eop-field">
                                                    <label for="eop-shipping-neighborhood"><?php esc_html_e( 'Bairro', EOP_TEXT_DOMAIN ); ?></label>
                                                    <input type="text" id="eop-shipping-neighborhood" />
                                                </div>
                                                <div class="eop-field">
                                                    <label for="eop-shipping-address-2"><?php esc_html_e( 'Complemento', EOP_TEXT_DOMAIN ); ?></label>
                                                    <input type="text" id="eop-shipping-address-2" />
                                                </div>
                                            </div>

                                            <div class="eop-field">
                                                <button type="button" id="eop-calc-shipping" class="eop-btn eop-btn-primary eop-btn-block"><?php esc_html_e( 'Buscar opcoes de frete', EOP_TEXT_DOMAIN ); ?></button>
                                            </div>

                                            <div id="eop-shipping-rates" class="eop-shipping-rates"></div>
                                        </div>
                                    </div>

                                    <input type="hidden" id="eop-shipping" value="0" />

                                    <div class="eop-field">
                                        <label for="eop-discount"><?php esc_html_e( 'Desconto geral', EOP_TEXT_DOMAIN ); ?></label>
                                        <input type="text" id="eop-discount" class="eop-discount-text-input" value="" placeholder="10% ou 10" inputmode="decimal" />
                                    </div>

                                    <div class="eop-totals">
                                        <div class="eop-total-row">
                                            <span><?php esc_html_e( 'Subtotal:', EOP_TEXT_DOMAIN ); ?></span>
                                            <span id="eop-subtotal">R$ 0,00</span>
                                        </div>
                                        <div class="eop-total-row">
                                            <span><?php esc_html_e( 'Frete:', EOP_TEXT_DOMAIN ); ?></span>
                                            <span id="eop-shipping-total">R$ 0,00</span>
                                        </div>
                                        <div class="eop-total-row">
                                            <span><?php esc_html_e( 'Desconto:', EOP_TEXT_DOMAIN ); ?></span>
                                            <span id="eop-discount-total">- R$ 0,00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="eop-totals-grand-always">
                                <div class="eop-total-row eop-total-grand">
                                    <span><?php esc_html_e( 'Total:', EOP_TEXT_DOMAIN ); ?></span>
                                    <span id="eop-grand-total">R$ 0,00</span>
                                </div>
                            </div>
                        </div>

                        <div class="eop-card">
                            <div class="eop-field">
                                <label for="eop-status"><?php esc_html_e( 'Status', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop-status">
                                    <option value="completed"><?php esc_html_e( 'Concluido', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="pending"><?php esc_html_e( 'Pendente', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="processing"><?php esc_html_e( 'Processando', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="on-hold"><?php esc_html_e( 'Aguardando', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>

                            <button type="button" id="eop-submit" class="eop-btn eop-btn-primary eop-btn-block">
                                <?php esc_html_e( 'Finalizar e Gerar PDF', EOP_TEXT_DOMAIN ); ?>
                            </button>
                        </div>

                        <div class="eop-card eop-post-flow-card" id="eop-post-flow-card">
                            <div class="eop-post-flow-card__head">
                                <div>
                                    <h2><?php esc_html_e( 'Fluxo complementar do cliente', EOP_TEXT_DOMAIN ); ?></h2>
                                    <p id="eop-post-flow-subtitle"><?php esc_html_e( 'O resumo complementar aparece quando um pedido existente entra em modo de edicao.', EOP_TEXT_DOMAIN ); ?></p>
                                </div>
                                <span class="eop-post-flow-badge is-inactive" id="eop-post-flow-badge"><?php esc_html_e( 'Inativo', EOP_TEXT_DOMAIN ); ?></span>
                            </div>

                            <div class="eop-post-flow-card__stats" id="eop-post-flow-stats"></div>

                            <div class="eop-post-flow-card__section">
                                <h3><?php esc_html_e( 'Contrato', EOP_TEXT_DOMAIN ); ?></h3>
                                <p id="eop-post-flow-contract"><?php esc_html_e( 'Nenhum aceite registrado.', EOP_TEXT_DOMAIN ); ?></p>
                            </div>

                            <div class="eop-post-flow-card__section">
                                <h3><?php esc_html_e( 'Documentos para assinatura', EOP_TEXT_DOMAIN ); ?></h3>
                                <div class="eop-post-flow-list" id="eop-post-flow-signature-documents"></div>
                            </div>

                            <div class="eop-post-flow-card__section">
                                <h3><?php esc_html_e( 'Dados do pedido', EOP_TEXT_DOMAIN ); ?></h3>
                                <div class="eop-post-flow-list" id="eop-post-flow-order-data"></div>
                            </div>

                            <div class="eop-post-flow-card__section">
                                <h3><?php esc_html_e( 'Anexo', EOP_TEXT_DOMAIN ); ?></h3>
                                <div class="eop-post-flow-list" id="eop-post-flow-attachment"></div>
                            </div>

                            <div class="eop-post-flow-card__section">
                                <h3><?php esc_html_e( 'Produtos', EOP_TEXT_DOMAIN ); ?></h3>
                                <div class="eop-post-flow-list" id="eop-post-flow-products"></div>
                            </div>

                            <div class="eop-post-flow-card__actions">
                                <a class="eop-btn" id="eop-post-flow-public-link" href="#" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Abrir link publico', EOP_TEXT_DOMAIN ); ?></a>
                                <a class="eop-btn eop-btn-secondary" id="eop-post-flow-pdf-link" href="#" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Baixar PDF complementar', EOP_TEXT_DOMAIN ); ?></a>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </section>

            <section class="eop-pdv-view<?php echo 'orders' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="orders"<?php echo 'orders' === $initial_view ? '' : ' hidden'; ?>>
                <div class="eop-admin-view-header">
                    <div class="eop-admin-view-copy">
                        <span class="eop-admin-view-kicker"><?php esc_html_e( 'Gestao comercial', EOP_TEXT_DOMAIN ); ?></span>
                        <div class="eop-admin-view-title-row">
                            <h2 class="eop-admin-view-title">
                                <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                                <span><?php esc_html_e( 'Pedidos', EOP_TEXT_DOMAIN ); ?></span>
                            </h2>
                        </div>
                        <p class="eop-admin-view-desc"><?php esc_html_e( 'Acompanhe pedidos e propostas da equipe comercial com os atalhos principais do fluxo.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                </div>

                <div class="eop-admin-view-main">
                <div class="eop-orders-browser">
                    <div class="eop-card eop-orders-browser__controls">
                        <div class="eop-orders-browser__top">
                            <div>
                                <h2><?php esc_html_e( 'Pedidos criados', EOP_TEXT_DOMAIN ); ?></h2>
                                <p><?php esc_html_e( 'Acompanhe propostas e pedidos sem sair da tela de vendas.', EOP_TEXT_DOMAIN ); ?></p>
                            </div>
                            <button type="button" class="eop-btn" id="eop-orders-refresh"><?php esc_html_e( 'Atualizar', EOP_TEXT_DOMAIN ); ?></button>
                        </div>

                        <div class="eop-orders-browser__filters">
                            <div class="eop-field">
                                <label for="eop-orders-search"><?php esc_html_e( 'Buscar', EOP_TEXT_DOMAIN ); ?></label>
                                <input type="search" id="eop-orders-search" placeholder="<?php esc_attr_e( 'Pedido, cliente ou e-mail', EOP_TEXT_DOMAIN ); ?>" />
                            </div>
                            <div class="eop-field">
                                <label for="eop-orders-status-filter"><?php esc_html_e( 'Status', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop-orders-status-filter">
                                    <option value="any"><?php esc_html_e( 'Todos', EOP_TEXT_DOMAIN ); ?></option>
                                    <?php foreach ( $order_statuses as $status_key => $status_label ) : ?>
                                        <option value="<?php echo esc_attr( str_replace( 'wc-', '', $status_key ) ); ?>"><?php echo esc_html( $status_label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="eop-field">
                                <label for="eop-orders-flow-filter"><?php esc_html_e( 'Fluxo complementar', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop-orders-flow-filter">
                                    <option value="any"><?php esc_html_e( 'Todos', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="active"><?php esc_html_e( 'Com fluxo ativo', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="pending"><?php esc_html_e( 'Pendentes', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="completed"><?php esc_html_e( 'Concluidos', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="eop-orders-browser__summary" id="eop-orders-summary"></div>
                    <div class="eop-orders-browser__list" id="eop-orders-list"></div>
                    <div class="eop-orders-browser__pagination" id="eop-orders-pagination"></div>
                </div>
                </div>
            </section>

            <section class="eop-pdv-view<?php echo 'pdf' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="pdf"<?php echo 'pdf' === $initial_view ? '' : ' hidden'; ?>>
                <div class="eop-admin-panel-head">
                    <h2><?php esc_html_e( 'PDF', EOP_TEXT_DOMAIN ); ?></h2>
                    <p><?php esc_html_e( 'Configure documentos, preview e comportamento do modulo PDF sem sair do shell original do Pedido Expresso.', EOP_TEXT_DOMAIN ); ?></p>
                </div>
                <div class="eop-admin-view-main">
                <?php
                if ( class_exists( 'EOP_PDF_Admin_Page' ) ) {
                    EOP_PDF_Admin_Page::render_embedded_page();
                }
                ?>
                </div>
            </section>

            <?php if ( $can_manage_settings ) : ?>
                <section class="eop-pdv-view<?php echo 'settings-store-info' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-store-info"<?php echo 'settings-store-info' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Informacoes sobre a loja', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Centralize logo, dados institucionais e informacoes exibidas nos documentos do Pedido Expresso.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php EOP_PDF_Admin_Page::render_embedded_page( 'store' ); ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'settings-general-config' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-general-config"<?php echo 'settings-general-config' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Configuracoes Gerais', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Mantenha em um bloco proprio as regras operacionais do plugin, paginas publicas e comportamento comercial principal.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php EOP_Settings::render_embedded_page( 'general-config' ); ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'settings-confirmation-flow' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-confirmation-flow"<?php echo 'settings-confirmation-flow' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Fluxo de Confirmacao', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Edite a jornada complementar apos a proposta em uma tela propria, com contrato, anexos e personalizacao.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php EOP_Settings::render_embedded_page( 'confirmation-flow' ); ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'settings-order-link-style' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-order-link-style"<?php echo 'settings-order-link-style' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Estilo/Visual do Link do Pedido', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Separe a identidade visual principal do shell e do link do pedido para ajustes rapidos de marca.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php EOP_Settings::render_embedded_page( 'order-link-style' ); ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'settings-proposal-link-style' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-proposal-link-style"<?php echo 'settings-proposal-link-style' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Estilo/Visual do Link de Proposta', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Ajuste o visual publico da proposta sem misturar essas opcoes com o restante do admin.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php EOP_Settings::render_embedded_page( 'proposal-link-style' ); ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'settings-texts' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-texts"<?php echo 'settings-texts' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Textos e mensagens', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Mantenha em uma pagina propria os titulos, descricoes e labels usados no painel e na proposta publica.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php EOP_Settings::render_embedded_page( 'texts' ); ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'documentation' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="documentation"<?php echo 'documentation' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Documentacao', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Consulte em uma area propria o efeito real de cada configuracao do modulo de documentos.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php EOP_PDF_Admin_Page::render_embedded_page( 'documentation' ); ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'license' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="license"<?php echo 'license' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Licenca', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Consulte a validade da assinatura e administre a ativacao do plugin sem sair do painel.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <div class="eop-admin-license-shell">
                        <?php
                        if ( $license_manager ) {
                            $license_manager->activated();
                        }
                        ?>
                    </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <div id="eop-success-modal" class="eop-modal" style="display:none;">
        <div class="eop-modal-content">
            <h2><?php esc_html_e( 'Pedido Criado!', EOP_TEXT_DOMAIN ); ?></h2>
            <p id="eop-success-message"></p>
            <div class="eop-modal-actions">
                <a id="eop-pdf-link" href="#" target="_blank" class="eop-btn eop-btn-primary"><?php esc_html_e( 'Abrir PDF', EOP_TEXT_DOMAIN ); ?></a>
                <a id="eop-public-link" href="#" target="_blank" class="eop-btn" style="display:none;"><?php esc_html_e( 'Abrir link do cliente', EOP_TEXT_DOMAIN ); ?></a>
                <a id="eop-order-link" href="#" target="_blank" class="eop-btn"><?php esc_html_e( 'Ver Pedido', EOP_TEXT_DOMAIN ); ?></a>
                <button type="button" id="eop-new-order" class="eop-btn"><?php esc_html_e( 'Novo Pedido', EOP_TEXT_DOMAIN ); ?></button>
            </div>
        </div>
    </div>
</div>
