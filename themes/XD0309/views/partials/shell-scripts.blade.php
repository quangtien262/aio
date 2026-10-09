    <script>
        (() => {
            const mobileToggle = document.querySelector('[data-xd5-menu]');
            const mobileMenu = document.querySelector('[data-xd5-nav]');
            const closeMobileMenu = () => {
                if (!mobileToggle || !mobileMenu) return;
                mobileMenu.classList.remove('is-open');
                mobileToggle.setAttribute('aria-expanded', 'false');
            };
            mobileToggle?.addEventListener('click', () => {
                const willOpen = !mobileMenu?.classList.contains('is-open');
                if (!mobileMenu) return;
                mobileMenu.classList.toggle('is-open', Boolean(willOpen));
                mobileToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });
            document.addEventListener('click', (event) => {
                if (!mobileMenu?.classList.contains('is-open')) return;
                if (mobileMenu.contains(event.target) || mobileToggle?.contains(event.target)) return;
                closeMobileMenu();
            });
            mobileMenu?.querySelectorAll('a').forEach((link) => {
                link.addEventListener('click', closeMobileMenu);
            });
        })();
    </script>
