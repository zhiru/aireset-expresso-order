/* global jQuery, eop_orders_vars */
(function ($) {
    'use strict';

    var items = [];
    var i18n = eop_orders_vars.i18n || {};
    var selectedShippingRate = null;
    var shippingAddress = {};
    var postcodeLookupCache = {};
    var postcodeLookupBusy = false;

    /* ========================================
     *  Utilities
     * ======================================== */

    function formatBRL(value) {
        return 'R$ ' + parseFloat(value || 0).toFixed(2).replace('.', ',');
    }

    function parseDiscountInput(rawValue) {
        var raw = String(rawValue || '').trim().replace(',', '.');
        var hasPercent = raw.indexOf('%') !== -1;
        var numeric = parseFloat(raw.replace(/[^0-9.\-]/g, ''));

        if (isNaN(numeric) || numeric <= 0) {
            return { type: 'fixed', value: 0 };
        }

        return {
            type: hasPercent ? 'percent' : 'fixed',
            value: Math.max(0, numeric)
        };
    }

    function formatDiscountInput(type, value) {
        var numeric = parseFloat(value) || 0;

        if (numeric <= 0) {
            return '';
        }

        return type === 'percent' ? (String(numeric).replace('.', ',') + '%') : String(numeric).replace('.', ',');
    }

    function sanitizePostcode(value) {
        return (value || '').replace(/\D/g, '').slice(0, 8);
    }

    function formatPostcode(value) {
        var digits = sanitizePostcode(value);
        if (digits.length <= 5) return digits;
        return digits.slice(0, 5) + '-' + digits.slice(5);
    }

    function showNotice(message, type) {
        var cls = type === 'error' ? 'notice-error' : 'notice-success';
        var html = '<div class="notice ' + cls + ' is-dismissible"><p>' + $('<span>').text(message).html() + '</p></div>';
        $('#eop-notices').html(html);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /* ========================================
     *  Discount helpers (same as admin.js)
     * ======================================== */

    function calcItemDiscount(item) {
        var lineTotal = item.price * item.quantity;
        var discVal = parseFloat(item.discount_value) || 0;
        if (discVal <= 0) return 0;
        if (item.discount_type === 'percent') {
            return Math.min(lineTotal, lineTotal * discVal / 100);
        }
        return Math.min(lineTotal, discVal);
    }

    function recalcTotals() {
        var subtotal = 0;
        var itemDiscounts = 0;

        items.forEach(function (item) {
            var lineTotal = item.price * item.quantity;
            subtotal += lineTotal;
            itemDiscounts += calcItemDiscount(item);
        });

        var afterItemDisc = subtotal - itemDiscounts;
        var shipping = parseFloat($('#eop-shipping').val()) || 0;
        var discountParsed = parseDiscountInput($('#eop-discount').val());
        var discountType = discountParsed.type;
        var discountInput = discountParsed.value;
        var generalDiscount = 0;

        if (discountInput > 0) {
            if (discountType === 'percent') {
                generalDiscount = afterItemDisc * discountInput / 100;
            } else {
                generalDiscount = discountInput;
            }
        }

        var totalDiscount = itemDiscounts + generalDiscount;
        var grand = Math.max(0, subtotal + shipping - totalDiscount);

        $('#eop-subtotal').text(formatBRL(subtotal));
        $('#eop-shipping-total').text(formatBRL(shipping));
        $('#eop-discount-total').text('- ' + formatBRL(totalDiscount));
        $('#eop-grand-total').text(formatBRL(grand));
    }

    function updateCardSubtotal($card, idx) {
        var item = items[idx];
        var lineTotal = item.price * item.quantity;
        var disc = calcItemDiscount(item);
        var sub = lineTotal - disc;
        $card.find('.eop-item-card__subtotal').text(formatBRL(sub));
    }

    function renderItems() {
        var $body = $('#eop-items-body');
        $body.empty();

        if (!items.length) {
            $body.append('<div class="eop-items-empty">' + (i18n.no_items || 'Nenhum produto adicionado.') + '</div>');
            recalcTotals();
            return;
        }

        items.forEach(function (item, idx) {
            var lineTotal = item.price * item.quantity;
            var disc = calcItemDiscount(item);
            var sub = lineTotal - disc;
            var discType = item.discount_type || 'fixed';
            var discVal = parseFloat(item.discount_value) || 0;
            var imgSrc = item.image || '';
            var nameHtml = $('<span>').text(item.name + (item.sku ? ' [' + item.sku + ']' : '')).html();

            var card = '<div class="eop-item-card" data-idx="' + idx + '">' +
                '<a href="#" class="eop-remove-item" title="Remover">&times;</a>' +
                '<div class="eop-item-card__left">' +
                    (imgSrc ? '<img src="' + $('<span>').text(imgSrc).html() + '" alt="" class="eop-item-card__img" />' : '') +
                '</div>' +
                '<div class="eop-item-card__right">' +
                    '<div class="eop-item-card__name">' + nameHtml + '</div>' +
                    '<div class="eop-item-card__fields">' +
                        '<div class="eop-item-card__field">' +
                            '<span class="eop-item-card__label">Preço</span>' +
                            '<span class="eop-item-card__value">' + formatBRL(item.price) + '</span>' +
                        '</div>' +
                        '<div class="eop-item-card__field">' +
                            '<span class="eop-item-card__label">Qtd</span>' +
                            '<input type="number" class="eop-qty" min="1" value="' + item.quantity + '" />' +
                        '</div>' +
                        '<div class="eop-item-card__field">' +
                            '<span class="eop-item-card__label">Desconto</span>' +
                            '<div class="eop-item-discount-group">' +
                                '<input type="text" class="eop-item-discount" value="' + $('<span>').text(formatDiscountInput(discType, discVal)).html() + '" placeholder="10% ou 10" />' +
                            '</div>' +
                        '</div>' +
                        '<div class="eop-item-card__field">' +
                            '<span class="eop-item-card__label">Subtotal</span>' +
                            '<span class="eop-item-card__value eop-item-card__subtotal">' + formatBRL(sub) + '</span>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

            $body.append(card);
        });

        recalcTotals();
    }

    /* ========================================
     *  Shipping panel helpers
     * ======================================== */

    function getShippingPanelElements() {
        return {
            toggle: $('#eop-shipping-toggle'),
            panel: $('#eop-shipping-panel'),
            icon: $('.eop-shipping-toggle__icon'),
            summary: $('#eop-shipping-summary'),
            status: $('#eop-shipping-address-status'),
            rates: $('#eop-shipping-rates')
        };
    }

    function setShippingPanelState(isOpen) {
        var ui = getShippingPanelElements();
        if (!ui.panel.length || !ui.toggle.length) return;
        ui.panel.attr('hidden', !isOpen);
        ui.toggle.attr('aria-expanded', isOpen ? 'true' : 'false');
        if (ui.icon.length) ui.icon.text(isOpen ? '−' : '+');
    }

    function setShippingSummary(message) {
        var ui = getShippingPanelElements();
        if (ui.summary.length) ui.summary.text(message);
    }

    function setShippingAddressStatus(message, type) {
        var ui = getShippingPanelElements();
        var cssClass = 'eop-shipping-panel__status';
        if (!ui.status.length) return;
        if (type) cssClass += ' is-' + type;
        ui.status.text(message || '').attr('class', cssClass);
    }

    function clearShippingSelection(resetValue) {
        var ui = getShippingPanelElements();
        ui.rates.empty();
        selectedShippingRate = null;
        if (resetValue) {
            $('#eop-shipping').val(0);
            setShippingSummary(i18n.shipping_summary_default || 'Clique para calcular com o endereco do cliente.');
        } else {
            setShippingSummary(i18n.shipping_summary_pending || 'Preencha o endereco e escolha uma opcao de frete.');
        }
        recalcTotals();
    }

    function getShippingAddress() {
        return {
            country: 'BR',
            postcode: sanitizePostcode($('#eop-shipping-postcode').val().trim()),
            state: $('#eop-shipping-state').val().trim(),
            city: $('#eop-shipping-city').val().trim(),
            address: $('#eop-shipping-address').val().trim(),
            number: $('#eop-shipping-number').val().trim(),
            neighborhood: $('#eop-shipping-neighborhood').val().trim(),
            address_2: $('#eop-shipping-address-2').val().trim()
        };
    }

    function fillAddressFromPostcode(data) {
        if (data.uf) $('#eop-shipping-state').val(data.uf);
        if (data.localidade) $('#eop-shipping-city').val(data.localidade);
        if (data.logradouro) $('#eop-shipping-address').val(data.logradouro);
        if (data.bairro) $('#eop-shipping-neighborhood').val(data.bairro);
    }

    function lookupPostcode(force) {
        var postcode = sanitizePostcode($('#eop-shipping-postcode').val());
        $('#eop-shipping-postcode').val(formatPostcode(postcode));

        if (postcode.length !== 8) {
            if (force) setShippingAddressStatus(i18n.shipping_postcode_invalid || 'Digite um CEP valido com 8 numeros.', 'error');
            return;
        }
        if (postcodeLookupBusy) return;
        if (postcodeLookupCache[postcode]) {
            fillAddressFromPostcode(postcodeLookupCache[postcode]);
            setShippingAddressStatus(i18n.shipping_postcode_found || 'Endereco encontrado.', 'success');
            return;
        }

        postcodeLookupBusy = true;
        setShippingAddressStatus(i18n.shipping_postcode_loading || 'Buscando endereco pelo CEP...', 'loading');

        fetch('https://viacep.com.br/ws/' + postcode + '/json/')
            .then(function (r) { if (!r.ok) throw new Error('fail'); return r.json(); })
            .then(function (data) {
                postcodeLookupBusy = false;
                if (!data || data.erro) {
                    setShippingAddressStatus(i18n.shipping_postcode_not_found || 'CEP nao encontrado.', 'error');
                    return;
                }
                postcodeLookupCache[postcode] = data;
                fillAddressFromPostcode(data);
                setShippingAddressStatus(i18n.shipping_postcode_found || 'Endereco encontrado.', 'success');
            })
            .catch(function () {
                postcodeLookupBusy = false;
                setShippingAddressStatus(i18n.shipping_postcode_error || 'Erro ao buscar CEP.', 'error');
            });
    }

    function renderShippingRates(packages) {
        var html = '';
        packages.forEach(function (pkg, pkgIndex) {
            html += '<div class="eop-shipping-group">';
            html += '<div class="eop-shipping-group__title">' + $('<span>').text(pkg.package_name).html() + '</div>';
            pkg.rates.forEach(function (rate, rateIndex) {
                var details = [];
                if (rate.meta_data) {
                    Object.keys(rate.meta_data).forEach(function (key) { details.push(rate.meta_data[key]); });
                }
                html += '<label class="eop-shipping-rate">';
                html += '<input type="radio" name="eop_shipping_rate_' + pkgIndex + '" value="' + $('<span>').text(JSON.stringify(rate)).html() + '" ' + (pkgIndex === 0 && rateIndex === 0 ? 'checked' : '') + '>';
                html += '<span class="eop-shipping-rate__content">';
                html += '<strong>' + $('<span>').text(rate.label).html() + '</strong>';
                html += '<em>' + formatBRL(rate.cost) + '</em>';
                if (details.length) html += '<small>' + $('<span>').text(details.join(' | ')).html() + '</small>';
                html += '</span></label>';
            });
            html += '</div>';
        });
        $('#eop-shipping-rates').html(html);
        setShippingSummary(i18n.shipping_summary_ready || 'Escolha a opcao de frete.');
        $('input[name^="eop_shipping_rate_"]').first().trigger('change');
    }

    /* ========================================
     *  Load order data on edit page
     * ======================================== */

    function loadOrder() {
        var orderId = $('#eop-edit-order-id').val();
        if (!orderId) return;

        $.post(eop_orders_vars.ajax_url, {
            action: 'eop_load_order',
            nonce: eop_orders_vars.nonce,
            order_id: orderId
        }, function (res) {
            if (!res.success) {
                showNotice(res.data && res.data.message ? res.data.message : 'Erro ao carregar pedido.', 'error');
                return;
            }

            var d = res.data;

            // Customer
            $('#eop-document').val(d.customer.document || '');
            $('#eop-name').val(d.customer.name || '');
            $('#eop-email').val(d.customer.email || '');
            $('#eop-phone').val(d.customer.phone || '');
            $('#eop-user-id').val(d.customer.user_id || 0);

            // Items
            items = (d.items || []).map(function (item) {
                return {
                    product_id: item.product_id,
                    name: item.name,
                    sku: item.sku || '',
                    price: parseFloat(item.price) || 0,
                    image: item.image || '',
                    quantity: parseInt(item.quantity, 10) || 1,
                    discount_type: item.discount_type || 'fixed',
                    discount_value: parseFloat(item.discount_value) || 0
                };
            });
            renderItems();

            // Shipping
            $('#eop-shipping').val(d.shipping || 0);
            if (d.shipping_method) {
                setShippingSummary(d.shipping_method + ' • ' + formatBRL(d.shipping));
            }

            // Shipping address
            var addr = d.shipping_address || {};
            if (addr.postcode) $('#eop-shipping-postcode').val(formatPostcode(addr.postcode));
            if (addr.state) $('#eop-shipping-state').val(addr.state);
            if (addr.city) $('#eop-shipping-city').val(addr.city);
            if (addr.address) $('#eop-shipping-address').val(addr.address);
            if (addr.number) $('#eop-shipping-number').val(addr.number);
            if (addr.neighborhood) $('#eop-shipping-neighborhood').val(addr.neighborhood);
            if (addr.address_2) $('#eop-shipping-address-2').val(addr.address_2);

            // Discount
            $('#eop-discount').val(formatDiscountInput(d.discount_type || 'fixed', d.discount || 0));

            // Status
            $('#eop-status').val(d.status || 'pending');

            recalcTotals();
        }).fail(function () {
            showNotice('Erro ao carregar pedido.', 'error');
        });
    }

    /* ========================================
     *  Event bindings (edit page only)
     * ======================================== */

    if (!$('#eop-edit-order-id').length) {
        return; // Not on edit page
    }

    // Load order on page load
    loadOrder();

    // Customer search
    $('#eop-search-customer').on('click', function () {
        var doc = $('#eop-document').val().replace(/\D/g, '');
        if (!doc) return;

        var $status = $('#eop-customer-status');
        $status.text('Buscando...').attr('class', 'eop-status searching');

        $.post(eop_orders_vars.ajax_url, {
            action: 'eop_search_customer',
            nonce: eop_orders_vars.nonce,
            document: doc
        }, function (res) {
            if (res.success && res.data.found) {
                $('#eop-user-id').val(res.data.user_id);
                $('#eop-name').val(res.data.name);
                $('#eop-email').val(res.data.email);
                $('#eop-phone').val(res.data.phone);
                $status.text('Cliente encontrado!').attr('class', 'eop-status found');
                return;
            }
            $('#eop-user-id').val(0);
            $status.text('Nao encontrado. Preencha manualmente.').attr('class', 'eop-status not-found');
        }).fail(function () {
            $status.text('Erro na busca.').attr('class', 'eop-status not-found');
        });
    });

    $('#eop-document').on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#eop-search-customer').trigger('click');
        }
    });

    // Product search
    $('#eop-product-search').select2({
        placeholder: i18n.search_product,
        minimumInputLength: 3,
        allowClear: true,
        ajax: {
            url: eop_orders_vars.ajax_url,
            dataType: 'json',
            delay: 300,
            data: function (params) {
                return {
                    action: 'eop_search_products',
                    nonce: eop_orders_vars.nonce,
                    term: params.term
                };
            },
            processResults: function (data) { return data; },
            cache: true
        }
    });

    $('#eop-product-search').on('select2:select', function (e) {
        var selected = e.params.data;
        var existing = items.find(function (item) { return item.product_id === selected.id; });

        if (existing) {
            existing.quantity += 1;
        } else {
            items.push({
                product_id: selected.id,
                name: selected.name,
                sku: selected.sku || '',
                price: selected.price,
                image: selected.image || '',
                quantity: 1,
                discount_type: 'fixed',
                discount_value: 0
            });
        }

        renderItems();
        $(this).val(null).trigger('change');
    });

    // Qty change
    $(document).on('change input', '.eop-qty', function () {
        var $card = $(this).closest('.eop-item-card');
        var idx = $card.data('idx');
        var val = parseInt($(this).val(), 10);
        if (val < 1) val = 1;
        items[idx].quantity = val;
        updateCardSubtotal($card, idx);
        recalcTotals();
    });

    // Remove item
    $(document).on('click', '.eop-remove-item', function (e) {
        e.preventDefault();
        var idx = $(this).closest('.eop-item-card').data('idx');
        items.splice(idx, 1);
        renderItems();
    });

    // Item discount
    $(document).on('change input', '.eop-item-discount', function () {
        var $card = $(this).closest('.eop-item-card');
        var idx = $card.data('idx');
        var parsed = parseDiscountInput($card.find('.eop-item-discount').val());
        items[idx].discount_type = parsed.type;
        items[idx].discount_value = parsed.value;
        updateCardSubtotal($card, idx);
        recalcTotals();
    });

    // General discount & shipping
    $('#eop-shipping, #eop-discount').on('input change', recalcTotals);

    // Shipping toggle
    $('#eop-shipping-toggle').on('click', function () {
        var isOpen = $(this).attr('aria-expanded') === 'true';
        setShippingPanelState(!isOpen);
        if (!isOpen && !$('#eop-shipping-postcode').val()) {
            setShippingAddressStatus(i18n.shipping_panel_hint || 'Comece pelo CEP.', 'info');
        }
    });

    // CEP lookup
    $('#eop-shipping-postcode').on('input', function () {
        var formatted = formatPostcode($(this).val());
        var digits = sanitizePostcode(formatted);
        $(this).val(formatted);
        clearShippingSelection(true);
        setShippingAddressStatus('', '');
        if (digits.length === 8) lookupPostcode(false);
    });

    $('#eop-shipping-postcode').on('blur', function () { lookupPostcode(true); });

    // Address change
    $('#eop-shipping-state, #eop-shipping-city, #eop-shipping-address, #eop-shipping-number, #eop-shipping-neighborhood, #eop-shipping-address-2').on('input change', function () {
        if (selectedShippingRate) {
            clearShippingSelection(true);
        }
    });

    // Calc shipping
    $('#eop-calc-shipping').on('click', function () {
        var $btn = $(this);
        shippingAddress = getShippingAddress();

        if (!items.length) { showNotice(i18n.missing_products, 'error'); return; }
        if (!shippingAddress.postcode || !shippingAddress.city || !shippingAddress.address || !shippingAddress.number) {
            showNotice(i18n.shipping_missing, 'error');
            return;
        }

        $btn.prop('disabled', true).text(i18n.shipping_loading);
        setShippingAddressStatus(i18n.shipping_loading || 'Calculando frete...', 'loading');
        clearShippingSelection(false);

        $.post(eop_orders_vars.ajax_url, {
            action: 'eop_calculate_shipping',
            nonce: eop_orders_vars.nonce,
            items: JSON.stringify(items.map(function (item) { return { product_id: item.product_id, quantity: item.quantity }; })),
            address: JSON.stringify(shippingAddress)
        }, function (res) {
            $btn.prop('disabled', false).text(i18n.shipping_calculate);
            if (!res.success) {
                setShippingAddressStatus('', '');
                showNotice(res.data && res.data.message ? res.data.message : i18n.error, 'error');
                return;
            }
            setShippingAddressStatus(i18n.shipping_rates_found || 'Opcoes encontradas.', 'success');
            renderShippingRates(res.data.rates);
        }).fail(function () {
            $btn.prop('disabled', false).text(i18n.shipping_calculate);
            setShippingAddressStatus('', '');
            showNotice(i18n.error, 'error');
        });
    });

    // Shipping rate selection
    $(document).on('change', 'input[name^="eop_shipping_rate_"]', function () {
        try {
            selectedShippingRate = JSON.parse($(this).val());
            $('#eop-shipping').val(selectedShippingRate.cost || 0);
            shippingAddress = getShippingAddress();
            setShippingSummary((selectedShippingRate.label || 'Frete') + ' • ' + formatBRL(selectedShippingRate.cost || 0));
            recalcTotals();
        } catch (err) {
            selectedShippingRate = null;
            $('#eop-shipping').val(0);
            recalcTotals();
        }
    });

    // Save order
    $('#eop-save-order').on('click', function () {
        if (!items.length) {
            showNotice(i18n.missing_products, 'error');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text(i18n.processing);

        var orderData = {
            order_id: parseInt($('#eop-edit-order-id').val(), 10),
            customer: {
                user_id: parseInt($('#eop-user-id').val(), 10) || 0,
                name: $('#eop-name').val().trim(),
                email: $('#eop-email').val().trim(),
                phone: $('#eop-phone').val().trim(),
                document: $('#eop-document').val().replace(/\D/g, '')
            },
            items: items.map(function (item) {
                return {
                    product_id: item.product_id,
                    quantity: item.quantity,
                    discount_type: item.discount_type || 'fixed',
                    discount_value: parseFloat(item.discount_value) || 0
                };
            }),
            shipping: parseFloat($('#eop-shipping').val()) || 0,
            shipping_method: selectedShippingRate ? selectedShippingRate.label : '',
            shipping_address: getShippingAddress(),
            discount: parseDiscountInput($('#eop-discount').val()).value,
            discount_type: parseDiscountInput($('#eop-discount').val()).type,
            status: $('#eop-status').val(),
            note: $('#eop-note').val().trim()
        };

        $.post(eop_orders_vars.ajax_url, {
            action: 'eop_update_order',
            nonce: eop_orders_vars.nonce,
            order_data: JSON.stringify(orderData)
        }, function (res) {
            $btn.prop('disabled', false).text(i18n.save_label);

            if (!res.success) {
                showNotice(res.data && res.data.message ? res.data.message : i18n.error, 'error');
                return;
            }

            showNotice(i18n.saved || 'Pedido atualizado com sucesso!', 'success');
            $('#eop-note').val('');
        }).fail(function () {
            $btn.prop('disabled', false).text(i18n.save_label);
            showNotice(i18n.error, 'error');
        });
    });

    $(document).on('click', '.eop-accordion__toggle', function () {
        var $toggle = $(this);
        var $body = $toggle.next('.eop-accordion__body');
        var isOpen = $toggle.attr('aria-expanded') === 'true';
        var $icon = $toggle.find('.eop-accordion__icon').first();

        $toggle.attr('aria-expanded', !isOpen ? 'true' : 'false');
        $body.attr('hidden', isOpen);

        if ($icon.length) {
            $icon.text(isOpen ? '+' : '−');
        }
    });

})(jQuery);
