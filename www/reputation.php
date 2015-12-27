<?php
// error_reporting(E_ALL);
// ini_set("display_errors", 1);  
 
if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('li_start')) ? eval($hook) : null;

// If we are logged in, we shouldn't be here
if ($forum_user['is_guest'])
{
	header('Location: '.forum_link($forum_url['index']));
	exit;
}

// Load the profile.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/profile.php';
require FORUM_ROOT.'include/reputation.php';

if ($forum_config['o_regs_allow'] == '0')
	message($lang_profile['No new regs']);



// Setup form
$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;
$forum_page['form_action'] = 'reputation.php'.'?action=approve';

// Setup form information
$forum_page['frm_info'] = array();
if ($forum_config['o_regs_verify'] != '0')
	$forum_page['frm_info']['email'] = '<p class="warn">'.$lang_profile['Reg e-mail info'].'</p>';

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	sprintf($lang_profile['Give your partner a comment'], $forum_config['o_board_title'])
);

// Load JS for timezone detection
$forum_loader->add_js($base_url.'/include/js/min/punbb.timezone.min.js');
$forum_loader->add_js('PUNBB.timezone.detect_on_register_form();', array('type' => 'inline'));


($hook = get_hook('rg_register_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE', 'register');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();


?>

	<div class="main-head">
		<h2 class="hn"><span><?php echo sprintf($lang_profile['Give your partner a comment'], $forum_config['o_board_title']) ?></span></h2>
	</div>
	<div class="main-content main-frm">
<?php
	if (!empty($forum_page['frm_info'])):
?>
		<div class="ct-box info-box">
			<?php echo implode("\n\t\t\t", $forum_page['frm_info'])."\n" ?>
		</div>
<?php
	endif;


?>
		<div id="req-msg" class="req-warn ct-box error-box">
			<p class="important"><?php echo $lang_common['Required warn'] ?></p>
		</div>
		<form class="frm-form frm-suggest-username" id="afocus" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>" autocomplete="off">
			<div class="hidden">
				<input type="hidden" name="form_sent" value="1" />
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token($forum_page['form_action']) ?>" />
				<input type="hidden" name="timezone" id="register_timezone" value="<?php echo forum_htmlencode($forum_config['o_default_timezone']) ?>" />
				<input type="hidden" name="dst" id="register_dst" value="<?php echo forum_htmlencode($forum_config['o_default_dst']) ?>" />
			</div>
<?php ($hook = get_hook('rg_register_pre_group')) ? eval($hook) : null; ?>
			<div class="frm-group group<?php echo ++$forum_page['group_count'] ?>">

<?php ($hook = get_hook('rg_register_pre_username')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count']; if ($forum_config['o_regs_verify'] == '0') echo ' prepend-top'; ?>">
					<div class="sf-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_profile['Username'] ?></span> <small><?php echo $lang_profile['Username help'] ?></small></label><br />
						<span class="fld-input"><input type="text" data-suggest-role="username" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_username" value="<?php echo(isset($_POST['req_username']) ? forum_htmlencode($_POST['req_username']) : '') ?>" size="35" maxlength="25" required spellcheck="false" /></span>
					</div>
				</div>
				
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_profile['Comment'] ?></span></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="comment">
							<option value="1"><?php echo $lang_profile['Positive'];?></option>
							<option value="-1"><?php echo $lang_profile['Negative'];?></option>
							<option value="0"><?php echo $lang_profile['Neutral'];?></option>
						
										</select></span>
					</div>
				</div>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_profile['Your transaction role'] ?></span></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="transactionRole">
							<option value="seller"><?php echo $lang_profile['Seller'];?></option>
							<option value="buyer"><?php echo $lang_profile['Buyer'];?></option>
										</select></span>
					</div>
				</div>
<?php ($hook = get_hook('rg_register_pre_comment')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count']; if ($forum_config['o_regs_verify'] == '0') echo ' prepend-top'; ?>">
					<div class="sf-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_profile['Add trade reputation'] ?></span> <small></small></label><br />
						<span class="fld-input"><textarea cols="60" rows="1" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_reputation"><?php echo(isset($_POST['req_reputation']) ? forum_htmlencode($_POST['req_reputation']) : $lang_profile['Transaction was succesfull.']) ?></textarea></span>
					</div>
				</div>

			</div>
<?php ($hook = get_hook('rg_register_group_end')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit primary"><input type="submit" name="approve" value="<?php echo $lang_profile['Approve'] ?>" /></span>
			</div>
		</form>
	</div>
<?php
//jesli formularz juz byl wyslany
if (isset($_POST['form_sent']) and isset($_GET['action']))
{
	if ($_GET['action']=='approve')
	{
		$errors = array();
		$comment_input=$_POST['comment'];
		if ($_POST["transactionRole"]=='buyer')
		{
			$comment_input= $_POST['comment']*get_comment_weight();
		}
		$comment_text =$_POST['req_reputation'];
		$comment_receiver=$_POST['req_username'];
		
		$comment_text = substr($comment_text, 0, 127);
		$comment_text = reputation_replace_bad_characters($comment_text);
		//if (!reputation_is_comment_valid($comment_text))
		//	$errors[] = $lang_profile['Improper comment'];
		if (!reputation_is_username_valid($comment_receiver))
			$errors[] = $lang_profile['Improper username'];
		$receiver_id = reputation_get_user_id($comment_receiver, $errors);
			// Did everything go according to plan so far?
		if (empty($errors))
		{
			// dodaj do tabeli komentarzy
			reputation_insert_new_opinion($receiver_id, $comment_input, $comment_text);
			header('Location: '.forum_link($forum_url['index']));
			exit;
		}
		else // If there were any errors, show them
		{
			$forum_page['errors'] = array();
			foreach ($errors as $cur_error)
				$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

			($hook = get_hook('rg_pre_register_errors')) ? eval($hook) : null;

?>
			<div class="ct-box error-box">
				<ul class="error-list">
					<?php echo implode("\n\t\t\t\t", $forum_page['errors'])."\n" ?>
				</ul>
			</div>
<?php

		}	
	}
}


// wczytanie 20 ostatnich komentarzy
$comments = reputation_get_last_comments(20);
?>
	<div class="main-head">
		<h2 class="hn"><span><?php echo $lang_profile['Table summary']; ?></span></h2>
	</div>
<div class="ct-group">
	<table>
		<caption><?php echo $lang_profile['Table summary']; ?></caption>
			<thead>
				<tr>
					<td width="14%"><?php echo $lang_profile['For']; ?></td>
					<td width="6%"><?php echo $lang_profile['Comment']; ?></td>
					<td ><?php echo $lang_profile['Content']; ?></td>
					<td width="14%"><?php echo $lang_profile['From']; ?></td>
					<?php if($forum_user['is_admmod']) {echo '<td width="2%">'.$lang_profile['Delete'].'</td>';} ?>
				</tr>
		</thead>
			<tbody>
					<?php
		while ($comment =$forum_db->fetch_assoc($comments))
		{
			echo '<tr><td>'.get_userlink($comment['user_id']).'</td><td>'
			.get_comment_input_sign($comment['input']).'</td><td>'
			.$comment['text'].'</td><td>'
			.get_userlink($comment['from_user_id']).'</td>';
			if ($forum_user['is_admmod']) 
				{echo '<td width="2%">'.reputation_get_delete_link($comment['id']).'</td>';}
			echo '</tr>'; 
		}
					?>
			</tbody>
	</table>
</div>
			
<?php
if (isset($_GET['action']) and isset($_GET['id'])){
	if ($forum_user['is_admmod'] && $_GET['action']=='delete')
	{
	$id = $_GET['id'];
	if (!is_numeric($id)){ return;}
	/*
	//pobieram wplyw komentarza zeby mozna bylo zmiejszyc reputacje
	$query = array(
		'SELECT'=>'input , user_id',
		'FROM'	=> 'users_reputation',
		'WHERE'	=>	'id = $id'
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$comment  =$forum_db->fetch_assoc($result);
	
	//zminejszam reputacje
	$reputation =get_user_reputation($comment['user_id']);
	
	$reputation = $reputation - $comment['input'];
	$user_id = $comment['user_id'];
	$query = array(
		'UPDATE'	=> 'users',
		'SET'		=>	'reputation='.intval($reputation),
		'WHERE'		=>	'id='.intval($user_id)
	);
	$result =$forum_db->query_build($query) or error(__FILE__, __LINE__);
	*/
	
	//usuwam komentarz
	$query = array(
		'DELETE'	=> 'users_reputation',
		'WHERE'		=> "id = $id"
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
	//echo "skasowano komentarz id $id";
	header('Location: '.FORUM_ROOT.'reputation.php');
	exit;
	}
}
($hook = get_hook('rg_end')) ? eval($hook) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
