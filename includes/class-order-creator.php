<?php
defined( 'ABSPATH' ) || exit;

class EOP_Order_Creator {

    use EOP_License_Guard;

    public static function reload_and_recalculate( WC_Order $order ) {
        if ( ! self::_validate_session_tokens() ) {
            return $order;
        }

        $order->save();

        $reloaded = wc_get_order( $order->get_id() );
        if ( ! $reloaded instanceof WC_Order ) {
            return $order;
        }

        $reloaded->calculate_totals();
        self::sync_order_totals( $reloaded );

        return $reloaded;
    }

    public static function sync_order_totals( WC_Order $order ) {
        $items_subtotal     = 0.0;
        $items_total        = 0.0;
        $item_discount      = 0.0;
        $shipping_total     = 0.0;
        $fees_total         = 0.0;
        $fee_discount_total = 0.0;

        foreach ( $order->get_items( 'line_item' ) as $item ) {
            $line_subtotal = (float) $item->get_subtotal();
            $line_total    = (float) $item->get_total();

            $items_subtotal += $line_subtotal;
            $items_total    += $line_total;
            $item_discount  += max( 0, $line_subtotal - $line_total );
        }

        foreach ( $order->get_shipping_methods() as $shipping ) {
            $shipping_total += (float) $shipping->get_total();
        }

        foreach ( $order->get_fees() as $fee ) {
            $fee_total   = (float) $fee->get_total();
            $fees_total += $fee_total;

            if ( $fee_total < 0 ) {
                $fee_discount_total += abs( $fee_total );
            }
        }

        $cart_tax     = (float) $order->get_cart_tax();
        $shipping_tax = (float) $order->get_shipping_tax();
        $discount     = round( $item_discount + $fee_discount_total, wc_get_price_decimals() );
        $grand_total  = round( max( 0, $items_total + $fees_total + $shipping_total + $cart_tax + $shipping_tax ), wc_get_price_decimals() );

        $needs_save = false;

        if ( abs( (float) $order->get_discount_total() - $discount ) > 0.009 ) {
            $order->set_discount_total( $discount );
            $needs_save = true;
        }

        if ( abs( (float) $order->get_shipping_total() - $shipping_total ) > 0.009 ) {
            $order->set_shipping_total( $shipping_total );
            $needs_save = true;
        }

        if ( abs( (float) $order->get_total() - $grand_total ) > 0.009 ) {
            $order->set_total( $grand_total );
            $needs_save = true;
        }

        if ( $needs_save ) {
            $order->save();
        }

        return array(
            'items_subtotal' => $items_subtotal,
            'items_total'    => $items_total,
            'shipping_total' => $shipping_total,
            'fees_total'     => $fees_total,
            'discount_total' => $discount,
            'grand_total'    => $grand_total,
        );
    }

