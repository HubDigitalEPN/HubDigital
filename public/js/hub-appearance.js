(() => {
    const key = 'flux.appearance';
    const valid = appearance => ['light', 'dark', 'system'].includes(appearance);
    function applyAppearance(appearance) {
        const selected = valid(appearance) ? appearance : 'light';
        // También en el portal: Flux no debe borrar la elección de la cuenta.
        window.localStorage.setItem(key, selected);
        const publicPortal = document.documentElement.dataset.portalTheme === 'light';
        const dark = !publicPortal && (selected === 'dark'
            || selected === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    }
    window.Flux = {...window.Flux, applyAppearance};
    // Claro es el valor inicial. El sistema solo se sigue si se elige expresamente.
    applyAppearance(window.localStorage.getItem(key));
})();
