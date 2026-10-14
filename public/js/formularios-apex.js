(() => {
    if (window.hubApexFormsInitialized) return;
    window.hubApexFormsInitialized = true;
    const selector = 'input, select, textarea, button.hub-lov-trigger';
    const buttons = 'button[data-flux-button], a[data-flux-button], form button, form a[class]';
    const excluded = new Set(['hidden', 'checkbox', 'radio', 'file', 'range', 'color', 'button', 'submit', 'reset']);
    const eligible = control => !(control.tagName === 'INPUT' && excluded.has(control.type));
    const mark = (element, attribute) => {
        if (!element.hasAttribute(attribute)) element.setAttribute(attribute, '');
    };
    function decorate(control) {
        if (!eligible(control)) return;
        mark(control, 'data-hub-apex-control');
        let field = control.closest('[data-flux-field]');
        let label = field?.querySelector('[data-flux-label], .hub-lov-label');
        if (!field) {
            label = [...(control.labels || [])].find(item => !item.classList.contains('sr-only'));
            let candidate = control.parentElement;
            for (let depth = 0; label && candidate && depth < 3; depth++, candidate = candidate.parentElement) {
                if (candidate.contains(label)) { field = candidate; break; }
            }
        }
        if (!field || !label || label.classList.contains('sr-only')) return;
        const controls = [...field.querySelectorAll(selector)].filter(eligible)
            .filter(item => !item.closest('.hub-lov-overlay'));
        if (controls.length !== 1 || controls[0] !== control) return;
        mark(field, 'data-hub-apex-field');
        mark(label, 'data-hub-apex-label');
        if (label.parentElement !== field && label.parentElement.querySelector('a[href]')) {
            mark(label.parentElement, 'data-hub-apex-label-row');
        }
        if (control.tagName === 'INPUT' && !control.hasAttribute('placeholder')) {
            // Sólo presentación: conserva valores y no dispara validaciones.
            control.setAttribute('placeholder', ' ');
        }
    }
    function enhance(root) {
        if (root.nodeType !== Node.ELEMENT_NODE && root !== document) return;
        positionNotices();
        revealCurrentStep();
        const modals = [...root.querySelectorAll('ui-modal[aria-labelledby]')];
        if (root.matches?.('ui-modal[aria-labelledby]')) modals.push(root);
        modals.forEach(modal => {
            const dialog = modal.querySelector('dialog');
            const label = modal.getAttribute('aria-labelledby');
            if (dialog && dialog.getAttribute('aria-labelledby') !== label) dialog.setAttribute('aria-labelledby', label);
        });
        if (root.matches?.(selector)) decorate(root);
        root.querySelectorAll(selector).forEach(decorate);
        if (root.matches?.(buttons)) alignPrimary(root);
        root.querySelectorAll(buttons).forEach(alignPrimary);
    }
    function alignPrimary(button) {
        const primary = button.classList.contains('bg-[var(--color-accent)]')
            || ['bg-accent', 'bg-science-blue', 'bg-blue-navy'].some(name => button.classList.contains(name));
        if (primary) mark(button, 'data-hub-primary-button');
    }
    function positionNotices() {
        const headers = [...document.querySelectorAll('header, [role="banner"]')].filter(e => !e.closest('dialog'));
        const bottom = headers.reduce((value, header) => {
            const rect = header.getBoundingClientRect();
            return rect.width > 0 && rect.top <= 1 && rect.bottom > 0 ? Math.max(value, rect.bottom) : value;
        }, 0);
        const top = Math.ceil(bottom + 16) + 'px';
        if (document.documentElement.style.getPropertyValue('--hub-floating-notice-top') !== top) {
            document.documentElement.style.setProperty('--hub-floating-notice-top', top);
        }
    }
    let previousStep = null;
    function revealCurrentStep(force = false) {
        const current = document.querySelector('.hub-wizard-step[aria-current="step"]');
        if (!current || (!force && current === previousStep)) return;
        previousStep = current;
        const wrapper = current.closest('.hub-wizard-stepper-scroll');
        const list = current.closest('.hub-wizard-steps');
        const scroll = list?.scrollWidth > list?.clientWidth ? list : wrapper;
        if (!scroll) return;
        const bounds = scroll.getBoundingClientRect(), step = current.getBoundingClientRect();
        if (step.left < bounds.left + 8) scroll.scrollLeft -= bounds.left + 8 - step.left;
        else if (step.right > bounds.right - 8) scroll.scrollLeft += step.right - bounds.right + 8;
    }
    const pending = new Set();
    let frame = 0;
    function schedule(element) {
        if (element.nodeType !== Node.ELEMENT_NODE) return;
        pending.add(element.closest('[data-flux-field]') || element);
        if (frame) return;
        frame = requestAnimationFrame(() => {
            frame = 0;
            const roots = [...pending];
            pending.clear();
            roots.forEach(enhance);
        });
    }
    enhance(document);
    window.addEventListener('resize', () => requestAnimationFrame(() => { positionNotices(); revealCurrentStep(true); }), {passive: true});
    const observer = new MutationObserver(records => {
        for (const record of records) {
            if (record.type === 'attributes') schedule(record.target);
            else {
                record.addedNodes.forEach(schedule);
                schedule(record.target);
            }
        }
    });
    const options = {
        childList: true, subtree: true, attributes: true,
        attributeFilter: ['data-hub-apex-field', 'data-hub-apex-control', 'data-hub-apex-label', 'data-hub-primary-button'],
    };
    observer.observe(document.body, options);
    document.addEventListener('livewire:navigated', () => {
        enhance(document);
        observer.disconnect();
        observer.observe(document.body, options);
    });
})();
