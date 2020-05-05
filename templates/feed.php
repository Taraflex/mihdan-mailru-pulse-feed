<?php
header('Content-Type: ' . feed_content_type('rss-http') . '; charset=UTF-8', true);
echo '<?xml version="1.0" encoding="UTF-8"?>';

//ini_set('display_errors', 1);
//error_reporting(E_ALL);

function pulse_content()
{
    $content = wp_kses(apply_filters('the_content', get_the_content()), [
        'a'      => ['href' => true],
        'p'      => [], 'blockquote'                         => [],
        'table'  => [], 'tbody'                              => [], 'tr'  => [], 'th' => [], 'td' => [],
        'ul'     => [], 'ol'                                 => [], 'li'  => [],
        'h1'     => [], 'h2'                                 => [], 'h3'  => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'figure' => [], 'figcaption'                         => [], 'img' => ['src' => true, 'alt' => true],
        'video'  => ['src' => true], 'source' => ['src' => true]
    ]);
    $doc = new \DQ\DomQuery('<body>' . $content . '<body>');
    $doc->find('img,video')->each(function (\DQ\DomQuery $i) {
        if ($i->getNodes()[0]->parentNode->tagName != 'figure') {
            $i->wrap('<figure></figure>');
        }
        $figure = $i->parent();
        $figp   = $figure->parent();
        if ($figp->children()->count() < 2) {
            $figp->replaceWith($figp->contents());
        }
        $thumbnail = null;
        $type      = null;
        if ($i->tagName == 'video') {
            $thumbnail = $i->getAttribute('src');
            if (empty($thumbnail)) {
                $i->setAttribute('src', $thumbnail = $i->children('source')->getAttribute('src'));
            }
            $type = ['type' => 'video/mp4'];
            $i->children()->remove();
        } else {
            $thumbnail = $i->getAttribute('src');
            $type      = wp_check_filetype($thumbnail);
        }

        if ($thumbnail) {
            echo '<enclosure url="' . esc_url($thumbnail) . '" type="' . esc_attr($type['type']) . '"/>';
        }
    });
    echo '<content:encoded><![CDATA[';
    $doc->xml_mode = true;
    echo $doc->getInnerHtml();
    echo ']]></content:encoded>';
}

?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
	<channel>
		<title><?php bloginfo_rss('name');?></title>
		<link><?php bloginfo_rss('url');?></link>
		<description><?php bloginfo_rss('description');?></description>
		<language><?php echo substr(get_bloginfo_rss('language'), 0, 2); ?></language>
		<?php do_action('rss2_head');?>
		<?php $content_required = !empty(get_option(MIHDAN_MAILRU_PULSE_CONTENT_SETTINGS));?>
		<?php while (have_posts()): ?>
			<?php the_post();?>
			<?php if (empty(get_post_meta(get_the_ID(), '_pulse_nopublish', true))): ?>
			<item>
				<link><?php the_permalink_rss();?></link>
				<title><?php the_title_rss();?></title>
				<author><?php the_author();?></author>
				<pubDate><?php echo esc_html(get_post_time('r', true)); ?></pubDate>
				<description><![CDATA[<?php the_excerpt_rss();?>]]></description>
				<?php if (has_post_thumbnail()): ?>
					<?php
$thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'full');
$type      = wp_check_filetype($thumbnail);
?>
					<enclosure url="<?php echo esc_url($thumbnail); ?>" type="<?php echo esc_attr($type['type']); ?>"/>
				<?php endif;?>
				<?php if ($content_required): ?>
					<?php pulse_content();?>
				<?php endif;?>
			</item>
			<?php endif;?>
		<?php endwhile;?>
	</channel>
</rss>
