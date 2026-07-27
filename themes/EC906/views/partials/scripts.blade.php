<script>
document.addEventListener('DOMContentLoaded',()=>{
  const menu=document.querySelector('[data-ec96-menu]'),nav=document.querySelector('[data-ec96-nav]');
  menu?.addEventListener('click',()=>nav?.classList.toggle('is-open'));
  const root=document.querySelector('[data-ec96-slider]');
  if(root){
    const slides=[...root.querySelectorAll('[data-ec96-slide]')],dots=[...root.querySelectorAll('[data-ec96-dot]')];
    let active=0,timer;
    const show=index=>{active=(index+slides.length)%slides.length;slides.forEach((el,i)=>el.classList.toggle('is-active',i===active));dots.forEach((el,i)=>el.classList.toggle('is-active',i===active))};
    dots.forEach((dot,index)=>dot.addEventListener('click',()=>{show(index);restart()}));
    const restart=()=>{clearInterval(timer);if(slides.length>1)timer=setInterval(()=>show(active+1),Number(root.dataset.autoplay)||5500)};
    restart();
  }
  const countdown=document.querySelector('[data-ec96-countdown]');
  if(countdown){
    let left=(Number(countdown.dataset.hours)||4)*3600+33*60+9;
    setInterval(()=>{left=Math.max(0,left-1);const values=[Math.floor(left/3600),Math.floor(left%3600/60),left%60];[...countdown.children].forEach((box,i)=>{box.firstChild.nodeValue=String(values[i]).padStart(2,'0')})},1000);
  }
});
</script>
