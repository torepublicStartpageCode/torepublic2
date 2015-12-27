<?php

define('FORUM_SKIP_CSRF_CONFIRM', 1);

if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('po_start')) ? eval($hook) : null;

if ($forum_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the post.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/post.php';
require FORUM_ROOT.'lang/'.$forum_user['language'].'/escrows.php';

$fid = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
if ($tid < 1 && $fid < 1 || $tid > 0 && $fid > 0)
{
	message($lang_common['Bad request']);
}

// Creates a new topic with its first post
function escrow_publish_topic_problem($post_info)
{
	global $forum_db, $db_type, $forum_config, $lang_common;

	if ($return != null)
		return;
	
		
	// Add the topic
	$query = array(
		'INSERT'	=> 'poster, subject, posted, last_post, last_poster, forum_id, visibility',
		'INTO'		=> 'topics',
		'VALUES'	=> '\''.$forum_db->escape($post_info['poster']).'\', \''.$forum_db->escape($post_info['subject']).'\', '.$post_info['posted'].', '.$post_info['posted'].', \''.$forum_db->escape($post_info['poster']).'\', '.$post_info['forum_id'].', '.$post_info['visibility']
	);

	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	$new_tid = $forum_db->insert_id();

	// Create the post ("topic post")
	$query = array(
		'INSERT'	=> 'poster, poster_id, poster_ip, message, posted, topic_id',
		'INTO'		=> 'posts',
		'VALUES'	=> '\''.$forum_db->escape($post_info['poster']).'\', '.$post_info['poster_id'].', \''.$forum_db->escape(get_remote_address()).'\', \''.$forum_db->escape($post_info['message']).'\', '.$post_info['posted'].', '.$new_tid
	);

	$forum_db->query_build($query) or error(__FILE__, __LINE__);
	$new_pid = $forum_db->insert_id();
	
	// Update the topic with last_post_id and first_post_id
	$query = array(
		'UPDATE'	=> 'topics',
		'SET'		=> 'last_post_id='.$new_pid.', first_post_id='.$new_pid,
		'WHERE'		=> 'id='.$new_tid
	);

	$forum_db->query_build($query) or error(__FILE__, __LINE__);
	return $new_tid;
}

// EXTENSION 22.08.13 IS this an escrow problem forum?
if ($fid == $forum_config['o_problem_forum_id'] && $_SESSION['can_report_problem']==1)
{
	// find buyer and seller ids
	$escrowinfo = find_escrow_info_by_problemid($id);
	$buyerid = $_SESSION['escrowinfo']['buyerid'];
	$sellerid= $_SESSION['escrowinfo']['sellerid'];

	if ( $forum_user['id']==$buyerid || $forum_user['id']==$sellerid || $forum_user['is_admmod'])
	{
		$query = array(
		'SELECT'	=> 'f.*',
		'FROM'		=> 'forums AS f',
		'WHERE'		=> 'f.id='.$fid
	);
		
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$cur_posting = $forum_db->fetch_assoc($result);
	}
	
	if (!$cur_posting)
		message($lang_common['Bad request']);
		

// Is someone trying to post into a redirect forum?
if ($cur_posting['redirect_url'] != '')
	message($lang_common['Bad request']);

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_posting['moderators'] != '') ? unserialize($cur_posting['moderators']) : array();
$forum_page['is_admmod'] = ($forum_user['g_id'] == FORUM_ADMIN || ($forum_user['g_moderator'] == '1' && array_key_exists($forum_user['username'], $mods_array))) ? true : false;
//jezeli nic nie zostalo zaznaczone
$forum_page['claim_reason'] = (!isset($_POST['claim_reason']) || strtoupper($_POST['claim_reason']) != 'NOT_RECEIVED' && strtoupper($_POST['claim_reason']) != 'FALSE_DESCRIPTION') ? 'NOT_RECEIVED' : strtoupper($_POST['claim_reason']);
$forum_page['claim_action'] = (!isset($_POST['claim_action']) || strtoupper($_POST['claim_action']) != 'FULL_REFUND' && strtoupper($_POST['claim_action']) != 'PARTIAL_REFUND') ? 'FULL_REFUND' : strtoupper($_POST['claim_action']);

// Start with a clean slate
$errors = array();
// Did someone just hit "Submit" or "Preview"?
if (isset($_POST['form_sent']) and isset($_SESSION['escrowinfo']))
{

	// It's a new topic
	if ($fid)
	{
		$subject = forum_trim($_POST['req_subject']);

		if ($subject == '')
			$errors[] = $lang_post['No subject'];
		else if (utf8_strlen($subject) > 70)
			$errors[] = $lang_post['Too long subject'];
		else if ($forum_config['p_subject_all_caps'] == '0' && check_is_all_caps($subject) && !$forum_page['is_admmod'])
			$errors[] = $lang_post['All caps subject'];
	}

	$username = $forum_user['username'];
	$email = $forum_user['email'];

	// Clean up message from POST
	$message = forum_linebreaks(forum_trim($_POST['req_message']));

	if (strlen($message) > FORUM_MAX_POSTSIZE_BYTES)
		$errors[] = sprintf($lang_post['Too long message'], forum_number_format(strlen($message)), forum_number_format(FORUM_MAX_POSTSIZE_BYTES));
	else if ($forum_config['p_message_all_caps'] == '0' && check_is_all_caps($message) && !$forum_page['is_admmod'])
		$errors[] = $lang_post['All caps message'];

	// Validate BBCode syntax
	if ($forum_config['p_message_bbcode'] == '1' || $forum_config['o_make_links'] == '1')
	{
		if (!defined('FORUM_PARSER_LOADED'))
			require FORUM_ROOT.'include/parser.php';

		$message = preparse_bbcode($message, $errors);
	}

	if ($message == '')
		$errors[] = $lang_post['No message'];

	$now = time();

	// Did everything go according to plan?
	if (empty($errors) && !isset($_POST['preview']) and $fid)
	{
			$post_info = array(
				'poster'		=> $username,
				'poster_id'		=> $forum_user['id'],	// Always 1 for guest posts
				'subject'		=> $subject,
				'message'		=> $message,
				'hide_smilies'	=> '0',
				'posted'		=> $now,
				//'subscribe'		=> '0',
				'forum_id'		=> $fid,
				'forum_name'	=> $cur_posting['forum_name'],
				'update_user'	=> true,
				'update_unread'	=> true,
				'visibility'	=>	'4'
			);

			$new_tid = escrow_publish_topic_problem($post_info);
			
			//przypisuje escrow okreslone id problemu
			set_escrow_problemid($_SESSION['escrowinfo']['index'], $new_tid);
			
			// zmiana statusu escrow na PROBLEM_REPORTED
			change_escrow_status($_SESSION['escrowinfo']['index'], PROBLEM_REPORTED);
			
			$problem_link = FORUM_ROOT.'viewtopic.php?id='.$new_tid;
			//wyslij wiadomosc o zgloszeniu problemu 
			escrow_notify_problem_occured($_SESSION['escrowinfo'], $problem_link);
	
			//zanotuj ze w tym escrow wystapil problem
			note_problem_occured($_SESSION['escrowinfo']['index']);
	
			//zanotuj powod problemu i zadane rozwiazanie
			if ($_POST['claim_reason'] =='NOT_RECEIVED')
				$claim_reason = NOT_RECEIVED;
			else if ($_POST['claim_reason'] =='FALSE_DESCRIPTION')
				$claim_reason = FALSE_DESCRIPTION;
			if ($_POST['claim_action'] =='FULL_REFUND')
				$claim_action = FULL_REFUND;
			else if ($_POST['claim_action']=='PARTIAL_REFUND')
				$claim_action =PARTIAL_REFUND;
			escrow_note_problem_reason_and_claim($_SESSION['escrowinfo']['index'],$claim_reason ,$claim_action);
	
		redirect(FORUM_ROOT.'viewtopic.php?id='.$new_tid);

	}
}

}
	
