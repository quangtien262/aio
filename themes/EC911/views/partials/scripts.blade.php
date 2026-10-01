<script>
document.addEventListener('DOMContentLoaded',()=>{const menu=document.querySelector('[data-ec11-menu]'),nav=document.querySelector('[data-ec11-nav]');menu?.addEventListener('click',()=>{const open=nav?.classList.toggle('is-open');menu.setAttribute('aria-expanded',String(Boolean(open)));});
const categories=document.querySelector('[data-ec11-category-menu]');
document.addEventListener('click',event=>{if(categories?.open&&!categories.contains(event.target))categories.open=false;});
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&categories?.open){categories.open=false;categories.querySelector('summary').focus();}});
const slides=[...document.querySelectorAll('[data-ec11-slide]')];if(slides.length>1){let i=0;setInterval(()=>{slides[i].classList.remove('is-active');i=(i+1)%slides.length;slides[i].classList.add('is-active')},5200)}})
</script>
