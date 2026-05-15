<?php
defined( 'ABSPATH' ) || exit;
?>
<section class="eop-settings-card eop-contract-preview-settings">
    <h2><?php esc_html_e( 'Visual da pagina contratual', EOP_TEXT_DOMAIN ); ?></h2>
    <p><?php esc_html_e( 'Centralize aqui os textos do contrato, do resumo lateral, da jornada e a estilizacao de cada bloco antes de validar o preview da pagina publica.', EOP_TEXT_DOMAIN ); ?></p>
    <?php self::render_post_confirmation_contract_visual_editor( $settings ); ?>
</section>
<?php if ( class_exists( 'EOP_Post_Confirmation_Flow' ) && method_exists( 'EOP_Post_Confirmation_Flow', 'render_admin_contract_preview_markup' ) ) : ?>
    <?php echo EOP_Post_Confirmation_Flow::render_admin_contract_preview_markup( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>
