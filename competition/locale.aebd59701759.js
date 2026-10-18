(()=>{'use strict';
const languages=['fr','en','it','es','de'],names=['Français','English','Italiano','Español','Deutsch'];
let saved='fr';try{saved=localStorage.getItem('tip-language')||'fr';}catch{}
let lang=new URL(location.href).searchParams.get('lang')||saved;if(!languages.includes(lang))lang='fr';
const rows=window.TIP_TRANSLATIONS||[],exact=new Map(rows.map(r=>[r[0].trim(),r]));
const patterns=rows.filter(r=>r[0].includes('{')).map(r=>({r,re:new RegExp('^'+r[0].split(/(\{\d+\})/).map(s=>/^\{\d+\}$/.test(s)?'(.+?)':s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')).join('')+'$')}));
function translate(s,depth=0){if(lang==='fr'||!s)return s;const lead=s.match(/^\s*/)[0],tail=s.match(/\s*$/)[0],key=s.trim();let row=exact.get(key),result=row?.[languages.indexOf(lang)];if(!result&&depth<2)for(const p of patterns){const m=key.match(p.re);if(m){result=p.r[languages.indexOf(lang)].replace(/\{(\d+)\}/g,(_,n)=>translate(m[+n+1],depth+1));break;}}return result?lead+result+tail:s;}
const originals=new WeakMap(),attributes=new WeakMap();
const skip='script,style,[translate="no"],.language-links';
function apply(){observer.disconnect();document.documentElement.lang=lang;
 const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);let node;
 while(node=walker.nextNode()){if(node.parentElement?.closest(skip))continue;const previous=originals.get(node);const source=previous&&node.nodeValue===previous.output?previous.source:node.nodeValue;const output=translate(source);originals.set(node,{source,output});if(node.nodeValue!==output)node.nodeValue=output;}
 for(const el of document.querySelectorAll('[placeholder],[aria-label],[title]')){if(el.closest(skip))continue;let state=attributes.get(el)||{};for(const attr of ['placeholder','aria-label','title']){if(!el.hasAttribute(attr))continue;const old=state[attr],now=el.getAttribute(attr),source=old&&now===old.output?old.source:now,output=translate(source);state[attr]={source,output};if(now!==output)el.setAttribute(attr,output);}attributes.set(el,state);}
 document.title=translate('Compétition · Touch In Paris 2027');document.querySelectorAll('.language-links').forEach(n=>n.setAttribute('aria-label',translate('Langue')));document.querySelectorAll('.edition sup').forEach(n=>n.textContent=({fr:'e',en:'th',it:'ª',es:'.ª',de:'.'})[lang]);
 document.querySelectorAll('.language-links a').forEach(a=>{if(a.lang===lang)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current');});
 document.querySelectorAll('a[href^="/fr/"]').forEach(a=>{a.dataset.home='true';});document.querySelectorAll('[data-home]').forEach(a=>a.href='/'+lang+'/');
 observer.observe(document.body,{childList:true,subtree:true,characterData:true,attributes:true,attributeFilter:['placeholder','aria-label','title']});
}
const observer=new MutationObserver(apply);
document.querySelectorAll('.language-links a').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();lang=a.lang;try{localStorage.setItem('tip-language',lang);}catch{}const url=new URL(location.href);url.searchParams.set('lang',lang);history.replaceState(null,'',url);apply();}));
try{localStorage.setItem('tip-language',lang);}catch{}apply();
})();
