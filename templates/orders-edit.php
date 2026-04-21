<?php
defined( 'ABSPATH' ) || exit;

$settings = EOP_Settings::get_all();
$font_css = EOP_Settings::get_font_css_family( $settings['font_family'] );
$order_id = absint( $_GET['order_id'] ?? 0 );
?>
<style>
    .eop-wrap {
        font-family: <?php echo esc_attr( $font_css ); ?>;
    }
</style>
<div class="wrap eop-wrap eop-edit-wrap">
    <h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=eop-pedidos' ) ); ?>" class="eop-back-link">&larr;</a>
        <?php
        printf(
            /* translators: %d: order ID */
            esc_html__( 'Editar Pedido #%d', EOP_TEXT_DOMAIN ),
            $order_id
        );
        ?>
    </h1>

    <div id="eop-notices"></div>

    <input type="hidden" id="eop-edit-order-id" value="<?php echo esc_attr( $order_id ); ?>" />

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
                    <button type="button" id="eop-apply-item-defaults" class="button"><?php esc_html_e( 'Aplicar', EOP_TEXT_DOMAIN ); ?></button>
                </div>
            </div>

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
                <input type="text" id="eop-discount" class="eop-discount-text-input" value="" placeholder="10% ou 10" />
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
                    <option value="cancelled"><?php esc_html_e( 'Cancelado', EOP_TEXT_DOMAIN ); ?></option>
                </select>
            </div>

            <div class="eop-field">
                <label for="eop-note"><?php esc_html_e( 'Nota interna (opcional)', EOP_TEXT_DOMAIN ); ?></label>
                <textarea id="eop-note" rows="2" placeholder="<?php esc_attr_e( 'Ex: Cliente pediu troca de cor...', EOP_TEXT_DOMAIN ); ?>"></textarea>
            </div>

            <button type="button" id="eop-save-order" class="button button-primary button-hero">
                <?php esc_html_e( 'Salvar alteracoes', EOP_TEXT_DOMAIN ); ?>
            </button>

            <p class="eop-edit-links">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=eop-pedidos' ) ); ?>"><?php esc_html_e( 'Voltar para lista', EOP_TEXT_DOMAIN ); ?></a>
                &nbsp;|&nbsp;
                <a href="<?php echo esc_url( get_edit_post_link( $order_id ) ?: admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ); ?>" target="_blank"><?php esc_html_e( 'Abrir no WooCommerce', EOP_TEXT_DOMAIN ); ?></a>
            </p>
        </div>

    </div><!-- .eop-grid -->
</div>
