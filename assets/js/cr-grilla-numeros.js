(function (global) {
    'use strict';

    const STATUS = { AVAILABLE: 0, PAID: 1, RESERVED: 2, CANCELLED: 3 };
    const ITEMS_POR_PAGINA_MOBILE = 50;
    const ITEMS_POR_PAGINA_DESKTOP = 80;

    const uiState = new Map();

    function escapeHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function parseStatus(t) {
        return Number(t?.status_ticket);
    }

    function isDisponible(t) {
        return parseStatus(t) === STATUS.AVAILABLE;
    }

    
    function isVisibleInGrilla(t) {
        const st = parseStatus(t);
        return st === STATUS.AVAILABLE || st === STATUS.PAID;
    }

    function filterVisibleItems(items) {
        return (items || []).filter(isVisibleInGrilla);
    }

    function itemsPorPagina() {
        return global.matchMedia('(max-width: 767.98px)').matches
            ? ITEMS_POR_PAGINA_MOBILE
            : ITEMS_POR_PAGINA_DESKTOP;
    }

    function getUi(gridId) {
        if (!uiState.has(gridId)) {
            uiState.set(gridId, { pagina: 1, busqueda: '' });
        }
        return uiState.get(gridId);
    }

    function resetUi(gridId) {
        uiState.set(gridId, { pagina: 1, busqueda: '' });
    }

    function contarDisponibles(items) {
        return items.filter(isDisponible).length;
    }

    function filterItems(items, q) {
        const query = String(q ?? '').replace(/\D/g, '');
        if (!query) return items;
        return items.filter(t => String(t.number_ticket).includes(query));
    }

    function statusClass(t) {
        switch (parseStatus(t)) {
            case STATUS.PAID:
                return 'cr-grilla-num--vendido';
            case STATUS.RESERVED:
                return 'cr-grilla-num--reservado';
            case STATUS.CANCELLED:
                return 'cr-grilla-num--vendido';
            default:
                return '';
        }
    }

    function statusLabel(t) {
        switch (parseStatus(t)) {
            case STATUS.PAID:
                return 'Vendido';
            case STATUS.RESERVED:
                return 'Reservado';
            case STATUS.CANCELLED:
                return 'No disponible';
            default:
                return 'Disponible';
        }
    }

    function isPremium(t) {
        return Number(t?.is_premium_ticket) === 1;
    }

    function renderNumeroCell(t, selectedSet, onToggleName) {
        const id = parseInt(t.id_ticket, 10);
        const num = escapeHtml(t.number_ticket);
        const disponible = isDisponible(t);
        const selected = selectedSet.has(String(id));
        const classes = ['cr-grilla-num'];
        const stClass = statusClass(t);
        if (stClass) classes.push(stClass);
        if (isPremium(t) && disponible) classes.push('cr-grilla-num--premium');
        if (selected) classes.push('cr-grilla-num--selected');

        if (!disponible) {
            return `<span class="${classes.join(' ')}" data-ticket-id="${id}" role="img" aria-label="Nro ${num}, ${statusLabel(t)}" title="${statusLabel(t)}">${num}</span>`;
        }

        const fn = onToggleName || 'CrGrillaNumeros.toggle';
        return `<button type="button" class="${classes.join(' ')}" data-ticket-id="${id}" aria-pressed="${selected ? 'true' : 'false'}" aria-label="Nro ${num}, ${selected ? 'seleccionado' : 'disponible'}" onclick="${fn}(${id})">${num}</button>`;
    }

    function renderStats(statsEl, visibleItems, selectedCount) {
        if (!statsEl || statsEl.classList.contains('d-none')) return;
        const disp = contarDisponibles(visibleItems);
        const vend = visibleItems.filter(t => parseStatus(t) === STATUS.PAID).length;
        const total = visibleItems.length;

        statsEl.innerHTML = `
            <div class="cr-grilla-stat"><span class="cr-grilla-stat__dot cr-grilla-stat__dot--libre"></span>${disp} libres</div>
            <div class="cr-grilla-stat"><span class="cr-grilla-stat__dot cr-grilla-stat__dot--sel"></span>${selectedCount} elegidos</div>
            <div class="cr-grilla-stat"><span class="cr-grilla-stat__dot cr-grilla-stat__dot--vend"></span>${vend} vend.</div>
            <div class="cr-grilla-stat cr-grilla-stat--total">${total} en grilla</div>`;
    }

    function renderPager(pagerEl, pagina, totalPaginas, totalFiltrados) {
        if (!pagerEl) return;

        if (totalFiltrados === 0) {
            pagerEl.innerHTML = '';
            pagerEl.classList.add('d-none');
            return;
        }

        pagerEl.classList.remove('d-none');
        const prevDisabled = pagina <= 1 ? ' disabled' : '';
        const nextDisabled = pagina >= totalPaginas ? ' disabled' : '';

        pagerEl.innerHTML = `
            <button type="button" class="cr-grilla-pager__btn" data-grilla-pager="prev"${prevDisabled} aria-label="Página anterior">
                <i class="ti ti-chevron-left"></i>
            </button>
            <span class="cr-grilla-pager__info">${pagina} / ${totalPaginas}</span>
            <button type="button" class="cr-grilla-pager__btn" data-grilla-pager="next"${nextDisabled} aria-label="Página siguiente">
                <i class="ti ti-chevron-right"></i>
            </button>`;
    }

    function render(config) {
        const {
            gridId,
            pagerId,
            statsId,
            countId,
            searchId,
            items = [],
            selectedIds = [],
            serverStats = null,
            onToggleName = 'CrGrillaNumeros.toggle',
            emptyMessage = 'No hay nros para esta dinámica.',
        } = config;

        const grid = document.getElementById(gridId);
        if (!grid) return;

        const ui = getUi(gridId);
        const q = searchId
            ? String(document.getElementById(searchId)?.value ?? '').replace(/\D/g, '')
            : ui.busqueda;
        ui.busqueda = q;

        const visibles = filterVisibleItems(items);
        const filtrados = filterItems(visibles, q);
        const porPagina = itemsPorPagina();
        const totalPaginas = Math.max(1, Math.ceil(filtrados.length / porPagina));

        if (ui.pagina > totalPaginas) ui.pagina = totalPaginas;
        if (ui.pagina < 1) ui.pagina = 1;

        const inicio = (ui.pagina - 1) * porPagina;
        const paginaItems = filtrados.slice(inicio, inicio + porPagina);
        const selectedSet = new Set(selectedIds.map(String));

        if (filtrados.length === 0) {
            grid.innerHTML = `<p class="cr-grilla-empty">${escapeHtml(emptyMessage)}</p>`;
        } else {
            grid.innerHTML = paginaItems.map(t => renderNumeroCell(t, selectedSet, onToggleName)).join('');
        }

        const selectedCount = selectedIds.length;
        if (countId) {
            const el = document.getElementById(countId);
            if (el) el.textContent = String(selectedCount);
        }

        renderStats(document.getElementById(statsId), visibles, selectedCount);
        renderPager(document.getElementById(pagerId), ui.pagina, totalPaginas, filtrados.length);

        const pager = document.getElementById(pagerId);
        if (pager && !pager.dataset.bound) {
            pager.dataset.bound = '1';
            pager.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-grilla-pager]');
                if (!btn || btn.disabled) return;
                const st = getUi(gridId);
                if (btn.dataset.grillaPager === 'prev' && st.pagina > 1) {
                    st.pagina--;
                } else if (btn.dataset.grillaPager === 'next') {
                    st.pagina++;
                }
                if (typeof config.onPageChange === 'function') {
                    config.onPageChange();
                } else if (typeof config.rerender === 'function') {
                    config.rerender();
                }
            });
        }
    }

    function bindSearch(searchId, gridId, rerender) {
        const input = document.getElementById(searchId);
        if (!input || input.dataset.grillaBound) return;
        input.dataset.grillaBound = '1';

        input.addEventListener('input', () => {
            const ui = getUi(gridId);
            ui.pagina = 1;
            ui.busqueda = input.value.replace(/\D/g, '');
            rerender();
        });
    }

    function patchSelection(gridId, ticketId, isSelected) {
        const grid = document.getElementById(gridId);
        if (!grid) return false;

        const cell = grid.querySelector(`[data-ticket-id="${ticketId}"]`);
        if (!cell || !cell.matches('button.cr-grilla-num')) return false;

        cell.classList.toggle('cr-grilla-num--selected', isSelected);
        cell.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        const num = cell.textContent.trim();
        cell.setAttribute(
            'aria-label',
            `Nro ${num}, ${isSelected ? 'seleccionado' : 'disponible'}`
        );
        return true;
    }

    global.CrGrillaNumeros = {
        STATUS,
        isDisponible,
        contarDisponibles,
        filterVisibleItems,
        resetUi,
        resetPage(gridId) {
            getUi(gridId).pagina = 1;
        },
        render,
        patchSelection,
        bindSearch,
        escapeHtml,
    };
})(typeof window !== 'undefined' ? window : globalThis);
