<script>
(() => {
    document.querySelectorAll('.n88-article-body table').forEach(table => {
        if (table.parentElement.classList.contains('n88-table-scroll')) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'n88-table-scroll';
        wrapper.tabIndex = 0;
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', table.caption?.textContent?.trim() || (document.documentElement.lang.startsWith('vi') ? 'Bảng nội dung, có thể cuộn ngang' : 'Content table, horizontally scrollable'));
        table.before(wrapper);
        wrapper.append(table);
    });
    const nav = document.querySelector('[data-n88-nav]');
    const menuButton = document.querySelector('[data-n88-menu]');
    const setSubmenu = (button, open) => {
        const submenu = document.getElementById(button.getAttribute('aria-controls'));
        if (!submenu) return;
        button.setAttribute('aria-expanded', String(open));
        submenu.hidden = !open;
        submenu.classList.remove('opens-left');
        if (open && window.matchMedia('(min-width:901px)').matches && submenu.getBoundingClientRect().right > window.innerWidth - 12) submenu.classList.add('opens-left');
        if (!open) submenu.querySelectorAll('[data-n88-submenu-toggle]').forEach(child => setSubmenu(child, false));
    };
    const closeMenus = () => {
        nav?.querySelectorAll('[data-n88-submenu-toggle]').forEach(button => setSubmenu(button, false));
        nav?.classList.remove('is-open');
        menuButton?.setAttribute('aria-expanded', 'false');
    };
    menuButton?.addEventListener('click', () => {
        const open = !nav?.classList.contains('is-open');
        if (!open) closeMenus();
        else { nav?.classList.add('is-open'); menuButton.setAttribute('aria-expanded', 'true'); }
    });
    nav?.querySelectorAll('[data-n88-submenu-toggle]').forEach(button => {
        const item = button.closest('.n88-nav-item');
        button.addEventListener('click', () => setSubmenu(button, button.getAttribute('aria-expanded') !== 'true'));
        item.addEventListener('pointerenter', () => {
            if (window.matchMedia('(min-width:901px) and (hover:hover)').matches) setSubmenu(button, true);
        });
        item.addEventListener('pointerleave', () => {
            if (window.matchMedia('(min-width:901px) and (hover:hover)').matches && !item.contains(document.activeElement)) setSubmenu(button, false);
        });
        item.addEventListener('focusout', event => {
            if (!item.contains(event.relatedTarget)) setSubmenu(button, false);
        });
    });
    document.addEventListener('click', event => {
        if (!nav?.contains(event.target) && !menuButton?.contains(event.target)) closeMenus();
    });
    nav?.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        event.preventDefault();
        const submenu = event.target.closest('.n88-submenu');
        const button = submenu ? document.querySelector(`[aria-controls="${submenu.id}"]`) : event.target.closest('.n88-nav-item')?.querySelector('[data-n88-submenu-toggle]');
        if (button) { button.focus(); setSubmenu(button, false); }
        else { closeMenus(); menuButton?.focus(); }
    });
    window.matchMedia('(max-width:900px)').addEventListener('change', closeMenus);
    const search = document.querySelector('[data-n88-search-panel]');
    document.querySelector('[data-n88-search]')?.addEventListener('click', () => { search?.classList.toggle('is-open'); search?.querySelector('input')?.focus(); });
    const observer = new IntersectionObserver(entries => entries.forEach(entry => entry.isIntersecting && entry.target.classList.add('is-visible')), {threshold: .08});
    document.querySelectorAll('[data-n88-reveal]').forEach(element => observer.observe(element));
})();
</script>
