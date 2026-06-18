// Widget de <select> buscable (mismo de Crear Orden, extraído para reuso).
// Uso:  <select class="form-control searchable">...  → se realza al cargar el DOM.
// Expone select.__ss = { refresh, setDisabled } para sincronizar cambios por JS.
(function () {
    function enhance(select) {
        if (select.__ss) return;   // ya realzado
        const placeholder = (select.options[0] && select.options[0].value === '') ? select.options[0].text : 'Buscar...';

        const wrap = document.createElement('div');
        wrap.className = 'multi-select ss';
        wrap.innerHTML = `
            <div class="ms-input">
                <input type="text" class="ms-search" autocomplete="off" placeholder="${placeholder}">
                <svg class="ms-chev" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6l4 4 4-4"/></svg>
            </div>
            <div class="ms-menu" hidden></div>`;
        select.style.display = 'none';
        select.removeAttribute('required');                 // se valida en el servidor
        select.parentNode.insertBefore(wrap, select.nextSibling);

        const input = wrap.querySelector('.ms-search');
        const menu  = wrap.querySelector('.ms-menu');

        let options  = [];
        let curValue = '';
        let curLabel = '';

        function readOptions() {
            options  = [...select.options].map(o => ({ value: o.value, label: o.text }));
            curValue = select.value;
            curLabel = (options.find(o => o.value === curValue) || {}).label || '';
            input.value = curValue ? curLabel : '';
        }

        function renderMenu(q = '') {
            const ql = q.toLowerCase();
            const list = options.filter(o => o.value !== '' && o.label.toLowerCase().includes(ql));
            menu.innerHTML = list.length
                ? list.map(o => `<button type="button" class="ms-option${o.value === curValue ? ' active' : ''}" data-value="${o.value}">${o.label}</button>`).join('')
                : `<div class="ms-empty">Sin coincidencias</div>`;
        }
        const open  = () => { if (input.disabled) return; wrap.classList.add('open'); menu.hidden = false; renderMenu(input.value === curLabel ? '' : input.value); };
        const close = () => { wrap.classList.remove('open'); menu.hidden = true; };

        function choose(value, label) {
            curValue = value; curLabel = label;
            select.value = value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            input.value = label;
            close();
        }

        wrap.querySelector('.ms-input').addEventListener('click', () => { if (!input.disabled) input.focus(); });
        input.addEventListener('focus', () => { open(); input.select(); });
        input.addEventListener('input', () => { open(); renderMenu(input.value); });
        menu.addEventListener('mousedown', (e) => {
            const opt = e.target.closest('.ms-option');
            if (!opt) return;
            e.preventDefault();
            const o = options.find(x => x.value === opt.dataset.value);
            if (o) choose(o.value, o.label);
        });
        input.addEventListener('blur', () => setTimeout(() => { close(); input.value = curValue ? curLabel : ''; }, 120));
        document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) { close(); input.value = curValue ? curLabel : ''; } });

        function setDisabled(off) {
            input.disabled = off;
            wrap.classList.toggle('ss-disabled', off);
            readOptions();
        }
        setDisabled(select.disabled);
        readOptions();

        select.__ss = { refresh: readOptions, setDisabled };
    }

    window.enhanceSearchable = enhance;
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('select.searchable').forEach(enhance);
    });
})();