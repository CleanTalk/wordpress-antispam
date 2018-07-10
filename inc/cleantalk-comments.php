<?php

add_action('admin_menu', 'ct_add_comments_menu');
add_action( 'wp_ajax_ajax_check_comments', 'ct_ajax_check_comments' );
add_action( 'wp_ajax_ajax_info_comments', 'ct_ajax_info_comments' );
add_action( 'wp_ajax_ajax_insert_comments', 'ct_ajax_insert_comments' );
add_action( 'wp_ajax_ajax_delete_checked', 'ct_ajax_delete_checked' );
add_action( 'wp_ajax_ajax_delete_all', 'ct_ajax_delete_all' );
add_action( 'wp_ajax_ajax_clear_comments', 'ct_ajax_clear_comments' );
add_action( 'wp_ajax_ajax_ct_approve_comment', 'ct_comment_check_approve_comment' );

function ct_add_comments_menu(){
	if(current_user_can('activate_plugins'))
		add_comments_page( __("Check for spam", 'cleantalk'), __("Find spam comments", 'cleantalk'), 'read', 'ct_check_spam', 'ct_show_checkspam_page');
}

function ct_show_checkspam_page(){
    global $ct_plugin_name;
	
	// Getting total spam comments
	$args_spam = array(
		'meta_query' => array(
			Array(
				'key' => 'ct_marked_as_spam',
				'compare' => 'EXISTS'
			)
		),
		'count'=>true
	);
	$cnt_spam = get_comments($args_spam);
	
?>
	<div class="wrap">
		<h2><img src="<?php echo plugin_dir_url(__FILE__) ?>/images/logo_color.png" /> <?php echo $ct_plugin_name; ?></h2><br />
		
	<!-- AJAX error message --> 
		<div id="ct_error_message" style="display:none">
			<h3>
				<?php _e("Ajax error. Process will be automatically restarted in 3 seconds. Status: ", 'cleantalk'); ?><span id="cleantalk_ajax_error"></span> (<span id="cleantalk_js_func"></span>)
			</h3>
			<h4>Please, check for JavaScript errors in your dashboard and and repair it.</h4>
		</div>
		
	<!-- Deleting message --> 
		<div id="ct_deleting_message" style="display:none;">
			<?php _e("Please wait for a while. CleanTalk is deleting spam comments. Comments left: ", 'cleantalk'); ?> <span id="cleantalk_comments_left">
            <?php echo $cnt_spam; ?>
            </span>
		</div>
		
	<!-- Main info --> 
		<h3 id="ct_checking_status"><?php echo ct_ajax_info_comments(true);?></h3>
		
	<!-- Check options -->
		<div class="ct_to_hide" id="ct_check_params_wrapper">
			<button class="button ct_check_params_elem" id="ct_check_spam_button"><?php _e("Start check", 'cleantalk'); ?></button>
			<?php if(!empty($_COOKIE['ct_paused_comments_check'])) { ?><button class="button ct_check_params_elem" id="ct_proceed_check_button"><?php _e("Continue check", 'cleantalk'); ?></button><?php } ?>
			<p class="ct_check_params_desc"><?php _e("The plugin will check all comments against blacklists database and show you senders that have spam activity on other websites.", 'cleantalk'); ?></p>
			<br />
			<div class="ct_check_params_elem ct_check_params_elem_sub">
				<input id="ct_accurate_check" type="checkbox" value="1" /><b><label for="ct_accurate_check"><?php _e("Accurate check", 'cleantalk'); ?></b></label>
			</div>
			<p class="ct_check_params_desc"><?php _e("Allows to use comment's dates to perform more accurate check. Could seriously slow down the check.", 'cleantalk'); ?></p>
			<br />
			<div class="ct_check_params_elem ct_check_params_elem_sub">
				<input id="ct_allow_date_range" type="checkbox" value="1" /><label for="ct_allow_date_range"><b><?php _e("Specify date range", 'cleantalk'); ?></b></label>
			</div>
			<div class="ct_check_params_desc">
				<input class="ct_date" type="text" id="ct_date_range_from" value="<?php echo isset($_GET['from']) ? $_GET['from'] : ''; ?>" disabled readonly />
				<input class="ct_date" type="text" id="ct_date_range_till" value="<?php echo isset($_GET['till']) ? $_GET['till'] : ''; ?>" disabled readonly />
			</div>
			<br>
			<?php ct_input_get_premium(); ?>
		</div>
		
		<!-- Cooling notice --> 
		<h3 id="ct_cooling_notice"></h3>
		
		<!-- Preloader and working message -->
		<div id="ct_preloader">
			<img border=0 src="<?php print plugin_dir_url(__FILE__); ?>images/preloader.gif" />
        </div>
		<div id="ct_working_message">
			<?php _e("Please wait! CleanTalk is checking all approved and pending comments via blacklist database at cleantalk.org. You will have option to delete found spam comments after plugin finish.", 'cleantalk'); ?>
		</div>
		
		<!-- Pause button -->
		<button class="button" id="ct_pause">Pause check</button>
		
		<?php
			
			// Pagination			
			$page = !empty($_GET['spam_page']) ? intval($_GET['spam_page']) : 1;
			$on_page = 20;
			
			$args_spam = array(
				'meta_query' => array(
					Array(
						'key' => 'ct_marked_as_spam',
						'value' => '1',
						'compare' => 'NUMERIC'
					)
				),
				'number'=>$on_page,
				'offset'=>($page-1)*$on_page
			);
			
			$c_spam = get_comments($args_spam);
			if($cnt_spam>0){
				
				$pages = ceil(intval($cnt_spam)/$on_page);
				if($pages && $pages != 1){
					echo "<div class='ct_to_hide pagination'>"
							."<b>Pages:</b>"
							."<ul class='pagination'>";
								for($i = 1; $i <= $pages; $i++){
									echo "<li class='pagination'>"
											."<a href='edit-comments.php?page=ct_check_spam&spam_page=$i&ct_worked=1'>"
												.($i == $page ? "<span class='current_page'>$i</span>" : $i)
											."</a>"
										."</li>";
								}
						echo "</ul>";
					echo "</div>";
				}
		?>
				<table class="ct_to_hide widefat fixed comments" id="ct_check_comments_table">
					<thead>
						<th scope="col" id="cb" class="manage-column column-cb check-column">
							<label class="screen-reader-text" for="cb-select-all-1">Select All</label>
							<input id="cb-select-all-1" type="checkbox" style="margin-top:0;"/>
						</th>
						<th scope="col" id="author" class="manage-column column-slug"><?php print _e( 'Author' ); ?></th>
						<th scope="col" id="comment" class="manage-column column-comment"><?php _e( 'Comment', 'cleantalk'); ?></th>
						<th scope="col" id="response" class="manage-column column-response sortable desc"><?php _e( 'In Response To', 'cleantalk' );?></th>
					</thead>
					<tbody id="the-comment-list" data-wp-lists="list:comment">
						<?php
							for($i=0;$i<sizeof($c_spam);$i++){
								$id      = $c_spam[$i]->comment_ID;
								$post_id = $c_spam[$i]->comment_post_ID;
								$login   = $c_spam[$i]->comment_author;
								$email   = $c_spam[$i]->comment_author_email;
								$ip      = $c_spam[$i]->comment_author_IP;
								
								echo "<tr id='comment-$id' class='comment even thread-even depth-1 approved  cleantalk_comment' data-id='$id'>"
									."<th scope='row' class='check-column'>"
										."<label class='screen-reader-text' for='cb-select-$id'>Select comment</label>"
										."<input id='cb-select-$id' type='checkbox' name='del_comments[]' value='$id'/>"
									."</th>"
									."<td class='author column-author' nowrap>"
									."<strong>"
										.get_avatar( $c_spam[$i]->user_id , 32)
										."$login"
									."</strong>"
									."<br />"
									."<br />";
									
									// Outputs email if exists
									if($email)
										echo "<a href='mailto:$email'>$email</a>"
										."<a href='https://cleantalk.org/blacklists/$email ' target='_blank'>"
											."&nbsp;<img src='".plugin_dir_url(__FILE__)."images/new_window.gif' border='0' style='float:none'/>"
										."</a>";
									else
										echo "No email";
									echo "<br/>";
									
									// Outputs IP if exists
									if($ip)
										echo "<a href='edit-comments.php?s=$ip&mode=detail'>$ip </a>"
										."<a href='https://cleantalk.org/blacklists/$ip ' target='_blank'>"
											."&nbsp;<img src='".plugin_dir_url(__FILE__)."images/new_window.gif' border='0' style='float:none'/>"
										."</a>";
									else
										echo "No IP adress";
								echo "</td>";
							?>
								<td class="comment column-comment">
									<div class="submitted-on">
										<?php printf( __( 'Submitted on <a href="%1$s">%2$s at %3$s</a>' ), get_comment_link($id),
											/* translators: comment date format. See http://php.net/date */
											get_comment_date( __( 'Y/m/d' ),$id ),
											get_comment_date( get_option( 'time_format' ),$id )
											); 
										?>
											
									</div>
									<p>
									<?php print $c_spam[$i]->comment_content; ?>
									</p>
									<div style="height:16px; display: none;" id='cleantalk_button_set_<?php print $id; ?>'>
										<a href="#" class="cleantalk_delete_from_list_button"  	data-id="<?php print $id; ?>" style="color:#0a0;" onclick="return false;" onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';"><?php _e("Approve", "cleantalk"); ?></a>
										&nbsp;|&nbsp;
										<a href="#" class="cleantalk_delete_button"  			data-id="<?php print $id; ?>" style="color:#a00;" onclick="return false;" onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';"><?php _e("Delete", "cleantalk"); ?></a>
									</div>
								</td>
								<td class="response column-response">
									<div>
										<span>
											<a href="/wp-admin/post.php?post=<?php print $post_id; ?>&action=edit"><?php print get_the_title($post_id); ?></a>
											<br/>
											<a href="/wp-admin/edit-comments.php?p=<?php print $post_id; ?>" class="post-com-count">
												<span class="comment-count"><?php
													$p_cnt=wp_count_comments();
													print $p_cnt->total_comments;
												?></span>
											</a>
										</span>
										<a href="<?php print get_permalink($post_id); ?>"><?php print _e('View Post');?></a>
									</div>
								</td>
								</tr>
								<?php
							}
						?>
					</tbody>
				</table>
				<?php
					// Pagination
					if($pages && $pages != 1){
						echo "<div class='ct_to_hide pagination'>"
								."<b>Pages:</b>"
								."<ul class='pagination'>";
									for($i = 1; $i <= $pages; $i++){
										echo "<li class='pagination'>"
												."<a href='edit-comments.php?page=ct_check_spam&spam_page=$i&ct_worked=1'>"
													.($i == $page ? "<span class='current_page'>$i</span>" : $i)
												."</a>"
											."</li>";
									}
							echo "</ul>";
						echo "</div>";
					}
				?>
				<div class="ct_to_hide" id="ct_tools_buttons" style="margin-top: 10px;">
					<button class= "button" id="ct_delete_all"><?php _e('Delete all comments from the list', 'cleantalk'); ?></button> 
					<button class="button" id="ct_delete_checked"><?php _e('Delete selected', 'cleantalk'); ?></button><br /><br />
				</div>
				<?php
			}
			echo $_SERVER['REMOTE_ADDR']=='127.0.0.1' ? '<button class="button ct_to_hide" id="ct_insert_comments">'. __('Insert comments', 'cleantalk') .' (100)</button> ' : '';
			echo $_SERVER['REMOTE_ADDR']=='127.0.0.1' ? '<button class="button ct_to_hide" id="ct_delete_comments">'. __('Delete comments', 'cleantalk') .' (110)</button>' : '';
			
			if($cnt_spam > 0){
				echo "<div id='ct_search_info'>"
						."<br />"
						.__('There is some differencies between blacklists database and our API mechanisms. Blacklists shows all history of spam activity, but our API (that used in spam checking) used another parameters, too: last day of activity, number of spam attacks during last days etc. This mechanisms help us to reduce number of false positivitie. So, there is nothing strange, if some emails/IPs will be not found by this checking.', 'cleantalk')
					."</div>";
			}
		?>
		<div>
			<button class="button" id="ct_stop_deletion" style="display:none;"><?php _e("Stop deletion", 'cleantalk'); ?></button>
		</div>
	</div>
	<?php
}

