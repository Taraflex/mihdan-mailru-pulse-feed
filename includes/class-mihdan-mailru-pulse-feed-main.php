<?php
/**
 * Created by PhpStorm.
 * User: mihdan
 * Date: 06.02.19
 * Time: 21:57
 */

class Mihdan_Mailru_Pulse_Feed_Main
{

    private $slug;
    private $feedname;
    private $allowable_tags = array(
        'p' => array()
    );

    public function __construct()
    {
        $this->setup();
        $this->hooks();
    }

    private function setup()
    {
        $this->slug = str_replace('-', '_', MIHDAN_MAILRU_PULSE_FEED_SLUG);
    }

    private function hooks()
    {
        add_action('init', array($this, 'init'));
        add_action('init', array($this, 'flush_rewrite_rules'), 99);
        add_action('after_setup_theme', array($this, 'after_setup_theme'));
        add_filter('wpseo_include_rss_footer', array($this, 'hide_wpseo_rss_footer'));
        add_filter('the_excerpt_rss', array($this, 'the_excerpt_rss'), 99);
        add_action('template_redirect', array($this, 'send_headers_for_aio_seo_pack'), 20);

        register_activation_hook(MIHDAN_MAILRU_PULSE_FEED_FILE, array($this, 'on_activate'));
        register_deactivation_hook(MIHDAN_MAILRU_PULSE_FEED_FILE, array($this, 'on_deactivate'));
    }

    public function registerAjax($command, callable $handler, $public = false)
    {
        if (wp_doing_ajax()) {
            $cb = function () use ($handler) {
                try {
                    ini_set('html_errors', '0');
                    $data = $handler($_POST);
                    @header('Content-Type: application/json; charset=' . get_option('blog_charset'));
                    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                } catch (\Throwable $e) {
                    @header('Content-Type: application/json; charset=' . get_option('blog_charset'));
                    echo json_encode(
                        ['error' => ['message' => $e->getMessage(), 'code' => $e->getCode()]],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                } catch (\Exception $e) {
                    //for php5
                    @header('Content-Type: application/json; charset=' . get_option('blog_charset'));
                    echo json_encode(
                        ['error' => ['message' => $e->getMessage(), 'code' => $e->getCode()]],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                } finally {
                    ini_set('html_errors', '1');
                    die();
                }
            };
            if ($public) {
                add_action('wp_ajax_nopriv_' . $command, $cb);
            }
            add_action('wp_ajax_' . $command, $cb);
        }
    }

    public function meta_box_markup()
    {
        ?>
<div><label class="selectit"><input type="checkbox" id="mailru_pulse_publish_input" <?php echo empty(get_post_meta(get_the_ID(), '_pulse_nopublish', true)) ? 'checked="checked"' : ''; ?>>Публиковать</label><span id="mailru_pulse_publish_spinner" class="spinner" style="float:initial;margin:0 10px"></span></div>
<script>
(function(){
    var processSettings = false;

    window.onbeforeunload = function() {
        if (processSettings) {
            return 'Saving mail.ru pulse settings';
        }
    };

    var input = document.getElementById('mailru_pulse_publish_input');
    var spinner = document.getElementById('mailru_pulse_publish_spinner');
    if(input){
        var label = input.parentElement;
        input.addEventListener('change', function(){
            label.style.pointerEvents = 'none';
            label.style.userSelect = 'none';
            label.style.msUserSelect = 'none';
            label.style.webkitUserSelect = 'none';
            label.style.opacity = '0.5';
            spinner.style.visibility = 'visible';
            processSettings = true;
            $.post({
                url: ajaxurl,
                dataType: 'json',
                data: {
                    action: 'save_pulse_post_settings',
                    publish: input.checked ? '1' : '',
                    post: <?php echo get_the_ID();?>
                }
            }).fail(function(jqXHR, textStatus, errorThrown){
                input.checked = !input.checked;
                console.error(textStatus, errorThrown);
                alert((textStatus || '') +'\n'+ (errorThrown || ''));
            }).always(function(){
                label.removeAttribute('style');
                spinner.style.visibility = 'hidden';
                processSettings = false;
            });
        });
    }
    })();
</script>
<?php
}

    public function add_meta_box()
    {
        add_meta_box('demo-meta-box', 'Mail.ru pulse', array($this, 'meta_box_markup'), 'post', 'side', 'high', null);
    }

    public function the_excerpt_rss($excerpt)
    {
        if (is_feed($this->feedname)) {
            $excerpt = wp_kses($excerpt, $this->allowable_tags);
        }

        return $excerpt;
    }

    public function after_setup_theme()
    {
        $this->feedname = apply_filters('mihdan_mailru_pulse_feed_feedname', MIHDAN_MAILRU_PULSE_FEED_SLUG);
    }

    public function init()
    {
        add_feed($this->feedname, array($this, 'require_feed_template'));

        $this->registerAjax('save_pulse_post_settings', function ($data) {
            if (empty($data['publish'])) {
                update_post_meta($data['post'], '_pulse_nopublish', true);
            } else {
                delete_post_meta($data['post'], '_pulse_nopublish');
            }
            return array('status' => 'ok');
        });

        add_action('add_meta_boxes', array($this, 'add_meta_box'));
    }

    public function require_feed_template()
    {
        require MIHDAN_MAILRU_PULSE_FEED_PATH . '/templates/feed.php';
    }

    public function flush_rewrite_rules()
    {
        // Ищем опцию.
        if (get_option($this->slug . '_flush_rewrite_rules')) {

            // Скидываем реврайты.
            flush_rewrite_rules();

            // Удаляем опцию.
            delete_option($this->slug . '_flush_rewrite_rules');
        }
    }

    public function hide_wpseo_rss_footer($include_footer = true)
    {
        if (is_feed($this->feedname)) {
            $include_footer = false;
        }

        return $include_footer;
    }

    public function send_headers_for_aio_seo_pack()
    {
        // Добавим заголовок `X-Robots-Tag`
        // для решения проблемы с сеошными плагинами.
        header('X-Robots-Tag: index, follow', true);
    }

    public function on_activate()
    {
        // Добавим флаг, свидетельствующий о том,
        // что нужно сбросить реврайты.
        update_option($this->slug . '_flush_rewrite_rules', 1, true);
    }

    public function on_deactivate()
    {
        // Сбросить правила реврайтов
        flush_rewrite_rules();
    }
}
