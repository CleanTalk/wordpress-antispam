<?php

add_action('admin_menu', 'ct_add_users_menu');
add_action( 'wp_ajax_ajax_check_users', 'ct_ajax_check_users' );
add_action( 'wp_ajax_ajax_info_users', 'ct_ajax_info_users' );
add_action( 'wp_ajax_ajax_insert_users', 'ct_ajax_insert_users' );
add_action( 'wp_ajax_ajax_delete_checked_users', 'ct_ajax_delete_checked_users' );
add_action( 'wp_ajax_ajax_delete_all_users', 'ct_ajax_delete_all_users' );
add_action( 'wp_ajax_ajax_clear_users', 'ct_ajax_clear_users' );
add_action( 'wp_ajax_ajax_ct_approve_user', 'ct_usercheck_approve_user' );
add_action( 'wp_ajax_ajax_ct_get_csv_file', 'ct_usercheck_get_csv_file' );

function ct_add_users_menu(){
	if(current_user_can('activate_plugins'))
		add_users_page( __("Check for spam", 'cleantalk'), __("Find spam users", 'cleantalk'), 'read', 'ct_check_users', 'ct_show_users_page');
}

function ct_show_users_page(){
    global $ct_plugin_name, $wpdb;
	
	// Getting total spam users
	$r = $wpdb->get_results("
		SELECT 
			DISTINCT COUNT($wpdb->users.ID) AS cnt 
			FROM $wpdb->users 
		INNER JOIN $wpdb->usermeta
			ON $wpdb->users.ID = $wpdb->usermeta.user_id 
			WHERE $wpdb->usermeta.meta_key='ct_marked_as_spam';"
	, ARRAY_A);
	$cnt_spam1=$r[0]['cnt'];
	
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
		<div id="ct_deleting_message" style="display:none">
			<?php _e("Please wait for a while. CleanTalk is deleting spam users. Users left: ", 'cleantalk'); ?> <span id="cleantalk_users_left">
            <?php echo $cnt_spam1;?>
            </span>
		</div>
				
	<!-- Main info --> 
		<h3 id="ct_checking_status"><?php echo ct_ajax_info_users(true); ?></h3>
		
	<!-- Check options -->
		<div class="ct_to_hide" id="ct_check_params_wrapper">
			<button class="button ct_check_params_elem" id="ct_check_spam_button"><?php _e("Start check", 'cleantalk'); ?></button>
			<?php if(!empty($_COOKIE['ct_paused_users_check'])) { ?><button class="button ct_check_params_elem" id="ct_proceed_check_button"><?php _e("Continue check", 'cleantalk'); ?></button><?php } ?>
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
			<?php _e("Please wait for a while. CleanTalk is checking all users via blacklist database at cleantalk.org. You will have option to delete found spam users after plugin finish.", 'cleantalk'); ?>
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
			
			$c_spam=get_users($args_spam);
			if($cnt_spam1 > 0){
			
				$pages = ceil(intval($cnt_spam1)/$on_page);
				if($pages && $pages != 1){
					echo "<div class='ct_to_hide pagination'>"
							."<b>Pages:</b>"
							."<ul class='pagination'>";
								for($i = 1; $i <= $pages; $i++){
									echo "<li class='pagination'>"
											."<a href='users.php?page=ct_check_users&spam_page=$i&ct_worked=1'>"
												.($i == $page ? "<span class='current_page'>$i</span>" : $i)
											."</a>"
										."</li>";
								}
						echo "</ul>";
					echo "</div>";
				}
		?>
				<table class="ct_to_hide widefat fixed comments" id="ct_check_users_table">
					<thead>
						<th scope="col" id="cb" class="manage-column column-cb check-column">
							<label class="screen-reader-text" for="cb-select-all-1">Select All</label>
							<input id="cb-select-all-1" type="checkbox"/>
						</th>
						<th scope="col" id="author" class="manage-column column-slug"><?php _e('Username');?></th>
						<th scope="col" id="comment" class="manage-column column-comment"><?php _e('Name');?></th>
						<th scope="col" id="response" class="manage-column column-comment"><?php _e('E-mail');?></th>
						<th scope="col" id="role" class="manage-column column-response sortable desc"><?php _e('Role');?></th>
						<th scope="col" id="posts" class="manage-column column-response sortable desc"><?php _e('Posts');?></th>
					</thead>
					<tbody id="the-comment-list" data-wp-lists="list:comment">
						<?php
							for($i=0;$i<sizeof($c_spam);$i++){
								$id =  $c_spam[$i]->ID;
								$login =  $c_spam[$i]->data->user_login;
								$email =  $c_spam[$i]->data->user_email;
								
								echo "<tr id='comment-$id' class='comment even thread-even depth-1 approved  cleantalk_user' data-id='$id'>"
									."<th scope='row' class='check-column'>"
										."<label class='screen-reader-text' for='cb-select-$id'>Select user</label>"
										."<input id='cb-select-$id' type='checkbox' name='del_comments[]' />"
									."</th>"
									."<td class='author column-author' nowrap>"
										."<strong>"
											.get_avatar( $c_spam[$i]->data->ID , 32)
											.$login
										."</strong>"
										."<br/>"
										."<br/>";
										
										// Outputs email if exists
										if(!empty($email)){
											echo "<a href='mailto:$email'>$email</a>"
												."<a href='https://cleantalk.org/blacklists/$email' target='_blank'>"
													."&nbsp;<img src='".plugin_dir_url(__FILE__)."images/new_window.gif' border='0' style='float:none' />"
												."</a>";
										}else{
											echo "No email";
										}
										echo "<br/>";
										
										// Outputs IP if exists
										$user_meta = get_user_meta($id, 'session_tokens', true);
										if(!empty($user_meta) && is_array($user_meta)){
											$user_meta=array_values($user_meta);
											if(!empty($user_meta[0]['ip'])){
												$ip = $user_meta[0]['ip'];
												echo "<a href='user-edit.php?user_id=$id'>$ip</a>"
													."<a href='https://cleantalk.org/blacklists/$ip' target='_blank'>"
														."&nbsp;<img src='".plugin_dir_url(__FILE__)."images/new_window.gif' border='0' style='float:none' />"
													."</a>";
											}else
												echo "No IP adress";
										}else
											echo "No IP adress";
									echo "</td>";
							?>
									<td class="comment column-comment">
										<div class="submitted-on">
											<?php print $c_spam[$i]->data->display_name; ?>
											<div style="height:16px; display: none;" id="cleantalk_button_set_<?php print $id; ?>">
												<a href="#" class="cleantalk_delete_from_list_button"  	data-id="<?php print $id; ?>" style="color:#0a0;" onclick="return false;" onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';"><?php _e("Approve", "cleantalk"); ?></a>
												&nbsp;|&nbsp;
												<a href="#" class="cleantalk_delete_user_button" id="cleantalk_delete_user_<?php print $id; ?>" data-id="<?php print $id; ?>" style="color:#a00;display:none;" onclick="return false;" onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';"><?php _e("Delete", "cleantalk"); ?></a>
											</div>
										</div>
									</td>
									<td class="comment column-comment">
										<?php print $email; ?>
									</td>
									<td class="comment column-comment">
										<?php
											$info=get_userdata( $id );
											print implode(', ', $info->roles);
										?>
									</td>
									<td class="comment column-comment">
										<?php
											print count_user_posts($id);
										?>
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
												."<a href='users.php?page=ct_check_users&spam_page=$i&ct_worked=1'>"
													.($i == $page ? "<span class='current_page'>$i</span>" : $i)
												."</a>"
											."</li>";
									}
							echo "</ul>";
						echo "</div>";
					}
				?>			
				<div class="ct_to_hide" id="ct_tools_buttons" style="margin-top: 10px;">
					<button class="button" id="ct_delete_all_users"><?php _e('Delete all users from list', 'cleantalk'); ?></button> 
					<button class="button" id="ct_delete_checked_users"><?php _e('Delete selected', 'cleantalk'); ?></button>
					<button class="button" id="ct_get_csv_file"><?php _e('Download results in CSV', 'cleantalk'); ?></button>
				</div>
				<?php
			}
			echo $_SERVER['REMOTE_ADDR']=='127.0.0.1' ? '<br /><button class=" ct_to_hide button" id="ct_insert_users">'. __('Insert accounts', 'cleantalk'). ' (100)</button> ' : '';
			echo $_SERVER['REMOTE_ADDR']=='127.0.0.1' ?       '<button class="ct_to_hide button" id="ct_delete_users">'. __('Delete accounts', 'cleantalk'). ' (110)</button><br />' : '';
			
			if($cnt_spam1 > 0){
				echo "<div id='ct_search_info'>"
						."<br />"
						.__("There is some differencies between blacklists database and our API mechanisms. Blacklists shows all history of spam activity, but our API (that used in spam checking) used another parameters, too: last day of activity, number of spam attacks during last days etc. This mechanisms help us to reduce number of false positivitie. So, there is nothing strange, if some emails/IPs will be not found by this checking.", 'cleantalk')
					."</div>";
			}
		?>		
		<div>
			<button class="button" id="ct_stop_deletion" style="display:none;"><?php _e("Stop deletion", 'cleantalk'); ?></button>
		</div>
		<div id='ct_csv_wrapper' style="display: none;"></div>
	</div>
