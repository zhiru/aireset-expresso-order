/* global jQuery, eop_settings_vars */
(function ($) {
    'use strict';

    var mediaFrame = null;

    function createPreviewMarkup(url) {
        return '<img src="' + url + '" alt="" />';
    }

    function createEmptyMarkup() {
        var emptyText = (window.eop_settings_vars && eop_settings_vars.no_logo) || 'Nenhum logo selecionado ainda.';
        return '<span class="eop-settings-media__empty">' + emptyText + '</span>';
    }

    function setMediaOnWrap($wrap, url) {
        var hasUrl = Boolean(url);
        var $hiddenInput = $wrap.find('#eop_logo');
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
        $selectButton.text(hasUrl ? eop_settings_vars.change_logo : eop_settings_vars.select_logo);
    }

    function bindMediaUploader() {
        $(document).on('click', '[data-media-select]', function (event) {
            var $button = $(this);
            var $wrap = $button.closest('.eop-settings-media');

            event.preventDefault();

            if (typeof wp === 'undefined' || !wp.media) {
                return;
            }

            mediaFrame = wp.media({
                title: eop_settings_vars.media_title,
                button: {
                    text: eop_settings_vars.media_button
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

    $(function () {
        hideExternalNotices();
        window.setTimeout(hideExternalNotices, 120);

        $('.eop-color-field').wpColorPicker();

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

        if (window.wp && wp.media && window.eop_settings_vars) {
            bindMediaUploader();
        }
    });
})(jQuery);
