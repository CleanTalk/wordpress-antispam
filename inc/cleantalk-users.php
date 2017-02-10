<?php
add_action('admin_menu', 'ct_add_users_menu');
add_action('admin_print_footer_scripts','ct_add_users_button');
add_action( 'wp_ajax_ajax_check_users', 'ct_ajax_check_users' );
add_action( 'wp_ajax_ajax_info_users', 'ct_ajax_info_users' );
add_action( 'wp_ajax_ajax_insert_users', 'ct_ajax_insert_users' );
add_action( 'wp_ajax_ajax_delete_checked_users', 'ct_ajax_delete_checked_users' );
add_action( 'wp_ajax_ajax_delete_all_users', 'ct_ajax_delete_all_users' );
add_action( 'wp_ajax_ajax_clear_users', 'ct_ajax_clear_users' );
add_action( 'wp_ajax_ajax_ct_approve_user', 'ct_usercheck_approve_user' );

function ct_add_users_menu(){
	
	if(current_user_can('activate_plugins'))
		add_users_page( __("Check for spam", 'cleantalk'), __("Check for spam", 'cleantalk'), 'read', 'ct_check_users', 'ct_show_users_page');
	
}

function ct_show_users_page()
{
    global $ct_plugin_name;
	?>
	<div class="wrap">
		<h2><?php echo $ct_plugin_name; ?></h2><br />
		<?php
		global $wpdb;
		$r=$wpdb->get_results("
			SELECT 
				COUNT(all_users.ID) - COUNT(meta_users.user_id) AS cnt_unchecked
				FROM $wpdb->users all_users
			LEFT JOIN $wpdb->usermeta meta_users 
				ON all_users.ID = meta_users.user_id
				WHERE meta_users.meta_key='ct_checked'
					OR meta_users.meta_key='ct_hash';
		");
		
		$cnt_unchecked=$r[0]->cnt_unchecked;
		
		echo(" cnt_unchecked $cnt_unchecked<br>");
		
		/*$args_spam = array(
			'meta_query' => array(
				Array(
					'key' => 'ct_marked_as_spam',
					'compare' => 'EXISTS'
				)
			)
		);*/
		$r=$wpdb->get_results("select distinct count($wpdb->users.ID) as cnt from $wpdb->users inner join $wpdb->usermeta on $wpdb->users.ID=$wpdb->usermeta.user_id where $wpdb->usermeta.meta_key='ct_marked_as_spam';", ARRAY_A);
$cnt_spam1=$r[0]['cnt'];
?>		

		<div id="ct_error_message" style="display:none">
			<h3 style="text-align: center;width:90%;">
				<?php _e("Ajax error. Process will be automatically restarted in 3 seconds. Status: ", 'cleantalk'); ?><span id="cleantalk_ajax_error"></span> (<span id="cleantalk_js_func"></span>)
			</h3>
			<h4 style="text-align:center;width:90%;">Please, check for JavaScript errors in your dashboard and and repair it.</h4>
		</div>
		<div id="ct_deleting_message" style="display:none">
			<?php _e("Please wait for a while. CleanTalk is deleting spam users. Users left: ", 'cleantalk'); ?> <span id="cleantalk_users_left">
            <?php echo $cnt_spam1;?>
            </span>
		</div>
		<div id="ct_done_message" <?php if($cnt_unchecked>0) print 'style="display:none"'; ?>>
			<?php //_e("Done. All comments tested via blacklists database, please see result bellow.", 'cleantalk'); ?>
		</div>
		<h3 id="ct_checking_users_status" style="text-align:center;width:90%;"><?php ct_ajax_info_users(true);?></h3>
		<div style="text-align:center;width:100%;display:none;" id="ct_preloader"><img border=0 src="<?php print plugin_dir_url(__FILE__); ?>images/preloader.gif" /></div>
		<div id="ct_working_message" style="margin:auto;padding:3px;width:70%;border:2px dotted gray;display:none;background:#ffff99;margin-top: 1em;">
			<?php _e("Please wait for a while. CleanTalk is checking all users via blacklist database at cleantalk.org. You will have option to delete found spam users after plugin finish.", 'cleantalk'); ?>
		</div>
		<?php
			$page=1;
			if(isset($_GET['spam_page']))
			{
				$page=intval($_GET['spam_page']);
			}
			$args_spam = array(
				'meta_query' => array(
					Array(
						'key' => 'ct_marked_as_spam',
						'value' => '1',
						'compare' => 'NUMERIC'
					)
				),
				'number'=>30,
				'offset'=>($page-1)*30
			);
			
			$c_spam=get_users($args_spam);
			if($cnt_spam1>0)
			{
		?>
		<table class="widefat fixed comments" id="ct_check_users_table">
			<thead>
				<th scope="col" id="cb" class="manage-column column-cb check-column">
					<label class="screen-reader-text" for="cb-select-all-1">Select All</label>
					<input id="cb-select-all-1" type="checkbox"/>
				</th>
				<th scope="col" id="author" class="manage-column column-slug"><?php _e('Username');?></th>
				<th scope="col" id="comment" class="manage-column column-comment"><?php _e( 'Name' );;?></th>
				<th scope="col" id="response" class="manage-column column-comment">E-mail</th>
				<th scope="col" id="role" class="manage-column column-response sortable desc"><?php _e( 'Role' );?></th>
				<th scope="col" id="posts" class="manage-column column-response sortable desc"><?php print _e( 'Posts' );?></th>
			</thead>
			<tbody id="the-comment-list" data-wp-lists="list:comment">
				<?php
					for($i=0;$i<sizeof($c_spam);$i++)
					{
						?>
						<tr id="comment-<?php print $c_spam[$i]->ID; ?>" class="comment even thread-even depth-1 approved  cleantalk_user" data-id="<?php print $c_spam[$i]->ID; ?>">
						<th scope="row" class="check-column">
							<label class="screen-reader-text" for="cb-select-<?php print $c_spam[$i]->ID; ?>">Select user</label>
							<input id="cb-select-<?php print $c_spam[$i]->ID; ?>" type="checkbox" name="del_comments[]" value="<?php print $c_spam[$i]->comment_ID; ?>"/>
						</th>
						<td class="author column-author" nowrap>
						<strong>
							<?php echo get_avatar( $c_spam[$i]->data->user_email , 32); ?>
							 <?php print $c_spam[$i]->data->user_login; ?>
							</strong>
							<br/>
							<a href="mailto:<?php print $c_spam[$i]->data->user_email; ?>"><?php print $c_spam[$i]->data->user_email; ?></a> <a href="https://cleantalk.org/blacklists/<?php print $c_spam[$i]->data->user_email ; ?>" target="_blank"><img src="<?php print plugin_dir_url(__FILE__); ?>images/new_window.gif" border="0" style="float:none"/></a>
							<br/>
							<?php
							$user_meta=get_user_meta($c_spam[$i]->ID, 'session_tokens', true);
							if(is_array($user_meta))
							{
								$user_meta=array_values($user_meta);
							}
							$ip='';
							if(@isset($user_meta[0]['ip']))
							{
								$ip=$user_meta[0]['ip'];
								?>
								<a href="user-edit.php?user_id=<?php print $c_spam[$i]->ID ; ?>"><?php print $ip ; ?></a> 
								<a href="https://cleantalk.org/blacklists/<?php print $ip ; ?>" target="_blank"><img src="<?php print plugin_dir_url(__FILE__); ?>images/new_window.gif" border="0" style="float:none"/></a>
								<?php
							}
								?>
						</td>
						<td class="comment column-comment">
							<div class="submitted-on">
								<?php print $c_spam[$i]->data->display_name; ?>
								<div style="height:16px; display: none;" id="cleantalk_button_set_<?php print $c_spam[$i]->ID; ?>">
									<a href="#" class="cleantalk_delete_from_list_button"  	data-id="<?php print $c_spam[$i]->ID; ?>" style="color:#0a0;" onclick="return false;" onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';"><?php _e("Approve", "cleantalk"); ?></a>
									&nbsp;|&nbsp;
									<a href="#" class="cleantalk_delete_user_button" id="cleantalk_delete_user_<?php print $c_spam[$i]->ID; ?>" data-id="<?php print $c_spam[$i]->ID; ?>" style="color:#a00;display:none;" onclick="return false;" onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';"><?php _e("Delete", "cleantalk"); ?></a>
								</div>
							</div>
						</td>
						<td class="comment column-comment">
							<?php print $c_spam[$i]->data->user_email; ?>
						</td>
						<td class="comment column-comment">
							<?php
								$info=get_userdata( $c_spam[$i]->ID );
								print implode(', ', $info->roles);
							?>
						</td>
						<td class="comment column-comment">
							<?php
								print count_user_posts($c_spam[$i]->ID);
							?>
						</td>
						</tr>
						<?php
					}
					if($cnt_spam1>30)
					{
				?>
				<tr class="comment even thread-even depth-1 approved">
					<td colspan="4"> 
						<?php
							
							$pages=ceil(intval($cnt_spam1)/30);
							for($i=1;$i<=$pages;$i++)
							{
								if($i==$page)
								{
									print "<a href='users.php?page=ct_check_users&spam_page=$i&ct_worked=1'><b>$i</b></a> ";
								}
								else
								{
									print "<a href='users.php?page=ct_check_users&spam_page=$i&ct_worked=1'>$i</a> ";
								}								
							}
						?>
					</td>
				</tr>
				<?php
					}
				?>
			</tbody>
		</table>
        <div id="ct_tools_buttons" style="margin-top: 10px;">
		<button class="button" id="ct_delete_all_users"><?php _e('Delete all users from list', 'cleantalk'); ?></button> 
		<button class="button" id="ct_delete_checked_users"><?php _e('Delete selected', 'cleantalk'); ?></button>
		<?php
		}
		if($_SERVER['REMOTE_ADDR']=='127.0.0.1')print '<button class="button" id="ct_insert_users">'. __('Insert accounts', 'cleantalk'). '</button><br />';

		?>
        </div>
		<br /><br />
        <table>
            <tr>
                <td>
		            <button class="button" id="ct_check_users_button"><?php _e("Check for spam", 'cleantalk'); ?></button>
                </td>
                <td style="padding-left: 2em;">
                <div id="ct_info_message" class="wrap"><?php _e("The plugin will check all users against blacklists database and show you senders that have spam activity on other websites. Just click 'Find spam users' to start.", 'cleantalk'); ?>
                </td>
            </tr>
        </table>
		<?php
			if($cnt_spam1>0)
			{
				print "
        <div id=\"ct_search_info\">
        <br />".
		__("There is some differencies between blacklists database and our API mechanisms. Blacklists shows all history of spam activity, but our API (that used in spam checking) used another parameters, too: last day of activity, number of spam attacks during last days etc. This mechanisms help us to reduce number of false positivitie. So, there is nothing strange, if some emails/IPs will be not found by this checking.", 'cleantalk')
		."</div>";
			}
		?>
			
	</div>
    
    <div>
		<button class="button" id="ct_stop_deletion" style="display:none;"><?php _e("Stop deletion", 'cleantalk'); ?></button>
    </div>
	<?php
}

function ct_add_users_button()
{
    global $cleantalk_plugin_version;

}

function ct_ajax_check_users()
{
	global $ct_options,$ct_ip_penalty_days;

	check_ajax_referer('ct_secret_nonce', 'security');

	$ct_options = ct_get_options();
    
    $skip_roles = array(
        'administrator'
    );

	$args_unchecked = array(
		'meta_query' => array(
			Array(
				'key' => 'ct_checked',
				'value' => '1',
				'compare' => 'NOT EXISTS'
			),
		),
		'orderby' => 'registered',
		'order' => 'ASC',
		'number' => 100
	);
	
	$u = get_users($args_unchecked);
		
    if(sizeof($u)>0){
		
		foreach($u as $user_index => $user){
			
			if(!isset($curr_date))
				$curr_date = (substr($user->data->user_registered, 0, 9) ? substr($user->data->user_registered, 0, 9) : '');

			if(substr($user->data->user_registered, 0, 9) != $curr_date)
				unset($u[$user_index]);

		}
		unset($user_index, $user);
				
		$data=Array();
		for($i=0;$i<sizeof($u);$i++)
		{
			$user_meta=get_user_meta($u[$i]->ID, 'session_tokens', true);
							
			if(is_array($user_meta))
			{
				$user_meta=array_values($user_meta);
			}
			if(isset($user_meta[0]['ip']))
			{
				$data[]=$user_meta[0]['ip'];
			    $u[$i]->data->user_ip = $user_meta[0]['ip'];
			} else {
			    $u[$i]->data->user_ip = null;
            }
            if (isset($u[$i]->data->user_email) && $u[$i]->data->user_email) {
			    $data[]=$u[$i]->data->user_email;
            }
		}
		if(count($data) == 0){
			print 0;
			die();
		}
		$data=implode(',',$data);
				
        $request=Array();
        $request['method_name'] = 'spam_check_cms'; 
        $request['auth_key'] = $ct_options['apikey'];
        $request['data'] = $data; 
        $url='https://api.cleantalk.org';
        if(!function_exists('sendRawRequest'))
        {
            require_once('cleantalk.class.php');
        }
        $result=sendRawRequest($url, $request, false, 5);
        		
        $result=json_decode($result);

		if(isset($result->error_message))
		{
			print $result->error_message;
		}
		else
		{
			for($i=0;$i<sizeof($u);$i++)
			{
				update_user_meta($u[$i]->ID,'ct_checked',date("Y-m-d H:m:s"),true);
                //
                // Do not display forbidden roles.
                //
                $skip_user = false;
                foreach ($skip_roles as $role) {
                    if (!$skip_user && in_array($role, $u[$i]->roles)) {
					    delete_user_meta($u[$i]->ID, 'ct_marked_as_spam');
                        $skip_user = true;
                        continue;
                    }
                }
                if ($skip_user) {
                    continue;
                }
                $mark_spam_ip = false;
                $mark_spam_email = false;
                $ip_update_time = 0; 


                $uip = $u[$i]->data->user_ip;
                $uim = $u[$i]->data->user_email;

				if(isset($result->data->$uip) && $result->data->$uip->appears == 1)
				{
				    $mark_spam_ip = true;
                    $ip_update_time = strtotime($result->data->$uip->updated);
				}
				if(isset($result->data->$uim) && $result->data->$uim->appears==1)
				{
                    $mark_spam_email = true;
				}
                /*
				// Do not use the spam records becaus it was a spammer far time ago.
                if (time() - $ip_update_time > 86400 * $ct_ip_penalty_days) {
                    $mark_spam_ip = false;     
                }
				//*/
				if ($mark_spam_ip || $mark_spam_email) {
					update_user_meta($u[$i]->ID,'ct_marked_as_spam','1',true);
                }
			}
			print 1;
		}
	}
	else
	{
		print 0;
	}

	die;
}

function ct_ajax_info_users($direct_call = false)
{
    if (!$direct_call) {
	    check_ajax_referer( 'ct_secret_nonce', 'security' );
    }

    global $wpdb;
		// All users
		$r=$wpdb->get_results("
			SELECT 
				COUNT(ID) AS cnt
			FROM $wpdb->users
		");
		$cnt = $r[0]->cnt;
		
		// Checked
		$r=$wpdb->get_results("select distinct count($wpdb->users.ID) as cnt from $wpdb->users inner join $wpdb->usermeta on $wpdb->users.ID=$wpdb->usermeta.user_id where $wpdb->usermeta.meta_key='ct_checked' or $wpdb->usermeta.meta_key='ct_hash';");
		$cnt_checked=$r[0]->cnt;
		
		//Spam
		$r=$wpdb->get_results("select distinct count($wpdb->users.ID) as cnt from $wpdb->users inner join $wpdb->usermeta on $wpdb->users.ID=$wpdb->usermeta.user_id where $wpdb->usermeta.meta_key='ct_marked_as_spam';", ARRAY_A);
		$cnt_spam1=$r[0]['cnt'];
			
	printf (__("Total users %s, checked %s, found %s spam users", 'cleantalk'), $cnt, $cnt_checked, $cnt_spam1);
    $backup_notice = '&nbsp;';
    if ($cnt_spam1 > 0) {
        $backup_notice = __("Please do backup of WordPress database before delete any accounts!", 'cleantalk');
    }
	print "<p>$backup_notice</p>";

    if (!$direct_call)
	    die();

    return null;
}

function ct_ajax_insert_users()
{
	check_ajax_referer( 'ct_secret_nonce', 'security' );
	
    global $wpdb;
	
	/* DELETION
	$users = get_users(array('search' => '*user_*', 'search_columns' => array('login', 'nicename')));
	$inserted = 0;
	foreach($users as $user)
		if(wp_delete_user($user->id))
			$inserted++;
	//*/
	
	$result = $wpdb->get_results("SELECT network FROM `".$wpdb->base_prefix."cleantalk_sfw` LIMIT 200;", ARRAY_A);
	if($result){
		$ip = array();
		foreach($result as $value){
			$ips[] = long2ip($value['network']);
		}
		unset($value);
		
		$inserted = 0;
		$use_id = 0;
		for($i=0; $i<200 ;$i++){
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
			else
				error_log(print_r($user_id, true));
			
		}
	}else{
		$inserted = '0';
	}

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
    global $wpdb;
    
	$r = $wpdb->get_results("select count(*) as cnt from $wpdb->usermeta where meta_key='ct_marked_as_spam';");	
	$count_all = $r ? $r[0]->cnt : 0;
    
	$args = array(
		'meta_key' => 'ct_marked_as_spam',
		'meta_value' => '1',
		'fields' => array('ID'),
		'number' => 10
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
	$wpdb->query("delete from $wpdb->usermeta where meta_key='ct_hash' or meta_key='ct_checked' or meta_key='ct_marked_as_spam';");
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
?>
