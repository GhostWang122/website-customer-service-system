<?php
/**
 * Agent outbound Chinese → English (V1.7.7).
 * 1) Local CS glossary (longest match first)
 * 2) Free MyMemory MT for residual CJK (no DeepSeek)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AACAI_Translate {

    /**
     * Chinese (zh-TW / zh-CN) → English CS phrasebook.
     * Longer phrases should be listed; apply() sorts by length desc.
     *
     * @return array<string,string>
     */
    public static function glossary() {
        return array(
            // —— Greetings ——
            '您好，很高兴为您服务' => 'Hello, happy to help you.',
            '您好，很高興為您服務' => 'Hello, happy to help you.',
            '您好，有什么可以帮您' => 'Hello, how can I help you?',
            '您好，有什麼可以幫您' => 'Hello, how can I help you?',
            '请问有什么可以帮您' => 'How can I help you?',
            '請問有什麼可以幫您' => 'How can I help you?',
            '有什么可以帮您的吗' => 'Is there anything I can help you with?',
            '有什麼可以幫您的嗎' => 'Is there anything I can help you with?',
            '您好呀' => 'Hello!',
            '您好！' => 'Hello!',
            '您好' => 'Hello',
            '你好呀' => 'Hi!',
            '你好！' => 'Hello!',
            '你好' => 'Hello',
            '嗨您好' => 'Hi there',
            '嗨' => 'Hi',
            '哈咯' => 'Hello',
            '哈囉' => 'Hello',
            '早上好' => 'Good morning',
            '早安' => 'Good morning',
            '下午好' => 'Good afternoon',
            '晚上好' => 'Good evening',
            '午安' => 'Good afternoon',

            // —— Thanks / ack ——
            '非常感谢您的耐心等待' => 'Thank you very much for your patience.',
            '非常感謝您的耐心等待' => 'Thank you very much for your patience.',
            '感谢您的耐心等待' => 'Thank you for your patience.',
            '感謝您的耐心等待' => 'Thank you for your patience.',
            '谢谢您的理解与支持' => 'Thank you for your understanding and support.',
            '謝謝您的理解與支持' => 'Thank you for your understanding and support.',
            '谢谢您的理解' => 'Thank you for your understanding.',
            '謝謝您的理解' => 'Thank you for your understanding.',
            '谢谢您的回覆' => 'Thank you for your reply.',
            '謝謝您的回覆' => 'Thank you for your reply.',
            '谢谢您的回复' => 'Thank you for your reply.',
            '谢谢您的合作' => 'Thank you for your cooperation.',
            '謝謝您的合作' => 'Thank you for your cooperation.',
            '谢谢您' => 'Thank you',
            '謝謝您' => 'Thank you',
            '非常感谢' => 'Thank you very much',
            '非常感謝' => 'Thank you very much',
            '谢谢你' => 'Thank you',
            '謝謝你' => 'Thank you',
            '谢谢' => 'Thank you',
            '謝謝' => 'Thank you',
            '感谢' => 'Thank you',
            '感謝' => 'Thank you',
            '多谢' => 'Thanks',
            '多謝' => 'Thanks',
            '好的，没问题' => 'Okay, no problem.',
            '好的，沒問題' => 'Okay, no problem.',
            '没问题' => 'No problem',
            '沒問題' => 'No problem',
            '好的' => 'Okay',
            '可以的' => 'Sure',
            '当然可以' => 'Of course',
            '當然可以' => 'Of course',
            '收到' => 'Got it',
            '明白了' => 'Understood',
            '了解' => 'Understood',
            '好的呢' => 'Okay',

            // —— Wait / hold ——
            '请稍等片刻，我马上为您查询' => 'Please wait a moment while I check that for you.',
            '請稍等片刻，我馬上為您查詢' => 'Please wait a moment while I check that for you.',
            '请稍等，我马上帮您查订单号' => 'Please wait a moment while I look up your order number',
            '請稍等，我馬上幫您查訂單號' => 'Please wait a moment while I look up your order number',
            '我马上帮您查订单号' => 'I will look up your order number right away',
            '我馬上幫您查訂單號' => 'I will look up your order number right away',
            '请稍等，我马上帮您查' => 'Please wait a moment while I look that up for you',
            '請稍等，我馬上幫您查' => 'Please wait a moment while I look that up for you',
            '请您稍等一下' => 'Please wait a moment.',
            '請您稍等一下' => 'Please wait a moment.',
            '请稍等一下' => 'Please wait a moment.',
            '請稍等一下' => 'Please wait a moment.',
            '请稍候' => 'Please wait a moment.',
            '請稍候' => 'Please wait a moment.',
            '请稍等' => 'Please wait a moment.',
            '請稍等' => 'Please wait a moment.',
            '稍等一下' => 'Just a moment',
            '稍等' => 'One moment',
            '马上就好' => 'It will be ready shortly.',
            '馬上就好' => 'It will be ready shortly.',
            '我马上处理' => 'I will take care of this right away.',
            '我馬上處理' => 'I will take care of this right away.',

            // —— Shipping / delivery (store policy) ——
            '包裹已发货，预计15–30个工作日送达' => 'Your parcel has shipped and should arrive in about 15–30 business days',
            '包裹已發貨，預計15–30個工作日送達' => 'Your parcel has shipped and should arrive in about 15–30 business days',
            '包裹已发货，预计15-30个工作日送达' => 'Your parcel has shipped and should arrive in about 15–30 business days',
            '包裹已發貨，預計15-30個工作日送達' => 'Your parcel has shipped and should arrive in about 15–30 business days',
            '预计15–30个工作日送达' => 'estimated delivery is 15–30 business days',
            '預計15–30個工作日送達' => 'estimated delivery is 15–30 business days',
            '预计15-30个工作日送达' => 'estimated delivery is 15–30 business days',
            '預計15-30個工作日送達' => 'estimated delivery is 15–30 business days',
            '15–30个工作日' => '15–30 business days',
            '15–30個工作日' => '15–30 business days',
            '15-30个工作日' => '15–30 business days',
            '15-30個工作日' => '15–30 business days',
            '十五到三十个工作日' => '15–30 business days',
            '十五到三十個工作日' => '15–30 business days',
            '满80美元免运费' => 'free shipping on eligible orders of $80 or more',
            '滿80美元免運費' => 'free shipping on eligible orders of $80 or more',
            '满$80免运费' => 'free shipping on eligible orders of $80 or more',
            '滿$80免運費' => 'free shipping on eligible orders of $80 or more',
            '订单满80免运' => 'free shipping on eligible orders of $80 or more',
            '訂單滿80免運' => 'free shipping on eligible orders of $80 or more',
            '免运费门槛是80美元' => 'The free-shipping threshold is $80',
            '免運費門檻是80美元' => 'The free-shipping threshold is $80',
            '最终运费以结账页面为准' => 'Final shipping options and charges are confirmed at checkout',
            '最終運費以結帳頁面為準' => 'Final shipping options and charges are confirmed at checkout',
            '最终运费以结算页为准' => 'Final shipping options and charges are confirmed at checkout',
            '物流时效以实际承运为准' => 'Delivery timing depends on the carrier and your location',
            '物流時效以實際承運為準' => 'Delivery timing depends on the carrier and your location',
            '包裹已经发货' => 'Your parcel has already shipped',
            '包裹已經發貨' => 'Your parcel has already shipped',
            '包裹已发货' => 'Your parcel has shipped',
            '包裹已發貨' => 'Your parcel has shipped',
            '已经发货' => 'has already shipped',
            '已經發貨' => 'has already shipped',
            '正在准备发货' => 'We are preparing your order for shipment',
            '正在準備發貨' => 'We are preparing your order for shipment',
            '正在处理您的订单' => 'We are processing your order',
            '正在處理您的訂單' => 'We are processing your order',
            '订单已发货' => 'Your order has shipped',
            '訂單已發貨' => 'Your order has shipped',
            '查不到物流信息' => 'I cannot find tracking information yet',
            '查不到物流資訊' => 'I cannot find tracking information yet',
            '物流信息' => 'tracking information',
            '物流資訊' => 'tracking information',
            '运单号' => 'tracking number',
            '運單號' => 'tracking number',
            '快递单号' => 'tracking number',
            '快遞單號' => 'tracking number',
            '工作日送达' => 'business days for delivery',
            '工作日送達' => 'business days for delivery',
            '免运费' => 'free shipping',
            '免運費' => 'free shipping',
            '包邮' => 'free shipping',
            '包郵' => 'free shipping',
            '配送时间' => 'delivery time',
            '配送時間' => 'delivery time',
            '运送时间' => 'shipping time',
            '運送時間' => 'shipping time',
            '发货' => 'shipment',
            '發貨' => 'shipment',

            // —— Returns / refunds ——
            '我们提供30天退换货服务' => 'We offer a 30-day returns and exchanges window',
            '我們提供30天退換貨服務' => 'We offer a 30-day returns and exchanges window',
            '30天内可申请退换货' => 'You may request a return or exchange within 30 days',
            '30天內可申請退換貨' => 'You may request a return or exchange within 30 days',
            '退换货期限为30天' => 'The returns and exchanges window is 30 days',
            '退換貨期限為30天' => 'The returns and exchanges window is 30 days',
            '退货期限是30天' => 'The return window is 30 days',
            '退貨期限是30天' => 'The return window is 30 days',
            '30天退换货' => '30-day returns and exchanges',
            '30天退換貨' => '30-day returns and exchanges',
            '申请退款' => 'request a refund',
            '申請退款' => 'request a refund',
            '办理退货' => 'process a return',
            '辦理退貨' => 'process a return',
            '退换货' => 'returns and exchanges',
            '退換貨' => 'returns and exchanges',
            '退货政策' => 'return policy',
            '退貨政策' => 'return policy',
            '退款政策' => 'refund policy',
            '可以退货吗' => 'Can I return this?',
            '可以退貨嗎' => 'Can I return this?',
            '退货' => 'return',
            '退貨' => 'return',
            '退款' => 'refund',
            '换货' => 'exchange',
            '換貨' => 'exchange',

            // —— Order status / account ——
            '请问您的订单号是多少' => 'May I have your order number?',
            '請問您的訂單號是多少' => 'May I have your order number?',
            '请提供您的订单号' => 'Please provide your order number.',
            '請提供您的訂單號' => 'Please provide your order number.',
            '请提供订单编号' => 'Please provide the order number.',
            '請提供訂單編號' => 'Please provide the order number.',
            '订单状态' => 'order status',
            '訂單狀態' => 'order status',
            '订单号' => 'order number',
            '訂單號' => 'order number',
            '订单编号' => 'order number',
            '訂單編號' => 'order number',
            '我的账户' => 'My Account',
            '我的帳戶' => 'My Account',
            '请登录我的账户查看' => 'Please sign in to My Account to check',
            '請登入我的帳戶查看' => 'Please sign in to My Account to check',
            '请查收确认邮件' => 'Please check your confirmation email',
            '請查收確認郵件' => 'Please check your confirmation email',
            '支付成功' => 'payment successful',
            '支付失敗' => 'payment failed',
            '支付失败' => 'payment failed',
            '未付款' => 'unpaid',
            '已付款' => 'paid',
            '已取消' => 'cancelled',
            '已完成' => 'completed',
            '处理中' => 'processing',
            '處理中' => 'processing',

            // —— Apology / empathy ——
            '非常抱歉给您带来不便' => 'We are very sorry for the inconvenience.',
            '非常抱歉給您帶來不便' => 'We are very sorry for the inconvenience.',
            '抱歉给您带来不便' => 'Sorry for the inconvenience.',
            '抱歉給您帶來不便' => 'Sorry for the inconvenience.',
            '对此造成的不便深表歉意' => 'We sincerely apologize for the inconvenience this has caused.',
            '對此造成的不便深表歉意' => 'We sincerely apologize for the inconvenience this has caused.',
            '很抱歉让您久等了' => 'Sorry to keep you waiting.',
            '很抱歉讓您久等了' => 'Sorry to keep you waiting.',
            '不好意思' => 'Sorry about that',
            '非常抱歉' => 'We are very sorry',
            '很抱歉' => 'I am sorry',
            '抱歉' => 'Sorry',

            // —— Security / privacy ——
            '请勿在聊天中提供密码或完整银行卡号' => 'Please do not share passwords or full payment card numbers in this chat.',
            '請勿在聊天中提供密碼或完整銀行卡號' => 'Please do not share passwords or full payment card numbers in this chat.',
            '请不要发送银行卡信息' => 'Please do not send payment card details.',
            '請不要發送銀行卡資訊' => 'Please do not send payment card details.',
            '请勿分享密码' => 'Please do not share your password.',
            '請勿分享密碼' => 'Please do not share your password.',

            // —— Closing / goodbye ——
            '祝您购物愉快' => 'Enjoy your shopping!',
            '祝您購物愉快' => 'Enjoy your shopping!',
            '祝您有美好的一天' => 'Have a wonderful day!',
            '如果还有其他问题随时联系我们' => 'If you have any other questions, feel free to contact us anytime.',
            '如果還有其他問題隨時聯絡我們' => 'If you have any other questions, feel free to contact us anytime.',
            '还有其他可以帮您的吗' => 'Is there anything else I can help you with?',
            '還有其他可以幫您的嗎' => 'Is there anything else I can help you with?',
            '再见' => 'Goodbye',
            '再見' => 'Goodbye',
            '拜拜' => 'Bye',
            '晚安' => 'Good night',

            // —— Common CS connectors ——
            '根据我们的政策' => 'According to our policy',
            '根據我們的政策' => 'According to our policy',
            '关于您的订单' => 'Regarding your order',
            '關於您的訂單' => 'Regarding your order',
            '关于运费' => 'Regarding shipping',
            '關於運費' => 'Regarding shipping',
            '关于退货' => 'Regarding returns',
            '關於退貨' => 'Regarding returns',
            '我来帮您确认一下' => 'Let me confirm that for you.',
            '我來幫您確認一下' => 'Let me confirm that for you.',
            '我帮您查一下' => 'Let me check that for you.',
            '我幫您查一下' => 'Let me check that for you.',
            '请您提供更多信息' => 'Please provide a bit more information.',
            '請您提供更多資訊' => 'Please provide a bit more information.',
            '方便留下您的邮箱吗' => 'Could you share your email address?',
            '方便留下您的郵箱嗎' => 'Could you share your email address?',
            '客服' => 'customer support',
            '人工客服' => 'live agent',
            '在线客服' => 'live chat',
            '線上客服' => 'live chat',
        );
    }

    public static function text_has_cjk( $text ) {
        return (bool) preg_match( '/\p{Han}/u', (string) $text );
    }

    /** @return int */
    public static function glossary_size() {
        return count( self::glossary() );
    }

    /**
     * Apply glossary: longest Chinese keys first (substring replace).
     * Returns array( 'text' => string, 'hits' => int ).
     */
    public static function apply_glossary( $text ) {
        $text = (string) $text;
        $map = self::glossary();
        uksort(
            $map,
            static function ( $a, $b ) {
                $la = function_exists( 'mb_strlen' ) ? mb_strlen( $a, 'UTF-8' ) : strlen( $a );
                $lb = function_exists( 'mb_strlen' ) ? mb_strlen( $b, 'UTF-8' ) : strlen( $b );
                if ( $la === $lb ) {
                    return strcmp( $b, $a );
                }
                return $lb <=> $la;
            }
        );
        $hits = 0;
        foreach ( $map as $zh => $en ) {
            if ( $zh === '' ) { continue; }
            if ( function_exists( 'mb_strpos' ) ) {
                if ( mb_strpos( $text, $zh, 0, 'UTF-8' ) === false ) { continue; }
            } elseif ( strpos( $text, $zh ) === false ) {
                continue;
            }
            $count = 0;
            $text = str_replace( $zh, $en, $text, $count );
            $hits += (int) $count;
        }
        // Normalize leftover full-width punctuation common after mixed replace.
        $text = str_replace(
            array( '，', '。', '！', '？', '：', '；', '、', '（', '）' ),
            array( ', ', '. ', '! ', '? ', ': ', '; ', ', ', '(', ')' ),
            $text
        );
        $text = preg_replace( '/[ \t]{2,}/u', ' ', $text );
        $text = preg_replace( '/\s+([,.!?])/u', '$1', $text );
        // Lowercase English that starts right after a comma/semicolon (e.g. "Hello, Your" → "Hello, your").
        $text = preg_replace_callback(
            '/([,;:]\s+)([A-Z])/',
            static function ( $m ) {
                return $m[1] . strtolower( $m[2] );
            },
            $text
        );
        // Space after sentence-ending punctuation when glued to the next word.
        $text = preg_replace( '/([.!?])([A-Za-z])/u', '$1 $2', $text );
        $text = preg_replace( '/\.{2,}/u', '.', $text );
        $text = preg_replace( '/([.!?])\s*\1+/u', '$1', $text );
        $text = preg_replace( '/[ \t]{2,}/u', ' ', $text );
        // Capitalize start of a new sentence.
        $text = preg_replace_callback(
            '/([.!?]\s+)([a-z])/u',
            static function ( $m ) {
                return $m[1] . strtoupper( $m[2] );
            },
            $text
        );
        return array( 'text' => trim( (string) $text ), 'hits' => $hits );
    }

    /**
     * Free MyMemory Translation API (no DeepSeek).
     * Tries zh-CN|en then zh-TW|en. Returns '' on failure.
     */
    /**
     * Generic MyMemory pair (no DeepSeek). Used for ES↔EN policy routing.
     * @param string $text
     * @param string $langpair e.g. es|en or en|es
     * @return string translated or ''
     */
    public static function mymemory_translate( $text, $langpair ) {
        $text = trim( (string) $text );
        $langpair = trim( (string) $langpair );
        if ( $text === '' || $langpair === '' ) { return ''; }
        $chunks = self::chunk_for_mt( $text, 450 );
        $parts = array();
        foreach ( $chunks as $chunk ) {
            $tr = self::mymemory_request( $chunk, $langpair );
            if ( $tr === '' ) { return ''; }
            $parts[] = $tr;
        }
        $out = trim( implode( ' ', $parts ) );
        return aacai_mb_substr_safe( $out, 0, 4000 );
    }

    public static function mymemory_to_english( $text ) {

        $text = trim( (string) $text );
        if ( $text === '' ) { return ''; }
        // Free tier ~500 bytes; chunk on paragraph/sentence if needed.
        $chunks = self::chunk_for_mt( $text, 450 );
        $parts = array();
        foreach ( $chunks as $chunk ) {
            $tr = self::mymemory_request( $chunk, 'zh-CN|en' );
            if ( $tr === '' || self::text_has_cjk( $tr ) ) {
                $tr2 = self::mymemory_request( $chunk, 'zh-TW|en' );
                if ( $tr2 !== '' ) { $tr = $tr2; }
            }
            if ( $tr === '' ) { return ''; }
            $parts[] = $tr;
        }
        $out = trim( implode( ' ', $parts ) );
        return aacai_mb_substr_safe( $out, 0, 4000 );
    }

    /** @return list<string> */
    private static function chunk_for_mt( $text, $max_bytes ) {
        $text = (string) $text;
        if ( strlen( $text ) <= $max_bytes ) { return array( $text ); }
        $chunks = array();
        $buf = '';
        $parts = preg_split( '/(?<=[。！？.!?\n])/u', $text ) ?: array( $text );
        foreach ( $parts as $p ) {
            if ( $p === '' ) { continue; }
            if ( strlen( $buf ) + strlen( $p ) > $max_bytes && $buf !== '' ) {
                $chunks[] = $buf;
                $buf = $p;
            } else {
                $buf .= $p;
            }
            if ( strlen( $buf ) > $max_bytes ) {
                // Hard split oversized segment.
                while ( strlen( $buf ) > $max_bytes ) {
                    $chunks[] = substr( $buf, 0, $max_bytes );
                    $buf = substr( $buf, $max_bytes );
                }
            }
        }
        if ( $buf !== '' ) { $chunks[] = $buf; }
        return $chunks ?: array( $text );
    }

    private static function mymemory_request( $text, $langpair ) {
        $url = add_query_arg(
            array(
                'q' => $text,
                'langpair' => $langpair,
            ),
            'https://api.mymemory.translated.net/get'
        );
        $result = wp_remote_get(
            $url,
            array(
                'timeout' => 6,
                'redirection' => 0,
                'headers' => array( 'Accept' => 'application/json' ),
                'user-agent' => 'AstroAndCharm-CustomerAssistant/1.7.7',
            )
        );
        if ( is_wp_error( $result ) ) { return ''; }
        $code = wp_remote_retrieve_response_code( $result );
        $data = json_decode( wp_remote_retrieve_body( $result ), true );
        if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) { return ''; }
        $tr = '';
        if ( ! empty( $data['responseData']['translatedText'] ) ) {
            $tr = trim( (string) $data['responseData']['translatedText'] );
        }
        if ( $tr === '' ) { return ''; }
        // MyMemory error strings.
        if ( stripos( $tr, 'PLEASE SELECT TWO DISTINCT LANGUAGES' ) !== false ) { return ''; }
        if ( stripos( $tr, 'MYMEMORY WARNING' ) !== false ) { return ''; }
        if ( stripos( $tr, 'INVALID LANGUAGE PAIR' ) !== false ) { return ''; }
        $tr = trim( wp_strip_all_tags( $tr ) );
        // If "translation" is identical and still CJK-heavy, treat as failure for this pair.
        if ( $tr === $text && self::text_has_cjk( $tr ) ) { return ''; }
        return $tr;
    }

    /**
     * Full agent outbound pipeline: glossary → MyMemory for residual CJK.
     *
     * @return array{visitor_message:string,original_message:string,translated:bool,notice:string,method:string}
     */
    public static function prepare_agent_outbound( $typed ) {
        $typed = aacai_mb_substr_safe( sanitize_textarea_field( (string) $typed ), 0, 4000 );
        $out = array(
            'visitor_message' => $typed,
            'original_message' => '',
            'translated' => false,
            'notice' => '',
            'method' => 'none',
        );
        if ( $typed === '' ) { return $out; }
        if ( ! self::text_has_cjk( $typed ) ) { return $out; }

        $g = self::apply_glossary( $typed );
        $after = $g['text'];
        $method = $g['hits'] > 0 ? 'glossary' : 'none';

        if ( self::text_has_cjk( $after ) ) {
            $mt = self::mymemory_to_english( $after );
            if ( $mt !== '' && ! self::text_has_cjk( $mt ) ) {
                $after = $mt;
                $method = ( $g['hits'] > 0 ) ? 'glossary+mymemory' : 'mymemory';
            } elseif ( $mt !== '' && $mt !== $after ) {
                // Partial MT (may still contain some CJK) — prefer if fewer Han chars.
                $after_han = preg_match_all( '/\p{Han}/u', $after );
                $mt_han = preg_match_all( '/\p{Han}/u', $mt );
                if ( $mt_han < $after_han ) {
                    $after = $mt;
                    $method = ( $g['hits'] > 0 ) ? 'glossary+mymemory' : 'mymemory';
                }
            } elseif ( $g['hits'] === 0 ) {
                $out['notice'] = 'Translation unavailable; visitor received your original text.';
                return $out;
            } else {
                // Glossary helped but residual CJK remains and MT failed — still send partial EN.
                $out['notice'] = 'Free translation partially unavailable; visitor received glossary-based English (some Chinese may remain).';
            }
        }

        $after = trim( (string) $after );
        if ( $after === '' || $after === $typed ) {
            if ( empty( $out['notice'] ) ) {
                $out['notice'] = 'Translation unavailable; visitor received your original text.';
            }
            return $out;
        }

        $out['visitor_message'] = $after;
        $out['original_message'] = $typed;
        $out['translated'] = true;
        $out['method'] = $method;
        return $out;
    }

    /** Detect mostly Latin / English text (admin Inbox EN→zh-TW candidate). */
    public static function is_primarily_latin( $text ) {
        $text = trim( (string) $text );
        if ( $text === '' ) { return false; }
        if ( self::text_has_cjk( $text ) ) {
            $han = preg_match_all( '/\p{Han}/u', $text );
            $latin = preg_match_all( '/[A-Za-z]/', $text );
            if ( $han > 0 && $han >= $latin ) { return false; }
        }
        return (bool) preg_match( '/[A-Za-z]/', $text );
    }

    /**
     * Reverse glossary: English → Traditional Chinese (prefer TW forms).
     *
     * @return array<string,string>
     */
    public static function reverse_glossary() {
        static $rev = null;
        if ( $rev !== null ) { return $rev; }
        $rev = array();
        $extra = array(
            'Hello, happy to help you.' => '您好，很高興為您服務。',
            'Hello, how can I help you?' => '您好，有什麼可以幫您？',
            'How can I help you?' => '請問有什麼可以幫您？',
            'Thank you for your patience.' => '感謝您的耐心等待。',
            'Thank you very much for your patience.' => '非常感謝您的耐心等待。',
            'Please wait a moment.' => '請稍等。',
            'Please wait a moment while I check that for you.' => '請稍等片刻，我馬上為您查詢。',
            'estimated delivery is 15–30 business days' => '預計15–30個工作日送達',
            '15–30 business days' => '15–30個工作日',
            'free shipping on eligible orders of $80 or more' => '滿80美元免運費',
            'We offer a 30-day returns and exchanges window' => '我們提供30天退換貨服務',
            'The return window is 30 days' => '退貨期限是30天',
            'May I have your order number?' => '請問您的訂單號是多少？',
            'Please provide your order number.' => '請提供您的訂單號。',
            'Sorry for the inconvenience.' => '抱歉給您帶來不便。',
            'We are very sorry for the inconvenience.' => '非常抱歉給您帶來不便。',
            'Enjoy your shopping!' => '祝您購物愉快！',
            'Have a wonderful day!' => '祝您有美好的一天！',
            'If you have any other questions, feel free to contact us anytime.' => '如果還有其他問題隨時聯絡我們。',
            'Is there anything else I can help you with?' => '還有其他可以幫您的嗎？',
            'Got it' => '收到',
            'Understood' => '明白了',
            'No problem' => '沒問題',
            'Okay' => '好的',
            'Thank you' => '謝謝',
            'Hello' => '您好',
            'Hi' => '嗨',
            'Goodbye' => '再見',
            'order number' => '訂單號',
            'order status' => '訂單狀態',
            'tracking number' => '運單號',
            'tracking information' => '物流資訊',
            'free shipping' => '免運費',
            'return policy' => '退貨政策',
            'refund' => '退款',
            'exchange' => '換貨',
            'return' => '退貨',
            'live agent' => '人工客服',
            'customer support' => '客服',
        );
        foreach ( $extra as $en => $zh ) {
            $rev[ $en ] = $zh;
        }
        foreach ( self::glossary() as $zh => $en ) {
            $en = trim( (string) $en );
            $zh = (string) $zh;
            if ( $en === '' || $zh === '' ) { continue; }
            if ( ! isset( $rev[ $en ] ) ) {
                $rev[ $en ] = $zh;
                continue;
            }
            if ( self::looks_traditional( $zh ) && ! self::looks_traditional( $rev[ $en ] ) ) {
                $rev[ $en ] = $zh;
            }
        }
        return $rev;
    }

    private static function looks_traditional( $zh ) {
        return (bool) preg_match( '/[們為來時開關這過嗎說對貨運詢務歡謝見長樣經還會點擊頁網路換銀郵確認備註訪問題難實際載體簽證碼後東國產業專屬總計價錢幣優滿遞預約個內辦條裡戶帳單號發門檻]/u', (string) $zh );
    }

    /** Apply reverse glossary (longest EN keys first). */
    public static function apply_reverse_glossary( $text ) {
        $text = (string) $text;
        $map = self::reverse_glossary();
        uksort(
            $map,
            static function ( $a, $b ) {
                $la = function_exists( 'mb_strlen' ) ? mb_strlen( $a, 'UTF-8' ) : strlen( $a );
                $lb = function_exists( 'mb_strlen' ) ? mb_strlen( $b, 'UTF-8' ) : strlen( $b );
                if ( $la === $lb ) { return strcmp( $b, $a ); }
                return $lb <=> $la;
            }
        );
        $hits = 0;
        foreach ( $map as $en => $zh ) {
            if ( $en === '' ) { continue; }
            $len = function_exists( 'mb_strlen' ) ? mb_strlen( $en, 'UTF-8' ) : strlen( $en );
            $is_multi = ( strpos( $en, ' ' ) !== false ) || $len > 12;
            if ( $is_multi ) {
                if ( stripos( $text, $en ) === false ) { continue; }
                $count = 0;
                $text = str_ireplace( $en, $zh, $text, $count );
                $hits += (int) $count;
            } else {
                $pattern = '/\b' . preg_quote( $en, '/' ) . '\b/i';
                $n = 0;
                $text = preg_replace( $pattern, $zh, $text, -1, $n );
                $hits += (int) $n;
            }
        }
        return array( 'text' => trim( (string) $text ), 'hits' => $hits );
    }

    /** Lightweight Simplified → Traditional for common CS chars after en|zh-CN fallback. */
    public static function simplify_to_traditional( $text ) {
        $map = array(
            '们' => '們', '为' => '為', '么' => '麼', '来' => '來', '时' => '時', '开' => '開',
            '关' => '關', '过' => '過', '这' => '這', '说' => '說', '对' => '對', '货' => '貨',
            '运' => '運', '费' => '費', '询' => '詢', '务' => '務', '达' => '達', '发' => '發',
            '门' => '門', '槛' => '檻', '账' => '帳', '单' => '單', '号' => '號', '请' => '請',
            '帮' => '幫', '订' => '訂', '购' => '購', '欢' => '歡', '谢' => '謝', '见' => '見',
            '长' => '長', '样' => '樣', '经' => '經', '还' => '還', '会' => '會', '点' => '點',
            '击' => '擊', '页' => '頁', '网' => '網', '络' => '絡', '换' => '換', '银' => '銀',
            '邮' => '郵', '确' => '確', '认' => '認', '处' => '處', '备' => '備', '注' => '註',
            '访' => '訪', '问' => '問', '题' => '題', '难' => '難', '实' => '實', '际' => '際',
            '载' => '載', '体' => '體', '签' => '簽', '证' => '證', '码' => '碼', '复' => '覆',
            '后' => '後', '东' => '東', '里' => '裡', '国' => '國', '产' => '產', '业' => '業',
            '专' => '專', '属' => '屬', '总' => '總', '计' => '計', '价' => '價', '钱' => '錢',
            '币' => '幣', '优' => '優', '满' => '滿', '递' => '遞', '预' => '預', '约' => '約',
            '个' => '個', '内' => '內', '办' => '辦', '条' => '條', '户' => '戶', '线' => '線',
            '兴' => '興',
        );
        return strtr( (string) $text, $map );
    }

    /**
     * MyMemory EN → zh-TW (fallback en|zh-CN then simplify_to_traditional).
     * Returns '' on failure. No DeepSeek.
     */
    public static function mymemory_to_zh_tw( $text ) {
        $text = trim( (string) $text );
        if ( $text === '' ) { return ''; }
        $chunks = self::chunk_for_mt( $text, 450 );
        $parts = array();
        foreach ( $chunks as $chunk ) {
            $tr = self::mymemory_request( $chunk, 'en|zh-TW' );
            if ( $tr === '' ) {
                $tr2 = self::mymemory_request( $chunk, 'en|zh-CN' );
                if ( $tr2 !== '' ) {
                    $tr = self::simplify_to_traditional( $tr2 );
                }
            }
            if ( $tr === '' ) { return ''; }
            $parts[] = $tr;
        }
        $out = trim( implode( '', $parts ) );
        $out = preg_replace( '/[ \t]{2,}/u', ' ', $out );
        return aacai_mb_substr_safe( $out, 0, 4000 );
    }

    /**
     * Translate English → Traditional Chinese for admin Inbox.
     * Cache via transient by text hash (DB column filled by caller).
     *
     * @return string zh-TW or '' on soft fail
     */
    public static function translate_en_to_zh_tw( $english ) {
        $english = trim( (string) $english );
        if ( $english === '' || ! self::is_primarily_latin( $english ) ) { return ''; }

        $hash = md5( $english );
        $tkey = 'aacai_enzh_' . $hash;
        $cached = get_transient( $tkey );
        if ( is_string( $cached ) && $cached !== '' ) {
            return $cached;
        }

        $rev = self::reverse_glossary();
        foreach ( $rev as $en => $zh ) {
            if ( strcasecmp( trim( $en ), $english ) === 0 ) {
                set_transient( $tkey, $zh, WEEK_IN_SECONDS );
                return $zh;
            }
        }

        $g = self::apply_reverse_glossary( $english );
        $after = $g['text'];
        if ( self::is_primarily_latin( $after ) || ! self::text_has_cjk( $after ) ) {
            $mt = self::mymemory_to_zh_tw( $english );
            if ( $mt !== '' && self::text_has_cjk( $mt ) ) {
                $after = $mt;
            } elseif ( ! ( $g['hits'] > 0 && self::text_has_cjk( $after ) ) ) {
                return '';
            }
        }

        $after = trim( (string) $after );
        if ( $after === '' || $after === $english ) { return ''; }
        set_transient( $tkey, $after, WEEK_IN_SECONDS );
        return $after;
    }

    /**
     * Enrich one Inbox message for bilingual admin display.
     *
     * @param array $item keys: id, role, message, original_message?, admin_zh_tw?
     * @return array
     */
    public static function enrich_admin_message( array $item ) {
        $role = (string) ( $item['role'] ?? '' );
        $message = trim( (string) ( $item['message'] ?? '' ) );
        $original = trim( (string) ( $item['original_message'] ?? '' ) );
        $cached_zh = trim( (string) ( $item['admin_zh_tw'] ?? '' ) );
        $id = (int) ( $item['id'] ?? 0 );

        // Agent typed ZH → show ZH primary + EN visitor copy (no retranslate).
        if ( $role === 'agent' && $original !== '' && self::text_has_cjk( $original ) ) {
            $item['admin_display'] = $original;
            $item['zh_tw'] = $original;
            $item['en_original'] = $message;
            $item['bilingual'] = ( $message !== '' && $message !== $original );
            return $item;
        }

        // Pure CJK already.
        if ( $message !== '' && self::text_has_cjk( $message ) && ! self::is_primarily_latin( $message ) ) {
            $item['admin_display'] = $message;
            $item['zh_tw'] = $message;
            $item['bilingual'] = false;
            return $item;
        }

        // user / assistant / agent / system English → zh-TW.
        if ( in_array( $role, array( 'user', 'assistant', 'bot', 'agent', 'system' ), true )
            && self::is_primarily_latin( $message ) ) {
            $zh = $cached_zh;
            if ( $zh === '' ) {
                $zh = self::translate_en_to_zh_tw( $message );
                if ( $zh !== '' && $id > 0 && class_exists( 'AAC_AI_Conversations' ) ) {
                    AAC_AI_Conversations::save_admin_zh_tw( $id, $zh );
                }
            }
            if ( $zh !== '' ) {
                $item['admin_display'] = $zh;
                $item['zh_tw'] = $zh;
                $item['en_original'] = $message;
                $item['admin_zh_tw'] = $zh;
                $item['bilingual'] = true;
                return $item;
            }
        }

        $item['admin_display'] = $message;
        $item['bilingual'] = false;
        return $item;
    }
}
