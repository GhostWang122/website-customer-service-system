<?php
/**
 * Contacts CRM — capture email/phone from chats (V1.7).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AACAI_Contacts {
    const SCHEMA_OPTION = 'aacai_contacts_schema_version';
    const SCHEMA_VERSION = '1.8.0';
    const PER_PAGE = 50;

    public static function boot() {
        add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 4 );
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 28 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
        add_action( 'admin_post_aacai_contact_save', array( __CLASS__, 'save_manual' ) );
        add_action( 'admin_post_aacai_contact_delete', array( __CLASS__, 'delete' ) );
    }

    public static function enqueue_admin( $hook ) {
        if ( strpos( (string) $hook, 'aacai-contacts' ) === false && strpos( (string) $hook, 'aacai-team-contacts' ) === false ) {
            return;
        }
        $base = trailingslashit( dirname( plugin_dir_url( __FILE__ ) ) ) . 'assets/';
        wp_enqueue_style( 'aacai-admin-shared', $base . 'admin-shared.css', array(), AAC_AI_Assistant::VERSION );
    }

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'aacai_contacts';
    }

    public static function maybe_upgrade() {
        if ( get_option( self::SCHEMA_OPTION ) === self::SCHEMA_VERSION ) { return; }
        self::install();
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(190) NOT NULL DEFAULT '',
            name varchar(190) NOT NULL DEFAULT '',
            phone varchar(64) NOT NULL DEFAULT '',
            last_session_key char(64) NOT NULL DEFAULT '',
            source varchar(32) NOT NULL DEFAULT '',
            last_seen_at datetime NOT NULL,
            created_at datetime NOT NULL,
            notes text NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY last_seen_at (last_seen_at),
            KEY last_session_key (last_session_key)
        ) {$charset};" );
        update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
    }

    public static function admin_menu() {
        add_submenu_page( 'aacai', 'Contacts', 'Contacts', 'manage_options', 'aacai-contacts', array( __CLASS__, 'page' ) );
        $s = (array) get_option( AAC_AI_Assistant::OPTION, array() );
        if ( ( $s['log_access'] ?? 'admins' ) === 'shop_managers' ) {
            add_submenu_page( 'woocommerce', 'Customer Contacts', 'Customer Contacts', 'manage_woocommerce', 'aacai-team-contacts', array( __CLASS__, 'page' ) );
        }
    }

    /** Extract and upsert contact from a chat message body. */
    public static function maybe_capture_from_message( $hashed_session, $role, $body ) {
        if ( $role !== 'user' && $role !== 'agent' ) { return; }
        $body = (string) $body;
        $email = '';
        if ( preg_match( '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $body, $m ) ) {
            $email = strtolower( sanitize_email( $m[0] ) );
        }
        $phone = '';
        if ( preg_match( '/(?:\+?\d[\d\-\s().]{7,}\d)/', $body, $pm ) ) {
            $digits = preg_replace( '/\D+/', '', $pm[0] );
            if ( strlen( $digits ) >= 8 && strlen( $digits ) <= 15 ) {
                $phone = sanitize_text_field( trim( $pm[0] ) );
            }
        }
        if ( $email === '' && $phone === '' ) { return; }
        self::upsert( array(
            'email' => $email,
            'phone' => $phone,
            'name' => $email !== '' ? self::name_from_email( $email ) : '',
            'last_session_key' => $hashed_session,
        ) );
    }

    private static function name_from_email( $email ) {
        $local = strstr( $email, '@', true );
        return $local ? sanitize_text_field( $local ) : '';
    }

    public static function upsert( $data ) {
        global $wpdb;
        self::install();
        $email = isset( $data['email'] ) ? strtolower( sanitize_email( $data['email'] ) ) : '';
        $phone = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';
        $name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        $session = isset( $data['last_session_key'] ) ? preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $data['last_session_key'] ) ) : '';
        $source = isset( $data['source'] ) ? sanitize_key( (string) $data['source'] ) : '';
        if ( strlen( $session ) !== 64 ) { $session = ''; }
        if ( $email === '' && $phone === '' ) { return 0; }
        $now = current_time( 'mysql', true );
        $table = self::table();

        $existing = null;
        if ( $email !== '' ) {
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email=%s", $email ), ARRAY_A );
        }
        if ( ! $existing && $phone !== '' ) {
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE phone=%s AND phone<>'' LIMIT 1", $phone ), ARRAY_A );
        }

        if ( $existing ) {
            $upd = array( 'last_seen_at' => $now );
            if ( $name !== '' && empty( $existing['name'] ) ) { $upd['name'] = $name; }
            if ( $phone !== '' && empty( $existing['phone'] ) ) { $upd['phone'] = $phone; }
            if ( $email !== '' && empty( $existing['email'] ) ) { $upd['email'] = $email; }
            if ( $session !== '' ) { $upd['last_session_key'] = $session; }
            if ( $source !== '' ) { $upd['source'] = $source; }
            $wpdb->update( $table, $upd, array( 'id' => (int) $existing['id'] ) );
            return (int) $existing['id'];
        }

        $wpdb->insert( $table, array(
            'email' => $email,
            'name' => $name !== '' ? $name : ( $email ? self::name_from_email( $email ) : 'Contact' ),
            'phone' => $phone,
            'last_session_key' => $session,
            'source' => $source,
            'last_seen_at' => $now,
            'created_at' => $now,
            'notes' => '',
        ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
        return (int) $wpdb->insert_id;
    }

    public static function page() {
        if ( ! AAC_AI_Conversations::can_view() ) {
            wp_die( 'You do not have permission to view contacts.' );
        }
        self::install();
        global $wpdb;
        $table = self::table();
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $paged = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $where = '1=1';
        $args = array();
        if ( $search !== '' ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= ' AND (email LIKE %s OR name LIKE %s OR phone LIKE %s)';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }
        $sql_count = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        $total = $args ? (int) $wpdb->get_var( $wpdb->prepare( $sql_count, $args ) ) : (int) $wpdb->get_var( $sql_count );
        $pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
        $paged = min( $paged, $pages );
        $offset = ( $paged - 1 ) * self::PER_PAGE;
        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY last_seen_at DESC LIMIT %d OFFSET %d";
        $qargs = array_merge( $args, array( self::PER_PAGE, $offset ) );
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $qargs ), ARRAY_A );
        // P8 (1.11.4): per-contact capture trail, read from the captures table.
        $trail = array();
        if ( $rows && class_exists( 'AACAI_Captures' ) ) {
            $page_emails = array();
            foreach ( (array) $rows as $r ) { if ( ! empty( $r['email'] ) ) { $page_emails[] = (string) $r['email']; } }
            $trail = AACAI_Captures::events_for_emails( $page_emails );
        }

        $page_slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'aacai-contacts';
        echo '<div class="wrap aacai-contacts-wrap">';
        echo '<h1>' . esc_html( AACAI_I18n::label( 'contacts' ) ); if ( class_exists( 'AAC_AI_Assistant' ) ) { AAC_AI_Assistant::render_version_badge(); } echo '</h1>';
        echo '<p class="description">' . esc_html( AACAI_I18n::t( 'Emails and phones captured from chat messages. Searchable for follow-up. No extra PII beyond what visitors typed.' ) ) . '</p>';

        echo '<div class="aacai-admin-shell aacai-contacts-layout">';
        echo '<aside class="aacai-admin-nav aacai-contacts-nav">';
        echo '<button type="button" class="aacai-green-btn" onclick="document.getElementById(\'aacai-add-contact\').hidden=false">+ ' . esc_html( AACAI_I18n::label( 'add_contact' ) ) . '</button>';
        echo '<a class="folder is-active" href="#">' . esc_html( AACAI_I18n::label( 'people' ) ) . '</a>';
        echo '<a class="folder na" href="#">' . esc_html( AACAI_I18n::label( 'organizations' ) ) . ' <span class="aacai-folder-na-tag">' . esc_html( AACAI_I18n::label( 'na' ) ) . '</span></a>';
        echo '</aside>';
        echo '<div class="aacai-admin-main aacai-contacts-main">';

        echo '<div id="aacai-add-contact" class="aacai-box" hidden style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-bottom:14px">';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="aacai_contact_save">';
        wp_nonce_field( 'aacai_contact_save' );
        echo '<p><label>' . esc_html( AACAI_I18n::label( 'name' ) ) . ' <input type="text" name="name" required></label> ';
        echo '<label>' . esc_html( AACAI_I18n::label( 'email' ) ) . ' <input type="email" name="email"></label> ';
        echo '<label>' . esc_html( AACAI_I18n::label( 'phone' ) ) . ' <input type="text" name="phone"></label> ';
        echo '<button class="button button-primary" type="submit">' . esc_html( AACAI_I18n::label( 'save_contact' ) ) . '</button></p>';
        echo '</form></div>';

        echo '<div class="aacai-contacts-toolbar">';
        echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( $page_slug ) . '">';
        echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr( AACAI_I18n::label( 'search_contacts' ) ) . '"> ';
        echo '<button class="button">' . esc_html( AACAI_I18n::label( 'search_btn' ) ) . '</button></form>';
        echo '<span class="description">' . esc_html( number_format_i18n( $total ) ) . ' contact(s)</span>';
        echo '</div>';

        echo '<table class="widefat striped"><thead><tr><th>' . esc_html( AACAI_I18n::label( 'name' ) ) . '</th><th>' . esc_html( AACAI_I18n::label( 'primary_email' ) ) . '</th><th>' . esc_html( AACAI_I18n::label( 'primary_phone' ) ) . '</th><th>Source</th><th>' . esc_html( AACAI_I18n::label( 'last_seen' ) ) . '</th><th>' . esc_html( AACAI_I18n::label( 'last_session' ) ) . '</th></tr></thead><tbody>';
        if ( ! $rows ) {
            echo '<tr><td colspan="6">' . esc_html( AACAI_I18n::label( 'no_contacts' ) ) . '</td></tr>';
        }
        foreach ( (array) $rows as $r ) {
            $initial = strtoupper( substr( (string) ( $r['name'] ?: $r['email'] ?: '?' ), 0, 1 ) );
            $session = (string) $r['last_session_key'];
            $link = '';
            if ( preg_match( '/^[a-f0-9]{64}$/', $session ) ) {
                $link = admin_url( 'admin.php?page=aacai-conversations&session=' . rawurlencode( $session ) );
            }
            echo '<tr>';
            echo '<td><span class="aacai-avatar">' . esc_html( $initial ) . '</span>' . esc_html( $r['name'] ?: '—' ) . '</td>';
            echo '<td>' . esc_html( $r['email'] ?: '—' ) . '</td>';
            echo '<td>' . esc_html( $r['phone'] ?: '—' ) . '</td>';
            echo '<td>' . esc_html( ( $r['source'] ?? '' ) !== '' ? (string) $r['source'] : '—' ) . '</td>';
            echo '<td>' . esc_html( $r['last_seen_at'] ) . '</td>';
            echo '<td>' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( AACAI_I18n::label( 'open_chat' ) ) . '</a>' : '—' ) . '</td>';
            echo '</tr>';
            $events = ( ! empty( $r['email'] ) && isset( $trail[ strtolower( (string) $r['email'] ) ] ) ) ? $trail[ strtolower( (string) $r['email'] ) ] : array();
            if ( $events ) {
                echo '<tr class="aacai-trail-row"><td colspan="6"><details><summary>Capture trail (' . esc_html( count( $events ) ) . ')</summary><ul style="margin:8px 0 4px 18px;list-style:disc">';
                foreach ( $events as $ev ) {
                    $ev_session = (string) ( $ev['session_key'] ?? '' );
                    $ev_link = '';
                    if ( preg_match( '/^[a-f0-9]{64}$/', $ev_session ) ) {
                        $ev_link = ' — <a href="' . esc_url( admin_url( 'admin.php?page=aacai-conversations&session=' . rawurlencode( $ev_session ) ) ) . '">chat</a>';
                    }
                    echo '<li>' . esc_html( (string) $ev['created_at'] ) . ' UTC — <strong>' . esc_html( (string) $ev['source'] ) . '</strong>' . $ev_link . '</li>';
                }
                echo '</ul></details></td></tr>';
            }
        }
        echo '</tbody></table>';

        if ( $pages > 1 ) {
            echo '<p style="margin-top:12px">';
            for ( $i = 1; $i <= min( $pages, 20 ); $i++ ) {
                $u = add_query_arg( array( 'page' => $page_slug, 's' => $search, 'paged' => $i ), admin_url( 'admin.php' ) );
                echo $i === $paged ? '<strong>' . $i . '</strong> ' : '<a href="' . esc_url( $u ) . '">' . $i . '</a> ';
            }
            echo '</p>';
        }
        echo '</div></div></div>';
    }

    public static function save_manual() {
        if ( ! AAC_AI_Conversations::can_view() ) { wp_die( 'Forbidden' ); }
        check_admin_referer( 'aacai_contact_save' );
        self::upsert( array(
            'name' => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
            'email' => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
            'phone' => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
        ) );
        wp_safe_redirect( admin_url( 'admin.php?page=aacai-contacts&saved=1' ) );
        exit;
    }

    public static function delete() {
        // reserved
    }
}