    /**
     * Create WooCommerce order from submitted data.
     *
     * @param array $data {
     *     @type array  $customer { name, email, phone, document, user_id }
     *     @type array  $items    [ { product_id, quantity } ]
     *     @type float  $shipping
     *     @type float  $discount
     *     @type string $discount_type 'fixed' or 'percent'
     *     @type string $status   'completed' or 'pending'
     * }
     * @return array|WP_Error
     */
    public static function create( $data ) {
        $flow_mode = EOP_Settings::get( 'flow_mode', 'proposal' );

        // --- Validate items ---
        $items = $data['items'] ?? array();
        if ( empty( $items ) || ! is_array( $items ) ) {
            return new WP_Error( 'no_items', __( 'Adicione ao menos um produto.', EOP_TEXT_DOMAIN ) );
        }

        // --- Validate customer ---
        $customer = $data['customer'] ?? array();
        $name     = sanitize_text_field( $customer['name'] ?? '' );
        $email    = sanitize_email( $customer['email'] ?? '' );
        $phone    = sanitize_text_field( $customer['phone'] ?? '' );
        $document = preg_replace( '/[^0-9]/', '', sanitize_text_field( $customer['document'] ?? '' ) );
        $phone    = preg_replace( '/[^0-9+\(\)\-\s]/', '', $phone );
        $shipping_address = isset( $data['shipping_address'] ) && is_array( $data['shipping_address'] ) ? $data['shipping_address'] : array();
        $selected_shipping_rate = isset( $data['shipping_rate'] ) && is_array( $data['shipping_rate'] ) ? $data['shipping_rate'] : array();

        // All customer fields are optional.

        // --- Resolve or create user ---
        $user_id = isset( $customer['user_id'] ) ? absint( $customer['user_id'] ) : 0;

        if ( ! $user_id && $email ) {
            $existing = get_user_by( 'email', $email );
            if ( $existing ) {
                $user_id = $existing->ID;
            }
        }

        if ( ! $user_id && $email ) {
            $user_id = self::create_customer( $name, $email, $phone, $document );
            if ( is_wp_error( $user_id ) ) {
                return $user_id;
            }
        }

        // --- Create order ---
        $order = wc_create_order( array(
            'customer_id' => $user_id,
            'created_via' => 'aireset-expresso-order',
        ) );

        if ( is_wp_error( $order ) ) {
            return $order;
        }

        $valid_items = 0;
        $items_subtotal = 0;

        // --- Add line items ---
        foreach ( $items as $item ) {
            $product_id = absint( $item['product_id'] ?? 0 );
            $quantity   = max( 1, absint( $item['quantity'] ?? 1 ) );
            $product    = wc_get_product( $product_id );

            if ( ! $product || ! $product->exists() ) {
                continue;
            }

            $line_item_id = $order->add_product( $product, $quantity );
            $valid_items++;

            // --- Per-item discount ---
            $item_disc_type  = in_array( $item['discount_type'] ?? 'fixed', array( 'fixed', 'percent' ), true ) ? $item['discount_type'] : 'fixed';
            $item_disc_value = floatval( $item['discount_value'] ?? 0 );
            $item_disc_value = max( 0, $item_disc_value );

            if ( $item_disc_value > 0 && $line_item_id ) {
                $line_total = (float) $product->get_price() * $quantity;

                if ( 'percent' === $item_disc_type ) {
                    $disc_amount = min( $line_total, $line_total * $item_disc_value / 100 );
                } else {
                    $disc_amount = min( $line_total, $item_disc_value );
                }

                if ( $disc_amount > 0 ) {
                    $line_item = $order->get_item( $line_item_id );
                    if ( $line_item ) {
                        $line_item->set_subtotal( $line_total );
                        $line_item->set_total( $line_total - $disc_amount );
                        $line_item->save();
                    }
                    $items_subtotal += ( $line_total - $disc_amount );
                } else {
                    $items_subtotal += (float) $product->get_price() * $quantity;
                }
            } else {
                $items_subtotal += (float) $product->get_price() * $quantity;
            }
        }

        if ( 0 === $valid_items ) {
            $order->delete( true );
            return new WP_Error( 'invalid_items', __( 'Nenhum produto valido foi encontrado para criar o pedido.', EOP_TEXT_DOMAIN ) );
        }

        // --- Billing data (all optional) ---
        if ( $name ) {
            $name_parts = explode( ' ', $name, 2 );
            $order->set_billing_first_name( $name_parts[0] );
            $order->set_billing_last_name( $name_parts[1] ?? '' );
        }
        if ( $email ) {
            $order->set_billing_email( $email );
        }
        if ( $phone ) {
            $order->set_billing_phone( $phone );
        }

        if ( ! empty( $shipping_address ) ) {
            $shipping_line_2_parts = array_filter( array(
                sanitize_text_field( $shipping_address['number'] ?? '' ),
                sanitize_text_field( $shipping_address['neighborhood'] ?? '' ),
                sanitize_text_field( $shipping_address['address_2'] ?? '' ),
            ) );

            $order->set_shipping_first_name( $order->get_billing_first_name() );
            $order->set_shipping_last_name( $order->get_billing_last_name() );
            $order->set_shipping_country( sanitize_text_field( $shipping_address['country'] ?? 'BR' ) );
            $order->set_shipping_state( sanitize_text_field( $shipping_address['state'] ?? '' ) );
            $order->set_shipping_postcode( preg_replace( '/[^0-9]/', '', (string) ( $shipping_address['postcode'] ?? '' ) ) );
            $order->set_shipping_city( sanitize_text_field( $shipping_address['city'] ?? '' ) );
            $order->set_shipping_address_1( sanitize_text_field( $shipping_address['address'] ?? '' ) );
            $order->set_shipping_address_2( implode( ' | ', $shipping_line_2_parts ) );
            $order->update_meta_data( '_shipping_number', sanitize_text_field( $shipping_address['number'] ?? '' ) );
            $order->update_meta_data( '_shipping_neighborhood', sanitize_text_field( $shipping_address['neighborhood'] ?? '' ) );
        }

        // Brazilian meta fields (WooCommerce Extra Checkout Fields for Brazil).
        if ( $document ) {
            $meta_key = strlen( $document ) <= 11 ? '_billing_cpf' : '_billing_cnpj';
            $order->update_meta_data( $meta_key, $document );
            $order->update_meta_data( '_billing_persontype', strlen( $document ) <= 11 ? '1' : '2' );
        }

        // --- Shipping ---
        $shipping_value = floatval( $data['shipping'] ?? 0 );
        $shipping_value = max( 0, $shipping_value );
        if ( $shipping_value > 0 ) {
            $shipping_item = new WC_Order_Item_Shipping();
            $shipping_item->set_method_title( sanitize_text_field( $selected_shipping_rate['label'] ?? __( 'Frete calculado', EOP_TEXT_DOMAIN ) ) );
            $shipping_item->set_method_id( sanitize_text_field( $selected_shipping_rate['id'] ?? 'flat_rate' ) );
            $shipping_item->set_total( $shipping_value );

            if ( ! empty( $selected_shipping_rate['instance_id'] ) ) {
                $shipping_item->set_instance_id( absint( $selected_shipping_rate['instance_id'] ) );
            }

            if ( ! empty( $selected_shipping_rate['meta_data'] ) && is_array( $selected_shipping_rate['meta_data'] ) ) {
                foreach ( $selected_shipping_rate['meta_data'] as $meta_key => $meta_value ) {
                    if ( is_scalar( $meta_value ) && '' !== (string) $meta_value ) {
                        $shipping_item->add_meta_data( sanitize_text_field( $meta_key ), sanitize_text_field( (string) $meta_value ) );
                    }
                }
            }

            $order->add_item( $shipping_item );
        }

        // --- Discount (as negative fee) ---
        $discount_value = floatval( $data['discount'] ?? 0 );
        $discount_value = max( 0, $discount_value );
        $discount_type  = in_array( $data['discount_type'] ?? 'fixed', array( 'fixed', 'percent' ), true ) ? $data['discount_type'] : 'fixed';

        if ( $discount_value > 0 ) {
            if ( 'percent' === $discount_type ) {
                $discount_abs = $items_subtotal * $discount_value / 100;
            } else {
                $discount_abs = $discount_value;
            }
            $discount_abs = max( 0, $discount_abs );

            if ( $discount_abs > 0 ) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name( __( 'Desconto manual', EOP_TEXT_DOMAIN ) );
                $fee->set_amount( -1 * $discount_abs );
                $fee->set_total( -1 * $discount_abs );
                $order->add_item( $fee );
            }
        }

