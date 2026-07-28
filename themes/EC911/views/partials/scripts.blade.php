<script>
document.addEventListener('DOMContentLoaded',()=>{const menu=document.querySelector('[data-ec11-menu]'),nav=document.querySelector('[data-ec11-nav]');menu?.addEventListener('click',()=>nav?.classList.toggle('is-open'));const slides=[...document.querySelectorAll('[data-ec11-slide]')];if(slides.length>1){let i=0;setInterval(()=>{slides[i].classList.remove('is-active');i=(i+1)%slides.length;slides[i].classList.add('is-active')},5200)}})
</script>
