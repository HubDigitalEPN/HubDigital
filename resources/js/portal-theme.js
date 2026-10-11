// El portal tiene paleta propia; la preferencia de la cuenta se conserva intacta.
function temaPortal() {
    if (document.body?.classList.contains('portal-publico')) document.documentElement.classList.remove('dark');
}
temaPortal();
document.addEventListener('livewire:navigated', temaPortal);