        // --- Calculate & save ---
        $order = self::reload_and_recalculate( $order );

        $status = sanitize_text_field( $data['status'] ?? 'completed' );
        $allowed = array( 'completed', 'pending', 'processing', 'on-hold' );
        if ( ! in_array( $status, $allowed, true ) ) {
            $status = 'completed';
        }

        if ( 'proposal' === $flow_mode ) {
            $status = 'pending';
        }

        $order->set_status( $status );
        $order->update_meta_data( '_eop_source', 'aireset-expresso-order' );
        $order->update_meta_data( '_eop_created_by', get_current_user_id() );
        $order->update_meta_data( '_eop_created_by_name', wp_get_current_user()->display_name );
        $order->add_order_note( __( 'Pedido criado via Aireset Expresso Order.', EOP_TEXT_DOMAIN ) );

        if ( 'proposal' === $flow_mode ) {
            EOP_Public_Proposal::create_public_token( $order );
            $order->add_order_note( __( 'Link publico da proposta gerado para o cliente.', EOP_TEXT_DOMAIN ) );
        }

        $order->save();

        // Update user meta if we have a user.
        if ( $user_id ) {
            if ( $document ) {
                $meta_key = strlen( $document ) <= 11 ? '_billing_cpf' : '_billing_cnpj';
                update_user_meta( $user_id, $meta_key, $document );
            }
            if ( $phone ) {
                update_user_meta( $user_id, 'billing_phone', $phone );
            }
            if ( $name ) {
                $name_parts = explode( ' ', $name, 2 );
                update_user_meta( $user_id, 'billing_first_name', $name_parts[0] );
                update_user_meta( $user_id, 'billing_last_name', $name_parts[1] ?? '' );
            }
            if ( $email ) {
                update_user_meta( $user_id, 'billing_email', $email );
            }
        }

