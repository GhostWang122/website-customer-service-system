<?php
/**
 * Email captures (V1.11.0, round 5): one-field email opt-ins from the widget —
 * waiting_email_reply (email the team's reply), result_email_bracelet,
 * result_email_reading. Each row ties an email to a hashed session + source so
 * Contacts can be told apart by source and per-source counts can be reported.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AACAI_Captures {
    const SCHEMA_OPTION = 'aacai_captures_schema_version';
    const SCHEMA_VERSION = '1';
    const SOURCES = array( 'waiting_email_reply', 'result_email_bracelet', 'result_email_reading' );

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'aacai_captures';
    }

    public static function install() {
        if ( get_option( self::SCHEMA_OPTION ) === self::SCHEMA_VERSION ) { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_key char(64) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            source varchar(32) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY session_key (session_key),
            KEY source (source),
            KEY created_at (created_at)
        ) {$charset};" );
        update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
    }

    /** Record a capture; returns row id (existing row for an exact duplicate). */
    public static function record( $hashed_session, $email, $source ) {
        self::install();
        global $wpdb;
        $email = strtolower( sanitize_email( (string) $email ) );
        $source = sanitize_key( (string) $source );
        $session = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $hashed_session ) );
        if ( ! is_email( $email ) || ! in_array( $source, self::SOURCES, true ) || strlen( $session ) !== 64 ) {
            return 0;
        }
        $table = self::table();
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE session_key=%s AND email=%s AND source=%s LIMIT 1",
            $session, $email, $source
        ) );
        if ( $existing ) { return (int) $existing; }
        $wpdb->insert( $table, array(
            'session_key' => $session,
            'email' => $email,
            'source' => $source,
            'created_at' => current_time( 'mysql', true ),
        ), array( '%s', '%s', '%s', '%s' ) );
        return (int) $wpdb->insert_id;
    }

    /** Latest email that asked for team replies by email in this session. */
    public static function waiting_email( $hashed_session ) {
        self::install();
        global $wpdb;
        $email = $wpdb->get_var( $wpdb->prepare(
            'SELECT email FROM ' . self::table() . " WHERE session_key=%s AND source='waiting_email_reply' ORDER BY id DESC LIMIT 1",
            (string) $hashed_session
        ) );
        return $email ? strtolower( sanitize_email( (string) $email ) ) : '';
    }

    /**
     * Capture events for a set of emails (P8, 1.11.4): map of
     * email => list of (source, session_key, created_at), oldest first.
     * The captures table has recorded every form capture since 1.11.0.
     */
    public static function events_for_emails( $emails ) {
        self::install();
        global $wpdb;
        $emails = array_values( array_unique( array_filter( array_map( 'strtolower', (array) $emails ) ) ) );
        if ( ! $emails ) { return array(); }
        $placeholders = implode( ',', array_fill( 0, count( $emails ), '%s' ) );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT email, source, session_key, created_at FROM " . self::table() . " WHERE email IN ({$placeholders}) ORDER BY created_at ASC, id ASC",
            $emails
        ), ARRAY_A );
        $out = array();
        foreach ( (array) $rows as $r ) {
            $out[ strtolower( (string) $r['email'] ) ][] = $r;
        }
        return $out;
    }

    /** @return array source => count (all time). */
    public static function counts() {
        self::install();
        global $wpdb;
        $rows = $wpdb->get_results( 'SELECT source, COUNT(*) AS c FROM ' . self::table() . ' GROUP BY source', ARRAY_A );
        $out = array();
        foreach ( self::SOURCES as $src ) { $out[ $src ] = 0; }
        foreach ( (array) $rows as $r ) {
            if ( isset( $out[ (string) $r['source'] ] ) ) { $out[ (string) $r['source'] ] = (int) $r['c']; }
        }
        return $out;
    }
}