function ct_ajax_check_comments(){
	
	check_ajax_referer( 'ct_secret_nonce', 'security' );
	
	global $wpdb, $ct_options, $ct_ip_penalty_days;
	
	$ct_options = ct_get_options();
	
	if(isset($_POST['from'], $_POST['till'])){
		$from_date = date('Y-m-d', intval(strtotime($_POST['from'])));
		$till_date = date('Y-m-d', intval(strtotime($_POST['till'])));
	}
	
	// Gettings comments 100 unchecked comments
	if(isset($_COOKIE['ct_comments_safe_check'])){
		$c = $wpdb->get_results("
			SELECT comment_ID, comment_date_gmt, comment_author_IP, comment_author_email
			FROM {$wpdb->comments} as comm
			WHERE 
				(comm.comment_approved = '1' OR comm.comment_approved = '0')
				AND NOT EXISTS(
				SELECT comment_id, meta_key
					FROM {$wpdb->commentmeta} as meta
					WHERE comm.comment_ID = meta.comment_id AND (meta_key = 'ct_checked' OR meta_key = 'ct_bad')
			)
			ORDER BY comment_date_gmt
			LIMIT 100",
			ARRAY_A
		);
	}else{
		$params = array(
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => 'ct_checked',
					'compare' => 'NOT EXISTS'
				),
				array(
					'key' => 'ct_bad',
					'compare' => 'NOT EXISTS'
				)
			),
			'orderby' => 'comment_date_gmt',
			'order' => 'ASC',
			'number' => 100
		);	
		if(isset($from_date, $till_date)){
			$params['date_query'] = array(
				'column'   => 'comment_date_gmt',
				'after'     => $from_date,
				'before'    => $till_date,
				'inclusive' => true,						
			);
		}
		$c = get_comments( $params );
	}
	
	$check_result = array(
		'end' => 0,
		'checked' => 0,
		'spam' => 0,
		'bad' => 0,
		'error' => 0
	);
	
	if(sizeof($c)>0){
		
		// Coverting $c to objects
		if(is_array($c[0])){
			foreach($c as $key => $value){
				$c[$key] = (object)$value;
			} unset($key, $value);
		}
		
		if(!empty($_POST['accurate_check'])){
			// Leaving comments only with first comment's date. Unsetting others.
			
			foreach($c as $comment_index => $comment){
				
				if(!isset($curr_date))
					$curr_date = (substr($comment->comment_date_gmt, 0, 10) ? substr($comment->comment_date_gmt, 0, 10) : '');
				
				if(substr($comment->comment_date_gmt, 0, 10) != $curr_date)
					unset($c[$comment_index]);

			}
			unset($comment_index, $comment);
		}
		
		// Checking comments IP/Email. Gathering $data for check.
		$data = Array();
		for($i=0;$i<sizeof($c);$i++){
			
			$curr_ip = $c[$i]->comment_author_IP;
			$curr_email = $c[$i]->comment_author_email;
			
			// Check for identity
			$curr_ip    = preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $curr_ip) === 1 ? $curr_ip    : null;
			$curr_email = preg_match('/^\S+@\S+\.\S+$/', $curr_email) === 1                    ? $curr_email : null;
			
			if(empty($curr_ip) && empty($curr_email)){
				$check_result['bad']++;
				update_comment_meta($c[$i]->comment_ID,'ct_bad','1');
				unset($c[$i]);
			}else{
				if(!empty($curr_ip))
					$data[] = $curr_ip;
				if(!empty($curr_email))
					$data[] = $curr_email;
				// Patch for empty IP/Email
				$c[$i]->comment_author_IP    = empty($curr_ip)    ? 'none' : $curr_ip;				
			    $c[$i]->comment_author_email = empty($curr_email) ? 'none' : $curr_email;
			}
		}
		
		// Recombining after checking and unsettting
		$c = array_values($c);
		
		// Drop if data empty and there's no comments to check
		if(count($data) == 0){
			if($_POST['unchecked'] === 0)
				$check_result['end'] = 1;
			print json_encode($check_result);
			die();
		}
		
		$result = CleantalkHelper::api_method__spam_check_cms($ct_options['apikey'], $data, !empty($_POST['accurate_check']) ? $curr_date : null);
		
		if(empty($result['error'])){
			
			for($i=0;$i<sizeof($c);$i++){
				
				$mark_spam_ip = false;
				$mark_spam_email = false;

				$check_result['checked']++;
				update_comment_meta($c[$i]->comment_ID,'ct_checked',date("Y-m-d H:m:s"));
				$uip=$c[$i]->comment_author_IP;
				$uim=$c[$i]->comment_author_email;
				
				if(isset($result[$uip]) && $result[$uip]['appears'] == 1)
					$mark_spam_ip = true;
				
				if(isset($result[$uim]) && $result[$uim]['appears'] == 1)
					$mark_spam_email = true;
				
				if ($mark_spam_ip || $mark_spam_email){
					$check_result['spam']++;
					update_comment_meta($c[$i]->comment_ID,'ct_marked_as_spam','1');
				}
			}
			print json_encode($check_result);
			
		}else{
			$check_result['error'] = 1;
			$check_result['error_message'] = $result['error_string'];
			echo json_encode($check_result);
		}
	}else{
		$check_result['end'] = 1;
		print json_encode($check_result);
	}

	die;
}

