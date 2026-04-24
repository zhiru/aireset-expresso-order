/* global jQuery, eop_settings_vars */
(function ($) {
    'use strict';

    var mediaFrame = null;
    var mediaUploaderBound = false;
    var colorisConfigured = false;
    var colorSwatches = ['#067bc2', '#84bcda', '#80e377', '#ecc30b', '#f37748', '#d56062'];

    function getSettingsVar(key, fallback) {
        if (window.eop_settings_vars && Object.prototype.hasOwnProperty.call(window.eop_settings_vars, key)) {
            return window.eop_settings_vars[key];
        }

        return fallback;
    }

    function createPreviewMarkup(url) {
        return '<img src="' + url + '" alt="" />';
    }

    function createEmptyMarkup() {
        var emptyText = getSettingsVar('no_logo', 'Nenhum logo selecionado ainda.');
        return '<span class="eop-settings-media__empty">' + emptyText + '</span>';
    }

    function setMediaOnWrap($wrap, url) {
        var hasUrl = Boolean(url);
        var $hiddenInput = $wrap.find('input[type="hidden"]').first();
        var $urlInput = $wrap.find('[data-media-url]');
        var $preview = $wrap.find('[data-media-preview]');
        var $selectButton = $wrap.find('[data-media-select]');
        var $removeButton = $wrap.find('[data-media-remove]');

        $hiddenInput.val(url);
        $urlInput.val(url);
        $preview
            .toggleClass('has-image', hasUrl)
            .html(hasUrl ? createPreviewMarkup(url) : createEmptyMarkup());
        $removeButton.toggleClass('is-hidden', !hasUrl);
        $selectButton.text(hasUrl ? getSettingsVar('change_logo', 'Trocar logo') : getSettingsVar('select_logo', 'Selecionar logo'));
    }

    function bindMediaUploader() {
        if (mediaUploaderBound) {
            return;
        }

        mediaUploaderBound = true;

        $(document).on('click', '[data-media-select]', function (event) {
            var $button = $(this);
            var $wrap = $button.closest('.eop-settings-media');

            event.preventDefault();

            if (typeof wp === 'undefined' || !wp.media) {
                return;
            }

            mediaFrame = wp.media({
                title: getSettingsVar('media_title', 'Selecionar logo'),
                button: {
                    text: getSettingsVar('media_button', 'Usar esta imagem')
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            mediaFrame.on('select', function () {
                var selection = mediaFrame.state().get('selection').first();
                var attachment = selection ? selection.toJSON() : null;
                var url = attachment && attachment.url ? attachment.url : '';

                setMediaOnWrap($wrap, url);
            });

            mediaFrame.open();
        });

        $(document).on('click', '[data-media-remove]', function (event) {
            event.preventDefault();
            setMediaOnWrap($(this).closest('.eop-settings-media'), '');
        });
    }

    function hideExternalNotices() {
        var selectors = [
            '#wpbody-content > .notice',
            '#wpbody-content > .update-nag',
            '#wpbody-content > .updated',
            '#wpbody-content > .error',
            '.eop-admin-spa .notice',
            '.eop-admin-spa .update-nag',
            '.eop-admin-spa .updated',
            '.eop-admin-spa .error',
            '.eop-admin-spa .fs-notice'
        ].join(', ');

        $(selectors).each(function () {
            if (!$(this).closest('#eop-notices, .eop-settings-page, .el-license-container').length) {
                $(this).hide();
            }
        });
    }

    function injectPdfHelpTooltips($scope) {
        var helpMap = (window.eop_settings_vars && eop_settings_vars.pdf_help_map) || {};
        var statusLabels = (window.eop_settings_vars && eop_settings_vars.help_statuses) || {};
        var buttonLabel = (window.eop_settings_vars && eop_settings_vars.help_label) || 'Ajuda da configuracao';
        var $root = $scope && $scope.length ? $scope : $(document);

        $.each(helpMap, function (fieldId, config) {
            var $label = $root.find('label[for="' + fieldId + '"]').first();
            var $tooltip;
            var $button;
            var $bubble;
            var statusText = config && config.status && statusLabels[config.status] ? statusLabels[config.status] : '';

            if (!$label.length || $label.find('.eop-help-tip').length) {
                return;
            }

            $tooltip = $('<span>', {
                class: 'eop-help-tip'
            });

            $button = $('<button>', {
                type: 'button',
                class: 'eop-help-tip__button',
                'aria-label': buttonLabel,
                text: '?'
            });

            $bubble = $('<span>', {
                class: 'eop-help-tip__bubble',
                role: 'tooltip'
            });

            if (config && config.label) {
                $('<strong>', {
                    class: 'eop-help-tip__title',
                    text: config.label
                }).appendTo($bubble);
            }

            if (statusText) {
                $('<span>', {
                    class: 'eop-help-tip__status eop-help-tip__status--' + String(config.status || ''),
                    text: statusText
                }).appendTo($bubble);
            }

            if (config && config.help) {
                $('<span>', {
                    class: 'eop-help-tip__text',
                    text: config.help
                }).appendTo($bubble);
            }

            if (config && config.effect) {
                $('<span>', {
                    class: 'eop-help-tip__effect',
                    text: config.effect
                }).appendTo($bubble);
            }

            $tooltip.append($button, $bubble);
            $label.append($tooltip);
        });
    }

    function initColorFields($scope) {
        var clearLabel = (window.eop_settings_vars && eop_settings_vars.color_clear) || 'Limpar';
        var closeLabel = (window.eop_settings_vars && eop_settings_vars.color_close) || 'Fechar';
        var $root = $scope && $scope.length ? $scope : $(document);
        var $fields = $root.find('.eop-color-field');

        if (!$fields.length) {
            return;
        }

        if (typeof window.Coloris !== 'undefined') {
            if (!colorisConfigured) {
                window.Coloris({
                    el: '.eop-color-field'
                });

                window.Coloris.setInstance('.eop-color-field', {
                    theme: 'pill',
                    themeMode: 'dark',
                    formatToggle: true,
                    closeButton: true,
                    closeLabel: closeLabel,
                    clearButton: true,
                    clearLabel: clearLabel,
                    swatchesOnly: false,
                    swatches: colorSwatches
                });

                colorisConfigured = true;
            }

            if (typeof window.Coloris.wrap === 'function') {
                window.Coloris.wrap('.eop-color-field');
            }

            $fields.each(function () {
                var $input = $(this);
                var $wrapper = $input.parent('.clr-field');

                if ($wrapper.length) {
                    $wrapper.css('color', $input.val() || 'transparent');
                }
            });

            return;
        }

        if ($.fn.wpColorPicker) {
            $fields.each(function () {
                var $input = $(this);

                if ($input.hasClass('wp-color-picker')) {
                    return;
                }

                $input.wpColorPicker();
            });
        }
    }

    function setColorFieldValue($input, value) {
        var nextValue = String(value || '');

        $input.val(nextValue).trigger('input').trigger('change');

        if ($input.parent('.clr-field').length) {
            $input.parent('.clr-field').css('color', nextValue || 'transparent');
        }
    }

    function mountColorDefaultButtons($scope) {
        var defaultLabel = (window.eop_settings_vars && eop_settings_vars.color_default) || 'Padrao';
        var $root = $scope && $scope.length ? $scope : $(document);

        $root.find('.eop-color-field').each(function () {
            var $input = $(this);
            var defaultColor = String($input.data('default-color') || '');
            var $pickerShell = $input.parent('.clr-field').length ? $input.parent('.clr-field') : $input;
            var $control;

            if (!defaultColor) {
                return;
            }

            if (!$pickerShell.parent().hasClass('eop-color-control')) {
                $pickerShell.wrap('<div class="eop-color-control"></div>');
            }

            $control = $pickerShell.parent();

            if (!$control.find('.eop-color-default').length) {
                $('<button>', {
                    type: 'button',
                    class: 'button button-secondary eop-color-default',
                    text: defaultLabel,
                    'data-default-color': defaultColor,
                    'aria-label': defaultLabel
                }).appendTo($control);
            }
        });
    }

    function initSettingsUi(scope) {
        var $scope = scope && scope.jquery ? scope : $(scope || document);

        hideExternalNotices();
        injectPdfHelpTooltips($scope);
        initColorFields($scope);
        mountColorDefaultButtons($scope);
    }

    $(function () {
        initSettingsUi($(document));
        window.setTimeout(hideExternalNotices, 120);

        $(document).on('click', '.eop-color-default', function (event) {
            var $button = $(this);
            var $input = $button.closest('.eop-color-control').find('.eop-color-field').first();
            var defaultColor = String($button.data('default-color') || '');

            event.preventDefault();

            if (!$input.length || !defaultColor) {
                return;
            }

            setColorFieldValue($input, defaultColor);
        });

        $('.eop-settings-switcher').on('click', function () {
            var $button = $(this);
            var targetName = $button.data('target-name');
            var enabledValue = String($button.data('enabled-value') || 'yes');
            var disabledValue = String($button.data('disabled-value') || 'no');
            var $input = $('input[type="hidden"][name="' + targetName + '"]');
            var isEnabled = $button.hasClass('is-enabled');
            var nextEnabled = !isEnabled;

            if (!$input.length) {
                return;
            }

            $button.toggleClass('is-enabled', nextEnabled);
            $button.attr('aria-checked', nextEnabled ? 'true' : 'false');
            $input.val(nextEnabled ? enabledValue : disabledValue);
            $button
                .siblings('.eop-settings-switcher__status')
                .text(nextEnabled ? 'Ativado' : 'Desativado');
        });

        if (eop_settings_vars && eop_settings_vars.has_fontselect && $.fn.fontselect) {
            $('.select_font').fontselect({
                placeholder: eop_settings_vars.font_placeholder || 'Escolha uma fonte Google'
            });
        }

        bindMediaUploader();

        $(document).on('eop:settings-ui:init', function (event, scope) {
            initSettingsUi(scope || document);
        });
    });
})(jQuery);
