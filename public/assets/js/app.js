/**
 * Stovel front-end helpers — vanilla JS, no dependencies.
 */
(function () {
    'use strict';

    const doc = document;

    /* —— Drawer —— */
    function initDrawer() {
        const toggle = doc.querySelector('[data-drawer-toggle]');
        const drawer = doc.getElementById('app-drawer');
        if (!toggle || !drawer) return;

        const panel = drawer.querySelector('.drawer-panel');
        drawer.removeAttribute('hidden');

        function open() {
            drawer.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            doc.body.style.overflow = 'hidden';
        }

        function close() {
            drawer.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            doc.body.style.overflow = '';
        }

        toggle.addEventListener('click', () => {
            if (drawer.classList.contains('is-open')) close();
            else open();
        });

        drawer.addEventListener('click', (e) => {
            if (e.target === drawer) close();
        });

        if (panel) {
            panel.querySelectorAll('a').forEach((link) => {
                link.addEventListener('click', () => close());
            });
        }

        doc.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') close();
        });
    }

    /* —— Modals —— */
    function initModals() {
        doc.querySelectorAll('[data-modal-open]').forEach((trigger) => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                const id = trigger.getAttribute('data-modal-open');
                const backdrop = id ? doc.getElementById(id) : null;
                if (backdrop) backdrop.classList.add('is-open');
            });
        });

        doc.querySelectorAll('[data-modal-close]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const backdrop = btn.closest('.modal-backdrop');
                if (backdrop) backdrop.classList.remove('is-open');
            });
        });

        doc.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) backdrop.classList.remove('is-open');
            });
        });
    }

    /* —— Confirm destructive actions —— */
    function initConfirm() {
        doc.querySelectorAll('[data-confirm]').forEach((el) => {
            el.addEventListener('click', (e) => {
                const message = el.getAttribute('data-confirm') || 'Are you sure?';
                if (!window.confirm(message)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        });

        doc.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (e) => {
                const message = form.getAttribute('data-confirm') || 'Are you sure?';
                if (!window.confirm(message)) e.preventDefault();
            });
        });
    }

    /* —— Disable submit while posting —— */
    function initDisableOnSubmit() {
        doc.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', () => {
                const btn = form.querySelector('[type="submit"]');
                if (!btn || btn.disabled) return;
                btn.disabled = true;
                btn.classList.add('is-disabled');
                const label = btn.textContent;
                btn.dataset.originalLabel = label;
                btn.textContent = 'Please wait…';
                window.setTimeout(() => {
                    btn.disabled = false;
                    btn.classList.remove('is-disabled');
                    if (btn.dataset.originalLabel) btn.textContent = btn.dataset.originalLabel;
                }, 15000);
            });
        });
    }

    /* —— Table client-side search —— */
    function initTableSearch() {
        doc.querySelectorAll('[data-table-search]').forEach((input) => {
            const tableId = input.getAttribute('data-table-search');
            const table = tableId ? doc.getElementById(tableId) : input.closest('.table-wrap')?.querySelector('.table');
            if (!table) return;

            input.classList.add('table-search');
            input.setAttribute('placeholder', input.getAttribute('placeholder') || 'Search table…');
            input.setAttribute('aria-label', 'Filter table rows');

            input.addEventListener('input', () => {
                const q = input.value.trim().toLowerCase();
                table.querySelectorAll('tbody tr').forEach((row) => {
                    const text = row.textContent?.toLowerCase() || '';
                    row.style.display = !q || text.includes(q) ? '' : 'none';
                });
            });
        });
    }

    /* —— Toasts from flash alerts —— */
    function initToasts() {
        let stack = doc.querySelector('.toast-stack');
        if (!stack) {
            stack = doc.createElement('div');
            stack.className = 'toast-stack';
            stack.setAttribute('aria-live', 'polite');
            doc.body.appendChild(stack);
        }

        doc.querySelectorAll('.alert').forEach((alert, i) => {
            if (i > 0) return;
            const type = alert.className.includes('danger') ? 'error' : 'success';
            showToast(alert.textContent?.trim() || '', type, 5000);
        });
    }

    function showToast(message, type, durationMs) {
        let stack = doc.querySelector('.toast-stack');
        if (!stack) {
            stack = doc.createElement('div');
            stack.className = 'toast-stack';
            doc.body.appendChild(stack);
        }
        const el = doc.createElement('div');
        el.className = 'toast toast-' + (type || 'info');
        el.textContent = message;
        stack.appendChild(el);
        window.setTimeout(() => {
            el.remove();
            if (!stack.childElementCount) stack.remove();
        }, durationMs || 4000);
    }

    /* —— StovelCharts — lightweight SVG helpers —— */
    const StovelCharts = {
        barChart(container, labels, values, options) {
            options = options || {};
            const width = options.width || 320;
            const height = options.height || 160;
            const max = Math.max.apply(null, values.concat([1]));
            const barW = width / values.length - 8;
            const svgNS = 'http://www.w3.org/2000/svg';
            const svg = doc.createElementNS(svgNS, 'svg');
            svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
            svg.setAttribute('role', 'img');
            svg.setAttribute('aria-label', options.title || 'Bar chart');

            values.forEach((v, i) => {
                const h = (v / max) * (height - 24);
                const rect = doc.createElementNS(svgNS, 'rect');
                rect.setAttribute('x', String(4 + i * (barW + 8)));
                rect.setAttribute('y', String(height - h - 4));
                rect.setAttribute('width', String(barW));
                rect.setAttribute('height', String(h));
                rect.setAttribute('rx', '3');
                rect.setAttribute('fill', options.color || '#0d6b4f');
                svg.appendChild(rect);

                if (labels[i]) {
                    const text = doc.createElementNS(svgNS, 'text');
                    text.setAttribute('x', String(4 + i * (barW + 8) + barW / 2));
                    text.setAttribute('y', String(height - 2));
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('font-size', '9');
                    text.setAttribute('fill', '#5c6f68');
                    text.textContent = labels[i].slice(0, 6);
                    svg.appendChild(text);
                }
            });

            container.innerHTML = '';
            container.appendChild(svg);
            return svg;
        },

        sparkline(container, values, options) {
            options = options || {};
            const width = options.width || 200;
            const height = options.height || 48;
            const max = Math.max.apply(null, values.concat([1]));
            const min = Math.min.apply(null, values.concat([0]));
            const range = max - min || 1;
            const step = width / Math.max(values.length - 1, 1);
            const points = values.map((v, i) => {
                const x = i * step;
                const y = height - ((v - min) / range) * (height - 8) - 4;
                return x + ',' + y;
            }).join(' ');

            const svgNS = 'http://www.w3.org/2000/svg';
            const svg = doc.createElementNS(svgNS, 'svg');
            svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
            const poly = doc.createElementNS(svgNS, 'polyline');
            poly.setAttribute('points', points);
            poly.setAttribute('fill', 'none');
            poly.setAttribute('stroke', options.color || '#14b88a');
            poly.setAttribute('stroke-width', '2');
            poly.setAttribute('stroke-linecap', 'round');
            svg.appendChild(poly);
            container.innerHTML = '';
            container.appendChild(svg);
            return svg;
        },

        donut(container, segments, options) {
            options = options || {};
            const size = options.size || 120;
            const total = segments.reduce((s, seg) => s + seg.value, 0) || 1;
            const svgNS = 'http://www.w3.org/2000/svg';
            const svg = doc.createElementNS(svgNS, 'svg');
            svg.setAttribute('viewBox', '0 0 ' + size + ' ' + size);
            const r = size / 2 - 6;
            const cx = size / 2;
            const cy = size / 2;
            let angle = -Math.PI / 2;
            const colors = options.colors || ['#0d6b4f', '#14b88a', '#b7791f', '#5c6f68'];

            segments.forEach((seg, i) => {
                const slice = (seg.value / total) * Math.PI * 2;
                const x1 = cx + r * Math.cos(angle);
                const y1 = cy + r * Math.sin(angle);
                angle += slice;
                const x2 = cx + r * Math.cos(angle);
                const y2 = cy + r * Math.sin(angle);
                const large = slice > Math.PI ? 1 : 0;
                const path = doc.createElementNS(svgNS, 'path');
                path.setAttribute('d', 'M ' + cx + ' ' + cy + ' L ' + x1 + ' ' + y1 + ' A ' + r + ' ' + r + ' 0 ' + large + ' 1 ' + x2 + ' ' + y2 + ' Z');
                path.setAttribute('fill', seg.color || colors[i % colors.length]);
                svg.appendChild(path);
            });

            container.innerHTML = '';
            container.appendChild(svg);
            return svg;
        }
    };

    window.StovelCharts = StovelCharts;
    window.StovelUI = { showToast: showToast };

    function init() {
        initDrawer();
        initModals();
        initConfirm();
        initDisableOnSubmit();
        initTableSearch();
        initToasts();
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