// Setup form
$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;
$forum_page['form_action'] = ($tid ? forum_link($forum_url['new_problem_reply'], $tid) : forum_link($forum_url['new_problem'], $fid));
$forum_page['form_attributes'] = array();

$forum_page['hidden_fields'] = array(
	'form_sent'		=> '<input type="hidden" name="form_sent" value="1" />',
	'form_user'		=> '<input type="hidden" name="form_user" value="'.((!$forum_user['is_guest']) ? forum_htmlencode($forum_user['username']) : 'Guest').'" />',
	'csrf_token'	=> '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />'
);

// Setup help
$forum_page['text_options'] = array();
if ($forum_config['p_message_bbcode'] == '1')
	$forum_page['text_options']['bbcode'] = '<span'.(empty($forum_page['text_options']) ? ' class="first-item"' : '').'><a class="exthelp" href="'.forum_link($forum_url['help'], 'bbcode').'" title="'.sprintf($lang_common['Help page'], $lang_common['BBCode']).'">'.$lang_common['BBCode'].'</a></span>';
if ($forum_config['p_message_img_tag'] == '1')
	$forum_page['text_options']['img'] = '<span'.(empty($forum_page['text_options']) ? ' class="first-item"' : '').'><a class="exthelp" href="'.forum_link($forum_url['help'], 'img').'" title="'.sprintf($lang_common['Help page'], $lang_common['Images']).'">'.$lang_common['Images'].'</a></span>';
