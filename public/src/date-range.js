// Date-range picker de Gentelella v4 (extraído de form-controls.js, sin dependencias).
// Markup:  <div class="date-range" data-date-range><input type="text" class="form-control" readonly></div>
// Emite 'change' en el wrapper con detail { from: Date, to: Date }.
(function () {
    const MONTHS = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const DOW = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];

    function fmt(d) {
        if (!d) return '';
        return d.toLocaleDateString('es-PE', { day: 'numeric', month: 'short', year: 'numeric' });
    }
    function isoDay(d) {
        return new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
    }

    function buildMonth(year, month, fromTs, toTs, hoverTs) {
        const first = new Date(year, month, 1);
        const startWeekday = (first.getDay() + 6) % 7;
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const cells = [];
        for (let i = 0; i < startWeekday; i += 1) cells.push(null);
        for (let d = 1; d <= daysInMonth; d += 1) cells.push(new Date(year, month, d));
        while (cells.length % 7 !== 0) cells.push(null);

        const html = cells.map((cell) => {
            if (!cell) return '<div class="dr-cell empty"></div>';
            const ts = isoDay(cell);
            const cls = ['dr-cell'];
            if (fromTs && ts === fromTs) cls.push('selected', 'range-start');
            if (toTs && ts === toTs) cls.push('selected', 'range-end');
            if (fromTs && toTs && ts > fromTs && ts < toTs) cls.push('in-range');
            if (fromTs && !toTs && hoverTs && ts > fromTs && ts <= hoverTs) cls.push('in-range', 'preview');
            if (ts === isoDay(new Date())) cls.push('today');
            return `<button type="button" class="${cls.join(' ')}" data-ts="${ts}">${cell.getDate()}</button>`;
        }).join('');

        return `
            <div class="dr-month">
                <div class="dr-month-head">${MONTHS[month]} ${year}</div>
                <div class="dr-dow">${DOW.map((d) => `<span>${d}</span>`).join('')}</div>
                <div class="dr-grid">${html}</div>
            </div>`;
    }

    function initDateRange(wrap) {
        if (wrap.dataset.drInit) return;
        wrap.dataset.drInit = '1';
        const input = wrap.querySelector('input');
        if (!input) return;
        input.readOnly = true;

        const state = { from: null, to: null, hover: null, pivot: new Date() };

        const popover = document.createElement('div');
        popover.className = 'dr-popover';
        popover.hidden = true;
        popover.style.display = 'none';   // .dr-popover tiene display:flex y gana a [hidden]
        document.body.appendChild(popover);

        const presets = [
            { label: 'Hoy',           get: () => { const d = new Date(); return [d, d]; } },
            { label: 'Últimos 7 días', get: () => { const a = new Date(); const b = new Date(); a.setDate(a.getDate() - 6); return [a, b]; } },
            { label: 'Últimos 30 días', get: () => { const a = new Date(); const b = new Date(); a.setDate(a.getDate() - 29); return [a, b]; } },
            { label: 'Este mes',      get: () => { const b = new Date(); const a = new Date(b.getFullYear(), b.getMonth(), 1); return [a, b]; } },
            { label: 'Mes anterior',  get: () => { const t = new Date(); const a = new Date(t.getFullYear(), t.getMonth() - 1, 1); const b = new Date(t.getFullYear(), t.getMonth(), 0); return [a, b]; } },
            { label: 'Este año',      get: () => { const b = new Date(); const a = new Date(b.getFullYear(), 0, 1); return [a, b]; } },
        ];

        const render = () => {
            const m1 = state.pivot;
            const m2 = new Date(m1.getFullYear(), m1.getMonth() + 1, 1);
            const fromTs = state.from ? isoDay(state.from) : null;
            const toTs = state.to ? isoDay(state.to) : null;
            const hoverTs = state.hover ? isoDay(state.hover) : null;
            popover.innerHTML = `
                <div class="dr-presets">
                    ${presets.map((p) => `<button type="button" class="dr-preset" data-preset="${p.label}">${p.label}</button>`).join('')}
                </div>
                <div class="dr-cal">
                    <div class="dr-nav">
                        <button type="button" class="dr-prev" aria-label="Mes anterior"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 4L6 8l4 4"/></svg></button>
                        <div class="dr-spacer"></div>
                        <button type="button" class="dr-next" aria-label="Mes siguiente"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 4l4 4-4 4"/></svg></button>
                    </div>
                    <div class="dr-months">
                        ${buildMonth(m1.getFullYear(), m1.getMonth(), fromTs, toTs, hoverTs)}
                        ${buildMonth(m2.getFullYear(), m2.getMonth(), fromTs, toTs, hoverTs)}
                    </div>
                    <div class="dr-footer">
                        <div class="dr-summary">${state.from ? fmt(state.from) : '—'} → ${state.to ? fmt(state.to) : '—'}</div>
                        <div style="display:flex;gap:6px">
                            <button type="button" class="btn btn-ghost btn-sm" data-action="close">Cerrar</button>
                            <button type="button" class="btn btn-ghost btn-sm" data-action="clear">Limpiar</button>
                            <button type="button" class="btn btn-primary btn-sm" data-action="apply" ${state.from ? '' : 'disabled'}>Aplicar</button>
                        </div>
                    </div>
                </div>`;
        };

        const position = () => {
            const r = input.getBoundingClientRect();
            popover.style.position = 'fixed';
            popover.style.top = `${r.bottom + 6}px`;
            popover.style.left = `${Math.max(8, Math.min(r.left, window.innerWidth - popover.offsetWidth - 8))}px`;
        };

        const open = () => { popover.hidden = false; popover.style.display = ''; render(); requestAnimationFrame(position); wrap.classList.add('open'); };
        const close = () => { popover.hidden = true; popover.style.display = 'none'; wrap.classList.remove('open'); };

        // Clic en el campo: abre o cierra (toggle)
        input.addEventListener('click', () => { popover.hidden ? open() : close(); });

        // Reset externo: limpia selección y notifica
        wrap.__clear = () => {
            state.from = state.to = state.hover = null;
            input.value = '';
            wrap.dispatchEvent(new CustomEvent('change', { detail: { from: null, to: null } }));
        };

        popover.addEventListener('click', (e) => {
            const cell = e.target.closest('.dr-cell:not(.empty)');
            if (cell) {
                const ts = parseInt(cell.dataset.ts, 10);
                const d = new Date(ts);
                if (!state.from || (state.from && state.to)) { state.from = d; state.to = null; state.hover = null; }
                else if (ts < isoDay(state.from)) { state.to = state.from; state.from = d; }
                else { state.to = d; }
                render();
                return;
            }
            if (e.target.closest('.dr-prev')) { state.pivot = new Date(state.pivot.getFullYear(), state.pivot.getMonth() - 1, 1); render(); }
            else if (e.target.closest('.dr-next')) { state.pivot = new Date(state.pivot.getFullYear(), state.pivot.getMonth() + 1, 1); render(); }
            else if (e.target.closest('[data-preset]')) {
                const p = presets.find((x) => x.label === e.target.closest('[data-preset]').dataset.preset);
                if (p) { const [a, b] = p.get(); state.from = a; state.to = b; state.pivot = new Date(a.getFullYear(), a.getMonth(), 1); render(); }
            }
            else if (e.target.closest('[data-action="close"]')) { close(); }
            else if (e.target.closest('[data-action="clear"]')) { state.from = state.to = null; render(); }
            else if (e.target.closest('[data-action="apply"]')) {
                input.value = state.from ? `${fmt(state.from)} → ${fmt(state.to || state.from)}` : '';
                wrap.dispatchEvent(new CustomEvent('change', { detail: { from: state.from, to: state.to || state.from } }));
                close();
            }
        });

        popover.addEventListener('mouseover', (e) => {
            if (state.from && !state.to) {
                const cell = e.target.closest('.dr-cell:not(.empty)');
                if (cell) { state.hover = new Date(parseInt(cell.dataset.ts, 10)); render(); }
            }
        });

        document.addEventListener('click', (e) => {
            if (popover.hidden) return;
            if (popover.contains(e.target) || wrap.contains(e.target)) return;
            close();
        }, true);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !popover.hidden) close(); });
        window.addEventListener('resize', () => { if (!popover.hidden) position(); });
    }

    window.initDateRange = initDateRange;
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-date-range]').forEach(initDateRange);
    });
})();