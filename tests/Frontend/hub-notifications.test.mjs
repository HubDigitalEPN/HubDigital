import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/hub-notifications.js', import.meta.url), 'utf8');

function browser() {
    let now = 10000, sequence = 0, observer, requestFailed;
    const timers = new Map(), events = new Map(), frames = [], feedback = [];
    class Element {
        constructor(tag) { this.tagName = tag; this.children = []; this.dataset = {}; this.attributes = {}; this.listeners = {}; this.hidden = false; this.className = ''; this.text = ''; }
        append(...children) { children.forEach(child => { child.parent = this; this.children.push(child); }); }
        remove() { if (this.parent) this.parent.children = this.parent.children.filter(child => child !== this); }
        setAttribute(name, value) { this.attributes[name] = value; }
        addEventListener(name, listener) { (this.listeners[name] ||= []).push(listener); }
        emit(name) { this.listeners[name]?.forEach(listener => listener()); }
        set textContent(value) { this.text = String(value); }
        get textContent() { return this.text + this.children.map(child => child.textContent).join(' '); }
        get innerText() { return this.textContent; }
        set innerHTML(_) { throw new Error('El contenido de un aviso no puede interpretarse como HTML'); }
        getClientRects() { return this.hidden || this.parent?.hidden ? [] : [{}]; }
        closest(selector) {
            if (selector.includes('.hub-in-app-toast') && this.className === 'hub-in-app-toast') return this;
            if (selector.includes('[data-hub-notification-root]') && this.attributes['data-hub-notification-root'] !== undefined) return this;
            return this.parent?.closest(selector) || null;
        }
        matches(selector) {
            if (selector === ':popover-open') return this.open === true;
            return selector.includes('[role="alert"]') && this.attributes.role === 'alert';
        }
        querySelector(selector) {
            const all = this.children.flatMap(child => [child, ...child.descendants()]);
            if (selector.startsWith('[data-hub-in-app-toast-id=')) return all.find(child => selector.includes(child.dataset.hubInAppToastId || '\u0000')) || null;
            return all.find(child => ['button', 'a', 'input', 'select', 'textarea'].includes(child.tagName)) || null;
        }
        descendants() { return this.children.flatMap(child => [child, ...child.descendants()]); }
        showPopover() { this.open = true; }
        hidePopover() { this.open = false; }
    }
    const body = new Element('body');
    const document = { body, readyState: 'complete', activeElement: 'campo-original',
        createElement: tag => new Element(tag),
        getElementById: id => body.descendants().find(child => child.id === id),
        querySelectorAll: () => feedback,
        addEventListener() {},
    };
    const context = vm.createContext({ document, CSS: { escape: value => value },
        Date: class extends Date { static now() { return now; } },
        window: { setTimeout(callback, delay) { const id = ++sequence; timers.set(id, { callback, at: now + delay }); return id; }, addEventListener(name, listener, capture) { events.set(name, { listener, capture }); } },
        clearTimeout: id => timers.delete(id), requestAnimationFrame: callback => frames.push(callback),
        getComputedStyle: () => ({ visibility: 'visible' }),
        MutationObserver: class { constructor(callback) { observer = callback; } observe() {} },
        location: { reload() {} },
    });
    const api = vm.runInContext(source.replaceAll('export ', '') + '\n({ showNotification, bootNotifications });', context);
    const notices = () => document.getElementById('hub-in-app-toast-tray')?.children || [];
    function flush() { while (frames.length) frames.shift()(); }
    return { ...api, document, events, notices, flush, timers,
        boot() { api.bootNotifications({ hook(name, callback) { assert.equal(name, 'request'); callback({ fail(handler) { requestFailed = handler; } }); } }); flush(); },
        failure(status) { let prevented = false; requestFailed({ status, content: '<html>información privada del servidor</html>', preventDefault() { prevented = true; } }); return prevented; },
        advance(ms) { now += ms; for (const [id, timer] of [...timers]) if (timer.at <= now) { timers.delete(id); timer.callback(); } },
        error(message) { const element = new Element('p'); element.setAttribute('role', 'alert'); element.textContent = message; feedback.push(element); body.append(element); return element; },
        mutate() { observer(); flush(); },
    };
}

test('los errores se anuncian sin interpretar HTML ni mover el foco del formulario', () => {
    const ui = browser();
    const notice = ui.showNotification({ message: '<img src=x onerror=alert(1)>', variant: 'danger' });
    assert.equal(notice.attributes.role, 'alert');
    assert.equal(notice.dataset.variant, 'error');
    assert.equal(notice.children[0].children[1].textContent, '<img src=x onerror=alert(1)>');
    assert.equal(ui.document.activeElement, 'campo-original');
    assert.equal(ui.document.getElementById('hub-in-app-toast-tray').open, true);
});

test('un aviso cierra a los cuatro segundos y también admite cierre manual', () => {
    const ui = browser();
    ui.showNotification({ message: 'Configuración guardada.', variant: 'success' });
    ui.advance(3999); assert.equal(ui.notices().length, 1);
    ui.advance(1); assert.equal(ui.notices().length, 0);
    const notice = ui.showNotification({ message: 'Segundo aviso' });
    assert.equal(notice.children[1].attributes['aria-label'], 'Cerrar notificación');
    notice.children[1].emit('click');
    assert.equal(ui.notices().length, 0);
    assert.equal(ui.timers.size, 0);
});

test('el foco permite leer y ejecutar la acción antes de reanudar el cierre', () => {
    const ui = browser(); let opened = false;
    const notice = ui.showNotification({ id: 'expediente-1', message: 'Solicitud recibida', action: 'Abrir expediente', onAction() { opened = true; } });
    notice.emit('focusin'); ui.advance(5000);
    assert.equal(ui.notices().length, 1);
    notice.children[0].children[2].emit('click'); assert.equal(opened, true);
    notice.emit('focusout'); ui.advance(4000);
    assert.equal(ui.notices().length, 0);
});

test('los eventos existentes comparten un presentador sin abrir los avisos anteriores', () => {
    const ui = browser(); ui.boot();
    for (const name of ['show-toast', 'toast', 'domain-error']) {
        const event = ui.events.get(name); let stopped = false;
        assert.equal(event.capture, true);
        event.listener({ detail: { message: name }, stopImmediatePropagation() { stopped = true; } });
        assert.equal(stopped, true);
    }
    assert.equal(ui.notices().length, 3);
    assert.equal(ui.notices()[2].dataset.variant, 'error');
});

test('los errores del formulario se agrupan y un cambio de mensaje vuelve a notificarse', () => {
    const ui = browser(), first = ui.error('Indica el código de institución.');
    ui.error('Indica el código de colección.'); ui.boot();
    assert.equal(ui.notices().length, 1);
    assert.match(ui.notices()[0].textContent, /institución.*\n.*colección/s);
    assert.equal(first.hidden, true);
    ui.mutate(); assert.equal(ui.notices().length, 1);
    first.textContent = 'El código no es válido.';
    ui.advance(500); ui.mutate();
    assert.equal(ui.notices().length, 2);
    assert.match(ui.notices()[1].textContent, /El código no es válido/);
});

test('un fallo HTTP evita el modal técnico y conserva privado el cuerpo de la respuesta', () => {
    const ui = browser(); ui.boot();
    assert.equal(ui.failure(500), true);
    assert.match(ui.notices()[0].textContent, /No se pudo completar/);
    assert.doesNotMatch(ui.notices()[0].textContent, /información privada/);
    assert.equal(ui.failure(419), true);
    assert.equal(ui.notices()[1].children[0].children[2].textContent, 'Recargar página');
});
