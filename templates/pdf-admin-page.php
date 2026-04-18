<?php
defined( 'ABSPATH' ) || exit;

$plugin_settings = EOP_Settings::get_all();
$pdf_settings    = EOP_PDF_Settings::get_all();
$embedded        = ! empty( $embedded );
$font_css        = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $plugin_settings['font_family'] ) : "'Segoe UI', sans-serif";
$tabs            = EOP_PDF_Admin_Page::get_accessible_tabs();
$tab             = EOP_PDF_Admin_Page::normalize_tab( EOP_PDF_Admin_Page::get_current_tab(), $default_tab );

if ( ! isset( $tabs[ $tab ] ) ) {
    $tab = key( $tabs );
}

$document        = isset( $_GET['document'] ) && 'proposal' === sanitize_key( wp_unslash( $_GET['document'] ) ) ? 'proposal' : 'order';
$recent_orders   = EOP_Document_Manager::get_recent_orders( 20 );
$preview_order   = EOP_Document_Manager::get_preview_order( absint( $_GET['preview_order'] ?? 0 ) );
$preview_allowed = $preview_order instanceof WC_Order && 'no' === $pdf_settings['advanced_html_output'];
$preview_mode    = empty( $_GET['preview_order'] ) ? __( 'Atualmente mostrando o ultimo pedido', EOP_TEXT_DOMAIN ) : __( 'Atualmente mostrando o pedido selecionado', EOP_TEXT_DOMAIN );
$preview_pdf_url = $preview_order instanceof WC_Order ? EOP_Document_Manager::get_pdf_document_url( $preview_order, $document ) : '';
$order_page_url  = EOP_Page_Installer::get_page_url( 'order' );
$proposal_url    = EOP_Page_Installer::get_page_url( 'proposal' );
$current_tab_label = isset( $tabs[ $tab ] ) ? $tabs[ $tab ] : __( 'PDF', EOP_TEXT_DOMAIN );
?>
<style>
    .eop-pdf-admin {
        --eop-primary: <?php echo esc_attr( $plugin_settings['primary_color'] ); ?>;
        --eop-surface: <?php echo esc_attr( $plugin_settings['surface_color'] ); ?>;
        --eop-border: <?php echo esc_attr( $plugin_settings['border_color'] ); ?>;
        --eop-radius: <?php echo esc_attr( absint( $plugin_settings['border_radius'] ) ); ?>px;
        --eop-font-family: <?php echo esc_attr( $font_css ); ?>;
        font-family: <?php echo esc_attr( $font_css ); ?>;
    }
</style>

