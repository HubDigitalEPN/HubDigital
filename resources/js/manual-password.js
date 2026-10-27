const message = 'Escribe la contraseña manualmente. No se permite autocompletar, pegar ni arrastrar una clave.';

/** Estado efímero: no persiste claves en cookies, storage ni atributos del DOM. */
export function manualPasswordEntry() {
    let value = '', pending = false, blocked = false;
    return {
        reset() { value = ''; pending = false; blocked = false; },
        before(event) {
            // Enter confirma el formulario; no escribe ni reemplaza la clave.
            if (event.isTrusted === true && event.inputType === 'insertLineBreak') {
                pending = 'submit';
                return true;
            }
            const editing = ['deleteContentBackward', 'deleteContentForward', 'deleteByCut', 'insertCompositionText', 'deleteCompositionText', 'insertFromComposition'];
            pending = event.isTrusted === true && (editing.includes(event.inputType)
                || (event.inputType === 'insertText' && [...(event.data || '')].length === 1));
            if (!pending) { value = ''; blocked = true; }
            return pending;
        },
        input(event, next) {
            const accepted = event.isTrusted === true && (pending === true
                || (pending === 'submit' && !blocked && next === value));
            pending = false;
            if (accepted) { value = next; blocked = false; }
            else { value = ''; blocked = true; }
            return accepted;
        },
        valid(next) { return !blocked && next === value; },
    };
}

export function bootManualPasswords() {
    const states = new WeakMap();
    const activeFields = new Set();
    let clearing = false;
    const fields = root => [...root.querySelectorAll('input[type="password"],input[data-manual-password]')];
    function clear(field, error = false) {
        states.get(field)?.reset();
        field.value = '';
        field.setCustomValidity(error ? message : '');
        // Mantener vacío también el modelo de Livewire, sin propagar el valor rechazado.
        clearing = true;
        try { field.dispatchEvent(new Event('input', { bubbles: true })); }
        finally { clearing = false; }
    }
    function prepare(field) {
        if (states.has(field)) return;
        states.set(field, manualPasswordEntry());
        activeFields.add(field);
        field.setAttribute('data-manual-password', '');
        field.setAttribute('autocomplete', 'off');
        field.setAttribute('data-lpignore', 'true');
        field.setAttribute('data-1p-ignore', 'true');
        field.setAttribute('autocapitalize', 'off');
        field.setAttribute('spellcheck', 'false');
        field.form?.setAttribute('autocomplete', 'off');
        field.closest('[data-flux-input]')?.querySelectorAll('button[x-data="fluxInputViewable"]')
            .forEach(button => { button.tabIndex = -1; });
        clear(field);
    }
    const scan = () => fields(document).forEach(prepare);
    const getField = event => event.target?.matches?.('input[type="password"],input[data-manual-password]') ? event.target : null;
    document.addEventListener('beforeinput', event => {
        const field = getField(event);
        if (!field) return;
        prepare(field);
        if (!states.get(field).before(event)) { event.preventDefault(); clear(field, true); }
    }, true);
    document.addEventListener('input', event => {
        const field = getField(event);
        if (!field || clearing) return;
        prepare(field);
        if (!states.get(field).input(event, field.value)) {
            event.stopImmediatePropagation();
            clear(field, true);
        } else field.setCustomValidity('');
    }, true);
    for (const name of ['paste', 'drop']) document.addEventListener(name, event => {
        const field = getField(event);
        if (!field) return;
        prepare(field);
        event.preventDefault();
        clear(field, true);
    }, true);
    document.addEventListener('submit', event => {
        for (const field of fields(event.target)) {
            prepare(field);
            if (!states.get(field).valid(field.value) || field.validationMessage === message) {
                event.preventDefault();
                event.stopImmediatePropagation();
                clear(field, true);
                field.focus();
                field.reportValidity();
                return;
            }
        }
    }, true);
    // Algunos gestores cambian value sin emitir eventos. Comprobarlo antes de
    // enviar y durante la presencia del campo, incluido tras mostrar la clave.
    window.setInterval(() => activeFields.forEach(field => {
        if (!field.isConnected) { activeFields.delete(field); return; }
        if (!states.get(field).valid(field.value)) clear(field, true);
    }), 250);
    const clearAll = () => {
        scan(); fields(document).forEach(field => clear(field));
    };
    window.addEventListener('pagehide', clearAll);
    window.addEventListener('pageshow', event => { if (event.persisted) clearAll(); });
    document.addEventListener('livewire:navigated', scan);
    new MutationObserver(mutations => {
        for (const mutation of mutations) for (const node of mutation.addedNodes) {
            if (node.nodeType !== 1) continue;
            if (node.matches('input[type="password"],input[data-manual-password]')) prepare(node);
            fields(node).forEach(prepare);
        }
    }).observe(document.documentElement, { childList: true, subtree: true });
    scan();
}
