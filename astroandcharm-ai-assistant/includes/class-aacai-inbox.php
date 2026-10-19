<?php
/**
 * Tawk-aligned Live Inbox workbench for Conversations admin (V1.6.1).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AAC_AI_Inbox {
    const INBOX_LIMIT = 80;

    public static function boot() {
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_global_notif' ) );
    }

    public static function enqueue( $hook ) {
        if ( ! AAC_AI_Conversations::can_view() ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $ok = in_array( $page, array( 'aacai-conversations', 'aacai-team-conversations' ), true )
            || ( strpos( (string) $hook, 'aacai-conversations' ) !== false )
            || ( strpos( (string) $hook, 'aacai-team-conversations' ) !== false );
        if ( ! $ok ) { return; }
        if ( ! empty( $_GET['legacy'] ) ) { return; }

        $base = trailingslashit( dirname( plugin_dir_url( __FILE__ ) ) ) . 'assets/';

        // P0-5: ensure sessions index exists before first paint / REST.
        self::ensure_sessions_index();
        $bootstrap = self::query_inbox( 'all', '', self::INBOX_LIMIT );

        wp_enqueue_style( 'aacai-admin-shared', $base . 'admin-shared.css', array(), AAC_AI_Assistant::VERSION );
        wp_enqueue_style( 'aacai-admin-inbox', $base . 'admin-inbox.css', array( 'aacai-admin-shared' ), AAC_AI_Assistant::VERSION );
        wp_enqueue_script( 'aacai-admin-inbox', $base . 'admin-inbox.js', array(), AAC_AI_Assistant::VERSION, true );
        wp_localize_script( 'aacai-admin-inbox', 'AACAI_INBOX', array(
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'inboxEndpoint' => esc_url_raw( rest_url( 'aacai/v1/live/inbox' ) ),
            'threadEndpoint' => esc_url_raw( rest_url( 'aacai/v1/live/thread' ) ),
            'pollEndpoint' => esc_url_raw( rest_url( 'aacai/v1/live/admin-poll' ) ),
            'claimEndpoint' => esc_url_raw( rest_url( 'aacai/v1/live/claim' ) ),
            'releaseEndpoint' => esc_url_raw( rest_url( 'aacai/v1/live/release' ) ),
            'replyEndpoint' => esc_url_raw( rest_url( 'aacai/v1/live/reply' ) ),
            'resolveEndpoint' => esc_url_raw( rest_url( 'aacai/v1/live/resolve' ) ),
            'tagsEndpoint' => esc_url_raw( rest_url( 'aacai/v1/live/tags' ) ),
            'legacyUrl' => esc_url_raw( add_query_arg( array(
                'page' => current_user_can( 'manage_options' ) ? 'aacai-conversations' : 'aacai-team-conversations',
                'legacy' => '1',
            ), admin_url( 'admin.php' ) ) ),
            'initialSession' => isset( $_GET['session'] ) ? preg_replace( '/[^a-f0-9]/', '', strtolower( (string) wp_unslash( $_GET['session'] ) ) ) : '',
            'bootstrap' => $bootstrap,
            'currentUserId' => (int) get_current_user_id(),
            'pollMs' => 2500,
            'inboxRefreshMs' => 10000,
            'notif' => self::notif_config(),
            'inboxUrl' => esc_url_raw( add_query_arg( array(
                'page' => current_user_can( 'manage_options' ) ? 'aacai-conversations' : 'aacai-team-conversations',
            ), admin_url( 'admin.php' ) ) ),
            'i18n' => AACAI_I18n::js_map( array(
                'empty_title', 'empty_hint', 'loading', 'no_convos', 'send', 'take_over', 'return_to_bot',
                'needs_reply', 'needs_followup', 'error', 'resolve', 'details', 'open', 'visitor', 'all', 'mine', 'chats',
                'waiting', 'agent', 'trash', 'compose', 'mode_bot', 'mode_waiting', 'mode_agent',
            ) ),
            'phrases' => AACAI_I18n::js_phrases(),
            // aliases used by older JS keys
            'i18nAliases' => array(
                'empty' => AACAI_I18n::label( 'empty_title' ),
                'emptyHint' => AACAI_I18n::label( 'empty_hint' ),
                'noConvos' => AACAI_I18n::label( 'no_convos' ),
                'claim' => AACAI_I18n::label( 'take_over' ),
                'release' => AACAI_I18n::label( 'return_to_bot' ),
                'needsReply' => AACAI_I18n::label( 'needs_reply' ),
                'needsFollowup' => AACAI_I18n::label( 'needs_followup' ),
            ),
        ) );
    }

    public static function render( $preselect = '' ) {
        $preselect = preg_match( '/^[a-f0-9]{64}$/', (string) $preselect ) ? $preselect : '';
        $s = (array) get_option( AAC_AI_Assistant::OPTION, array() );
        $logging = ! empty( $s['logging'] );
        $legacy = add_query_arg( array(
            'page' => current_user_can( 'manage_options' ) ? 'aacai-conversations' : 'aacai-team-conversations',
            'legacy' => '1',
        ), admin_url( 'admin.php' ) );

        echo '<div class="wrap aacai-inbox-wrap">';
        echo '<div class="aacai-inbox-topbar">';
        echo '<div class="aacai-inbox-topbar-title"><h1>' . esc_html( AACAI_I18n::label( 'inbox' ) ) . '</h1>';
        if ( class_exists( 'AAC_AI_Assistant' ) ) { AAC_AI_Assistant::render_version_badge(); }
        echo '<span class="aacai-inbox-topbar-sub">' . esc_html( AACAI_I18n::label( 'customer_assistant' ) ) . '</span></div>';
        echo '<div class="aacai-inbox-topbar-actions"><a class="aacai-link-muted" href="' . esc_url( $legacy ) . '">' . esc_html( AACAI_I18n::label( 'legacy_list' ) ) . '</a></div>';
        echo '</div>';

        if ( ! $logging ) {
            echo '<div class="notice notice-warning aacai-inbox-notice aacai-keep-notice"><p><strong>' . esc_html( AACAI_I18n::label( 'recording_off' ) ) . '</strong> ' . esc_html( AACAI_I18n::t( 'Enable it under Settings or Legacy list so new visitor chats appear here.' ) ) . '</p></div>';
        }

        echo '<div id="aacai-notif-perm" class="aacai-notif-perm" hidden>';
        echo '<span>' . esc_html( AACAI_I18n::t( 'Enable desktop notifications for new Waiting / Needs reply chats?' ) ) . ' / 開啟桌面通知？</span>';
        echo '<button type="button" class="button button-primary" id="aacai-notif-enable">' . esc_html( AACAI_I18n::t( 'Enable' ) ) . '</button>';
        echo '<button type="button" class="button" id="aacai-notif-dismiss">' . esc_html( AACAI_I18n::t( 'Not now' ) ) . '</button>';
        echo '</div>';
        echo '<div id="aacai-notif-test-bar" class="aacai-notif-test-bar" hidden>';
        echo '<span>' . esc_html( AACAI_I18n::t( 'Inbox alerts' ) ) . ' / 收件匣提醒：</span>';
        echo '<button type="button" class="button" id="aacai-notif-test">' . esc_html( AACAI_I18n::t( 'Test alert' ) ) . ' / 測試提醒</button>';
        echo '<span class="aacai-notif-test-hint">' . esc_html( AACAI_I18n::t( 'Plays sound, flashes tab title, and shows a desktop toast (if allowed).' ) ) . '</span>';
        echo '</div>';
        echo '<div id="aacai-inbox-app" class="aacai-inbox-app" data-preselect="' . esc_attr( $preselect ) . '">';

        // 1) Folders (Tawk-style: Compose + All/Mine + folder list)
        echo '<aside class="aacai-col-folders aacai-col-folders-tint" aria-label="Folders">';
        echo '<div class="aacai-folders-head"><span class="aacai-folders-brand">' . esc_html( AACAI_I18n::label( 'inbox' ) ) . '</span></div>';
        echo '<button type="button" class="aacai-compose-btn" id="aacai-compose-hint" title="' . esc_attr( AACAI_I18n::t( 'Visitors start chats from the store widget' ) ) . '">';
        echo '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
        echo ' ' . esc_html( AACAI_I18n::label( 'compose' ) ) . '</button>';
        echo '<div class="aacai-scope-tabs" role="tablist">';
        echo '<button type="button" class="aacai-scope-tab is-active" data-scope="all" role="tab">' . esc_html( AACAI_I18n::label( 'all' ) ) . '<span class="aacai-notif-dot" data-scope-dot="all" hidden></span></button>';
        echo '<button type="button" class="aacai-scope-tab" data-scope="mine" role="tab">' . esc_html( AACAI_I18n::label( 'mine' ) ) . '<span class="aacai-notif-dot" data-scope-dot="mine" hidden></span></button>';
        echo '</div>';
        echo '<nav class="aacai-folder-nav" aria-label="Conversation folders">';
        $folders = array(
            'all'            => array( AACAI_I18n::label( 'all' ), 'ico-inbox' ),
            'chats'          => array( AACAI_I18n::label( 'chats' ), 'ico-chat' ),
            'waiting'        => array( AACAI_I18n::label( 'waiting' ), 'ico-wait' ),
            'agent'          => array( AACAI_I18n::label( 'agent' ), 'ico-agent' ),
            'needs_reply'    => array( AACAI_I18n::label( 'needs_reply' ), 'ico-need' ),
            'needs_followup' => array( AACAI_I18n::label( 'needs_followup' ), 'ico-follow' ),
            'resolved'       => array( AACAI_I18n::label( 'trash' ), 'ico-trash' ),
        );
        foreach ( $folders as $key => $meta ) {
            $active = ( $key === 'all' ) ? ' is-active' : '';
            echo '<button type="button" class="aacai-folder' . $active . '" data-filter="' . esc_attr( $key ) . '">';
            echo '<span class="aacai-folder-ico ' . esc_attr( $meta[1] ) . '" aria-hidden="true"></span>';
            echo '<span class="aacai-folder-label">' . esc_html( $meta[0] ) . '</span>';
            echo '<span class="aacai-folder-badge"><span class="aacai-notif-dot aacai-folder-dot" data-folder-dot="' . esc_attr( $key ) . '" hidden></span>';
            echo '<span class="aacai-folder-count" data-count-for="' . esc_attr( $key ) . '">0</span></span>';
            echo '</button>';
        }
        echo '<div class="aacai-folder aacai-folder-na" title="' . esc_attr( AACAI_I18n::label( 'na' ) ) . '"><span class="aacai-folder-ico ico-ticket"></span><span class="aacai-folder-label">' . esc_html( AACAI_I18n::label( 'tickets' ) ) . '</span><span class="aacai-folder-na-tag">' . esc_html( AACAI_I18n::label( 'na' ) ) . '</span></div>';
        echo '<div class="aacai-folder aacai-folder-na" title="' . esc_attr( AACAI_I18n::label( 'na' ) ) . '"><span class="aacai-folder-ico ico-spam"></span><span class="aacai-folder-label">' . esc_html( AACAI_I18n::label( 'spam' ) ) . '</span><span class="aacai-folder-na-tag">' . esc_html( AACAI_I18n::label( 'na' ) ) . '</span></div>';
        echo '</nav>';
        echo '<div class="aacai-folders-foot">';
        echo '<a class="aacai-connect-btn" href="' . esc_url( $legacy ) . '">' . esc_html( AACAI_I18n::label( 'legacy_list_view' ) ) . '</a>';
        echo '</div>';
        echo '</aside>';

        // 2) Conversation list
        echo '<aside class="aacai-col-list" aria-label="Conversations">';
        echo '<div class="aacai-list-toolbar">';
        echo '<div class="aacai-list-search-wrap"><svg class="aacai-ico" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>';
        echo '<input type="search" id="aacai-inbox-search" placeholder="' . esc_attr( AACAI_I18n::label( 'search' ) ) . '" autocomplete="off"></div>';
        echo '<span class="aacai-list-folder-title" id="aacai-list-folder-title">' . esc_html( AACAI_I18n::label( 'all' ) ) . '</span>';
        echo '</div>';
        echo '<div class="aacai-inbox-list" id="aacai-inbox-list" role="listbox" aria-label="Conversation list"></div>';
        echo '</aside>';

        // 3) Thread
        echo '<section class="aacai-col-thread" aria-label="Conversation">';
        echo '<div class="aacai-inbox-empty" id="aacai-inbox-empty">';
        echo '<div class="aacai-empty-art" aria-hidden="true">';
        echo '<svg viewBox="0 0 160 110" width="140" height="96" aria-hidden="true"><ellipse cx="58" cy="48" rx="46" ry="34" fill="#ffe566"/><ellipse cx="108" cy="62" rx="42" ry="30" fill="#5b9cff"/><circle cx="42" cy="42" r="4" fill="#1d2327" opacity=".55"/><circle cx="58" cy="42" r="4" fill="#1d2327" opacity=".55"/><circle cx="74" cy="42" r="4" fill="#1d2327" opacity=".55"/><circle cx="94" cy="58" r="3.5" fill="#fff" opacity=".9"/><circle cx="108" cy="58" r="3.5" fill="#fff" opacity=".9"/><circle cx="122" cy="58" r="3.5" fill="#fff" opacity=".9"/></svg>';
        echo '</div>';
        echo '<h2>' . esc_html( AACAI_I18n::label( 'empty_title' ) ) . '</h2>';
        echo '<p>' . esc_html( AACAI_I18n::label( 'empty_hint' ) ) . '</p>';
        echo '</div>';
        echo '<div class="aacai-inbox-toolbar" id="aacai-inbox-toolbar" hidden>';
        echo '<div class="aacai-thread-who">';
        echo '<span class="aacai-avatar aacai-avatar-lg" id="aacai-thread-avatar">V</span>';
        echo '<div><strong id="aacai-inbox-title">' . esc_html( AACAI_I18n::label( 'visitor' ) ) . '</strong>';
        echo '<div class="aacai-thread-badges"><span class="aacai-inbox-badge" id="aacai-inbox-mode-badge"></span>';
        echo '<span class="aacai-inbox-badge aacai-badge-need" id="aacai-inbox-need-badge" hidden>' . esc_html( AACAI_I18n::label( 'needs_reply' ) ) . '</span>';
        echo '<span class="aacai-inbox-badge aacai-badge-followup" id="aacai-inbox-followup-badge" hidden>' . esc_html( AACAI_I18n::label( 'needs_followup' ) ) . '</span></div></div></div>';
        echo '<div class="aacai-inbox-toolbar-actions">';
        echo '<button type="button" class="aacai-btn aacai-btn-ghost" id="aacai-inbox-claim">' . esc_html( AACAI_I18n::label( 'take_over' ) ) . '</button>';
        echo '<button type="button" class="aacai-btn aacai-btn-ghost" id="aacai-inbox-release" hidden>' . esc_html( AACAI_I18n::label( 'return_to_bot' ) ) . '</button>';
        echo '<button type="button" class="aacai-btn aacai-btn-primary" id="aacai-inbox-resolve">' . esc_html( AACAI_I18n::label( 'resolve' ) ) . '</button>';
        echo '<button type="button" class="aacai-btn aacai-btn-ghost" id="aacai-inbox-toggle-meta" title="' . esc_attr( AACAI_I18n::label( 'details' ) ) . '">' . esc_html( AACAI_I18n::label( 'details' ) ) . '</button>';
        echo '</div></div>';
        echo '<div class="aacai-inbox-stream" id="aacai-inbox-stream" hidden></div>';
        echo '<form class="aacai-inbox-composer" id="aacai-inbox-composer" hidden>';
        echo '<textarea id="aacai-inbox-reply" rows="3" maxlength="4000" placeholder="' . esc_attr( AACAI_I18n::label( 'type_reply' ) ) . '"></textarea>';
        echo '<div class="aacai-inbox-composer-bar">';
        echo '<span class="aacai-inbox-hint">' . esc_html( AACAI_I18n::label( 'send_hint' ) ) . '</span>';
        echo '<button type="submit" class="aacai-btn aacai-btn-primary" id="aacai-inbox-send">' . esc_html( AACAI_I18n::label( 'send' ) ) . '</button>';
        echo '</div></form>';
        echo '</section>';

        // 4) Details slide-over
        echo '<aside class="aacai-col-details" id="aacai-inbox-right" hidden aria-label="Visitor details">';
        echo '<div class="aacai-inbox-right-head"><strong>' . esc_html( AACAI_I18n::label( 'details' ) ) . '</strong><button type="button" class="aacai-icon-btn" id="aacai-inbox-close-meta" aria-label="' . esc_attr( AACAI_I18n::label( 'hide' ) ) . '">×</button></div>';
        echo '<div class="aacai-details-profile" id="aacai-details-profile"></div>';
        echo '<div class="aacai-details-section"><button type="button" class="aacai-details-sec-toggle" disabled>' . esc_html( AACAI_I18n::label( 'about' ) ) . '</button>';
        echo '<dl class="aacai-inbox-meta" id="aacai-inbox-meta"></dl></div>';
        echo '<div class="aacai-details-section"><button type="button" class="aacai-details-sec-toggle" disabled>' . esc_html( AACAI_I18n::label( 'orders' ) ) . '</button>';
        echo '<div id="aacai-inbox-orders" class="aacai-inbox-orders"></div></div>';
        echo '<div class="aacai-details-section"><button type="button" class="aacai-details-sec-toggle" disabled>' . esc_html( AACAI_I18n::label( 'organization' ) ) . '</button>';
        echo '<p class="aacai-stub-na">' . esc_html( AACAI_I18n::t( 'No organization linked.' ) ) . ' <span class="aacai-folder-na-tag">' . esc_html( AACAI_I18n::label( 'stub' ) ) . '</span></p></div>';
        echo '<div class="aacai-details-section"><button type="button" class="aacai-details-sec-toggle" disabled>' . esc_html( AACAI_I18n::label( 'deals' ) ) . '</button>';
        echo '<p class="aacai-stub-na">' . esc_html( AACAI_I18n::t( '0 involved. Use WooCommerce orders above.' ) ) . ' <span class="aacai-folder-na-tag">' . esc_html( AACAI_I18n::label( 'stub' ) ) . '</span></p></div>';
        echo '<div class="aacai-details-section"><button type="button" class="aacai-details-sec-toggle" disabled>' . esc_html( AACAI_I18n::label( 'tags' ) ) . '</button>';
        echo '<div class="aacai-tags-editor"><input type="text" id="aacai-inbox-tags" placeholder="' . esc_attr( AACAI_I18n::t( 'refund, vip, follow-up' ) ) . '" maxlength="255">';
        echo '<button type="button" class="aacai-btn aacai-btn-primary" id="aacai-inbox-tags-save">' . esc_html( AACAI_I18n::label( 'save_tags' ) ) . '</button></div></div>';
        echo '</aside>';

        echo '</div></div>';
    }

    /**
     * P0-5: if messages exist but sessions index is empty/stale, rebuild from messages.
     * Same backfill as Conversations::install INSERT IGNORE — safe to run often.
     */
    public static function ensure_sessions_index() {
        global $wpdb;
        $sessions = $wpdb->prefix . 'aacai_sessions';
        $messages = $wpdb->prefix . 'aacai_messages';
        $sess_ok = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $sessions ) ) ) === $sessions );
        $msg_ok = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $messages ) ) ) === $messages );
        if ( ! $sess_ok || ! $msg_ok ) {
            if ( class_exists( 'AAC_AI_Conversations' ) ) {
                AAC_AI_Conversations::install();
            }
            return;
        }
        $msg_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$messages}" );
        $sess_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sessions}" );
        if ( $msg_count > 0 && $sess_count === 0 ) {
            $wpdb->query( "INSERT IGNORE INTO {$sessions} (session_key,status,mode,agent_user_id,first_message_at,last_message_at,message_count)
                SELECT session_key,'open','bot',0,MIN(created_at),MAX(created_at),COUNT(*)
                FROM {$messages} GROUP BY session_key" );
        } elseif ( $msg_count > 0 ) {
            // Catch sessions that never got an index row (partial writes).
            $wpdb->query( "INSERT IGNORE INTO {$sessions} (session_key,status,mode,agent_user_id,first_message_at,last_message_at,message_count)
                SELECT m.session_key,'open','bot',0,MIN(m.created_at),MAX(m.created_at),COUNT(*)
                FROM {$messages} m
                LEFT JOIN {$sessions} s ON s.session_key = m.session_key
                WHERE s.session_key IS NULL
                GROUP BY m.session_key" );
        }
    }

    /** Build inbox rows for REST. */
    public static function query_inbox( $filter = 'all', $search = '', $limit = 80 ) {
        global $wpdb;
        self::ensure_sessions_index();
        $filter = sanitize_key( $filter );
        if ( ! in_array( $filter, array( 'all', 'chats', 'waiting', 'agent', 'needs_reply', 'needs_followup', 'resolved', 'mine' ), true ) ) {
            $filter = 'all';
        }
        $search = aacai_mb_substr_safe( trim( (string) $search ), 0, 120 );
        $limit = max( 1, min( 150, (int) $limit ) );
        $sessions = $wpdb->prefix . 'aacai_sessions';
        $messages = $wpdb->prefix . 'aacai_messages';
        $uid = (int) get_current_user_id();

        $args = array();
        $where = array( '1=1' );
        if ( $filter === 'waiting' ) {
            $where[] = "s.mode='waiting'";
        } elseif ( $filter === 'agent' ) {
            $where[] = "s.mode='agent'";
        } elseif ( $filter === 'chats' ) {
            // Active conversations: live queue OR open/pending bot threads (not trash/resolved).
            $where[] = "(s.mode IN ('waiting','agent') OR (s.mode='bot' AND s.status IN ('open','pending')))";
        } elseif ( $filter === 'needs_followup' ) {
            // status=pending is the legacy / flag_needs_followup marker.
            $where[] = "s.status='pending'";
        } elseif ( $filter === 'resolved' ) {
            $where[] = "s.status='resolved'";
        } elseif ( $filter === 'mine' ) {
            $where[] = 's.agent_user_id=%d';
            $args[] = $uid;
        }
        if ( $search !== '' ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where[] = "(s.session_key LIKE %s OR EXISTS (SELECT 1 FROM {$messages} m2 WHERE m2.session_key=s.session_key AND m2.message LIKE %s))";
            $args[] = $like;
            $args[] = $like;
        }
        $zh_sub = '';
        $zh_col = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$messages} LIKE %s", 'admin_zh_tw' ) );
        if ( $zh_col ) {
            $zh_sub = ",
            (SELECT admin_zh_tw FROM {$messages} mx WHERE mx.session_key=s.session_key ORDER BY mx.id DESC LIMIT 1) AS last_zh";
        }
        $sql = "SELECT s.session_key, s.status, s.mode, s.agent_user_id, s.message_count, s.first_message_at, s.last_message_at,
            (SELECT message FROM {$messages} mx WHERE mx.session_key=s.session_key ORDER BY mx.id DESC LIMIT 1) AS last_message,
            (SELECT role FROM {$messages} mx WHERE mx.session_key=s.session_key ORDER BY mx.id DESC LIMIT 1) AS last_role
            {$zh_sub}
            FROM {$sessions} s
            WHERE " . implode( ' AND ', $where ) . "
            ORDER BY s.last_message_at DESC
            LIMIT {$limit}";
        if ( $args ) {
            $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
        } else {
            $rows = $wpdb->get_results( $sql, ARRAY_A );
        }
        if ( ! is_array( $rows ) ) {
            $rows = array();
        }

        $out = array();
        $counts = array( 'all' => 0, 'chats' => 0, 'waiting' => 0, 'agent' => 0, 'needs_reply' => 0, 'needs_followup' => 0, 'resolved' => 0, 'mine' => 0 );
        // Separate count query for badges (unfiltered by needs_reply filter).
        $count_rows = $wpdb->get_results(
            "SELECT s.session_key, s.mode, s.status, s.agent_user_id,
                (SELECT role FROM {$messages} mx WHERE mx.session_key=s.session_key ORDER BY mx.id DESC LIMIT 1) AS last_role
             FROM {$sessions} s",
            ARRAY_A
        );
        foreach ( (array) $count_rows as $c ) {
            $counts['all']++;
            $mode = AAC_AI_Conversations::normalize_mode( $c['mode'] ?? 'bot' );
            $status = (string) ( $c['status'] ?? 'open' );
            if ( $mode === 'waiting' || $mode === 'agent' || ( $mode === 'bot' && in_array( $status, array( 'open', 'pending' ), true ) ) ) { $counts['chats']++; }
            if ( $mode === 'waiting' ) { $counts['waiting']++; }
            if ( $mode === 'agent' ) { $counts['agent']++; }
            if ( $status === 'resolved' ) { $counts['resolved']++; }
            if ( $status === 'pending' ) { $counts['needs_followup']++; }
            if ( (int) ( $c['agent_user_id'] ?? 0 ) === $uid && $uid > 0 ) { $counts['mine']++; }
            $needs = in_array( $mode, array( 'waiting', 'agent' ), true ) && ( ( $c['last_role'] ?? '' ) === 'user' );
            if ( $needs ) { $counts['needs_reply']++; }
        }

        foreach ( $rows as $r ) {
            $mode = AAC_AI_Conversations::normalize_mode( $r['mode'] ?? 'bot' );
            $status = (string) ( $r['status'] ?? 'open' );
            $needs = in_array( $mode, array( 'waiting', 'agent' ), true ) && ( ( $r['last_role'] ?? '' ) === 'user' );
            $needs_followup = ( $status === 'pending' );
            if ( $filter === 'needs_reply' && ! $needs ) {
                continue;
            }
            $preview_src = (string) ( $r['last_message'] ?? '' );
            if ( ! empty( $r['last_zh'] ) ) {
                $preview_src = (string) $r['last_zh'];
            }
            $preview = wp_trim_words( wp_strip_all_tags( $preview_src ), 14, '…' );
            $out[] = array(
                'session' => (string) $r['session_key'],
                'short' => substr( (string) $r['session_key'], 0, 10 ),
                'status' => $status,
                'mode' => $mode,
                'mode_label' => AAC_AI_Conversations::mode_label( $mode ),
                'needs_reply' => $needs,
                'needs_followup' => $needs_followup,
                'message_count' => (int) $r['message_count'],
                'first_message_at' => (string) $r['first_message_at'],
                'last_message_at' => (string) $r['last_message_at'],
                'last_role' => (string) ( $r['last_role'] ?? '' ),
                'preview' => $preview !== '' ? $preview : '—',
                'agent_user_id' => (int) ( $r['agent_user_id'] ?? 0 ),
            );
        }

        return array(
            'items' => $out,
            'counts' => $counts,
        );
    }

    public static function query_thread( $session ) {
        global $wpdb;
        $session = strtolower( sanitize_text_field( (string) $session ) );
        if ( ! preg_match( '/^[a-f0-9]{64}$/', $session ) ) {
            return null;
        }
        $row = AAC_AI_Conversations::get_session_row( $session );
        if ( ! $row ) {
            return null;
        }
        $messages = $wpdb->prefix . 'aacai_messages';
        if ( class_exists( 'AAC_AI_Conversations' ) ) {
            AAC_AI_Conversations::ensure_original_message_column();
            AAC_AI_Conversations::ensure_admin_zh_tw_column();
        }
        $col_orig = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$messages} LIKE %s", 'original_message' ) );
        $col_zh = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$messages} LIKE %s", 'admin_zh_tw' ) );
        $cols = 'id, role, message, created_at';
        if ( $col_orig ) { $cols = 'id, role, message, original_message, created_at'; }
        if ( $col_zh ) {
            $cols = $col_orig
                ? 'id, role, message, original_message, admin_zh_tw, created_at'
                : 'id, role, message, admin_zh_tw, created_at';
        }
        $msgs = $wpdb->get_results( $wpdb->prepare(
            "SELECT {$cols} FROM {$messages} WHERE session_key=%s ORDER BY id ASC LIMIT 500",
            $session
        ), ARRAY_A );
        if ( ! is_array( $msgs ) ) {
            $msgs = array();
        }
        $last_role = '';
        $last_id = 0;
        $formatted = array();
        // F1 (1.9.0): thread open must never hang on translation. Enrichment is
        // best-effort only: small call budget AND a hard wall-clock budget —
        // beyond either, messages render untranslated instead of blocking.
        $enrich_budget = 8; // soft cap MyMemory calls per thread open
        $enrich_deadline = microtime( true ) + 4.0; // seconds
        foreach ( $msgs as $m ) {
            $role = in_array( $m['role'], array( 'user', 'assistant', 'agent', 'system' ), true ) ? $m['role'] : 'assistant';
            $last_role = $role;
            $last_id = (int) $m['id'];
            $item = array(
                'id' => (int) $m['id'],
                'role' => $role,
                'message' => (string) $m['message'],
                'created_at' => (string) $m['created_at'],
            );
            if ( ! empty( $m['original_message'] ) ) {
                $item['original_message'] = (string) $m['original_message'];
            }
            if ( ! empty( $m['admin_zh_tw'] ) ) {
                $item['admin_zh_tw'] = (string) $m['admin_zh_tw'];
            }
            if ( class_exists( 'AACAI_Translate' ) ) {
                $needs_mt = empty( $item['admin_zh_tw'] )
                    && empty( $item['original_message'] )
                    && AACAI_Translate::is_primarily_latin( $item['message'] );
                if ( $needs_mt && ( $enrich_budget <= 0 || microtime( true ) > $enrich_deadline ) ) {
                    $item['admin_display'] = $item['message'];
                    $item['bilingual'] = false;
                } else {
                    if ( $needs_mt ) { $enrich_budget--; }
                    $item = AACAI_Translate::enrich_admin_message( $item );
                }
            }
            $formatted[] = $item;
        }
        $mode = AAC_AI_Conversations::normalize_mode( $row['mode'] ?? 'bot' );
        $status = (string) ( $row['status'] ?? 'open' );
        $needs = in_array( $mode, array( 'waiting', 'agent' ), true ) && $last_role === 'user';
        $needs_followup = ( $status === 'pending' );
        $agent_name = '';
        if ( ! empty( $row['agent_user_id'] ) ) {
            $u = get_userdata( (int) $row['agent_user_id'] );
            if ( $u ) {
                $agent_name = $u->display_name;
            }
        }
        $orders = self::attributed_orders( $session );

        return array(
            'session' => $session,
            'short' => substr( $session, 0, 12 ),
            'status' => $status,
            'mode' => $mode,
            'mode_label' => AAC_AI_Conversations::mode_label( $mode ),
            'needs_reply' => $needs,
            'needs_followup' => $needs_followup,
            'message_count' => (int) $row['message_count'],
            'first_message_at' => (string) $row['first_message_at'],
            'last_message_at' => (string) $row['last_message_at'],
            'agent_user_id' => (int) ( $row['agent_user_id'] ?? 0 ),
            'agent_name' => $agent_name,
            'first_source' => (string) ( $row['first_source'] ?? '' ),
            'has_takeover' => ! empty( $row['has_takeover'] ),
            'last_id' => $last_id,
            'messages' => $formatted,
            'orders' => $orders,
            'woo_active' => function_exists( 'wc_get_orders' ),
            'tags' => (string) ( $row['tags'] ?? '' ),
            'satisfaction' => (string) ( $row['satisfaction'] ?? '' ),
        );
    }

    private static function attributed_orders( $hashed_session ) {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return array();
        }
        $orders = wc_get_orders( array(
            'limit' => 8,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_aacai_session',
                    'value' => $hashed_session,
                    'compare' => '=',
                ),
            ),
            'return' => 'objects',
        ) );
        $out = array();
        foreach ( (array) $orders as $order ) {
            if ( ! $order || ! method_exists( $order, 'get_id' ) ) {
                continue;
            }
            $id = (int) $order->get_id();
            $out[] = array(
                'id' => $id,
                'number' => method_exists( $order, 'get_order_number' ) ? (string) $order->get_order_number() : (string) $id,
                'status' => method_exists( $order, 'get_status' ) ? (string) $order->get_status() : '',
                'total' => method_exists( $order, 'get_formatted_order_total' ) ? wp_strip_all_tags( $order->get_formatted_order_total() ) : '',
                'url' => admin_url( 'post.php?post=' . $id . '&action=edit' ),
            );
        }
        return $out;
    }

    public static function rest_inbox( WP_REST_Request $request ) {
        $data = self::query_inbox(
            (string) $request->get_param( 'filter' ),
            (string) $request->get_param( 's' ),
            absint( $request->get_param( 'limit' ) ) ?: self::INBOX_LIMIT
        );
        if ( ! headers_sent() ) {
            nocache_headers();
        }
        return rest_ensure_response( $data );
    }

    public static function rest_thread( WP_REST_Request $request ) {
        $session = strtolower( sanitize_text_field( (string) $request->get_param( 'session' ) ) );
        $thread = self::query_thread( $session );
        if ( ! $thread ) {
            return new WP_Error( 'aacai_missing', 'Conversation not found.', array( 'status' => 404 ) );
        }
        if ( ! headers_sent() ) {
            nocache_headers();
        }
        return rest_ensure_response( $thread );
    }

    /** Notification settings for Inbox + global admin badge (V1.8.6). */
    public static function notif_config() {
        $s = (array) get_option( AAC_AI_Assistant::OPTION, array() );
        return array(
            'sound' => ! isset( $s['notif_sound'] ) || ! empty( $s['notif_sound'] ),
            'desktop' => ! isset( $s['notif_desktop'] ) || ! empty( $s['notif_desktop'] ),
            'menuBadge' => ! isset( $s['notif_menu_badge'] ) || ! empty( $s['notif_menu_badge'] ),
            'botChats' => ! empty( $s['notif_bot_chats'] ),
        );
    }

    /** Counts used for WP menu badge: waiting + needs_reply (+ follow-up). */
    public static function alert_badge_count() {
        $data = self::query_inbox( 'all', '', 1 );
        $c = isset( $data['counts'] ) && is_array( $data['counts'] ) ? $data['counts'] : array();
        return (int) ( $c['waiting'] ?? 0 ) + (int) ( $c['needs_reply'] ?? 0 );
    }

    /** Global admin script: menu badge + optional desktop alerts off Inbox page. */
    public static function enqueue_global_notif( $hook ) {
        if ( ! AAC_AI_Conversations::can_view() ) { return; }
        $cfg = self::notif_config();
        if ( empty( $cfg['menuBadge'] ) && empty( $cfg['desktop'] ) && empty( $cfg['sound'] ) ) { return; }
        $base = trailingslashit( dirname( plugin_dir_url( __FILE__ ) ) ) . 'assets/';
        wp_enqueue_script( 'aacai-admin-notif', $base . 'admin-notif.js', array(), AAC_AI_Assistant::VERSION, true );
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $on_inbox = in_array( $page, array( 'aacai-conversations', 'aacai-team-conversations' ), true )
            && empty( $_GET['legacy'] );
        wp_localize_script( 'aacai-admin-notif', 'AACAI_NOTIF', array(
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'inboxEndpoint' => esc_url_raw( rest_url( 'aacai/v1/live/inbox' ) ),
            'inboxUrl' => esc_url_raw( add_query_arg( array(
                'page' => current_user_can( 'manage_options' ) ? 'aacai-conversations' : 'aacai-team-conversations',
            ), admin_url( 'admin.php' ) ) ),
            'pollMs' => $on_inbox ? 10000 : 20000,
            'onInbox' => $on_inbox,
            'notif' => $cfg,
            'initialBadge' => self::alert_badge_count(),
        ) );
    }
}
