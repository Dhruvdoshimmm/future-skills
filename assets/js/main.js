(function () {
  // Mobile nav
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('main-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // FAQ accordion (one open at a time)
  var heads = document.querySelectorAll('.acc-head');
  heads.forEach(function (h) {
    h.addEventListener('click', function () {
      var item = h.parentElement;
      var body = document.getElementById(h.getAttribute('aria-controls'));
      var isOpen = h.getAttribute('aria-expanded') === 'true';
      heads.forEach(function (o) {
        o.setAttribute('aria-expanded', 'false');
        o.parentElement.classList.remove('open');
        document.getElementById(o.getAttribute('aria-controls')).hidden = true;
      });
      if (!isOpen) {
        h.setAttribute('aria-expanded', 'true');
        item.classList.add('open');
        body.hidden = false;
      }
    });
  });

  // Live chat (visitor + admin): render, poll, ajax send
  var box = document.getElementById('chatBox');
  if (box) {
    var me = box.dataset.me, meLabel = box.dataset.meLabel, themLabel = box.dataset.themLabel;
    var last = parseInt(box.dataset.last || '0', 10);
    var form = document.getElementById('chatForm');
    box.scrollTop = box.scrollHeight;

    function render(msgs, newLast) {
      var nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
      box.textContent = '';
      msgs.forEach(function (m) {
        var d = document.createElement('div');
        d.className = 'chat-msg ' + (m.from === me ? 'me' : 'them');
        var sm = document.createElement('small');
        sm.textContent = (m.from === me ? meLabel : themLabel) + ' \u00b7 ' + m.time;
        var sp = document.createElement('span');
        sp.textContent = m.text;
        d.appendChild(sm); d.appendChild(sp); box.appendChild(d);
      });
      last = newLast;
      if (nearBottom) box.scrollTop = box.scrollHeight;
    }

    var polling = false;
    setInterval(function () {
      if (document.hidden || polling || !box.dataset.poll) return;
      polling = true;
      fetch(box.dataset.poll, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok && d.last !== last) render(d.messages, d.last); })
        .catch(function () {})
        .then(function () { polling = false; });
    }, parseInt(box.dataset.interval || '5000', 10));

    if (form && window.fetch && window.FormData) {
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var input = form.querySelector('input[type=text]');
        var btn = form.querySelector('button');
        if (!input.value.trim()) return;
        btn.disabled = true;
        fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d.ok) { render(d.messages, d.last); box.scrollTop = box.scrollHeight; input.value = ''; }
            else alert(d.error || 'Could not send message.');
          })
          .catch(function () { form.submit(); })
          .then(function () { btn.disabled = false; input.focus(); });
      });
    }
  }
})();
