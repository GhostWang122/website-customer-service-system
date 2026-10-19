# AstroAndCharm Customer Assistant — v1.11.5

## Install / upgrade (important)
1. Upload the release zip **astroandcharm-ai-assistant-1.11.5.zip** on the Plugins page and choose **Replace current with uploaded**.
2. Do NOT change any settings after upgrade — the built-in migrations preserve Visibility (Public), rate/daily caps, logging, edited Knowledge, all sessions/messages, and every Fixed Reply except where a migration notes a targeted by-title update.
3. Confirm the Plugins list shows **Version 1.11.5**.
4. Open **Settings** once after upgrade (runs pending migrations), then clear caches.
5. Rollback package: the previous release zip (1.11.4).

> Sanitised public copy: WhatsApp numbers in this repository are replaced with `YOUR_WHATSAPP_NUMBER` placeholders. Set your real channel links in the plugin settings / code before deploying from this copy. No API keys are stored in this repository — the AI API key is entered in the plugin Settings and kept in the WordPress database only.

## What changed in V1.9.0 (dev/test loop round 1)
| Item | Change |
|------|--------|
| **F1 Inbox Loading** | Thread open no longer hangs: server-side translation enrichment is budget-capped (8 calls / 4 s wall clock, then messages render untranslated), MyMemory timeout 12 s → 6 s; admin JS retries the thread request (3 attempts, backoff, 20 s timeout) and shows a Retry button instead of a stuck "Loading…". |
| **F2 Payment methods** | "Can I pay by credit card?" is no longer treated as sharing card data. Privacy refusal now targets card-number shapes (long digit runs / spaced groups / number after "card number" / CVV with digits) and passwords/SSN. Payment-methods questions are answered from the **live enabled WooCommerce gateways**. |
| **F3 Materials pull** | Product detail pull now includes product **tags + visible attributes** (the materials a product page lists — e.g. Lucky Charm's stones). Materials answers name them; product cards carry a `materials` field for the AI layer. No certified-gemstone claims. |
| **F4 Gift + deadline** | Gift-with-deadline asks (gift/girlfriend/birthday + next week / in time / …) are answered intent-first: honest 15–30 business days timing, instantly delivered readings as the alternative, product card only as an attachment. More typo mappings added. |
| **F5 Multi-intent** | Each question in a multi-question message is routed separately — payment methods and free shipping are now first-class slots next to shipping / international / returns, so 3-question messages get 3 answers. |
| **C1–C13 copy** | Promotions approved no-discount body; Zodiac Compare slug text removed (+ system prompt: internal distinction only, never mention slugs/URL internals); Contact "(Inbox)" removed (+ system prompt reworded); Tarot generic + How merged to one standard text with an explicit in-code priority guard for the tarot group; Astrology completed (birth date/time/city + page-generated chart + Astrology link); Free Shipping short body; Authenticity wording aligned with the real materials pull; Payment Issue deterministic "released automatically by your bank"; Change Address team-confirm ending; sizing Product notes use complete sentences only; brand body unified with the Knowledge text; final fallback softened; "could not verify" series reworded to "couldn't find". |
| **C14 Defaults = live** | Live-only Fixed Replies (Christmas cutoff / cancel order / readings delivery) and live-improved bodies are backfilled into the package defaults, applied by `migrate_190` as targeted by-title updates + missing-title appends only. Shipping/Returns/International approved policy bodies are untouched. |

## What changed in 1.11.5 (owner pause switch)

- **Email capture is paused by default**: new setting `email_capture`
  (Settings → "Email capture cards & reply-by-email"), default OFF — so
  upgrading to 1.11.5 immediately hides all three capture cards, makes the
  /capture endpoint refuse submissions, and pauses the auto-email of team
  replies to opted-in visitors. The owner asked for the pause while
  dedicated staff watch the inbox (2026-10-18). Nothing is deleted:
  captures, contacts and counts are preserved, the channel row is
  untouched, and re-checking the setting restores the full round-5
  behavior with no further release.

## What changed in 1.11.4 (round 5.1 — P8)

- **Contact capture trail**: the Contacts list keeps its last-touch Source
  column, and each contact with form captures now has an expandable
  "Capture trail (n)" row listing every capture event (time UTC, source,
  link to the chat) in time order — the per-contact source history is no
  longer lost when the same email uses several hooks. The trail is read
  from the captures table, which has recorded every form capture since
  1.11.0, so events from 1.11.0 onward are shown; nothing was fabricated
  or backfilled (contacts that only typed an email in chat have no trail).
  Usage per-source counts are unchanged.

## What changed in 1.11.3

- Channel row gains Pinterest (owner request): https://www.pinterest.com/astroandcharm/
  — the site's own Pinterest URL as used by its social dock ("Follow AstroAndCharm
  on Pinterest") and footer. Row is now Email / WhatsApp / Instagram / Pinterest.

## What changed in 1.11.2 (owner correction)

- The teaser hook is an ADDITION, not a replacement (the 1.11.1 teaser had
  swapped the welcome copy out). The bubble now shows the hook line
  "First free Tarot & Astrology calculator" as a gold strip on top, with the
  original welcome copy intact below it ("Need a hand? / Questions about
  bracelets, Tarot, Astrology or your order? / Ask me anything →"). Clicking
  the teaser is back to simply opening the chat (no forced readings guide).
  The earlier appearance (0.9s) and the +86 WhatsApp number from 1.11.1 stay.

## What changed in 1.11.1 (owner adjustments)

- **Teaser is now the hook**: the launcher teaser leads with the owner's line
  "First free Tarot & Astrology calculator" (subline: the first Tarot reading is
  free, plus bracelet and order help; CTA "Start free →"). It appears sooner
  (0.9s instead of 1.9s, stays ~14s) and clicking it opens the chat on the
  readings guide (Tarot / Astrology / Find a Bracelet).
- **WhatsApp number**: the channel row now uses the global number
  https://wa.me/YOUR_WHATSAPP_NUMBER (the footer value) instead of the +1 dock number,
  per the owner's direction — the store serves a global audience. (The site's
  social dock outside this plugin still carries the +1 link.)

## What changed in 1.11.0 (round 5 — email capture)

- **R5-1 Waiting email reply**: when a chat enters Waiting, a dismissible card offers
  "Want our reply by email too?" (one email field; microcopy "Only used for this
  reply/result. No marketing emails."). Submitting stores the opt-in
  (new `aacai_captures` table + Contacts, source `waiting_email_reply`) and confirms
  in chat. When a team member replies in the Inbox, that reply is also emailed to
  the visitor via wp_mail (subject "AstroAndCharm — a reply from our team", body is
  the reply text plus a link back to the site) — hooked into the single agent-reply
  endpoint, using the visitor-visible (translated) message text.
- **R5-2 Result hooks**: after a bracelet recommendation answer the widget offers
  "Want this bracelet match sent to your email?" (source `result_email_bracelet`;
  the email contains the product name + on-site product link). After a Tarot /
  Astrology guidance answer it offers "Want the link and a summary sent to your
  email?" (source `result_email_reading`; the email carries the experience page
  link + a one-line summary). Each hook shows at most once per session, is
  dismissible, and never blocks the answer it follows.
- **R5-3 Channel row**: a persistent "Reach us:" row under the chat — Email
  (mailto:company@astroandcharm.com), WhatsApp and Instagram (new tab). Values read
  from the live site on 2026-10-18: WhatsApp uses the global social dock's contact
  link (aria-label "Contact AstroAndCharm on WhatsApp", https://wa.me/YOUR_WHATSAPP_NUMBER);
  note the theme footer carries a different WhatsApp number
  (https://wa.me/YOUR_WHATSAPP_NUMBER) — flagged to the owner as a possible stale footer
  value. Instagram is https://www.instagram.com/astroandcharm/ (footer + dock agree).
- **Admin**: Contacts gained a Source column (schema 1.8.0); Usage & Logs shows
  all-time capture counts per source. Capture discipline: email field only, cards
  only after answers, once per session per hook, no marketing opt-in anywhere,
  no discount wording.

## What changed in 1.10.2 (round-4 smoke fix)

- The 1.10.1 post-deploy smoke found that "How should I care for my bracelet?" reached
  neither the materials route nor the Product Materials & Care fixed reply (its trigger
  phrases did not cover natural care phrasings), so the AI answered without the approved
  care sentence. 1.10.2 adds a dedicated care route: pure care questions (how to / do I /
  should I care, care for my bracelet, caring for, take care of, 保养 phrasings) are
  served the Product Materials & Care fixed reply directly. Mixed material+care
  questions stay on the materials route. The entry's live trigger list is also widened
  by migrate_1102() (append-missing only; bodies and enabled states untouched).

## What changed in 1.10.1 (round 4)

- **R4-1 Waiting expectation (48 hours)**: every visitor-facing Waiting surface now uses
  the owner's 48-hour wording — the request-human reply, the in-chat handover reply, the
  Waiting banner, and the transcript system message ("A team member will reply here within
  48 hours."). No surface promises a faster human reply. The waiting email alert to the
  owner already existed and is unchanged. A separate offline form was NOT added: once a
  chat enters Waiting, visitor messages are stored, the owner is emailed, and any email
  address the visitor types is captured into Contacts — the chat itself is the intake.
- **R4-2 Care sentence**: Product Materials & Care now ends with the owner-approved care
  sentence; migrate_1101() appends it to the live entry only when absent, preserving the
  rest of the live body verbatim.
- **R4-3 Assistant Identity reply**: new fixed reply for direct identity questions
  (are you a robot / a bot / AI / a real person / is this automated, incl. Chinese). It
  answers honestly as the assistant and points to 'Chat with our team'; it never claims
  to be human. The identity route runs before the human-request route, but an explicit
  human request in the same message still goes to Waiting (the human-request detector
  also learned "get me a human", "connect me with an agent", "talk to an agent").
- **R4-4 Tarot states**: verified live on 1.10.0 — Tarot Reading / Tarot Free /
  Tarot How It Works / Tarot Not In Chat all enabled, Bazi Introduction disabled.
  migrate_1101() re-asserts the four Tarot entries as enabled per the owner's ruling;
  Bazi and every other entry's enabled state are never touched.

## What changed in 1.10.0 (round 3)

- **R3-1 Unanswered questions report** (Analytics page, follows the page's date range):
  visitor questions whose reply was a fallback (source `unverified`) are aggregated by
  question text with times asked, last asked (UTC) and a link into the conversation.
  Placement rationale: Analytics already counts unverified replies per day; the panel
  turns that counter into the owner's fixed-reply to-do list.
- **R3-2 Daily AI cap warnings**: when today's attempted AI calls reach 80% of the daily
  cap, a warning banner appears in wp-admin and one email goes to the alerts recipient
  (falling back to the site admin email); at 100% a second, stronger banner/email fires.
  Each level emails at most once per level per UTC day. Usage & Logs shows
  "Today: used X / limit Y / remaining Z" at the top.
- **R3-3 DeepSeek failure degradation**: a DeepSeek timeout or upstream error now returns
  a real chat reply (question noted, team will follow up in this chat), logs the exchange
  as `unverified` (so it appears in the R3-1 report), and flags the session
  Needs follow-up (which also triggers the existing follow-up email alert). Fixed replies
  never touch DeepSeek and are unaffected. Previously this returned a bare REST 502.
- **R3-4 Page context**: on WooCommerce product pages the widget's opening actions become
  product questions (What is it made of? / How do I choose a size? / How long is delivery?)
  plus the team button; other pages keep the standard actions. The widget sends the current
  product id with each chat message; materials / sizing / authenticity answers resolve to
  that product (after anything named in the current message, before older chat history)
  instead of asking which product is meant.
- No settings, replies, knowledge, sessions or approved policy texts change in 1.10.0;
  there is no data migration in this version.

## What changed in 1.9.1 (round 2)

- T8/C6 fix at the generation layer: the generated "Free Shipping" reply was mapped to the
  full shipping policy in the chat route, the admin row display and the save handler; all
  three now use the short free-shipping body from live Settings values. migrate_191()
  re-asserts the stored row and appends the three live-only replies if missing, without
  touching enabled states. Includes the round-1 C14 backfill (Christmas Delivery /
  Cancel My Order / Readings Delivery verbatim from live + live Change Shipping Address).

## Policies (unchanged)
Shipping **15–30 business days**; returns **30 days** from delivery, unused/original condition, refunds to original payment method; free standard shipping **$80+**; return shipping by reason (merchant fault → merchant covers; customer reason → customer pays); **no discounts sitewide**; Christmas cutoff **2026-11-10 23:59 ET**; paid readings delivered on the page with an email copy; Bazi is hidden and never recommended by the assistant.

## Quick retest (round 1)
| Check | Phrase | Expect |
|-------|--------|--------|
| F1 | Open several Inbox conversations in a row | Thread loads every time; on failure a Retry button appears, never a stuck Loading |
| F2 | `Can I pay by credit card on your site?` | Normal payment-methods answer naming the live gateways, no privacy refusal |
| F2b | `my card number is 4111 1111 1111 1111` | Privacy refusal (no card data in chat) |
| F3 | `What is the Lucky Charm Bracelet made of?` / `Is it real crystal?` (with product context) | Answer names the materials listed on the product page (its stones); no certified-gemstone claim |
| F4 | `hi i want bye 2 money braclet for my gf bday next week will it arive in time` | Text first: 15–30 business days won't arrive by next week + readings as immediate alternative; Money card attached |
| F5 | `Do you ship to Canada? Can I pay by credit card? How long is the return window?` | All three answered in one reply |
