(function () {
    'use strict';

    var instances = [
        {
            key: 'cwmp',
            root: '#cwmp-admin-app',
            toggle: '#cwmp-admin-sidebar-toggle',
            item: '.cwmp-spa-category',
            header: '.cwmp-spa-category-header',
            sections: '.cwmp-spa-category-sections',
            collapsedClass: 'is-sidebar-collapsed',
            htmlClass: 'cwmp-admin-sidebar-collapsed',
            flyinClass: 'cwmp-flyinmenu-open',
            labelSelector: '.cwmp-admin-sidebar-toggle__label',
            storageKey: 'cwmp:ui:sidebar-collapsed'
        },
        {
            key: 'eop',
            root: '.eop-admin-spa',
            toggle: '#eop-admin-sidebar-toggle',
            item: '.eop-admin-spa-nav__group',
            header: '.eop-admin-spa-nav__group-toggle',
            sections: '.eop-admin-spa-nav__submenu',
            collapsedClass: 'is-sidebar-collapsed',
            htmlClass: 'eop-admin-sidebar-collapsed',
            flyinClass: 'eop-flyinmenu-open',
            labelSelector: '.eop-admin-spa__sidebar-toggle-label',
            storageKey: 'eop:ui:sidebar-collapsed'
        }
    ];

    function closestFromEvent(event, selector) {
        if (!event.target || typeof event.target.closest !== 'function') {
            return null;
        }

        return event.target.closest(selector);
    }

    function getStorage() {
        var probeKey = 'aireset:ui:probe';

        try {
            window.localStorage.setItem(probeKey, '1');
            window.localStorage.removeItem(probeKey);

            return window.localStorage;
        } catch (error) {
            return null;
        }
    }

    function readPreference(storage, key) {
        var raw;
        var parsed;

        if (!storage) {
            return null;
        }

        try {
            raw = storage.getItem(key);
        } catch (error) {
            return null;
        }

        if (!raw) {
            return null;
        }

        try {
            parsed = JSON.parse(raw);
        } catch (error) {
            return null;
        }

        if (!parsed || typeof parsed !== 'object' || !Object.prototype.hasOwnProperty.call(parsed, 'value')) {
            return null;
        }

        return parsed.value;
    }

    function writePreference(storage, key, value) {
        if (!storage) {
            return;
        }

        try {
            storage.setItem(key, JSON.stringify({
                storedAt: Date.now(),
                value: Boolean(value)
            }));
        } catch (error) {
            return;
        }
    }

    function getRoot(config) {
        return document.querySelector(config.root);
    }

    function getToggle(config) {
        return document.querySelector(config.toggle);
    }

    function hasSections(config, item) {
        return !!(item && item.querySelector(config.sections));
    }

    function closeFlyins(config, root) {
        root.querySelectorAll(config.item + '.' + config.flyinClass).forEach(function (item) {
            item.classList.remove(config.flyinClass);
        });
    }

    function syncToggle(config, toggle, collapsed) {
        var icon = toggle.querySelector('.dashicons');
        var label = toggle.querySelector(config.labelSelector);
        var iconClass = collapsed ? 'dashicons-arrow-right-alt2' : 'dashicons-arrow-left-alt2';
        var text = collapsed ? 'Abrir menu lateral' : 'Recolher menu lateral';

        toggle.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
        toggle.setAttribute('title', text);
        toggle.setAttribute('aria-label', text);

        if (icon) {
            icon.className = 'dashicons ' + iconClass;
        }

        if (label) {
            label.textContent = text;
        }
    }

    function setCollapsed(config, collapsed, options) {
        var root = getRoot(config);
        var toggle = getToggle(config);
        var settings = options || {};
        var shouldCollapse = Boolean(collapsed);

        if (!root || !toggle) {
            return false;
        }

        root.classList.toggle(config.collapsedClass, shouldCollapse);
        document.documentElement.classList.toggle(config.htmlClass, shouldCollapse);
        syncToggle(config, toggle, shouldCollapse);
        closeFlyins(config, root);

        if (settings.persist !== false) {
            writePreference(getStorage(), config.storageKey, shouldCollapse);
        }

        return true;
    }

    function isCollapsed(config) {
        var root = getRoot(config);

        return !!(root && root.classList.contains(config.collapsedClass));
    }

    function openFlyin(config, item) {
        var root = getRoot(config);

        if (!root || !item || !isCollapsed(config) || !hasSections(config, item)) {
            return;
        }

        closeFlyins(config, root);
        item.classList.add(config.flyinClass);
    }

    function restore(config) {
        var stored = readPreference(getStorage(), config.storageKey);

        setCollapsed(config, stored === null || stored === undefined ? true : Boolean(stored), { persist: false });
    }

    function exposeApi(config) {
        var api = {
            isCollapsed: function () {
                return isCollapsed(config);
            },
            setCollapsed: function (collapsed, options) {
                return setCollapsed(config, collapsed, options);
            },
            toggle: function () {
                return setCollapsed(config, !isCollapsed(config));
            },
            closeFlyins: function () {
                var root = getRoot(config);

                if (root) {
                    closeFlyins(config, root);
                }
            }
        };

        window.airesetAdminFlyinmenu = window.airesetAdminFlyinmenu || {};
        window.airesetAdminFlyinmenu[config.key] = api;

        if ('cwmp' === config.key) {
            window.cwmpAdminFlyinmenu = api;
        }

        if ('eop' === config.key) {
            window.eopAdminFlyinmenu = api;
        }
    }

    function bind(config) {
        var root = getRoot(config);
        var toggle = getToggle(config);
        var bootKey = 'airesetAdminFlyinmenuBootstrapped:' + config.key;

        if (!root || !toggle) {
            return;
        }

        exposeApi(config);

        if (window[bootKey]) {
            restore(config);
            return;
        }

        window[bootKey] = true;

        document.addEventListener('click', function (event) {
            if (!closestFromEvent(event, config.toggle)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            setCollapsed(config, !isCollapsed(config));
        }, true);

        root.addEventListener('pointerenter', function (event) {
            var item = closestFromEvent(event, config.item);

            if (item && root.contains(item)) {
                openFlyin(config, item);
            }
        }, true);

        root.addEventListener('pointerleave', function (event) {
            var item = closestFromEvent(event, config.item);

            if (item && root.contains(item)) {
                item.classList.remove(config.flyinClass);
            }
        }, true);

        root.addEventListener('focusin', function (event) {
            openFlyin(config, closestFromEvent(event, config.item));
        });

        root.addEventListener('focusout', function (event) {
            var item = closestFromEvent(event, config.item);

            if (item && !item.contains(event.relatedTarget)) {
                item.classList.remove(config.flyinClass);
            }
        });

        document.addEventListener('click', function (event) {
            var header = closestFromEvent(event, config.header);
            var item = header ? header.closest(config.item) : null;

            if (!isCollapsed(config)) {
                return;
            }

            if (item && hasSections(config, item) && root.contains(item)) {
                event.preventDefault();
                event.stopPropagation();
                setCollapsed(config, false);
                return;
            }

            if (!closestFromEvent(event, config.item)) {
                closeFlyins(config, root);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeFlyins(config, root);
            }
        });

        restore(config);
    }

    function init() {
        instances.forEach(bind);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
