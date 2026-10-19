(function () {
  'use strict';
  var cfg = window.AACAI_INBOX;
  var app = document.getElementById('aacai-inbox-app');
  if (!cfg || !app) return;

  var PH = cfg.phrases || {};
  function tp(s) { return PH[s] || s; }
  var I = Object.assign({}, cfg.i18n || {}, cfg.i18nAliases || {});
  function t(key, fallback) {
    return I[key] || fallback || key;
  }

  var FOLDER_LABELS = {
    all: t('all', 'All'),
    chats: t('chats', 'Chats'),
    waiting: t('waiting', 'Waiting'),
    agent: t('agent', 'Agent'),
    needs_reply: t('needs_reply', 'Needs reply'),
    needs_followup: t('needs_followup', 'Needs follow-up'),
    resolved: t('trash', 'Trash'),
    mine: t('mine', 'Mine')
  };

  var state = {
    scope: 'all',
    filter: 'all',
    search: '',
    session: '',
    lastId: 0,
    mode: 'bot',
    metaOpen: false,
    pollTimer: null,
    inboxTimer: null,
    prevCounts: null,
    baseTitle: document.title,
    titleFlashTimer: null,
    notifAsked: false,
    loadSeq: 0
  };
  var NOTIF = (cfg.notif || {});

  var listEl = document.getElementById('aacai-inbox-list');
  var stream = document.getElementById('aacai-inbox-stream');
  var empty = document.getElementById('aacai-inbox-empty');
  var toolbar = document.getElementById('aacai-inbox-toolbar');
  var composer = document.getElementById('aacai-inbox-composer');
  var replyBox = document.getElementById('aacai-inbox-reply');
  var titleEl = document.getElementById('aacai-inbox-title');
  var modeBadge = document.getElementById('aacai-inbox-mode-badge');
  var needBadge = document.getElementById('aacai-inbox-need-badge');
  var followupBadge = document.getElementById('aacai-inbox-followup-badge');
  var claimBtn = document.getElementById('aacai-inbox-claim');
  var releaseBtn = document.getElementById('aacai-inbox-release');
  var metaPane = document.getElementById('aacai-inbox-right');
  var metaDl = document.getElementById('aacai-inbox-meta');
  var ordersEl = document.getElementById('aacai-inbox-orders');
  var folderTitle = document.getElementById('aacai-list-folder-title');
  var threadAvatar = document.getElementById('aacai-thread-avatar');
  var detailsProfile = document.getElementById('aacai-details-profile');

  function headers() {
    return { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json', 'Cache-Control': 'no-cache' };
  }
  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }
  function roleLabel(role) {
    return tp(({ user: 'Visitor', assistant: 'Assistant', agent: 'Team agent', system: 'Notice' })[role] || role);
  }
  function avatarClass(short) {
    var n = 0;
    var s = String(short || '');
    for (var i = 0; i < s.length; i++) n = (n + s.charCodeAt(i)) % 6;
    return 'c' + n;
  }
  function statusClass(it) {
    if (it.needs_followup || it.status === 'pending') return 'is-followup';
    if (it.needs_reply) return 'is-needs';
    if (it.mode === 'waiting') return 'is-waiting';
    if (it.mode === 'agent') return 'is-agent';
    if (it.status === 'resolved') return 'is-resolved';
    return 'is-bot';
  }
  function effectiveFilter() {
    if (state.scope === 'mine') return 'mine';
    return state.filter || 'all';
  }
  function updateFolderTitle() {
    if (!folderTitle) return;
    folderTitle.textContent = FOLDER_LABELS[effectiveFilter()] || 'All';
  }

  function setCounts(counts) {
    if (!counts) return;
    Object.keys(counts).forEach(function (k) {
      var els = app.querySelectorAll('[data-count-for="' + k + '"]');
      els.forEach(function (el) { el.textContent = String(counts[k] || 0); });
    });
    function showDot(sel, n) {
      var d = app.querySelector(sel);
      if (!d) return;
      if (n > 0) d.removeAttribute('hidden');
      else d.setAttribute('hidden', 'hidden');
    }
    showDot('[data-folder-dot="waiting"]', counts.waiting || 0);
    showDot('[data-folder-dot="needs_reply"]', counts.needs_reply || 0);
    showDot('[data-folder-dot="needs_followup"]', counts.needs_followup || 0);
    showDot('[data-folder-dot="chats"]', counts.chats || 0);
    showDot('[data-scope-dot="all"]', (counts.waiting || 0) + (counts.needs_reply || 0) + (counts.needs_followup || 0));
    showDot('[data-scope-dot="mine"]', counts.mine || 0);

    var alertN = (counts.waiting || 0) + (counts.needs_reply || 0);
    maybeAlertFromCounts(counts, alertN);
    if (window.AACAI_NOTIF_API && typeof window.AACAI_NOTIF_API.setBadge === 'function') {
      window.AACAI_NOTIF_API.setBadge(alertN);
    }
  }

  var audioCtx = null;
  function getAudioCtx() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return null;
      if (!audioCtx || audioCtx.state === 'closed') {
        audioCtx = new Ctx();
      }
      if (audioCtx.state === 'suspended') {
        try { audioCtx.resume(); } catch (e) {}
      }
      return audioCtx;
    } catch (e) {
      return null;
    }
  }

  function unlockAudio() {
    var ctx = getAudioCtx();
    if (!ctx) return;
    try {
      if (ctx.state === 'suspended') { ctx.resume(); }
    } catch (e) {}
  }

  function playBeep() {
    if (NOTIF.sound === false) return;
    try {
      var ctx = getAudioCtx();
      if (!ctx) return;
      var o = ctx.createOscillator();
      var g = ctx.createGain();
      o.type = 'sine';
      o.frequency.value = 880;
      g.gain.value = 0.04;
      o.connect(g);
      g.connect(ctx.destination);
      o.start();
      setTimeout(function () {
        try { o.stop(); } catch (e) {}
      }, 160);
    } catch (e) {}
  }

  function runTestAlert() {
    unlockAudio();
    playBeep();
    flashTitle(1);
    fireDesktop('Customer Assistant', 'Test alert / 測試提醒', '');
    setTimeout(function () {
      // stop title flash after a short demo unless real alerts remain
      var n = ((state.prevCounts && state.prevCounts.waiting) || 0)
        + ((state.prevCounts && state.prevCounts.needs_reply) || 0);
      flashTitle(n);
    }, 4000);
  }

  function flashTitle(n) {
    if (state.titleFlashTimer) { clearInterval(state.titleFlashTimer); state.titleFlashTimer = null; }
    if (!n) {
      document.title = state.baseTitle;
      return;
    }
    var on = false;
    var label = '(' + n + ') 新對話 / New';
    state.titleFlashTimer = setInterval(function () {
      on = !on;
      document.title = on ? label : state.baseTitle;
    }, 1200);
  }

  function fireDesktop(title, body, session) {
    if (NOTIF.desktop === false) return;
    if (!('Notification' in window)) return;
    if (Notification.permission !== 'granted') return;
    try {
      var n = new Notification(title || 'Customer Assistant', {
        body: body || '',
        tag: 'aacai-' + (session || 'inbox'),
        renotify: true
      });
      n.onclick = function () {
        try { window.focus(); } catch (e) {}
        if (session) { selectSession(session); }
        n.close();
      };
    } catch (e) {}
  }

  function ensureDesktopPermission() {
    if (NOTIF.desktop === false) return;
    if (!('Notification' in window)) return;
    if (Notification.permission !== 'default') return;
    if (state.notifAsked) return;
    state.notifAsked = true;
    var bar = document.getElementById('aacai-notif-perm');
    if (bar) bar.hidden = false;
  }

  function maybeAlertFromCounts(counts, alertN) {
    var prev = state.prevCounts;
    state.prevCounts = {
      waiting: counts.waiting || 0,
      needs_reply: counts.needs_reply || 0,
      chats: counts.chats || 0,
      mine: counts.mine || 0,
      all: counts.all || 0
    };
    if (!prev) {
      flashTitle(alertN);
      return;
    }
    var hit = false;
    var why = '';
    if ((counts.waiting || 0) > (prev.waiting || 0)) {
      hit = true; why = 'New Waiting chat';
    }
    if ((counts.needs_reply || 0) > (prev.needs_reply || 0)) {
      hit = true; why = why || 'Needs reply';
    }
    if (NOTIF.botChats && (counts.chats || 0) > (prev.chats || 0)) {
      hit = true; why = why || 'New chat';
    }
    flashTitle(alertN);
    if (!hit) return;
    playBeep();
    fireDesktop('Customer Assistant', why || 'Inbox update', '');
  }

  function alertVisitorMessage(session) {
    playBeep();
    var n = ((state.prevCounts && state.prevCounts.waiting) || 0)
      + ((state.prevCounts && state.prevCounts.needs_reply) || 0) + 1;
    flashTitle(n);
    fireDesktop('Visitor message', 'New message in live chat', session);
  }

  function renderList(items) {
    listEl.textContent = '';
    if (!items || !items.length) {
      var p = document.createElement('div');
      p.className = 'aacai-inbox-list-empty';
      p.textContent = t('no_convos', t('noConvos'));
      listEl.appendChild(p);
      return;
    }
    items.forEach(function (it) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.setAttribute('role', 'option');
      btn.dataset.session = it.session;
      var initial = (it.short || 'V').charAt(0).toUpperCase();
      var timeShort = (it.last_message_at || '').replace(/^\d{4}-\d{2}-\d{2}\s*/, '').slice(0, 5);
      var openPill = (it.mode === 'waiting' || it.needs_reply || it.status === 'open')
        ? '<span class="aacai-status-open-pill">' + esc(t('open')) + '</span>' : '';
      var followPill = (it.needs_followup || it.status === 'pending')
        ? '<span class="aacai-status-follow-pill">' + esc(t('needs_followup', 'Needs follow-up')) + '</span>' : '';
      btn.className = 'aacai-inbox-item'
        + (it.session === state.session ? ' is-active' : '')
        + (it.needs_reply ? ' is-needs' : '')
        + ((it.needs_followup || it.status === 'pending') ? ' is-followup' : '');
      btn.innerHTML =
        '<span class="aacai-avatar ' + avatarClass(it.short) + '" aria-hidden="true">' + esc(initial) + '</span>'
        + '<span class="aacai-inbox-item-body">'
        + '<span class="aacai-inbox-item-name">Visitor ' + esc(it.short) + openPill + followPill + '</span>'
        + '<span class="aacai-inbox-item-preview">' + esc(it.preview) + (timeShort ? ' · ' + esc(timeShort) : '') + '</span>'
        + '</span>'
        + '<span class="aacai-status-sq ' + statusClass(it) + '" title="' + esc((it.needs_followup || it.status === 'pending') ? t('needs_followup', 'Needs follow-up') : (it.mode_label || it.mode)) + '"></span>';
      btn.addEventListener('click', function () { selectSession(it.session); });
      listEl.appendChild(btn);
    });
  }

  function appendBubble(m) {
    if (!m || !m.id) return;
    if (stream.querySelector('[data-id="' + m.id + '"]')) return;
    var div = document.createElement('div');
    div.className = 'aacai-bub ' + (m.role || 'assistant');
    div.setAttribute('data-id', String(m.id));
    var meta = document.createElement('div');
    meta.className = 'aacai-bub-meta';
    var strong = document.createElement('strong');
    strong.textContent = roleLabel(m.role);
    var time = document.createElement('time');
    time.textContent = (m.created_at || '') + ' UTC';
    meta.appendChild(strong);
    meta.appendChild(time);
    div.appendChild(meta);

    var original = (m.original_message || m.original || '').trim();
    var visitorText = (m.message || '').trim();
    var zh = (m.admin_display || m.zh_tw || m.admin_zh_tw || '').trim();
    var enLine = (m.en_original || '').trim();
    var bilingual = !!m.bilingual;

    // Agent ZH typed: primary = original ZH, secondary = EN sent to visitor.
    if (m.role === 'agent' && original) {
      zh = original;
      enLine = visitorText;
      bilingual = !!(visitorText && visitorText !== original);
    } else if (bilingual && zh) {
      if (!enLine) enLine = visitorText;
    } else if (zh && enLine) {
      bilingual = true;
    } else {
      zh = zh || visitorText;
      bilingual = false;
    }

    var body = document.createElement('div');
    body.className = 'aacai-bub-body';
    body.textContent = zh || visitorText;
    div.appendChild(body);

    if (bilingual && enLine && enLine !== zh) {
      var sent = document.createElement('div');
      sent.className = 'aacai-bub-sent aacai-bub-en';
      if (m.role === 'agent' && original) {
        sent.textContent = tp('Sent to visitor') + ': ' + enLine;
      } else {
        sent.textContent = 'EN · ' + enLine;
      }
      div.appendChild(sent);
    }

    stream.appendChild(div);
    stream.scrollTop = stream.scrollHeight;
    state.lastId = Math.max(state.lastId, parseInt(m.id, 10) || 0);
  }

  function fillDetails(thread) {
    if (detailsProfile) {
      var initial = (thread.short || 'V').charAt(0).toUpperCase();
      detailsProfile.innerHTML =
        '<span class="aacai-avatar aacai-avatar-lg ' + avatarClass(thread.short) + '">' + esc(initial) + '</span>'
        + '<div><div class="aacai-details-profile-name">Visitor ' + esc(thread.short || '') + '</div>'
        + '<div class="aacai-details-profile-sub">' + esc(thread.mode_label || thread.mode) + '</div></div>';
    }
    var rows = [
      [tp('Session'), thread.session],
      [tp('Mode'), thread.mode_label],
      [tp('Messages'), String(thread.message_count)],
      [tp('First activity (UTC)'), thread.first_message_at],
      [tp('Last activity (UTC)'), thread.last_message_at],
      [tp('Agent'), thread.agent_name || (thread.agent_user_id ? '#' + thread.agent_user_id : '—')],
      [tp('First reply source'), thread.first_source || '—']
    ];
    metaDl.textContent = '';
    rows.forEach(function (pair) {
      var dt = document.createElement('dt');
      dt.textContent = pair[0];
      var dd = document.createElement('dd');
      dd.textContent = pair[1];
      metaDl.appendChild(dt);
      metaDl.appendChild(dd);
    });
    ordersEl.textContent = '';
    if (!thread.woo_active) {
      ordersEl.innerHTML = '<p class="description">' + esc(tp('WooCommerce inactive — no order attribution.')) + '</p>';
    } else if (thread.orders && thread.orders.length) {
      thread.orders.forEach(function (o) {
        var a = document.createElement('a');
        a.href = o.url;
        a.target = '_blank';
        a.rel = 'noopener';
        a.textContent = '#' + o.number + ' · ' + o.status + (o.total ? ' · ' + o.total : '');
        ordersEl.appendChild(a);
      });
    } else {
      ordersEl.innerHTML = '<p class="description">' + esc(tp('No attributed orders for this session.')) + '</p>';
    }
    var tagsInput = document.getElementById('aacai-inbox-tags');
    if (tagsInput) tagsInput.value = thread.tags || '';
  }

  function renderThread(thread) {
    state.session = thread.session;
    state.mode = thread.mode;
    state.lastId = thread.last_id || 0;
    empty.hidden = true;
    toolbar.hidden = false;
    stream.hidden = false;
    composer.hidden = false;
    app.classList.add('is-thread-open');
    titleEl.textContent = t('visitor') + ' ' + (thread.short || thread.session.slice(0, 12));
    if (threadAvatar) {
      threadAvatar.textContent = (thread.short || 'V').charAt(0).toUpperCase();
      threadAvatar.className = 'aacai-avatar aacai-avatar-lg ' + avatarClass(thread.short);
    }
    modeBadge.textContent = thread.mode_label || thread.mode;
    modeBadge.className = 'aacai-inbox-badge is-' + thread.mode;
    needBadge.hidden = !thread.needs_reply;
    if (followupBadge) followupBadge.hidden = !(thread.needs_followup || thread.status === 'pending');
    claimBtn.hidden = thread.mode === 'agent';
    releaseBtn.hidden = thread.mode !== 'agent';

    stream.textContent = '';
    (thread.messages || []).forEach(appendBubble);
    fillDetails(thread);

    // Auto-open details when a chat is selected
    setMetaOpen(true);
    highlightActive();
    startPoll();
  }

  function highlightActive() {
    listEl.querySelectorAll('.aacai-inbox-item').forEach(function (el) {
      el.classList.toggle('is-active', el.dataset.session === state.session);
    });
  }

  function applyInboxData(data) {
    if (!data) return;
    setCounts(data.counts || {});
    renderList(data.items || []);
  }

  function loadInbox() {
    updateFolderTitle();
    var url = cfg.inboxEndpoint
      + (cfg.inboxEndpoint.indexOf('?') >= 0 ? '&' : '?')
      + 'filter=' + encodeURIComponent(effectiveFilter())
      + '&s=' + encodeURIComponent(state.search)
      + '&_=' + Date.now();
    return fetch(url, { credentials: 'same-origin', headers: headers(), cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) {
          return r.text().then(function (t) {
            var err = new Error('inbox_http_' + r.status);
            err.status = r.status;
            err.body = t;
            throw err;
          });
        }
        return r.json();
      })
      .then(function (data) {
        applyInboxData(data);
      })
      .catch(function (err) {
        // Keep last good list; show a one-line hint if still empty.
        if (listEl && !listEl.querySelector('.aacai-inbox-item')) {
          var p = document.createElement('div');
          p.className = 'aacai-inbox-list-empty';
          p.textContent = t('error', 'Could not load inbox') + (err && err.status ? (' (' + err.status + ')') : '')
            + '. Try Legacy list or refresh.';
          listEl.textContent = '';
          listEl.appendChild(p);
        }
      });
  }

  function selectSession(session) {
    if (!session) return;
    state.session = session;
    var seq = ++state.loadSeq;
    stream.textContent = '';
    empty.hidden = false;
    empty.innerHTML = '<p>' + esc(t('loading')) + '</p>';
    toolbar.hidden = true;
    composer.hidden = true;
    stream.hidden = true;
    // Auto-open details shell so the right column is not blank while loading.
    setMetaOpen(true);
    if (detailsProfile) {
      detailsProfile.innerHTML = '<p class="description">' + esc(t('loading')) + '</p>';
    }
    var url = cfg.threadEndpoint
      + (cfg.threadEndpoint.indexOf('?') >= 0 ? '&' : '?')
      + 'session=' + encodeURIComponent(session)
      + '&_=' + Date.now();

    // F1 (1.9.0): bounded timeout per attempt + automatic retries with backoff,
    // and a manual Retry button on final failure — the pane must never sit on
    // "Loading…" forever.
    function attempt(n) {
      var ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
      var timer = setTimeout(function () {
        if (ctrl) try { ctrl.abort(); } catch (e) {}
      }, 20000);
      var opts = { credentials: 'same-origin', headers: headers(), cache: 'no-store' };
      if (ctrl) opts.signal = ctrl.signal;
      return fetch(url, opts)
        .then(function (r) {
          if (!r.ok) {
            return r.text().then(function (body) {
              var err = new Error('thread_http_' + r.status);
              err.status = r.status;
              err.body = body;
              throw err;
            });
          }
          return r.json();
        })
        .then(function (thread) {
          clearTimeout(timer);
          if (seq !== state.loadSeq) return; // stale response
          if (!thread || !thread.session) throw new Error('thread_empty');
          empty.innerHTML = '';
          renderThread(thread);
          try {
            var u = new URL(window.location.href);
            u.searchParams.set('session', session);
            u.searchParams.delete('legacy');
            history.replaceState(null, '', u.toString());
          } catch (e) {}
        })
        .catch(function (err) {
          clearTimeout(timer);
          if (seq !== state.loadSeq) return;
          // Retry transient failures (network / timeout / 5xx); 4xx is final.
          var retryable = !err || !err.status || err.status >= 500 || err.name === 'AbortError';
          if (retryable && n < 3) {
            empty.innerHTML = '<p>' + esc(t('loading')) + ' (' + n + '/3)</p>';
            setTimeout(function () {
              if (seq === state.loadSeq) attempt(n + 1);
            }, n * 900);
            return;
          }
          empty.hidden = false;
          var msg = t('error');
          if (err && err.name === 'AbortError') {
            msg = t('error') + ' (timeout)';
          } else if (err && err.status) {
            msg = t('error') + ' (' + err.status + ')';
          }
          empty.innerHTML = '';
          var pEl = document.createElement('p');
          pEl.textContent = msg;
          empty.appendChild(pEl);
          var retryBtn = document.createElement('button');
          retryBtn.type = 'button';
          retryBtn.className = 'button';
          retryBtn.textContent = 'Retry / 重試';
          retryBtn.addEventListener('click', function () { selectSession(session); });
          empty.appendChild(retryBtn);
          if (detailsProfile) {
            detailsProfile.innerHTML = '<p class="description">' + esc(msg) + '</p>';
          }
        });
    }
    attempt(1);
  }

  function startPoll() {
    stopPoll();
    function tick() {
      if (!state.session) return;
      var url = cfg.pollEndpoint
        + (cfg.pollEndpoint.indexOf('?') >= 0 ? '&' : '?')
        + 'session=' + encodeURIComponent(state.session)
        + '&after_id=' + encodeURIComponent(String(state.lastId || 0))
        + '&_=' + Date.now();
      fetch(url, { credentials: 'same-origin', headers: headers(), cache: 'no-store' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data) return;
          if (data.mode) {
            state.mode = data.mode;
            modeBadge.textContent = ({ bot: t('mode_bot'), waiting: t('mode_waiting'), agent: t('mode_agent') })[data.mode] || data.mode;
            modeBadge.className = 'aacai-inbox-badge is-' + data.mode;
            claimBtn.hidden = data.mode === 'agent';
            releaseBtn.hidden = data.mode !== 'agent';
          }
          var hadUser = false;
          (data.messages || []).forEach(function (msg) {
            appendBubble(msg);
            if (msg && msg.role === 'user') hadUser = true;
          });
          var last = data.messages && data.messages.length ? data.messages[data.messages.length - 1] : null;
          if (last && last.role === 'user' && (state.mode === 'waiting' || state.mode === 'agent')) {
            needBadge.hidden = false;
          } else if (last && last.role !== 'user') {
            needBadge.hidden = true;
          }
          if (hadUser && (state.mode === 'waiting' || state.mode === 'agent' || data.mode === 'waiting' || data.mode === 'agent')) {
            alertVisitorMessage(state.session);
          }
        })
        .catch(function () {})
        .finally(function () {
          var ms = cfg.pollMs || 2500;
          if (typeof document !== 'undefined' && document.visibilityState === 'visible' && document.hasFocus && document.hasFocus()) {
            ms = Math.min(ms, 2000);
          } else {
            ms = Math.max(ms, 5000);
          }
          state.pollTimer = setTimeout(tick, ms);
        });
    }
    state.pollTimer = setTimeout(tick, cfg.pollMs || 2500);
  }
  function stopPoll() {
    if (state.pollTimer) { clearTimeout(state.pollTimer); state.pollTimer = null; }
  }

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: headers(),
      body: JSON.stringify(body)
    }).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw j; return j; }); });
  }

  claimBtn.addEventListener('click', function () {
    if (!state.session) return;
    claimBtn.disabled = true;
    post(cfg.claimEndpoint, { session: state.session })
      .then(function () { return selectSession(state.session); })
      .then(function () { loadInbox(); })
      .catch(function () { alert(t('error')); })
      .finally(function () { claimBtn.disabled = false; });
  });
  releaseBtn.addEventListener('click', function () {
    if (!state.session) return;
    releaseBtn.disabled = true;
    post(cfg.releaseEndpoint, { session: state.session })
      .then(function () { return selectSession(state.session); })
      .then(function () { loadInbox(); })
      .catch(function () { alert(t('error')); })
      .finally(function () { releaseBtn.disabled = false; });
  });
  var resolveBtn = document.getElementById('aacai-inbox-resolve');
  if (resolveBtn) {
    resolveBtn.addEventListener('click', function () {
      if (!state.session || !cfg.resolveEndpoint) return;
      resolveBtn.disabled = true;
      post(cfg.resolveEndpoint, { session: state.session })
        .then(function () { return selectSession(state.session); })
        .then(function () { loadInbox(); })
        .catch(function () { alert(t('error')); })
        .finally(function () { resolveBtn.disabled = false; });
    });
  }
  var tagsSave = document.getElementById('aacai-inbox-tags-save');
  if (tagsSave) {
    tagsSave.addEventListener('click', function () {
      if (!state.session || !cfg.tagsEndpoint) return;
      var tagsInput = document.getElementById('aacai-inbox-tags');
      tagsSave.disabled = true;
      post(cfg.tagsEndpoint, { session: state.session, tags: (tagsInput && tagsInput.value) || '' })
        .catch(function () { alert(t('error')); })
        .finally(function () { tagsSave.disabled = false; });
    });
  }
  composer.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!state.session) return;
    var text = (replyBox.value || '').trim();
    if (!text) return;
    var btn = document.getElementById('aacai-inbox-send');
    btn.disabled = true;
    post(cfg.replyEndpoint, { session: state.session, message: text })
      .then(function (res) {
        replyBox.value = '';
        if (res && res.id) {
          var visitorMsg = (res.message != null && res.message !== '') ? res.message : text;
          var original = (res.original != null && res.original !== '') ? res.original : '';
          // If translated, original is Chinese typed; message is English for visitor.
          // If not translated, show typed text as message.
          appendBubble({
            id: res.id,
            role: 'agent',
            message: visitorMsg,
            original_message: original || (visitorMsg !== text ? text : ''),
            created_at: new Date().toISOString().replace('T', ' ').slice(0, 19)
          });
        }
        if (res && res.translate_notice) {
          alert(res.translate_notice);
        }
        state.mode = 'agent';
        modeBadge.textContent = t('mode_agent');
        modeBadge.className = 'aacai-inbox-badge is-agent';
        claimBtn.hidden = true;
        releaseBtn.hidden = false;
        needBadge.hidden = true;
        loadInbox();
        startPoll();
      })
      .catch(function () { alert(t('error')); })
      .finally(function () { btn.disabled = false; replyBox.focus(); });
  });

  app.querySelectorAll('.aacai-folder[data-filter]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      app.querySelectorAll('.aacai-folder[data-filter]').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      state.filter = btn.dataset.filter || 'all';
      // Switching folder while on Mine keeps mine scope (Mine overrides folder in API)
      if (state.scope === 'mine') {
        // stay on mine; folder highlight is visual only until All is chosen
      }
      loadInbox();
    });
  });

  app.querySelectorAll('.aacai-scope-tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      app.querySelectorAll('.aacai-scope-tab').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      state.scope = btn.dataset.scope || 'all';
      loadInbox();
    });
  });

  var searchInput = document.getElementById('aacai-inbox-search');
  var searchTimer = null;
  searchInput.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
      state.search = searchInput.value || '';
      loadInbox();
    }, 280);
  });

  function setMetaOpen(open) {
    state.metaOpen = !!open;
    if (open && state.session) {
      metaPane.hidden = false;
      app.classList.add('is-details-open');
    } else {
      metaPane.hidden = true;
      app.classList.remove('is-details-open');
    }
  }
  document.getElementById('aacai-inbox-toggle-meta').addEventListener('click', function () {
    setMetaOpen(metaPane.hidden);
  });
  document.getElementById('aacai-inbox-close-meta').addEventListener('click', function () {
    setMetaOpen(false);
  });

  var composeBtn = document.getElementById('aacai-compose-hint');
  if (composeBtn) {
    composeBtn.addEventListener('click', function () {
      alert(t('compose') + '\nVisitors start chats from the storefront widget. / 訪客請從前台聊天視窗發起對話。');
    });
  }

  // Desktop notification permission chip + Test alert (V1.8.7)
  (function () {
    var bar = document.getElementById('aacai-notif-perm');
    var btn = document.getElementById('aacai-notif-enable');
    var dismiss = document.getElementById('aacai-notif-dismiss');
    var testBtn = document.getElementById('aacai-notif-test');
    var testBar = document.getElementById('aacai-notif-test-bar');
    if (testBar) {
      var showTest = (NOTIF.sound !== false) || (NOTIF.desktop !== false);
      testBar.hidden = !showTest;
    }
    if (btn) {
      btn.addEventListener('click', function () {
        if (!('Notification' in window)) return;
        Notification.requestPermission().then(function (p) {
          if (bar) bar.hidden = true;
          if (p === 'granted') {
            unlockAudio();
            playBeep();
            fireDesktop('Customer Assistant', 'Desktop alerts enabled / 桌面通知已開啟', '');
          }
        });
      });
    }
    if (dismiss && bar) {
      dismiss.addEventListener('click', function () { bar.hidden = true; });
    }
    if (testBtn) {
      testBtn.addEventListener('click', function () {
        runTestAlert();
      });
    }
    ensureDesktopPermission();
  })();

  // Init: details closed until a chat is selected
  setMetaOpen(false);
  // P0-5: paint server-rendered bootstrap immediately (works even if REST is blocked).
  if (cfg.bootstrap && (cfg.bootstrap.items || cfg.bootstrap.counts)) {
    applyInboxData(cfg.bootstrap);
  }
  loadInbox().then(function () {
    var initial = (cfg.initialSession || app.dataset.preselect || '').toLowerCase();
    if (/^[a-f0-9]{64}$/.test(initial)) {
      selectSession(initial);
    }
  });
  state.inboxTimer = setInterval(function () {
    var ms = cfg.inboxRefreshMs || 10000;
    loadInbox();
  }, cfg.inboxRefreshMs || 10000);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') { loadInbox(); }
  });
})();
