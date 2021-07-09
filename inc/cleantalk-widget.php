<?php

// Hooks on widget loads
add_action( 'widgets_init', 'cleantalk_load_widget' );

/**
 * Register and load the widget
 */
function cleantalk_load_widget(){
	register_widget( 'cleantalk_widget' );
}

class cleantalk_widget extends WP_Widget
{
	function __construct()
	{
		parent::__construct(
			// Base ID of your widget
			'cleantalk_widget', 
		
			// Widget name will appear in UI
			__('CleanTalk Widget', 'cleantalk-spam-protect'),
		
			// Widget description
			array( 'description' => __( 'CleanTalk widget', 'cleantalk-spam-protect'), )
		);
	}

	// Creating widget front-end
	// This is where the action happens
	public function widget( $args, $instance )
	{
		global $apbct;
		
		$instance['title'] = isset( $instance['title'] ) ? $instance['title'] : __( 'Spam blocked', 'cleantalk-spam-protect');
		$title = apply_filters( 'widget_title', $instance['title'] );
		echo $args['before_widget'];
		
		// Showing title
		if ( ! empty( $title ) ){ echo $args['before_title'] . $title . $args['after_title']; }
		
		// Parsing incoming params
		$blocked = number_format($apbct->data['spam_count'], 0, ',', ' ');
		
		$a_style = 'cursor: pointer; display: block; padding: 5px 0 5px; text-align: center; text-decoration: none; border-radius: 5px; -moz-border-radius: 5px; -webkit-border-radius: 5px; font-weight: normal; width: 100%; ';
		$strong_style = 'display: block; font-size: 15px; line-height: 16px; padding: 0 13px; white-space: nowrap; ';
		
		if(!isset($instance['style'])){
			$instance['style'] = 'cleantalk';
		}
		
		switch($instance['style']){
			case 'cleantalk':
				$a_style      .= 'background: #3090C7; background-image: -moz-linear-gradient(0% 100% 90deg,#2060a7,#3090C7); background-image: -webkit-gradient(linear,0% 0,0% 100%,from(#3090C7),to(#2060A7)); border: 1px solid #33eeee; color: #AFCA63;';
				$strong_style .= 'color: #FFF;';
			break;
			case 'light':
				$a_style      .= 'background: #fafafa; background-image: -moz-linear-gradient(0% 100% 90deg,#ddd,#fff); background-image: -webkit-gradient(linear,0% 0,0% 100%,from(#fff),to(#ddd)); border: 1px solid #ddd; color: #000;';
				$strong_style .= 'color: #000;';
			break;
			case 'ex_light':
				$a_style      .= 'background: #fff; border: 1px solid #ddd; color: #777;';
				$strong_style .= 'color: #555;';
			break;
			case 'dark':
				$a_style      .= 'background: #333; background-image: -moz-linear-gradient(0% 100% 90deg,#555,#000); background-image: -webkit-gradient(linear,0% 0,0% 100%,from(#000),to(#555)); border: 1px solid #999; color: #fff;';
				$strong_style .= 'color: #FFF;';
			break;
		}
		
		// This is where you run the code and display the output
		echo '<div style="width:auto;">'
			.'<a href="https://cleantalk.org'.(!empty($instance['refid']) ? '?pid='.$instance['refid'] : '').'" target="_blank" title="'.__('CleanTalk\'s main page', 'cleantalk-spam-protect').'" style="'.$a_style.'">'
				.'<strong style="'.$strong_style.'"><b>'.$blocked.'</b> '.__('spam', 'cleantalk-spam-protect').'</strong> '.__('blocked by', 'cleantalk-spam-protect').' <strong>CleanTalk</strong>'
			.'</a>'
		.'</div>';
		
		echo $args['after_widget'];
	}
		
	// Widget Backend 
	public function form( $instance )
	{
		// Widget admin form
		
		$title = isset( $instance[ 'title' ] ) ? $instance[ 'title' ] : __( 'Spam blocked', 'cleantalk-spam-protect');
		$style = isset( $instance[ 'style' ] ) ? $instance[ 'style' ] : 'ct_style';
		$refid = isset( $instance[ 'refid' ] ) ? $instance[ 'refid' ] : '';
		// Title field
		echo '<p>'
			.'<label for="' . $this->get_field_id( 'title' ) . '">' . __( 'Title:', 'cleantalk-spam-protect') . '</label>'
			.'<input class="widefat" id="'.$this->get_field_id( 'title' ).'" name="'.$this->get_field_name( 'title' ).'" type="text" value="'.esc_attr( $title ).'" />'
		.'</p>';
		// Style
		echo '<p>'
			.'<label for="' . $this->get_field_id( 'style' ) . '">' . __( 'Style:', 'cleantalk-spam-protect') . '</label>'
			.'<select id="'.$this->get_field_id( 'style' ).'" class="widefat" name="'.$this->get_field_name( 'style' ).'">'
				.'<option '.($style == 'cleantalk' ? 'selected' : '').' value="cleantalk">'.__('CleanTalk\'s Style', 'cleantalk-spam-protect').'</option>'
				.'<option '.($style == 'light'     ? 'selected' : '').' value="light">'.__('Light', 'cleantalk-spam-protect').'</option>'
				.'<option '.($style == 'ex_light'  ? 'selected' : '').' value="ex_light">'.__('Extremely Light', 'cleantalk-spam-protect').'</option>'
				.'<option '.($style == 'dark'      ? 'selected' : '').' value="dark">'.__('Dark', 'cleantalk-spam-protect').'</option>'
			.'</select>'
		.'</p>';
		// Ref ID
		echo '<p>'
			.'<label for="' . $this->get_field_id( 'refid' ) . '">' . __( 'Referal link ID:', 'cleantalk-spam-protect') . '</label>'
			.'<input class="widefat" id="'.$this->get_field_id( 'refid' ).'" name="'.$this->get_field_name( 'refid' ).'" type="text" value="'.$refid.'" />'
		.'</p>';
		
		return 'noform';
	}
		
	// Updating widget replacing old instances with new
	public function update( $new_instance, $old_instance )
	{
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['style'] = ( ! empty( $new_instance['style'] ) ) ? strip_tags( $new_instance['style'] ) : '';
		$instance['refid'] = ( ! empty( $new_instance['refid'] ) ) ? strip_tags( $new_instance['refid'] ) : '';
		return $instance;
	}
}