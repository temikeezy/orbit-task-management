(function(){
  function ready(fn){ if(document.readyState!='loading'){fn()} else {document.addEventListener('DOMContentLoaded', fn)} }
  ready(function(){
    document.body.addEventListener('click', function(e){
      var btn = e.target.closest('.otm-toggle');
      if(!btn) return;
      var sel = btn.getAttribute('data-target');
      if(!sel) return;
      var el = document.querySelector(sel);
      if(!el) return;
      var show = el.style.display === 'none' || getComputedStyle(el).display === 'none';
      el.style.display = show ? 'block' : 'none';
      // Optional: toggle active state
      btn.classList.toggle('is-active', show);
      e.preventDefault();
    }, false);
  });
})();