<?php
}

function ct_ajax_check_users(){
	
	check_ajax_referer('ct_secret_nonce', 'security');
	
	global $ct_options,$ct_ip_penalty_days;

	$ct_options = ct_get_options();
    
    $skip_roles = array(
        'administrator'
    );

	$params = array(
		// 'fields' => array(
			// 'ID',
			// 'user_login',
			// 'user_email',
			// 'user_registered',
		// ),
		'meta_query' => array(
			'relation' => 'AND',
			array(
				'key' => 'ct_checked',
				'compare' => 'NOT EXISTS'
			),
			array(
				'key' => 'ct_bad',
				'compare' => 'NOT EXISTS'
			),
		),
		'orderby' => 'registered',
		'order' => 'ASC',
		'number' => 100
	);
	
	if(isset($_POST['from'], $_POST['till'])){
		
		$from_date = date('Y-m-d', intval(strtotime($_POST['from'])));
		$till_date = date('Y-m-d', intval(strtotime($_POST['till'])));

		$params['date_query'] = array(
			'column'   => 'user_registered',
			'after'     => $from_date,
			'before'    => $till_date,
			'inclusive' => true,						
		);
	}
	
	$u = get_users( $params );
	
	$check_result = array(
		'end' => 0,
		'checked' => 0,
		'spam' => 0,
		'bad' => 0,
		'error' => 0
	);
	
    if(count($u) > 0){
		
		if(!empty($_POST['accurate_check'])){
			// Leaving users only with first comment's date. Unsetting others.
			foreach($u as $user_index => $user){
				
				if(!isset($curr_date))
					$curr_date = (substr($user->data->user_registered, 0, 10) ? substr($user->data->user_registered, 0, 10) : '');

				if(substr($user->data->user_registered, 0, 10) != $curr_date)
					unset($u[$user_index]);

			}
			unset($user_index, $user);
		}
		
		// Checking comments IP/Email. Gathering $data for check.
		$data=Array();
		for($i=0; $i < count($u); $i++){
			
			$user_meta = get_user_meta($u[$i]->ID, 'session_tokens', true);
			if(is_array($user_meta))
				$user_meta = array_values($user_meta);
			
			$curr_ip    = !empty($user_meta[0]['ip'])      ? trim($user_meta[0]['ip'])      : '';
			$curr_email = !empty($u[$i]->data->user_email) ? trim($u[$i]->data->user_email) : '';

			// Check for identity
			$curr_ip    = preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $curr_ip) === 1 ? $curr_ip    : null;
			$curr_email = preg_match('/^\S+@\S+\.\S+$/', $curr_email) === 1                    ? $curr_email : null;
			
			if(empty($curr_ip) && empty($curr_email)){
				$check_result['bad']++;
				update_user_meta($u[$i]->ID,'ct_bad','1',true);
				unset($u[$i]);
			}else{
				if(!empty($curr_ip))
					$data[] = $curr_ip;
				if(!empty($curr_email))
					$data[] = $curr_email;
				// Patch for empty IP/Email
				$u[$i]->data->user_ip    = empty($curr_ip)    ? 'none' : $curr_ip;				
			    $u[$i]->data->user_email = empty($curr_email) ? 'none' : $curr_email;
			}
		}
		
		// Recombining after checking and unsettting
		$u = array_values($u);
		
		// Drop if data empty and there's no users to check
		if(count($data) == 0){
			if($_POST['unchecked'] === 0)
				$check_result['end'] = 1;
			print json_encode($check_result);
			die();
		}
		
		$result = CleantalkHelper::api_method__spam_check_cms($ct_options['apikey'], $data, !empty($_POST['accurate_check']) ? $curr_date : null);
		
		if(empty($result['error'])){
				
				// Opening CSV file
				$current_user = wp_get_current_user();
				$filename = WP_PLUGIN_DIR."/cleantalk-spam-protect/check-results/user_check_by_{$current_user->user_login}.csv";
				$text = "";
				
				if(isset($_POST['new_check']) && $_POST['new_check'] == 'true'){
					$file_desc = fopen($filename, 'w');
					$text .= "login,email,ip".PHP_EOL;
				}else
					$file_desc = fopen($filename, 'a+');
				// End of Opening CSV			
							
				for($i=0;$i<sizeof($u);$i++){
					
					$check_result['checked']++;
					update_user_meta($u[$i]->ID,'ct_checked',date("Y-m-d H:m:s"),true);
					
					// Do not display forbidden roles.
					foreach ($skip_roles as $role) {
						if (in_array($role, $u[$i]->roles)){
							delete_user_meta($u[$i]->ID, 'ct_marked_as_spam');
							continue 2;
						}
					}
					
					$mark_spam_ip = false;
					$mark_spam_email = false;

					$uip = $u[$i]->data->user_ip;
					$uim = $u[$i]->data->user_email;
					
					if(isset($result[$uip]) && $result[$uip]['appears'] == 1)
						$mark_spam_ip = true;
					
					if(isset($result[$uim]) && $result[$uim]['appears'] == 1)
						$mark_spam_email = true;
					
					if ($mark_spam_ip || $mark_spam_email){
						$check_result['spam']++;
						update_user_meta($u[$i]->ID,'ct_marked_as_spam','1',true);
						$text .= $u[$i]->user_login.',';
						$text .= ($mark_spam_email ? $uim : '').',';
						$text .= ($mark_spam_ip    ? $uip : '').PHP_EOL;	
					}
							
				}
				fwrite($file_desc, $text);
				fclose($file_desc);
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

function ct_ajax_info_users($direct_call = false)
{
    if (!$direct_call)
	    check_ajax_referer( 'ct_secret_nonce', 'security' );
	
	// Checking dates value
	if(isset($_POST['from'], $_POST['till'])){
		
		$from_date = date('Y-m-d', intval(strtotime($_POST['from'])));
		$till_date = date('Y-m-d', intval(strtotime($_POST['till'])));
	}

	// Total users
	$params = array(
		'fields' => 'ID',
		'count'=>true,
	);
	if(isset($from_date, $till_date)) $params['date_query'] = array('column' => 'user_registered', 'after' => $from_date, 'before' => $till_date, 'inclusive' => true);
	$tmp = new WP_User_Query($params);
	$cnt = $tmp->get_total();

	// Checked users
	$params = array(
		'fields' => 'ID',
		'meta_key' => 'ct_checked',
		'count_total' => true,
	);
	if(isset($from_date, $till_date)) $params['date_query'] = array('column' => 'user_registered', 'after' => $from_date, 'before' => $till_date, 'inclusive' => true);
	$tmp = new WP_User_Query($params);
	$cnt_checked = $tmp->get_total();
	
	// Spam users
	$params = array(
		'fields' => 'ID',
		'meta_key' => 'ct_marked_as_spam',
		'count_total' => true,
	);
	if(isset($from_date, $till_date)) $params['date_query'] = array('column' => 'user_registered', 'after' => $from_date, 'before' => $till_date, 'inclusive' => true);
	$tmp = new WP_User_Query($params);
	$cnt_spam = $tmp->get_total();
	
	// Bad users (without IP and Email)
	$params = array(
		'fields' => 'ID',
		'meta_key' => 'ct_bad',
		'count_total' => true,
	);
	if(isset($from_date, $till_date)) $params['date_query'] = array('column' => 'user_registered', 'after' => $from_date, 'before' => $till_date, 'inclusive' => true);
	$tmp = new WP_User_Query($params);
	$cnt_bad = $tmp->get_total();
	
	$return = array(
		'message' => '',
		'total' => $cnt,
		'spam' => $cnt_spam,
		'checked' => $cnt_checked,
		'bad' => $cnt_bad,
	);
	
	$return['message'] .= sprintf (__("Total users %s, checked %s, found %s spam users and %s bad users (without IP or email)", 'cleantalk'), $cnt, $cnt_checked, $cnt_spam, $cnt_bad);
	
    $backup_notice = '&nbsp;';
    if ($cnt_spam > 0) {
        $backup_notice = __("Please do backup of WordPress database before delete any accounts!", 'cleantalk');
    }
	$return['message'] .= "<p>$backup_notice</p>";
	
	if($direct_call){
		return $return['message'];
	}else{
		echo json_encode($return);
		die();
	}
	
    return null;
}

function ct_ajax_insert_users()
{
	check_ajax_referer( 'ct_secret_nonce', 'security' );
	
	//* DELETION
	if(!empty($_POST['delete'])){
		$users = get_users(array('search' => '*user_*', 'search_columns' => array('login', 'nicename')));
		$deleted = 0;
		$amount_to_delete = 15;
		foreach($users as $user){
			if($deleted >= $amount_to_delete)
				break;
			if(wp_delete_user($user->ID))
				$deleted++;
		}
		 print "$deleted";
		die();
	}
	//*/
	
	//* INSERTION
	global $wpdb;
	$to_insert = 100;
	$result = $wpdb->get_results("SELECT network FROM `".$wpdb->base_prefix."cleantalk_sfw` LIMIT $to_insert;", ARRAY_A);
	
	if($result){
		$ip = array();
		foreach($result as $value){
			$ips[] = long2ip($value['network']);
		}
		unset($value);
		
		$inserted = 0;
		for($i=0; $i<$to_insert; $i++){
			$rnd=mt_rand(1,10000000);
			
			$user_name = "user_$rnd";
			$email="stop_email_$rnd@example.com";
			
			$user_id = wp_create_user(
				$user_name,
				rand(),
				$email
			);

			$curr_user = get_user_by('email', $email);
						
			update_user_meta($curr_user->ID, 'session_tokens', array($rnd => array('ip' => $ips[$i])));
			
			if (is_int($user_id))
				$inserted++;
			
		}
	}else{
		$inserted = '0';
	}
	//*/

    print "$inserted";
	die();
}

function ct_ajax_delete_checked_users()
{
	check_ajax_referer( 'ct_secret_nonce', 'security' );
	foreach($_POST['ids'] as $key=>$value)
	{
		wp_delete_user($value);
	}
	die();
}

function ct_ajax_delete_all_users()
{
	check_ajax_referer( 'ct_secret_nonce', 'security' );

    global $wpdb;
    
	$r = $wpdb->get_results("select count(*) as cnt from $wpdb->usermeta where meta_key='ct_marked_as_spam';");	
	$count_all = $r ? $r[0]->cnt : 0;
    
	$args = array(
		'meta_key' => 'ct_marked_as_spam',
		'meta_value' => '1',
		'fields' => array('ID'),
		'number' => 50
	);
	$users = get_users($args);
	
    if ($users){
        foreach($users as $user){
            wp_delete_user($user->ID);
            usleep(5000);
        }
	}

	print $count_all;
	die();
}

function ct_ajax_clear_users()
{
	check_ajax_referer( 'ct_secret_nonce', 'security' );
	global $wpdb;
	$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('ct_checked', 'ct_marked_as_spam', 'ct_bad');");
	die();
}

/**
 * Admin action 'user_unapproved_to_approved' - Approve user, delete from the deleting list
 */
function ct_usercheck_approve_user() {
	
	check_ajax_referer( 'ct_secret_nonce', 'security' );

	delete_metadata('user', $_POST['id'], 'ct_marked_as_spam');

	die();
}

/**
 * Admin action 'wp_ajax_ajax_ct_get_csv_file' - prints CSV file to AJAX
 */
function ct_usercheck_get_csv_file() {
	
	check_ajax_referer( 'ct_secret_nonce', 'security' );

	$filename = !empty($_POST['filename']) ? $_POST['filename'] : false;
	
	if($filename !== false && file_exists(WP_PLUGIN_DIR."/cleantalk-spam-protect/check-results/{$filename}.csv"))
		$output = 1;
	else
		$output = 0;
	
	echo $output;
	
	die();
}

?>