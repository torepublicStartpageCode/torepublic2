<?php
// Make sure no one attempts to run this script "directly"
if (!defined('FORUM'))
	exit;
	
function get_comment_weight()
{
	global $forum_user;
	if ($forum_user['karma']>0)
		$comment_weight= intval(sqrt($forum_user['karma']));
	else
		$comment_weight = 1;
	return $comment_weight;
}

function get_user_reputation($user_id)
{
	global $forum_db, $forum_user;
	$query = array(
		'SELECT'	=> 'reputation',
		'FROM'		=> 'users',
		'WHERE'	=> 'id='.mysql_escape_string($user_id).''
	);

	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$reputation = $forum_db->fetch_assoc($result);
	return $reputation['reputation'];
}

function get_reputation_comments($user_id)
{
	global $forum_db, $forum_user;
	$query = array(
		'SELECT'	=> '*',
		'FROM'		=> 'users_reputation',
		'WHERE'	=> 'user_id='.mysql_escape_string($user_id).'',
		'LIMIT' => '20'
	);
	
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	//$reputation = $forum_db->fetch_assoc($result);
	return $result;
}

function reputation_get_username($user_id)
{
	global $forum_db;
	$query = array(
		'SELECT'	=> 'username',
		'FROM'		=> 'users',
		'WHERE'	=> 'id='.mysql_escape_string($user_id).''
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$username = $forum_db->fetch_assoc($result);
	return $username['username'];
}

// Returns 'NULL' for an empty username or errors for an incorrect username
function reputation_get_user_id($username, &$errors)
{
	global $forum_db, $forum_user;

	$receiver_id = 'NULL';

	if ($username != '')
	{
		$query = array(
			'SELECT'	=> 'id',
			'FROM'		=> 'users',
			'WHERE'		=> 'username=\''.$forum_db->escape($username).'\''
		);


		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		$row = $forum_db->fetch_assoc($result);
		if (!$row)
			$errors[] = sprintf($lang_pun_pm['Non-existent username'], forum_htmlencode($username));
		else
			$receiver_id = intval($row['id']);

		if ($forum_user['id'] == $receiver_id)
			$errors[] = $lang_pun_pm['Message to yourself'];
	}

	return $receiver_id;
}

function reputation_insert_new_opinion($receiver_id, $comment_input, $comment_text)
{
	global $forum_db, $forum_user;
	//komentarz
	$timestamp0 = time();
	$query = array(
		'INSERT'	=> 'user_id, input, text, from_user_id, date',
		'INTO'		=> 'users_reputation',
		'VALUES'	=> $receiver_id.', '.$comment_input.', \''.$forum_db->escape($comment_text).'\', '.$forum_db->escape($forum_user['id']).', '.$timestamp0
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
	//zwiekszenie reputacji handlowej
	$query = array(
		'SELECT'	=> 'reputation',
		'FROM'		=>	'users',
		'WHERE'		=>	'id='.intval($receiver_id),
	);
	$result =$forum_db->query_build($query) or error(__FILE__, __LINE__);
	$reputation =$forum_db->fetch_assoc($result);
	$reputation = $reputation['reputation'];
	$reputation = $reputation + $comment_input;
	
	$query = array(
		'UPDATE'	=> 'users',
		'SET'		=>	'reputation='.intval($reputation),
		'WHERE'		=>	'id='.intval($receiver_id),
	);
	$result =$forum_db->query_build($query) or error(__FILE__, __LINE__);
	
}

function get_userlink($user_id)
{
	$username = reputation_get_username($user_id);
	$link = '<a href='.FORUM_ROOT.'/profile.php?id='.$user_id.'>'.$username.'</a>';
	return $link;
}

function get_comment_input_sign($input)
{
	if ($input>0)
		return 'Pozytywny';
	else if ($input==0)
		return 'Neutralny';
	else if ($input<0)
		return 'Negatywny';
}

function reputation_get_delete_link($id)
{
	return '<a href='.FORUM_ROOT.'reputation.php?action=delete&id='.$id.'><u> [X] </u></a>';	
}

function get_reputation_html_string($user_id)
{
	global $forum_db;
	$reputation_html = '<table>';
	$reputation_comments_array = get_reputation_comments($user_id);
	//print_r($reputation_comments_array);
	
	while ($comment =$forum_db->fetch_assoc($reputation_comments_array))
	{
		$table_row = '<tr><td width="5%">'.date('y/m/d',$comment['date']).'</td><td width="7%">'.get_comment_input_sign($comment['input']).'</td>
		<td><i>'.$comment['text'].'</i></td><td width="15%">'.get_userlink($comment['from_user_id']).'</td></tr>';
		$reputation_html=$reputation_html.''.$table_row;
	}
	$reputation_html=$reputation_html.'</table>';
	return $reputation_html;
}

function reputation_replace_bad_characters($comment)
{
	$b_c = array(";",")","*","\'","(","\"", "<",">","\/","\\");
	foreach ($b_c as &$c)
		{
			$comment = str_replace($c, ' ', $comment);
		}
	return $comment;
}

function reputation_is_username_valid($username)
	{
	$forbidden_chars = array(";",")","*","\'","(","\"");
	$max_lenght = 28;
	
	$errors_sum =0;
	//check  lenght
	if (strlen($username)>$max_lenght)
		$errors_sum=$errors_sum+1;	
	
	//check if forbidden chars occure
	foreach ($forbidden_chars as &$forbidden_character)
		{
		if (strpos($username, $forbidden_character))
			$errors_sum=$errors_sum+1;	
		}
	//result of checking
	if ($errors_sum ==0)
		return true;
	else
		return false;
	}
	
function reputation_get_last_comments($limit)
{
	global $forum_db;
	$query = array(
		'SELECT'	=> 'id, user_id, input, text, from_user_id, date',
		'FROM'		=> 'users_reputation',
		'ORDER BY'	=> 'date DESC',
		'LIMIT'		=> '0, ' . $limit
	);

	$comments = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	return $comments;
}
?>
