<script>
document.addEventListener('DOMContentLoaded',()=>{
 const menu=document.querySelector('[data-s604-menu]'),nav=document.querySelector('[data-s604-nav]');
 menu?.addEventListener('click',()=>nav?.classList.toggle('is-open'));
 const searchButton=document.querySelector('[data-s604-search]'),searchPanel=document.querySelector('[data-s604-search-panel]');
 searchButton?.addEventListener('click',()=>{searchPanel?.classList.toggle('is-open');searchPanel?.querySelector('input')?.focus()});
 document.querySelectorAll('[data-s604-slider]').forEach(slider=>{
  const slides=[...slider.querySelectorAll('[data-s604-slide]')];if(slides.length<2)return;let index=0;
  const show=next=>{slides[index].classList.remove('is-active');index=(next+slides.length)%slides.length;slides[index].classList.add('is-active')};
  slider.querySelector('[data-s604-prev]')?.addEventListener('click',()=>show(index-1));
  slider.querySelector('[data-s604-next]')?.addEventListener('click',()=>show(index+1));
  setInterval(()=>show(index+1),Math.max(3500,Number(slider.dataset.autoplay)||5600));
 });
});
</script>
