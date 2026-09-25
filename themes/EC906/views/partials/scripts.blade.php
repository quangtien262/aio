<script>
document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('[data-ec96-navigation]').forEach(navigation=>{
    const close=button=>{
      button.setAttribute('aria-expanded','false');
      const panel=document.getElementById(button.getAttribute('aria-controls'));
      if(panel){panel.hidden=true;panel.querySelectorAll('.ec96-submenu-toggle').forEach(close);}
    };
    const closeAll=()=>navigation.querySelectorAll('.ec96-submenu-toggle').forEach(close);
    const open=button=>{
      const item=button.closest('.ec96-nav-item');
      [...item.parentElement.children].filter(sibling=>sibling!==item).forEach(sibling=>sibling.querySelectorAll('.ec96-submenu-toggle').forEach(close));
      button.setAttribute('aria-expanded','true');
      const panel=document.getElementById(button.getAttribute('aria-controls'));
      panel.hidden=false;
      if(getComputedStyle(panel).position==='absolute'){
        panel.style.transform='';
        const bounds=panel.getBoundingClientRect();
        const shift=bounds.right>innerWidth-14?innerWidth-14-bounds.right:bounds.left<14?14-bounds.left:0;
        if(shift)panel.style.transform=`translateX(${shift}px)`;
      }
    };
    navigation.querySelectorAll('.ec96-submenu-toggle').forEach(button=>{
      button.addEventListener('click',()=>button.getAttribute('aria-expanded')==='true'?close(button):open(button));
      button.addEventListener('keydown',event=>{
        if(event.key==='ArrowDown'){event.preventDefault();open(button);document.getElementById(button.getAttribute('aria-controls')).querySelector('a')?.focus();}
      });
    });
    navigation.addEventListener('keydown',event=>{
      if(event.key!=='Escape')return;
      const ownButton=event.target.closest('.ec96-submenu-toggle');
      const panel=event.target.closest('.ec96-submenu');
      const button=ownButton?.getAttribute('aria-expanded')==='true'?ownButton:panel?.parentElement.querySelector(':scope > .ec96-nav-row > .ec96-submenu-toggle');
      if(button){event.preventDefault();event.stopPropagation();close(button);button.focus();}
    });
    document.addEventListener('click',event=>{if(!navigation.contains(event.target))closeAll();});
    navigation.addEventListener('focusout',event=>{if(!navigation.contains(event.relatedTarget))closeAll();});
    window.addEventListener('resize',closeAll);
  });
  const menu=document.querySelector('[data-ec96-menu]'),categories=document.querySelector('[data-ec96-categories]');
  if(menu && categories){
    const setOpen=open=>{categories.hidden=!open;menu.setAttribute('aria-expanded',String(open));};
    menu.addEventListener('click',()=>setOpen(categories.hidden));
    document.addEventListener('click',event=>{if(!menu.contains(event.target)&&!categories.contains(event.target))setOpen(false);});
    document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!categories.hidden){setOpen(false);menu.focus();}});
    document.addEventListener('focusin',event=>{if(!menu.contains(event.target)&&!categories.contains(event.target))setOpen(false);});
    menu.addEventListener('keydown',event=>{if(event.key==='ArrowDown'){event.preventDefault();setOpen(true);categories.querySelector('a')?.focus();}});
  }
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