        // --- PDF URL ---
        $pdf_url = self::get_pdf_document_url( $order, 'proposal' === $flow_mode ? 'proposal' : 'order' );
        $public_url = 'proposal' === $flow_mode ? EOP_Public_Proposal::get_public_link( $order ) : '';

        return array(
            'order_id'   => $order->get_id(),
            'order_url'  => $order->get_edit_order_url(),
            'pdf_url'    => $pdf_url,
            'public_url' => $public_url,
            'flow_mode'  => $flow_mode,
        );
    }

    /**
     * Create a new WP customer user.
     *
     * @param string $name
     * @param string $email
     * @param string $phone
     * @param string $document
     * @return int|WP_Error
     */
    private static function create_customer( $name, $email, $phone, $document ) {
        $base_username = $name ? strtolower( str_replace( ' ', '.', $name ) ) : current( explode( '@', $email ) );
        $username      = sanitize_user( $base_username );

        if ( '' === $username ) {
            $username = 'cliente_' . wp_rand( 1000, 9999 );
        }

        if ( username_exists( $username ) ) {
            $username = $username . '_' . wp_rand( 100, 999 );
        }

        $password = wp_generate_password( 12, true );

        $user_id = wp_insert_user( array(
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $name,
            'role'         => 'customer',
        ) );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        if ( $document ) {
            $meta_key = strlen( $document ) <= 11 ? '_billing_cpf' : '_billing_cnpj';
            update_user_meta( $user_id, $meta_key, $document );
        }
        update_user_meta( $user_id, 'billing_phone', $phone );

        $name_parts = explode( ' ', $name, 2 );
        update_user_meta( $user_id, 'billing_first_name', $name_parts[0] );
        update_user_meta( $user_id, 'billing_last_name', $name_parts[1] ?? '' );
        update_user_meta( $user_id, 'billing_email', $email );

        return $user_id;
    }

    /**
     * Get PDF document URL from the native Aireset document manager.
     *
     * @param WC_Order $order
     * @param string   $document_type
     * @return string
     */
    public static function get_pdf_document_url( $order, $document_type = '' ) {
        if ( $order instanceof WC_Order && class_exists( 'EOP_Document_Manager' ) ) {
            return EOP_Document_Manager::get_pdf_document_url( $order, $document_type );
        }

        return $order->get_edit_order_url();
    }
}
