<script>
(() => {
  const nav = document.querySelector('[data-s601-nav]');
  document.querySelector('[data-s601-menu-toggle]')?.addEventListener('click', () => nav?.classList.toggle('is-open'));
  document.querySelectorAll('[data-s601-slider]').forEach((slider) => {
    const slides = [...slider.querySelectorAll('[data-s601-slide]')]; if (slides.length < 2) return;
    let index = 0; const show = (next) => { slides[index].classList.remove('is-active'); index = (next + slides.length) % slides.length; slides[index].classList.add('is-active'); };
    slider.querySelector('[data-s601-prev]')?.addEventListener('click', () => show(index - 1)); slider.querySelector('[data-s601-next]')?.addEventListener('click', () => show(index + 1));
    window.setInterval(() => show(index + 1), Math.max(2500, Number(slider.dataset.autoplay || 6000)));
  });
  document.querySelectorAll('[data-s601-countdown]').forEach((node) => { const end = new Date(node.dataset.s601Countdown || Date.now() + 604800000); const tick = () => { const distance = Math.max(0, end.getTime() - Date.now()); const days = Math.floor(distance / 86400000); const hours = Math.floor(distance / 3600000) % 24; const minutes = Math.floor(distance / 60000) % 60; const seconds = Math.floor(distance / 1000) % 60; node.textContent = `${days} ngày · ${String(hours).padStart(2,'0')} : ${String(minutes).padStart(2,'0')} : ${String(seconds).padStart(2,'0')}`; }; tick(); window.setInterval(tick, 1000); });
})();
</script>