<div class="<?php echo esc_attr( $embedded ? 'eop-pdf-admin eop-pdf-admin--embedded' : 'wrap eop-pdf-admin' ); ?>">
    <?php if ( ! $embedded ) : ?>
        <div class="eop-pdf-admin__header">
            <div>
                <h1><?php esc_html_e( 'PDF', EOP_TEXT_DOMAIN ); ?></h1>
                <p><?php esc_html_e( 'Modulo nativo de documentos do Pedido Expresso, com configuracoes e preview no estilo da experiencia anterior.', EOP_TEXT_DOMAIN ); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ( ! $embedded ) : ?>
        <nav class="nav-tab-wrapper eop-pdf-admin__tabs">
            <?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
                <a class="nav-tab<?php echo $tab_key === $tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( EOP_PDF_Admin_Page::get_tab_url( $tab_key, array( 'document' => $document, 'preview_order' => $preview_order ? $preview_order->get_id() : 0 ) ) ); ?>">
                    <?php echo esc_html( $tab_label ); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if ( isset( $_GET['settings-updated'] ) && 'true' === wp_unslash( $_GET['settings-updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Configuracoes do PDF salvas com sucesso.', EOP_TEXT_DOMAIN ); ?></p></div>
    <?php endif; ?>

    <div class="eop-pdf-admin__shell" data-eop-pdf-shell data-eop-pdf-tab-current="<?php echo esc_attr( $tab ); ?>">
        <div class="eop-pdf-admin__sidebar">
            <?php if ( 'general' === $tab ) : ?>
                <form method="post" action="options.php" class="eop-pdf-admin__form">
                    <?php settings_fields( 'eop_pdf_settings_group' ); ?>

                    <div class="eop-pdf-admin__notice">
                        <p><?php esc_html_e( 'Configure como o PDF deve ser exibido, qual modelo usar e quais dados da loja aparecem no documento.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>

                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Configuracoes de exibicao', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_pdf_display_mode"><?php esc_html_e( 'Como voce deseja visualizar o PDF?', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_pdf_display_mode" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[display_mode]">
                                    <option value="new_tab" <?php selected( $pdf_settings['display_mode'], 'new_tab' ); ?>><?php esc_html_e( 'Abrir o PDF em uma nova aba/janela', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="download" <?php selected( $pdf_settings['display_mode'], 'download' ); ?>><?php esc_html_e( 'Baixar automaticamente', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_pdf_paper_size"><?php esc_html_e( 'Tamanho do papel', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_pdf_paper_size" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[paper_size]">
                                    <option value="a4" <?php selected( $pdf_settings['paper_size'], 'a4' ); ?>>A4</option>
                                    <option value="letter" <?php selected( $pdf_settings['paper_size'], 'letter' ); ?>>Letter</option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_pdf_template_name"><?php esc_html_e( 'Escolha um modelo', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_pdf_template_name" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[template_name]">
                                    <option value="simple" <?php selected( $pdf_settings['template_name'], 'simple' ); ?>><?php esc_html_e( 'Simple', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="compact" <?php selected( $pdf_settings['template_name'], 'compact' ); ?>><?php esc_html_e( 'Compact', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="minimal" <?php selected( $pdf_settings['template_name'], 'minimal' ); ?>><?php esc_html_e( 'Minimal', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_pdf_ink_saving_mode"><?php esc_html_e( 'Modo de economia de tinta', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_pdf_ink_saving_mode" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[ink_saving_mode]">
                                    <option value="no" <?php selected( $pdf_settings['ink_saving_mode'], 'no' ); ?>><?php esc_html_e( 'Desativado', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['ink_saving_mode'], 'yes' ); ?>><?php esc_html_e( 'Ativado', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_pdf_test_mode"><?php esc_html_e( 'Modo de teste', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_pdf_test_mode" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[test_mode]">
                                    <option value="no" <?php selected( $pdf_settings['test_mode'], 'no' ); ?>><?php esc_html_e( 'Desativado', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['test_mode'], 'yes' ); ?>><?php esc_html_e( 'Ativado', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_pdf_font_subsetting"><?php esc_html_e( 'Font subsetting', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_pdf_font_subsetting" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[font_subsetting]">
                                    <option value="yes" <?php selected( $pdf_settings['font_subsetting'], 'yes' ); ?>><?php esc_html_e( 'Ativado', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings['font_subsetting'], 'no' ); ?>><?php esc_html_e( 'Desativado', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                        </div>
                    </details>

                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Informacoes sobre a loja', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field is-full">
                                <label><?php esc_html_e( 'Logo/Cabecalho da loja', EOP_TEXT_DOMAIN ); ?></label>
                                <div class="eop-settings-media" data-media-role="shop-logo">
                                    <input type="hidden" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_logo_url]" value="<?php echo esc_attr( $pdf_settings['shop_logo_url'] ); ?>" />
                                    <div class="eop-settings-media__preview<?php echo $pdf_settings['shop_logo_url'] ? ' has-image' : ''; ?>" data-media-preview>
                                        <?php if ( $pdf_settings['shop_logo_url'] ) : ?>
                                            <img src="<?php echo esc_url( $pdf_settings['shop_logo_url'] ); ?>" alt="">
                                        <?php else : ?>
                                            <span class="eop-settings-media__empty"><?php esc_html_e( 'Nenhum logo selecionado ainda.', EOP_TEXT_DOMAIN ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="eop-settings-media__details">
                                        <input type="url" class="eop-settings-media__url" value="<?php echo esc_attr( $pdf_settings['shop_logo_url'] ); ?>" readonly data-media-url />
                                        <div class="eop-settings-media__actions">
                                            <button type="button" class="button button-secondary eop-settings-media__select" data-media-select><?php echo $pdf_settings['shop_logo_url'] ? esc_html__( 'Trocar logo', EOP_TEXT_DOMAIN ) : esc_html__( 'Selecionar logo', EOP_TEXT_DOMAIN ); ?></button>
                                            <button type="button" class="button button-link-delete eop-settings-media__remove<?php echo $pdf_settings['shop_logo_url'] ? '' : ' is-hidden'; ?>" data-media-remove><?php esc_html_e( 'Remover logo', EOP_TEXT_DOMAIN ); ?></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_logo_height"><?php esc_html_e( 'Altura do logo', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_logo_height" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_logo_height]" value="<?php echo esc_attr( $pdf_settings['shop_logo_height'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_name"><?php esc_html_e( 'Nome da loja', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_name" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_name]" value="<?php echo esc_attr( $pdf_settings['shop_name'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_address_line_1"><?php esc_html_e( 'Endereco da loja, linha 1', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_address_line_1" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_address_line_1]" value="<?php echo esc_attr( $pdf_settings['shop_address_line_1'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_address_line_2"><?php esc_html_e( 'Endereco da loja, linha 2', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_address_line_2" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_address_line_2]" value="<?php echo esc_attr( $pdf_settings['shop_address_line_2'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_city"><?php esc_html_e( 'Cidade', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_city" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_city]" value="<?php echo esc_attr( $pdf_settings['shop_city'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_state"><?php esc_html_e( 'Estado', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_state" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_state]" value="<?php echo esc_attr( $pdf_settings['shop_state'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_postcode"><?php esc_html_e( 'CEP', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_postcode" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_postcode]" value="<?php echo esc_attr( $pdf_settings['shop_postcode'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_country"><?php esc_html_e( 'Pais', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_country" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_country]" value="<?php echo esc_attr( $pdf_settings['shop_country'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_phone"><?php esc_html_e( 'Telefone da loja', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_phone" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_phone]" value="<?php echo esc_attr( $pdf_settings['shop_phone'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_email"><?php esc_html_e( 'E-mail da loja', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_email" type="email" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_email]" value="<?php echo esc_attr( $pdf_settings['shop_email'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_vat_number"><?php esc_html_e( 'Documento / VAT', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_vat_number" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_vat_number]" value="<?php echo esc_attr( $pdf_settings['shop_vat_number'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_chamber_of_commerce"><?php esc_html_e( 'Camara de comercio / registro', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_chamber_of_commerce" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_chamber_of_commerce]" value="<?php echo esc_attr( $pdf_settings['shop_chamber_of_commerce'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_extra_1"><?php esc_html_e( 'Campo extra 1', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_extra_1" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_extra_1]" value="<?php echo esc_attr( $pdf_settings['shop_extra_1'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_extra_2"><?php esc_html_e( 'Campo extra 2', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_extra_2" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_extra_2]" value="<?php echo esc_attr( $pdf_settings['shop_extra_2'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_extra_3"><?php esc_html_e( 'Campo extra 3', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_extra_3" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_extra_3]" value="<?php echo esc_attr( $pdf_settings['shop_extra_3'] ); ?>" />
                            </div>
                            <div class="eop-settings-field is-full">
                                <label for="eop_shop_footer"><?php esc_html_e( 'Rodape / informacoes adicionais', EOP_TEXT_DOMAIN ); ?></label>
                                <textarea id="eop_shop_footer" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_footer]"><?php echo esc_textarea( $pdf_settings['shop_footer'] ); ?></textarea>
                            </div>
                        </div>
                    </details>

                    <div class="eop-pdf-admin__actions"><?php submit_button( __( 'Salvar configuracoes gerais', EOP_TEXT_DOMAIN ), 'primary', 'submit', false ); ?></div>
                </form>
            <?php elseif ( 'documents' === $tab ) : ?>
                <form method="post" action="options.php" class="eop-pdf-admin__form">
                    <?php settings_fields( 'eop_pdf_settings_group' ); ?>

                    <details class="eop-pdf-admin__section" open>
                        <summary><?php echo esc_html( 'proposal' === $document ? __( 'Configuracoes da proposta', EOP_TEXT_DOMAIN ) : __( 'Configuracoes do pedido', EOP_TEXT_DOMAIN ) ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_doc_enabled"><?php esc_html_e( 'Documento habilitado', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_enabled" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_enabled]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_enabled' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_enabled' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_attach_email"><?php esc_html_e( 'Anexar em e-mails', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_attach_email" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_attach_email]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_attach_email' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_attach_email' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_shipping"><?php esc_html_e( 'Exibir endereco de entrega', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_shipping" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_shipping]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_shipping' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_shipping' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_billing"><?php esc_html_e( 'Exibir endereco de cobranca', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_billing" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_billing]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_billing' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_billing' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_email"><?php esc_html_e( 'Exibir e-mail do cliente', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_email" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_email]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_email' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_email' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_phone"><?php esc_html_e( 'Exibir telefone do cliente', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_phone" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_phone]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_phone' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_phone' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_notes"><?php esc_html_e( 'Exibir notas do cliente', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_notes" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_notes]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_notes' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_notes' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_mark_printed"><?php esc_html_e( 'Marcar como impresso', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_mark_printed" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_mark_printed]">
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_mark_printed' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_mark_printed' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_prefix"><?php esc_html_e( 'Prefixo do numero', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_prefix" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_prefix]" value="<?php echo esc_attr( $pdf_settings[ $document . '_prefix' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_suffix"><?php esc_html_e( 'Sufixo do numero', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_suffix" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_suffix]" value="<?php echo esc_attr( $pdf_settings[ $document . '_suffix' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_padding"><?php esc_html_e( 'Padding', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_padding" type="number" min="0" max="12" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_padding]" value="<?php echo esc_attr( $pdf_settings[ $document . '_padding' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_next_number"><?php esc_html_e( 'Proximo numero', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_next_number" type="number" min="1" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_next_number]" value="<?php echo esc_attr( $pdf_settings[ $document . '_next_number' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_reset_yearly"><?php esc_html_e( 'Reset anual', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_reset_yearly" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_reset_yearly]">
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_reset_yearly' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_reset_yearly' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <?php if ( 'order' === $document ) : ?>
                                <div class="eop-settings-field">
                                    <label for="eop_doc_myaccount"><?php esc_html_e( 'Download no Minha Conta', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_doc_myaccount" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[order_myaccount_download]">
                                        <option value="no" <?php selected( $pdf_settings['order_myaccount_download'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                        <option value="yes" <?php selected( $pdf_settings['order_myaccount_download'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    </select>
                                </div>
                            <?php else : ?>
                                <div class="eop-settings-field">
                                    <label for="eop_doc_public_pdf"><?php esc_html_e( 'Permitir PDF publico da proposta', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_doc_public_pdf" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[proposal_public_pdf]">
                                        <option value="yes" <?php selected( $pdf_settings['proposal_public_pdf'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                        <option value="no" <?php selected( $pdf_settings['proposal_public_pdf'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                    </details>
                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Colunas do detalhamento', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_sku"><?php esc_html_e( 'Exibir SKU do produto', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_sku" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_sku]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_sku' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_sku' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_quantity"><?php esc_html_e( 'Exibir coluna de quantidade', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_quantity" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_quantity]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_quantity' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_quantity' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_unit_price"><?php esc_html_e( 'Exibir coluna de valor unitario', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_unit_price" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_unit_price]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_unit_price' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_unit_price' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_discount"><?php esc_html_e( 'Exibir coluna de desconto', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_discount" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_discount]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_discount' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_discount' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_discounted_unit_price"><?php esc_html_e( 'Exibir coluna de valor unitario com desconto', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_discounted_unit_price" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_discounted_unit_price]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_discounted_unit_price' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_discounted_unit_price' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_line_total"><?php esc_html_e( 'Exibir coluna de total do item', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_line_total" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_line_total]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_line_total' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_line_total' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                        </div>
                    </details>
                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Textos das colunas', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_doc_product_label"><?php esc_html_e( 'Texto da coluna de produto', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_product_label" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_product_label]" value="<?php echo esc_attr( $pdf_settings[ $document . '_product_label' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_quantity_label"><?php esc_html_e( 'Texto da coluna de quantidade', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_quantity_label" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_quantity_label]" value="<?php echo esc_attr( $pdf_settings[ $document . '_quantity_label' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_unit_price_label"><?php esc_html_e( 'Texto da coluna de valor unitario', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_unit_price_label" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_unit_price_label]" value="<?php echo esc_attr( $pdf_settings[ $document . '_unit_price_label' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_discount_label"><?php esc_html_e( 'Texto da coluna de desconto', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_discount_label" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_discount_label]" value="<?php echo esc_attr( $pdf_settings[ $document . '_discount_label' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_discounted_unit_price_label"><?php esc_html_e( 'Texto da coluna de valor unitario com desconto', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_discounted_unit_price_label" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_discounted_unit_price_label]" value="<?php echo esc_attr( $pdf_settings[ $document . '_discounted_unit_price_label' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_line_total_label"><?php esc_html_e( 'Texto da coluna de total do item', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_line_total_label" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_line_total_label]" value="<?php echo esc_attr( $pdf_settings[ $document . '_line_total_label' ] ); ?>" />
                            </div>
                        </div>
                    </details>
                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Totais exibidos', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_total_subtotal"><?php esc_html_e( 'Exibir subtotal', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_total_subtotal" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_total_subtotal]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_total_subtotal' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_total_subtotal' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_total_shipping"><?php esc_html_e( 'Exibir frete', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_total_shipping" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_total_shipping]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_total_shipping' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_total_shipping' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_total_discount"><?php esc_html_e( 'Exibir desconto total', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_total_discount" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_total_discount]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_total_discount' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_total_discount' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_total_total"><?php esc_html_e( 'Exibir total final', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_total_total" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_total_total]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_total_total' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_total_total' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                        </div>
                    </details>
                    <div class="eop-pdf-admin__actions"><?php submit_button( __( 'Salvar configuracoes do documento', EOP_TEXT_DOMAIN ), 'primary', 'submit', false ); ?></div>
                </form>
            <?php elseif ( 'edocuments' === $tab ) : ?>
                <form method="post" action="options.php" class="eop-pdf-admin__form">
                    <?php settings_fields( 'eop_pdf_settings_group' ); ?>
                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Documentos eletrônicos', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_edoc_enabled"><?php esc_html_e( 'Ativar documentos eletrônicos', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_edoc_enabled" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_enabled]">
                                    <option value="no" <?php selected( $pdf_settings['edoc_enabled'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['edoc_enabled'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_format"><?php esc_html_e( 'Formato / sintaxe', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_edoc_format" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_format]">
                                    <option value="ubl" <?php selected( $pdf_settings['edoc_format'], 'ubl' ); ?>>UBL</option>
                                    <option value="cii" <?php selected( $pdf_settings['edoc_format'], 'cii' ); ?>>CII</option>
                                    <option value="peppol" <?php selected( $pdf_settings['edoc_format'], 'peppol' ); ?>>Peppol</option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_embed_pdf"><?php esc_html_e( 'Embutir PDF', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_edoc_embed_pdf" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_embed_pdf]">
                                    <option value="yes" <?php selected( $pdf_settings['edoc_embed_pdf'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings['edoc_embed_pdf'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_preview_xml"><?php esc_html_e( 'Habilitar preview XML', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_edoc_preview_xml" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_preview_xml]">
                                    <option value="yes" <?php selected( $pdf_settings['edoc_preview_xml'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings['edoc_preview_xml'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_logging"><?php esc_html_e( 'Habilitar logs', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_edoc_logging" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_logging]">
                                    <option value="no" <?php selected( $pdf_settings['edoc_logging'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['edoc_logging'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_supplier_scheme"><?php esc_html_e( 'Identificador do fornecedor', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_edoc_supplier_scheme" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_supplier_scheme]" value="<?php echo esc_attr( $pdf_settings['edoc_supplier_scheme'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_customer_scheme"><?php esc_html_e( 'Identificador do cliente', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_edoc_customer_scheme" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_customer_scheme]" value="<?php echo esc_attr( $pdf_settings['edoc_customer_scheme'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_network_endpoint"><?php esc_html_e( 'Peppol Endpoint ID', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_edoc_network_endpoint" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_network_endpoint]" value="<?php echo esc_attr( $pdf_settings['edoc_network_endpoint'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_network_eas"><?php esc_html_e( 'Peppol EAS', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_edoc_network_eas" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_network_eas]" value="<?php echo esc_attr( $pdf_settings['edoc_network_eas'] ); ?>" />
                            </div>
                        </div>
                    </details>
                    <div class="eop-pdf-admin__actions"><?php submit_button( __( 'Salvar configuracoes eletrônicas', EOP_TEXT_DOMAIN ), 'primary', 'submit', false ); ?></div>
                </form>
            <?php elseif ( 'advanced' === $tab ) : ?>
                <form method="post" action="options.php" class="eop-pdf-admin__form">
                    <?php settings_fields( 'eop_pdf_settings_group' ); ?>
                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Configuracoes avancadas', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_adv_link_access"><?php esc_html_e( 'Politica de acesso ao link', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_adv_link_access" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[advanced_link_access]">
                                    <option value="private_nonce" <?php selected( $pdf_settings['advanced_link_access'], 'private_nonce' ); ?>><?php esc_html_e( 'Privado com nonce', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="public_token" <?php selected( $pdf_settings['advanced_link_access'], 'public_token' ); ?>><?php esc_html_e( 'Publico por token', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="order_owner" <?php selected( $pdf_settings['advanced_link_access'], 'order_owner' ); ?>><?php esc_html_e( 'Dono do pedido', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_adv_pretty_links"><?php esc_html_e( 'Pretty links', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_adv_pretty_links" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[advanced_pretty_links]">
                                    <option value="no" <?php selected( $pdf_settings['advanced_pretty_links'], 'no' ); ?>><?php esc_html_e( 'Desativado', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['advanced_pretty_links'], 'yes' ); ?>><?php esc_html_e( 'Ativado', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_adv_html_output"><?php esc_html_e( 'Forcar output HTML', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_adv_html_output" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[advanced_html_output]">
                                    <option value="no" <?php selected( $pdf_settings['advanced_html_output'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['advanced_html_output'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_adv_debug"><?php esc_html_e( 'Debug do modulo', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_adv_debug" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[advanced_debug]">
                                    <option value="no" <?php selected( $pdf_settings['advanced_debug'], 'no' ); ?>><?php esc_html_e( 'Desativado', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['advanced_debug'], 'yes' ); ?>><?php esc_html_e( 'Ativado', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_adv_order_note_logs"><?php esc_html_e( 'Logar nas notas do pedido', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_adv_order_note_logs" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[advanced_order_note_logs]">
                                    <option value="no" <?php selected( $pdf_settings['advanced_order_note_logs'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['advanced_order_note_logs'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_adv_auto_cleanup"><?php esc_html_e( 'Limpeza automatica', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_adv_auto_cleanup" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[advanced_auto_cleanup]">
                                    <option value="yes" <?php selected( $pdf_settings['advanced_auto_cleanup'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings['advanced_auto_cleanup'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_adv_danger_zone"><?php esc_html_e( 'Danger zone / ferramentas destrutivas', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_adv_danger_zone" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[advanced_danger_zone]">
                                    <option value="no" <?php selected( $pdf_settings['advanced_danger_zone'], 'no' ); ?>><?php esc_html_e( 'Bloqueado', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['advanced_danger_zone'], 'yes' ); ?>><?php esc_html_e( 'Liberado', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                        </div>
                    </details>

                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Status e links do modulo', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__status-grid">
                            <div class="eop-pdf-admin__status-card">
                                <span><?php esc_html_e( 'Pagina do pedido', EOP_TEXT_DOMAIN ); ?></span>
                                <?php if ( $order_page_url ) : ?>
                                    <a href="<?php echo esc_url( $order_page_url ); ?>" target="_blank"><?php echo esc_html( $order_page_url ); ?></a>
                                <?php else : ?>
                                    <strong><?php esc_html_e( 'Nao configurada', EOP_TEXT_DOMAIN ); ?></strong>
                                <?php endif; ?>
                            </div>
                            <div class="eop-pdf-admin__status-card">
                                <span><?php esc_html_e( 'Pagina da proposta', EOP_TEXT_DOMAIN ); ?></span>
                                <?php if ( $proposal_url ) : ?>
                                    <a href="<?php echo esc_url( $proposal_url ); ?>" target="_blank"><?php echo esc_html( $proposal_url ); ?></a>
                                <?php else : ?>
                                    <strong><?php esc_html_e( 'Nao configurada', EOP_TEXT_DOMAIN ); ?></strong>
                                <?php endif; ?>
                            </div>
                        </div>
                    </details>
                    <div class="eop-pdf-admin__actions"><?php submit_button( __( 'Salvar configuracoes avancadas', EOP_TEXT_DOMAIN ), 'primary', 'submit', false ); ?></div>
                </form>
            <?php else : ?>
                <div class="eop-pdf-admin__updates">
                    <div class="eop-pdf-admin__notice">
                        <p><?php esc_html_e( 'Esta aba concentra a evolucao do modulo PDF nativo para substituir a dependencia externa com paridade cada vez maior.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-pdf-admin__status-grid">
                        <div class="eop-pdf-admin__status-card"><span><?php esc_html_e( 'Ja entregue', EOP_TEXT_DOMAIN ); ?></span><strong><?php esc_html_e( 'Paginas automaticas, links privados/publicos, numeracao basica, preview lateral e tabs de configuracao.', EOP_TEXT_DOMAIN ); ?></strong></div>
                        <div class="eop-pdf-admin__status-card"><span><?php esc_html_e( 'Proxima camada', EOP_TEXT_DOMAIN ); ?></span><strong><?php esc_html_e( 'Acoes na lista de pedidos, anexos de e-mail, My Account e ferramentas avancadas.', EOP_TEXT_DOMAIN ); ?></strong></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <button type="button" class="eop-pdf-admin__preview-toggle" data-eop-pdf-preview-toggle aria-expanded="false" aria-controls="eop-pdf-preview-drawer">
            <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
            <span><?php esc_html_e( 'Abrir preview', EOP_TEXT_DOMAIN ); ?></span>
        </button>

        <div class="eop-pdf-admin__preview-backdrop" data-eop-pdf-preview-close></div>

        <aside id="eop-pdf-preview-drawer" class="eop-pdf-admin__preview-drawer" aria-hidden="true">
            <div class="eop-pdf-admin__preview-header">
                <div>
                    <strong><?php esc_html_e( 'Preview do documento', EOP_TEXT_DOMAIN ); ?></strong>
                    <span><?php echo esc_html( $current_tab_label ); ?></span>
                </div>
                <button type="button" class="button button-secondary" data-eop-pdf-preview-close><?php esc_html_e( 'Fechar', EOP_TEXT_DOMAIN ); ?></button>
            </div>

            <div class="eop-pdf-admin__preview">
                <form method="get" class="eop-pdf-admin__preview-toolbar">
                    <?php if ( $embedded || EOP_PDF_Admin_Page::is_spa_request() ) : ?>
                        <input type="hidden" name="page" value="eop-pedido-expresso" />
                        <input type="hidden" name="view" value="pdf" />
                        <input type="hidden" name="pdf_tab" value="<?php echo esc_attr( $tab ); ?>" />
                    <?php else : ?>
                        <input type="hidden" name="page" value="<?php echo esc_attr( EOP_PDF_Admin_Page::get_tab_page_slug( $tab ) ); ?>" />
                    <?php endif; ?>
                    <button type="submit" class="button button-primary" formaction="<?php echo esc_url( $preview_pdf_url ); ?>" formmethod="get"<?php echo $preview_pdf_url ? '' : ' disabled'; ?>>PDF</button>
                    <div class="eop-pdf-admin__toolbar-selects">
                        <label>
                            <span><?php esc_html_e( 'Documento', EOP_TEXT_DOMAIN ); ?></span>
                            <select name="document">
                                <option value="order" <?php selected( $document, 'order' ); ?>><?php esc_html_e( 'Pedido', EOP_TEXT_DOMAIN ); ?></option>
                                <option value="proposal" <?php selected( $document, 'proposal' ); ?>><?php esc_html_e( 'Proposta', EOP_TEXT_DOMAIN ); ?></option>
                            </select>
                        </label>
                        <label>
                            <span><?php echo esc_html( $preview_mode ); ?></span>
                            <select name="preview_order">
                                <option value="0"><?php esc_html_e( 'Ultimo pedido', EOP_TEXT_DOMAIN ); ?></option>
                                <?php foreach ( $recent_orders as $order_option ) : ?>
                                    <option value="<?php echo esc_attr( $order_option->get_id() ); ?>" <?php selected( $preview_order && $preview_order->get_id() === $order_option->get_id() ); ?>>
                                        <?php echo esc_html( '#' . $order_option->get_id() . ' - ' . trim( $order_option->get_billing_first_name() . ' ' . $order_option->get_billing_last_name() ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </form>

                <div class="eop-pdf-admin__preview-body">
                    <?php if ( $preview_allowed ) : ?>
                        <?php echo EOP_Document_Manager::get_preview_html( $preview_order, $document ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php elseif ( $preview_order instanceof WC_Order ) : ?>
                        <div class="eop-pdf-admin__empty-preview">
                            <strong><?php esc_html_e( 'Preview HTML desativado no modo avancado.', EOP_TEXT_DOMAIN ); ?></strong>
                            <p><?php esc_html_e( 'Altere a configuracao "Forcar output HTML" em Avancado para voltar a renderizar o preview lateral.', EOP_TEXT_DOMAIN ); ?></p>
                        </div>
                    <?php else : ?>
                        <div class="eop-pdf-admin__empty-preview">
                            <strong><?php esc_html_e( 'Nenhum pedido encontrado para preview.', EOP_TEXT_DOMAIN ); ?></strong>
                            <p><?php esc_html_e( 'Crie um pedido no Pedido Expresso para visualizar o PDF aqui.', EOP_TEXT_DOMAIN ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</div>
