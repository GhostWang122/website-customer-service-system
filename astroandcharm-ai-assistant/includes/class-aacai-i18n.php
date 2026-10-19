<?php
/**
 * Admin bilingual EN + zh-Hant labels (V1.7.9). Visitor widget is English-only.
 * Format: "English · 繁體中文"
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AACAI_I18n {
    /** @var array<string,array{0:string,1:string}> */
    private static $map = array(
        // Inbox chrome
        'inbox'           => array( 'Inbox', '收件匣' ),
        'customer_assistant' => array( 'Customer Assistant', '顧客助理' ),
        'legacy_list'     => array( 'Legacy list', '舊版列表' ),
        'legacy_list_view'=> array( 'Legacy list view', '舊版列表檢視' ),
        'compose'         => array( 'Compose', '撰寫' ),
        'all'             => array( 'All', '全部' ),
        'mine'            => array( 'Mine', '我的' ),
        'chats'           => array( 'Chats', '對話' ),
        'waiting'         => array( 'Waiting', '等待中' ),
        'agent'           => array( 'Agent', '客服' ),
        'needs_reply'     => array( 'Needs reply', '待回覆' ),
        'needs_followup'  => array( 'Needs follow-up', '需跟進' ),
        'trash'           => array( 'Trash', '垃圾桶' ),
        'tickets'         => array( 'Tickets', '工單' ),
        'spam'            => array( 'Spam', '垃圾訊息' ),
        'na'              => array( 'N/A', '不適用' ),
        'search'          => array( 'Search…', '搜尋…' ),
        'search_btn'      => array( 'Search', '搜尋' ),
        'search_contacts' => array( 'Search name, email, phone…', '搜尋姓名、電郵、電話…' ),
        'empty_title'     => array( 'No conversation selected', '尚未選擇對話' ),
        'empty_hint'      => array( 'Choose a chat from the list to start helping visitors.', '請從左側列表選擇對話以協助訪客。' ),
        'no_convos'       => array( 'No conversations in this folder.', '此資料夾沒有對話。' ),
        'loading'         => array( 'Loading…', '載入中…' ),
        'error'           => array( 'Something went wrong. Please retry.', '發生錯誤，請重試。' ),
        'visitor'         => array( 'Visitor', '訪客' ),
        'take_over'       => array( 'Take over', '接管' ),
        'return_to_bot'   => array( 'Return to bot', '交回機器人' ),
        'resolve'         => array( 'Resolve', '結案' ),
        'details'         => array( 'Details', '詳情' ),
        'send'            => array( 'Send', '傳送' ),
        'type_reply'      => array( 'Type a reply…', '輸入回覆…' ),
        'send_hint'       => array( 'Sending claims the chat if needed', '傳送時如需要會自動接管對話' ),
        'about'           => array( 'About', '關於' ),
        'orders'          => array( 'Orders', '訂單' ),
        'organization'    => array( 'Organization', '組織' ),
        'deals'           => array( 'Deals', '商機' ),
        'tags'            => array( 'Tags', '標籤' ),
        'save_tags'       => array( 'Save tags', '儲存標籤' ),
        'open'            => array( 'Open', '進行中' ),
        'hide'            => array( 'Hide', '隱藏' ),
        'stub'            => array( 'Stub', '預留' ),
        'recording_off'   => array( 'Chat recording is OFF.', '聊天記錄已關閉。' ),
        'mode_bot'        => array( 'Bot', '機器人' ),
        'mode_waiting'    => array( 'Waiting for agent', '等待客服' ),
        'mode_agent'      => array( 'Agent active', '客服服務中' ),

        // Contacts
        'contacts'        => array( 'Contacts', '聯絡人' ),
        'add_contact'     => array( 'Add Contact', '新增聯絡人' ),
        'people'          => array( 'People', '人物' ),
        'organizations'   => array( 'Organizations', '組織' ),
        'save_contact'    => array( 'Save contact', '儲存聯絡人' ),
        'name'            => array( 'Name', '姓名' ),
        'email'           => array( 'Email', '電郵' ),
        'phone'           => array( 'Phone', '電話' ),
        'open_chat'       => array( 'Open chat', '開啟對話' ),
        'last_seen'       => array( 'Last seen (UTC)', '最後出現（UTC）' ),
        'last_session'    => array( 'Last session', '最近對話' ),
        'primary_email'   => array( 'Primary email', '主要電郵' ),
        'primary_phone'   => array( 'Primary phone', '主要電話' ),
        'no_contacts'     => array( 'No contacts yet. When a visitor types an email in chat, it appears here.', '尚無聯絡人。訪客在聊天中輸入電郵後會顯示於此。' ),

        // Analytics / Reporting
        'reporting'       => array( 'Reporting', '報表' ),
        'live_chat'       => array( 'Live Chat', '即時聊天' ),
        'chat_volume'     => array( 'Chat Volume', '聊天量' ),
        'user_satisfaction'=> array( 'User Satisfaction', '用戶滿意度' ),
        'reply_sources'   => array( 'Reply sources', '回覆來源' ),
        'missed_chats'    => array( 'Missed Chats', '未接對話' ),
        'chat_duration'   => array( 'Chat Duration', '聊天時長' ),
        'other'           => array( 'Other', '其他' ),
        'lead_capture'    => array( 'Lead Capture', '潛在客戶' ),
        'apply'           => array( 'Apply', '套用' ),
        'negative'        => array( 'Negative', '負面' ),
        'positive'        => array( 'Positive', '正面' ),
        'neutral'         => array( 'Neutral', '中立' ),
        'last_7'          => array( 'Last 7 days', '近 7 日' ),
        'last_30'         => array( 'Last 30 days', '近 30 日' ),
        'last_90'         => array( 'Last 90 days', '近 90 日' ),

        // Settings
        'overview'        => array( 'Overview', '總覽' ),
        'save_property'   => array( 'Save property settings', '儲存物業設定' ),
        'property_name'   => array( 'Property name', '物業名稱' ),
        'status_visibility'=> array( 'Status & visibility', '狀態與顯示' ),
        'active'          => array( 'Active', '啟用中' ),
        'off'             => array( 'Off', '關閉' ),
        'visibility'      => array( 'Visibility', '可見範圍' ),
        'property_url_region' => array( 'Property URL & region', '物業網址與地區' ),
        'javascript_api'  => array( 'Javascript API / DeepSeek', 'Javascript API／DeepSeek' ),
        'store_policies'  => array( 'Authoritative Store Policies', '官方商店政策' ),
        'data_privacy'    => array( 'Data & privacy / copy', '資料與隱私／文案' ),
        'agent_display_name' => array( 'Agent display name', '客服顯示名稱' ),
        'agent_display_default' => array( 'Astro & Charm', '客服' ),
        'translate_agent_to_en' => array( 'Translate agent replies to English', '客服回覆譯成英文' ),

        // Widget (visitor-facing)
        'chat_with_team'  => array( 'Chat with our team', '聯絡真人客服' ),
        'banner_agent'    => array( "You're now chatting with our team.", '您正在與客服團隊對話。' ),
        'banner_waiting'  => array( 'Waiting for our team — replies within 48 hours.', '正在等候客服加入（48 小时内回复）…' ),
        'rating_q'        => array( 'How was this chat?', '這次對話如何？' ),
        'rating_thanks'   => array( 'Thanks for your feedback.', '感謝您的回饋。' ),
        'rating_positive' => array( 'Positive', '正面' ),
        'rating_neutral'  => array( 'Neutral', '中立' ),
        'rating_negative' => array( 'Negative', '負面' ),
    );

    /** English phrase => zh-Hant for admin UI (V1.7.5). Visitor widget does not use this map. */
    private static $phrases = array(
        'Bot' => '機器人',
        'Waiting for agent' => '等待客服',
        'Agent active' => '客服服務中',
        'Session' => '對話編號',
        'Mode' => '模式',
        'Messages' => '訊息數',
        'First activity (UTC)' => '首次活動（UTC）',
        'Last activity (UTC)' => '最後活動（UTC）',
        'Agent' => '客服',
        'First reply source' => '首次回覆來源',
        'Assistant' => '助理',
        'Team agent' => '客服人員',
        'Notice' => '通知',
        'Visitor' => '訪客',
        'WooCommerce inactive — no order attribution.' => 'WooCommerce 未啟用，無訂單歸因。',
        'No attributed orders for this session.' => '此對話沒有歸因訂單。',
        'Display name' => '顯示名稱',
        'Shown above live-agent replies in the visitor widget. Leave blank for the default.' => '顯示於訪客小工具真人客服回覆上方。留空則使用預設名稱。',
        'From WordPress Settings → General. Edit there to rename.' => '取自 WordPress 設定 → 一般，請在該處修改名稱。',
        'Brand mark (plugin assets)' => '品牌標誌（外掛資源）',
        'Off' => '關閉',
        'Administrator only (recommended for testing)' => '僅管理員（建議測試用）',
        'Public — show to all visitors' => '公開 — 所有訪客可見',
        'Save chat transcripts (required for Inbox / Contacts capture)' => '儲存聊天記錄（收件匣／聯絡人擷取必需）',
        'Conversation access' => '對話存取權限',
        'Administrators only' => '僅管理員',
        'Administrators + Shop Managers' => '管理員＋商店經理',
        'Floating button position' => '浮動按鈕位置',
        'Bottom right' => '右下角',
        'Bottom left' => '左下角',
        'Property URL' => '物業網址',
        'Property ID' => '物業 ID',
        'Timezone' => '時區',
        'WordPress timezone. Chat logs store UTC.' => 'WordPress 時區；聊天記錄以 UTC 儲存。',
        'Tarot URL' => '塔羅網址',
        'Astrology URL' => '占星網址',
        'DeepSeek API key' => 'DeepSeek API 金鑰',
        'Key configured — leave blank to keep' => '已設定金鑰 — 留空即保留',
        'Paste your key' => '貼上您的金鑰',
        'Remove saved key' => '移除已存金鑰',
        'Model' => '模型',
        'Maximum AI requests per day' => '每日 AI 請求上限',
        'Maximum messages per IP per hour' => '每 IP 每小時訊息上限',
        'Log retention (days)' => '記錄保留天數',
        'Estimated delivery — minimum (business days)' => '預計送達 — 最少（工作天）',
        'Estimated delivery — maximum (business days)' => '預計送達 — 最多（工作天）',
        'Returns and exchanges window (days)' => '退換貨期限（天）',
        'Free shipping threshold ($)' => '免運門檻（$）',
        'Welcome message' => '歡迎訊息',
        'Site knowledge / AI constraints' => '網站知識／AI 限制',
        'Saved.' => '已儲存。',
        'Property settings for this WordPress site (one property = this installation). Matches Tawk Overview organization.' => '此 WordPress 網站的物業設定（一個安裝即一個物業）。',
        'No API keys or private customer data. Update your privacy notice when enabling chat recording or Contacts capture.' => '請勿填入 API 金鑰或顧客私人資料。啟用聊天記錄或聯絡人擷取時請更新私隱聲明。',
        'Widget opens' => '小工具開啟數',
        'Inbound sessions' => '進線對話',
        'Open → chat' => '開啟 → 對話',
        'Human takeovers' => '真人接管',
        'Attributed orders' => '歸因訂單',
        'Conversion rate' => '下單率',
        'Attributed revenue' => '歸因營收',
        'Total inbound sessions' => '進線對話總數',
        'Sessions by day (UTC)' => '每日對話數（UTC）',
        'Ratings by day' => '每日評分',
        'No data yet.' => '尚無資料。',
        'No daily rows yet.' => '尚無每日資料。',
        'Source' => '來源',
        'Count' => '數量',
        'Share' => '佔比',
        'WooCommerce inactive — live order attribution unavailable.' => 'WooCommerce 未啟用，無法即時歸因訂單。',
        'Collected when visitors rate after an agent returns the chat to bot or marks Resolve. One rating per session.' => '客服交回機器人或結案後由訪客評分，每個對話限評一次。',
        'Fixed / policy' => '固定回覆／政策',
        'DeepSeek AI' => 'DeepSeek AI',
        'Guide' => '導覽',
        'Unverified' => '未驗證',
        'Human queue' => '真人佇列',
        'System' => '系統',
        'Customer Care' => '顧客服務',
        'Need a hand?' => '需要協助嗎？',
        'Questions about bracelets, Tarot, Astrology or your order?' => '有關手鏈、塔羅、占星或訂單的問題？',
        'Ask me anything →' => '隨時問我 →',
        'Customer Assistant' => '顧客助理',
        'AstroAndCharm · Customer Care' => 'AstroAndCharm · 顧客服務',
        'Welcome home' => '返回首頁',
        'New conversation' => '新對話',
        'Close' => '關閉',
        'Your AstroAndCharm Guide' => '您的 AstroAndCharm 指南',
        'How can we guide you?' => '我們可以如何協助您？',
        'Discover a bracelet, explore a reading, or get help with your order.' => '探索手鏈、體驗占卜，或取得訂單協助。',
        'Welcome to AstroAndCharm' => '歡迎來到 AstroAndCharm',
        'What are you looking for?' => '您在找什麼？',
        'Find My Bracelet' => '尋找我的手鏈',
        'Intention · Identity · Signature' => '意念 · 身份 · 簽名',
        'Tarot Reading' => '塔羅占卜',
        'Explore a question' => '探索一個問題',
        'Astrology' => '占星',
        'Your birth chart' => '您的星盤',
        'Orders & Support' => '訂單與支援',
        'Shipping, returns & account questions' => '運送、退貨及帳戶問題',
        'Talk to a real person on our team' => '與團隊真人客服對話',
        'Discover Bracelets' => '探索手鏈',
        'View All' => '查看全部',
        'Ask me anything...' => '隨時問我…',
        'Send' => '傳送',
        'Please do not share passwords or payment card details.' => '請勿分享密碼或付款卡資料。',
        'Your message' => '您的訊息',
        'Which type of bracelet would you like to explore? Our five intention designs and two 12-sign zodiac collections are separate.' => '您想探索哪類手鏈？五款意念設計與兩個十二星座系列分開提供。',
        'Intention Bracelets · 5 themes' => '意念手鏈 · 5 種主題',
        'Zodiac Identity · 12 signs' => '星座身份 · 12 星座',
        'Zodiac Signature · 12 signs' => '星座簽名 · 12 星座',
        'Choose the theme you want your bracelet to represent. I’ll show the matching item from our current store.' => '選擇手鏈代表的主題，我會顯示商店中對應的商品。',
        'Love' => '愛情',
        'Money' => '財富',
        'Luck' => '幸運',
        'Lucky' => '幸運',
        'Protection' => '守護',
        'Healing' => '療癒',
        'Choose another collection' => '選擇其他系列',
        'Which experience would you like to explore? I can guide you to the official pages.' => '您想體驗哪項服務？我可以帶您到官方頁面。',
        'Find a Bracelet' => '尋找手鏈',
        'What do you need help with?' => '需要什麼協助？',
        'Shipping & Delivery' => '運送與配送',
        'Returns & Refunds' => '退貨與退款',
        'Orders & Payments' => '訂單與付款',
        'Checking our site…' => '正在查詢網站…',
        'Please shorten your question.' => '請縮短您的問題。',
        'Sorry, there was a problem. Please try again.' => '抱歉，發生問題，請再試一次。',
        'Customer support is temporarily unavailable.' => '客服暫時無法使用。',
        'Could not reach our team right now.' => '目前無法聯絡團隊。',
        'A team member will join soon.' => '客服人員即將加入。',
        'A team member will reply here within 48 hours.' => '客服人員將在 48 小時內於此回覆。',
        'View details' => '查看詳情',
        'Choose your sign to see the exact bracelet and current price.' => '選擇您的星座，查看對應手鏈與現價。',
        'Zodiac Identity' => '星座身份',
        'Zodiac Signature' => '星座簽名',
        'Intention Bracelets' => '意念手鏈',
        'Explore Tarot' => '探索塔羅',
        'Learn more' => '了解更多',
        'Intention' => '意念',
        'Identity' => '身份',
        'Signature' => '簽名',
        'My Account' => '我的帳戶',
        'Open Tarot Reading' => '開啟塔羅占卜',
        'Explore Astrology' => '探索占星',
        'Browse Bracelets' => '瀏覽手鏈',
        'Find Your Bracelet' => '尋找您的手鏈',
        'View product' => '查看商品',
        'See product page' => '查看商品頁',
        'Aries' => '牡羊座',
        'Taurus' => '金牛座',
        'Gemini' => '雙子座',
        'Cancer' => '巨蟹座',
        'Leo' => '獅子座',
        'Virgo' => '處女座',
        'Libra' => '天秤座',
        'Scorpio' => '天蠍座',
        'Sagittarius' => '射手座',
        'Capricorn' => '摩羯座',
        'Aquarius' => '水瓶座',
        'Pisces' => '雙魚座',
        'Zodiac Identity has one design for each sign.' => '星座身份系列每個星座各有一款設計。',
        'Zodiac Signature has one design for each sign.' => '星座簽名系列每個星座各有一款設計。',
        'Sorry, I could not complete that response.' => '抱歉，無法完成回覆。',
        'Approved reply' => '核准回覆',
        'DeepSeek + site sources' => 'DeepSeek＋網站來源',
        'Verified page guide' => '已驗證頁面導覽',
        'Unverified detail' => '未驗證細節',
        'Local quick guide' => '本機快速導覽',
                'Team queue' => '客服佇列',
        'Translate agent replies to English' => '客服回覆譯成英文',
        'When you type Chinese in Inbox, visitors see English via a built-in CS glossary plus free MyMemory translation (no DeepSeek).' => '在收件匣輸入中文時，訪客會透過內建客服用語表＋免費 MyMemory 翻譯看到英文（不經 DeepSeek）。',
        'Saved hourly cap' => '已儲存的每小時上限',
        'Rate-limit IP source' => '限流 IP 來源',
        'Hourly cap did not verify after save. Check object cache / DB write permissions.' => '儲存後無法驗證每小時上限。請檢查物件快取／資料庫寫入權限。',
        'Sent to visitor' => '已傳送給訪客',
        'Free translation partially unavailable; visitor received glossary-based English (some Chinese may remain).' => '免費翻譯部分無法使用，訪客收到用語表英譯（可能仍含少量中文）。',
        'Translation unavailable; visitor received your original text.' => '翻譯無法使用，訪客收到原文。',
        'Enable it under Settings or Legacy list so new visitor chats appear here.' => '請在設定或舊版列表啟用，新訪客對話才會出現在此。',
        'Visitors start chats from the store widget' => '訪客請從商店前台小工具發起對話。',
        'No organization linked.' => '尚未連結組織。',
        '0 involved. Use WooCommerce orders above.' => '尚無相關項目。請使用上方 WooCommerce 訂單。',
        'Emails and phones captured from chat messages. Searchable for follow-up. No extra PII beyond what visitors typed.' => '從聊天訊息擷取的電郵與電話，可搜尋以便跟進。除訪客輸入內容外不另存個人資料。',
    );


    /** Longer visitor reply: English paragraph, blank line, then 繁體中文. */
    public static function bi( $en, $zh ) {
        $en = trim( (string) $en );
        $zh = trim( (string) $zh );
        if ( $zh === '' ) { return $en; }
        if ( $en === '' ) { return $zh; }
        return $en . "\n\n" . $zh;
    }

    /** Bilingual by English phrase: "English · 繁中" (falls back to the English text). */
    public static function t( $english ) {
        $english = (string) $english;
        return isset( self::$phrases[ $english ] ) ? $english . ' · ' . self::$phrases[ $english ] : $english;
    }

    /** zh-Hant only for a phrase (empty if unknown). */
    public static function zh_phrase( $english ) {
        return isset( self::$phrases[ $english ] ) ? self::$phrases[ $english ] : '';
    }

    /** Phrase map for JS: English => "English · 繁中". */
    public static function js_phrases() {
        $out = array();
        foreach ( self::$phrases as $en => $zh ) { $out[ $en ] = $en . ' · ' . $zh; }
        return $out;
    }

    /** "English · 繁中" */
    public static function label( $key ) {
        if ( ! isset( self::$map[ $key ] ) ) {
            return (string) $key;
        }
        $pair = self::$map[ $key ];
        return $pair[0] . ' · ' . $pair[1];
    }

    /** English only (fallback). */
    public static function en( $key ) {
        return isset( self::$map[ $key ] ) ? self::$map[ $key ][0] : (string) $key;
    }

    /** zh-Hant only. */
    public static function zh( $key ) {
        return isset( self::$map[ $key ] ) ? self::$map[ $key ][1] : (string) $key;
    }

    /** Full map for wp_localize_script (key => bilingual string). */
    public static function js_map( $keys = null ) {
        $out = array();
        $src = self::$map;
        if ( is_array( $keys ) ) {
            $src = array();
            foreach ( $keys as $k ) {
                if ( isset( self::$map[ $k ] ) ) {
                    $src[ $k ] = self::$map[ $k ];
                }
            }
        }
        foreach ( $src as $k => $pair ) {
            $out[ $k ] = $pair[0] . ' · ' . $pair[1];
        }
        return $out;
    }

    /** Escaped HTML label. */
    public static function e( $key ) {
        echo esc_html( self::label( $key ) );
    }
}
