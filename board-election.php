<?php
/*
Plugin Name: Board Election
Description: Allow registered users to elect board members
Plugin URI: https://wordpress.org/plugins/board-election/
Author: Pascal Casier
Author URI: http://casier.eu/wp-dev/
Text Domain: board-election
Version: 1.0.1
License: GPL2
*/


// No direct access
if ( !defined( 'ABSPATH' ) ) exit;

define ('B_ELECTION_VERSION' , '1.0.1');
define ('TEXTDOMAIN' , 'board-election');

if(!defined('B_ELECTION_URL')) define('B_ELECTION_URL', plugin_dir_url( __FILE__ ));

// Activate translations
add_action('plugins_loaded', 'b_elect_load_textdomain');
function b_elect_load_textdomain() {
	load_plugin_textdomain( 'board-election', false, dirname( plugin_basename(__FILE__) ) . '/lang/' );
}

// add plugin upgrade notification
add_action('in_plugin_update_message-board-election/board-election.php', 'bElectionshowUpgradeNotification', 10, 2);
function bElectionshowUpgradeNotification($currentPluginMetadata, $newPluginMetadata){
   // check "upgrade_notice"
   if (isset($newPluginMetadata->upgrade_notice) && strlen(trim($newPluginMetadata->upgrade_notice)) > 0){
        echo '<p style="background-color: #d54e21; padding: 10px; color: #f9f9f9; margin-top: 10px"><strong>' . __('Upgrade Notice', TEXTDOMAIN) . ':</strong> ';
        echo esc_html($newPluginMetadata->upgrade_notice) . '</p>';
   }
}

// Add Settings and Donate next to the plugin on the plugins page
add_filter('plugin_action_links', 'b_election_plugin_action_links', 10, 2);
function b_election_plugin_action_links($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        // The "page" query string value must be equal to the slug
        // of the Settings admin page we defined earlier, which in
        // this case equals "myplugin-settings".
        $settings_link = '<a href="http://casier.eu/wp-dev/">' . __('Donate', TEXTDOMAIN) . '</a>';
        array_unshift($links, $settings_link);
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=board_election">' . __('Settings', TEXTDOMAIN) . '</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}

//Modify Users Admin page
add_filter('manage_users_columns', 'b_elect_add_user_id_column');
function b_elect_add_user_id_column($columns) {
	$columns['vote_allowed'] = 'Voting Allowed';
	return $columns;
}
add_action('manage_users_custom_column',  'b_elect_show_user_id_column_content', 10, 3);
function b_elect_show_user_id_column_content($value, $column_name, $user_id) {
	$vote_allowed = get_user_meta( $user_id, 'b-elect-allowed', true );
	if ( 'vote_allowed' == $column_name )
		return $vote_allowed;
	return $value;
}
add_action( 'admin_footer-users.php', 'b_elect_add_bulk_actions' );
function b_elect_add_bulk_actions() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('<option>').val('voteallow').text('Vote Allow')
                .appendTo("select[name='action'], select[name='action2']");
            $('<option>').val('votedisable').text('Vote Disable')
                .appendTo("select[name='action'], select[name='action2']");
        });
    </script>
    <?php
}
add_action( 'admin_action_voteallow', 'b_elect_bulk_voteallow' );
function b_elect_bulk_voteallow() {
	// security: make sure we start from bulk posts
	//check_admin_referer('voteallow');

	foreach ($_REQUEST['users'] as $userid) {
    		update_user_meta( $userid, 'b-elect-allowed', 'yes');
	}
	//wp_die( '<pre>' . print_r( $_REQUEST, true ) . '</pre>' ); 
}
add_action( 'admin_action_votedisable', 'b_elect_bulk_votedisable' );
function b_elect_bulk_votedisable() {
	// security: make sure we start from bulk posts
	//check_admin_referer('votedisable');

	foreach ($_REQUEST['users'] as $userid) {
    		delete_user_meta( $userid, 'b-elect-allowed');
	}
}

