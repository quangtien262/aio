    <script>
        (() => {
            const mobileToggle = document.querySelector('[data-xd-mobile-menu-toggle]');
            const mobileMenu = document.querySelector('[data-xd-mobile-menu]');
            const closeMobileMenu = () => {
                if (!mobileToggle || !mobileMenu) return;
                mobileMenu.hidden = true;
                mobileMenu.classList.remove('is-open');
                mobileToggle.setAttribute('aria-expanded', 'false');
                mobileMenu.querySelectorAll('[data-xd-submenu-toggle]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
                mobileMenu.querySelectorAll('[data-xd-submenu]').forEach((submenu) => { submenu.hidden = true; });
            };
            mobileToggle?.addEventListener('click', () => {
                const willOpen = mobileMenu?.hidden;
                if (!mobileMenu) return;
                mobileMenu.hidden = !willOpen;
                mobileMenu.classList.toggle('is-open', Boolean(willOpen));
                mobileToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });
            document.addEventListener('click', (event) => {
                if (!mobileMenu || mobileMenu.hidden) return;
                if (mobileMenu.contains(event.target) || mobileToggle?.contains(event.target)) return;
                closeMobileMenu();
            });
            document.querySelectorAll('.xd2-mobile-link').forEach((link) => {
                link.addEventListener('click', closeMobileMenu);
            });
            document.querySelectorAll('[data-xd-submenu-toggle]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const item = button.closest('[data-xd-mobile-nav-item], [data-xd-desktop-nav-item]');
                    const submenu = item?.querySelector(':scope > [data-xd-submenu]');
                    if (!submenu) return;
                    const willOpen = button.getAttribute('aria-expanded') !== 'true';
                    button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                    if (item.matches('[data-xd-mobile-nav-item]')) submenu.hidden = !willOpen;
                    else item.classList.toggle('is-open', willOpen);
                });
            });
            document.addEventListener('click', (event) => {
                if (event.target.closest('.xd2-nav')) return;
                document.querySelectorAll('[data-xd-desktop-nav-item].is-open').forEach((item) => item.classList.remove('is-open'));
                document.querySelectorAll('.xd2-submenu-toggle[aria-expanded="true"]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
            });
        })();
    </script>
