(() => {
    if (window.hubPlatformInformation) return;
    window.hubPlatformInformation = true;

    const contents = new Map();
    const paths = new Set(['/informacion/privacidad', '/informacion/terminos', '/informacion/licencia', '/informacion/citar', '/informacion/creditos']);
    let controller;
    let trigger;
    let revision = 0;
    let frame;
    let observer;

    function updateFooterSpace() {
        frame = null;
        const rect = document.querySelector('.platform-footer')?.getBoundingClientRect();
        const overlap = rect && rect.bottom > 0 ? Math.max(0, Math.min(rect.height, innerHeight - rect.top)) : 0;
        const value = `${Math.ceil(overlap)}px`;
        if (document.documentElement.style.getPropertyValue('--platform-footer-overlap') !== value) {
            document.documentElement.style.setProperty('--platform-footer-overlap', value);
        }
        const actionsHeight = `${Math.ceil(document.querySelector('.research-filter-actions')?.getBoundingClientRect().height || 0)}px`;
        if (document.documentElement.style.getPropertyValue('--platform-filter-actions-height') !== actionsHeight) {
            document.documentElement.style.setProperty('--platform-filter-actions-height', actionsHeight);
        }
    }

    function scheduleFooterSpace() {
        if (!frame) frame = requestAnimationFrame(updateFooterSpace);
    }

    function connectFooter() {
        observer?.disconnect();
        if (window.ResizeObserver) {
            observer = new ResizeObserver(scheduleFooterSpace);
            observer.observe(document.body);
            const footer = document.querySelector('.platform-footer');
            if (footer) observer.observe(footer);
            const actions = document.querySelector('.research-filter-actions');
            if (actions) observer.observe(actions);
        }
        scheduleFooterSpace();
    }

    async function loadInformation(dialog, link) {
        const ticket = ++revision;
        controller?.abort();
        controller = new AbortController();
        const body = dialog.querySelector('.platform-information-content');
        body.innerHTML = '<p role="status">Cargando información…</p>';
        body.scrollTop = 0;
        try {
            let html = contents.get(link.href);
            if (html === undefined) {
                const response = await fetch(link.href, {signal: controller.signal, credentials: 'same-origin'});
                if (!response.ok || new URL(response.url).origin !== location.origin) throw new Error('Información no disponible');
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const article = page.querySelector('[data-platform-information-content]');
                if (!article) throw new Error('Información no disponible');
                article.querySelectorAll('h1, [data-platform-information-return], script').forEach(element => element.remove());
                html = article.innerHTML;
                contents.set(link.href, html);
            }
            if (ticket !== revision || !dialog.isConnected || !dialog.open) return;
            body.innerHTML = html;
            body.scrollTop = 0;
        } catch (error) {
            if (error.name === 'AbortError' || ticket !== revision || !dialog.open) return;
            body.innerHTML = '<p role="alert">No se pudo cargar la información. Comprueba tu conexión y vuelve a intentarlo.</p><button type="button">Volver a cargar</button>';
            body.querySelector('button').addEventListener('click', () => loadInformation(dialog, link), {once: true});
        }
    }

    document.addEventListener('click', event => {
        const link = event.target.closest?.('a[data-platform-information]');
        if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey && event.button === 0) {
            const url = new URL(link.href);
            const dialog = document.getElementById('platform-information-dialog');
            if (!dialog || url.origin !== location.origin || !paths.has(url.pathname)) return;
            event.preventDefault();
            trigger = link;
            dialog.querySelector('#platform-information-title').textContent = link.textContent.trim();
            if (!dialog.open) dialog.showModal();
            dialog.querySelector('[data-platform-information-close]').focus();
            loadInformation(dialog, link);
            return;
        }
        if (event.target.closest?.('[data-platform-information-close]')) event.target.closest('dialog')?.close();
        if (event.target.matches?.('.platform-information-dialog[open]')) {
            const rect = event.target.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) event.target.close();
        }
    });

    document.addEventListener('close', event => {
        if (!event.target.matches?.('.platform-information-dialog')) return;
        if (event.target.open) return;
        revision++;
        controller?.abort();
        if (trigger?.isConnected) trigger.focus({preventScroll: true});
        scheduleFooterSpace();
    }, true);
    document.addEventListener('livewire:navigating', () => {
        controller?.abort();
        document.querySelector('.platform-information-dialog[open]')?.close();
    });
    document.addEventListener('livewire:navigated', connectFooter);
    addEventListener('scroll', scheduleFooterSpace, {passive: true});
    addEventListener('resize', scheduleFooterSpace, {passive: true});
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', connectFooter, {once: true});
    else connectFooter();
})();
