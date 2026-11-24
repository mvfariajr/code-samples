/* Plans page form behavior (template-plans.php). */
(function () {
  var form = document.getElementById('plan-form');
  if (!form) return;

  var start = document.getElementById('f-start');
  var pay = document.getElementById('f-pay');
  var invoiceWrap = document.getElementById('f-invoice-wrap');
  var care = document.getElementById('f-care');
  var sitesWrap = document.getElementById('f-sites-wrap');
  var sites = document.getElementById('f-sites');
  var rate = document.getElementById('care-rate');
  var planErr = document.getElementById('f-err');
  var radios = form.querySelectorAll('input[name="plan"]');
  var submit = form.querySelector('button[type="submit"]');
  var paid = ['oncall', 'ongoing', 'partner', 'custom'];

  // Start date: earliest is tomorrow in the visitor's local time.
  var d = new Date();
  d.setDate(d.getDate() + 1);
  start.min = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

  function plan() {
    var r = form.querySelector('input[name="plan"]:checked');
    return r ? r.value : '';
  }

  function hasChoice() {
    return care.checked || (plan() !== '' && plan() !== 'none');
  }

  function sync() {
    sitesWrap.hidden = !care.checked;
    sites.required = care.checked;
    rate.textContent = paid.indexOf(plan()) > -1 ? '($75 per site with your retainer)' : '($85 per site, or $75 with a retainer plan)';
    if (hasChoice()) planErr.hidden = true;
  }

  pay.addEventListener('change', function () {
    invoiceWrap.hidden = pay.value !== 'invoice';
  });

  care.addEventListener('change', sync);
  radios.forEach(function (r) { r.addEventListener('change', sync); });

  // Plan buttons select the matching option; the #start link scrolls to the form.
  document.querySelectorAll('.fd-plans [data-plan]').forEach(function (a) {
    a.addEventListener('click', function () {
      var v = a.getAttribute('data-plan');
      if (v === 'care') {
        care.checked = true;
        if (!plan()) form.querySelector('input[name="plan"][value="none"]').checked = true;
      } else {
        form.querySelector('input[name="plan"][value="' + v + '"]').checked = true;
      }
      sync();
    });
  });

  sync();

  form.addEventListener('submit', function (e) {
    if (!hasChoice()) {
      e.preventDefault();
      planErr.hidden = false;
      (form.querySelector('input[name="plan"]:checked') || radios[0]).focus();
      return;
    }
    if (!form.checkValidity()) {
      e.preventDefault();
      form.reportValidity();
      return;
    }
    submit.disabled = true;
  });

  // Re-enable the button if the visitor comes back to this page from the history cache.
  window.addEventListener('pageshow', function () { submit.disabled = false; });

  // After a successful send the form is hidden; this brings back an empty one in place.
  var another = document.getElementById('fd-plans-another');
  if (another) {
    another.parentNode.hidden = false;
    another.addEventListener('click', function () {
      document.getElementById('fd-plans-notice').hidden = true;
      another.parentNode.hidden = true;
      form.hidden = false;
      radios[0].focus();
    });
  }
})();
