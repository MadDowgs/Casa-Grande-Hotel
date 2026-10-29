(function () {
  // sticky navigation: transparent over the hero, glass once you scroll
  var nav = document.getElementById('nav');
  function onScroll() { nav.classList.toggle('solid', window.scrollY > 30 || !document.querySelector('.hero')); }
  onScroll(); window.addEventListener('scroll', onScroll, { passive: true });
  var burger = document.getElementById('burger');
  if (burger) burger.addEventListener('click', function () { nav.classList.toggle('open'); });
  document.querySelectorAll('.links a').forEach(function (a) { a.addEventListener('click', function () { nav.classList.remove('open'); }); });

  // reveal on scroll
  var els = document.querySelectorAll('.rv');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { threshold: 0.12 });
    els.forEach(function (e) { io.observe(e); });
  } else { els.forEach(function (e) { e.classList.add('in'); }); }

  // flash messages
  document.querySelectorAll('.flash').forEach(function (f) {
    var x = f.querySelector('button'); if (x) x.addEventListener('click', function () { f.remove(); });
    if (f.classList.contains('ok')) setTimeout(function () { f.remove(); }, 5000);
  });

  // date pairs: keep check-out after check-in, and update the live price summary
  function fmt(n) { return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function nice(v) { var d = new Date(v); return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' }); }
  document.querySelectorAll('form[data-dates]').forEach(function (f) {
    var a = f.querySelector('[name=check_in]'), b = f.querySelector('[name=check_out]'), price = +f.dataset.price || 0;
    function nextDay(v) { var d = new Date(v); d.setUTCDate(d.getUTCDate() + 1); return d.toISOString().slice(0, 10); }
    function calc() {
      var n = Math.round((new Date(b.value) - new Date(a.value)) / 864e5);
      var set = function (id, t) { var el = document.getElementById(id); if (el) el.textContent = t; };
      set('sIn', nice(a.value)); set('sOut', nice(b.value));
      set('sNights', n > 0 ? n : '—'); set('sTotal', n > 0 && price ? fmt(n * price) : '—');
    }
    a.addEventListener('change', function () { if (!b.value || b.value <= a.value) b.value = nextDay(a.value); b.min = nextDay(a.value); calc(); });
    b.addEventListener('change', calc);
    b.min = a.value ? nextDay(a.value) : ''; calc();
  });

  // disable a form's button after submit so nothing is sent twice
  document.querySelectorAll('form').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (ev.defaultPrevented) return;
      var b = f.querySelector('button.btn'); if (b) { b.disabled = true; b.textContent = 'Please wait…'; }
    });
  });
  window.addEventListener('pageshow', function (ev) { if (ev.persisted) location.reload(); });
})();
