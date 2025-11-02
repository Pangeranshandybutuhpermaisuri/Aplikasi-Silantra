(function(){
  function ready(fn){ if(document.readyState!=='loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }
  ready(function(){
    var cfg = window.DockIcons || {};
    var size = typeof cfg.size === 'number' ? cfg.size : 22; // fit existing 44x44 box nicely
    var map = {
      account: cfg.profile || 'assets/profile_icon.png',
      home: cfg.home || 'assets/home_icon.png',
      back: cfg.back || 'assets/back_icon.png'
    };
    document.querySelectorAll('.dock .dock-btn').forEach(function(btn){
      var key = btn.classList.contains('account') ? 'account' : (btn.classList.contains('home') ? 'home' : (btn.classList.contains('back') ? 'back' : null));
      if(!key) return;
      var svg = btn.querySelector('svg'); if(svg) svg.remove();
      var img = btn.querySelector('img[data-dock-icon]');
      if(!img){ img = document.createElement('img'); img.setAttribute('data-dock-icon',''); btn.appendChild(img); }
      img.src = map[key];
      img.alt = key;
      img.style.width = size + 'px';
      img.style.height = size + 'px';
      img.style.objectFit = 'contain';
      img.style.pointerEvents = 'none';
      img.decoding = 'async';
      img.loading = 'lazy';
    });
  });
})();