function ct_ajax_info_comments($direct_call = false){
	
	if (!$direct_call)
        check_ajax_referer( 'ct_secret_nonce', 'security' );
	
	global $wpdb;
	
	// Checking dates value
	if(isset($_POST['from'], $_POST['till'])){
		$from_date = date('Y-m-d', intval(strtotime($_POST['from'])));
		$till_date = date('Y-m-d', intval(strtotime($_POST['till'])));
	}
	
	$metas = array('', 'ct_marked_as_spam', 'ct_checked', 'ct_bad');
	
	$result = array();
	foreach($metas as $meta){
		if(isset($_COOKIE['ct_comments_safe_check'])){
			$res = $wpdb->get_results("
				SELECT COUNT(DISTINCT comment_ID) AS cnt
				FROM {$wpdb->comments} AS comm
				WHERE comm.comment_approved IN ('1','0')".
				(!empty($meta)
					? " AND EXISTS (SELECT comment_id, meta_key
						FROM {$wpdb->commentmeta} meta
						WHERE comm.comment_ID = meta.comment_id AND meta_key = '$meta')"
					: '').
				(isset($from_date, $till_date)
					? " AND comment_date_gmt BETWEEN 
						STR_TO_DATE('$from_date', '%Y-%m-%d %H:%i:%s') AND 
						STR_TO_DATE('$till_date', '%Y-%m-%d %H:%i:%s')"
					: ''),
				ARRAY_A);
			$result[] = $res[0]['cnt'];
		}else{
			$params = array('fields' => 'ids', 'count' => true);
			if(!empty($meta))
				$params['meta_key'] = $meta;
			if(isset($from_date, $till_date))
				$params['date_query'] = array('column' => 'comment_date_gmt', 'after' => $from_date, 'before' => $till_date, 'inclusive' => true);
			$result[] = get_comments( $params );
		}
	}
	
	$cnt         = $result[0]; // Total comments
	$cnt_spam    = $result[1]; // Spam comments
	$cnt_checked = $result[2]; // Checked comments
	$cnt_bad     = $result[3]; // Bad comments (without IP and Email)
	
	$return = array(
		'message' => '',
		'total' => $cnt,
		'spam' => $cnt_spam,
		'checked' => $cnt_checked,
		'bad' => $cnt_bad,
	);
	
	$return['message'] .= sprintf (__("Total comments %s. Checked %s. Found %s spam comments. %s bad comments (without IP or email).", 'cleantalk'), $cnt, $cnt_checked, $cnt_spam, $cnt_bad);
	
    $backup_notice = '&nbsp;';
    if ($cnt_spam > 0){
        $backup_notice = __("Please do backup of WordPress database before delete any comments!", 'cleantalk');
	}
	$return['message'] .= "<p>$backup_notice</p>";
	
    if($direct_call){
		return $return['message'];
	}else{
		echo json_encode($return);
		die();
	}
	
}