if ($forum_config['o_smilies'] == '1')
	$forum_page['text_options']['smilies'] = '<span'.(empty($forum_page['text_options']) ? ' class="first-item"' : '').'><a class="exthelp" href="'.forum_link($forum_url['help'], 'smilies').'" title="'.sprintf($lang_common['Help page'], $lang_common['Smilies']).'">'.$lang_common['Smilies'].'</a></span>';

// Setup breadcrumbs
$forum_page['crumbs'][] = array($forum_config['o_board_title'], forum_link($forum_url['index']));
$forum_page['crumbs'][] = array($cur_posting['forum_name'], forum_link($forum_url['forum'], array($cur_posting['id'], sef_friendly($cur_posting['forum_name']))));
if ($tid)
	$forum_page['crumbs'][] = array($cur_posting['subject'], forum_link($forum_url['topic'], array($tid, sef_friendly($cur_posting['subject']))));
$forum_page['crumbs'][] = $tid ? $lang_post['Post reply'] : $lang_escrows['Submit claim'];

($hook = get_hook('po_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE', 'post');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('po_main_output_start')) ? eval($hook) : null;

?>
	<div class="main-head">
		<h2 class="hn"><span><?php echo $tid ? $lang_post['Post reply'] : $lang_escrows['Submit claim'] ?></span></h2>
	</div>

	<div class="main-subhead">
		<h2 class="hn"><span><?php echo ($tid) ? $lang_post['Compose your reply'] : $lang_escrows['Compose your claim'] ?></span></h2>
	</div>
	<div id="post-form" class="main-content main-frm">
<?php

	if (!empty($forum_page['text_options']))
		echo "\t\t".'<p class="ct-options options">'.sprintf($lang_common['You may use'], implode(' ', $forum_page['text_options'])).'</p>'."\n";

	// If there were any errors, show them
	if (!empty($errors))
	{
		$forum_page['errors'] = array();
		foreach ($errors as $cur_error)
			$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

		($hook = get_hook('po_pre_post_errors')) ? eval($hook) : null;

?>
		<div class="ct-box error-box">
			<h2 class="warn hn"><?php echo $lang_post['Post errors'] ?></h2>
			<ul class="error-list">
				<?php echo implode("\n\t\t\t\t", $forum_page['errors'])."\n" ?>
			</ul>
		</div>
<?php

	}

?>
		<div id="req-msg" class="req-warn ct-box error-box">
			<p class="important"><?php echo $lang_common['Required warn'] ?></p>
		</div>
		<form id="afocus" class="frm-form frm-ctrl-" method="post" accept-charset="utf-8">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php

