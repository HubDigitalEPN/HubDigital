if ('serviceWorker' in navigator && window.isSecureContext) {
    navigator.serviceWorker.register('/service-worker.js', {scope: '/', updateViaCache: 'none'}).catch(() => {});
    navigator.serviceWorker.addEventListener('message', event => {
        if (event.data?.type === 'portal-taxonomy-updated') window.dispatchEvent(new CustomEvent('portal-taxonomy-updated'));
    });
}
