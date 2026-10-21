const root = document.documentElement;
try { root.dataset.theme = localStorage.getItem('vp-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); } catch { root.dataset.theme = 'light'; }
document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
    root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('vp-theme', root.dataset.theme); } catch {}
});
const menu = document.querySelector('[data-menu]');
menu?.addEventListener('click', () => { const open = document.querySelector('#sidebar').classList.toggle('open'); menu.setAttribute('aria-expanded', String(open)); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { document.querySelector('#sidebar')?.classList.remove('open'); menu?.setAttribute('aria-expanded', 'false'); } });
document.addEventListener('click', e => { if (document.querySelector('#sidebar.open') && !e.target.closest('#sidebar,[data-menu]')) { document.querySelector('#sidebar').classList.remove('open'); menu?.setAttribute('aria-expanded', 'false'); } });
document.querySelectorAll('[data-auto-submit]').forEach(el => el.addEventListener('change', () => el.form.requestSubmit()));
document.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
    if (form.dataset.confirm && !confirm(form.dataset.confirm)) { event.preventDefault(); return; }
    const button = event.submitter;
    if (button) { button.classList.add('busy'); button.setAttribute('aria-busy', 'true'); setTimeout(() => { button.disabled = true; }, 0); }
}));
document.querySelectorAll('[data-tenant-select]').forEach(select => {
    const update = () => select.form.querySelectorAll('[data-relation-select] option').forEach(option => {
        const hidden = Boolean(option.dataset.tenant && option.dataset.tenant !== select.value);
        option.hidden = hidden; option.disabled = hidden;
        if (hidden && option.selected) option.parentElement.value = '';
    });
    select.addEventListener('change', update); update();
});
document.querySelectorAll('[data-timer-start]').forEach(el => {
    const update = () => { const secs = Math.max(0, Math.floor((Date.now() - Date.parse(el.dataset.timerStart)) / 1000)); el.textContent = [Math.floor(secs/3600), Math.floor(secs/60)%60, secs%60].map(n => String(n).padStart(2,'0')).join(':'); };
    update(); setInterval(update, 1000);
});
