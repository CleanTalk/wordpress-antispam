<?php

namespace Cleantalk\Antispam;

use Cleantalk\Templates\Singleton;

/**
 * Class DisableComments
 * Discribes functions needed to use disable comments functionality
 *
 * Only for Wodrdpress 4.7 and above
 *
 * Uses singleton template
 *
 * @contributor Cleantalk
 */
class DisableComments
{
    use Singleton;

    /**
     * Determs is WordPress Multisite is enabled
     *
     * @var bool
     */
    private $is_wpms = false;

    /**
     * Post types to disable comments
     *
     * @var array
     */
    private $types_to_disable;

    /**
     * @var \Cleantalk\ApbctWP\State antispam instance
     */
    private $apbct;

    /**
     * Singleton constructor.
     */
    public function init()
    {
        global $apbct;

        $this->apbct = $apbct;

        $types_to_disable = array();

        if ( $this->apbct->settings['comments__disable_comments__all'] ) {
            $types_to_disable = array('page', 'post', 'media', 'attachment');
        } else {
            if ( $this->apbct->settings['comments__disable_comments__posts'] ) {
                $types_to_disable[] = 'post';
            }
            if ( $this->apbct->settings['comments__disable_comments__pages'] ) {
                $types_to_disable[] = 'page';
            }
            if ( $this->apbct->settings['comments__disable_comments__media'] ) {
                $types_to_disable[] = 'media';
                $types_to_disable[] = 'attachment';
            }
        }

        $this->is_wpms = APBCT_WPMS;
        $this->types_to_disable = $types_to_disable;

        add_action('widgets_init', array($this, 'disableRcWidget'));
        add_filter('wp_headers', array($this, 'filterHeaders'));
        add_action('template_redirect', array($this, 'filterQuery'), 9);
        add_action('template_redirect', array($this, 'filterAdminBar'));
        add_action('admin_init', array($this, 'filterAdminBar'));
        add_action('wp_loaded', array($this, 'disableTypes'));
        add_action('enqueue_block_editor_assets', array($this, 'filterGutenbergBlocks'));
    }

    /**
     * @param string|bool $type
     *
     * @return bool
     */
    public function isCurrentTypeToDisable($type = '')
    {
        $type = $type ?: get_post_type();

        return in_array($type, $this->types_to_disable);
    }

    /**
     *
     * @return void
     */
    public function disableTypes()
    {
        if ( ! empty($this->types_to_disable) ) {
            foreach ( $this->types_to_disable as $type ) {
                // we need to know what native support was for later
                if ( post_type_supports($type, 'comments') ) {
                    remove_post_type_support($type, 'comments');
                    remove_post_type_support($type, 'trackbacks');
                }
            }

            add_filter('comments_array', array($this, 'filterExistingComments'), 20, 2);
            add_filter('comments_open', array($this, 'filterCommentStatus'), 20, 2);
            add_filter('pings_open', array($this, 'filterCommentStatus'), 20, 2);
            add_filter('get_comments_number', array($this, 'filterCommentsNumber'), 20, 2);

            if ( is_admin() ) {
                if ( $this->apbct->settings['comments__disable_comments__all'] ) {
                    add_action('admin_menu', array($this, 'adminFilterMenu'), 999);
                    add_action('admin_print_styles-index.php', array($this, 'adminFilterCss'));
                    add_action('admin_print_styles-profile.php', array($this, 'adminFilterCss'));
                    add_action('wp_dashboard_setup', array($this, 'adminFilterDashboard'));
                    add_filter('pre_option_default_pingback_flag', '__return_zero');
                }
            } else {
                add_filter('get_comments_number', array($this, 'templateCheck'), 20, 2);
            }
        }
    }

    public function disableRcWidget()
    {
        unregister_widget('WP_Widget_Recent_Comments');
    }

    public function adminFilterCss()
    {
        echo '<style>
			#dashboard_right_now .comment-count,
			#dashboard_right_now .comment-mod-count,
			#latest-comments,
			#welcome-panel .welcome-comments,
			.user-comment-shortcuts-wrap {
				display: none !important;
			}
		</style>';
    }

    public function adminFilterDashboard()
    {
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    }

    public function adminFilterMenu()
    {
        global $pagenow;

        if ( in_array($pagenow, array('comment.php', 'edit-comments.php', 'options-discussion.php')) ) {
            wp_die(__('Comments are closed.'), '', array('response' => 403));
        }

        remove_menu_page('edit-comments.php');
        remove_submenu_page('options-general.php', 'options-discussion.php');
    }

    public function templateCheck($count, $_post_id)
    {
        if ( is_singular() && $this->isCurrentTypeToDisable() ) {
            add_filter('comments_template', array($this, 'templateReplace'), 20);
            wp_deregister_script('comment-reply');
            remove_action('wp_head', 'feed_links_extra', 3);
        }

        return $count;
    }

    public function templateReplace()
    {
        return APBCT_DIR_PATH . 'templates/empty_comments.php';
    }

    public function filterHeaders($headers)
    {
        unset($headers['X-Pingback']);

        return $headers;
    }

    public function filterQuery($_headers)
    {
        if ( is_comment_feed() ) {
            wp_die(__('Comments are closed.'), '', array('response' => 403));
        }
    }

    public function filterAdminBar()
    {
        if ( is_admin_bar_showing() ) {
            remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
            if ( $this->is_wpms ) {
                add_action('admin_bar_menu', array($this, 'removeCommentLinksWpms'), 500);
            }
        }
    }

    /**
     * Determines if scripts should be enqueued
     */
    public function filterGutenbergBlocks($_hook)
    {
        if ( $this->isCurrentTypeToDisable() ) {
            wp_enqueue_script(
                'cleantalk-disable-comments-gutenberg',
                plugin_dir_url(__FILE__) . 'assets/apbct-disable-comments.js',
                array(),
                APBCT_VERSION,
                true
            );
            wp_localize_script(
                'cleantalk-disable-comments-gutenberg',
                'apbctDisableComments',
                array(
                    'disabled_blocks' => array('core/latest-comments'),
                )
            );
        }
    }

    public function filterExistingComments($comments, $_post_id)
    {
        return $this->isCurrentTypeToDisable() ? array() : $comments;
    }

    public function filterCommentStatus($open, $_post_id)
    {
        return $this->isCurrentTypeToDisable() ? false : $open;
    }

    public function filterCommentsNumber($count, $_post_id)
    {
        return $this->isCurrentTypeToDisable() ? 0 : $count;
    }

    public function removeCommentLinksWpms($wp_admin_bar)
    {
        if ( is_user_logged_in() ) {
            foreach ( (array)$wp_admin_bar->user->blogs as $blog ) {
                $wp_admin_bar->remove_menu('blog-' . $blog->userblog_id . '-c');
            }
        } else {
            $wp_admin_bar->remove_menu('blog-' . get_current_blog_id() . '-c');
        }
    }
}
