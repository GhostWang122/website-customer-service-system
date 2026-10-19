<?php
/** Public-site grounding, never indexes account data or private posts. */
if (!defined('ABSPATH')) { exit; }
final class AACAI_Knowledge {
    const INDEX = 'aacai_kb_index_12';
    const META = 'aacai_kb_meta_12';
    const SETTINGS = 'aacai_kb_settings_12';
    private static function config() {
        return wp_parse_args((array)get_option(self::SETTINGS, array()), array('pages'=>1,'posts'=>0,'exclude'=>'checkout,cart,my-account,privacy-policy,terms-and-conditions','max_docs'=>200,'rendered'=>1));
    }
    public static function boot() {
        add_action('admin_post_aacai_sync_kb', array(__CLASS__,'sync_action'));
        add_action('admin_post_aacai_kb_config', array(__CLASS__,'save_config'));
        add_action('save_post', array(__CLASS__,'update_post'), 50, 2);
        add_action('before_delete_post', array(__CLASS__,'remove_post'));
        add_action('transition_post_status', array(__CLASS__,'status_change'), 15, 3);
    }
    private static function allowed($post) {
        $cfg = self::config();
        if (!$post || $post->post_status !== 'publish') { return false; }
        if ($post->post_type === 'page' && !$cfg['pages']) { return false; }
        if ($post->post_type === 'post' && !$cfg['posts']) { return false; }
        if (!in_array($post->post_type,array('page','post'),true)) { return false; }
        if (post_password_required($post)) { return false; }
        $slug = (string)$post->post_name;
        if (preg_match('/(coming-soon|staging|draft|old-version|test-page)/i',$slug)) { return false; }
        foreach (explode(',',(string)$cfg['exclude']) as $part) {
            $part = strtolower(trim($part));
            if ($part && $slug === $part) { return false; }
        }
        return true;
    }
    private static function clean($v) {
        $v = (string)$v;
        $v = preg_replace('~<(script|style|noscript|iframe|svg)\b[^>]*>.*?</\1\s*>~is',' ',$v);
        $v = strip_shortcodes($v);
        $v = html_entity_decode(wp_strip_all_tags($v), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $v = preg_replace('/\s+/u',' ', $v);
        return trim($v);
    }
    private static function elementor_strings($item,&$parts,$depth=0) {
        if ($depth>20 || !is_array($item) || count($parts)>160) { return; }
        $fields = array('title','editor','text','description','content','tab_content','alert_title','alert_description','button_text','heading','html','faq_question','faq_answer');
        foreach ($item as $key=>$value) {
            if (is_array($value)) { self::elementor_strings($value,$parts,$depth+1); }
            elseif (is_string($value) && in_array((string)$key,$fields,true) && strlen($value)>3) {
                $value=self::clean($value);
                if ($value && strlen($value)>5) { $parts[]=$value; }
            }
        }
    }
    private static function rendered_text($url) {
        if (!function_exists('wp_safe_remote_get')) return '';
        $site=wp_parse_url(home_url(),PHP_URL_HOST);
        $host=wp_parse_url($url,PHP_URL_HOST);
        if (!$host || !$site || strcasecmp($host,$site)!==0) return '';
        $response=wp_safe_remote_get($url,array('timeout'=>4,'redirection'=>0,'limit_response_size'=>1500000,'headers'=>array('Accept'=>'text/html')));
        if (is_wp_error($response) || (int)wp_remote_retrieve_response_code($response)!==200) return '';
        $html=(string)wp_remote_retrieve_body($response);
        if (!$html || strlen($html)>1600000) return '';
        if (!class_exists('DOMDocument')) {
            $fragment=$html;
            if (preg_match('~<main\b[^>]*>(.*?)</main>~is',$html,$m)) $fragment=$m[1];
            elseif (preg_match('~<article\b[^>]*>(.*?)</article>~is',$html,$m)) $fragment=$m[1];
            $fragment=preg_replace('~<(nav|header|footer|form)\b[^>]*>.*?</\1\s*>~is',' ',$fragment);
            return aacai_mb_substr_safe(self::clean($fragment),0,9000);
        }
        $dom=new DOMDocument();
        $before=libxml_use_internal_errors(true);
        $ok=$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();libxml_use_internal_errors($before);
        if (!$ok) return '';
        $xp=new DOMXPath($dom);
        foreach ($xp->query('//script|//style|//noscript|//svg|//nav|//footer|//header|//form|//*[@id="aacai-root"]') as $node) {
            if ($node->parentNode) $node->parentNode->removeChild($node);
        }
        $main=$xp->query('//main|//*[@id="content"]|//article');
        $node=($main && $main->length)?$main->item(0):$dom->getElementsByTagName('body')->item(0);
        return $node ? aacai_mb_substr_safe(self::clean($node->textContent),0,9000) : '';
    }
    private static function capture_slugs($post) {
        return (int)$post->ID === (int)get_option('page_on_front') || in_array((string)$post->post_name,array('tarot-reading','astrology-calculator','bazi-calculator','tarot-vs-astrology','which-bracelet-should-i-wear','shop'),true);
    }
    private static function make_doc($post,$capture=false) {
        if (!self::allowed($post)) { return null; }
        $body = self::clean($post->post_content);
        $el = get_post_meta($post->ID,'_elementor_data',true);
        if ($el) {
            $json = is_array($el) ? $el : json_decode((string)$el,true);
            if (is_array($json)) {
                $parts=[];
                self::elementor_strings($json,$parts);
                $parts = array_unique($parts);
                $extra = implode(' ', $parts);
                if ($extra && $extra !== $body) { $body = $body ? $body . ' ' . $extra : $extra; }
            }
        }
        if ($capture && self::capture_slugs($post)) {
            $rendered=self::rendered_text(get_permalink($post));
            // WPCode-generated landing sections can be absent from post_content and Elementor meta.
            if ($rendered && strlen($rendered)>strlen($body)/2) $body=$rendered;
        }
        $body = aacai_mb_substr_safe($body,0,9000);
        if (strlen($body)<35) { return null; }
        return array('id'=>(int)$post->ID,'title'=>get_the_title($post),'url'=>get_permalink($post),'slug'=>$post->post_name,'text'=>$body,'modified'=>$post->post_modified_gmt);
    }
    public static function sync() {
        $cfg=self::config();
        $types=[];
        if ($cfg['pages']) $types[]='page';
        if ($cfg['posts']) $types[]='post';
        $index=[];
        if ($types) {
            $posts = get_posts(array('post_type'=>$types,'post_status'=>'publish','posts_per_page'=>max(20,min(250,(int)$cfg['max_docs'])),'orderby'=>'modified','order'=>'DESC','suppress_filters'=>false));
            foreach ($posts as $post) {
                $doc=self::make_doc($post,!empty($cfg['rendered']));
                if ($doc) $index[$post->ID]=$doc;
            }
        }
        update_option(self::INDEX,$index,false);
        update_option(self::META,array('updated'=>gmdate('Y-m-d H:i:s'),'count'=>count($index)),false);
        return count($index);
    }
    public static function update_post($post_id,$post) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !in_array($post->post_type,array('page','post'),true)) return;
        $index=(array)get_option(self::INDEX,array());
        $doc=self::make_doc($post);
        if ($doc) $index[$post_id]=$doc;
        else unset($index[$post_id]);
        update_option(self::INDEX,$index,false);
        update_option(self::META,array('updated'=>gmdate('Y-m-d H:i:s'),'count'=>count($index)),false);
    }
    public static function remove_post($id) {
        $index=(array)get_option(self::INDEX,array());
        if (isset($index[$id])) {unset($index[$id]);update_option(self::INDEX,$index,false);}
    }
    public static function status_change($new,$old,$post) {
        if ($new !== $old && in_array($post->post_type,array('page','post'),true)) self::update_post($post->ID,$post);
    }
    public static function sync_action() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('aacai_sync_kb');
        $count=self::sync();
        wp_safe_redirect(add_query_arg(array('page'=>'aacai-knowledge','synced'=>$count),admin_url('admin.php')));exit;
    }
    public static function save_config() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('aacai_kb_config');
        $raw=(isset($_POST['kb']) && is_array($_POST['kb'])) ? wp_unslash($_POST['kb']):array();
        update_option(self::SETTINGS,array('pages'=>!empty($raw['pages'])?1:0,'posts'=>!empty($raw['posts'])?1:0,'exclude'=>sanitize_text_field($raw['exclude']??''),'max_docs'=>max(20,min(250,absint($raw['max_docs']??200))),'rendered'=>!empty($raw['rendered'])?1:0),false);
        self::sync();
        wp_safe_redirect(add_query_arg(array('page'=>'aacai-knowledge','saved'=>'1'),admin_url('admin.php')));exit;
    }
    private static function terms($q) {
        $q=strtolower(remove_accents((string)$q));
        preg_match_all('/[a-z0-9]{3,}|[\x{4e00}-\x{9fff}]{2,}/u',$q,$matches);
        $stop=array('this','that','what','where','when','which','does','have','with','from','please','could','would','about','your','their','really','there','them','they','want','help','show','tell','need','more','some','much','many','very','here','can','how','the','are','and','you','for','site','astroandcharm');
        return array_slice(array_values(array_unique(array_diff($matches[0],$stop))),0,12);
    }
    public static function retrieve($query,$limit=3) {
        $index=(array)get_option(self::INDEX,array());
        $terms=self::terms($query);
        if (!$terms || !$index) return [];
        $found=[];
        foreach ($index as $doc) {
            if (!is_array($doc) || empty($doc['text'])) continue;
            $title=strtolower((string)($doc['title']??''));$slug=strtolower((string)($doc['slug']??''));$body=strtolower((string)$doc['text']);
            $score=0;$first=-1;
            foreach ($terms as $t) {
                if (strpos($title,$t)!==false) $score+=6;
                if (strpos($slug,$t)!==false) $score+=4;
                $at=strpos($body,$t);
                if ($at!==false) {$score+=1; if($first===-1)$first=$at;}
            }
            if ($score<2) continue;
            $start=max(0,$first<0?0:$first-220);
            $found[]=array('score'=>$score,'title'=>(string)$doc['title'],'url'=>esc_url_raw($doc['url']),'excerpt'=>aacai_mb_substr_safe(substr($doc['text'],$start),0,1800));
        }
        usort($found,function($a,$b){return $b['score']<=>$a['score'];});
        return array_slice($found,0,max(1,min(5,(int)$limit)));
    }
    public static function source_page($slug) {
        $p=get_page_by_path($slug,OBJECT,'page');
        if ($p && self::allowed($p)) return get_permalink($p);
        return '';
    }
    public static function page() {
        if(!current_user_can('manage_options')) return;
        $meta=(array)get_option(self::META,array()); $index=(array)get_option(self::INDEX,array());$cfg=self::config();
        echo '<div class="wrap"><h1>Customer Assistant — Website Knowledge</h1><p>Public pages are indexed from your WordPress database and, for key landing pages, optionally via a read-only same-site rendered HTML fetch; WooCommerce prices and inventory are always queried from live product objects. No private orders, drafts, or customer data are indexed.</p>';
        if(isset($_GET['synced'])) echo '<div class="notice notice-success"><p>Indexed '.esc_html(absint($_GET['synced'])).' published pages or posts.</p></div>';
        if(isset($_GET['saved'])) echo '<div class="notice notice-success"><p>Index preferences saved and content refreshed.</p></div>';
        echo '<div style="background:#fff;padding:20px;max-width:950px;border:1px solid #ddd;border-radius:8px"><h2>Index status</h2><p><strong>'.esc_html(count($index)).'</strong> documents | Last sync (UTC): '.esc_html($meta['updated']??'Never').'</p>';
        echo '<p><strong>Important:</strong> Website pages may contain contradictory policy or payment claims. Shipping, returns, promotions, reading fees, and checkout terms must be approved as fixed replies or verified from WooCommerce / the respective plugin. The AI must not quote those amounts based on page text alone.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="aacai_kb_config">';wp_nonce_field('aacai_kb_config');
        echo '<p><label><input type="checkbox" name="kb[pages]" value="1" '.checked($cfg['pages'],1,false).'> Index published WordPress pages</label></p>';
        echo '<p><label><input type="checkbox" name="kb[posts]" value="1" '.checked($cfg['posts'],1,false).'> Also index blog posts (OFF by default: historical posts can contain outdated offers)</label></p>';
        echo '<p><label><input type="checkbox" name="kb[rendered]" value="1" '.checked($cfg['rendered'],1,false).'> Attempt to capture rendered public content for key landing pages (including WPCode sections; uses local HTTP loopback)</label></p>';
        echo '<p><label>Exclude exact slugs (comma-separated)<br><input type="text" name="kb[exclude]" size="90" value="'.esc_attr($cfg['exclude']).'"></label></p>';
        echo '<p><label>Max documents <input type="number" min="20" max="250" name="kb[max_docs]" value="'.esc_attr($cfg['max_docs']).'"></label></p>';
        submit_button('Save and rebuild index');echo '</form><hr>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="aacai_sync_kb">';wp_nonce_field('aacai_sync_kb');submit_button('Sync site knowledge now','secondary');echo '</form>';
        if (!$index) echo '<p><strong>Not indexed yet.</strong> Click Sync site knowledge now before testing website-specific answers.</p>';
        else {echo '<h3>Indexed public URLs</h3><div style="max-height:420px;overflow:auto"><table class="widefat striped"><thead><tr><th>Page</th><th>Updated UTC</th></tr></thead><tbody>';
            foreach(array_slice($index,0,250,true) as $doc) echo '<tr><td><a href="'.esc_url($doc['url']).'" target="_blank" rel="noopener">'.esc_html($doc['title']).'</a></td><td>'.esc_html($doc['modified']).'</td></tr>';
            echo '</tbody></table></div>';
        }
        echo '</div></div>';
    }
}
