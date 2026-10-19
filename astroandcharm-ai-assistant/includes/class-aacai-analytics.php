<?php
/**
 * Customer Assistant — inbound / reply-source / order attribution analytics (V1.5).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AACAI_Analytics {
    const SCHEMA_OPTION = 'aacai_analytics_schema_version';
    const SCHEMA_VERSION = '1.7.0';
    const COOKIE = 'aacai_sid';
    const ATTR_WINDOW_DAYS = 7;
    const ORDER_META_SESSION = '_aacai_session';
    const ORDER_META_AT = '_aacai_attributed_at';

    public static function boot() {
        add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 3 );
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 30 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
        add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'attribute_order' ), 20, 1 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'attribute_order_object' ), 20, 1 );
    }

    public static function enqueue_admin( $hook ) {
        if ( strpos( (string) $hook, 'aacai-analytics' ) === false ) { return; }
        $base = trailingslashit( dirname( plugin_dir_url( __FILE__ ) ) ) . 'assets/';
        wp_enqueue_style( 'aacai-admin-shared', $base . 'admin-shared.css', array(), AAC_AI_Assistant::VERSION );
    }

    private static function daily_table() {
        global $wpdb;
        return $wpdb->prefix . 'aacai_daily_stats';
    }

    public static function maybe_upgrade() {
        if ( get_option( self::SCHEMA_OPTION ) === self::SCHEMA_VERSION ) { return; }
        self::install();
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $daily = self::daily_table();
        dbDelta( "CREATE TABLE {$daily} (
            day date NOT NULL,
            widget_opens int unsigned NOT NULL DEFAULT 0,
            sessions_started int unsigned NOT NULL DEFAULT 0,
            messages_user int unsigned NOT NULL DEFAULT 0,
            messages_assistant int unsigned NOT NULL DEFAULT 0,
            src_fixed int unsigned NOT NULL DEFAULT 0,
            src_ai int unsigned NOT NULL DEFAULT 0,
            src_woocommerce int unsigned NOT NULL DEFAULT 0,
            src_guide int unsigned NOT NULL DEFAULT 0,
            src_unverified int unsigned NOT NULL DEFAULT 0,
            src_human_queue int unsigned NOT NULL DEFAULT 0,
            src_agent int unsigned NOT NULL DEFAULT 0,
            src_system int unsigned NOT NULL DEFAULT 0,
            takeovers int unsigned NOT NULL DEFAULT 0,
            attributed_orders int unsigned NOT NULL DEFAULT 0,
            attributed_revenue decimal(14,2) NOT NULL DEFAULT 0.00,
            sat_positive int unsigned NOT NULL DEFAULT 0,
            sat_negative int unsigned NOT NULL DEFAULT 0,
            sat_neutral int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (day)
        ) {$charset};" );

        $messages = $wpdb->prefix . 'aacai_messages';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $messages ) ) ) === $messages ) {
            $col = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$messages} LIKE %s", 'source' ) );
            if ( ! $col ) {
                $wpdb->query( "ALTER TABLE {$messages} ADD COLUMN source varchar(32) NOT NULL DEFAULT '' AFTER role" );
            }
        }

        $sessions = $wpdb->prefix . 'aacai_sessions';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $sessions ) ) ) === $sessions ) {
            $col = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$sessions} LIKE %s", 'first_source' ) );
            if ( ! $col ) {
                $wpdb->query( "ALTER TABLE {$sessions} ADD COLUMN first_source varchar(32) NOT NULL DEFAULT '' AFTER agent_user_id, ADD COLUMN has_takeover tinyint(1) NOT NULL DEFAULT 0 AFTER first_source" );
            }
        }

        // Ensure satisfaction counters exist on upgrades
        foreach ( array( 'sat_positive', 'sat_negative', 'sat_neutral' ) as $col ) {
            $exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$daily} LIKE %s", $col ) );
            if ( ! $exists ) {
                $wpdb->query( "ALTER TABLE {$daily} ADD COLUMN {$col} int unsigned NOT NULL DEFAULT 0" );
            }
        }

        update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
    }

    public static function record_satisfaction( $rating ) {
        $rating = sanitize_key( (string) $rating );
        $map = array( 'positive' => 'sat_positive', 'negative' => 'sat_negative', 'neutral' => 'sat_neutral' );
        if ( ! isset( $map[ $rating ] ) ) { return; }
        self::bump_daily( self::utc_day(), array( $map[ $rating ] => 1 ) );
    }

    public static function normalize_source( $source ) {
        $source = sanitize_key( (string) $source );
        $allowed = array( 'fixed', 'ai', 'woocommerce', 'guide', 'guide_js', 'unverified', 'human_queue', 'agent', 'system', '' );
        if ( $source === 'guide_js' ) { return 'guide'; }
        return in_array( $source, $allowed, true ) ? $source : '';
    }

    private static function utc_day( $mysql_utc = null ) {
        if ( $mysql_utc ) {
            return substr( (string) $mysql_utc, 0, 10 );
        }
        return gmdate( 'Y-m-d' );
    }

    private static function bump_daily( $day, $fields ) {
        global $wpdb;
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $day ) ) { return; }
        $table = self::daily_table();
        $allowed = array(
            'widget_opens', 'sessions_started', 'messages_user', 'messages_assistant',
            'src_fixed', 'src_ai', 'src_woocommerce', 'src_guide', 'src_unverified',
            'src_human_queue', 'src_agent', 'src_system', 'takeovers',
            'attributed_orders', 'attributed_revenue', 'sat_positive', 'sat_negative', 'sat_neutral',
        );
        $sets = array();
        $insert_cols = array( 'day' );
        $insert_vals = array( '%s' );
        $insert_args = array( $day );
        $update_parts = array();
        foreach ( $fields as $k => $v ) {
            if ( ! in_array( $k, $allowed, true ) ) { continue; }
            $v = ( $k === 'attributed_revenue' ) ? (float) $v : max( 0, (int) $v );
            if ( $v == 0 ) { continue; }
            $insert_cols[] = $k;
            if ( $k === 'attributed_revenue' ) {
                $insert_vals[] = '%f';
                $insert_args[] = $v;
                $update_parts[] = "{$k}={$k}+VALUES({$k})";
            } else {
                $insert_vals[] = '%d';
                $insert_args[] = (int) $v;
                $update_parts[] = "{$k}={$k}+VALUES({$k})";
            }
        }
        if ( count( $insert_cols ) < 2 ) {
            // Ensure row exists even for zero bumps used as touch — skip.
            return;
        }
        $sql = 'INSERT INTO ' . $table . ' (' . implode( ',', $insert_cols ) . ') VALUES (' . implode( ',', $insert_vals ) . ')';
        if ( $update_parts ) {
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode( ', ', $update_parts );
        }
        $wpdb->query( $wpdb->prepare( $sql, $insert_args ) );
    }

    /** Called after a message row is stored. */
    public static function on_message_saved( $hashed_session, $role, $source, $created_at, $is_new_session ) {
        $day = self::utc_day( $created_at );
        $source = self::normalize_source( $source );
        $fields = array();
        if ( $is_new_session ) {
            $fields['sessions_started'] = 1;
            // Backfill widget_opens when the open beacon was missed (keeps Open→Chat ≤ 100%).
            if ( preg_match( '/^[a-f0-9]{64}$/', (string) $hashed_session ) ) {
                $okey = 'aacai_open_' . substr( $hashed_session, 0, 24 ) . '_' . str_replace( '-', '', $day );
                if ( ! get_transient( $okey ) ) {
                    set_transient( $okey, 1, DAY_IN_SECONDS );
                    $fields['widget_opens'] = 1;
                }
            }
        }
        if ( $role === 'user' ) {
            $fields['messages_user'] = 1;
        } elseif ( in_array( $role, array( 'assistant', 'agent', 'system' ), true ) ) {
            $fields['messages_assistant'] = 1;
        }
        if ( $source !== '' ) {
            $map = array(
                'fixed' => 'src_fixed',
                'ai' => 'src_ai',
                'woocommerce' => 'src_woocommerce',
                'guide' => 'src_guide',
                'unverified' => 'src_unverified',
                'human_queue' => 'src_human_queue',
                'agent' => 'src_agent',
                'system' => 'src_system',
            );
            if ( isset( $map[ $source ] ) ) {
                $fields[ $map[ $source ] ] = 1;
            }
        } elseif ( $role === 'agent' ) {
            $fields['src_agent'] = 1;
        } elseif ( $role === 'system' ) {
            $fields['src_system'] = 1;
        }
        if ( $fields ) {
            self::bump_daily( $day, $fields );
        }

        // Session first_source / takeover flag.
        global $wpdb;
        $sessions = $wpdb->prefix . 'aacai_sessions';
        if ( $source && preg_match( '/^[a-f0-9]{64}$/', (string) $hashed_session ) ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$sessions} SET first_source = IF(first_source='' OR first_source IS NULL, %s, first_source) WHERE session_key=%s",
                $source,
                $hashed_session
            ) );
        }
    }

    public static function record_widget_open( $raw_session ) {
        $day = self::utc_day();
        // Deduplicate opens per session per UTC day via transient.
        $hash = AAC_AI_Conversations::hash_raw_session( $raw_session );
        $key = 'aacai_open_' . substr( $hash, 0, 24 ) . '_' . str_replace( '-', '', $day );
        if ( get_transient( $key ) ) {
            return false;
        }
        set_transient( $key, 1, DAY_IN_SECONDS );
        self::bump_daily( $day, array( 'widget_opens' => 1 ) );
        return true;
    }

    public static function record_takeover( $hashed_session ) {
        global $wpdb;
        $day = self::utc_day();
        self::bump_daily( $day, array( 'takeovers' => 1 ) );
        if ( preg_match( '/^[a-f0-9]{64}$/', (string) $hashed_session ) ) {
            $sessions = $wpdb->prefix . 'aacai_sessions';
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$sessions} SET has_takeover=1 WHERE session_key=%s",
                $hashed_session
            ) );
        }
    }

    /** Read visitor cookie, validate, return raw session or ''. */
    public static function cookie_raw_session() {
        if ( empty( $_COOKIE[ self::COOKIE ] ) ) { return ''; }
        $raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
        if ( ! preg_match( '/^[a-zA-Z0-9_-]{8,80}$/', $raw ) ) { return ''; }
        return $raw;
    }

    public static function attribute_order( $order_id ) {
        $order_id = (int) $order_id;
        if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) { return; }
        $order = wc_get_order( $order_id );
        if ( ! $order ) { return; }
        self::attribute_order_object( $order );
    }

    public static function attribute_order_object( $order ) {
        if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) { return; }
        if ( $order->get_meta( self::ORDER_META_SESSION ) ) { return; } // already attributed

        $raw = self::cookie_raw_session();
        if ( $raw === '' ) { return; }
        $hashed = AAC_AI_Conversations::hash_raw_session( $raw );
        $row = AAC_AI_Conversations::get_session_row( $hashed );
        if ( ! $row ) { return; }

        $last = isset( $row['last_message_at'] ) ? strtotime( $row['last_message_at'] . ' UTC' ) : 0;
        if ( ! $last ) { return; }
        $window = self::ATTR_WINDOW_DAYS * DAY_IN_SECONDS;
        if ( ( time() - $last ) > $window ) { return; }

        $order->update_meta_data( self::ORDER_META_SESSION, $hashed );
        $order->update_meta_data( self::ORDER_META_AT, gmdate( 'Y-m-d H:i:s' ) );
        $order->save();

        $total = method_exists( $order, 'get_total' ) ? (float) $order->get_total() : 0;
        self::bump_daily( self::utc_day(), array(
            'attributed_orders' => 1,
            'attributed_revenue' => max( 0, $total ),
        ) );
    }

    public static function admin_menu() {
        add_submenu_page(
            'aacai',
            'Analytics',
            'Analytics',
            'manage_options',
            'aacai-analytics',
            array( __CLASS__, 'page' )
        );
    }

    private static function parse_range() {
        $preset = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '7';
        if ( ! in_array( $preset, array( '7', '30', '90' ), true ) ) { $preset = '7'; }
        $days = (int) $preset;
        $to = gmdate( 'Y-m-d' );
        $from = gmdate( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
        return array( 'preset' => $preset, 'from' => $from, 'to' => $to, 'days' => $days );
    }

    private static function sum_daily( $from, $to ) {
        global $wpdb;
        $table = self::daily_table();
        $exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table );
        $empty = array(
            'widget_opens' => 0, 'sessions_started' => 0, 'messages_user' => 0, 'messages_assistant' => 0,
            'src_fixed' => 0, 'src_ai' => 0, 'src_woocommerce' => 0, 'src_guide' => 0, 'src_unverified' => 0,
            'src_human_queue' => 0, 'src_agent' => 0, 'src_system' => 0, 'takeovers' => 0,
            'attributed_orders' => 0, 'attributed_revenue' => 0, 'sat_positive' => 0, 'sat_negative' => 0, 'sat_neutral' => 0,
        );
        if ( ! $exists ) { return array( $empty, array() ); }
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COALESCE(SUM(widget_opens),0) widget_opens,
                COALESCE(SUM(sessions_started),0) sessions_started,
                COALESCE(SUM(messages_user),0) messages_user,
                COALESCE(SUM(messages_assistant),0) messages_assistant,
                COALESCE(SUM(src_fixed),0) src_fixed,
                COALESCE(SUM(src_ai),0) src_ai,
                COALESCE(SUM(src_woocommerce),0) src_woocommerce,
                COALESCE(SUM(src_guide),0) src_guide,
                COALESCE(SUM(src_unverified),0) src_unverified,
                COALESCE(SUM(src_human_queue),0) src_human_queue,
                COALESCE(SUM(src_agent),0) src_agent,
                COALESCE(SUM(src_system),0) src_system,
                COALESCE(SUM(takeovers),0) takeovers,
                COALESCE(SUM(attributed_orders),0) attributed_orders,
                COALESCE(SUM(attributed_revenue),0) attributed_revenue,
                COALESCE(SUM(sat_positive),0) sat_positive,
                COALESCE(SUM(sat_negative),0) sat_negative,
                COALESCE(SUM(sat_neutral),0) sat_neutral
             FROM {$table} WHERE day >= %s AND day <= %s",
            $from, $to
        ), ARRAY_A );
        $series = $wpdb->get_results( $wpdb->prepare(
            "SELECT day, widget_opens, sessions_started, attributed_orders, src_fixed, src_ai, src_agent,
                sat_positive, sat_negative, sat_neutral
             FROM {$table} WHERE day >= %s AND day <= %s ORDER BY day ASC",
            $from, $to
        ), ARRAY_A );
        return array( is_array( $row ) ? $row : $empty, is_array( $series ) ? $series : array() );
    }

    /** Fallback counts from messages/sessions when daily stats are sparse (pre-upgrade traffic). */
    private static function fallback_from_logs( $from, $to ) {
        global $wpdb;
        $sessions = $wpdb->prefix . 'aacai_sessions';
        $messages = $wpdb->prefix . 'aacai_messages';
        $from_dt = $from . ' 00:00:00';
        $to_dt = $to . ' 23:59:59';
        $inbound = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$sessions} WHERE first_message_at >= %s AND first_message_at <= %s",
            $from_dt, $to_dt
        ) );
        $takeovers = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$sessions} WHERE has_takeover=1 AND last_message_at >= %s AND last_message_at <= %s",
            $from_dt, $to_dt
        ) );
        $by_source = array();
        $has_source = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$messages} LIKE %s", 'source' ) );
        if ( $has_source ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT source, COUNT(*) c FROM {$messages}
                 WHERE created_at >= %s AND created_at <= %s AND source <> '' AND role IN ('assistant','agent','system')
                 GROUP BY source",
                $from_dt, $to_dt
            ), ARRAY_A );
            foreach ( (array) $rows as $r ) {
                $by_source[ self::normalize_source( $r['source'] ) ] = (int) $r['c'];
            }
        }
        return array( 'inbound' => $inbound, 'takeovers' => $takeovers, 'by_source' => $by_source );
    }

    private static function attributed_orders_in_range( $from, $to ) {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return array( 'count' => null, 'revenue' => null, 'woo' => false );
        }
        $orders = wc_get_orders( array(
            'limit' => 200,
            'status' => array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending' ),
            'date_created' => $from . '...' . $to,
            'meta_query' => array(
                array(
                    'key' => self::ORDER_META_SESSION,
                    'compare' => 'EXISTS',
                ),
            ),
            'return' => 'objects',
        ) );
        $count = 0;
        $revenue = 0.0;
        foreach ( (array) $orders as $order ) {
            if ( ! $order || ! method_exists( $order, 'get_meta' ) ) { continue; }
            if ( ! $order->get_meta( self::ORDER_META_SESSION ) ) { continue; }
            $count++;
            $revenue += (float) $order->get_total();
        }
        return array( 'count' => $count, 'revenue' => $revenue, 'woo' => true );
    }

    private static function pct( $num, $den ) {
        if ( $den <= 0 ) { return '—'; }
        return round( 100 * $num / $den, 1 ) . '%';
    }

    private static function bar( $value, $max, $color = '#ac8546' ) {
        $max = max( 1, (float) $max );
        $w = max( 0, min( 100, round( 100 * (float) $value / $max ) ) );
        return '<div style="background:#f0ebe3;border-radius:4px;height:10px;overflow:hidden"><div style="width:' . esc_attr( $w ) . '%;height:10px;background:' . esc_attr( $color ) . '"></div></div>';
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        self::install();
        $range = self::parse_range();
        list( $sum, $series ) = self::sum_daily( $range['from'], $range['to'] );
        $fb = self::fallback_from_logs( $range['from'], $range['to'] );
        $orders = self::attributed_orders_in_range( $range['from'], $range['to'] );

        $inbound = max( (int) $sum['sessions_started'], (int) $fb['inbound'] );
        $opens = (int) $sum['widget_opens'];
        // Denominator must cover every inbound chat (historical undercount of opens).
        if ( $inbound > $opens ) { $opens = $inbound; }
        $takeovers = max( (int) $sum['takeovers'], (int) $fb['takeovers'] );

        $src = array(
            'fixed' => (int) $sum['src_fixed'],
            'ai' => (int) $sum['src_ai'],
            'woocommerce' => (int) $sum['src_woocommerce'],
            'guide' => (int) $sum['src_guide'],
            'unverified' => (int) $sum['src_unverified'],
            'human_queue' => (int) $sum['src_human_queue'],
            'agent' => (int) $sum['src_agent'],
            'system' => (int) $sum['src_system'],
        );
        foreach ( $fb['by_source'] as $k => $v ) {
            if ( $k && isset( $src[ $k ] ) ) {
                $src[ $k ] = max( $src[ $k ], (int) $v );
            }
        }
        $src_total = array_sum( $src );
        $attr_orders = $orders['woo'] ? (int) $orders['count'] : (int) $sum['attributed_orders'];
        $attr_rev = $orders['woo'] ? (float) $orders['revenue'] : (float) $sum['attributed_revenue'];
        // Prefer the higher of daily counter vs live WC query (daily may lag if meta backfill).
        if ( $orders['woo'] ) {
            $attr_orders = max( $attr_orders, (int) $sum['attributed_orders'] );
            $attr_rev = max( $attr_rev, (float) $sum['attributed_revenue'] );
        }

        $conv = self::pct( $attr_orders, $inbound );
        $open_to_chat = self::pct( $inbound, $opens );


        $sat_pos = (int) ( $sum['sat_positive'] ?? 0 );
        $sat_neg = (int) ( $sum['sat_negative'] ?? 0 );
        $sat_neu = (int) ( $sum['sat_neutral'] ?? 0 );
        $view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'volume';
        if ( ! in_array( $view, array( 'volume', 'satisfaction', 'sources' ), true ) ) { $view = 'volume'; }

        $max_sessions = 1;
        foreach ( $series as $r ) {
            $max_sessions = max( $max_sessions, (int) $r['sessions_started'], (int) $r['widget_opens'] );
        }
        $max_sat_day = 1;
        foreach ( $series as $r ) {
            $max_sat_day = max( $max_sat_day, (int) ( $r['sat_positive'] ?? 0 ) + (int) ( $r['sat_negative'] ?? 0 ) + (int) ( $r['sat_neutral'] ?? 0 ) );
        }

        echo '<div class="wrap aacai-reporting">';
        echo '<div class="aacai-admin-shell">';
        echo '<aside class="aacai-admin-nav aacai-rep-nav"><h2>' . esc_html( AACAI_I18n::label( 'reporting' ) ) . '</h2>';
        echo '<div class="grp">' . esc_html( AACAI_I18n::label( 'live_chat' ) ) . '</div>';
        $base = admin_url( 'admin.php?page=aacai-analytics&range=' . rawurlencode( $range['preset'] ) );
        echo '<a class="' . ( $view === 'volume' ? 'is-active' : '' ) . '" href="' . esc_url( $base . '&view=volume' ) . '">' . esc_html( AACAI_I18n::label( 'chat_volume' ) ) . '</a>';
        echo '<a class="' . ( $view === 'satisfaction' ? 'is-active' : '' ) . '" href="' . esc_url( $base . '&view=satisfaction' ) . '">' . esc_html( AACAI_I18n::label( 'user_satisfaction' ) ) . '</a>';
        echo '<a class="' . ( $view === 'sources' ? 'is-active' : '' ) . '" href="' . esc_url( $base . '&view=sources' ) . '">' . esc_html( AACAI_I18n::label( 'reply_sources' ) ) . '</a>';
        echo '<a class="na" href="#">' . esc_html( AACAI_I18n::label( 'missed_chats' ) ) . '</a><a class="na" href="#">' . esc_html( AACAI_I18n::label( 'chat_duration' ) ) . '</a>';
        echo '<div class="grp">' . esc_html( AACAI_I18n::label( 'other' ) ) . '</div><a class="na" href="#">' . esc_html( AACAI_I18n::label( 'tickets' ) ) . '</a><a class="na" href="#">' . esc_html( AACAI_I18n::label( 'lead_capture' ) ) . '</a>';
        echo '</aside><div class="aacai-admin-main aacai-rep-main">';

        echo '<div class="aacai-rep-head">';
        $titles = array( 'volume' => AACAI_I18n::label( 'chat_volume' ), 'satisfaction' => AACAI_I18n::label( 'user_satisfaction' ), 'sources' => AACAI_I18n::label( 'reply_sources' ) );
        echo '<h1>' . esc_html( $titles[ $view ] ); if ( class_exists( 'AAC_AI_Assistant' ) ) { AAC_AI_Assistant::render_version_badge(); } echo '</h1>';
        echo '<form method="get" style="display:flex;gap:8px;align-items:center">';
        echo '<input type="hidden" name="page" value="aacai-analytics"><input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
        echo '<select name="range">';
        foreach ( array( '7' => AACAI_I18n::label( 'last_7' ), '30' => AACAI_I18n::label( 'last_30' ), '90' => AACAI_I18n::label( 'last_90' ) ) as $k => $lab ) {
            echo '<option value="' . esc_attr( $k ) . '" ' . selected( $range['preset'], $k, false ) . '>' . esc_html( $lab ) . '</option>';
        }
        echo '</select><button class="button">' . esc_html( AACAI_I18n::label( 'apply' ) ) . '</button>';
        echo '<span style="color:#6b7280;font-size:12px">' . esc_html( $range['from'] ) . ' → ' . esc_html( $range['to'] ) . '</span></form></div>';

        if ( $view === 'volume' ) {
            echo '<p class="aacai-total-line">'.esc_html(AACAI_I18n::t('Total inbound sessions')).' <strong>' . esc_html( number_format_i18n( $inbound ) ) . '</strong></p>';
            echo '<div class="aacai-metric-row">';
            foreach ( array(
                array( AACAI_I18n::t('Widget opens'), number_format_i18n( $opens ) ),
                array( AACAI_I18n::t('Inbound sessions'), number_format_i18n( $inbound ) ),
                array( AACAI_I18n::t('Open → chat'), $open_to_chat ),
                array( AACAI_I18n::t('Human takeovers'), number_format_i18n( $takeovers ) ),
                array( AACAI_I18n::t('Attributed orders'), number_format_i18n( $attr_orders ) ),
                array( AACAI_I18n::t('Conversion rate'), $conv ),
            ) as $c ) {
                echo '<div class="aacai-metric"><div class="lab">' . esc_html( $c[0] ) . '</div><div class="num">' . $c[1] . '</div></div>';
            }
            echo '</div>';
            echo '<div class="aacai-chart"><div class="lab" style="font-size:12px;color:#6b7280;margin-bottom:8px">'.esc_html(AACAI_I18n::t('Sessions by day (UTC)')).'</div>';
            if ( ! $series ) {
                echo '<p>'.esc_html(AACAI_I18n::t('No daily rows yet.')).'</p>';
            } else {
                echo '<div class="aacai-chart-bars">';
                foreach ( $series as $r ) {
                    $v = (int) $r['sessions_started'];
                    $h = max( 2, (int) round( 140 * $v / $max_sessions ) );
                    echo '<div class="aacai-chart-col"><div class="aacai-chart-bar" style="height:' . esc_attr( $h ) . 'px" title="' . esc_attr( $v ) . '"></div>';
                    echo '<div class="aacai-chart-lbl">' . esc_html( substr( $r['day'], 5 ) ) . '</div></div>';
                }
                echo '</div>';
            }
            echo '</div>';
        } elseif ( $view === 'satisfaction' ) {
            echo '<div class="aacai-metric-row">';
            echo '<div class="aacai-metric neg"><div class="lab">' . esc_html( AACAI_I18n::label( 'negative' ) ) . '</div><div class="num">' . esc_html( number_format_i18n( $sat_neg ) ) . '</div></div>';
            echo '<div class="aacai-metric pos"><div class="lab">' . esc_html( AACAI_I18n::label( 'positive' ) ) . '</div><div class="num">' . esc_html( number_format_i18n( $sat_pos ) ) . '</div></div>';
            echo '<div class="aacai-metric neu"><div class="lab">' . esc_html( AACAI_I18n::label( 'neutral' ) ) . '</div><div class="num">' . esc_html( number_format_i18n( $sat_neu ) ) . '</div></div>';
            echo '</div>';
            echo '<p class="description">'.esc_html(AACAI_I18n::t('Collected when visitors rate after an agent returns the chat to bot or marks Resolve. One rating per session.')).'</p>';
            echo '<div class="aacai-chart"><div style="font-size:12px;color:#6b7280;margin-bottom:8px">'.esc_html(AACAI_I18n::t('Ratings by day')).'</div>';
            if ( ! $series ) {
                echo '<p>'.esc_html(AACAI_I18n::t('No data yet.')).'</p>';
            } else {
                echo '<div class="aacai-chart-bars">';
                foreach ( $series as $r ) {
                    $p = (int) ( $r['sat_positive'] ?? 0 );
                    $n = (int) ( $r['sat_negative'] ?? 0 );
                    $u = (int) ( $r['sat_neutral'] ?? 0 );
                    $tot = $p + $n + $u;
                    $h = $tot > 0 ? max( 8, (int) round( 140 * $tot / $max_sat_day ) ) : 2;
                    echo '<div class="aacai-chart-col"><div class="aacai-chart-stack" style="height:' . esc_attr( $h ) . 'px">';
                    if ( $tot > 0 ) {
                        echo '<div style="flex:' . $p . ';background:#03a84e"></div>';
                        echo '<div style="flex:' . $u . ';background:#f5a623"></div>';
                        echo '<div style="flex:' . $n . ';background:#e53935"></div>';
                    } else {
                        echo '<div style="flex:1;background:#e5e7eb"></div>';
                    }
                    echo '</div><div class="aacai-chart-lbl">' . esc_html( substr( $r['day'], 5 ) ) . '</div></div>';
                }
                echo '</div>';
            }
            echo '</div>';
        } else {
            // sources + conversion
            echo '<div class="aacai-metric-row">';
            echo '<div class="aacai-metric"><div class="lab">'.esc_html(AACAI_I18n::t('Attributed revenue')).'</div><div class="num">' . ( $orders['woo'] || $attr_rev ? wc_price_safe( $attr_rev ) : '—' ) . '</div></div>';
            echo '<div class="aacai-metric"><div class="lab">下單率</div><div class="num">' . esc_html( $conv ) . '</div></div>';
            echo '</div>';
            if ( ! $orders['woo'] ) {
                echo '<div class="notice notice-warning inline aacai-keep-notice"><p>'.esc_html(AACAI_I18n::t('WooCommerce inactive — live order attribution unavailable.')).'</p></div>';
            }
            $labels = array(
                'fixed' => AACAI_I18n::t('Fixed / policy'), 'ai' => AACAI_I18n::t('DeepSeek AI'), 'woocommerce' => 'WooCommerce',
                'guide' => AACAI_I18n::t('Guide'), 'unverified' => AACAI_I18n::t('Unverified'), 'human_queue' => AACAI_I18n::t('Human queue'),
                'agent' => AACAI_I18n::t('Team agent'), 'system' => AACAI_I18n::t('System'),
            );
            $max = max( 1, $src ? max( $src ) : 1 );
            echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th>'.esc_html(AACAI_I18n::t('Source')).'</th><th>'.esc_html(AACAI_I18n::t('Count')).'</th><th>'.esc_html(AACAI_I18n::t('Share')).'</th><th></th></tr></thead><tbody>';
            foreach ( $labels as $k => $lab ) {
                $n = (int) $src[ $k ];
                echo '<tr><td>' . esc_html( $lab ) . '</td><td>' . esc_html( number_format_i18n( $n ) ) . '</td><td>' . esc_html( self::pct( $n, $src_total ) ) . '</td><td style="width:40%">' . self::bar( $n, $max, '#03a84e' ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        self::unanswered_panel( $range );

        echo '</div></div></div>';

    }

    /**
     * R3-1 (1.10.0): Unanswered questions report — visitor questions whose
     * assistant reply was a fallback (source 'unverified': no approved answer
     * found, or the AI service was unavailable). Aggregated by question text
     * over the selected range; each row links to one conversation that asked
     * it. This is the store owner's fixed-reply to-do list.
     */
    public static function unanswered_panel( $range ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aacai_messages';
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.session_key, a.created_at,
                ( SELECT u.message FROM {$table} u
                  WHERE u.session_key = a.session_key AND u.role = 'user' AND u.id < a.id
                  ORDER BY u.id DESC LIMIT 1 ) AS question
             FROM {$table} a
             WHERE a.role = 'assistant' AND a.source = 'unverified'
               AND a.created_at >= %s AND a.created_at <= %s
             ORDER BY a.id DESC LIMIT 1000",
            (string) $range['from'] . ' 00:00:00',
            (string) $range['to'] . ' 23:59:59'
        ) );
        $agg = array();
        if ( is_array( $rows ) ) {
            foreach ( $rows as $r ) {
                $q = trim( (string) ( $r->question ?? '' ) );
                if ( $q === '' ) { continue; }
                $key = mb_strtolower( preg_replace( '/\s+/u', ' ', $q ) );
                if ( ! isset( $agg[ $key ] ) ) {
                    $agg[ $key ] = array( 'question' => $q, 'times' => 0, 'last_at' => (string) $r->created_at, 'session' => (string) $r->session_key );
                }
                $agg[ $key ]['times']++;
            }
        }
        $list = array_values( $agg );
        usort( $list, function ( $a, $b ) {
            if ( $a['times'] !== $b['times'] ) { return $b['times'] - $a['times']; }
            return strcmp( $b['last_at'], $a['last_at'] );
        } );
        $list = array_slice( $list, 0, 50 );

        echo '<h2 style="margin-top:28px">' . esc_html( AACAI_I18n::t( 'Unanswered questions' ) ) . '</h2>';
        echo '<p class="description">' . esc_html( AACAI_I18n::t( 'Visitor questions that received a fallback reply (no approved answer, or the AI service was unavailable) in the selected range. Use this list to decide which fixed replies to add next.' ) ) . '</p>';
        if ( ! $list ) {
            echo '<p>' . esc_html( AACAI_I18n::t( 'No unanswered questions in this range.' ) ) . '</p>';
            return;
        }
        echo '<table class="widefat striped" style="max-width:960px"><thead><tr><th>' . esc_html( AACAI_I18n::t( 'Question' ) ) . '</th><th>' . esc_html( AACAI_I18n::t( 'Times asked' ) ) . '</th><th>' . esc_html( AACAI_I18n::t( 'Last asked (UTC)' ) ) . '</th><th>' . esc_html( AACAI_I18n::t( 'Conversation' ) ) . '</th></tr></thead><tbody>';
        foreach ( $list as $row ) {
            $link = class_exists( 'AACAI_Email_Alerts' ) ? AACAI_Email_Alerts::inbox_url( $row['session'] ) : '';
            echo '<tr><td>' . esc_html( $row['question'] ) . '</td><td>' . esc_html( number_format_i18n( $row['times'] ) ) . '</td><td>' . esc_html( $row['last_at'] ) . '</td><td>'
                . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( AACAI_I18n::t( 'Open' ) ) . '</a>' : '' ) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
}

/** Format money without fatally requiring WooCommerce. */
function wc_price_safe( $amount ) {
    if ( function_exists( 'wc_price' ) ) {
        return wp_kses_post( wc_price( $amount ) );
    }
    return esc_html( number_format( (float) $amount, 2 ) );
}