function ct_ajax_insert_comments(){
	
	check_ajax_referer( 'ct_secret_nonce', 'security' );
	
	//* DELETION
	if(!empty($_POST['delete'])){
		$comments = get_comments(array('author_email' => 'stop_email@example.com'));
		$deleted = 0;
		$amount_to_delete = 15;
		foreach($comments as $comment){
			if($deleted >= $amount_to_delete)
				break;
			if(wp_delete_comment($comment->comment_ID, true))
				$deleted++;
		}
		 print "$deleted";
		die();
	}
	
	//* INSERTION
	global $wpdb;
	
	$to_insert = 100;
	$time = current_time('timestamp')-(730*86400);
	
	$result = $wpdb->get_results("SELECT network FROM `".$wpdb->base_prefix."cleantalk_sfw` LIMIT $to_insert;", ARRAY_A);
	
	if($result){
		$ip = array();
		foreach($result as $value){
			$ips[] = long2ip($value['network']);
		}
		unset($value);
	
		$inserted = 0;
		for($i=0, $j=0; $i<$to_insert; $i++, $j++){
			
			if(!isset($ips[$j]))
				$j=0;
			
			$rnd=mt_rand(1,100);
			
			$email="stop_email@example.com";
			
			$data = array(
				'comment_post_ID' => 1,
				'comment_author' => "author_$rnd",
				'comment_author_email' => $email,
				'comment_author_url' => 'http://',
				'comment_content' => "comment content ".mt_rand(1,10000)." ".mt_rand(1,10000)." ".mt_rand(1,10000),
				'comment_type' => '',
				'comment_parent' => 0,
				'user_id' => 1,
				'comment_author_IP' => $ips[$j],
				'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
				'comment_date' => date('Y-m-d H:i:s', $time),
				'comment_approved' => 1,
			);
			
			wp_insert_comment($data);
			$time = $time + 86400;
			$inserted++;
		}
	}else{
		$inserted = '0';
	}
	
	print $inserted;
	die();
}

