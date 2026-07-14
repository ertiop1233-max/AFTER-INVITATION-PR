class l{constructor(){this.items=[],this.currentIndex=0,this.container=null,this.focusedBeforeOpen=null}open(e,i=0){this.focusedBeforeOpen=document.activeElement,this.items=e,this.currentIndex=i,this.render()}close(){this.container&&(this.container.remove(),this.container=null),document.removeEventListener("keydown",this.handleKeyDown),document.body.style.overflow="",this.focusedBeforeOpen&&this.focusedBeforeOpen.focus()}next(){this.currentIndex=(this.currentIndex+1)%this.items.length,this.updateContent()}prev(){this.currentIndex=(this.currentIndex-1+this.items.length)%this.items.length,this.updateContent()}render(){this.close();const e=document.createElement("div");e.className="lightbox",e.setAttribute("role","dialog"),e.setAttribute("aria-modal","true"),e.setAttribute("aria-label","Media viewer");const i=this.items.length>1;e.innerHTML=`
            <button class="lightbox-close" aria-label="Close media viewer" type="button">&times;</button>
            ${i?`
                <button class="lightbox-nav lightbox-prev" aria-label="Previous media" type="button">&#8249;</button>
                <button class="lightbox-nav lightbox-next" aria-label="Next media" type="button">&#8250;</button>
                <div style="position:absolute;top:var(--space-4);left:50%;transform:translateX(-50%);background:var(--color-bg-elevated);padding:var(--space-1) var(--space-3);border-radius:var(--radius-full);font-size:var(--font-size-xs);color:var(--color-text-secondary)" id="lightboxPosition">1 / ${this.items.length}</div>
            `:""}
            <div class="lightbox-content"></div>
        `;const s=e.querySelector(".lightbox-close");s.addEventListener("click",()=>this.close()),e.addEventListener("click",t=>{t.target===e&&this.close()}),i&&(e.querySelector(".lightbox-prev").addEventListener("click",t=>{t.stopPropagation(),this.prev()}),e.querySelector(".lightbox-next").addEventListener("click",t=>{t.stopPropagation(),this.next()})),document.body.appendChild(e),document.body.style.overflow="hidden",this.container=e,this.updateContent(),s.focus(),this.handleKeyDown=t=>{if(t.key==="Escape"&&(t.preventDefault(),this.close()),t.key==="ArrowLeft"&&i&&(t.preventDefault(),this.prev()),t.key==="ArrowRight"&&i&&(t.preventDefault(),this.next()),t.key==="Tab"){const n=this.getFocusableElements();if(n.length===0)return;const r=n[0],o=n[n.length-1];t.shiftKey&&document.activeElement===r?(t.preventDefault(),o.focus()):!t.shiftKey&&document.activeElement===o&&(t.preventDefault(),r.focus())}},document.addEventListener("keydown",this.handleKeyDown)}getFocusableElements(){return this.container?Array.from(this.container.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])')).filter(e=>e.offsetParent!==null):[]}updateContent(){if(!this.container)return;const e=this.items[this.currentIndex],i=this.container.querySelector(".lightbox-content"),s=this.container.querySelector("#lightboxPosition");s&&(s.textContent=`${this.currentIndex+1} / ${this.items.length}`);const t=e.download_url?`<a href="${e.download_url}" class="btn btn-secondary" style="position:absolute;bottom:var(--space-4);left:50%;transform:translateX(-50%)" download>Download Original</a>`:"",n=e.mime_type&&(e.mime_type==="image/heic"||e.mime_type==="image/heif");if(e.type==="video"){i.innerHTML=`
                <video src="${e.url}" controls autoplay playsinline></video>
                <div class="media-fallback" style="display:none">
                    <img src="${e.thumbnail_url||""}" alt="${e.name||""}" style="max-width:90vw;max-height:70vh;border-radius:var(--radius-md)">
                    <p style="color:var(--color-text-secondary);margin:var(--space-3) 0">This video format may not be supported by your browser.</p>
                    ${t}
                </div>
            `;const r=i.querySelector("video"),o=i.querySelector(".media-fallback");r.addEventListener("error",()=>{r.style.display="none",o.style.display="flex",o.style.flexDirection="column",o.style.alignItems="center"})}else n&&e.thumbnail_url?i.innerHTML=`
                <img src="${e.thumbnail_url}" alt="${e.name||""}">
                ${t}
            `:n?i.innerHTML=`
                <div style="text-align:center">
                    <p style="color:var(--color-text-secondary);margin-bottom:var(--space-3)">
                        HEIC images may not display in all browsers.
                    </p>
                    ${t}
                </div>
            `:i.innerHTML=`<img src="${e.url}" alt="${e.name||""}">`}}window.mediaViewer=new l;
