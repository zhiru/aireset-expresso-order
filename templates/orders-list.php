<?php
defined( 'ABSPATH' ) || exit;

$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'any';
$per_page = 20;

$result = EOP_Orders_Page::get_orders( array(
    'limit'  => $per_page,
    'paged'  => $paged,
    'status' => $status,
    'search' => $search,
) );

$orders      = $result->orders;
$total       = $result->total;
$total_pages = $result->max_num_pages;
$statuses    = wc_get_order_statuses();
?>
<div class="wrap eop-wrap eop-orders-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Pedidos Expresso', EOP_TEXT_DOMAIN ); ?></h1>
    <hr class="wp-header-end" />

    <form method="get" class="eop-orders-filters">
        <input type="hidden" name="page" value="eop-pedidos" />

        <div class="eop-orders-filters__row">
            <select name="status">
                <option value="any" <?php selected( $status, 'any' ); ?>><?php esc_html_e( 'Todos os status', EOP_TEXT_DOMAIN ); ?></option>
                <?php foreach ( $statuses as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( str_replace( 'wc-', '', $key ) ); ?>" <?php selected( $status, str_replace( 'wc-', '', $key ) ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>

            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar por # ou nome...', EOP_TEXT_DOMAIN ); ?>" />

            <button type="submit" class="button"><?php esc_html_e( 'Filtrar', EOP_TEXT_DOMAIN ); ?></button>
        </div>
    </form>

    <?php if ( empty( $orders ) ) : ?>
        <div class="eop-orders-empty">
            <p><?php esc_html_e( 'Nenhum pedido encontrado.', EOP_TEXT_DOMAIN ); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped eop-orders-table">
            <thead>
                <tr>
                    <th class="eop-orders-col-id"><?php esc_html_e( 'Pedido', EOP_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Cliente', EOP_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Status', EOP_TEXT_DOMAIN ); ?></th>
                    <th class="eop-orders-col-total"><?php esc_html_e( 'Total', EOP_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Data', EOP_TEXT_DOMAIN ); ?></th>
                    <th class="eop-orders-col-actions"><?php esc_html_e( 'Acoes', EOP_TEXT_DOMAIN ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $orders as $order ) : ?>
                    <?php
                    $order_id    = $order->get_id();
                    $name        = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
                    $email       = $order->get_billing_email();
                    $status_obj  = wc_get_order_status_name( $order->get_status() );
                    $total_val   = $order->get_total();
                    $date        = $order->get_date_created();
                    $wc_url      = $order->get_edit_order_url();
                    $public_url  = 'yes' === $order->get_meta( '_eop_is_proposal' ) ? EOP_Public_Proposal::get_public_link( $order ) : '';
                    $is_proposal = 'yes' === $order->get_meta( '_eop_is_proposal' );
                    ?>
                    <tr>
                        <td class="eop-orders-col-id">
                            <strong>#<?php echo esc_html( $order_id ); ?></strong>
                            <?php if ( $is_proposal ) : ?>
                                <span class="eop-orders-badge eop-orders-badge--proposal"><?php esc_html_e( 'Proposta', EOP_TEXT_DOMAIN ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="eop-orders-customer-name"><?php echo esc_html( $name ?: '—' ); ?></span>
                            <?php if ( $email ) : ?>
                                <br><small><?php echo esc_html( $email ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <mark class="order-status status-<?php echo esc_attr( $order->get_status() ); ?>">
                                <span><?php echo esc_html( $status_obj ); ?></span>
                            </mark>
                        </td>
                        <td class="eop-orders-col-total"><?php echo wp_kses_post( wc_price( $total_val ) ); ?></td>
                        <td><?php echo $date ? esc_html( $date->date_i18n( 'd/m/Y H:i' ) ) : '—'; ?></td>
                        <td class="eop-orders-col-actions">
                            <?php if ( $public_url ) : ?>
                                <a href="<?php echo esc_url( $public_url ); ?>" class="button button-small" target="_blank" title="<?php esc_attr_e( 'Abrir proposta', EOP_TEXT_DOMAIN ); ?>"><?php esc_html_e( 'Proposta', EOP_TEXT_DOMAIN ); ?></a>
                            <?php endif; ?>
                            <a href="<?php echo esc_url( $wc_url ); ?>" class="button button-small" target="_blank" title="<?php esc_attr_e( 'Ver no WC', EOP_TEXT_DOMAIN ); ?>"><?php esc_html_e( 'WC', EOP_TEXT_DOMAIN ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php
                        printf(
                            esc_html( _n( '%s item', '%s itens', $total, EOP_TEXT_DOMAIN ) ),
                            esc_html( number_format_i18n( $total ) )
                        );
                        ?>
                    </span>
                    <?php
                    echo wp_kses_post(
                        paginate_links( array(
                            'base'      => add_query_arg( 'paged', '%#%' ),
                            'format'    => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total'     => $total_pages,
                            'current'   => $paged,
                        ) )
                    );
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
