<script>
document.addEventListener('DOMContentLoaded',()=>{
 const menu=document.querySelector('[data-ec98-menu]'),nav=document.querySelector('[data-ec98-nav]');menu?.addEventListener('click',()=>nav?.classList.toggle('is-open'));
 const slider=document.querySelector('[data-ec98-slider]');if(slider){const slides=[...slider.querySelectorAll('[data-ec98-slide]')];if(slides.length>1){let index=0;setInterval(()=>{slides[index].classList.remove('is-active');index=(index+1)%slides.length;slides[index].classList.add('is-active')},Number(slider.dataset.autoplay)||5500)}}
});
</script>