function board_election_page() {
	// Check if options need to be saved, so if coming from form
	if ( isset($_POST['optssave']) ) {
		if( !empty($_POST["b-election-cand-ids"]) ) {
			update_option('b-election-cand-ids', $_POST["b-election-cand-ids"]);
		} else {
			delete_option('b-election-cand-ids');
		}
		
		update_option('b-election-who-can-vote', $_POST["b-election-who-can-vote"]);
		update_option('b-election-support-email', $_POST["b-election-support-email"]);

		if( !empty($_POST["b-election-max-votes"]) ) {
			update_option('b-election-max-votes', $_POST["b-election-max-votes"]);
		} else {
			update_option('b-election-max-votes', '1');
		}

		if( !empty($_POST["b-election-end-time-tick"]) ) {
			update_option('b-election-end-time-tick', 'activate');
		} else {
			delete_option('b-election-end-time-tick');
		}

		if( !empty($_POST["b-election-show-wpml"]) ) {
			update_option('b-election-show-wpml', 'activate');
		} else {
			delete_option('b-election-show-wpml');
		}
		
		$date_value = $_POST["end_year"] . '-' . $_POST["end_month"] . '-' . $_POST["end_day"] . ' ' . $_POST["end_hour"] . ':' . $_POST["end_min"] . ':00';
		update_option('b-election-end-time', strtotime($date_value));
		
		// JUST FOR THE TEST NOW
		global $current_user;
		$user_id = $current_user->ID;
		delete_user_meta( $user_id, 'b-election-ids');
		delete_user_meta( $user_id, 'b-election-names');
	}

	$b_election_support_email = get_option('b-election-support-email', false);
	if (!$b_election_support_email) { $b_election_support_email = get_option( 'admin_email', 'No email' ); }
	$b_election_show_wpml = get_option('b-election-show-wpml', false);
	$b_election_who_can_vote = get_option('b-election-who-can-vote', false);
	$b_election_cand_ids = get_option('b-election-cand-ids', false);
	$b_election_max_votes = get_option('b-election-max-votes', false);
	if (!$b_election_max_votes) { $b_election_max_votes = '1'; }
	$b_election_end_time_tick = get_option('b-election-end-time-tick', false);
	$b_election_end_time = get_option('b-election-end-time', false);
	if (!$b_election_end_time) { $b_election_end_time = strtotime("+1 week"); }
	$endtime = array();
	$endtime["day"] = date('d', $b_election_end_time);
	$endtime["month"] = date('n', $b_election_end_time);
	$endtime["year"] = date('Y', $b_election_end_time);
	$endtime["hour"] = date('H', $b_election_end_time);
	$endtime["min"] = date('i', $b_election_end_time);

	echo '<h1>' . __('Board Election', TEXTDOMAIN) . '</h1>';
		echo '<table border="0"><tr><td>';
		echo '<form action="" method="post">';

		echo '<p><label>' . __('Candidate IDs, separated with a comma', TEXTDOMAIN) . ' : </label>';
		echo '<input type="text" name="b-election-cand-ids" id="b-election-cand-ids" value="' . $b_election_cand_ids . '" size="100" /><br>';
		echo '<label>&nbsp;&nbsp;&nbsp;(Enter the media IDs like: 12560,17852,11456,15845)</label></p>';

		if (!$b_election_who_can_vote) { $b_election_who_can_vote = 'All'; }
		echo '<p><label>Who is allowed to vote : </label>';
		echo '<select name="b-election-who-can-vote">';
		echo '<option value="All" ';
			if ($b_election_who_can_vote == 'All') { echo 'selected'; }
			echo '>All registered users</option>';
		echo '<option value="List" ';
			if ($b_election_who_can_vote == 'List') { echo 'selected'; }
			echo '>Only subset of users</option>';
		echo '</select></p>';

		echo '<p><label>Maximum number of votes per user : </label>';
		echo '<input type="text" name="b-election-max-votes" id="b-election-max-votes" value="' . $b_election_max_votes . '" size="3" /></p>';

		echo '<p><label>Email address for support questions : </label>';
		echo '<input type="text" name="b-election-support-email" id="b-election-support-email" value="' . $b_election_support_email . '" size="50" /></p>';

		echo '<p><input type="checkbox" name="b-election-end-time-tick" id="b-election-end-time-tick" value="b-election-end-time-tick" ';
		if ($b_election_end_time_tick) { echo 'checked'; }
		echo '><label for="b-election-end-time-tick">Block voting on </label>';
			echo '<select name="end_day">';
			for ($i = 1; $i <= 31; $i++) {
				if ($i < 10) {
					$d = '0' . $i;
				} else {
					$d = $i;
				}
				if ($d == $endtime["day"]) {
					$thisone = 'selected';
				} else {
					$thisone = '';
				}
				echo '<option value="' . $i . '" '. $thisone . '>' . $d . '</option>';
			}
			echo '</select>';
			
			echo '<select name="end_month">';
			for ($i = 1; $i <= 12; $i++) {
				if ($i == $endtime["month"]) {
					$thisone = 'selected';
				} else {
					$thisone = '';
				}
				$t = '22-' . $i . '-2000 00:00:00';
				echo '<option value="' . $i . '" ' . $thisone . '>' . strftime('%B', strtotime($t)) . '</option>';
			}
			echo '</select>';
			
			echo '<select name="end_year">';
			for ($i = 2015; $i <= 2030; $i++) {
				if ($i == $endtime["year"]) {
					$thisone = 'selected';
				} else {
					$thisone = '';
				}
				echo '<option value="' . $i . '" ' . $thisone . '>' . $i . '</option>';
			}
			echo '</select>';

		echo '&nbsp;&nbsp;<label>at </label>';
			echo '<select name="end_hour">';
			for ($i = 0; $i <= 23; $i++) {
				if ($i < 10) {
					$d = '0' . $i;
				} else {
					$d = $i;
				}
				if ($d == $endtime["hour"]) {
					$thisone = 'selected';
				} else {
					$thisone = '';
				}
				echo '<option value="' . $i . '" ' . $thisone . '>' . $d . '</option>';
			}
			echo '</select>';
			
			echo '<select name="end_min">';
			for ($i = 0; $i <= 59; $i += 5) {
				if ($i < 10) {
					$d = '0' . $i;
				} else {
					$d = $i;
				}
				if ($d == $endtime["min"]) {
					$thisone = 'selected';
				} else {
					$thisone = '';
				}
				echo '<option value="' . $i . '" ' . $thisone . '>' . $d . '</option>';
			}
			echo '</select>';
			
		echo '</p>';


		if ( function_exists('icl_object_id') ) {
			echo '<p><input type="checkbox" name="b-election-show-wpml" id="b-election-show-wpmlk" value="b-election-show-wpml" ';
			if ($b_election_show_wpml) { echo 'checked'; }
			echo '><label for="b-election-show-wpml">Show WPML language selector.</label><p>';
		}

		echo '<input type="submit" name="optssave" value="Save settings" />';
		echo '  WARNING: Just for the test now, saving the settings removes your OWN votes, so you can test again.';
		echo '</form>';
	
		echo '<p>&nbsp;</p>';
		echo '<h3>Usage</h3>';
		echo '<p><b>Create a page and enter the [board_election] shortcode.</b></p>';
		echo '<p>Final version should have a "select" button from the Media Gallery directly, for now enter the media library IDs like: 12560,17852,11456,15845<br>';
		echo '&nbsp;&nbsp;Image title = Candidate Name; Caption = Candidate competence field; Description = Candidate resume; Picture should be square (or slightly wider)</p>';
		echo '<p>Permissions are set by using the bulk options in "Users > All Users" where you can allow or disable voting, only works using the TOP box for now</p>';


		echo '</td><td style="text-align: left;vertical-align: top;padding: 35px;">';
		echo '<table style="border: 1px solid green;">';
		echo '<tr><td style="vertical-align:top;text-align:center;padding:15px;">Is this plugin helpful ?<br><a href="http://casier.eu/wp-dev/"><img src="https://www.paypalobjects.com/webstatic/en_US/btn/btn_donate_cc_147x47.png" height="40"></a></td></tr>';
		echo '<tr><td style="vertical-align:top;text-align:center;padding:15px;">Just 1 or 2 EUR/USD for a coffee<br>is very much appreciated!</td></tr>';
		echo '<tr><td nowrap style="vertical-align:top;text-align:center;padding:15px;">Do you use bbPress?<br>Consider also <a href="http://casier.eu/wp-dev" target="_blank">these great plugins</a>.</td></tr>';
		echo '</table>';
			
		echo '</td></tr></table>';

	echo '<br>----------------<br><br>';
	$users_that_voted = get_users(array('meta_key' => 'b-election-names',));
	echo '<h2>REPORT 1: Users that voted online (' . count($users_that_voted) . ' in total)</h2>';
	echo '<table border="0">';
	echo '<tr><th style="padding:8px;">Name</th><th style="padding:8px;">email</th><th style="padding:8px;">username</th></tr>';
	foreach ($users_that_voted as $item) {
		echo '<tr><td style="padding:8px;">' . $item->display_name . '</td><td style="padding:8px;">' . $item->user_email . '</td><td style="padding:8px;">' . $item->user_nicename . '</td></tr>';
	}
	echo '</table>';
	echo '<br>----------------<br><br>';
	echo '<h2>REPORT 2: Votes received per candidate</h2>';
	// Fill the array
	$AllCandVotes = array();
	foreach ($users_that_voted as $item) {
		$aUserVotes = explode('*' , get_user_meta( $item->ID, 'b-election-names', true ) );
		foreach ($aUserVotes as $s) {
			if ($s) {
				$AllCandVotes[$s] += 1;
			}
		}
	}
	// Sort
	arsort($AllCandVotes);
	// Now print it
	echo '<table border="0">';
	echo '<tr><th style="padding:8px;">Name</th><th style="padding:8px;">votes</th></tr>';
	foreach ($AllCandVotes as $s=>$v) {
		echo '<tr><td style="padding:8px;">' . $s . '</td><td style="padding:8px;">' . $v . '</td></tr>';
	}
	echo '</table>';
	echo '<br>';
	echo 'Just for reference: (list is in random order)<br>';
	shuffle($users_that_voted);
	foreach ($users_that_voted as $item) {
		echo '- ' . get_user_meta( $item->ID, 'b-election-names', true ) . '<br>';
	}

}

