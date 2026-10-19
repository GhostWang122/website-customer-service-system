# Website Customer Service System

AI-powered customer service system for websites — built for e-commerce stores, consulting and service businesses, and content sites that need front-line support without a full-time support team.

Current release ships as a WordPress / WooCommerce plugin: **AstroAndCharm Customer Assistant v1.11.5** (folder `astroandcharm-ai-assistant/`).

## What it does
- On-site chat widget with approved fixed replies for store policies (shipping, returns, free-shipping threshold, product care) — policy answers never improvise.
- AI-assisted answers (DeepSeek) grounded in product data and administrator-maintained knowledge, with strict no-fabrication rules for prices, delivery estimates and refund approvals.
- Human handover in the same window: visitor chats land in an admin Inbox (Waiting / Needs follow-up states), where the team can take over and reply directly.
- Lead capture hooks (email reply-back, result-by-email), contact source tracking, unanswered-question reports, usage analytics and daily AI-call caps.
- Privacy guards: card-number / password shaped messages are refused and kept out of the AI layer.

## Install
See `astroandcharm-ai-assistant/README.md` for install/upgrade steps and the full changelog.

## Notes on this public copy
- WhatsApp numbers are replaced with `YOUR_WHATSAPP_NUMBER` placeholders — set your real channel links before deploying.
- No API keys are stored in this repository. The AI API key is entered in the plugin Settings and kept only in the WordPress database.
- Business contact shown in the code is the store's public support email.
