    <script>
        (() => {
            const mobileToggle = document.querySelector('[data-xd-mobile-menu-toggle]');
            const mobileMenu = document.querySelector('[data-xd-mobile-menu]');
            const closeMobileMenu = () => {
                if (!mobileToggle || !mobileMenu) return;
                mobileMenu.hidden = true;
                mobileMenu.classList.remove('is-open');
                mobileToggle.setAttribute('aria-expanded', 'false');
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
            document.querySelectorAll('.xd-mobile-link').forEach((link) => {
                link.addEventListener('click', closeMobileMenu);
            });
        })();
    </script>


