(function () {
  'use strict';
  var cfg = window.AACAI_NOTIF;
  if (!cfg) return;
  var NOTIF = cfg.notif || {};
  var lastBadge = typeof cfg.initialBadge === 'number' ? cfg.initialBadge : 0;
  var prev = null;
  var timer = null;

  function setBadge(n) {
    lastBadge = Math.max(0, parseInt(n, 10) || 0);
    if (NOTIF.menuBadge === false) return;
    var menu = document.getElementById('toplevel_page_aacai');
    if (!menu) return;
    var name = menu.querySelector('.wp-menu-name');
    if (!name) return;
    var existing = name.querySelector('.aacai-menu-count');
    if (lastBadge <= 0) {
      if (existing) existing.remove();
      return;
    }
    if (!existing) {
      existing = document.createElement('span');
      existing.className = 'aacai-menu-count update-plugins count-' + lastBadge;
      existing.innerHTML = '<span class="plugin-count"></span>';
      name.appendChild(existing);
    }
    existing.className = 'aacai-menu-count update-plugins count-' + lastBadge;
    var pc = existing.querySelector('.plugin-count');
    if (pc) pc.textContent = String(lastBadge > 99 ? '99+' : lastBadge);
  }

  function playBeep() {
    if (NOTIF.sound === false) return;
    if (cfg.onInbox) return; // Inbox page handles its own sound
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      var ctx = new Ctx();
      var o = ctx.createOscillator();
      var g = ctx.createGain();
      o.type = 'sine';
      o.frequency.value = 880;
      g.gain.value = 0.035;
      o.connect(g);
      g.connect(ctx.destination);
      o.start();
      setTimeout(function () { try { o.stop(); ctx.close(); } catch (e) {} }, 140);
    } catch (e) {}
  }

  function fireDesktop(title, body) {
    if (NOTIF.desktop === false) return;
    if (cfg.onInbox) return;
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    try {
      var n = new Notification(title || 'Customer Assistant', {
        body: body || '',
        tag: 'aacai-global'
      });
      n.onclick = function () {
        try { window.focus(); } catch (e) {}
        if (cfg.inboxUrl) { window.location.href = cfg.inboxUrl; }
        n.close();
      };
    } catch (e) {}
  }

  function handleCounts(counts) {
    if (!counts) return;
    var alertN = (counts.waiting || 0) + (counts.needs_reply || 0);
    setBadge(alertN);
    if (!prev) {
      prev = counts;
      return;
    }
    var hit = false;
    var why = '';
    if ((counts.waiting || 0) > (prev.waiting || 0)) { hit = true; why = 'New Waiting chat'; }
    if ((counts.needs_reply || 0) > (prev.needs_reply || 0)) { hit = true; why = why || 'Needs reply'; }
    if (NOTIF.botChats && (counts.chats || 0) > (prev.chats || 0)) { hit = true; why = why || 'New chat'; }
    prev = counts;
    if (!hit || cfg.onInbox) return;
    playBeep();
    fireDesktop('Customer Assistant', why);
  }

  function poll() {
    var url = cfg.inboxEndpoint
      + (cfg.inboxEndpoint.indexOf('?') >= 0 ? '&' : '?')
      + 'filter=all&limit=1&_=' + Date.now();
    fetch(url, {
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': cfg.nonce, 'Cache-Control': 'no-cache' },
      cache: 'no-store'
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (data && data.counts) handleCounts(data.counts);
      })
      .catch(function () {})
      .finally(function () {
        timer = setTimeout(poll, cfg.pollMs || 20000);
      });
  }

  window.AACAI_NOTIF_API = { setBadge: setBadge };
  setBadge(lastBadge);
  timer = setTimeout(poll, 3000);
})();
