/* global jQuery, eop_vars */
(function ($) {
    'use strict';

    var items = [];
    var i18n = eop_vars.i18n || {};
    var discountMode = eop_vars.discount_mode || 'both';
    var selectedShippingRate = null;
    var shippingAddress = {};
    var postcodeLookupCache = {};
    var postcodeLookupBusy = false;
    var currentView = 'new-order';
    var ordersLoaded = false;
    var ordersPage = 1;
    var currentEditingOrderId = 0;

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

        if (!ui.panel.length || !ui.toggle.length) {
            return;
        }

        ui.panel.attr('hidden', !isOpen);
        ui.toggle.attr('aria-expanded', isOpen ? 'true' : 'false');

        if (ui.icon.length) {
            ui.icon.text(isOpen ? '-' : '+');
        }
    }

    function setShippingSummary(message) {
        var ui = getShippingPanelElements();

        if (ui.summary.length) {
            ui.summary.text(message);
        }
    }

    function setShippingAddressStatus(message, type) {
        var ui = getShippingPanelElements();
        var cssClass = 'eop-shipping-panel__status';

        if (!ui.status.length) {
            return;
        }

        if (type) {
            cssClass += ' is-' + type;
        }

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

    function sanitizePostcode(value) {
        return (value || '').replace(/\D/g, '').slice(0, 8);
    }

    function formatPostcode(value) {
        var digits = sanitizePostcode(value);

        if (digits.length <= 5) {
            return digits;
        }

        return digits.slice(0, 5) + '-' + digits.slice(5);
    }

    function formatBRL(value) {
        return 'R$ ' + parseFloat(value || 0).toFixed(2).replace('.', ',');
    }

    function escapeHtml(value) {
        return $('<span>').text(value == null ? '' : String(value)).html();
    }

    function updateEditingState() {
        var isEditing = currentEditingOrderId > 0;
        var $banner = $('#eop-editing-banner');
        var $title = $('#eop-editing-title');
        var $submit = $('#eop-submit');

        $('#eop-edit-order-id').val(currentEditingOrderId || 0);

        if ($banner.length) {
            $banner.attr('hidden', !isEditing).toggleClass('is-active', isEditing);
        }

        if ($title.length && isEditing) {
            $title.text((i18n.edit_title || 'Editando pedido') + ' #' + currentEditingOrderId);
        }

        if ($submit.length) {
            $submit.text(isEditing ? (i18n.edit_submit || 'Salvar alteracoes') : i18n.submit_label);
        }
    }

    function getCurrentSubmitLabel() {
        return currentEditingOrderId ? (i18n.edit_submit || 'Salvar alteracoes') : i18n.submit_label;
    }

    function enterEditMode(orderId) {
        currentEditingOrderId = parseInt(orderId, 10) || 0;
        updateEditingState();
        setAppView('new-order');
    }

    function exitEditMode() {
        currentEditingOrderId = 0;
        updateEditingState();
    }

    function parseDiscountInput(rawValue) {
        var raw = String(rawValue || '').trim().replace(',', '.');
        var hasPercent = raw.indexOf('%') !== -1;
        var numeric = parseFloat(raw.replace(/[^0-9.\-]/g, ''));

        if (isNaN(numeric) || numeric <= 0) {
            return { type: discountMode === 'percent' ? 'percent' : 'fixed', value: 0 };
        }

        var type;
        if (discountMode === 'percent') {
            type = 'percent';
        } else if (discountMode === 'fixed') {
            type = 'fixed';
        } else {
            type = hasPercent ? 'percent' : 'fixed';
        }

        return {
            type: type,
            value: Math.max(0, numeric)
        };
    }

    function setAppView(viewName) {
        var targetView = viewName === 'orders' ? 'orders' : 'new-order';

        currentView = targetView;

        $('.eop-pdv-nav__item').each(function () {
            var isActive = $(this).data('eop-view-target') === targetView;
            $(this).toggleClass('is-active', isActive).attr('aria-selected', isActive ? 'true' : 'false');
        });

        $('.eop-pdv-view').each(function () {
            var isActive = $(this).data('eop-view') === targetView;
            $(this).toggleClass('is-active', isActive).attr('hidden', !isActive);
        });

        if (targetView === 'orders' && !ordersLoaded) {
            loadOrdersList(1);
        }
    }

    function renderOrdersSummary(totalItems, viewer) {
        var prefix = viewer && viewer.is_admin ? 'Todos os pedidos expresso' : 'Seus pedidos expresso';

        $('#eop-orders-summary').html(
            '<div class="eop-orders-summary__card">' +
                '<strong>' + escapeHtml(prefix) + '</strong>' +
                '<span>' + escapeHtml(totalItems + ' ' + (i18n.orders_of || 'pedido(s) encontrado(s)')) + '</span>' +
            '</div>'
        );
    }

    function renderOrdersPagination(page, totalPages) {
        var html = '';
        var prevDisabled = page <= 1 ? ' disabled' : '';
        var nextDisabled = page >= totalPages ? ' disabled' : '';

        if (totalPages <= 1) {
            $('#eop-orders-pagination').empty();
            return;
        }

        html += '<button type="button" class="eop-btn eop-orders-page-btn" data-page="' + (page - 1) + '"' + prevDisabled + '>' + escapeHtml(i18n.orders_previous || 'Anterior') + '</button>';
        html += '<span class="eop-orders-page-indicator">' + escapeHtml('Página ' + page + ' de ' + totalPages) + '</span>';
        html += '<button type="button" class="eop-btn eop-orders-page-btn" data-page="' + (page + 1) + '"' + nextDisabled + '>' + escapeHtml(i18n.orders_next || 'Proxima') + '</button>';

        $('#eop-orders-pagination').html(html);
    }

    function renderOrdersList(orders, viewer) {
        var html = '';
        var isAdmin = viewer && viewer.is_admin;

        if (!orders.length) {
            $('#eop-orders-list').html('<div class="eop-card eop-orders-empty-state">' + escapeHtml(i18n.orders_empty || 'Nenhum pedido encontrado para este filtro.') + '</div>');
            return;
        }

        orders.forEach(function (order) {
            html += '<article class="eop-card eop-order-card">';
            html += '<div class="eop-order-card__header">';
            html += '<div>';
            html += '<div class="eop-order-card__number">' + escapeHtml(order.number) + '</div>';
            html += '<h3>' + escapeHtml(order.customer_name) + '</h3>';

            if (order.customer_email) {
                html += '<p class="eop-order-card__email">' + escapeHtml(order.customer_email) + '</p>';
            }

            html += '</div>';
            html += '<span class="eop-order-card__status status-' + escapeHtml(order.status) + '">' + escapeHtml(order.status_label) + '</span>';
            html += '</div>';

            html += '<div class="eop-order-card__meta">';
            html += '<div><span>Data</span><strong>' + escapeHtml(order.date_label) + '</strong></div>';
            html += '<div><span>Total</span><strong>' + order.total_html + '</strong></div>';

            if (isAdmin && order.created_by_name) {
                html += '<div><span>' + escapeHtml(i18n.orders_created_by || 'Vendedor') + '</span><strong>' + escapeHtml(order.created_by_name) + '</strong></div>';
            }

            html += '</div>';
            html += '<div class="eop-order-card__actions">';

            if (order.public_url) {
                html += '<a class="eop-btn eop-btn-primary eop-order-edit-spa" href="' + escapeHtml(order.public_url) + '" target="_blank">' + escapeHtml(i18n.orders_public || 'Link do cliente') + '</a>';
            }

            if (order.pdf_url) {
                html += '<a class="eop-btn eop-btn-primary eop-order-edit-spa" href="' + escapeHtml(order.pdf_url) + '" target="_blank">' + escapeHtml(i18n.orders_pdf || 'PDF') + '</a>';
            }

            html += '<button type="button" class="eop-btn eop-btn-primary eop-order-edit-spa" data-order-id="' + escapeHtml(order.id) + '">' + escapeHtml(i18n.orders_edit || 'Editar aqui') + '</button>';
            html += '</div>';
            html += '</article>';
        });

        $('#eop-orders-list').html(html);
    }

    function loadOrdersList(page) {
        var requestedPage = parseInt(page, 10) || 1;
        var search = $('#eop-orders-search').val() || '';
        var status = $('#eop-orders-status-filter').val() || 'any';

        ordersPage = requestedPage;
        $('#eop-orders-list').html('<div class="eop-card eop-orders-empty-state">' + escapeHtml(i18n.orders_loading || 'Carregando pedidos...') + '</div>');
        $('#eop-orders-pagination').empty();

        $.post(eop_vars.ajax_url, {
            action: 'eop_list_orders',
            nonce: eop_vars.nonce,
            paged: requestedPage,
            search: search,
            status: status
        }, function (res) {
            if (!res.success) {
                $('#eop-orders-list').html('<div class="eop-card eop-orders-empty-state">' + escapeHtml((res.data && res.data.message) || i18n.orders_error || 'Nao foi possivel carregar os pedidos agora.') + '</div>');
                return;
            }

            ordersLoaded = true;
            renderOrdersSummary(res.data.pagination.total_items, res.data.viewer);
            renderOrdersList(res.data.orders || [], res.data.viewer || {});
            renderOrdersPagination(res.data.pagination.page, res.data.pagination.total_pages);
        }).fail(function () {
            $('#eop-orders-list').html('<div class="eop-card eop-orders-empty-state">' + escapeHtml(i18n.orders_error || 'Nao foi possivel carregar os pedidos agora.') + '</div>');
        });
    }

    function loadOrderForEditing(orderId) {
        $.post(eop_vars.ajax_url, {
            action: 'eop_load_order',
            nonce: eop_vars.nonce,
            order_id: orderId
        }, function (res) {
            var d;
            var addr;

            if (!res.success) {
                showNotice((res.data && res.data.message) || i18n.edit_error || 'Nao foi possivel abrir este pedido para edicao.', 'error');
                return;
            }

            d = res.data || {};
            addr = d.shipping_address || {};

            enterEditMode(d.order_id || orderId);

            $('#eop-document').val(d.customer && d.customer.document ? d.customer.document : '');
            $('#eop-name').val(d.customer && d.customer.name ? d.customer.name : '');
            $('#eop-email').val(d.customer && d.customer.email ? d.customer.email : '');
            $('#eop-phone').val(d.customer && d.customer.phone ? d.customer.phone : '');
            $('#eop-user-id').val(d.customer && d.customer.user_id ? d.customer.user_id : 0);

            items = (d.items || []).map(function (item) {
                return {
                    product_id: item.product_id,
                    name: item.name,
                    sku: item.sku || '',
                    price: parseFloat(item.price) || 0,
                    image: item.image || '',
                    quantity: parseInt(item.quantity, 10) || 1,
                    discount_type: item.discount_type || (discountMode === 'fixed' ? 'fixed' : 'percent'),
                    discount_value: parseFloat(item.discount_value) || 0
                };
            });
            renderItems();

            $('#eop-shipping').val(parseFloat(d.shipping) || 0);
            $('#eop-discount').val(formatDiscountInput(d.discount_type || 'fixed', d.discount || 0));
            $('#eop-status').val(d.status || 'pending');

            $('#eop-shipping-postcode').val(addr.postcode ? formatPostcode(addr.postcode) : '');
            $('#eop-shipping-state').val(addr.state || '');
            $('#eop-shipping-city').val(addr.city || '');
            $('#eop-shipping-address').val(addr.address || '');
            $('#eop-shipping-number').val(addr.number || '');
            $('#eop-shipping-neighborhood').val(addr.neighborhood || '');
            $('#eop-shipping-address-2').val(addr.address_2 || '');

            selectedShippingRate = null;
            $('#eop-shipping-rates').empty();
            setShippingPanelState(false);
            setShippingAddressStatus('', '');

            if (d.shipping_method && parseFloat(d.shipping) > 0) {
                setShippingSummary(d.shipping_method + ' - ' + formatBRL(d.shipping));
            } else {
                setShippingSummary(i18n.shipping_summary_default || 'Clique para calcular com o endereco do cliente.');
            }

            recalcTotals();
            showNotice(i18n.edit_loaded || 'Pedido carregado no painel para edicao.', 'success');
        }).fail(function () {
            showNotice(i18n.edit_error || 'Nao foi possivel abrir este pedido para edicao.', 'error');
        });
    }

    function formatDiscountInput(type, value) {
        var numeric = parseFloat(value) || 0;

        if (numeric <= 0) {
            return '';
        }

        return type === 'percent' ? (String(numeric).replace('.', ',') + '%') : String(numeric).replace('.', ',');
    }

    function calcItemDiscount(item) {
        var lineTotal = item.price * item.quantity;
        var discVal = parseFloat(item.discount_value) || 0;

        if (discVal <= 0) {
            return 0;
        }

        if (item.discount_type === 'percent') {
            return Math.min(lineTotal, lineTotal * discVal / 100);
        }

        return Math.min(lineTotal, discVal * item.quantity);
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
            $body.append('<div class="eop-items-empty">Nenhum produto adicionado.</div>');
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

            var discSuffix = '';
            var discPlaceholder = '0';

            if (discountMode === 'percent') {
                discSuffix = '<span class="eop-item-discount-suffix">%</span>';
                discPlaceholder = '0';
            } else if (discountMode === 'fixed') {
                discSuffix = '<span class="eop-item-discount-suffix">R$</span>';
                discPlaceholder = '0,00';
            } else {
                discSuffix = '';
                discPlaceholder = '10 ou 10%';
            }

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
                            '<div class="eop-qty-stepper">' +
                                '<button type="button" class="eop-qty-btn eop-qty-dec" aria-label="Diminuir">&#8722;</button>' +
                                '<input type="number" class="eop-qty" min="1" value="' + item.quantity + '" inputmode="numeric" />' +
                                '<button type="button" class="eop-qty-btn eop-qty-inc" aria-label="Aumentar">+</button>' +
                            '</div>' +
                        '</div>' +
                        '<div class="eop-item-card__field">' +
                            '<span class="eop-item-card__label">Desconto</span>' +
                            '<div class="eop-item-discount-group">' +
                                '<input type="text" class="eop-item-discount" value="' + (discVal > 0 ? formatDiscountInput(discType, discVal) : '') + '" placeholder="' + discPlaceholder + '" inputmode="decimal" />' +
                                discSuffix +
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

    function resetForm() {
        items = [];
        renderItems();

        $('#eop-document, #eop-name, #eop-email, #eop-phone').val('');
        $('#eop-shipping-postcode, #eop-shipping-state, #eop-shipping-city, #eop-shipping-address, #eop-shipping-number, #eop-shipping-neighborhood, #eop-shipping-address-2').val('');
        $('#eop-user-id').val(0);
        $('#eop-shipping').val(0);
        $('#eop-discount').val('');
        $('#eop-status').val('completed');
        $('#eop-product-search').val(null).trigger('change');
        $('#eop-customer-status').text('').attr('class', 'eop-status');
        $('#eop-public-link').hide().attr('href', '#');
        $('#eop-success-modal').hide();
        selectedShippingRate = null;
        shippingAddress = {};
        setShippingPanelState(false);
        clearShippingSelection(true);
        setShippingAddressStatus('', '');
        exitEditMode();

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

    function renderShippingRates(packages) {
        var html = '';

        packages.forEach(function (pkg, pkgIndex) {
            html += '<div class="eop-shipping-group">';
            html += '<div class="eop-shipping-group__title">' + $('<span>').text(pkg.package_name).html() + '</div>';

            pkg.rates.forEach(function (rate, rateIndex) {
                var details = [];

                if (rate.meta_data) {
                    Object.keys(rate.meta_data).forEach(function (key) {
                        details.push(rate.meta_data[key]);
                    });
                }

                html += '<label class="eop-shipping-rate">';
                html += '<input type="radio" name="eop_shipping_rate_' + pkgIndex + '" value="' + $('<span>').text(JSON.stringify(rate)).html() + '" ' + (pkgIndex === 0 && rateIndex === 0 ? 'checked' : '') + '>';
                html += '<span class="eop-shipping-rate__content">';
                html += '<strong>' + $('<span>').text(rate.label).html() + '</strong>';
                html += '<em>' + formatBRL(rate.cost) + '</em>';
                if (details.length) {
                    html += '<small>' + $('<span>').text(details.join(' | ')).html() + '</small>';
                }
                html += '</span>';
                html += '</label>';
            });

            html += '</div>';
        });

        $('#eop-shipping-rates').html(html);
        setShippingSummary(i18n.shipping_summary_ready || 'Escolha a opcao de frete que melhor atende o cliente.');
        $('input[name^="eop_shipping_rate_"]').first().trigger('change');
    }

    function fillAddressFromPostcode(data) {
        if (data.uf) {
            $('#eop-shipping-state').val(data.uf);
        }

        if (data.localidade) {
            $('#eop-shipping-city').val(data.localidade);
        }

        if (data.logradouro) {
            $('#eop-shipping-address').val(data.logradouro);
        }

        if (data.bairro) {
            $('#eop-shipping-neighborhood').val(data.bairro);
        }
    }

    function lookupPostcode(force) {
        var postcode = sanitizePostcode($('#eop-shipping-postcode').val());

        $('#eop-shipping-postcode').val(formatPostcode(postcode));

        if (postcode.length !== 8) {
            if (force) {
                setShippingAddressStatus(i18n.shipping_postcode_invalid || 'Digite um CEP valido com 8 numeros.', 'error');
            }
            return;
        }

        if (postcodeLookupBusy) {
            return;
        }

        if (postcodeLookupCache[postcode]) {
            fillAddressFromPostcode(postcodeLookupCache[postcode]);
            setShippingAddressStatus(i18n.shipping_postcode_found || 'Endereco encontrado. Confira o numero e o complemento.', 'success');
            return;
        }

        postcodeLookupBusy = true;
        setShippingAddressStatus(i18n.shipping_postcode_loading || 'Buscando endereco pelo CEP...', 'loading');

        fetch('https://viacep.com.br/ws/' + postcode + '/json/')
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('cep_lookup_failed');
                }

                return response.json();
            })
            .then(function (data) {
                postcodeLookupBusy = false;

                if (!data || data.erro) {
                    setShippingAddressStatus(i18n.shipping_postcode_not_found || 'Nao encontramos esse CEP. Preencha o endereco manualmente.', 'error');
                    return;
                }

                postcodeLookupCache[postcode] = data;
                fillAddressFromPostcode(data);
                setShippingAddressStatus(i18n.shipping_postcode_found || 'Endereco encontrado. Confira o numero e o complemento.', 'success');
                setShippingSummary(i18n.shipping_summary_pending || 'Preencha o endereco e escolha uma opcao de frete.');
            })
            .catch(function () {
                postcodeLookupBusy = false;
                setShippingAddressStatus(i18n.shipping_postcode_error || 'Nao foi possivel buscar o CEP agora. Continue manualmente.', 'error');
            });
    }

    function showNotice(message, type) {
        var cls = type === 'error' ? 'notice-error' : 'notice-success';
        var html = '<div class="notice ' + cls + ' is-dismissible"><p>' + $('<span>').text(message).html() + '</p></div>';

        $('#eop-notices').html(html);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    $('#eop-search-customer').on('click', function () {
        var doc = $('#eop-document').val().replace(/\D/g, '');

        if (!doc) {
            return;
        }

        var $status = $('#eop-customer-status');
        $status.text('Buscando...').attr('class', 'eop-status searching');

        $.post(eop_vars.ajax_url, {
            action: 'eop_search_customer',
            nonce: eop_vars.nonce,
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

    $('#eop-product-search').select2({
        placeholder: i18n.search_product,
        minimumInputLength: 3,
        allowClear: true,
        ajax: {
            url: eop_vars.ajax_url,
            dataType: 'json',
            delay: 300,
            data: function (params) {
                return {
                    action: 'eop_search_products',
                    nonce: eop_vars.nonce,
                    term: params.term
                };
            },
            processResults: function (data) {
                return data;
            },
            cache: true
        }
    });

    $('#eop-product-search').on('select2:select', function (e) {
        var selected = e.params.data;
        var existing = items.find(function (item) {
            return item.product_id === selected.id;
        });

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
                discount_type: discountMode === 'fixed' ? 'fixed' : 'percent',
                discount_value: 0
            });
        }

        renderItems();
        $(this).val(null).trigger('change');
    });

    $(document).on('change input', '.eop-qty', function () {
        var $card = $(this).closest('.eop-item-card');
        var idx = $card.data('idx');
        var val = parseInt($(this).val(), 10);

        if (val < 1) {
            val = 1;
        }

        items[idx].quantity = val;
        updateCardSubtotal($card, idx);
        recalcTotals();
    });

    $(document).on('click', '.eop-qty-dec', function () {
        var $card = $(this).closest('.eop-item-card');
        var idx = $card.data('idx');
        var current = parseInt(items[idx].quantity, 10) || 1;
        var next = Math.max(1, current - 1);

        items[idx].quantity = next;
        $card.find('.eop-qty').val(next);
        updateCardSubtotal($card, idx);
        recalcTotals();
    });

    $(document).on('click', '.eop-qty-inc', function () {
        var $card = $(this).closest('.eop-item-card');
        var idx = $card.data('idx');
        var current = parseInt(items[idx].quantity, 10) || 1;
        var next = current + 1;

        items[idx].quantity = next;
        $card.find('.eop-qty').val(next);
        updateCardSubtotal($card, idx);
        recalcTotals();
    });

    $(document).on('click', '.eop-remove-item', function (e) {
        var idx;

        e.preventDefault();
        idx = $(this).closest('.eop-item-card').data('idx');

        items.splice(idx, 1);
        renderItems();
    });

    $('#eop-shipping, #eop-discount').on('input change', recalcTotals);

    $(document).on('change input', '.eop-item-discount', function () {
        var $card = $(this).closest('.eop-item-card');
        var idx = $card.data('idx');
        var parsed = parseDiscountInput($(this).val());

        items[idx].discount_type = parsed.type;
        items[idx].discount_value = parsed.value;
        updateCardSubtotal($card, idx);
        recalcTotals();
    });

    $('#eop-shipping-toggle').on('click', function () {
        var isOpen = $(this).attr('aria-expanded') === 'true';

        setShippingPanelState(!isOpen);

        if (!isOpen && !$('#eop-shipping-postcode').val()) {
            setShippingAddressStatus(i18n.shipping_panel_hint || 'Comece pelo CEP. O sistema tenta preencher o endereco automaticamente.', 'info');
        }
    });

    $('#eop-shipping-postcode').on('input', function () {
        var formatted = formatPostcode($(this).val());
        var digits = sanitizePostcode(formatted);

        $(this).val(formatted);
        clearShippingSelection(true);
        setShippingAddressStatus('', '');

        if (digits.length === 8) {
            lookupPostcode(false);
        }
    });

    $('#eop-shipping-postcode').on('blur', function () {
        lookupPostcode(true);
    });

    $('#eop-shipping-state, #eop-shipping-city, #eop-shipping-address, #eop-shipping-number, #eop-shipping-neighborhood, #eop-shipping-address-2').on('input change', function () {
        if (selectedShippingRate) {
            clearShippingSelection(true);
        } else {
            setShippingSummary(i18n.shipping_summary_pending || 'Preencha o endereco e escolha uma opcao de frete.');
        }
    });

    $('#eop-calc-shipping').on('click', function () {
        var $btn = $(this);
        shippingAddress = getShippingAddress();

        if (!items.length) {
            showNotice(i18n.missing_products, 'error');
            return;
        }

        if (!shippingAddress.postcode || !shippingAddress.city || !shippingAddress.address || !shippingAddress.number) {
            showNotice(i18n.shipping_missing, 'error');
            return;
        }

        $btn.prop('disabled', true).text(i18n.shipping_loading);
        setShippingAddressStatus(i18n.shipping_loading || 'Calculando frete...', 'loading');
        clearShippingSelection(false);

        $.post(eop_vars.ajax_url, {
            action: 'eop_calculate_shipping',
            nonce: eop_vars.nonce,
            items: JSON.stringify(items.map(function (item) {
                return {
                    product_id: item.product_id,
                    quantity: item.quantity
                };
            })),
            address: JSON.stringify(shippingAddress)
        }, function (res) {
            $btn.prop('disabled', false).text(i18n.shipping_calculate);

            if (!res.success) {
                setShippingAddressStatus('', '');
                showNotice(res.data && res.data.message ? res.data.message : i18n.error, 'error');
                return;
            }

            setShippingAddressStatus(i18n.shipping_rates_found || 'Opcoes encontradas. Escolha a melhor para o cliente.', 'success');
            renderShippingRates(res.data.rates);
        }).fail(function () {
            $btn.prop('disabled', false).text(i18n.shipping_calculate);
            setShippingAddressStatus('', '');
            showNotice(i18n.error, 'error');
        });
    });

    $(document).on('change', 'input[name^="eop_shipping_rate_"]', function () {
        var raw = $(this).val();

        try {
            selectedShippingRate = JSON.parse(raw);
            $('#eop-shipping').val(selectedShippingRate.cost || 0);
            shippingAddress = getShippingAddress();
            setShippingSummary((selectedShippingRate.label || 'Frete selecionado') + ' - ' + formatBRL(selectedShippingRate.cost || 0));
            recalcTotals();
        } catch (err) {
            selectedShippingRate = null;
            $('#eop-shipping').val(0);
            setShippingSummary(i18n.shipping_summary_pending || 'Preencha o endereco e escolha uma opcao de frete.');
            recalcTotals();
        }
    });

    $('#eop-submit').on('click', function () {
        var $btn;
        var orderData;
        var requestAction;

        if (!items.length) {
            showNotice(i18n.missing_products, 'error');
            return;
        }

        $btn = $(this);
        $btn.prop('disabled', true).text(i18n.processing);

        orderData = {
            order_id: currentEditingOrderId || 0,
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
                    discount_type: item.discount_type || 'percent',
                    discount_value: parseFloat(item.discount_value) || 0
                };
            }),
            shipping: parseFloat($('#eop-shipping').val()) || 0,
            shipping_rate: selectedShippingRate || null,
            shipping_method: selectedShippingRate ? selectedShippingRate.label : '',
            shipping_address: items.length ? getShippingAddress() : shippingAddress,
            discount: parseDiscountInput($('#eop-discount').val()).value,
            discount_type: parseDiscountInput($('#eop-discount').val()).type,
            status: $('#eop-status').val()
        };

        requestAction = currentEditingOrderId ? 'eop_update_order' : 'eop_create_order';

        $.post(eop_vars.ajax_url, {
            action: requestAction,
            nonce: eop_vars.nonce,
            order_data: JSON.stringify(orderData)
        }, function (res) {
            $btn.prop('disabled', false).text(getCurrentSubmitLabel());

            if (!res.success) {
                showNotice(res.data && res.data.message ? res.data.message : i18n.error, 'error');
                return;
            }

            if (currentEditingOrderId) {
                showNotice(res.data && res.data.message ? res.data.message : (i18n.edit_submit || 'Salvar alteracoes'), 'success');
                ordersLoaded = false;
                loadOrdersList(ordersPage || 1);
                exitEditMode();
                setAppView('orders');
                return;
            }

            $('#eop-success-message').text('Pedido #' + res.data.order_id + ' criado com sucesso.');
            $('#eop-pdf-link').attr('href', res.data.pdf_url);
            $('#eop-order-link').attr('href', res.data.order_url);
            if (res.data.public_url) {
                $('#eop-public-link').attr('href', res.data.public_url).show();
                $('#eop-success-message').text('Proposta #' + res.data.order_id + ' criada com sucesso.');
            } else {
                $('#eop-public-link').hide().attr('href', '#');
            }
            $('#eop-success-modal').show();

            if (res.data.pdf_url) {
                window.open(res.data.pdf_url, '_blank');
            }

            ordersLoaded = false;
        }).fail(function () {
            $btn.prop('disabled', false).text(getCurrentSubmitLabel());
            showNotice(i18n.error, 'error');
        });
    });

    $('#eop-new-order').on('click', function () {
        resetForm();
        setAppView('new-order');
    });

    $('#eop-cancel-edit').on('click', function () {
        resetForm();
        showNotice(i18n.edit_cancel || 'Edicao cancelada.', 'success');
    });

    $(document).on('click', '.eop-pdv-nav__item', function () {
        var targetView = $(this).data('eop-view-target');

        if (targetView === 'new-order') {
            resetForm();
            return;
        }

        setAppView(targetView);
    });

    $(document).on('click', '.eop-order-edit-spa', function () {
        loadOrderForEditing($(this).data('order-id'));
    });

    $('#eop-orders-refresh').on('click', function () {
        loadOrdersList(1);
    });

    $('#eop-orders-search').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadOrdersList(1);
        }
    });

    $('#eop-orders-status-filter').on('change', function () {
        loadOrdersList(1);
    });

    $(document).on('click', '.eop-orders-page-btn', function () {
        if ($(this).is(':disabled')) {
            return;
        }

        loadOrdersList($(this).data('page'));
    });

    $('#eop-success-modal').on('click', function (e) {
        if (e.target === this) {
            $(this).hide();
        }
    });

    /* Generic accordion toggle */
    $(document).on('click', '.eop-accordion__toggle', function () {
        var $toggle = $(this);
        var $body = $toggle.next('.eop-accordion__body');
        var isOpen = $toggle.attr('aria-expanded') === 'true';
        var $icon = $toggle.find('.eop-accordion__icon').first();

        $toggle.attr('aria-expanded', !isOpen ? 'true' : 'false');
        $body.attr('hidden', isOpen);

        if ($icon.length) {
            $icon.text(isOpen ? '+' : '-');
        }
    });
})(jQuery);
