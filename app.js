const image=document.getElementById('jersey');
const labels={magenta:{name:'Magenta & or',number:'01',alt:'Maillot collector magenta et or, vues de face et de dos : gargouille, monuments parisiens et numéro 26.'},noir:{name:'Noir & or',number:'02',alt:'Maillot collector noir et or, vues de face et de dos : gargouille, monuments parisiens et numéro 26.'}};
document.querySelectorAll('[data-color]').forEach(button=>button.addEventListener('click',()=>{const color=button.dataset.color;const info=labels[color];image.src=`assets/maillot-${color}.png`;image.alt=info.alt;document.getElementById('color-name').textContent=`— ${info.name}`;document.getElementById('view-label').textContent=`${info.number} / ${info.name.toUpperCase()}`;document.querySelectorAll('[data-color]').forEach(other=>{const selected=other===button;other.classList.toggle('active',selected);other.setAttribute('aria-pressed',String(selected));});}));
const teamFrame=document.querySelector('.team-frame');
if(teamFrame&&!window.matchMedia('(prefers-reduced-motion: reduce)').matches&&'IntersectionObserver' in window){
 const teamImage=teamFrame.querySelector('img');
 const observer=new IntersectionObserver(entries=>{if(entries.some(entry=>entry.isIntersecting)){const reveal=()=>teamFrame.classList.add('is-revealed');if(teamImage.complete&&teamImage.naturalWidth){reveal();}else{teamImage.addEventListener('load',reveal,{once:true});}observer.disconnect();}},{threshold:.15});
 observer.observe(teamFrame);
}
