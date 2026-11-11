<?php

namespace ApbctWP\ContactsEncoder;

use Cleantalk\ApbctWP\ContactsEncoder\ContactsEncoder;
use Cleantalk\ApbctWP\State;
use Cleantalk\Common\ContactsEncoder\Dto\Params;

class TestGridBuilderPlantedPlaceholder extends \WP_UnitTestCase
{
    private $original_apbct;
    private $original_shortcodes;
    private $original_template_id;
    private $original_template_content;

    public function set_up()
    {
        parent::set_up();

        global $apbct, $wp_filter, $shortcode_tags, $_wp_current_template_id, $_wp_current_template_content;
        $this->original_apbct = $apbct;
        $this->original_shortcodes = $shortcode_tags;
        $this->original_template_id = $_wp_current_template_id ?? null;
        $this->original_template_content = $_wp_current_template_content ?? null;
        foreach ( $wp_filter as $hook => $callbacks ) {
            foreach ( $callbacks->callbacks as $priority => $entries ) {
                foreach ( $entries as $entry ) {
                    $callback = $entry['function'];
                    if (
                        is_array($callback)
                        && is_object($callback[0])
                        && strpos(get_class($callback[0]), 'Cleantalk\\ApbctWP\\ContactsEncoder\\') === 0
                    ) {
                        remove_filter($hook, $callback, $priority);
                    }
                }
            }
        }
        remove_action('wp', 'apbct_buffer__start');

        wp_set_current_user(0);
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $apbct->settings['data__email_decoder'] = 1;
        $apbct->settings['data__email_decoder_buffer'] = 0;
        add_filter('pre_option_active_plugins', static function () {
            return array();
        });
        add_filter('pre_site_option_active_sitewide_plugins', static function () {
            return array();
        });
        update_option('show_avatars', 1);
    }

    public function tear_down()
    {
        global $apbct, $shortcode_tags, $_wp_current_template_id, $_wp_current_template_content;

        $shortcode_tags = $this->original_shortcodes;
        $_wp_current_template_id = $this->original_template_id;
        $_wp_current_template_content = $this->original_template_content;
        $apbct = $this->original_apbct;
        parent::tear_down();
    }

    /**
     * @dataProvider commentContentProvider
     */
    public function testRenderBlockDoesNotReplacePlantedWpGridBuilderPlaceholder($content)
    {
        if ( ! function_exists('get_the_block_template_html') ) {
            $this->markTestSkipped('Requires the WordPress block template renderer.');
        }
        foreach ( array('core/comments', 'core/comment-template', 'core/comment-author-name', 'core/avatar', 'core/comment-content') as $block ) {
            if ( ! \WP_Block_Type_Registry::get_instance()->is_registered($block) ) {
                $this->markTestSkipped('Requires the registered ' . $block . ' block.');
            }
        }

        $post_id = self::factory()->post->create(array(
            'post_status' => 'publish',
            'comment_status' => 'open',
        ));
        $marker = '%%APBCT_WPGB_REGION_0%%';
        $comment_id = self::factory()->comment->create(array(
            'comment_post_ID' => $post_id,
            'comment_author' => $marker,
            'comment_author_email' => 'commenter@example.org',
            'comment_author_url' => '',
            'comment_content' => $content,
            'comment_approved' => 1,
            'user_id' => 0,
        ));
        $this->go_to(get_permalink($post_id));

        $params = new Params();
        $params->api_key = 'placeholder-test-key';
        $encoder = new ContactsEncoder();
        $encoder->init($params);
        $encoder->runEncoding();
        $this->assertSame(10, has_filter('render_block', array($encoder, 'modifyContent')));

        global $_wp_current_template_id, $_wp_current_template_content;
        $_wp_current_template_id = '';
        $_wp_current_template_content =
            '<!-- wp:comments --><div class="wp-block-comments">'
            . '<!-- wp:comment-template -->'
            . '<!-- wp:comment-author-name /-->'
            . '<!-- wp:avatar /-->'
            . '<!-- wp:comment-content /-->'
            . '<!-- /wp:comment-template --></div><!-- /wp:comments -->'
            . '<!-- wp:paragraph --><p>encoding-control@example.org</p><!-- /wp:paragraph -->';
        $html = get_the_block_template_html();

        $this->assertStringContainsString('apbct-email-encoder', $html);
        $this->assertStringNotContainsString('encoding-control@example.org', $html);
        $this->assertStringContainsString('id="comment-' . $comment_id . '"', $html);

        $dom = new \DOMDocument();
        $previous_errors = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous_errors);
        }
        $xpath = new \DOMXPath($dom);
        $avatars = $xpath->query('//img[contains(concat(" ", normalize-space(@class), " "), " wp-block-avatar__image ")]');
        $this->assertSame(1, $avatars->length);
        $avatar = $avatars->item(0);
        $this->assertSame(sprintf(__('%s Avatar'), $marker), $avatar->getAttribute('alt'));
        $this->assertSame(0, $xpath->query('//*[@onerror]')->length);
        $authors = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-comment-author-name ")]');
        $this->assertSame(1, $authors->length);
        $this->assertSame($marker, $authors->item(0)->textContent);
        $this->assertGreaterThan(0, $xpath->query('//style[@id="apbct-wpgb-opacity-fix"]')->length);
    }

    public function commentContentProvider()
    {
        return array(
            'code element' => array("<code>' src=x onerror=alert(1) x='y</code>"),
            'caption syntax' => array("[caption x=\"' src=x onerror=alert(1) x='y\"]"),
        );
    }
}
