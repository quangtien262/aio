<script>
(() => {
  const nav = document.querySelector('[data-e800-nav]');
  document.querySelector('[data-e800-menu-toggle]')?.addEventListener('click', () => nav?.classList.toggle('is-open'));
  const search = document.querySelector('[data-e800-search]');
  document.querySelector('[data-e800-search-open]')?.addEventListener('click', () => { search.hidden = !search.hidden; if (!search.hidden) search.querySelector('input')?.focus(); });
  document.querySelectorAll('[data-e800-slider]').forEach((slider) => {
    const slides = [...slider.querySelectorAll('[data-e800-slide]')]; if (slides.length < 2) return;
    let index = 0; const show = (next) => { slides[index].classList.remove('is-active'); index = (next + slides.length) % slides.length; slides[index].classList.add('is-active'); };
    slider.querySelector('[data-e800-prev]')?.addEventListener('click', () => show(index - 1)); slider.querySelector('[data-e800-next]')?.addEventListener('click', () => show(index + 1));
    window.setInterval(() => show(index + 1), Math.max(3500, Number(slider.dataset.autoplay || 6500)));
  });
  document.querySelectorAll('[data-e800-tabs]').forEach((tabs) => tabs.addEventListener('click', (event) => { const button = event.target.closest('button'); if (!button) return; tabs.querySelectorAll('button').forEach((item) => item.classList.toggle('is-active', item === button)); }));
})();
</script>
