(function(){
  const contentEl = document.getElementById('app-content');
  const titleEl = document.getElementById('page-title');
  const navLinks = Array.from(document.querySelectorAll('[data-route]'));
  const API_BASE = (typeof window !== 'undefined' && window.SILANTRA_API_URL) ? window.SILANTRA_API_URL : '/api/';

  function toApiUrl(path){
    // Normalize joining API_BASE with relative api path
    const cleanBase = API_BASE.replace(/\/?$/, '/');
    const cleanPath = path.replace(/^\/?/, '');
    return cleanBase + cleanPath;
  }

  function purgeShellArtifacts(container){
    try{
      const root = container || contentEl;
      if(!root) return;
      const kill = (el)=>{ try{ el.remove(); }catch{} };
      // Remove known shell remnants
      root.querySelectorAll('.card-head, header, .nav, #page-title').forEach(kill);
      // Remove home icons commonly used
      root.querySelectorAll('img[alt="Home"], img[src*="home_icon" i], img[src*="home" i]').forEach(img=>{
        const parent = img.closest('div, header, section, .card-head') || img.parentElement;
        (parent && parent.childElementCount<=3) ? kill(parent) : kill(img);
      });
      // If there is a lone heading with text 'Login' at the very top, remove it
      const nodes = Array.from(root.children).slice(0,3);
      nodes.forEach(n=>{ if(/\blogin\b/i.test(n.textContent||'')){ kill(n); } });
    }catch{}
  }

  function injectTypography(file){
    try{
      const isLogin = /login\.html$/i.test(file);
      const isReg = /registrasi\.html$/i.test(file);
      if(!(isLogin || isReg)) return;
      const css = `
        .logo-subtitle{font-size:18px !important}
        .subhead{font-size:16px !important}
        .label{font-size:18px !important}
        .helper, .helper a{font-size:16px !important}
      `;
      const s = document.createElement('style');
      s.type = 'text/css';
      s.setAttribute('data-route-style','typo');
      s.appendChild(document.createTextNode(css));
      (document.head||document.documentElement).appendChild(s);
    }catch{}
  }

  const APP_BUILD = 'build-android-20251102-1148';

  const routes = {
    '/menu': { file: 'menu-awal.html', title: 'Menu', fullScreen: true },
    '/login': { file: 'login.html', title: 'Login', fullScreen: true },
    '/register': { file: 'registrasi.html', title: 'Register', fullScreen: true },
    '/registrasi': { file: 'registrasi.html', title: 'Registrasi' },
    '/profile': { file: 'profile.html', title: 'Profil' },

    // Forms & pages
    '/form-badan-usaha': { file: 'form-badan-usaha.html', title: 'Form Badan Usaha' },
    '/form-fkrtl': { file: 'form-fkrtl.html', title: 'Form FKRTL' },
    '/form-fktp': { file: 'form-fktp.html', title: 'Form FKTP' },
    '/form-informasi-pengaduan': { file: 'form-informasi-pengaduan.html', title: 'Informasi & Pengaduan' },
    '/form-pendaftaran-baru': { file: 'form-pendaftaran-baru.html', title: 'Pendaftaran Baru' },
    '/form-peralihan': { file: 'form-peralihan.html', title: 'Peralihan' },
    '/form-perubahan-data': { file: 'form-perubahan-data.html', title: 'Perubahan Data' },
    '/form-pindah-fktp-3bulan': { file: 'form-pindah-fktp-3bulan.html', title: 'Pindah FKTP 3 Bulan' },
    '/form-reaktivasi-peserta': { file: 'form-reaktivasi-peserta.html', title: 'Reaktivasi Peserta' },

    // Bukti & antrian
    '/antrian-badan-usaha': { file: 'antrian-badan-usaha.html', title: 'Antrian Badan Usaha' },
    '/bukti-antrian-badan-usaha': { file: 'bukti-antrian-badan-usaha.html', title: 'Bukti Antrian Badan Usaha' },
    '/bukti-antrian-kantor-cabang': { file: 'bukti-antrian-kantor-cabang.html', title: 'Bukti Antrian Kantor Cabang' },
    '/bukti-antrian-kantor-cabang-a': { file: 'bukti-antrian-kantor-cabang-a.html', title: 'Bukti Antrian Kantor Cabang A' },
    '/bukti-antrian-prioritas': { file: 'bukti-antrian-prioritas.html', title: 'Bukti Antrian Prioritas' },
    '/bukti-antrian-puskesmas': { file: 'bukti-antrian-puskesmas.html', title: 'Bukti Antrian Puskesmas' },
    '/bukti-antrian-rumah-sakit': { file: 'bukti-antrian-rumah-sakit.html', title: 'Bukti Antrian Rumah Sakit' },

    // Lainnya
    '/pilih-kantor-cabang': { file: 'pilih-kantor-cabang.html', title: 'Pilih Kantor Cabang' },
    '/pilih-layanan-kantor-cabang': { file: 'pilih-layanan-kantor-cabang.html', title: 'Pilih Layanan Cabang' },
    '/pilih-sub-pelayanan': { file: 'pilih-sub-pelayanan.html', title: 'Pilih Sub Pelayanan' },
    '/tempat-pengumpulan-berkas': { file: 'tempat-pengumpulan-berkas.html', title: 'Tempat Pengumpulan Berkas' },
    '/ubah-password': { file: 'ubah-password.html', title: 'Ubah Password' },

    // Fallback: generic loader by filename
    // Route format: #/page/<filename.html>
  };

  function setActive(path){
    navLinks.forEach(a => {
      try {
        const aPath = new URL(a.getAttribute('href'), location.href).hash.replace('#','');
        a.classList.toggle('active', aPath === path);
      } catch (_) {}
    });
  }

  async function fetchAndExtract(url){
    const res = await fetch(url, { cache: 'no-cache' });
    if(!res.ok) throw new Error(`Gagal memuat: ${url} (${res.status})`);
    let text = await res.text();
    // Normalize login page quirks before parsing
    try{
      const u = new URL(url, location.href);
      if(/login\.html$/i.test(u.pathname)){
        // Redirect target -> SPA route
        text = text.replace(/window\.location\.href\s*=\s*'menu-awal\.html[^']*'\s*;/gi, "location.hash = '/menu';");
        // Anchor rewrites
        text = text.replace(/id=("|')toReg\1\s+href=("|')registrasi\.html\2/gi, 'id="toReg" href="#/register"');
        text = text.replace(/id=("|')forgot\1\s+href=("|')#\2/gi, 'id="forgot" href="#/ubah-password"');
        // Remove JS handlers that block navigation for forgot/reg (best-effort)
        text = text.replace(/forgot\.addEventListener\([^)]*\);/gi, '');
        text = text.replace(/toReg\.addEventListener\([^)]*\);/gi, '');
      }
    }catch{}
    const bodyMatch = text.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
    const titleMatch = text.match(/<title[^>]*>([\s\S]*?)<\/title>/i);
    const styleMatches = [...text.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/gi)].map(m=>m[1]);
    const linkMatches = [...text.matchAll(/<link[^>]+rel=["']stylesheet["'][^>]*href=["']([^"']+)["'][^>]*>/gi)].map(m=>m[1]);
    return {
      html: bodyMatch ? bodyMatch[1] : text,
      title: titleMatch ? titleMatch[1] : null,
      styles: styleMatches,
      links: linkMatches,
    };
  }

  function clearRouteStyles(){
    document.querySelectorAll('style[data-route-style], link[data-route-style]')
      .forEach(el=>el.remove());
  }

  function applyRouteStyles(styles = [], links = [], baseUrl){
    const head = document.head || document.getElementsByTagName('head')[0];
    // Inject <style>
    styles.forEach(css => {
      if(!css) return;
      const s = document.createElement('style');
      s.setAttribute('data-route-style','inline');
      s.type = 'text/css';
      s.appendChild(document.createTextNode(css));
      head.appendChild(s);
    });
    // Inject <link rel="stylesheet">
    links.forEach(href => {
      if(!href) return;
      const link = document.createElement('link');
      link.setAttribute('data-route-style','link');
      link.rel = 'stylesheet';
      // Resolve relative href against fetched file url
      try{ link.href = new URL(href, baseUrl).toString(); }catch{ link.href = href; }
      head.appendChild(link);
    });
  }

  async function loadFile(file, pageTitle, meta){
    contentEl.innerHTML = `<p class="loading">Memuat halaman...</p>`;
    try {
      const baseUrl = new URL(file, location.href).toString();
      const { html, title, styles, links } = await fetchAndExtract(file);
      clearRouteStyles();
      applyRouteStyles(styles, links, baseUrl);
      contentEl.innerHTML = html;
      purgeShellArtifacts(contentEl);
      injectTypography(file);
      // Remove any obvious title rows that only contain 'Login' text
      try{
        Array.from(contentEl.querySelectorAll('*')).forEach(el=>{
          const txt = (el.textContent||'').trim();
          if(/^login$/i.test(txt) && el.tagName.match(/^H[1-6]|DIV|SPAN$/)){
            el.remove();
          }
        });
      }catch{}
      // Remove any residual header/card fragments inside page content
      try{
        contentEl.querySelectorAll('.card-head, .page-header, .header, .topbar, .title-bar').forEach(el=>el.remove());
        // If a title element shows "Login" at top with an adjacent icon, remove parent container heuristically
        const titleLogin = Array.from(contentEl.querySelectorAll('*')).find(el=>/\bLogin\b/i.test(el.textContent||''));
        if(titleLogin && titleLogin.closest('.card-head')){ titleLogin.closest('.card-head')?.remove(); }
      }catch{}
      titleEl.textContent = pageTitle || title || file;
      rewriteInternalLinks();
      rewriteFormsToApi();
      window.scrollTo({ top: 0, behavior: 'smooth' });
      // Toggle shell layout mode
      try{
        const appShell = document.querySelector('.app');
        if(appShell){ appShell.classList.toggle('full', !!(meta && meta.fullScreen)); }
      }catch{}
      // Debug overlay removed for production stability
    } catch (e) {
      console.error(e);
      contentEl.innerHTML = `<p class="error">Tidak dapat memuat halaman: ${file}</p>`;
      titleEl.textContent = 'Kesalahan';
    }
  }

  function filenameToRoute(file){
    const path = Object.entries(routes).find(([,v]) => v.file === file)?.[0];
    return path || `/page/${file}`;
  }

  function rewriteInternalLinks(){
    const anchors = contentEl.querySelectorAll('a[href]');
    anchors.forEach(a => {
      const href = a.getAttribute('href');
      if(!href) return;
      // Skip external
      if(/^https?:\/\//i.test(href) || href.startsWith('mailto:') || href.startsWith('tel:')) return;

      // If link points to API (e.g., api/*.php), rewrite to absolute API_BASE
      if(/^\s*api\//i.test(href) || /(^|\/)api\/[^?#]+\.php(\?|#|$)/i.test(href)){
        const apiPath = href.replace(/^\/?/,'');
        a.setAttribute('href', toApiUrl(apiPath.replace(/^api\//i, '')));
        a.setAttribute('target', '_self');
        return;
      }

      // If points to an html file in this project, rewrite to hash route
      const m = href.match(/([^#?]+\.html)(?:[?#].*)?$/i);
      if(m){
        const file = m[1];
        a.addEventListener('click', (ev) => {
          ev.preventDefault();
          navigate(filenameToRoute(file));
        });
        a.setAttribute('href', `#${filenameToRoute(file)}`);
        return;
      }

      // If it's a hash or relative path without .html, leave as-is
    });
  }

  function rewriteFormsToApi(){
    const forms = contentEl.querySelectorAll('form');
    forms.forEach(form => {
      const action = (form.getAttribute('action') || '').trim();
      if(!action || /^https?:\/\//i.test(action)) return; // already absolute or no action

      // If action targets api/*.php or *.php in api dir
      if(/^\s*api\//i.test(action) || /(^|\/)api\/[^?#]+\.php(\?|#|$)/i.test(action)){
        // Normalize to absolute API url (strip leading api/ if present)
        const cleaned = action.replace(/^\/?api\//i, '');
        form.setAttribute('action', toApiUrl(cleaned));
      }
    });
  }

  function parseHash(){
    const raw = location.hash.replace(/^#/, '') || '/login';
    const [path, query] = raw.split('?');
    const params = new URLSearchParams(query || '');
    return { path, params };
  }

  async function router(){
    const { path, params } = parseHash();
    setActive(path);
    if(routes[path]){
      const { file, title, ...meta } = routes[path];
      await loadFile(file, title, meta);
      return;
    }
    // Generic route: /page/<filename>
    const m = path.match(/^\/page\/(.+\.html)$/i);
    if(m){
      const file = decodeURIComponent(m[1]);
      await loadFile(file, params.get('title') || file);
      return;
    }
    // Not found -> go menu
    navigate('/menu', true);
  }

  function navigate(path, replace){
    const url = `#${path}`;
    if(replace) history.replaceState(null, '', url); else location.hash = path;
  }

  window.addEventListener('hashchange', router);
  document.addEventListener('DOMContentLoaded', router);
})();
