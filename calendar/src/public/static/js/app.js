// Interruptor de tema claro/oscuro. Cualquier elemento [data-theme-toggle] lo activa.
document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-theme-toggle]');
    if (!button) { return; }
    var root = document.documentElement;
    var next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    try { localStorage.setItem('nv_theme', next); } catch (e) {}
});