function b_election_add_admin_menu() {
	$confHook = add_options_page('Board Election', 'Board Election', 'activate_plugins', 'board_election', 'board_election_page');
}
add_action('admin_menu', 'b_election_add_admin_menu');

function b_election_add_alllist_button() {
		echo '<form action="" method="post" style="display: inline;">';
		echo '<input type="submit" name="ViewAllList" value="  ' . __('List of Candidates', TEXTDOMAIN) . '  " style="padding:5px 15px; background:#ccc; border:0 none; cursor:pointer; -webkit-border-radius: 5px; border-radius: 5px; font-size:18px;" />';
		echo '</form>';
}


function board_election_cookie() {
	setcookie('b-elect-cand-ids', '*');
	setcookie('b-elect-cand-names', '*');
	setcookie('b-elect-votes', 0);
}
add_action( 'init', 'board_election_cookie');

add_shortcode( 'board_election', 'shortcode_b_election' );
function shortcode_b_election() {
	
	wp_enqueue_script( 'jquery-coookies', B_ELECTION_URL . 'jquery.cookies.js', array( 'jquery' ) );
	
	$b_election_support_email = get_option('b-election-support-email', false);
	if (!$b_election_support_email) { $b_election_support_email = get_option( 'admin_email', 'No email' ); }
	if ( function_exists('icl_object_id') ) {
		$b_election_show_wpml = get_option('b-election-show-wpml', false);
		if ($b_election_show_wpml) {
			do_action('icl_language_selector'); echo '<br>';
		}
	}

	if (!is_user_logged_in() ) {
		echo  __('Sorry, you need to be logged-in to be able to vote.', TEXTDOMAIN) . '<br><br>';
		echo '<p>' . __('Please', TEXTDOMAIN) . ' <a href="' . wp_login_url( get_permalink() ) .'" title="Login">' . __('login', TEXTDOMAIN) . '</a> ' . __('to continue voting.', TEXTDOMAIN) . '</p>';
		echo '<p>' . __('If you did not register on this website, then please do so', TEXTDOMAIN) . ' <a href="' . wp_registration_url() .'" title="Register">' . __('here', TEXTDOMAIN) . '</a> (' . __('please remember to validate your registration by clicking on the link you will receive by email.', TEXTDOMAIN) . '</p>';
		echo '<p>' . __('Troubles ? Just drop an email to ', TEXTDOMAIN) . '<a href="mailto:' . $b_election_support_email . '">' . $b_election_support_email . '</a> .</p>';
		return;
	}
	
	$b_election_end_time_tick = get_option('b-election-end-time-tick', false);
	if ($b_election_end_time_tick) {
		$b_election_end_time_tick = get_option('b-election-end-time', false);
		if ($b_election_end_time_tick <= current_time( 'timestamp', 1 ) ) {
			// Time is up !
			echo __('Sorry, you can no longer vote because the deadline has passed.', TEXTDOMAIN) . '<br><br>';
			echo __('Deadline : ', TEXTDOMAIN) . date( 'Y-m-d H:i:s', $b_election_end_time_tick ) . '<br>';
			return;
		}
	}

	if ( isset($_POST['ViewAllList']) ) {
		$b_election_cand_ids = get_option('b-election-cand-ids', false);
		$candidates = explode(',' , $b_election_cand_ids);

		echo '<form action="" method="post">';
		echo '<input type="submit" name="Empty" value="  ' . __('Back', TEXTDOMAIN) . '  " style="padding:5px 15px; background:#ccc; border:0 none; cursor:pointer; -webkit-border-radius: 5px; border-radius: 5px; font-size:18px;" />';
		echo '</form><br>';

		echo '<table border="0">';
		foreach ($candidates as $candidate) {
			$cpost = get_post($candidate);
			echo '<tr style="border:7px solid silver;"><td style="width:170px;margin:20px;padding:5px;vertical-align:top;text-align:center;">';
			echo '<img style="border-style: none;box-shadow: none;" src="' . $cpost->guid . '" width="150" /><br>';
			echo '<b>' . $cpost->post_title . '</b></td>';
			echo '<td style="margin:20px;padding:5px;vertical-align:top;">' . $cpost->post_excerpt . '<br>' . $cpost->post_content . '</td>';
			echo '</tr>';
		}
		echo '</table>';

		echo '<form action="" method="post">';
		echo '<input type="submit" name="Empty" value="  ' . __('Back', TEXTDOMAIN) . '  " style="padding:5px 15px; background:#ccc; border:0 none; cursor:pointer; -webkit-border-radius: 5px; border-radius: 5px; font-size:18px;" />';
		echo '</form>';
		
		return;
	}

	global $current_user;
	$user_id = $current_user->ID;
	$user_votes_ids = get_user_meta( $user_id, 'b-election-ids', true );
	$user_votes_names = get_user_meta( $user_id, 'b-election-names', true );
	if ($user_votes_ids && $user_votes_names) {
		echo __('Welcome back.', TEXTDOMAIN) . '<br><br>';
		echo __('You have already voted. Your candidates are:', TEXTDOMAIN) . ' <b>' . $user_votes_names . '</b>';
		echo '<br><br>';
		b_election_add_alllist_button();
		return;
	}

	$b_election_who_can_vote = get_option('b-election-who-can-vote', false);
	if ($b_election_who_can_vote == 'List') {
		$user_perm = get_user_meta( $user_id, 'b-elect-allowed', true );
		if ($user_perm == 'yes') {
			// ok, user is allowed to vote
		} else {
			echo __('Hi, it seems you have not been given the right to vote.', TEXTDOMAIN) . '<br><br>';
			echo __('If you think this is an error, please send an email to ', TEXTDOMAIN) . '<a href="mailto:' . $b_election_support_email . '">' . $b_election_support_email . '</a> .';
			echo '<br><br>';
			return;
		}
	}

	
	if ( isset($_POST['submitvotes']) ) {
		if(!isset($_COOKIE['b-elect-cand-ids'])) {
			echo __('Something went wrong. Please close your browser, come back to this page and try again. If problems persist, contact the webmaster.', TEXTDOMAIN);
		} else {
			if(!isset($_COOKIE['b-elect-cand-names'])) {
				echo __('Something went wrong. Please close your browser, come back to this page and try again. If problems persist, contact the webmaster.', TEXTDOMAIN);
			} else {
				update_user_meta( $user_id, 'b-election-ids', $_COOKIE['b-elect-cand-ids'] );
				update_user_meta( $user_id, 'b-election-names', $_COOKIE['b-elect-cand-names'] );
				echo __('Thank you for voting.', TEXTDOMAIN) . '<br><br>';
				echo __('The following votes have been registered for you:', TEXTDOMAIN) . ' <b>' . $_COOKIE['b-elect-cand-names'] . '</b>';
				unset($_COOKIE['b-elect-cand-names']);
				unset($_COOKIE['b-elect-cand-ids']);
				echo '<br><br>';
				b_election_add_alllist_button();
			}
		}
	} else {
		$b_election_cand_ids = get_option('b-election-cand-ids', false);
		$b_election_max_votes = get_option('b-election-max-votes', false);
		if (!$b_election_max_votes) { $b_election_max_votes = '1'; }
		$candidates = explode(',' , $b_election_cand_ids);
		
		if ($b_election_max_votes == 1) {
			$sel_cand = __('Select your 1 candidate', TEXTDOMAIN) . ' ';
		} else {
			$sel_cand = __('Select up to', TEXTDOMAIN) . ' ' . $b_election_max_votes .' ' . __('candidates', TEXTDOMAIN) . ' ';
		}

		echo '<div>' . $sel_cand . __('from the list below. When you have made your choice, click the "Submit my votes" button.', TEXTDOMAIN) . '<br>';
		echo __('If during the selection you click on the wrong candidate, just click again to remove the vote.', TEXTDOMAIN) . '<br>';
		echo __('Once you click the "Submit my votes" button, your choice is stored and can no longer be changed.', TEXTDOMAIN) . '</div><br>';
		
		echo '<div class="current-votes" style="border-style: dotted;border-width:3px;padding: 5px;">' . __('0 votes so far...', TEXTDOMAIN) . '</div>';
		
		echo '<br><div><form action="" method="post" style="display: inline;">';
		echo '<input type="submit" name="submitvotes" value="  ' . __('Submit my votes', TEXTDOMAIN) . '  " style="padding:5px 15px; background:#ccc; border:0 none; cursor:pointer; -webkit-border-radius: 5px; border-radius: 5px; font-size:18px;" disabled=disabled />';
		echo '</form>';
		
		echo '&nbsp;&nbsp;&nbsp;&nbsp;';
		b_election_add_alllist_button();
		echo '</div>';
		
		foreach ($candidates as $candidate) {
			$cpost = get_post($candidate);
			echo '<div style="width:220px;height:270px;float:left;margin:20px;padding:5px;border:7px solid silver" onclick="clicked_vote(\'' . $cpost->ID . '\',\'' . $cpost->post_title . '\');" id="candidate-'. $cpost->ID .'">';
			echo '<img style="border-style: none;box-shadow: none;display: block;margin-left: auto;margin-right: auto;" src="' . $cpost->guid . '" width="150" alt="' . $cpost->post_content . '" title="' . $cpost->post_content . '" /><br>';
			echo '<b><center>' . $cpost->post_title . '</center></b><br><center>' . $cpost->post_excerpt . '</center><br>';
			echo '</div>';
		}
		
	
		?>
			<script type="text/javascript">
			function clicked_vote(cand_id,cand_name) {
				var cookieValue= jQuery.cookie('b-elect-cand-ids');
				var cookieNames= jQuery.cookie('b-elect-cand-names');
				var cookieVotes= parseInt(jQuery.cookie('b-elect-votes'),10);
				searchstr = "*" + cand_id + "*";
				if (cookieValue.indexOf(searchstr) >= 0) {
					var cookieValue = cookieValue.replace(cand_id + "*", "");
					var cookieNames = cookieNames.replace(cand_name + "*", "");
					document.cookie = "b-elect-cand-ids=" + cookieValue;
					document.cookie = "b-elect-cand-names=" + cookieNames;
					cookieVotes -= 1;
					document.cookie = "b-elect-votes=" + cookieVotes;
					jQuery("#candidate-" + cand_id).css("border", "7px solid silver");
				} else {
					if (cookieVotes == <?php echo $b_election_max_votes; ?> ) {
					} else {
						cookieValue += cand_id + "*";
						cookieNames += cand_name + "*";
						document.cookie = "b-elect-cand-ids=" + cookieValue;
						document.cookie = "b-elect-cand-names=" + cookieNames;
						cookieVotes += 1;
						document.cookie = "b-elect-votes=" + cookieVotes;
						jQuery("#candidate-" + cand_id).css("border", "7px solid red");
					}
				}
				jQuery("div.current-votes").text(cookieVotes + " <?php echo __('vote(s):', TEXTDOMAIN); ?>  " + cookieNames);
				if (cookieVotes == 0 ) {
					jQuery("input[type=submit]").prop("disabled", true);
				} else {
					jQuery("input[type=submit]").prop("disabled", false);
				}	
			}
			</script>
	
		<?php
	}
}

?>
