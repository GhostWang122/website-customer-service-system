<?php
/**
 * Store-owner email alerts (V1.8.8). WhatsApp/CallMeBot deferred.
 * Rate-limited once per session per event type via transients.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AACAI_Email_Alerts {
    const EVENTS = array( 'new_session', 'waiting', 'needs_followup' );

    /** @return array settings from AAC_AI_Assistant option with defaults. */
    private static function settings() {
        $s = (array) get_option( 'aacai_settings', array() );
        return wp_parse_args( $s, array(
            'email_alerts' => 1,
            'email_alerts_to' => '',
            'email_alert_new_session' => 1,
            'email_alert_waiting' => 1,
            'email_alert_needs_followup' => 1,
        ) );
    }

    private static function recipient( $s ) {
        $to = sanitize_email( (string) ( $s['email_alerts_to'] ?? '' ) );
        if ( $to === '' || ! is_email( $to ) ) {
            $to = sanitize_email( (string) get_option( 'admin_email' ) );
        }
        return is_email( $to ) ? $to : '';
    }

    private static function event_enabled( $event, $s ) {
        if ( empty( $s['email_alerts'] ) ) { return false; }
        if ( $event === 'new_session' ) { return ! empty( $s['email_alert_new_session'] ); }
        if ( $event === 'waiting' ) { return ! empty( $s['email_alert_waiting'] ); }
        if ( $event === 'needs_followup' ) { return ! empty( $s['email_alert_needs_followup'] ); }
        return false;
    }

    private static function rate_key( $event, $hashed_session ) {
        return 'aacai_em_' . sanitize_key( $event ) . '_' . substr( (string) $hashed_session, 0, 20 );
    }

    /** Inbox deep link for hashed session. */
    public static function inbox_url( $hashed_session ) {
        $page = current_user_can( 'manage_options' ) || ! is_user_logged_in()
            ? 'aacai-conversations'
            : 'aacai-team-conversations';
        return admin_url( 'admin.php?page=' . $page . '&session=' . rawurlencode( (string) $hashed_session ) );
    }

    /**
     * @param string $event new_session|waiting|needs_followup
     * @param string $hashed_session 64-char hex
     * @param string $snippet last visitor message (optional)
     */
    public static function notify( $event, $hashed_session, $snippet = '' ) {
        $event = sanitize_key( (string) $event );
        if ( ! in_array( $event, self::EVENTS, true ) ) { return false; }
        if ( ! preg_match( '/^[a-f0-9]{64}$/', (string) $hashed_session ) ) { return false; }

        $s = self::settings();
        if ( ! self::event_enabled( $event, $s ) ) { return false; }

        $to = self::recipient( $s );
        if ( $to === '' ) { return false; }

        $rk = self::rate_key( $event, $hashed_session );
        if ( get_transient( $rk ) ) { return false; }

        $short = substr( $hashed_session, 0, 12 );
        $site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $link = self::inbox_url( $hashed_session );
        $snippet = aacai_mb_substr_safe( sanitize_textarea_field( (string) $snippet ), 0, 280 );
        $snippet = trim( preg_replace( '/\s+/u', ' ', $snippet ) );

        $subjects = array(
            'new_session' => '[AstroAndCharm] New chat',
            'waiting' => '[AstroAndCharm] Waiting for agent',
            'needs_followup' => '[AstroAndCharm] Needs follow-up',
        );
        $headlines = array(
            'new_session' => 'A visitor started a new chat.',
            'waiting' => 'A visitor asked for a team member (Waiting).',
            'needs_followup' => 'A chat was marked Needs follow-up.',
        );
        $subject = $subjects[ $event ];
        $body = $site . "\n\n"
            . $headlines[ $event ] . "\n"
            . 'Session: ' . $short . "…\n";
        if ( $snippet !== '' ) {
            $body .= 'Last message: ' . $snippet . "\n";
        }
        $body .= "\nOpen Inbox:\n" . $link . "\n\n"
            . "— AstroAndCharm Customer Assistant\n"
            . "(You receive one email per session per event. WhatsApp via CallMeBot coming later.)\n";

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        $ok = wp_mail( $to, $subject, $body, $headers );
        // Always set rate-limit after attempt to avoid mail storms on misconfig.
        set_transient( $rk, 1, DAY_IN_SECONDS );
        return (bool) $ok;
    }

    /**
     * R3-2 (1.10.0): daily AI cap warning (80% / 100%). Operational alert —
     * respects the master email-alerts toggle; recipient falls back to the
     * site admin email like the other alerts. Sent at most once per level
     * per day (the caller tracks the level state).
     */
    public static function notify_cap( $level, $used, $cap ) {
        $s = self::settings();
        if ( empty( $s['email_alerts'] ) ) { return false; }
        $to = self::recipient( $s );
        if ( $to === '' ) { return false; }
        $level = ( (int) $level >= 100 ) ? 100 : 80;
        $used = (int) $used;
        $cap = max( 1, (int) $cap );
        $pct = (int) floor( $used * 100 / $cap );
        $site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $subject = '[AstroAndCharm] AI daily limit ' . ( $level >= 100 ? 'reached' : 'at ' . $pct . '%' );
        $body = $site . "\n\n"
            . sprintf( "Today's Customer Assistant AI requests: %d of %d (%d%%).", $used, $cap, $pct ) . "\n";
        if ( $level >= 100 ) {
            $body .= "The daily AI limit has been reached. AI answers pause until the daily counter resets (UTC); approved fixed replies are unaffected.\n";
        } else {
            $body .= "Early warning: the assistant is approaching its daily AI limit.\n";
        }
        $body .= "\nUsage & Logs:\n" . admin_url( 'admin.php?page=aacai-usage' ) . "\n\n"
            . "— AstroAndCharm Customer Assistant\n";
        return (bool) wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
    }

    /**
     * R5-1 (1.11.0): email a team member's chat reply to a visitor who opted in
     * with the Waiting email-reply card for this session. Visitor-requested
     * service mail — not gated by the owner-alert toggles.
     */
    public static function email_visitor_reply( $to, $message ) {
        $to = sanitize_email( (string) $to );
        if ( ! is_email( $to ) ) { return false; }
        $subject = 'AstroAndCharm — a reply from our team';
        $body = "Hi,\n\nOur team replied to your chat on AstroAndCharm:\n\n"
            . trim( (string) $message )
            . "\n\nContinue the conversation on our site: https://astroandcharm.com/\n\n— AstroAndCharm";
        return (bool) wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
    }

    /**
     * R5-2 (1.11.0): send a result the visitor asked to receive by email —
     * a bracelet match (name + link) or a reading page link + one-line summary.
     */
    public static function email_result( $to, $kind, $name = '', $url = '' ) {
        $to = sanitize_email( (string) $to );
        if ( ! is_email( $to ) ) { return false; }
        if ( $kind === 'bracelet' ) {
            $subject = 'AstroAndCharm — your bracelet match';
            $body = "Hi,\n\nHere is the bracelet match from your chat on AstroAndCharm:\n\n"
                . sanitize_text_field( (string) $name )
                . ( $url !== '' ? "\n" . esc_url_raw( (string) $url ) : '' )
                . "\n\nOnly used for this result. No marketing emails.\n\n— AstroAndCharm";
        } else {
            $is_astrology = ( $kind === 'astrology' );
            $subject = $is_astrology ? 'AstroAndCharm — your Astrology link' : 'AstroAndCharm — your Tarot link';
            $page = $is_astrology ? 'https://astroandcharm.com/astrology-calculator/' : 'https://astroandcharm.com/tarot-reading/';
            $summary = $is_astrology
                ? 'Astrology: explore the patterns in your birth chart, calculated instantly on the page; a copy is also sent by email after a paid reading.'
                : 'Tarot Reading: bring one specific question and draw three cards on the page for the guided experience.';
            $body = "Hi,\n\nHere is the link from your chat on AstroAndCharm:\n\n" . $summary . "\n" . $page
                . "\n\nOnly used for this result. No marketing emails.\n\n— AstroAndCharm";
        }
        return (bool) wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
    }
}
