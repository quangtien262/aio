<script>
(() => {
  const menu = document.querySelector('[data-b702-menu]');
  const nav = document.querySelector('[data-b702-nav]');
  menu?.addEventListener('click', () => nav?.classList.toggle('is-open'));
  document.querySelectorAll('[data-b702-slider]').forEach((slider) => {
    const slides = [...slider.querySelectorAll('.b702-hero-slide')]; if (slides.length < 2) return;
    let index = 0; const show = (next) => { slides[index].classList.remove('is-active'); index = (next + slides.length) % slides.length; slides[index].classList.add('is-active'); };
    let timer = setInterval(() => show(index + 1), Number(slider.dataset.delay || 5600));
    const move = (delta) => { clearInterval(timer); show(index + delta); timer = setInterval(() => show(index + 1), Number(slider.dataset.delay || 5600)); };
    slider.querySelector('[data-b702-prev]')?.addEventListener('click', () => move(-1)); slider.querySelector('[data-b702-next]')?.addEventListener('click', () => move(1));
  });
  const targets = [...document.querySelectorAll('.b702-section .b702-container')];
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
    targets.forEach((item) => item.classList.add('b702-reveal'));
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); } }), {threshold:.12});
    targets.forEach((item) => observer.observe(item));
  }
})();
</script>
