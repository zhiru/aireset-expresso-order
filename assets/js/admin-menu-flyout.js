/**
 * Aireset Admin Menu Flyout (shared)
 *
 * Multi-level submenu technique adapted from Elementor's editor-one-menu.
 * Uses safe-zone triangle detection, smart positioning, and keyboard navigation.
 *
 * Supports multiple flyouts via window.airesetAdminFlyouts array.
 * Each entry: { menuRoot, anchorPage, currentPage, title, items[] }
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  FlyoutRenderer – builds one <ul> per config and appends to anchor */
    /* ------------------------------------------------------------------ */
    var FlyoutRenderer = {

        renderAll: function () {
            var configs = this.collectConfigs();
            var rendered = 0;

            for (var i = 0; i < configs.length; i++) {
                if (this.renderOne(configs[i])) { rendered++; }
            }

            return rendered > 0;
        },

        collectConfigs: function () {
            var configs = [];

            /* New array-based configs from any plugin */
            if (Array.isArray(window.airesetAdminFlyouts)) {
                configs = configs.concat(window.airesetAdminFlyouts);
            }

            /* Backward compat: legacy single-config from aireset-expresso-order */
            if (typeof eopAdminMenuFlyout !== 'undefined' && eopAdminMenuFlyout && Array.isArray(eopAdminMenuFlyout.items)) {
                var dominated = configs.some(function (c) {
                    return c.anchorPage === eopAdminMenuFlyout.anchorPage;
                });
                if (!dominated) { configs.push(eopAdminMenuFlyout); }
            }

            return configs;
        },

        renderOne: function (cfg) {
            if (!cfg || !Array.isArray(cfg.items) || !cfg.items.length) { return false; }

            var anchorLi = this.findAnchorLi(cfg);
            if (!anchorLi || anchorLi.querySelector('.eop-submenu-flyout')) { return false; }

            anchorLi.classList.add('eop-has-flyout');

            var currentPage = String(cfg.currentPage || '');
            if (currentPage === String(cfg.anchorPage || '') || this.itemIsActive(cfg.items, currentPage)) {
                anchorLi.classList.add('eop-flyout-parent-current');
            }

            var ul = document.createElement('ul');
            ul.className = 'eop-submenu-flyout';
            ul.setAttribute('role', 'menu');
            ul.setAttribute('aria-label', cfg.title || '');

            cfg.items.forEach(function (item) {
                if (!item || !item.url) { return; }

                var li = document.createElement('li');
                li.setAttribute('role', 'none');

                var a = document.createElement('a');
                a.href = item.url;
                a.setAttribute('role', 'menuitem');
                a.setAttribute('tabindex', '-1');

                if (item.icon) {
                    var icon = document.createElement('span');
                    icon.className = 'eop-flyout-icon dashicons ' + String(item.icon);
                    a.appendChild(icon);
                }

                var label = document.createElement('span');
                label.className = 'eop-flyout-label';
                label.textContent = String(item.label || '');
                a.appendChild(label);

                if (String(item.key || '') === currentPage) {
                    li.classList.add('eop-flyout-current');
                }

                li.appendChild(a);
                ul.appendChild(li);
            });

            anchorLi.appendChild(ul);

            this.hideOriginalItems(anchorLi, cfg.items);

            return true;
        },

        hideOriginalItems: function (anchorLi, items) {
            var wpSubmenu = anchorLi.closest('.wp-submenu');
            if (!wpSubmenu) { return; }

            items.forEach(function (item) {
                if (!item || !item.key) { return; }

                var links = wpSubmenu.querySelectorAll(':scope > li > a[href]');
                for (var i = 0; i < links.length; i++) {
                    if ((links[i].getAttribute('href') || '').indexOf('page=' + item.key) !== -1) {
                        var li = links[i].closest('li');
                        if (li && li !== anchorLi) {
                            li.classList.add('eop-flyout-hidden');
                        }
                    }
                }
            });
        },

        findAnchorLi: function (cfg) {
            var menuRoot = document.getElementById(String(cfg.menuRoot || ''));
            if (!menuRoot) { return null; }

            var submenu = menuRoot.querySelector('.wp-submenu');
            if (!submenu) { return null; }

            var needle = 'page=' + String(cfg.anchorPage || '');
            var links = submenu.querySelectorAll('a[href]');

            for (var i = 0; i < links.length; i++) {
                if ((links[i].getAttribute('href') || '').indexOf(needle) !== -1) {
                    return links[i].closest('li');
                }
            }
            return null;
        },

        itemIsActive: function (items, currentPage) {
            return items.some(function (item) {
                return item && String(item.key || '') === currentPage;
            });
        }
    };

    /* ------------------------------------------------------------------ */
    /*  FlyoutInteraction – hover / focus / keyboard / safe-zone triangle */
    /* ------------------------------------------------------------------ */
    var FlyoutInteraction = {

        activeMenu: null,
        activeParent: null,
        closeTimeout: null,
        lastMousePos: null,
        exitPoint: null,
        mouseMoveHandler: null,

        handle: function () {
            this.setupFlyoutMenus();
            this.setupMobileSupport();
        },

        setupFlyoutMenus: function () {
            var self = this;
            var parents = document.querySelectorAll('#adminmenu li.eop-has-flyout');

            parents.forEach(function (parentLi) {
                var flyout = parentLi.querySelector('.eop-submenu-flyout');
                if (!flyout) { return; }
                self.attachHover(parentLi, flyout);
                self.attachFocus(parentLi, flyout);
                self.attachKeyboard(parentLi, flyout);
            });
        },

        /* --- Hover events with safe-zone triangle --- */

        attachHover: function (parentLi, flyout) {
            var self = this;

            parentLi.addEventListener('mouseenter', function () {
                if (self.activeMenu && !self.activeMenu.contains(parentLi) && self.activeMenu !== flyout) {
                    if (!self.isCursorInSafeZone()) {
                        self.hideFlyout(self.activeMenu);
                    }
                }
                self.clearClose();
                self.showFlyout(parentLi, flyout);
            });

            parentLi.addEventListener('mouseleave', function (e) {
                self.exitPoint = { x: e.clientX, y: e.clientY };
                self.scheduleClose(parentLi, flyout);
            });

            flyout.addEventListener('mouseenter', function () {
                self.clearClose();
                self.stopMouseTracking();
            });

            flyout.addEventListener('mouseleave', function (e) {
                self.exitPoint = { x: e.clientX, y: e.clientY };
                self.scheduleClose(parentLi, flyout);
            });
        },

        /* --- Focus events --- */

        attachFocus: function (parentLi, flyout) {
            var self = this;
            var parentLink = parentLi.querySelector(':scope > a');

            if (parentLink) {
                parentLink.addEventListener('focus', function () {
                    self.showFlyout(parentLi, flyout);
                });
            }

            flyout.addEventListener('focusout', function (e) {
                if (!parentLi.contains(e.relatedTarget)) {
                    self.hideFlyout(flyout);
                }
            });
        },

        /* --- Keyboard navigation (Arrow keys + Escape) --- */

        attachKeyboard: function (parentLi, flyout) {
            var self = this;

            parentLi.addEventListener('keydown', function (e) {
                var allLinks = flyout.querySelectorAll('a');
                var focused = flyout.querySelector('a:focus');
                var idx = Array.from(allLinks).indexOf(focused);
                var visible = flyout.classList.contains('eop-submenu-flyout-visible');

                switch (e.key) {
                    case 'ArrowRight':
                        if (!visible) {
                            e.preventDefault();
                            self.showFlyout(parentLi, flyout);
                            if (allLinks[0]) { allLinks[0].focus(); }
                        }
                        break;
                    case 'ArrowLeft':
                    case 'Escape':
                        if (visible) {
                            e.preventDefault();
                            self.hideFlyout(flyout);
                            var link = parentLi.querySelector(':scope > a');
                            if (link) { link.focus(); }
                        }
                        break;
                    case 'ArrowDown':
                        if (visible && idx >= 0) {
                            e.preventDefault();
                            var next = (idx + 1) % allLinks.length;
                            allLinks[next].focus();
                        }
                        break;
                    case 'ArrowUp':
                        if (visible && idx >= 0) {
                            e.preventDefault();
                            var prev = (idx - 1 + allLinks.length) % allLinks.length;
                            allLinks[prev].focus();
                        }
                        break;
                }
            });
        },

        /* --- Show / Hide --- */

        showFlyout: function (parentLi, flyout) {
            if (this.activeMenu && this.activeMenu !== flyout) {
                this.hideFlyout(this.activeMenu);
            }
            this.exitPoint = null;
            this.positionFlyout(parentLi, flyout);
            flyout.classList.add('eop-submenu-flyout-visible');
            this.activeMenu = flyout;
            this.activeParent = parentLi;
        },

        hideFlyout: function (flyout) {
            flyout.classList.remove('eop-submenu-flyout-visible');
            if (this.activeMenu === flyout) {
                this.activeMenu = null;
                this.activeParent = null;
                this.exitPoint = null;
                this.stopMouseTracking();
            }
        },

        /* --- Delayed close with safe-zone check --- */

        scheduleClose: function (parentLi, flyout) {
            var self = this;
            this.clearClose();
            this.startMouseTracking();
            this.closeTimeout = setTimeout(function () {
                self.checkAndClose(flyout);
            }, 300);
        },

        checkAndClose: function (flyout) {
            var self = this;
            if (!this.activeMenu) { return; }

            if (!this.isCursorInSafeZone()) {
                this.hideFlyout(flyout);
            } else {
                this.closeTimeout = setTimeout(function () {
                    self.checkAndClose(flyout);
                }, 300);
            }
        },

        clearClose: function () {
            if (this.closeTimeout) {
                clearTimeout(this.closeTimeout);
                this.closeTimeout = null;
            }
        },

        /* --- Mouse position tracking --- */

        startMouseTracking: function () {
            var self = this;
            this.stopMouseTracking();
            this.mouseMoveHandler = function (e) {
                self.lastMousePos = { x: e.clientX, y: e.clientY };
            };
            document.addEventListener('mousemove', this.mouseMoveHandler);
        },

        stopMouseTracking: function () {
            if (this.mouseMoveHandler) {
                document.removeEventListener('mousemove', this.mouseMoveHandler);
                this.mouseMoveHandler = null;
            }
            this.lastMousePos = null;
        },

        /* --- Safe-zone triangle (Elementor technique) --- */

        isCursorInSafeZone: function () {
            if (!this.lastMousePos || !this.activeMenu || !this.activeParent) {
                return false;
            }

            var cursor = this.lastMousePos;
            var parentRect = this.activeParent.getBoundingClientRect();

            if (this.isPointInRect(cursor, parentRect)) { return true; }

            var flyoutRect = this.activeMenu.getBoundingClientRect();

            if (this.isPointInRect(cursor, flyoutRect)) { return true; }

            return this.isPointInTriangle(cursor, parentRect, flyoutRect);
        },

        isPointInRect: function (pt, rect) {
            return pt.x >= rect.left && pt.x <= rect.right && pt.y >= rect.top && pt.y <= rect.bottom;
        },

        isPointInTriangle: function (cursor, parentRect, flyoutRect) {
            var exitX = this.exitPoint ? this.exitPoint.x : parentRect.right;
            var distParent = Math.abs(exitX - parentRect.right);
            var distFlyout = Math.abs(exitX - flyoutRect.left);
            var triangleApex, baseTop, baseBottom;

            if (distParent < distFlyout) {
                triangleApex = this.exitPoint || { x: parentRect.right, y: parentRect.top + parentRect.height / 2 };
                baseTop      = { x: flyoutRect.left, y: flyoutRect.top - 100 };
                baseBottom   = { x: flyoutRect.left, y: flyoutRect.bottom + 100 };
            } else {
                triangleApex = this.exitPoint || { x: flyoutRect.left, y: flyoutRect.top + flyoutRect.height / 2 };
                baseTop      = { x: parentRect.right, y: parentRect.top - 100 };
                baseBottom   = { x: parentRect.right, y: parentRect.bottom + 100 };
            }

            return this.pointInTriangle(cursor, triangleApex, baseTop, baseBottom);
        },

        pointInTriangle: function (p, v1, v2, v3) {
            var sign = function (p1, p2, p3) {
                return (p1.x - p3.x) * (p2.y - p3.y) - (p2.x - p3.x) * (p1.y - p3.y);
            };
            var d1 = sign(p, v1, v2);
            var d2 = sign(p, v2, v3);
            var d3 = sign(p, v3, v1);
            return !((d1 < 0 || d2 < 0 || d3 < 0) && (d1 > 0 || d2 > 0 || d3 > 0));
        },

        /* --- Smart positioning (prevent off-screen) --- */

        positionFlyout: function (parentLi, flyout) {
            flyout.style.top = '';
            var winH = window.innerHeight;
            var flyH = flyout.offsetHeight;
            var parentRect = parentLi.getBoundingClientRect();

            if (parentRect.top + flyH > winH) {
                var offset = winH - flyH - parentRect.top;
                if (offset < -parentRect.top) {
                    offset = -parentRect.top + 10;
                }
                flyout.style.top = offset + 'px';
            }
        },

        /* --- Mobile support --- */

        setupMobileSupport: function () {
            var self = this;
            if (window.innerWidth > 782) { return; }

            var links = document.querySelectorAll('#adminmenu li.eop-has-flyout > a');

            links.forEach(function (link) {
                link.addEventListener('click', function (e) {
                    var parentLi = link.parentElement;
                    var flyout = parentLi.querySelector('.eop-submenu-flyout');
                    if (!flyout) { return; }

                    if (parentLi.classList.contains('eop-flyout-open')) { return; }

                    e.preventDefault();
                    document.querySelectorAll('#adminmenu li.eop-has-flyout').forEach(function (item) {
                        item.classList.remove('eop-flyout-open');
                    });
                    parentLi.classList.add('eop-flyout-open');
                });
            });

            document.addEventListener('click', function (e) {
                if (!e.target.closest('#adminmenu li.eop-has-flyout')) {
                    document.querySelectorAll('#adminmenu li.eop-has-flyout').forEach(function (item) {
                        item.classList.remove('eop-flyout-open');
                    });
                }
            });
        }
    };

    /* ------------------------------------------------------------------ */
    /*  SidebarHighlight – keeps the correct menu item highlighted        */
    /* ------------------------------------------------------------------ */
    var SidebarHighlight = {

        handle: function () {
            var configs = FlyoutRenderer.collectConfigs();

            for (var i = 0; i < configs.length; i++) {
                this.highlightOne(configs[i]);
            }
        },

        highlightOne: function (cfg) {
            if (!cfg || !Array.isArray(cfg.items)) { return; }

            var currentPage = String(cfg.currentPage || '');
            if (!currentPage) { return; }

            var isChildPage = cfg.items.some(function (item) {
                return item && String(item.key || '') === currentPage;
            });

            if (!isChildPage) { return; }

            var menuRoot = document.getElementById(String(cfg.menuRoot || ''));
            if (!menuRoot) { return; }

            /* Force-activate the Aireset top-level menu */
            document.querySelectorAll('#adminmenu li.wp-has-current-submenu').forEach(function (item) {
                if (item !== menuRoot) {
                    item.classList.remove('wp-has-current-submenu', 'wp-menu-open', 'selected');
                    item.classList.add('wp-not-current-submenu');
                    var a = item.querySelector(':scope > a');
                    if (a) { a.classList.remove('wp-has-current-submenu', 'wp-menu-open', 'current'); }
                }
            });

            menuRoot.classList.remove('wp-not-current-submenu');
            menuRoot.classList.add('wp-has-current-submenu', 'wp-menu-open', 'selected');

            var rootLink = menuRoot.querySelector(':scope > a.menu-top');
            if (rootLink) {
                rootLink.classList.add('wp-has-current-submenu', 'wp-menu-open');
            }

            /* Highlight the anchor submenu item */
            var anchorSlug = String(cfg.anchorPage || '');
            var submenuItems = menuRoot.querySelectorAll('.wp-submenu li');

            submenuItems.forEach(function (li) {
                var a = li.querySelector('a');
                if (!a) { return; }
                li.classList.remove('current');
                a.classList.remove('current');
                a.setAttribute('aria-current', '');

                var href = a.getAttribute('href') || '';
                if (href.indexOf('page=' + anchorSlug) !== -1) {
                    li.classList.add('current');
                    a.classList.add('current');
                    a.setAttribute('aria-current', 'page');
                }
            });
        }
    };

    /* ------------------------------------------------------------------ */
    /*  Bootstrap                                                          */
    /* ------------------------------------------------------------------ */
    function init() {
        if (FlyoutRenderer.renderAll()) {
            FlyoutInteraction.handle();
            SidebarHighlight.handle();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
