<?php


namespace Cleantalk\ApbctWP;


class AdminNotices {

	/**
	 * @var AdminNotices
	 */
	private static $instance;

	/**
	 * @var array
	 */
	const NOTICES = array(
		'notice_get_key_error',
		'notice_key_is_incorrect',
		'notice_trial',
		'notice_renew',
		'notice_incompatibility'
	);

	/**
	 * @var State
	 */
	private $apbct;

	/**
	 * @var bool
	 */
	private $is_cleantalk_page;

	/**
	 * @var string
	 */
	private $settings_link;

	/**
	 * @var string
	 */
	private $user_token;

	/**
	 * AdminNotices constructor.
	 */
	private function __construct()
	{
		global $apbct;
		$this->apbct = $apbct;
		$this->is_cleantalk_page = isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'cleantalk', 'ct_check_spam', 'ct_check_users' ) );
		$this->user_token = $this->apbct->user_token ? '&user_token=' . $this->apbct->user_token : '';

		$self_owned_key = $this->apbct->moderate_ip == 0 && ! defined( 'CLEANTALK_ACCESS_KEY' );
		$is_dashboard   = is_network_admin() || is_admin();
		$is_admin       = current_user_can('activate_plugins');

		if( $self_owned_key && $is_dashboard && $is_admin ) {

			if( defined('DOING_AJAX') ) {

				add_action( 'wp_ajax_cleantalk_dismiss_notice' , array( $this, 'set_notice_dismissed' ) );

			} else {

				foreach( self::NOTICES as $notice ) {
					if( $this->is_cleantalk_page || ! $this->is_dismissed_notice( $notice ) ) {
						add_action('admin_notices',         array( $this, $notice ) );
						add_action('network_admin_notices', array( $this, $notice ) );
					}
				}

				add_filter( 'cleantalk_admin_bar__parent_node__after', array( $this, 'add_attention_mark' ), 20, 1 );

			}

		}

	}

	/**
	 * Get singleton instance of AdminNotices
	 *
	 * @return AdminNotices
	 */
	private static function get_instance()
	{
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static();
		}
		return self::$instance;
	}

	/**
	 * Initialize method
	 */
	public static function show_admin_notices()
	{
		$admin_notices = self::get_instance();

		if( is_network_admin() ) {
			$site_url = get_site_option( 'siteurl' );
			$site_url = preg_match( '/\/$/', $site_url ) ? $site_url : $site_url . '/';
			$admin_notices->settings_link = $site_url . 'wp-admin/options-general.php?page=cleantalk';
		} else {
			$admin_notices->settings_link = 'options-general.php?page=cleantalk';
		}
	}

	/**
	 * Callback for the notice hook
	 */
	public function notice_get_key_error()
	{
		if( $this->apbct->notice_show && ! empty( $this->apbct->errors['key_get'] ) && ! $this->apbct->white_label ){
			$register_link = 'https://cleantalk.org/register?platform=wordpress&email=' . urlencode( ct_get_admin_email() ) . '&website=' . urlencode( get_option( 'siteurl' ) );
			$content = sprintf(__("Unable to get Access key automatically: %s", 'cleantalk-spam-protect'), $this->apbct->errors['key_get']['error'] ) .
			           '<a target="_blank" style="margin-left: 10px" href="' . $register_link . '">' . esc_html__('Get the Access key', 'cleantalk-spam-protect') . '</a>';
			$id = 'cleantalk_' . __FUNCTION__;
			$this->generate_notice_html( $content, $id );
		}
	}

	/**
	 * Callback for the notice hook
	 */
	public function notice_key_is_incorrect()
	{
		if ( ( ! apbct_api_key__is_correct() && $this->apbct->moderate_ip == 0 ) && ! $this->apbct->white_label ){
			$content = sprintf(__("Please enter Access Key in %s settings to enable anti spam protection!", 'cleantalk-spam-protect'), "<a href='{$this->settings_link}'>{$this->apbct->plugin_name}</a>");
			$id = 'cleantalk_' . __FUNCTION__;
			$this->generate_notice_html( $content, $id );
			$this->apbct->notice_show = false;
		}
	}

	/**
	 * Callback for the notice hook
	 */
	public function notice_trial()
	{
		if ( $this->apbct->notice_show && $this->apbct->notice_trial == 1 && $this->apbct->moderate_ip == 0 && ! $this->apbct->white_label ) {
			$content = sprintf(__("%s trial period ends, please upgrade to %s!", 'cleantalk-spam-protect'),
					"<a href='{$this->settings_link}'>" . $this->apbct->plugin_name . "</a>",
					"<a href=\"https://cleantalk.org/my/bill/recharge?utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%20backend%20trial$this->user_token&cp_mode=antispam\" target=\"_blank\"><b>premium version</b></a>");
			$additional_content = '<h4 style = "color: gray">' . esc_html__( 'Account status updates every 24 hours.', 'cleantalk-spam-protect' ) . '</h4>';
			$id = 'cleantalk_' . __FUNCTION__;
			$this->generate_notice_html( $content, $id, $additional_content );
			$this->apbct->notice_show = false;
		}
	}

	/**
	 * Callback for the notice hook
	 * @deprecated
	 */
	public function notice_renew()
	{
		if ( $this->apbct->notice_show && $this->apbct->notice_renew == 1 && $this->apbct->moderate_ip == 0 && ! $this->apbct->white_label ) {
			$renew_link = "<a href=\"https://cleantalk.org/my/bill/recharge?utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%%20backend%%20renew$this->user_token&cp_mode=antispam\" target=\"_blank\">%s</a>";
			$button_html 	= sprintf($renew_link, '<input type="button" class="button button-primary" value="'.__('RENEW ANTI-SPAM', 'cleantalk-spam-protect').'"  />');
			$link_html 		= sprintf($renew_link, "<b>".__('next year', 'cleantalk-spam-protect')."</b>");

			$content = sprintf(__("Please renew your anti-spam license for %s.", 'cleantalk-spam-protect'), $link_html);
			$additional_content = '<h4 style = "color: gray">' . esc_html__( 'Account status updates every 24 hours.', 'cleantalk-spam-protect' ) . '</h4>' . $button_html;
			$id = 'cleantalk_' . __FUNCTION__;
			$this->generate_notice_html( $content, $id, $additional_content );
			$this->apbct->notice_show = false;
		}
	}

	/**
	 * Callback for the notice hook
	 */
	public function notice_incompatibility()
	{
		if( ! empty( $this->apbct->data['notice_incompatibility'] ) && $this->is_cleantalk_page && $this->apbct->settings['sfw__enabled'] ){
			foreach ($this->apbct->data['notice_incompatibility'] as $notice) {
				$this->generate_notice_html( $notice );
			}
		}
	}

	/**
	 * Generate and output the notice HTML
	 *
	 * @param string $content Any HTML allowed
	 * @param string $id
	 * @param string $additional_content
	 */
	private function generate_notice_html( $content, $id = '', $additional_content = '' )
	{
		$notice_classes = $this->is_cleantalk_page ? 'apbct-notice notice notice-error' : 'apbct-notice notice notice-error is-dismissible';
		$notice_id = !empty( $id ) ? 'id="'. $id .'"' : '';

		echo '<div class="' . $notice_classes . '" ' . $notice_id . '>
				<h3>' . $content . '</h3>
				' . $additional_content . '
			  </div>';
	}

	/**
	 * Check dismiss status of the notice
	 *
	 * @param string $notice
	 * @return bool
	 */
	private function is_dismissed_notice( $notice )
	{
		return (bool) get_option( 'cleantalk_' . $notice . '_dismissed' );
	}

	public function set_notice_dismissed() {

		check_ajax_referer('ct_secret_nonce' );

		if( ! isset( $_POST['notice_id'] ) ) {
			wp_send_json_error( esc_html__( 'Wrong request.', 'cleantalk-spam-protect' ) );
		}

		$notice = sanitize_text_field( $_POST['notice_id'] );

		if( in_array( str_replace( 'cleantalk_', '', $notice ), self::NOTICES, true ) ) {
			if( update_option( $notice . '_dismissed', 1 ) ) {
				wp_send_json_success();
			} else {
				wp_send_json_error( esc_html__( 'Notice status not updated.', 'cleantalk-spam-protect' ) );
			}
		} else {
			wp_send_json_error( esc_html__( 'Notice name is not allowed.', 'cleantalk-spam-protect' ) );
		}

	}

	/**
	 * Callback for the admin-bar filtering hook
	 *
	 * @param string $after
	 *
	 * @return string
	 */
	public function add_attention_mark( $after ) {
		if( $this->apbct->notice_show ) {
			return $after . '<i class="icon-attention-alt"></i>';
		}
		return $after;
	}

}
