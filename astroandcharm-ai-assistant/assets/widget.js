(function () {
  'use strict';
  const root = document.getElementById('aacai-root');
  const cfg = window.AACAI_DATA;
  if (!root || !cfg) return;
  const I18N = cfg.i18n || {};
  const PH = cfg.phrases || {};
  function tp(s) { return PH[s] || s; }
  function ti18n(key, fallback) { return I18N[key] || fallback || key; }
  const $ = (selector) => root.querySelector(selector);
  const launcher = $('.aacai-launcher');
  const teaser = $('.aacai-teaser');
  const teaserClose = $('.aacai-teaser-close');
  const panel = $('.aacai-panel');
  const closeBtn = $('.aacai-close');
  const homeBtn = $('.aacai-home-btn');
  const clearBtn = $('.aacai-clear');
  const welcome = $('.aacai-welcome-view');
  const transcript = $('.aacai-transcript');
  const featured = $('.aacai-featured');
  const featuredTabs = $('.aacai-featured-tabs');
  const featuredRail = $('.aacai-featured-rail');
  let featuredCollections = [];
  const form = $('.aacai-form');
  const textarea = $('.aacai-form textarea');
  const sendBtn = $('.aacai-send');
  const STORE = 'aacai-chat-v1'; // preserve V1.2 chat on upgrade
  const SESSION = 'aacai-chat-session-v1';
  const TEASER_SEEN = 'aacai-teaser-seen-v13';
  let messages = [];
  let loading = false;
  let screen = 'home';
  let featuredLoaded = false;
  let sessionId = getSession();
  let liveMode = 'bot';
  let ratingPromptShown = false;
  let lastPollId = 0;
  let pollTimer = null;
  let pollIntervalMs = 2500;
  let idlePollStreak = 0;
  let seenRemoteIds = {};
  const iconPath = {
    chat:'<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/><path d="M8.5 11h7M8.5 14h4.5"/>',
    gem:'<path d="M6 3h12l4 6-10 12L2 9l4-6Z"/><path d="M2 9h20M6 3l6 18L18 3"/>',
    cards:'<rect x="6" y="3" width="14" height="18" rx="2"/><path d="m2 7 1 12a2 2 0 0 0 2 2h1M13 7l.9 2.1L16 10l-2.1.9L13 13l-.9-2.1L10 10l2.1-.9Z"/>',
    orbit:'<circle cx="12" cy="12" r="5"/><ellipse cx="12" cy="12" rx="11" ry="4.5" transform="rotate(-28 12 12)"/>',
    yin:'<circle cx="12" cy="12" r="9"/><path d="M12 3c5 0 5 9 0 9s-5 9 0 9"/><circle cx="12" cy="7.5" r="1"/><circle cx="12" cy="16.5" r="1"/>',
    bag:'<path d="M4 8h16l-1.2 13H5.2L4 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/>',
    arrow:'<path d="M5 12h14M13 6l6 6-6 6"/>',
    send:'<path d="m21 3-8.2 18-2.4-7.4L3 11.2 21 3Z"/><path d="M10.4 13.6 21 3"/>',
    home:'<path d="m3 10 9-7 9 7v10H3V10Z"/><path d="M9 20v-7h6v7"/>',
    refresh:'<path d="M20 7v5h-5"/><path d="M4 17v-5h5"/><path d="M5.7 9A7 7 0 0 1 18 7l2 5M4 12l2 5a7 7 0 0 0 12.3-2"/>',
    close:'<path d="M18 6 6 18M6 6l12 12"/>',
    shield:'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
    chevron:'<path d="m9 18 6-6-6-6"/>'
  };
  function icon(name, cls) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox','0 0 24 24');
    svg.setAttribute('fill','none');
    svg.setAttribute('stroke','currentColor');
    svg.setAttribute('stroke-width','1.6');
    svg.setAttribute('stroke-linecap','round');
    svg.setAttribute('stroke-linejoin','round');
    svg.setAttribute('aria-hidden','true');
    if (cls) svg.setAttribute('class',cls);
    svg.innerHTML = iconPath[name] || iconPath.arrow; // only static developer-authored SVG
    return svg;
  }
  function el(tag,cls,text) {
    const e=document.createElement(tag);
    if (cls) e.className=cls;
    if (typeof text==='string') e.textContent=text;
    return e;
  }
  function validUrl(url) { return typeof url === 'string' && /^https?:\/\//i.test(url); }
  function getSession() {
    try {
      let value=sessionStorage.getItem(SESSION);
      if (!value) {
        value=(window.crypto && window.crypto.randomUUID) ? crypto.randomUUID() : 's_'+Date.now()+'_'+Math.random().toString(36).slice(2);
        sessionStorage.setItem(SESSION,value);
      }
      return value;
    } catch(e) { return 's_'+Date.now()+'_'+Math.random().toString(36).slice(2); }
  }
  function setSidCookie(id) {
    try {
      const name = cfg.cookieName || 'aacai_sid';
      const days = Math.max(1, parseInt(cfg.cookieDays, 10) || 7);
      const maxAge = days * 24 * 60 * 60;
      const secure = location.protocol === 'https:' ? '; Secure' : '';
      document.cookie = name + '=' + encodeURIComponent(id) + '; path=/; max-age=' + maxAge + '; SameSite=Lax' + secure;
    } catch (e) {}
  }
  function trackOpen() {
    setSidCookie(sessionId);
    if (!cfg.openEndpoint) return;
    try {
      fetch(cfg.openEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type':'application/json','X-WP-Nonce':cfg.nonce},
        body: JSON.stringify({session: sessionId}),
        keepalive: true
      }).catch(function(){});
    } catch (e) {}
  }
  function recover() {
    try {
      const arr=JSON.parse(sessionStorage.getItem(STORE)||'[]');
      if (Array.isArray(arr)) messages=arr.slice(-20).filter(m=>m && typeof m.content==='string' && ['user','assistant','agent','system'].includes(m.role));
      const meta=JSON.parse(sessionStorage.getItem(STORE+'-live')||'{}');
      if (meta && typeof meta === 'object') {
        if (typeof meta.lastPollId === 'number') lastPollId = meta.lastPollId;
        if (meta.liveMode) liveMode = meta.liveMode;
        if (meta.seenRemoteIds && typeof meta.seenRemoteIds === 'object') seenRemoteIds = meta.seenRemoteIds;
      }
      messages.forEach((m)=>{ if (m.remoteId) { seenRemoteIds[m.remoteId]=1; lastPollId=Math.max(lastPollId, m.remoteId); } });
    } catch(e) {messages=[];}
  }
  function persist() {
    try{
      sessionStorage.setItem(STORE,JSON.stringify(messages.slice(-20)));
      sessionStorage.setItem(STORE+'-live', JSON.stringify({lastPollId, liveMode, seenRemoteIds}));
    }catch(e){}
  }
  function hideTeaser() { if (teaser) teaser.hidden=true; }
  function dismissTeaser() { hideTeaser();try{sessionStorage.setItem(TEASER_SEEN,'1');}catch(e){} }
  function scheduleTeaser() {
    if (!teaser) return;
    try { if (sessionStorage.getItem(TEASER_SEEN)==='1')return; } catch(e) {}
    // Delay until after page settles; never show over opened chat or cart overlay.
    setTimeout(()=>{if(panel.hidden && !document.hidden)teaser.hidden=false;},900);
    setTimeout(()=>{hideTeaser();},14000);
  }
  function setScreen(target) {
    screen=target;
    root.dataset.screen=screen;
    welcome.hidden=(screen!=='home');
    transcript.hidden=(screen==='home');
    homeBtn.hidden=(screen==='home');
    syncHumanBar();
    if (screen==='chat') scrollBottom();
  }
  function scrollBottom(){transcript.scrollTop=transcript.scrollHeight;}
  function open(){
    panel.hidden=false;root.classList.add('aacai-is-open');
    launcher.setAttribute('aria-expanded','true');
    dismissTeaser();
    if(window.innerWidth<=600) document.documentElement.classList.add('aacai-mobile-open');
    if(screen==='home') loadFeatured(); else textarea.focus({preventScroll:true});
    trackOpen();
    pollIntervalMs = 2500; idlePollStreak = 0;
    schedulePoll(0); // immediate poll so admin takeover is visible without waiting
  }
  function close(){
    panel.hidden=true;root.classList.remove('aacai-is-open');
    launcher.setAttribute('aria-expanded','false');
    document.documentElement.classList.remove('aacai-mobile-open');
    launcher.focus({preventScroll:true});
    stopPoll();
  }
  function makeProductCard(p,style) {
    if (!p || !validUrl(p.url))return null;
    const link=el('a',style==='featured'?'aacai-feature-card':'aacai-product');
    link.href=p.url;link.target='_blank';link.rel='noopener noreferrer';
    if (validUrl(p.image)) {
      const img=el('img',style==='featured'?'aacai-feature-image':'aacai-product-image');
      img.src=p.image;img.alt=p.name||'Bracelet';img.loading='lazy';img.decoding='async';
      link.appendChild(img);
    } else {
      const placeholder=el('div','aacai-photo-fallback');placeholder.appendChild(icon('gem'));
      link.appendChild(placeholder);
    }
    const desc=el('span',style==='featured'?'aacai-feature-info':'aacai-product-desc');
    if (p.collection && style!=='featured') desc.appendChild(el('span','aacai-product-collection',p.collection));
    desc.appendChild(el('strong','aacai-product-name',p.name||'View product'));
    const foot=el('span','aacai-product-foot');
    foot.appendChild(el('span','aacai-product-price',p.price||'See product page'));
    if (style==='featured') {const arrow=el('span','aacai-product-arrow');arrow.appendChild(icon('arrow'));foot.appendChild(arrow);}
    desc.appendChild(foot);
    if(style!=='featured' && p.stock) desc.appendChild(el('small','aacai-product-stock',p.stock));
    link.appendChild(desc);
    return link;
  }
  function showFeaturedCollection(id) {
    if (!featuredTabs || !featuredRail) return;
    const collection = featuredCollections.find(c => c && c.id === id) || featuredCollections.find(c => c && Array.isArray(c.products) && c.products.length) || null;
    featuredTabs.querySelectorAll('.aacai-featured-tab').forEach(btn => {
      const active = collection && btn.dataset.collection === collection.id;
      btn.classList.toggle('is-active', !!active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    featuredRail.textContent = '';
    if (!collection || !Array.isArray(collection.products)) return;
    collection.products.forEach(p => {
      const card = makeProductCard(p, 'featured');
      if (card) featuredRail.appendChild(card);
    });
    featuredRail.scrollLeft = 0;
  }
  async function loadFeatured() {
    if (featuredLoaded || !cfg.featuredEndpoint || !featuredTabs || !featuredRail) return;
    featuredLoaded = true;
    try {
      const r = await fetch(cfg.featuredEndpoint, {method:'GET', headers:{'X-WP-Nonce':cfg.nonce}, credentials:'same-origin', cache:'no-store'});
      if (!r.ok) return;
      const result = await r.json();
      let collections = Array.isArray(result.collections) ? result.collections : [];
      if (!collections.length && Array.isArray(result.products) && result.products.length) {
        collections = [{id:'intention', label:'Intention', products:result.products}];
      }
      featuredCollections = collections.filter(c => c && Array.isArray(c.products) && c.products.length);
      featuredTabs.textContent = '';
      featuredRail.textContent = '';
      if (!featuredCollections.length) {
        featured.hidden = true;
        return;
      }
      featuredCollections.forEach((c, index) => {
        const btn = el('button', 'aacai-featured-tab', c.label || c.id);
        btn.type = 'button';
        btn.dataset.collection = c.id;
        btn.setAttribute('role', 'tab');
        btn.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
        if (index === 0) btn.classList.add('is-active');
        btn.addEventListener('click', () => showFeaturedCollection(c.id));
        featuredTabs.appendChild(btn);
      });
      showFeaturedCollection(featuredCollections[0].id);
      featured.hidden = !featuredRail.children.length;
    } catch (e) {
      featured.hidden = true;
    }
  }
  const sourceLabels={woocommerce:'WooCommerce',fixed:'Approved reply',ai:'DeepSeek + site sources',guide:'Verified page guide',unverified:'Unverified detail',guide_js:'Local quick guide',human_queue:'Team queue'};
  function makeLinks(extras,wrap) {
    if (Array.isArray(extras.products) && extras.products.length) {
      const products=el('div','aacai-products');
      extras.products.slice(0,8).forEach(p=>{const link=makeProductCard(p,'chat');if(link)products.appendChild(link);});
      if(products.children.length)wrap.appendChild(products);
    }
    if(Array.isArray(extras.links) && extras.links.length){
      const row=el('div','aacai-links');
      extras.links.slice(0,5).forEach((l)=>{
        if (!l) return;
        // Legacy V1.3.1 support/Bazi links may persist in browser session.
        // This Customer Assistant is the support destination; never redirect again.
        if(l.action==='tawk' || /\b(contact support|customer care|human support|chat with support|bazi)\b/i.test(l.label||'')) return;
        if(typeof l.url==='string' && /\/bazi-calculator\/?/i.test(l.url)) return;
        if (!validUrl(l.url))return;
        const a=el('a','aacai-link');a.href=l.url;a.target='_blank';a.rel='noopener noreferrer';
        a.appendChild(el('span','',l.label||tp('View details')));a.appendChild(icon('arrow'));
        row.appendChild(a);
      });
      if(row.children.length)wrap.appendChild(row);
    }
  }
  function makeChoices(choices,wrap) {
    if(!Array.isArray(choices)||!choices.length)return;
    const row=el('div','aacai-choices');
    choices.forEach(c=>{
      if(/bazi/i.test(String(c.route||'')+' '+String(c.label||''))) return;
      const b=el('button','aacai-choice',c.label);b.type='button';b.disabled=loading;
      b.addEventListener('click',()=>{if(loading)return;if(c.guide)showGuide(c.guide);else send(c.question||c.label,c.route||'');});
      row.appendChild(b);
    });
    wrap.appendChild(row);
  }
  function addBubble(role,text,extras) {
    const kind = role === 'user' ? 'user' : (role === 'agent' ? 'agent' : (role === 'system' ? 'system' : 'assistant'));
    const wrap=el('div','aacai-message '+(kind==='user'?'aacai-from-user':(kind==='agent'?'aacai-from-agent':(kind==='system'?'aacai-from-system':'aacai-from-assistant'))));
    if (kind==='assistant' || kind==='agent') {
      const avatar=el('div','aacai-avatar'+(kind==='agent'?' aacai-avatar-agent':''));
      if(cfg.markLogo && validUrl(cfg.markLogo)){const img=el('img');img.src=cfg.markLogo;img.alt='';avatar.appendChild(img);}else avatar.appendChild(icon('gem'));
      wrap.appendChild(avatar);
    }
    const body=el('div','aacai-message-body');
    if (kind==='agent') {
      const agentName = (cfg.agentDisplayName && String(cfg.agentDisplayName).trim()) || ti18n('agent_display_default', 'Astro & Charm');
      body.appendChild(el('div','aacai-agent-label', agentName));
    }
    const bubble=el('div','aacai-bubble'+(kind==='system'?' aacai-bubble-system':''),text);
    body.appendChild(bubble);
    if(extras){makeLinks(extras,body);makeChoices(extras.choices,body);
      if(cfg.debugSource && extras.source && sourceLabels[extras.source])body.dataset.responseSource=sourceLabels[extras.source];
    }
    wrap.appendChild(body);transcript.appendChild(wrap);scrollBottom();
  }

  function showRatingPrompt() {
    if (ratingPromptShown) return;
    if (!cfg.satisfactionEndpoint) return;
    try {
      if (sessionStorage.getItem('aacai_rated_' + sessionId) === '1') return;
    } catch (e) {}
    const existing = document.getElementById('aacai-rating-bar');
    if (existing) return;
    ratingPromptShown = true;
    setScreen('chat');
    const bar = document.createElement('div');
    bar.id = 'aacai-rating-bar';
    bar.className = 'aacai-rating-bar';
    bar.innerHTML = '<span class="aacai-rating-q">' + ti18n('rating_q', 'How was this chat?') + '</span>'
      + '<button type="button" data-rating="positive" title="' + ti18n('rating_positive', 'Positive') + '">👍 <small>' + ti18n('rating_positive', 'Positive') + '</small></button>'
      + '<button type="button" data-rating="neutral" title="' + ti18n('rating_neutral', 'Neutral') + '">😐 <small>' + ti18n('rating_neutral', 'Neutral') + '</small></button>'
      + '<button type="button" data-rating="negative" title="' + ti18n('rating_negative', 'Negative') + '">👎 <small>' + ti18n('rating_negative', 'Negative') + '</small></button>';
    const host = transcript.parentNode || panel;
    host.insertBefore(bar, transcript.nextSibling);
    bar.addEventListener('click', async function (ev) {
      const btn = ev.target.closest('[data-rating]');
      if (!btn) return;
      const rating = btn.getAttribute('data-rating');
      try {
        await fetch(cfg.satisfactionEndpoint, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
          body: JSON.stringify({ session: sessionId, rating: rating })
        });
        try { sessionStorage.setItem('aacai_rated_' + sessionId, '1'); } catch (e) {}
      } catch (e) {}
      bar.innerHTML = '<span class="aacai-rating-q">' + ti18n('rating_thanks', 'Thanks for your feedback.') + '</span>';
      setTimeout(function () { if (bar.parentNode) bar.parentNode.removeChild(bar); }, 2200);
    });
  }

  function syncHumanBar() {
    const bar = root.querySelector('.aacai-human-bar');
    if (bar) bar.hidden = !(screen === 'chat' && liveMode === 'bot');
  }
  function updateLiveBanner() {
    syncHumanBar();
    const banner = document.getElementById('aacai-live-banner');
    if (!banner) return;
    if (liveMode === 'agent') {
      banner.hidden = false;
      banner.textContent = ti18n('banner_agent', "You're now chatting with our team.");
      banner.className = 'aacai-live-banner is-agent';
    } else if (liveMode === 'waiting') {
      banner.hidden = false;
      banner.textContent = ti18n('banner_waiting', 'Waiting for our team — replies within 48 hours.');
      banner.className = 'aacai-live-banner is-waiting';
      maybeWaitingCapture();
    } else {
      banner.hidden = true;
      banner.textContent = '';
      banner.className = 'aacai-live-banner';
    }
  }
  // R5 (1.11.0): one-field email capture cards. At most once per hook per
  // session, always after an answer, dismissible, never blocking the chat.
  function capState(hook) { try { return sessionStorage.getItem('aacai_cap_' + hook + '_' + sessionId) || ''; } catch (e) { return ''; } }
  function setCapState(hook, v) { try { sessionStorage.setItem('aacai_cap_' + hook + '_' + sessionId, v); } catch (e) {} }
  function showCaptureCard(hook, opts) {
    if (!cfg.captureEndpoint || !cfg.emailCapture) return;
    if (capState(hook)) return;
    setCapState(hook, 'shown');
    const card = el('div', 'aacai-capture-card');
    const closeBtn = el('button', 'aacai-capture-close', '\u00d7');
    closeBtn.type = 'button'; closeBtn.setAttribute('aria-label', 'Dismiss');
    closeBtn.addEventListener('click', () => { setCapState(hook, 'off'); card.remove(); });
    card.appendChild(closeBtn);
    card.appendChild(el('p', 'aacai-capture-q', opts.prompt));
    const formRow = el('form', 'aacai-capture-form');
    const input = el('input', 'aacai-capture-input');
    input.type = 'email'; input.required = true; input.placeholder = 'you@example.com'; input.setAttribute('aria-label', 'Email address');
    const submit = el('button', 'aacai-capture-submit', tp('Send'));
    submit.type = 'submit';
    formRow.appendChild(input); formRow.appendChild(submit);
    card.appendChild(formRow);
    card.appendChild(el('p', 'aacai-capture-note', tp('Only used for this reply/result. No marketing emails.')));
    const err = el('p', 'aacai-capture-err'); err.hidden = true; card.appendChild(err);
    formRow.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const email = (input.value || '').trim();
      if (!email) return;
      submit.disabled = true;
      try {
        const res = await fetch(cfg.captureEndpoint, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce}, body:JSON.stringify(Object.assign({session:sessionId, email:email, source:opts.source}, opts.payload || {}))});
        const data = await res.json().catch(() => null);
        if (!res.ok) throw new Error((data && data.message) || tp('Please check the email address and try again.'));
        setCapState(hook, 'done');
        card.textContent = '';
        card.appendChild(el('p', 'aacai-capture-done', opts.confirm));
      } catch (e) {
        err.hidden = false;
        err.textContent = (e && e.message) || tp('Please check the email address and try again.');
        submit.disabled = false;
      }
    });
    transcript.appendChild(card);
    scrollBottom();
  }
  function maybeWaitingCapture() {
    if (liveMode !== 'waiting') return;
    showCaptureCard('waiting', {
      source: 'waiting_email_reply',
      prompt: tp("Want our reply by email too? Leave your email and we'll send our team's answer there as well \u2014 replies within 48 hours."),
      confirm: tp("Thanks \u2014 we'll email our reply to you as well.")
    });
  }
  function normalizeBubbleText(s) {
    return String(s || '').replace(/\s+/g, ' ').trim();
  }
  function noteServerIds(payload) {
    if (!payload || typeof payload !== 'object') return;
    const ids = [payload.user_message_id, payload.assistant_message_id, payload.message_id];
    ids.forEach((raw) => {
      const id = parseInt(raw, 10) || 0;
      if (!id) return;
      seenRemoteIds[id] = 1;
      lastPollId = Math.max(lastPollId, id);
    });
    // Attach remoteId to the most recent local assistant/system bubble when server returns assistant_message_id.
    const aid = parseInt(payload.assistant_message_id, 10) || 0;
    if (aid) {
      for (let i = messages.length - 1; i >= 0; i--) {
        const r = messages[i].role;
        if ((r === 'assistant' || r === 'system') && !messages[i].remoteId) {
          messages[i].remoteId = aid;
          break;
        }
      }
    }
    persist();
  }
  function stopPoll() {
    if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
  }
  function schedulePoll(delay) {
    stopPoll();
    if (panel.hidden) return;
    const wait = typeof delay === 'number' ? delay : pollIntervalMs;
    pollTimer = setTimeout(runPoll, Math.max(0, wait));
  }
  /** Absorb a server message: dedupe by id, or match local bubble that has no remoteId yet. */
  function ingestRemoteMessage(m) {
    const id = parseInt(m && m.id, 10) || 0;
    if (!id) return false;
    lastPollId = Math.max(lastPollId, id);
    if (seenRemoteIds[id]) return false;
    // Already attached to a local message (chat response raced ahead of this poll).
    if (messages.some((loc) => loc.remoteId === id)) {
      seenRemoteIds[id] = 1;
      persist();
      return false;
    }
    const role = m.role === 'agent' ? 'agent' : (m.role === 'system' ? 'system' : 'assistant');
    const content = String(m.message || '');
    const normContent = normalizeBubbleText(content);
    // Match a locally-rendered bot/system ack that never received a server id.
    for (let i = messages.length - 1; i >= 0; i--) {
      const loc = messages[i];
      if (loc.remoteId) continue;
      const locRole = loc.role === 'agent' ? 'agent' : (loc.role === 'system' ? 'system' : (loc.role === 'user' ? 'user' : 'assistant'));
      // Local system notices are sometimes stored as assistant in history; compare loosely for assistant/system.
      const sameText = normalizeBubbleText(loc.content) === normContent;
      if (sameText && (role === locRole || (role !== 'agent' && locRole === 'assistant' && (role === 'system' || role === 'assistant')) || (role === 'assistant' && locRole === 'system'))) {
        loc.remoteId = id;
        if (role === 'agent') loc.role = 'agent';
        seenRemoteIds[id] = 1;
        persist();
        return false;
      }
    }
    seenRemoteIds[id] = 1;
    const storeRole = role === 'system' ? 'system' : role;
    messages.push({
      role: storeRole,
      content,
      extras: { source: role === 'agent' ? 'human_queue' : (role === 'system' ? 'fixed' : 'fixed') },
      remoteId: id
    });
    messages = messages.slice(-40);
    persist();
    if (liveMode === 'agent' || liveMode === 'waiting' || role === 'agent' || role === 'system') {
      setScreen('chat');
    }
    addBubble(role, content, { source: role === 'agent' ? 'human_queue' : 'fixed' });
    return true;
  }
  async function runPoll() {
    if (panel.hidden) return;
    if (!cfg.pollEndpoint) { schedulePoll(5000); return; }
    let immediateAgain = false;
    try {
      const url = cfg.pollEndpoint
        + (cfg.pollEndpoint.indexOf('?') >= 0 ? '&' : '?')
        + 'session=' + encodeURIComponent(sessionId)
        + '&after_id=' + encodeURIComponent(String(lastPollId || 0))
        + '&_=' + Date.now();
      const res = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': cfg.nonce, 'Cache-Control': 'no-cache' },
        cache: 'no-store'
      });
      if (res.ok) {
        const data = await res.json();
        if (data && data.mode) {
          liveMode = data.mode;
          updateLiveBanner();
          if (liveMode === 'agent' || liveMode === 'waiting') {
            // Even without new rows, surface live state when admin claimed from backend.
            if (screen === 'home' && (liveMode === 'agent')) setScreen('chat');
          }
        }
        if (data && data.ask_rating && !data.satisfaction) {
          showRatingPrompt();
        }
        let gotNew = 0;
        (data.messages || []).forEach((m) => {
          if (ingestRemoteMessage(m)) gotNew++;
        });
        const latest = parseInt(data.latest_id, 10) || 0;
        if (latest > lastPollId) {
          // Cursor behind server (e.g. filtered user rows); advance carefully only when batch drained.
        }
        if (data.has_more || ((data.messages || []).length >= 40)) {
          immediateAgain = true;
          pollIntervalMs = 400;
        } else if (gotNew) {
          idlePollStreak = 0;
          pollIntervalMs = (liveMode === 'agent' || liveMode === 'waiting') ? 2000 : 2500;
        } else {
          idlePollStreak++;
          if (liveMode === 'agent' || liveMode === 'waiting') {
            // Stay snappy while a human owns the thread.
            pollIntervalMs = 2000;
          } else if (idlePollStreak > 8) pollIntervalMs = 8000;
          else if (idlePollStreak > 3) pollIntervalMs = 4000;
          else pollIntervalMs = 2500;
        }
        persist();
      }
    } catch (e) { /* ignore transient poll errors */ }
    schedulePoll(immediateAgain ? 200 : pollIntervalMs);
  }
  async function requestHuman() {
    if (!cfg.requestHumanEndpoint || loading) return;
    setScreen('chat');
    setLoading(true);
    try {
      const res = await fetch(cfg.requestHumanEndpoint, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce}, body:JSON.stringify({session:sessionId})});
      const data = await res.json();
      if (!res.ok) throw new Error((data && data.message) || tp('Could not reach our team right now.'));
      if (data.mode) liveMode = data.mode;
      updateLiveBanner();
      const reply = (data && data.reply) || tp('A team member will reply here within 48 hours.');
      messages.push({role:'system', content:reply, extras:{source:'human_queue'}});
      messages = messages.slice(-40); persist();
      addBubble('system', reply, {source:'human_queue'});
      maybeWaitingCapture();
      pollIntervalMs = 2000; idlePollStreak = 0;
      schedulePoll(0); // pull claim/system rows immediately
    } catch (e) {
      addBubble('assistant', (e && e.message) || tp('Could not reach our team right now.'));
    } finally {
      setLoading(false);
      textarea.disabled = false;
    }
  }
  function renderHistory() {
    transcript.textContent='';
    messages.forEach(m=>{
      const role = m.role === 'agent' ? 'agent' : (m.role === 'system' ? 'system' : (m.role === 'user' ? 'user' : 'assistant'));
      addBubble(role,m.content,m.extras||{});
    });
    setScreen(messages.length?'chat':'home');
  }
  function showGuide(kind){
    if(loading)return;
    setScreen('chat');
    if(kind==='bracelet'){
      addBubble('assistant',tp('Which type of bracelet would you like to explore? Our five intention designs and two 12-sign zodiac collections are separate.'),{
        choices:[
          {label:tp('Intention Bracelets · 5 themes'),guide:'intention'},
          {label:tp('Zodiac Identity · 12 signs'),guide:'identity'},
          {label:tp('Zodiac Signature · 12 signs'),guide:'signature'}
        ],source:'guide_js'});
    } else if (kind==='intention') {
      addBubble('assistant',tp('Choose the theme you want your bracelet to represent. I’ll show the matching item from our current store.'),{
        choices:[
          {label:tp('Love'),question:'Show me Love bracelets',route:'product_love'},
          {label:tp('Money'),question:'Show me Money bracelets',route:'product_money'},
          {label:tp('Luck'),question:'Show me Lucky bracelets',route:'product_luck'},
          {label:tp('Protection'),question:'Show me Protection bracelets',route:'product_protection'},
          {label:tp('Healing'),question:'Show me Healing bracelets',route:'product_healing'},
          {label:tp('Choose another collection'),guide:'bracelet'}
        ],source:'guide_js'});
    } else if(kind==='identity'||kind==='signature') {
      const tier=kind==='identity'?'Identity':'Signature';
      const signs=['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
      const choices=signs.map(sign=>({label:sign,question:'Show me '+sign+' Zodiac '+tier+' bracelet',route:'zodiac_'+kind+'_'+sign.toLowerCase()}));
      choices.push({label:'Choose another collection',guide:'bracelet'});
      addBubble('assistant','Zodiac '+tier+' has one design for each sign. Choose your sign to see the exact bracelet and current price.',{choices,source:'guide_js'});
    } else if (kind==='readings') {
      addBubble('assistant',tp('Which experience would you like to explore? I can guide you to the official pages.'),{
        choices:[
          {label:tp('Tarot Reading'),question:'Explore Tarot Reading',route:'reading_tarot'},
          {label:tp('Astrology'),question:'Explore Astrology Calculator',route:'reading_astrology'},
          {label:tp('Find a Bracelet'),guide:'bracelet'}
        ],source:'guide_js'});
    } else if(kind==='support') {
      addBubble('assistant',tp('What do you need help with?'),{
        choices:[
          {label:tp('Shipping & Delivery'),question:'What is your shipping policy?',route:'support_shipping'},
          {label:tp('Returns & Refunds'),question:'What is your return policy?',route:'support_returns'},
          {label:tp('Orders & Payments'),question:'Where is my order?',route:'support_order'},
          {label: ti18n('chat_with_team', 'Chat with our team'), guide:'human'}
        ],source:'guide_js'});
    } else if(kind==='human') {
      requestHuman();
    }
  }
  function setLoading(on){
    loading=!!on;textarea.disabled=on;sendBtn.disabled=on;
    root.querySelectorAll('.aacai-choice,.aacai-quick-action,.aacai-human-btn').forEach(b=>b.disabled=on);
    const existing=$('#aacai-loading');if(existing)existing.remove();
    if(on){const e=el('div','aacai-loading');e.id='aacai-loading';
      e.appendChild(el('span','aacai-loading-dot'));
      e.appendChild(el('span','aacai-loading-dot'));
      e.appendChild(el('span','aacai-loading-dot'));
      e.appendChild(el('span','aacai-sr',tp('Checking our site…')));
      transcript.appendChild(e);scrollBottom();}
  }
  async function send(value,route){
    const q=(value||'').trim();if(!q||loading)return;
    setScreen('chat');
    if(q.length>(cfg.maxLength||1200)){addBubble('assistant',tp('Please shorten your question.'));return;}
    const history=messages.slice(-8).filter(m=>m.role==='user'||m.role==='assistant').map(m=>({role:m.role,content:m.content}));
    messages.push({role:'user',content:q});messages=messages.slice(-20);persist();addBubble('user',q);
    textarea.value='';setLoading(true);
    try {
      const res=await fetch(cfg.endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce},body:JSON.stringify({session:sessionId,message:q,history:history,route:route||'',page_product:(cfg.pageProduct&&cfg.pageProduct.id)?cfg.pageProduct.id:0})});
      const response=await res.json();
      if(!res.ok)throw new Error((response&&response.message)||tp('Customer support is temporarily unavailable.'));
      const reply=(response&&typeof response.reply==='string')?response.reply:tp('Sorry, I could not complete that response.');
      const extras={products:Array.isArray(response.products)?response.products:[],links:Array.isArray(response.links)?response.links:[],choices:Array.isArray(response.choices)?response.choices:[],source:(response.source||'')};
      if (response && response.mode) { liveMode = response.mode; updateLiveBanner(); }
      const bubbleRole = extras.source === 'human_queue' ? 'system' : 'assistant';
      const storeRole = extras.source === 'human_queue' ? 'system' : 'assistant';
      // Mark server ids BEFORE rendering so an in-flight poll cannot double-add the same assistant row.
      noteServerIds(response);
      const aid = parseInt(response && response.assistant_message_id, 10) || 0;
      // If poll already ingested this id, just attach extras onto that message; do not addBubble again.
      let ingestedIdx = -1;
      if (aid) {
        for (let i = messages.length - 1; i >= 0; i--) {
          if (messages[i].remoteId === aid) { ingestedIdx = i; break; }
        }
      }
      setLoading(false);
      if (ingestedIdx >= 0) {
        messages[ingestedIdx].extras = extras;
        messages[ingestedIdx].role = storeRole;
        persist();
        // Poll may have rendered text-only; re-draw when chat response carries cards/links/choices.
        const rich = (extras.products && extras.products.length) || (extras.links && extras.links.length) || (extras.choices && extras.choices.length);
        if (rich) {
          const nodes = transcript.querySelectorAll('.aacai-from-assistant, .aacai-from-system, .aacai-from-agent');
          const last = nodes[nodes.length - 1];
          if (last) last.remove();
          addBubble(bubbleRole, reply, extras);
        }
      } else {
        messages.push({role:storeRole,content:reply,extras,remoteId:aid||undefined});
        messages=messages.slice(-40);persist();
        addBubble(bubbleRole,reply,extras);
      }
      if (response && response.email_hook === 'bracelet') {
        const p0 = (extras.products && extras.products[0]) || {};
        showCaptureCard('bracelet', {
          source: 'result_email_bracelet',
          prompt: tp('Want this bracelet match sent to your email?'),
          confirm: tp("Thanks \u2014 we've sent it to your email."),
          payload: { product_name: String(p0.name || ''), product_url: String(p0.url || '') }
        });
      } else if (response && response.email_hook === 'reading') {
        showCaptureCard('reading', {
          source: 'result_email_reading',
          prompt: tp('Want the link and a summary sent to your email?'),
          confirm: tp("Thanks \u2014 we've sent it to your email."),
          payload: { reading_kind: response.email_hook_kind === 'astrology' ? 'astrology' : 'tarot' }
        });
      }
      if (response && response.mode === 'waiting') maybeWaitingCapture();
      pollIntervalMs = (liveMode === 'agent' || liveMode === 'waiting') ? 2000 : 2500;
      idlePollStreak = 0;
      schedulePoll(liveMode === 'agent' || liveMode === 'waiting' ? 0 : pollIntervalMs);
    } catch(e) {
      setLoading(false);addBubble('assistant',(e&&e.message)||tp('Sorry, there was a problem. Please try again.'));
    } finally {
      textarea.disabled=false;textarea.focus({preventScroll:true});
    }
  }
  launcher.addEventListener('click',()=>panel.hidden?open():close());
  closeBtn.addEventListener('click',close);
  if(teaser){teaser.addEventListener('click',(e)=>{if(e.target.closest('.aacai-teaser-close'))return;open();});}
  if(teaserClose){teaserClose.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();dismissTeaser();});}
  homeBtn.addEventListener('click',()=>{setScreen('home');loadFeatured();});
  clearBtn.addEventListener('click',()=>{
    messages=[];
    try{sessionStorage.removeItem(STORE);sessionStorage.removeItem(STORE+'-live');sessionStorage.removeItem(SESSION);}catch(e){}
    sessionId=getSession(); setSidCookie(sessionId); lastPollId=0; seenRemoteIds={}; liveMode='bot'; updateLiveBanner();
    renderHistory();loadFeatured(); schedulePoll(0);
  });
  form.addEventListener('submit',e=>{e.preventDefault();send(textarea.value,'');});
  textarea.addEventListener('keydown',e=>{
    if(e.key==='Enter'&&!e.shiftKey&&!e.isComposing){e.preventDefault();send(textarea.value,'');}
    if(e.key==='Escape')close();
  });
  root.addEventListener('keydown',e=>{if(e.key==='Escape'&&!panel.hidden)close();});
  root.querySelectorAll('[data-guide]').forEach(b=>b.addEventListener('click',()=>showGuide(b.dataset.guide)));
  root.querySelectorAll('[data-route]').forEach(b=>b.addEventListener('click',()=>send(b.dataset.question||b.textContent,b.dataset.route)));
  root.querySelectorAll('[data-aacai-icon]').forEach(node=>node.appendChild(icon(node.dataset.aacaiIcon)));
  setSidCookie(sessionId);
  recover();renderHistory();updateLiveBanner();scheduleTeaser();
})();
