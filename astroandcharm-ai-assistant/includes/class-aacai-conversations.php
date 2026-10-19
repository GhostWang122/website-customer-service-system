<?php
/**
 * AstroAndCharm AI Assistant — admin-only conversation management.
 * Keeps the original aacai_messages table unchanged for seamless upgrades.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AAC_AI_Conversations {
    const SCHEMA_VERSION = '1.8.6';
    const SCHEMA_OPTION = 'aacai_conversation_schema_version';
    const PER_PAGE = 20;
    const MESSAGES_PER_PAGE = 100;
    private static $messages_have_source = null;
    private static $messages_have_original = null;
    private static $messages_have_admin_zh = null;

    private static function sessions_table() {
        global $wpdb;
        return $wpdb->prefix . 'aacai_sessions';
    }

    private static function messages_table() {
        global $wpdb;
        return $wpdb->prefix . 'aacai_messages';
    }

    public static function boot() {
        add_action('init', array(__CLASS__, 'maybe_upgrade'), 2);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 25);
        add_action('admin_post_aacai_manage_conversations', array(__CLASS__, 'manage'));
        add_action('admin_post_aacai_export_conversations', array(__CLASS__, 'export_csv'));
        add_action('admin_post_aacai_update_logging', array(__CLASS__, 'update_logging'));
        add_action('admin_post_aacai_rebuild_sessions', array(__CLASS__, 'rebuild_sessions'));
        add_action('admin_post_aacai_live_claim', array(__CLASS__, 'admin_claim'));
        add_action('admin_post_aacai_live_release', array(__CLASS__, 'admin_release'));
        add_action('admin_post_aacai_live_reply', array(__CLASS__, 'admin_reply'));
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::sessions_table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            session_key char(64) NOT NULL,
            status varchar(16) NOT NULL DEFAULT 'open',
            mode varchar(16) NOT NULL DEFAULT 'bot',
            agent_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            first_source varchar(32) NOT NULL DEFAULT '',
            has_takeover tinyint(1) NOT NULL DEFAULT 0,
            first_message_at datetime NOT NULL,
            last_message_at datetime NOT NULL,
            message_count int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (session_key),
            KEY status_last (status,last_message_at),
            KEY mode_last (mode,last_message_at),
            KEY last_message_at (last_message_at)
        ) {$charset};");
        // Backfill sessions for previously logged V1.0.0 messages, without rewriting messages.
        $messages = self::messages_table();
        $wpdb->query("INSERT IGNORE INTO {$table} (session_key,status,mode,agent_user_id,first_message_at,last_message_at,message_count)
            SELECT session_key,'open','bot',0,MIN(created_at),MAX(created_at),COUNT(*)
            FROM {$messages} GROUP BY session_key");
        // Ensure new columns exist on older installs even if dbDelta skipped them.
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'mode'));
        if (!$col) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN mode varchar(16) NOT NULL DEFAULT 'bot' AFTER status, ADD COLUMN agent_user_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER mode, ADD KEY mode_last (mode,last_message_at)");
        }
        if (class_exists('AACAI_Analytics')) {
            AACAI_Analytics::install();
        }
        if (class_exists('AACAI_Contacts')) {
            AACAI_Contacts::install();
        }
        // V1.7 session CRM / satisfaction columns
        foreach (array(
            'satisfaction' => "ADD COLUMN satisfaction varchar(16) NOT NULL DEFAULT '' AFTER has_takeover",
            'ask_rating' => "ADD COLUMN ask_rating tinyint(1) NOT NULL DEFAULT 0 AFTER satisfaction",
            'tags' => "ADD COLUMN tags varchar(255) NOT NULL DEFAULT '' AFTER ask_rating",
        ) as $col => $ddl) {
            $exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $col));
            if (!$exists) {
                $wpdb->query("ALTER TABLE {$table} {$ddl}");
            }
        }
        self::$messages_have_source = true;
        self::ensure_original_message_column();
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
    }

    /** V1.7.6: store agent-typed Chinese when visitor sees English translation. */
    public static function ensure_original_message_column() {
        global $wpdb;
        $table = self::messages_table();
        $exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'original_message' ) );
        if ( ! $exists ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN original_message text NULL AFTER message" );
        }
        self::$messages_have_original = true;
        self::ensure_admin_zh_tw_column();
    }

    /** V1.8.6: cached EN→zh-TW for admin Inbox bilingual display. */
    public static function ensure_admin_zh_tw_column() {
        global $wpdb;
        $table = self::messages_table();
        $exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'admin_zh_tw' ) );
        if ( ! $exists ) {
            $after = self::messages_have_original() ? 'original_message' : 'message';
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN admin_zh_tw text NULL AFTER {$after}" );
        }
        self::$messages_have_admin_zh = true;
    }

    private static function messages_have_admin_zh() {
        global $wpdb;
        if ( self::$messages_have_admin_zh === null ) {
            self::$messages_have_admin_zh = (bool) $wpdb->get_results(
                $wpdb->prepare( 'SHOW COLUMNS FROM ' . self::messages_table() . ' LIKE %s', 'admin_zh_tw' )
            );
        }
        return (bool) self::$messages_have_admin_zh;
    }

    public static function save_admin_zh_tw( $message_id, $zh ) {
        global $wpdb;
        $message_id = (int) $message_id;
        $zh = aacai_mb_substr_safe( sanitize_textarea_field( (string) $zh ), 0, 4000 );
        if ( $message_id <= 0 || $zh === '' ) { return false; }
        self::ensure_admin_zh_tw_column();
        return false !== $wpdb->update(
            self::messages_table(),
            array( 'admin_zh_tw' => $zh ),
            array( 'id' => $message_id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    private static function messages_have_original() {
        global $wpdb;
        if ( self::$messages_have_original === null ) {
            self::$messages_have_original = (bool) $wpdb->get_results(
                $wpdb->prepare( 'SHOW COLUMNS FROM ' . self::messages_table() . ' LIKE %s', 'original_message' )
            );
        }
        return (bool) self::$messages_have_original;
    }

    public static function maybe_upgrade() {
        if (get_option(self::SCHEMA_OPTION) === self::SCHEMA_VERSION) { return; }
        // On upgrade, dbDelta/migration run once even if the plugin remains activated.
        self::install();
    }

    public static function touch($hashed_session, $at) {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$hashed_session)) { return; }
        $table = self::sessions_table();
        // Never reset live mode (waiting/agent) when new messages arrive.
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (session_key,status,mode,agent_user_id,first_message_at,last_message_at,message_count)
             VALUES (%s,'open','bot',0,%s,%s,1)
             ON DUPLICATE KEY UPDATE
                last_message_at=VALUES(last_message_at),
                message_count=message_count+1,
                status=IF(status='resolved','open',status)",
            $hashed_session, $at, $at
        ));
    }

    public static function hash_raw_session($raw_session) {
        return hash_hmac('sha256', (string)$raw_session, wp_salt('logged_in'));
    }

    public static function normalize_mode($mode) {
        $mode = sanitize_key((string)$mode);
        return in_array($mode, array('bot','waiting','agent'), true) ? $mode : 'bot';
    }

    public static function get_session_row($hashed_session) {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$hashed_session)) { return null; }
        $table = self::sessions_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE session_key=%s", $hashed_session), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function get_mode($hashed_session) {
        $row = self::get_session_row($hashed_session);
        if (!$row) { return 'bot'; }
        return self::normalize_mode($row['mode'] ?? 'bot');
    }

    public static function get_mode_for_raw($raw_session) {
        return self::get_mode(self::hash_raw_session($raw_session));
    }

    /** Insert a message; always persists (used for live agent / forced queue logging). */
    public static function insert_message($hashed_session, $role, $body, $source = '', $original_message = '') {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$hashed_session)) { return 0; }
        $role = sanitize_key((string)$role);
        if (!in_array($role, array('user','assistant','agent','system'), true)) { return 0; }
        $body = aacai_mb_substr_safe(sanitize_textarea_field((string)$body), 0, 4000);
        if ($body === '') { return 0; }
        $original_message = aacai_mb_substr_safe(sanitize_textarea_field((string)$original_message), 0, 4000);
        if ($original_message === $body) { $original_message = ''; }
        if ($source === '' && $role === 'agent') { $source = 'agent'; }
        if ($source === '' && $role === 'system') { $source = 'system'; }
        $source = class_exists('AACAI_Analytics') ? AACAI_Analytics::normalize_source($source) : sanitize_key((string)$source);
        $before = self::get_session_row($hashed_session);
        $is_new = !$before;
        $now = current_time('mysql', true);
        if (self::$messages_have_source === null) {
            self::$messages_have_source = (bool)$wpdb->get_results($wpdb->prepare('SHOW COLUMNS FROM ' . self::messages_table() . ' LIKE %s', 'source'));
        }
        $have_orig = self::messages_have_original();
        $row = array(
            'session_key' => $hashed_session,
            'role' => $role,
            'message' => $body,
            'created_at' => $now,
        );
        $fmt = array('%s','%s','%s','%s');
        if (self::$messages_have_source) {
            $row = array(
                'session_key' => $hashed_session,
                'role' => $role,
                'source' => $source,
                'message' => $body,
                'created_at' => $now,
            );
            $fmt = array('%s','%s','%s','%s','%s');
        }
        if ( $have_orig && $original_message !== '' ) {
            // Insert original after message: rebuild ordered fields for $wpdb->insert.
            $row['original_message'] = $original_message;
            $fmt[] = '%s';
        }
        $ok = $wpdb->insert(self::messages_table(), $row, $fmt);
        if ($ok === false) { return 0; }
        self::touch($hashed_session, $now);
        if (class_exists('AACAI_Analytics')) {
            AACAI_Analytics::on_message_saved($hashed_session, $role, $source, $now, $is_new);
        }
        if (class_exists('AACAI_Contacts')) {
            AACAI_Contacts::maybe_capture_from_message($hashed_session, $role, $body);
            if ( $original_message !== '' ) {
                AACAI_Contacts::maybe_capture_from_message($hashed_session, $role, $original_message);
            }
        }
        // V1.8.8: email store owner on first message of a brand-new session.
        if ( $is_new && $role === 'user' && class_exists( 'AACAI_Email_Alerts' ) ) {
            AACAI_Email_Alerts::notify( 'new_session', $hashed_session, $body );
        }
        return (int)$wpdb->insert_id;
    }

    public static function latest_message_id($hashed_session) {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$hashed_session)) { return 0; }
        $table = self::messages_table();
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(id) FROM {$table} WHERE session_key=%s",
            $hashed_session
        ));
    }

    public static function messages_after($hashed_session, $after_id, $limit = 50) {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$hashed_session)) { return array(); }
        $after_id = max(0, (int)$after_id);
        $limit = max(1, min(100, (int)$limit));
        $table = self::messages_table();
        $cols = 'id, role, message, created_at';
        if ( self::messages_have_original() ) {
            $cols = 'id, role, message, original_message, created_at';
        }
        if ( self::messages_have_admin_zh() ) {
            $cols = self::messages_have_original()
                ? 'id, role, message, original_message, admin_zh_tw, created_at'
                : 'id, role, message, admin_zh_tw, created_at';
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT {$cols} FROM {$table} WHERE session_key=%s AND id>%d ORDER BY id ASC LIMIT %d",
            $hashed_session, $after_id, $limit
        ), ARRAY_A);
        return is_array($rows) ? $rows : array();
    }

    public static function last_message_meta($hashed_session) {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$hashed_session)) { return null; }
        $table = self::messages_table();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, role, created_at FROM {$table} WHERE session_key=%s ORDER BY id DESC LIMIT 1",
            $hashed_session
        ), ARRAY_A);
    }

    public static function set_mode($hashed_session, $mode, $agent_user_id = 0) {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$hashed_session)) { return false; }
        $mode = self::normalize_mode($mode);
        $agent_user_id = max(0, (int)$agent_user_id);
        $table = self::sessions_table();
        $now = current_time('mysql', true);
        // Ensure row exists.
        self::touch($hashed_session, $now);
        $wpdb->update($table, array(
            'mode' => $mode,
            'agent_user_id' => ($mode === 'agent') ? $agent_user_id : 0,
            'status' => 'open',
        ), array('session_key' => $hashed_session), array('%s','%d','%s'), array('%s'));
        return true;
    }

    public static function claim_session($hashed_session, $user_id) {
        $user_id = (int)$user_id;
        if ($user_id <= 0) { return false; }
        $prev = self::get_mode($hashed_session);
        $ok = self::set_mode($hashed_session, 'agent', $user_id);
        if ($ok) {
            self::insert_message($hashed_session, 'system', "You're now chatting with our team. A specialist will continue this conversation here.", 'system');
            if ($prev !== 'agent' && class_exists('AACAI_Analytics')) {
                AACAI_Analytics::record_takeover($hashed_session);
            }
        }
        return $ok;
    }

    public static function release_session($hashed_session) {
        $ok = self::set_mode($hashed_session, 'bot', 0);
        if ($ok) {
            self::insert_message($hashed_session, 'system', "You're back with Customer Assistant. You can keep asking about bracelets, orders, shipping or returns.", 'system');
            self::flag_ask_rating($hashed_session);
        }
        return $ok;
    }

    /** Promote open sessions to Needs follow-up (pending). Does not reopen resolved. */
    public static function flag_needs_followup($hashed_session) {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$hashed_session)) { return false; }
        $table = self::sessions_table();
        $now = current_time('mysql', true);
        self::touch($hashed_session, $now);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status='pending' WHERE session_key=%s AND status='open'",
            $hashed_session
        ));
        if ( class_exists( 'AACAI_Email_Alerts' ) ) {
            $snippet = (string) $wpdb->get_var( $wpdb->prepare(
                'SELECT message FROM ' . self::messages_table() . " WHERE session_key=%s AND role='user' ORDER BY id DESC LIMIT 1",
                $hashed_session
            ) );
            AACAI_Email_Alerts::notify( 'needs_followup', $hashed_session, $snippet );
        }
        return true;
    }

    public static function flag_ask_rating($hashed_session) {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$hashed_session)) { return; }
        $table = self::sessions_table();
        $row = self::get_session_row($hashed_session);
        if (!$row) { return; }
        if (!empty($row['satisfaction'])) { return; } // already rated
        $wpdb->update($table, array('ask_rating' => 1), array('session_key' => $hashed_session), array('%d'), array('%s'));
    }

    public static function resolve_session($hashed_session) {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$hashed_session)) { return false; }
        $table = self::sessions_table();
        self::touch($hashed_session, current_time('mysql', true));
        $wpdb->update($table, array(
            'status' => 'resolved',
            'mode' => 'bot',
            'agent_user_id' => 0,
        ), array('session_key' => $hashed_session), array('%s','%s','%d'), array('%s'));
        self::insert_message($hashed_session, 'system', 'This conversation was marked resolved. You can still ask Customer Assistant anytime.', 'system');
        self::flag_ask_rating($hashed_session);
        return true;
    }

    public static function set_satisfaction($hashed_session, $rating) {
        global $wpdb;
        $rating = sanitize_key((string)$rating);
        if (!in_array($rating, array('positive','negative','neutral'), true)) { return false; }
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$hashed_session)) { return false; }
        $row = self::get_session_row($hashed_session);
        if (!$row) { return false; }
        if (!empty($row['satisfaction'])) { return false; } // once only
        $table = self::sessions_table();
        $wpdb->update($table, array(
            'satisfaction' => $rating,
            'ask_rating' => 0,
        ), array('session_key' => $hashed_session), array('%s','%d'), array('%s'));
        if (class_exists('AACAI_Analytics')) {
            AACAI_Analytics::record_satisfaction($rating);
        }
        return true;
    }

    public static function set_session_tags($hashed_session, $tags) {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$hashed_session)) { return false; }
        $tags = sanitize_text_field((string)$tags);
        $tags = aacai_mb_substr_safe($tags, 0, 255);
        $table = self::sessions_table();
        self::touch($hashed_session, current_time('mysql', true));
        $wpdb->update($table, array('tags' => $tags), array('session_key' => $hashed_session), array('%s'), array('%s'));
        return true;
    }

    public static function request_human($hashed_session) {
        $mode = self::get_mode($hashed_session);
        if ($mode === 'agent') { return 'agent'; }
        self::set_mode($hashed_session, 'waiting', 0);
        if ($mode !== 'waiting') {
            self::insert_message($hashed_session, 'system', 'A team member will reply here within 48 hours.', 'system');
            if ( class_exists( 'AACAI_Email_Alerts' ) ) {
                global $wpdb;
                $snippet = (string) $wpdb->get_var( $wpdb->prepare(
                    'SELECT message FROM ' . self::messages_table() . " WHERE session_key=%s AND role='user' ORDER BY id DESC LIMIT 1",
                    $hashed_session
                ) );
                AACAI_Email_Alerts::notify( 'waiting', $hashed_session, $snippet );
            }
        }
        return 'waiting';
    }

    public static function mode_label($mode) {
        $labels = array('bot'=>'Bot', 'waiting'=>'Waiting for agent', 'agent'=>'Agent active');
        $mode = self::normalize_mode($mode);
        return AACAI_I18n::t(isset($labels[$mode]) ? $labels[$mode] : 'Bot');
    }


    public static function prune_empty_sessions() {
        global $wpdb;
        $sessions = self::sessions_table();
        $messages = self::messages_table();
        // Retention may remove only the oldest part of a conversation: recount remaining messages.
        $wpdb->query("UPDATE {$sessions} s INNER JOIN (
            SELECT session_key, COUNT(*) remaining_count, MIN(created_at) first_at, MAX(created_at) last_at
            FROM {$messages} GROUP BY session_key
        ) x ON x.session_key=s.session_key
        SET s.message_count=x.remaining_count, s.first_message_at=x.first_at, s.last_message_at=x.last_at");
        $wpdb->query("DELETE s FROM {$sessions} s LEFT JOIN {$messages} m ON m.session_key=s.session_key WHERE m.id IS NULL");
    }

    private static function log_access_setting() {
        $settings = (array) get_option(AAC_AI_Assistant::OPTION, array());
        return isset($settings['log_access']) && $settings['log_access'] === 'shop_managers' ? 'shop_managers' : 'admins';
    }

    public static function can_view() {
        if (current_user_can('manage_options')) { return true; }
        return self::log_access_setting() === 'shop_managers' && current_user_can('manage_woocommerce');
    }

    public static function admin_menu() {
        add_submenu_page('aacai', 'Conversations', 'Conversations', 'manage_options', 'aacai-conversations', array(__CLASS__, 'page'));
        if (self::log_access_setting() === 'shop_managers') {
            add_submenu_page('woocommerce', 'Customer Conversations', 'Customer Conversations', 'manage_woocommerce', 'aacai-team-conversations', array(__CLASS__, 'page'));
        }
    }

    private static function page_slug() {
        return current_user_can('manage_options') ? 'aacai-conversations' : 'aacai-team-conversations';
    }

    private static function url($args = array()) {
        return add_query_arg(array_merge(array('page'=>self::page_slug()), $args), admin_url('admin.php'));
    }

    private static function sanitize_session($session) {
        $session = strtolower(sanitize_text_field((string)$session));
        return preg_match('/^[a-f0-9]{64}$/', $session) ? $session : '';
    }

    private static function filters($input) {
        $status = isset($input['status']) ? sanitize_key(wp_unslash($input['status'])) : '';
        $status = in_array($status, array('open','pending','resolved'), true) ? $status : '';
        $mode = isset($input['mode']) ? sanitize_key(wp_unslash($input['mode'])) : '';
        $mode = in_array($mode, array('bot','waiting','agent'), true) ? $mode : '';
        $search = isset($input['s']) ? sanitize_text_field(wp_unslash($input['s'])) : '';
        $search = aacai_mb_substr_safe(trim($search), 0, 120);
        return array('status'=>$status, 'mode'=>$mode, 's'=>$search);
    }

    private static function where_sql($filters, $session = '') {
        global $wpdb;
        $args = array();
        $parts = array('1=1');
        $messages = self::messages_table();
        if ($session) {
            $parts[] = 's.session_key=%s';
            $args[] = $session;
        }
        if ($filters['status']) {
            $parts[] = 's.status=%s';
            $args[] = $filters['status'];
        }
        if (!empty($filters['mode'])) {
            $parts[] = 's.mode=%s';
            $args[] = $filters['mode'];
        }
        if ($filters['s']) {
            $like = '%' . $wpdb->esc_like($filters['s']) . '%';
            $parts[] = "(s.session_key LIKE %s OR EXISTS (SELECT 1 FROM {$messages} lookup_msg WHERE lookup_msg.session_key=s.session_key AND lookup_msg.message LIKE %s))";
            $args[] = $like;
            $args[] = $like;
        }
        return array('sql'=>implode(' AND ', $parts), 'args'=>$args);
    }

    private static function query($sql, $params, $mode = 'results') {
        global $wpdb;
        $prepared = $params ? $wpdb->prepare($sql, $params) : $sql;
        if ($mode === 'var') { return $wpdb->get_var($prepared); }
        return $wpdb->get_results($prepared, ARRAY_A);
    }

    private static function label($status) {
        $labels = array('open'=>'New', 'pending'=>'Needs follow-up', 'resolved'=>'Resolved');
        return isset($labels[$status]) ? $labels[$status] : 'New';
    }

    private static function admin_style() {
        echo '<style>
        .aacai-convo-admin .aacai-card{background:#fff;border:1px solid #dcdcde;border-radius:9px;padding:18px;margin:15px 0;max-width:1250px}
        .aacai-convo-admin .aacai-tools{display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin:16px 0}
        .aacai-convo-admin .aacai-tools input[type=search]{width:min(340px,100%)}
        .aacai-convo-admin .aacai-tag{display:inline-block;padding:3px 9px;background:#f2f2f2;border-radius:20px;font-size:12px;font-weight:600}
        .aacai-convo-admin .aacai-tag-pending{background:#fff1da;color:#785100}
        .aacai-convo-admin .aacai-tag-resolved{background:#e8f5ed;color:#236b3b}
        .aacai-convo-admin .aacai-preview{max-width:390px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#50575e}
        .aacai-convo-admin .aacai-muted{color:#646970;font-size:12px}
        .aacai-convo-admin .aacai-messages{max-width:960px;margin:20px 0}
        .aacai-convo-admin .aacai-message{padding:13px 16px;margin:11px 0;border-radius:10px;white-space:pre-wrap;overflow-wrap:anywhere;border:1px solid #e1e3e5;background:#fff;line-height:1.6}
        .aacai-convo-admin .aacai-message.user{margin-right:13%;background:#f5f2ed;border-color:#e8dfd4}
        .aacai-convo-admin .aacai-message.assistant{margin-left:7%}
        .aacai-convo-admin .aacai-message-meta{display:flex;gap:12px;align-items:center;margin-bottom:6px;color:#646970;font-size:12px}
        .aacai-convo-admin .aacai-convo-td-actions{white-space:nowrap}
        .aacai-convo-admin .aacai-pagination{display:flex;align-items:center;gap:8px;margin:16px 0;flex-wrap:wrap}
        .aacai-convo-admin table.widefat td{vertical-align:middle}
        .aacai-convo-admin .aacai-danger{color:#b32d2e}
        .aacai-convo-admin .aacai-tag-waiting{background:#fff4ce;color:#8a6d00}
        .aacai-convo-admin .aacai-tag-agent{background:#dceeff;color:#0b5cad}
        .aacai-convo-admin .aacai-tag-bot{background:#f0f0f1;color:#50575e}
        .aacai-convo-admin .aacai-tag-unanswered{background:#f8d7da;color:#8a1f2a}
        .aacai-convo-admin .aacai-message.agent{margin-left:7%;background:#eef6ff;border-color:#c5dbf2}
        .aacai-convo-admin .aacai-message.system{margin-left:7%;background:#f7f7f7;border-style:dashed;color:#50575e}
        .aacai-convo-admin .aacai-live-reply{display:flex;flex-direction:column;gap:8px;max-width:960px;margin:12px 0 24px}
        .aacai-convo-admin .aacai-live-reply textarea{width:100%;min-height:90px}
        .aacai-convo-admin .aacai-live-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:10px 0}
        .aacai-convo-admin tr.aacai-row-waiting{background:#fffbeb}
        .aacai-convo-admin tr.aacai-row-agent{background:#f3f8ff}
        @media(max-width:782px){.aacai-convo-admin .aacai-message.user,.aacai-convo-admin .aacai-message.assistant,.aacai-convo-admin .aacai-message.agent{margin-left:0;margin-right:0}.aacai-convo-admin .aacai-table-wrap{overflow-x:auto}}
        </style>';
    }

    private static function admin_top($desc) {
        echo '<div class="wrap aacai-convo-admin"><h1>Customer Assistant — Conversations</h1><p>'.esc_html($desc).'</p>';
        self::admin_style();
        if (!empty($_GET['notice'])) {
            $notices = array(
                'updated'=>'Conversation status updated.',
                'deleted'=>'Selected conversations permanently deleted.',
                'nothing'=>'No valid conversations were selected.',
                'claimed'=>'You took over this conversation. Auto-replies are paused.',
                'released'=>'Returned to bot. Customer Assistant automation is active again.',
                'replied'=>'Reply sent to the visitor.',
                'reply_empty'=>'Please enter a reply before sending.',
            );
            $key = sanitize_key(wp_unslash($_GET['notice']));
            if (isset($notices[$key])) {
                echo '<div class="notice notice-info is-dismissible"><p>'.esc_html($notices[$key]).'</p></div>';
            }
        }
    }

    private static function pagination($page, $pages, $filters, $session = '') {
        if ($pages <= 1) { return; }
        $extra = array();
        if ( ! empty( $_GET['legacy'] ) ) {
            $extra['legacy'] = '1';
        }
        if ( $session ) {
            $extra['session'] = $session;
        }
        echo '<nav class="aacai-pagination" aria-label="Pagination">';
        if ($page > 1) {
            echo '<a class="button" href="'.esc_url(self::url(array_merge($filters, array('paged'=>$page-1), $extra))).'">&larr; Previous</a>';
        }
        echo '<span>Page '.esc_html($page).' of '.esc_html($pages).'</span>';
        if ($page < $pages) {
            echo '<a class="button" href="'.esc_url(self::url(array_merge($filters, array('paged'=>$page+1), $extra))).'">Next &rarr;</a>';
        }
        echo '</nav>';
    }

    private static function action_nonce() {
        wp_nonce_field('aacai_manage_conversations');
    }

    private static function status_buttons($session, $current) {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-flex;gap:7px;flex-wrap:wrap;align-items:center;margin:0 8px 0 0">';
        echo '<input type="hidden" name="action" value="aacai_manage_conversations">';
        echo '<input type="hidden" name="session" value="'.esc_attr($session).'">';
        self::action_nonce();
        echo '<select name="conversation_action" aria-label="Update conversation status">';
        foreach(array('open'=>'Mark new','pending'=>'Needs follow-up','resolved'=>'Resolved') as $status=>$title) {
            echo '<option value="'.esc_attr($status).'" '.selected($current,$status,false).'>'.esc_html($title).'</option>';
        }
        echo '</select> <button type="submit" class="button button-secondary">Update</button></form>';
    }

    public static function page() {
        if (!self::can_view()) { wp_die('You do not have permission to view customer conversations.'); }
        $session = isset($_GET['session']) ? self::sanitize_session(wp_unslash($_GET['session'])) : '';
        $legacy = ! empty( $_GET['legacy'] );
        // Phase A (V1.6): split-view Live Inbox is primary. ?legacy=1 keeps list/detail pages.
        if ( ! $legacy && class_exists( 'AAC_AI_Inbox' ) ) {
            AAC_AI_Inbox::render( $session );
            return;
        }
        if ($session) { self::detail($session); }
        else { self::listing(); }
    }

    private static function listing() {
        global $wpdb;
        // IMPORTANT: read the same persisted option used by the chat REST handler.
        // v1.1.0 accidentally referenced an undefined $s in this method, causing
        // 'Chat logging is OFF' to be shown even when recording was enabled.
        $s = (array) get_option(AAC_AI_Assistant::OPTION, array());
        $logging_enabled = !empty($s['logging']);
        $filters = self::filters($_GET);
        $paged = min(100000, max(1, absint($_GET['paged'] ?? 1)));
        $sessions = self::sessions_table();
        $messages = self::messages_table();
        $w = self::where_sql($filters);
        $total = (int) self::query("SELECT COUNT(*) FROM {$sessions} s WHERE {$w['sql']}", $w['args'], 'var');
        $pages = max(1, (int)ceil($total / self::PER_PAGE));
        $paged = min($paged, $pages);
        $params = array_merge($w['args'], array(self::PER_PAGE, ($paged-1)*self::PER_PAGE));
        $rows = self::query("SELECT s.* FROM {$sessions} s WHERE {$w['sql']} ORDER BY s.last_message_at DESC, s.session_key ASC LIMIT %d OFFSET %d", $params);
        $previews = array();
        $last_roles = array();
        if ($rows) {
            $keys = array_column($rows, 'session_key');
            $holders = implode(',', array_fill(0, count($keys), '%s'));
            $preview_rows = self::query("SELECT m.session_key, m.message FROM {$messages} m
                INNER JOIN (SELECT session_key, MAX(id) newest FROM {$messages} WHERE role='user' AND session_key IN ({$holders}) GROUP BY session_key) latest
                ON latest.newest=m.id", $keys);
            foreach($preview_rows as $r) { $previews[$r['session_key']] = $r['message']; }
            $last_rows = self::query("SELECT m.session_key, m.role FROM {$messages} m
                INNER JOIN (SELECT session_key, MAX(id) newest FROM {$messages} WHERE session_key IN ({$holders}) GROUP BY session_key) latest
                ON latest.newest=m.id", $keys);
            foreach($last_rows as $r) { $last_roles[$r['session_key']] = $r['role']; }
        }
        self::admin_top('Browse anonymized visitor sessions, search chat content, and update follow-up status. Times are UTC.');
        echo '<p><a class="button button-primary" href="'.esc_url(self::url()).'">Open Live Inbox workbench</a> <span class="aacai-muted">Legacy list view (export / bulk delete / rebuild). Conversations open in the workbench by default.</span></p>';
        if (isset($_GET['logging_notice'])) {
            $logging_notice = sanitize_key(wp_unslash($_GET['logging_notice']));
            if ($logging_notice === 'saved') {
                echo '<div class="notice notice-success inline aacai-keep-notice"><p>Chat recording preference saved and verified.</p></div>';
            } elseif ($logging_notice === 'failed') {
                echo '<div class="notice notice-error inline aacai-keep-notice"><p>Chat recording could not be verified after saving. Check database or object-cache configuration.</p></div>';
            } elseif ($logging_notice === 'reindexed') {
                echo '<div class="notice notice-success inline aacai-keep-notice"><p>Conversation index rebuild completed. Existing saved messages were preserved.</p></div>';
            }
        }
        if ($logging_enabled) {
            echo '<div class="notice notice-info inline aacai-keep-notice"><p><strong>Chat logging is ON.</strong> New visitor and assistant messages are recorded according to the retention setting. Earlier unsaved conversations cannot be recovered.</p></div>';
        } else {
            echo '<div class="notice notice-warning inline aacai-keep-notice"><p><strong>Chat logging is OFF.</strong> Only conversations saved while logging was enabled are available. Enable logging after updating the Privacy Policy. Earlier unsaved chats cannot be recovered.</p></div>';
        }
        if (current_user_can('manage_options')) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;gap:10px;align-items:center;margin:14px 0;flex-wrap:wrap">';
            echo '<input type="hidden" name="action" value="aacai_update_logging">';
            echo '<input type="hidden" name="enabled" value="'.($logging_enabled?'0':'1').'">';
            wp_nonce_field('aacai_update_logging');
            echo '<button type="submit" class="button '.($logging_enabled?'':'button-primary').'">'.($logging_enabled?'Disable chat recording':'Enable chat recording').'</button>';
            echo '<span class="aacai-muted">Current setting: <strong>'.($logging_enabled?'ON':'OFF').'</strong>. This updates the same setting used by the chat API. <a href="'.esc_url(admin_url('admin.php?page=aacai')).'">All settings</a></span>';
            echo '</form>';
        }
        // Read-only storage diagnostics: a displayed zero can mean no saved messages
        // or an out-of-date session index. Never guess which without checking both.
        if ($logging_enabled && !$filters['status'] && !$filters['s'] && $total === 0 && current_user_can('manage_options')) {
            $table_messages = self::messages_table();
            $table_sessions = self::sessions_table();
            $messages_exist = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_messages))) === $table_messages);
            $sessions_exist = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_sessions))) === $table_sessions);
            if (!$messages_exist || !$sessions_exist) {
                echo '<div class="notice notice-error inline aacai-keep-notice"><p><strong>Recording storage check:</strong> One or more chat database tables are missing. Please back up your database and reactivate the plugin to create the required tables.</p></div>';
            } else {
                $saved_messages = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table_messages}");
                $saved_sessions = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table_sessions}");
                if ($saved_messages > 0 && $saved_sessions === 0) {
                    echo '<div class="notice notice-warning inline aacai-keep-notice"><p><strong>Recording storage check:</strong> '.esc_html(number_format_i18n($saved_messages)).' saved messages exist, but the session index is empty.</p></div>';
                    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
                    echo '<input type="hidden" name="action" value="aacai_rebuild_sessions">';
                    wp_nonce_field('aacai_rebuild_sessions');
                    echo '<button class="button button-primary" type="submit">Rebuild conversation index (preserve messages)</button></form>';
                } elseif ($saved_messages === 0) {
                    echo '<p class="aacai-muted"><strong>Recording storage check:</strong> Database tables exist; no messages have been saved yet. Send a new test message from the public-facing chat widget while recording is ON, then reload this page.</p>';
                }
            }
        }
        echo '<form method="get" action="'.esc_url(admin_url('admin.php')).'" class="aacai-tools">';
        echo '<input type="hidden" name="page" value="'.esc_attr(self::page_slug()).'">';
        echo '<input type="hidden" name="legacy" value="1">';
        echo '<label for="aacai-filter-status" class="screen-reader-text">Status</label><select id="aacai-filter-status" name="status"><option value="">All statuses</option>';
        foreach(array('open'=>'New','pending'=>'Needs follow-up','resolved'=>'Resolved') as $status=>$label) {
            echo '<option value="'.esc_attr($status).'" '.selected($filters['status'],$status,false).'>'.esc_html($label).'</option>';
        }
        echo '</select>';
        echo '<label for="aacai-filter-mode" class="screen-reader-text">Live mode</label><select id="aacai-filter-mode" name="mode"><option value="">All live modes</option>';
        foreach(array('waiting'=>'Waiting for agent','agent'=>'Agent active','bot'=>'Bot only') as $mode=>$label) {
            echo '<option value="'.esc_attr($mode).'" '.selected($filters['mode']??'',$mode,false).'>'.esc_html($label).'</option>';
        }
        echo '</select>';
        echo '<label for="aacai-search" class="screen-reader-text">Search messages</label><input type="search" id="aacai-search" name="s" placeholder="Search messages or session ID" value="'.esc_attr($filters['s']).'">';
        echo '<button class="button button-primary" type="submit">Search</button>';
        echo '<a class="button" href="'.esc_url(self::url(array('legacy'=>'1'))).'">Reset</a>';
        if (current_user_can('manage_options')) {
            $export_url = add_query_arg(array_merge(array('action'=>'aacai_export_conversations','_wpnonce'=>wp_create_nonce('aacai_export_conversations')),$filters),admin_url('admin-post.php'));
            echo '<a class="button" href="'.esc_url($export_url).'">Export filtered CSV</a>';
        }
        echo '</form><p><strong>'.esc_html(number_format_i18n($total)).'</strong> conversation(s) matched.</p>';
        echo '<div class="aacai-card"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return this.querySelector(\'[name=conversation_action]\').value !== \'delete\' || confirm(\'Permanently delete the selected conversations and all messages? This cannot be undone.\');">';
        echo '<input type="hidden" name="action" value="aacai_manage_conversations">';
        self::action_nonce();
        echo '<div class="aacai-table-wrap"><table class="widefat striped"><thead><tr><th><input type="checkbox" aria-label="Select all visible sessions" onclick="this.closest(\'table\').querySelectorAll(\'input[name=&quot;sessions[]&quot;]\').forEach(e=>e.checked=this.checked)"></th><th>Conversation</th><th>Last user message</th><th>Live</th><th>Status</th><th>Messages</th><th>First / last activity (UTC)</th></tr></thead><tbody>';
        foreach($rows as $r) {
            $id = $r['session_key'];
            $view_url = self::url(array('session'=>$id));
            $preview = isset($previews[$id]) ? wp_trim_words($previews[$id],16,'…') : '—';
            $mode = self::normalize_mode($r['mode'] ?? 'bot');
            $last_role = $last_roles[$id] ?? '';
            $needs_reply = in_array($mode, array('waiting','agent'), true) && $last_role === 'user';
            $row_class = $mode === 'waiting' ? 'aacai-row-waiting' : ($mode === 'agent' ? 'aacai-row-agent' : '');
            echo '<tr class="'.esc_attr($row_class).'"><td><input type="checkbox" name="sessions[]" value="'.esc_attr($id).'" aria-label="Select conversation '.esc_attr(substr($id,0,10)).'"></td>';
            echo '<td><a href="'.esc_url($view_url).'"><strong>Visitor '.esc_html(substr($id,0,10)).'</strong></a><div class="aacai-muted">Anonymous session</div></td>';
            echo '<td><div class="aacai-preview" title="'.esc_attr($preview).'">'.esc_html($preview).'</div></td>';
            echo '<td><span class="aacai-tag aacai-tag-'.esc_attr($mode).'">'.esc_html(self::mode_label($mode)).'</span>';
            if ($needs_reply) echo ' <span class="aacai-tag aacai-tag-unanswered">Needs reply</span>';
            echo '</td>';
            echo '<td><span class="aacai-tag aacai-tag-'.esc_attr($r['status']).'">'.esc_html(self::label($r['status'])).'</span></td>';
            echo '<td>'.esc_html((int)$r['message_count']).'</td>';
            echo '<td><span class="aacai-muted">'.esc_html($r['first_message_at']).'</span><div>'.esc_html($r['last_message_at']).'</div></td></tr>';
        }
        if (!$rows) { echo '<tr><td colspan="7">No saved conversations match this filter.</td></tr>'; }
        echo '</tbody></table></div>';
        if ($rows) {
            echo '<div class="aacai-tools"><label for="aacai-bulk">Selected:</label><select id="aacai-bulk" name="conversation_action">';
            foreach(array('pending'=>'Mark needs follow-up','resolved'=>'Mark resolved','open'=>'Mark new') as $value=>$label) {
                echo '<option value="'.esc_attr($value).'">'.esc_html($label).'</option>';
            }
            if(current_user_can('manage_options')) { echo '<option value="delete">Delete permanently</option>'; }
            echo '</select><button type="submit" class="button">Apply</button></div>';
        }
        echo '</form>';
        self::pagination($paged,$pages,$filters);
        echo '</div><p class="aacai-muted">Only logged conversations appear here. Sessions are identified by pseudonymous hashes; the plugin does not infer a real customer name or identity.</p></div>';
    }

    private static function detail($session) {
        $sessions = self::sessions_table();
        $messages = self::messages_table();
        $row = self::query("SELECT * FROM {$sessions} WHERE session_key=%s",array($session));
        if (!$row) { wp_die('Conversation not found or already deleted.'); }
        $row = $row[0];
        $mode = self::normalize_mode($row['mode'] ?? 'bot');
        $total = (int)self::query("SELECT COUNT(*) FROM {$messages} WHERE session_key=%s",array($session),'var');
        $pages = max(1,(int)ceil($total / self::MESSAGES_PER_PAGE));
        $paged = min($pages,max(1,absint($_GET['paged'] ?? 1)));
        $rows = self::query("SELECT id,role,message,created_at FROM {$messages} WHERE session_key=%s ORDER BY id ASC LIMIT %d OFFSET %d",array($session,self::MESSAGES_PER_PAGE,($paged-1)*self::MESSAGES_PER_PAGE));
        $last_id = 0;
        foreach ($rows as $mm) { $last_id = max($last_id, (int)$mm['id']); }
        if (!$last_id) {
            $last_id = (int) self::query("SELECT MAX(id) FROM {$messages} WHERE session_key=%s", array($session), 'var');
        }
        self::admin_top('Anonymous visitor session · UTC times · ' . $total . ' saved messages · live mode: ' . self::mode_label($mode));
        echo '<p><a href="'.esc_url(self::url(array('legacy'=>'1'))).'">&larr; Back to legacy list</a> · <a href="'.esc_url(self::url(array('session'=>$session))).'">Open in Live Inbox</a></p>';
        echo '<div class="aacai-card"><h2>Visitor '.esc_html(substr($session,0,12)).'</h2>';
        echo '<p><code>'.esc_html($session).'</code></p>';
        echo '<div class="aacai-tools"><span class="aacai-tag aacai-tag-'.esc_attr($row['status']).'">'.esc_html(self::label($row['status'])).'</span>';
        echo '<span class="aacai-tag aacai-tag-'.esc_attr($mode).'">'.esc_html(self::mode_label($mode)).'</span>';
        if (!empty($row['agent_user_id'])) {
            $u = get_userdata((int)$row['agent_user_id']);
            if ($u) echo '<span class="aacai-muted">Agent: '.esc_html($u->display_name).'</span>';
        }
        self::status_buttons($session,$row['status']);
        echo '</div><div class="aacai-live-bar">';
        if ($mode !== 'agent') {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline">';
            echo '<input type="hidden" name="action" value="aacai_live_claim"><input type="hidden" name="session" value="'.esc_attr($session).'">';
            wp_nonce_field('aacai_live_claim');
            echo '<button type="submit" class="button button-primary">Take over chat</button></form>';
        } else {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline">';
            echo '<input type="hidden" name="action" value="aacai_live_release"><input type="hidden" name="session" value="'.esc_attr($session).'">';
            wp_nonce_field('aacai_live_release');
            echo '<button type="submit" class="button">Return to bot</button></form>';
        }
        if (current_user_can('manage_options')) {
            $export = add_query_arg(array('action'=>'aacai_export_conversations','session'=>$session,'_wpnonce'=>wp_create_nonce('aacai_export_conversations')),admin_url('admin-post.php'));
            echo '<a href="'.esc_url($export).'" class="button">Export conversation CSV</a>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline" onsubmit="return confirm(\'Permanently delete this conversation and all of its messages?\');">';
            echo '<input type="hidden" name="action" value="aacai_manage_conversations"><input type="hidden" name="conversation_action" value="delete"><input type="hidden" name="session" value="'.esc_attr($session).'">';
            self::action_nonce();
            echo '<button type="submit" class="button aacai-danger">Delete conversation</button></form>';
        }
        echo '</div><div class="aacai-muted">First: '.esc_html($row['first_message_at']).' UTC · Last: '.esc_html($row['last_message_at']).' UTC</div>';
        echo '<p class="aacai-muted">While <strong>Agent active</strong>, the visitor widget pauses all bot/AI auto-replies. New visitor messages appear below (auto-refresh ~2.5s).</p></div>';
        echo '<div class="aacai-messages" id="aacai-live-messages" data-last-id="'.esc_attr($last_id).'" data-session="'.esc_attr($session).'" data-endpoint="'.esc_attr(rest_url('aacai/v1/live/admin-poll')).'" data-nonce="'.esc_attr(wp_create_nonce('wp_rest')).'">';
        foreach($rows as $m) {
            $role = in_array($m['role'], array('user','assistant','agent','system'), true) ? $m['role'] : 'assistant';
            $names = array('user'=>'Visitor','assistant'=>'Customer Assistant','agent'=>'Team agent','system'=>'Notice');
            $label = isset($names[$role]) ? $names[$role] : 'Customer Assistant';
            echo '<div class="aacai-message '.esc_attr($role).'" data-id="'.esc_attr($m['id']).'"><div class="aacai-message-meta"><strong>'.esc_html($label).'</strong><time>'.esc_html($m['created_at']).' UTC</time></div>'.esc_html($m['message']).'</div>';
        }
        if (!$rows) { echo '<p class="aacai-empty">No messages.</p>'; }
        echo '</div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="aacai-live-reply">';
        echo '<input type="hidden" name="action" value="aacai_live_reply"><input type="hidden" name="session" value="'.esc_attr($session).'">';
        wp_nonce_field('aacai_live_reply');
        echo '<label for="aacai-agent-reply"><strong>Reply as team agent</strong></label>';
        echo '<textarea id="aacai-agent-reply" name="message" maxlength="4000" placeholder="Write a reply to the visitor…" required></textarea>';
        echo '<p><button type="submit" class="button button-primary">Send reply</button> <span class="aacai-muted">Sending also claims the chat if it is not already agent-owned.</span></p>';
        echo '</form>';
        self::pagination($paged,$pages,array(),$session);
        echo '<script>(function(){var box=document.getElementById("aacai-live-messages");if(!box)return;var last=parseInt(box.getAttribute("data-last-id")||"0",10)||0;var session=box.getAttribute("data-session");var endpoint=box.getAttribute("data-endpoint");var nonce=box.getAttribute("data-nonce");var labels={user:"Visitor",assistant:"Customer Assistant",agent:"Team agent",system:"Notice"};function add(m){if(!m||!m.id)return;if(box.querySelector("[data-id=\""+m.id+"\"]"))return;var empty=box.querySelector(".aacai-empty");if(empty)empty.remove();var d=document.createElement("div");d.className="aacai-message "+(m.role||"assistant");d.setAttribute("data-id",String(m.id));var head=document.createElement("div");head.className="aacai-message-meta";var strong=document.createElement("strong");strong.textContent=labels[m.role]||m.role;var time=document.createElement("time");time.textContent=(m.created_at||"")+" UTC";head.appendChild(strong);head.appendChild(time);d.appendChild(head);d.appendChild(document.createTextNode(m.message||""));box.appendChild(d);last=Math.max(last,parseInt(m.id,10)||0);box.setAttribute("data-last-id",String(last));}function tick(){var u=endpoint+(endpoint.indexOf("?")>=0?"&":"?")+"session="+encodeURIComponent(session)+"&after_id="+last;fetch(u,{credentials:"same-origin",headers:{"X-WP-Nonce":nonce}}).then(function(r){return r.ok?r.json():null}).then(function(data){if(!data||!data.messages)return;(data.messages||[]).forEach(add)}).catch(function(){})}setInterval(tick,2500)})();</script>';
        echo '<p class="aacai-muted">Pages are shown in chronological order; use the CSV export to retrieve every saved message in this session.</p></div>';
    }

    /** Recreate any missing session index entries; never remove saved messages. */
    public static function rebuild_sessions() {
        if (!current_user_can('manage_options')) { wp_die('Only administrators can rebuild the index.'); }
        check_admin_referer('aacai_rebuild_sessions');
        self::install();
        self::prune_empty_sessions();
        wp_safe_redirect(self::url(array('logging_notice'=>'reindexed')));
        exit;
    }

    /** A single-purpose logging switch, independent of the long Settings form. */
    public static function update_logging() {
        if (!current_user_can('manage_options')) { wp_die('Only administrators can change chat recording.'); }
        check_admin_referer('aacai_update_logging');
        $value = isset($_POST['enabled']) ? sanitize_text_field(wp_unslash($_POST['enabled'])) : '';
        if (!in_array($value, array('0', '1'), true)) { wp_die('Invalid recording setting.'); }
        $settings = (array) get_option(AAC_AI_Assistant::OPTION, array());
        $settings['logging'] = (int)$value;
        update_option(AAC_AI_Assistant::OPTION, $settings, false);
        $saved = (array) get_option(AAC_AI_Assistant::OPTION, array());
        $ok = isset($saved['logging']) && (int)$saved['logging'] === (int)$value;
        wp_safe_redirect(self::url(array('logging_notice'=> $ok ? 'saved' : 'failed')));
        exit;
    }

    public static function manage() {
        if (!self::can_view()) { wp_die('Unauthorized'); }
        check_admin_referer('aacai_manage_conversations');
        $action = isset($_POST['conversation_action']) ? sanitize_key(wp_unslash($_POST['conversation_action'])) : '';
        $raw_sessions = array();
        if (isset($_POST['sessions']) && is_array($_POST['sessions'])) { $raw_sessions = wp_unslash($_POST['sessions']); }
        elseif (isset($_POST['session'])) { $raw_sessions = array(wp_unslash($_POST['session'])); }
        $sessions = array_values(array_unique(array_filter(array_map(array(__CLASS__,'sanitize_session'),array_slice($raw_sessions,0,100)))));
        if (!$sessions) {
            wp_safe_redirect(self::url(array('notice'=>'nothing')));
            exit;
        }
        if (!in_array($action, array('open','pending','resolved','delete'),true)) { wp_die('Invalid conversation action.'); }
        if ($action === 'delete' && !current_user_can('manage_options')) { wp_die('Only administrators can delete conversations.'); }
        global $wpdb;
        $table = self::sessions_table();
        $messages = self::messages_table();
        $placeholders = implode(',',array_fill(0,count($sessions),'%s'));
        if ($action === 'delete') {
            $wpdb->query('START TRANSACTION');
            $a = $wpdb->query($wpdb->prepare("DELETE FROM {$messages} WHERE session_key IN ({$placeholders})",$sessions));
            $b = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE session_key IN ({$placeholders})",$sessions));
            if ($a === false || $b === false) { $wpdb->query('ROLLBACK'); wp_die('The database could not delete these conversations.'); }
            $wpdb->query('COMMIT');
            $notice = 'deleted';
        } else {
            $args = array_merge(array($action),$sessions);
            $wpdb->query($wpdb->prepare("UPDATE {$table} SET status=%s WHERE session_key IN ({$placeholders})",$args));
            $notice = 'updated';
        }
        wp_safe_redirect(self::url(array('notice'=>$notice)));
        exit;
    }

    private static function csv_cell($value) {
        $text = (string)$value;
        // Prevent spreadsheet formula execution if a visitor begins a message with =,+,-,@ or whitespace + formula.
        if (preg_match('/^[\x00-\x20]*[=+@-]/u', $text)) { $text = "'" . $text; }
        return $text;
    }

    public static function export_csv() {
        if (!current_user_can('manage_options')) { wp_die('Only administrators can export chat data.'); }
        check_admin_referer('aacai_export_conversations');
        $filters = self::filters($_GET);
        $session = isset($_GET['session']) ? self::sanitize_session(wp_unslash($_GET['session'])) : '';
        if (isset($_GET['session']) && !$session) { wp_die('Invalid session.'); }
        $where = self::where_sql($filters,$session);
        $messages = self::messages_table();
        $sessions = self::sessions_table();
        $filename = 'astroandcharm-conversations-' . gmdate('Ymd-His') . '.csv';
        while (ob_get_level() > 0) { ob_end_clean(); }
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        $out = fopen('php://output', 'w');
        if (!$out) { exit; }
        fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,array('Session (pseudonymous hash)','Follow-up status','UTC time','Role','Message'), ',', '"', '');
        $last_id = 0;
        do {
            $args = array_merge($where['args'], array($last_id,500));
            $rows = self::query("SELECT m.id,m.session_key,s.status,m.created_at,m.role,m.message
                FROM {$messages} m INNER JOIN {$sessions} s ON s.session_key=m.session_key
                WHERE {$where['sql']} AND m.id>%d ORDER BY m.id ASC LIMIT %d",$args);
            foreach($rows as $r) {
                fputcsv($out,array_map(array(__CLASS__,'csv_cell'),array($r['session_key'],$r['status'],$r['created_at'],$r['role'],$r['message'])), ',', '"', '');
                $last_id = (int)$r['id'];
            }
        } while (count($rows) === 500);
        fclose($out);
        exit;
    }
    public static function admin_claim() {
        if (!self::can_view()) { wp_die('Unauthorized'); }
        check_admin_referer('aacai_live_claim');
        $session = self::sanitize_session(isset($_POST['session']) ? wp_unslash($_POST['session']) : '');
        if (!$session) { wp_die('Invalid session.'); }
        self::claim_session($session, get_current_user_id());
        wp_safe_redirect(self::url(array('session'=>$session,'notice'=>'claimed')));
        exit;
    }

    public static function admin_release() {
        if (!self::can_view()) { wp_die('Unauthorized'); }
        check_admin_referer('aacai_live_release');
        $session = self::sanitize_session(isset($_POST['session']) ? wp_unslash($_POST['session']) : '');
        if (!$session) { wp_die('Invalid session.'); }
        self::release_session($session);
        wp_safe_redirect(self::url(array('session'=>$session,'notice'=>'released')));
        exit;
    }

    public static function admin_reply() {
        if (!self::can_view()) { wp_die('Unauthorized'); }
        check_admin_referer('aacai_live_reply');
        $session = self::sanitize_session(isset($_POST['session']) ? wp_unslash($_POST['session']) : '');
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $message = aacai_mb_substr_safe(trim($message), 0, 4000);
        if (!$session) { wp_die('Invalid session.'); }
        if ($message === '') {
            wp_safe_redirect(self::url(array('session'=>$session,'notice'=>'reply_empty')));
            exit;
        }
        if (self::get_mode($session) !== 'agent') {
            self::claim_session($session, get_current_user_id());
        }
        $prepared = class_exists('AAC_AI_Assistant')
            ? AAC_AI_Assistant::prepare_agent_outbound($message)
            : array('visitor_message'=>$message,'original_message'=>'');
        self::insert_message(
            $session,
            'agent',
            $prepared['visitor_message'],
            'agent',
            $prepared['original_message'] ?? ''
        );
        wp_safe_redirect(self::url(array('session'=>$session,'notice'=>'replied')));
        exit;
    }

}
