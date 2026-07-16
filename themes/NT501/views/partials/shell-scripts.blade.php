<script>
(() => { const toggle=document.querySelector('[data-foot-menu-toggle]'),menu=document.querySelector('[data-foot-menu]'); toggle?.addEventListener('click',()=>{const open=!menu?.classList.contains('is-open');menu?.classList.toggle('is-open',open);toggle.setAttribute('aria-expanded',open?'true':'false')}); const slides=[...document.querySelectorAll('[data-nt-hero-slide]')]; if(slides.length>1){let active=0;setInterval(()=>{slides[active].classList.remove('is-active');active=(active+1)%slides.length;slides[active].classList.add('is-active')},6500)}})();
</script>
