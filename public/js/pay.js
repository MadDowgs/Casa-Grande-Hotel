(function () {
  var f = document.getElementById('payForm'); if (!f) return;
  var card = document.getElementById('fcard'), wal = document.getElementById('fwallet'), wl = document.getElementById('wlabel'),
      num = f.card_number, exp = f.expiry, cvv = f.cvv, mob = f.mobile_number, brand = document.getElementById('brand');

  function sync() {
    var m = f.querySelector('input[name=method]:checked').value, c = m === 'card';
    card.hidden = !c; wal.hidden = c;
    card.querySelectorAll('input,select').forEach(function (x) { x.disabled = !c; });
    wal.querySelectorAll('input').forEach(function (x) { x.disabled = c; });
    wl.textContent = (m === 'maya' ? 'Maya' : 'GCash') + ' mobile number';
  }
  f.querySelectorAll('input[name=method]').forEach(function (r) { r.addEventListener('change', sync); });
  sync();

  function brandOf(d) {
    if (/^4/.test(d)) return 'Visa';
    if (/^(5[1-5]|222[1-9]|22[3-9]|2[3-6]|27[01]|2720)/.test(d)) return 'Mastercard';
    if (/^35(2[89]|[3-8])/.test(d)) return 'JCB';
    if (/^3[47]/.test(d)) return 'Amex';
    return '';
  }
  num.addEventListener('input', function () {
    var d = num.value.replace(/\D/g, '').slice(0, 16), b = brandOf(d), g = b === 'Amex' ? [4, 6, 5] : [4, 4, 4, 4], o = [], i = 0;
    if (b === 'Amex') d = d.slice(0, 15);
    g.forEach(function (n) { if (d.length > i) { o.push(d.substr(i, n)); i += n; } });
    num.value = o.join(' '); brand.textContent = b;
    cvv.maxLength = b === 'Amex' ? 4 : 3; cvv.placeholder = b === 'Amex' ? '1234' : '123';
  });
  exp.addEventListener('input', function () {
    var d = exp.value.replace(/\D/g, '').slice(0, 4); exp.value = d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d;
  });
  cvv.addEventListener('input', function () { cvv.value = cvv.value.replace(/\D/g, ''); });
  mob.addEventListener('input', function () { mob.value = mob.value.replace(/[^\d+]/g, ''); });

  // Friendly checks before sending (the Payment API validates again on the server)
  f.addEventListener('submit', function (ev) {
    var m = f.querySelector('input[name=method]:checked').value, msg = '';
    if (m === 'card') {
      var d = num.value.replace(/\D/g, '');
      if (!f.bank.value) msg = 'Select the bank that issued your card.';
      else if (d.length < 15) msg = 'Enter the full card number.';
      else if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(exp.value)) msg = 'Enter the expiry as MM/YY.';
      else if (cvv.value.length < cvv.maxLength) msg = 'Enter the security code (CVV).';
      else if (f.card_holder.value.trim().length < 2) msg = 'Enter the name on the card.';
    } else if (!/^(\+63|63|0)9\d{9}$/.test(mob.value)) msg = 'Enter a valid mobile number, e.g. 09171234567.';
    if (msg) {
      ev.preventDefault(); ev.stopImmediatePropagation();
      var b = f.querySelector('.btn'); b.disabled = false; b.textContent = f.dataset.label;
      var o = document.getElementById('perr');
      if (!o) { o = document.createElement('div'); o.id = 'perr'; o.className = 'msg bad'; o.setAttribute('role', 'alert'); f.parentNode.insertBefore(o, f); }
      o.textContent = msg; o.scrollIntoView({ block: 'center' });
    }
  }, true);
})();