if ($forum_user['is_guest'])
{
	$forum_page['email_form_name'] = ($forum_config['p_force_guest_email'] == '1') ? 'req_email' : 'email';

	($hook = get_hook('po_pre_guest_info_fieldset')) ? eval($hook) : null;

?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_post['Guest post legend'] ?></strong></legend>
<?php ($hook = get_hook('po_pre_guest_username')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_post['Guest name'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_username" value="<?php if (isset($_POST['req_username'])) echo forum_htmlencode($username); ?>" size="35" maxlength="25" /></span>
					</div>
				</div>
<?php ($hook = get_hook('po_pre_guest_email')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text<?php if ($forum_config['p_force_guest_email'] == '1') echo ' required' ?>">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_post['Guest e-mail'] ?></span></label><br />
						<span class="fld-input"><input type="email" id="fld<?php echo $forum_page['fld_count'] ?>" name="<?php echo $forum_page['email_form_name'] ?>" value="<?php if (isset($_POST[$forum_page['email_form_name']])) echo forum_htmlencode($email); ?>" size="35" maxlength="80" <?php if ($forum_config['p_force_guest_email'] == '1') echo 'required' ?> /></span>
					</div>
				</div>
<?php ($hook = get_hook('po_pre_guest_info_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php

	($hook = get_hook('po_guest_info_fieldset_end')) ? eval($hook) : null;

	// Reset counters
	$forum_page['group_count'] = $forum_page['item_count'] = 0;
}

($hook = get_hook('po_pre_req_info_fieldset')) ? eval($hook) : null;

?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_common['Required information'] ?></strong></legend>
<?php

if ($fid and isset($_SESSION['problem_subject']) and is_valid_subject($_SESSION['problem_subject']) and isset($_GET['subject']))
{
	$_POST['req_subject'] = $_SESSION['problem_subject'];
	($hook = get_hook('po_pre_req_subject')) ? eval($hook) : null;
?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text required longtext">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_post['Topic subject'] ?></span></label><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" type="text" name="req_subject" readonly value="<?php if (isset($_POST['req_subject'])) echo forum_htmlencode($_SESSION['problem_subject']); ?>" size="70" maxlength="70" required /></span>
					</div>
				</div>
<?php		
}
?>
<!--- pola okreslajace co sie stalo -->
<?php ($hook = get_hook('ul_pre_sort_order_fieldset')) ? eval($hook) : null; ?>
				<fieldset class="mf-set set<?php echo ++$forum_page['item_count'] ?>">
					<legend><span><?php echo $lang_escrows['Choose report reason'] ?></span></legend>
<?php ($hook = get_hook('ul_pre_sort_order')) ? eval($hook) : null; ?>
					<div class="mf-box mf-yesno">
						<div class="mf-item">
							<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="claim_reason" value="NOT_RECEIVED" <?php if ($forum_page['claim_reason'] == 'NOT_RECEIVED') echo ' checked="checked"' ?>/></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_escrows['Did not receive'] ?></label>
						</div>
						<div class="mf-item">
							<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="claim_reason" value="FALSE_DESCRIPTION" <?php if ($forum_page['claim_reason'] == 'FALSE_DESCRIPTION') echo ' checked="checked"' ?>/></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_escrows['Doesn\'t match the description'] ?></label>
						</div>
					</div>
					
<!--- pola okreslajace co chce kupujacy -->

					<legend><span><?php echo $lang_escrows['Choose claim type'] ?></span></legend>
<?php ($hook = get_hook('ul_pre_sort_order')) ? eval($hook) : null; ?>
					<div class="mf-box mf-yesno">
						<div class="mf-item">
							<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="claim_action" value="FULL_REFUND" <?php if ($forum_page['claim_action'] == 'FULL_REFUND') echo ' checked="checked"' ?>/></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_escrows['Full refund'] ?></label>
						</div>
						<div class="mf-item">
							<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="claim_action" value="PARTIAL_REFUND" <?php if ($forum_page['claim_action'] == 'PARTIAL_REFUND') echo ' checked="checked"' ?>/></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_escrows['Partial refund'] ?></label>
						</div>
					</div>
<?php ($hook = get_hook('ul_pre_sort_order_fieldset_end')) ? eval($hook) : null; ?>
				</fieldset>

<!--tresc wiadomosci -->					
				<div class="txt-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="txt-box textarea required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_escrows['Write your explanation'] ?></span></label>
						<div class="txt-input"><span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="req_message" rows="5" cols="95" required spellcheck="true"><?php echo isset($_POST['req_message']) ? forum_htmlencode($_POST['req_message']) : (isset($forum_page['quote']) ? forum_htmlencode($forum_page['quote']) : '') ?></textarea></span></div>
					</div>
				</div>
				
<?php
$forum_page['checkboxes'] = array();
?>
			</fieldset>
<?php
($hook = get_hook('po_req_info_fieldset_end')) ? eval($hook) : null;

?>
			<div class="frm-buttons">
				<span class="submit primary"><input type="submit" name="submit_button" value="<?php echo ($tid) ? $lang_escrows['Submit claim'] : $lang_escrows['Submit claim'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

($hook = get_hook('po_end')) ? eval($hook) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
