<?php
defined( 'ABSPATH' ) || exit;
?>
<section class="eop-settings-card eop-contract-preview-settings">
    <h2><?php esc_html_e( 'Visual da pagina de upload e produtos', EOP_TEXT_DOMAIN ); ?></h2>
    <p><?php esc_html_e( 'Centralize aqui os textos da etapa final, do anexo salvo, da lista de produtos e a estilizacao de cada elemento antes de validar o preview da pagina publica.', EOP_TEXT_DOMAIN ); ?></p>
    <?php self::render_post_confirmation_upload_products_visual_editor( $settings ); ?>
</section>
<?php if ( class_exists( 'EOP_Post_Confirmation_Flow' ) && method_exists( 'EOP_Post_Confirmation_Flow', 'render_admin_upload_products_preview_markup' ) ) : ?>
    <?php echo EOP_Post_Confirmation_Flow::render_admin_upload_products_preview_markup( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>
