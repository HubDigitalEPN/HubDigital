const STATUS_ID = 'hub-connectivity-status';
let initialized = false;

function refreshConnectivityStatus() {
    const status = document.getElementById(STATUS_ID);
    if (!status) return;
    if (status.dataset.sessionUnavailable === 'true') return;

    const offline = navigator.onLine === false;
    status.hidden = !offline;
    status.dataset.state = offline ? 'offline' : 'online';
    const message = status.querySelector('[data-hub-connectivity-message]');
    if (message) {
        message.textContent = offline
            ? 'Sin conexión. La información visible podría no estar actualizada; las acciones pendientes no se han enviado.'
            : 'Conexión restablecida.';
    }
}

function initializeConnectivityStatus() {
    if (initialized) {
        refreshConnectivityStatus();
        return;
    }

    initialized = true;
    window.addEventListener('offline', refreshConnectivityStatus);
    window.addEventListener('online', refreshConnectivityStatus);
    refreshConnectivityStatus();
}

document.addEventListener('DOMContentLoaded', initializeConnectivityStatus, { once: true });
document.addEventListener('livewire:navigated', initializeConnectivityStatus);
