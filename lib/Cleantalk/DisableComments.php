<?php

namespace Cleantalk;

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
class DisableComments{
	
	use \Cleantalk\Templates\Singleton;
	
	/**
	 * Determs is Wordpress Multisite is enabled
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
	 * @var /Cleantalk/ApbctState antispam instance
	 */
	private $apbct;
	
	/**
	 * Singleton constructor.
	 */
	function init(){
		
		global $apbct;
		
		$this->apbct = $apbct;
		
		$types_to_disable = array();
		
		if( $this->apbct->settings['disable_comments__all'] ){
			$types_to_disable = array( 'page', 'post', 'media' );
		}else{
			if( $this->apbct->settings['disable_comments__posts'] )
				$types_to_disable[] = 'post';
			if( $this->apbct->settings['disable_comments__pages'] )
				$types_to_disable[] = 'page';
			if( $this->apbct->settings['disable_comments__media'] )
				$types_to_disable[] = 'media';
		}
		
		$this->is_wpms = APBCT_WPMS;
		$this->types_to_disable = $types_to_disable;
		
		add_action( 'widgets_init', array( $this, 'disable_rc_widget' ) );
		add_filter( 'wp_headers', array( $this, 'filter__headers' ) );
		add_action( 'template_redirect', array( $this, 'filter__query' ), 9 );
		add_action( 'template_redirect', array( $this, 'filter__admin_bar' ) );
		add_action( 'admin_init', array( $this, 'filter__admin_bar' ) );
		add_action( 'wp_loaded', array( $this, 'disable_types' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'filter__gutenberg_blocks' ) );
		
	}
	
	function is_current_type_to_disable( $type = '' ){
		$type = $type ? $type : get_post_type();
		return in_array( $type, $this->types_to_disable );
	}
	
	/**
	 *
	 * @return void
	 */
	public function disable_types(){
		if( ! empty( $this->types_to_disable ) ){
			foreach ( $this->types_to_disable as $type ){
				// we need to know what native support was for later
				if( post_type_supports( $type, 'comments' ) ){
					// $this->modified_types[] = $type;
					remove_post_type_support( $type, 'comments' );
					remove_post_type_support( $type, 'trackbacks' );
				}
			}
			
			add_filter( 'comments_array', array( $this, 'filter__existing_comments' ), 20, 2 );
			add_filter( 'comments_open', array( $this, 'filter__comment_status' ), 20, 2 );
			add_filter( 'pings_open', array( $this, 'filter__comment_status' ), 20, 2 );
			add_filter( 'get_comments_number', array( $this, 'filter__comments_number' ), 20, 2 );
			
			if( is_admin() ){
				
				if( $this->apbct->settings['disable_comments__all'] ){
					add_action( 'admin_menu', array( $this,	'admin__filter_menu' ), 999 );
					add_action( 'admin_print_styles-index.php', array( $this, 'admin__filter_css' ) );
					add_action( 'admin_print_styles-profile.php', array( $this, 'admin__filter_css' ) );
					add_action( 'wp_dashboard_setup', array( $this, 'admin__filter_dashboard' ) );
					add_filter( 'pre_option_default_pingback_flag', '__return_zero' );
				}
			}else{
				
				add_filter( 'get_comments_number', array( $this, 'template__check' ), 20, 2 );
				
			}
		}
	}
	
	function disable_rc_widget(){
		unregister_widget( 'WP_Widget_Recent_Comments' );
	}
	
	function admin__filter_css(){
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
	
	function admin__filter_dashboard(){
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}
	
	function admin__filter_menu(){
		global $pagenow;
		
		if( in_array( $pagenow, array( 'comment.php', 'edit-comments.php', 'options-discussion.php' ) ) )
			wp_die( __( 'Comments are closed.' ), '', array( 'response' => 403 ) );
		
		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
	}
	
	function template__check(){
		if( is_singular() && $this->is_current_type_to_disable() ){
			add_filter( 'comments_template', array( $this, 'template__replace' ), 20 );
			wp_deregister_script( 'comment-reply' );
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}
	}
	
	function template__replace(){
		return APBCT_DIR_PATH . 'templates/empty_comments.php';
	}
	
	function filter__headers( $headers ){
		unset( $headers['X-Pingback'] );
		return $headers;
	}
	
	function filter__query( $headers ){
		if( is_comment_feed() )
			wp_die( __( 'Comments are closed.' ), '', array( 'response' => 403 ) );
	}
	
	function filter__admin_bar(){
		if( is_admin_bar_showing() ){
			remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
			if( $this->is_wpms ){
				add_action( 'admin_bar_menu', array( $this, 'remove__comment_links__wpms' ), 500 );
			}
		}
	}
	
	/**
	 * Determines if scripts should be enqueued
	 */
	public function filter__gutenberg_blocks( $hook ){
		if( $this->is_current_type_to_disable() ){
			wp_enqueue_script(
				'cleantalk-disable-comments-gutenberg',
				plugin_dir_url( __FILE__ ) . 'assets/apbct-disable-comments.js',
				array(),
				APBCT_VERSION,
				'in_footer'
			);
			wp_localize_script(
				'cleantalk-disable-comments-gutenberg',
				'apbctDisableComments',
				array(
					'disabled_blocks' => array( 'core/latest-comments' ),
				)
			);
		}
	}
	
	public function filter__existing_comments( $comments, $post_id ){
		return $this->is_current_type_to_disable() ? array() : $comments;
	}
	
	public function filter__comment_status( $open, $post_id ){
		return $this->is_current_type_to_disable() ? false : $open;
	}
	
	public function filter__comments_number( $count, $post_id ){
		return $this->is_current_type_to_disable() ? 0 : $count;
	}
	
	function remove__comment_links__wpms( $wp_admin_bar ){
		if( is_user_logged_in() ){
			
			foreach ( (array) $wp_admin_bar->user->blogs as $blog ){
				$wp_admin_bar->remove_menu( 'blog-' . $blog->userblog_id . '-c' );
			}
			
		}else
			$wp_admin_bar->remove_menu( 'blog-' . get_current_blog_id() . '-c' );
		
	}
}