function ct_ajax_delete_checked(){
	check_ajax_referer( 'ct_secret_nonce', 'security' );
	foreach($_POST['ids'] as $key=>$value){
		wp_delete_comment($value, false);
	}
	die();
}

function ct_ajax_delete_all(){
	check_ajax_referer( 'ct_secret_nonce', 'security' );
	$args_spam = array(
		'number'=>100,
		'meta_query' => array(
			Array(
				'key' => 'ct_marked_as_spam',
				'value' => '1',
				'compare' => 'NUMERIC'
			)
		)
	);	
	$c_spam=get_comments($args_spam);

	$cnt=sizeof($c_spam);
	
	$args_spam = array(
		'count'=>true,
		'meta_query' => array(
			Array(
				'key' => 'ct_marked_as_spam',
				'value' => '1',
				'compare' => 'NUMERIC'
			)
		)
	);
	$cnt_all=get_comments($args_spam);

	for($i=0;$i<sizeof($c_spam);$i++){
		wp_delete_comment($c_spam[$i]->comment_ID, false);
		usleep(10000);
	}
	print $cnt_all;
	die();
}

function ct_ajax_clear_comments(){
	
	check_ajax_referer( 'ct_secret_nonce', 'security' );
	global $wpdb;
	$wpdb->query("DELETE 
		FROM {$wpdb->commentmeta}
		WHERE meta_key IN ('ct_checked', 'ct_marked_as_spam', 'ct_bad');");
	die();
}

/**
 * Admin action 'comment_unapproved_to_approved' - Approve comment, delete from the deleting list
 */
function ct_comment_check_approve_comment(){
	
	check_ajax_referer( 'ct_secret_nonce', 'security' );

	$id=$_POST['id'];
	$comment = get_comment($id, 'ARRAY_A');
	$comment['comment_content'] = ct_unmark_red($comment['comment_content']);
	$comment['comment_approved'] = 1;
	update_comment_meta($id, 'ct_marked_as_spam', 0);
	wp_update_comment($comment);

	die();
}
?>