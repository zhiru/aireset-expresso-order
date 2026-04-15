<?php
defined( 'ABSPATH' ) || exit;
$settings = EOP_Settings::get_all();
$font_css = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $settings['font_family'] ) : "'Segoe UI', sans-serif";
?>
<style>
    .eop-wrap {
        font-family: <?php echo esc_attr( $font_css ); ?>;
    }
</style>
<div class="wrap eop-wrap">
    <h1><?php echo esc_html( $settings['panel_title'] ); ?></h1>
    <?php if ( ! empty( $settings['panel_subtitle'] ) ) : ?>
        <p><?php echo esc_html( $settings['panel_subtitle'] ); ?></p>
    <?php endif; ?>

    <div id="eop-notices"></div>

    <div class="eop-grid">

        <!-- Seção 1: Cliente -->
        <div class="eop-card">
            <h2><?php esc_html_e( 'Identificação do Cliente', EOP_TEXT_DOMAIN ); ?></h2>

            <div class="eop-field">
                <label for="eop-document"><?php esc_html_e( 'CPF / CNPJ', EOP_TEXT_DOMAIN ); ?></label>
                <div class="eop-input-group">
                    <input type="text" id="eop-document" placeholder="000.000.000-00" autocomplete="off" />
                    <button type="button" id="eop-search-customer" class="button"><?php esc_html_e( 'Buscar', EOP_TEXT_DOMAIN ); ?></button>
                </div>
                <span id="eop-customer-status" class="eop-status"></span>
            </div>

            <input type="hidden" id="eop-user-id" value="0" />

            <div class="eop-field">
                <label for="eop-name"><?php esc_html_e( 'Nome Completo', EOP_TEXT_DOMAIN ); ?></label>
                <input type="text" id="eop-name" />
            </div>

            <div class="eop-field-row">
                <div class="eop-field">
                    <label for="eop-email"><?php esc_html_e( 'E-mail', EOP_TEXT_DOMAIN ); ?></label>
                    <input type="email" id="eop-email" />
                </div>
                <div class="eop-field">
                    <label for="eop-phone"><?php esc_html_e( 'WhatsApp / Telefone', EOP_TEXT_DOMAIN ); ?></label>
                    <input type="tel" id="eop-phone" />
                </div>
            </div>
        </div>

        <!-- Seção 2: Produtos -->
        <div class="eop-card">
            <h2><?php esc_html_e( 'Produtos', EOP_TEXT_DOMAIN ); ?></h2>

            <div class="eop-field">
                <label for="eop-product-search"><?php esc_html_e( 'Buscar Produto', EOP_TEXT_DOMAIN ); ?></label>
                <select id="eop-product-search" style="width:100%"></select>
            </div>

            <div class="eop-items-list" id="eop-items-body">
                <div class="eop-items-empty"><?php esc_html_e( 'Nenhum produto adicionado.', EOP_TEXT_DOMAIN ); ?></div>
            </div>
        </div>

        <!-- Seção 3: Totais -->
        <div class="eop-card">
            <h2><?php esc_html_e( 'Ajustes e Totais', EOP_TEXT_DOMAIN ); ?></h2>

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
                        <button type="button" id="eop-calc-shipping" class="button button-primary button-hero"><?php esc_html_e( 'Buscar opcoes de frete', EOP_TEXT_DOMAIN ); ?></button>
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
                    <span><?php esc_html_e( 'Subtotal Produtos:', EOP_TEXT_DOMAIN ); ?></span>
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
                <div class="eop-total-row eop-total-grand">
                    <span><?php esc_html_e( 'Total Geral:', EOP_TEXT_DOMAIN ); ?></span>
                    <span id="eop-grand-total">R$ 0,00</span>
                </div>
            </div>
        </div>

        <!-- Seção 4: Ações -->
        <div class="eop-card eop-actions">
            <div class="eop-field">
                <label for="eop-status"><?php esc_html_e( 'Status do Pedido', EOP_TEXT_DOMAIN ); ?></label>
                <select id="eop-status">
                    <option value="completed"><?php esc_html_e( 'Concluído', EOP_TEXT_DOMAIN ); ?></option>
                    <option value="pending"><?php esc_html_e( 'Pendente', EOP_TEXT_DOMAIN ); ?></option>
                    <option value="processing"><?php esc_html_e( 'Processando', EOP_TEXT_DOMAIN ); ?></option>
                    <option value="on-hold"><?php esc_html_e( 'Aguardando', EOP_TEXT_DOMAIN ); ?></option>
                </select>
            </div>

            <button type="button" id="eop-submit" class="button button-primary button-hero">
                <?php esc_html_e( 'Finalizar e Gerar PDF', EOP_TEXT_DOMAIN ); ?>
            </button>
        </div>

    </div><!-- .eop-grid -->

    <!-- Success modal -->
    <div id="eop-success-modal" class="eop-modal" style="display:none;">
        <div class="eop-modal-content">
            <h2><?php esc_html_e( 'Pedido Criado!', EOP_TEXT_DOMAIN ); ?></h2>
            <p id="eop-success-message"></p>
            <div class="eop-modal-actions">
                <a id="eop-pdf-link" href="#" target="_blank" class="button button-primary"><?php esc_html_e( 'Abrir PDF', EOP_TEXT_DOMAIN ); ?></a>
                <a id="eop-public-link" href="#" target="_blank" class="button" style="display:none;"><?php esc_html_e( 'Abrir link do cliente', EOP_TEXT_DOMAIN ); ?></a>
                <a id="eop-order-link" href="#" target="_blank" class="button"><?php esc_html_e( 'Ver Pedido', EOP_TEXT_DOMAIN ); ?></a>
                <button type="button" id="eop-new-order" class="button"><?php esc_html_e( 'Novo Pedido', EOP_TEXT_DOMAIN ); ?></button>
            </div>
        </div>
    </div>
</div>
