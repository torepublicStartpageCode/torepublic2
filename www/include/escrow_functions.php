<?php
//error_reporting(1);
//ini_set("display_errors", 1);
//require FORUM_ROOT.'lang/'.$forum_user['language'].'/escrows.php';

define('ADDRESS_FREE',0);
define('ADDRESS_TAKEN', 1);

//jak wszystko idzie dobrze
define('ESCROW_STARTED',	0);
define('BITCOINS_RECEIVED', 1);
define('BITCOINS_RELEASED',	2);
define('ESCROW_FINISHED',	3);
//jak klient zglosi problem
define('PROBLEM_REPORTED',	4);
define('FULL_BITCOIN_RETURN',5);
define('PARTIAL_BITCOIN_RETURN',6);
define('NO_BITCOIN_RETURN',7);
//problemreason
define('NOT_RECEIVED',1);
define('FALSE_DESCRIPTION',2);
//problemclaim
define('FULL_REFUND',1);
define('PARTIAL_REFUND',2);

//payouts
define('PAYOUT_REQUESTED',0);
define('PAYOUT_REALISED',1);


function file_get_contents_via_tor($url)
{
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
	curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:9050');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch,CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
	//curl_setopt($ch, CURLOPT_HEADER, TRUE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE); 
	$response = curl_exec($ch);
	return $response;
}


function update_btc_price()
{
	global $forum_db;
	$price_in_fiat = 1;
	$price_of_one_dollar = file_get_contents_via_tor("https://blockchainbdgpzk.onion/tobtc?currency=USD&value=" . $price_in_fiat);
	$price_of_one_zloty = file_get_contents_via_tor("https://blockchainbdgpzk.onion/tobtc?currency=PLN&value=" . $price_in_fiat);
	if ($price_of_one_dollar>0)
	{
		$query = array(
			'UPDATE' 	=>  'config AS c',
			'SET'		=>	'c.conf_value='.intval(1/$price_of_one_dollar),
			'WHERE'		=>	'c.conf_name =\'btc_price_usd\''
		);
		$forum_db->query_build($query) or error(__FILE__, __LINE__);	
	}
	
	if ($price_of_one_zloty>0)
	{
		$query = array(
			'UPDATE' 	=>  'config AS c',
			'SET'		=>	'c.conf_value='.intval(1/$price_of_one_zloty),
			'WHERE'		=>	'c.conf_name =\'btc_price_pln\''
		);
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
	}
}

function market_get_topic_info($topic_id)
{
	global $forum_db;
	$query = array(
		'SELECT'	=>	'topic_id, poster',
		'FROM'		=>	'topics',
		'WHERE'		=>	'topic_id='.$topic_id
	);
	$result =$forum_db->query_build($query);
	$row = $forum_db->fetch_assoc($result);
	return $row;
}

function market_move_to_trash_old_offers()
{
	global $forum_config, $forum_db;
	$trash_forum_id = intval($forum_config['o_trash_forum_id']);

	$query = array(
		'SELECT'	=>	'a.topic_id, a.duration, t.id, t.posted, t.forum_id',
		'FROM'		=>	'auctions AS a INNER JOIN topics AS t ON a.topic_id=t.id',
		'WHERE'		=>	't.forum_id<>'.$trash_forum_id
	);
	$result =$forum_db->query_build($query);
	
	$time_now = time();
	while ($row = $forum_db->fetch_assoc($result))
	{
		if ($row['duration']+$row['posted']-$time_now <=0)
		{
			$query = array(
				'UPDATE'	=>	'topics',
				'SET'		=>	'topics.forum_id='.$trash_forum_id.', topics.sticky=0',
				'WHERE'		=>	'topics.id='.$row['id']
				);
			$result =$forum_db->query_build($query);
			
			$query = array(
				'UPDATE'	=>	'forums',
				'SET'		=>	'forums.num_topics=(forums.num_topics-1)',
				'WHERE'		=>	'forums.id='.$row['forum_id']
			);
			$result = $forum_db->query_build($query);
			
			$query = array(
				'DELETE'	=>	'auctions',
				'WHERE'		=>	'auctions.topic_id='.$row['id']
				);
			$result =$forum_db->query_build($query);	
		}
	}
}

function delete_auction($id)
{
	global $lang_escrows, $forum_user, $forum_db, $forum_url, $forum_config, $forum_flash;

	$trash_forum_id = intval($forum_config['o_trash_forum_id']);
	
	$query = array(
			'SELECT'	=>	't.id, t.forum_id, t.poster, t.subject, u.id AS user_id',
			'FROM'		=>	'topics AS t INNER JOIN users AS u ON u.username=t.poster',
			'WHERE'		=>	't.id='.$id
				);
	$result =$forum_db->query_build($query);
	$row = $forum_db->fetch_assoc($result);
	$forum_id = $row['forum_id'];
	
	if (is_numeric($forum_id))
	{
		$query = array(
				'UPDATE'	=>	'topics',
				'SET'		=>	'topics.forum_id='.$trash_forum_id.', topics.sticky=0',
				'WHERE'		=>	'topics.id='.$id
				);
		$result =$forum_db->query_build($query);
			
		$query = array(
				'UPDATE'	=>	'forums',
				'SET'		=>	'forums.num_topics=(forums.num_topics-1)',
				'WHERE'		=>	'forums.id='.$forum_id
				);
		$result = $forum_db->query_build($query);
			
		$query = array(
				'DELETE'	=>	'auctions',
				'WHERE'		=>	'auctions.topic_id='.$row['id']
				);
		$result =$forum_db->query_build($query);
		
		// Send messsage to poster
		$now = time();
		$auction_link = '[url]'.FORUM_ROOT.'viewtopic.php?id='.$row['id'].'[/url]';
		$body = sprintf($lang_escrows['Your auction was moved to trash'], $auction_link);
		
		$query = array(
			'INSERT'		=> 'sender_id, receiver_id, status, lastedited_at, read_at, subject, body',
			'INTO'			=> 'pun_pm_messages',
			'VALUES'		=> $forum_user['id'].', '.$row['user_id'].', \'sent\', '.$now.', 0, \''.$forum_db->escape($row['subject']).'\', \''.$forum_db->escape($body).'\''
			);
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	
		startescrow_clear_cache($row['user_id']);
		$forum_flash->add_info($lang_escrows['Auction deleted']);
		redirect(forum_link($forum_url['market']), $lang_escrows['Message sent']);
	}
}

// Returns 'NULL' for an empty username or errors for an incorrect username
function startescrow_get_receiver_id($username, &$errors)
{
	global $lang_escrows, $forum_db, $forum_user;

	$receiver_id = 'NULL';

	if ($username != '')
	{
		$query = array(
			'SELECT'	=> 'id',
			'FROM'		=> 'users',
			'WHERE'		=> 'username=\''.$forum_db->escape($username).'\''
		);

		($hook = get_hook('startescrows_fn_get_receiver_id_pre_query')) ? eval($hook) : null;

		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		$row = $forum_db->fetch_assoc($result);
		if (!$row)
			$errors[] = sprintf($lang_escrows['Non-existent username'], forum_htmlencode($username));
		else
			$receiver_id = intval($row['id']);

		if ($forum_user['id'] == $receiver_id)
			$errors[] = $lang_escrows['Message to yourself'];
	}

	($hook = get_hook('startescrows_fn_get_receiver_id_end')) ? eval($hook) : null;

	return $receiver_id;
}

// Erases user's ($id) cache
function startescrow_clear_cache($id)
{
	global $forum_db, $forum_user;

	$query = array(
		'UPDATE'	=> 'users',
		'SET'		=> 'pun_pm_new_messages = NULL',
		'WHERE'		=> 'id = '.$id,
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

function startescrow_send_message($body, $subject, $receiver_username, $amount, &$message_id)
{
	global $lang_escrows, $forum_user, $forum_db, $forum_url, $forum_config, $forum_flash;

	$errors = array();

	$receiver_id = startescrow_get_receiver_id($receiver_username, $errors);

	if ($receiver_id == 'NULL' && empty($errors))
		$errors[] = $lang_escrows['Empty receiver'];

	// Clean up body from POST
	$body = forum_linebreaks($body);

	if ($body == '')
		$errors[] = $lang_escrows['Empty body'];
	elseif (strlen($body) > FORUM_MAX_POSTSIZE_BYTES)
		$errors[] = sprintf($lang_escrows['Too long message'], forum_number_format(strlen($body)), forum_number_format(FORUM_MAX_POSTSIZE_BYTES));
	elseif ($forum_config['p_message_all_caps'] == '0' && utf8_strtoupper($body) == $body && !$forum_page['is_admmod'])
		$body = utf8_ucwords(utf8_strtolower($body));

	// Validate BBCode syntax
	if ($forum_config['p_message_bbcode'] == '1' || $forum_config['o_make_links'] == '1')
	{
		global $smilies;
		if (!defined('FORUM_PARSER_LOADED'))
			require FORUM_ROOT.'include/parser.php';
		$body = preparse_bbcode($body, $errors);
	}

	// Sending message to the buyer

	$btcaddress = get_free_btcaddress($errors); //book the address
	
	if (count($errors))
		return $errors;

	$now = time();

	// Send new message

	// Save to DB
	$query = array(
		'INSERT'		=> 'sender_id, receiver_id, status, lastedited_at, read_at, subject, body',
		'INTO'			=> 'pun_pm_messages',
		'VALUES'		=> $forum_user['id'].', '.$receiver_id.', \'sent\', '.$now.', 0, \''.$forum_db->escape($subject).'\', \''.$forum_db->escape($body).'\''
	);

	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$endtime = $now+$forum_config['o_empty_escrow_duration']*3600;
	$endtime = date('Y-m-d H:i:s ',$endtime);
	// Send message to the buyer
	$body = sprintf($lang_escrows['Escrow buyer message'], $endtime, $amount, $btcaddress);
	
	// Save to DB
	$query = array(
		'INSERT'		=> 'receiver_id, sender_id, status, lastedited_at, read_at, subject, body',
		'INTO'			=> 'pun_pm_messages',
		'VALUES'		=> $forum_user['id'].', '.$receiver_id.', \'sent\', '.$now.', 0, \''.$forum_db->escape($subject).'\', \''.$forum_db->escape($body).'\''
	);

	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);


	// ########### Add to escrows table
	$query =array(
		'INSERT'	=>	'time, buyerid, sellerid, amount, subject, status, recivedtime, btcaddress',
		'INTO'		=>	'escrows',
		'VALUES'	=>	$now.', '.$forum_user['id'].', '.$receiver_id.', '.$amount.', \''.$forum_db->escape($subject).'\', 0, 0, \''.$btcaddress.'\''
		);
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);


	startescrow_clear_cache($receiver_id); // Clear cached 'New messages' in the user table

	$forum_flash->add_info($lang_escrows['Escrow started']);
	redirect(forum_link($forum_url['pun_pm_inbox']), $lang_escrows['Message sent']);
}


function market_get_single_address_balance($address)
{
	$json_url = 'http://blockchain.info/rawaddr/'.$address;
	$json_data = file_get_contents($json_url);
	$addressesinfo = json_decode($json_data);
	return $addressesinfo->final_balance;	
}

function market_change_mainpage_comercial()
{
	global $forum_db;
	$now = time();
	$query = array(
		'SELECT'	=>	'*',
		'FROM'		=>	'comercials',
		'WHERE'		=>	'end>='.intval($now)
		);
	$comercial_string = "<table>";
	$result= $forum_db->query_build($query) or error(__FILE__, __LINE__);
	print_r($result);
	$i=0;
	while ($row = mysqli_fetch_assoc($result))
	{
		$comercial_string='<td>'.$comercial_string.$row['text'].'</td>';
		$i=$i+1;
	}
	if($i%3==0)
	{
		$comercial_string='<tr>'.$comercial_string.'</tr>';
	}
	$comercial_string=$comercial_string.'</table>';
	if($i>0)
	{
		$query = array(
			'UPDATE'		=> 'config AS c',
			'SET'			=> 'c.conf_value=\''.$forum_db->escape($comercial_string).'\'',
			'WHERE'			=> 'c.conf_name =\'o_maintenance_message\''
			);
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
	}
	
	echo $comercial_string;
}

function market_add_comercial($message, $duration)
{
	global $forum_db;
	
	$end = time()+$duration;
	$query = array(
		'INSERT'		=> 'text, end',
		'INTO'			=> 'comercials',
		'VALUES'		=> '\''.$forum_db->escape($message).'\' ,'.intval($end)
		);
	
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

function get_category_fids($categoryid)
{
	global $forum_db;
	$query = array(
		'SELECT'	=>	'f.id',
		'FROM'		=>	'forums AS f',
		'WHERE'		=>	'f.cat_id='.intval($categoryid)
	);
	$result =$forum_db->query_build($query);
	
	$result_array= array();
	while ($row = mysqli_fetch_assoc($result))
	{
		$result_array[]=$row['id'];
	}	
	return $result_array;
}

function market_get_category_forums($categoryid)
{
	global $forum_db;
	$query = array(
		'SELECT'	=>	'f.id , f.forum_name',
		'FROM'		=>	'forums AS f',
		'WHERE'		=>	'f.cat_id='.intval($categoryid)
	);
	$result =$forum_db->query_build($query);
	$output_str = '';
	$i =0; 
	$b =0;
	while ($row = mysqli_fetch_assoc($result))
	{
		$i++;
		$link = '<td><a href='.FORUM_ROOT.'market.php?category='.$row['id'].'>'.$row['forum_name'].'</a></td>';
		$output_str=$output_str.$link;
		$b= $i%4;
		if ($b==0)
			$output_str=$output_str.'</tr><tr>';
	}	
	if ($b>0)
	{
		while ($b>0)
		{
		$output_str=$output_str.'<td></td>';	
		$b=$b-1;
		}
	}
	return $output_str;
}

function market_get_category_sell_page($categoryid)
{
	global $forum_db;
	$query = array(
		'SELECT'	=>	'f.id , f.forum_name',
		'FROM'		=>	'forums AS f',
		'WHERE'		=>	'f.cat_id='.intval($categoryid)
	);
	$result =$forum_db->query_build($query);
	$output_str = '';
	$i=0;
	while ($row = mysqli_fetch_assoc($result))
	{
		$link = '<div style="width:33%;height:25pt;float:left;"><a href='.FORUM_ROOT.'market.php?action=sell&fid='.$row['id'].'>'.$row['forum_name'].'</a></div>';
		$output_str=$output_str.$link;
		$i++;
	}	
	if($i%3==2)
		$output_str=$output_str.'<div style="width:33%;height:25pt;float:left;"></div>';
	if($i%3==1)
		$output_str=$output_str.'<div style="width:33%;height:25pt;float:left;"></div><div style="width:33%;height:25pt;float:left;"> </div>';
	return $output_str;
}

function market_get_forum_name($forum_id)
{
	global $forum_db;
	$query = array(
		'SELECT'	=>	'f.id , f.forum_name',
		'FROM'		=>	'forums AS f',
		'WHERE'		=>	'f.id='.intval($forum_id)
	);
	$result =$forum_db->query_build($query);
	$row = $result->fetch_assoc();
	return $row['forum_name'];
}

function market_get_duration($seconds)
{
	global $lang_escrows;
	if ($seconds<60)
		return $lang_escrows['A few seconds'];
	$day = 60*60*24;
	$hour = 60*60;
	$minutes = 60;
	$result = intval($seconds/$day);
	if ($result >0)
		return $result.' '.$lang_escrows['Days'];
	$result = intval($seconds/$hour);
	if ($result >0)
		return $result.' '.$lang_escrows['Hours'];
	$result = intval($seconds/$minutes);
	if ($result >0)
		return $result.' '.$lang_escrows['Minutes'];
}

//search through topics and return the newest 30
function market_get_newest_offers($category_id, $forum_id=0, $amount = 30, $start=0, $poster_name='0', $forum_username='0'){
	global $forum_db, $lang_escrows;
	$end = $amount+ $start;

	if ($poster_name!='0')
	{
		$query = array(
			'SELECT'	=>	'topics.id, topics.subject, topics.posted, topics.forum_id, topics.poster, forums.id AS forum_id, topics.first_post_id, forums.cat_id, auctions.*, users.id AS user_id, users.username, users.reputation',
			'FROM'		=>	'topics LEFT JOIN forums ON topics.forum_id=forums.id LEFT JOIN auctions ON auctions.topic_id=topics.id LEFT JOIN users ON users.username=topics.poster',
			'WHERE'		=>	'forums.cat_id='.intval($category_id).' AND topics.poster=\''.$forum_db->escape($poster_name).' \'',
			'ORDER BY'	=>	'topics.posted, topics.sticky ASC',
			'LIMIT'		=>	intval($start).', '.intval($end)
			);	
	}
	else if ($forum_id==0)
	{
		$query = array(
			'SELECT'	=>	'topics.id, topics.subject, topics.posted, topics.forum_id, topics.poster, topics.first_post_id, forums.id AS forum_id, forums.cat_id, auctions.*, users.id AS user_id, users.username, users.reputation',
			'FROM'		=>	'topics LEFT JOIN forums ON topics.forum_id=forums.id LEFT JOIN auctions ON auctions.topic_id=topics.id LEFT JOIN users ON users.username=topics.poster',
			'WHERE'		=>	'forums.cat_id='.intval($category_id),
			'ORDER BY'	=>	'topics.posted, topics.sticky ASC',
			'LIMIT'		=>	intval($start).', '.intval($end)
			);			
	}
	else
	{
		$query = array(
			'SELECT'	=>	'topics.id, topics.subject, topics.posted, topics.forum_id, topics.poster, topics.first_post_id, forums.id AS forum_id, forums.cat_id, auctions.*, users.id AS user_id, users.username ,users.reputation',
			'FROM'		=>	'topics LEFT JOIN forums ON topics.forum_id=forums.id LEFT JOIN auctions ON auctions.topic_id=topics.id LEFT JOIN users ON users.username=topics.poster',
			'WHERE'		=>	'forums.cat_id='.intval($category_id).' AND forums.id='.intval($forum_id),
			'ORDER BY'	=>	'topics.posted, topics.sticky ASC',
			'LIMIT'		=>	intval($start).', '.intval($end)
			);	
	}
	$result =$forum_db->query_build($query);
	$output_str ='';
	$seller_options='';
	$row_counter = 0;
	$no_photo_root_url = FORUM_ROOT.'img/';
	$no_photo_url = $no_photo_root_url.'no_photo.jpg';
	while ($row = mysqli_fetch_assoc($result))
	{
		if($poster_name!='0' && $poster_name==$row['username'] && $forum_username==$row['username'])
		{
			$seller_options=' <a href="'.FORUM_ROOT.'edit.php?id='.$row['first_post_id'].'">Modify</a>, ';
			$seller_options= $seller_options.'<a href="'.FORUM_ROOT.'market.php?action=delete_auction&id='.$row['first_post_id'].'" >'.$lang_escrows['Delete auction'].'</a>';
		}		
		if (strlen($row['image_url'])==0)
			//$row['image_url']=$no_photo_url;
			if ($row['forum_id']==53)
				$row['image_url']=$no_photo_root_url.'drug.jpg';
			else if($row['forum_id']==54)
				$row['image_url']=$no_photo_root_url.'weapon.jpg';
			else if($row['forum_id']==50)
				$row['image_url']=$no_photo_root_url.'data.jpg';
			else if($row['forum_id']==51)
				$row['image_url']=$no_photo_root_url.'bank.jpg';
			else
				$row['image_url']=$no_photo_root_url.'other.jpg';

		if ($row['currency']==0)
			$currency='BTC';
		else if ($row['currency']==1)
			$currency='PLN';
		else if ($row['currency']==2)
			$currency='USD';
			
		$output_str=$output_str.'<div style="width:40%;float:left;font-size:15px;border:15px solid white;" id="auction'.$row['id'].'" >
		<div style="width:27%;float:left;" ><a href='.FORUM_ROOT.'market.php?action=viewoffer&id='.$row['id'].'><img style="width:98%;height:99%" src="'.$row['image_url'].'" style="display:block;" width="100%" height="100%"></a></div>
		<div style="width:73%;height:31pt;float:left;"><a href='.FORUM_ROOT.'market.php?action=viewoffer&id='.$row['id'].'>'.$row['subject'].'</a>   </div>
		<div style="width:50%;float:right;"><FONT SIZE=0.3>'.$lang_escrows['Seller'].' <a href="'.FORUM_ROOT.'profile.php?id='.$row['user_id'].'"><i><u>'.$row['poster'].'('.$row['reputation'].')</u></i></a></FONT></div>
		<div style="width:50%;float:right;color:red;">'.$lang_escrows['Finishes in'].' '.market_get_duration($row['duration'] - (time() - $row['posted'])).$seller_options.'</div>
		<div style="width:73%;float:right;color:gold;font-size:18px;">'.$lang_escrows['Price'].' '.round($row['price'],4).$currency.'</div>
		</div>';
		$row_counter=$row_counter +1;
		//opakowywanie w rzadek
		if ($row_counter%2==0 && $row_counter>0)
			$output_str='<div style="width:100%;float:left;">'.$output_str.'</div>';
	}
	// aukcje z forum 'Sprzedam i czasem oszukam' 
	$forum_id = 6;
	$query = array(
		'SELECT'	=>	'topics.id, topics.subject, topics.posted, topics.forum_id, topics.poster, topics.first_post_id, forums.id AS forum_id, forums.cat_id, auctions.*, users.id AS user_id, users.username ,users.reputation',
		'FROM'		=>	'topics LEFT JOIN forums ON topics.forum_id=forums.id LEFT JOIN auctions ON auctions.topic_id=topics.id LEFT JOIN users ON users.username=topics.poster',
		'WHERE'		=>	'forums.id='.intval($forum_id),
		'ORDER BY'	=>	'topics.posted DESC',
		'LIMIT'		=>	intval($start).', '.intval($end)
		);
	$result =$forum_db->query_build($query);
	$row_counter2=0;
	while ($row = mysqli_fetch_assoc($result))
	{
		$row['image_url']=$no_photo_url;
		$output_str2=$output_str2.'<div style="width:40%;float:left;font-size:15px;border:15px solid white;" id="auction'.$row['id'].'" >
		<div style="width:27%;float:left;" ><a href='.FORUM_ROOT.'viewtopic.php?id='.$row['id'].'><img style="width:98%;height:99%" src="'.$row['image_url'].'" style="display:block;" width="100%" height="100%"></a></div>
		<div style="width:73%;height:31pt;float:left;"><a href='.FORUM_ROOT.'viewtopic.php?id='.$row['id'].'>'.$row['subject'].'</a>   </div>
		<div style="width:50%;float:right;"><FONT SIZE=0.3>'.$lang_escrows['Seller'].' <a href="'.FORUM_ROOT.'profile.php?id='.$row['user_id'].'"><i><u>'.$row['poster'].'('.$row['reputation'].')</u></i></a></FONT></div>
		</div>';
		$row_counter2=$row_counter2 +1;
		$row_counter=$row_counter +1;
		//opakowywanie w rzadek
		if ($row_counter2%2==0 && $row_counter2>0)
			$output_str2='<div style="width:100%;float:left;">'.$output_str2.'</div>';
		
	}
	$output_str=$output_str.$output_str2;

	if ($row_counter==0)
			$output_str=$output_str.'<div style="width:100%;float:left;">'.$lang_escrows['No offers found'].'</div>';
	return $output_str;
}



function market_add_auction($topic_id, $price, $image_url, $main_page_promted, $duration, $currency)
{
	global $forum_db;
		$query = array(
			'INSERT'		=> 'topic_id, price, image_url, promoted, duration, currency',
			'INTO'			=> 'auctions',
			'VALUES'		=> intval($topic_id).','.floatval($price).', \''.$forum_db->escape($image_url).'\','.intval($main_page_promted).','.intval($duration).','.intval($currency)
		);
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
}

function lastchecktime()
{
	$timefilename = "lastcheck.php";
	$checktime = file_get_contents($timefilename);
	file_put_contents($timefilename, time());
	return $checktime;
}

function escrow_get_claim_reason($problemreason)
{
	if ($problemreason ==NOT_RECEIVED )
		return 'Not Received';
	else if ($problemreason ==FALSE_DESCRIPTION )
		return 'False Description';
	else
		return 'No Problem';
}
function escrow_get_action_claimed($problemclaim)
{
	if ($problemclaim ==FULL_REFUND)
		return 'Full Refund Claimed';
	else if ($problemclaim ==PARTIAL_REFUND)
		return 'Partial Refund Claimed';
	else
		return 'No Claim';
}

function get_escrow_status($int_status)
{
	if ($int_status==0)
		return 'Not paid yet';
	else if ($int_status==1)
		return 'Paid, Bitcoins pending';
	else if ($int_status==2)
		return 'Paid, Bitcoins released';
	else if ($int_status==3)
		return 'Finished';
	else if ($int_status==4)
		return 'Paid, but problem reported!';
	else if ($int_status==5)
		return 'Bitcoins returned to the buyer';
	else if ($int_status==6)
		return 'Bitcoins partialy returned to the buyer';
	else if ($int_status==7)
		return 'Problem occured, but no return to the buyer';
}

function note_problem_occured($id)
{
	global $forum_db;
	$query = array(
		'UPDATE' 	=> 'escrows AS e',
		'SET'		=>	'e.problemoccured=1',
		'WHERE'		=>	'e.index = '.intval($id)
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);	
}

function find_escrow_by_id($id)
{
	global $forum_db;
	$query = array(
		'SELECT'	=>	'e.*',
		'FROM'		=>	'escrows AS e',
		'WHERE'		=>	'e.index='.intval($id)
	);
	$result =$forum_db->query_build($query);
	$row = $forum_db->fetch_assoc($result);	
	return $row;
}

function find_escrow_status_by_id($id)
{
	global $forum_db;
	$query = array(
		'SELECT'	=>	'e.status',
		'FROM'		=>	'escrows AS e',
		'WHERE'		=>	'e.index='.intval($id)
	);
	$result =$forum_db->query_build($query);
	$row = $forum_db->fetch_assoc($result);	
	return $row['status'];
}

function set_escrow_moderatorid($id, $moderatorid)
{
	global $forum_db;
	$query = array(
		'UPDATE' 	=> 'escrows AS e',
		'SET'		=>	'e.moderatorid='.intval($moderatorid),
		'WHERE'		=>	'e.index = '.intval($id)
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);	
}

function get_forum_id_by_problemid($id)
{
	global $forum_db;
	$query = array(
		'SELECT'	=>	't.forum_id',
		'FROM'		=>	'topics AS t',
		'WHERE'		=>	't.id='.intval($id)
	);
	$result =$forum_db->query_build($query);
	$row = $forum_db->fetch_assoc($result);	
	return $row['forum_id'];
}

function find_escrow_info_by_problemid($id)
{
	global $forum_db;
	$query = array(
		'SELECT'	=>	'e.*',
		'FROM'		=>	'escrows AS e',
		'WHERE'		=>	'e.problemid='.intval($id)
	);
	$result =$forum_db->query_build($query);
	$row = $forum_db->fetch_assoc($result);	
	return $row;	
}
function get_user_info($id)
{
	global $forum_db;

	$query = array(
		'SELECT'	=> '*',
		'FROM'		=> 'users',
		'WHERE'		=> 'id='.intval($id),
	);

	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$row = $forum_db->fetch_assoc($result);
	return $row;
}

function escrow_get_username($id)
{
	global $forum_db;

	$query = array(
		'SELECT'	=> 'username',
		'FROM'		=> 'users',
		'WHERE'		=> 'id='.intval($id),
	);

	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$row = $forum_db->fetch_assoc($result);
	if ($row)
		$username = $row['username'];
	else
		$username = '';

	return $username;
}

function clear_pm_cache($id)
{
	global $forum_db, $forum_user;

	$query = array(
		'UPDATE'	=> 'users',
		'SET'		=> 'pun_pm_new_messages = NULL',
		'WHERE'		=> 'id = '.intval($id),
	);

	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	if ($forum_user['id'] == $id)
		unset($forum_user['pun_pm_new_messages']);
}

function set_escrow_problemid($id, $new_tid)
{
	global $forum_db;
	$query = array(
		'UPDATE' 	=> 'escrows AS e',
		'SET'		=>	'e.problemid='.intval($new_tid),
		'WHERE'		=>	'e.index = '.intval($id)
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);	
}

function escrow_note_problem_reason_and_claim($index,$claim_reason ,$claim_action)
{
	global $forum_db;
	$query = array(
		'UPDATE' 	=> 'escrows AS e',
		'SET'		=>	'e.problemreason='.intval($claim_reason).' , e.problemclaim='.intval($claim_action),
		'WHERE'		=>	'e.index = '.intval($index)
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

function notify_payment_received($btcaddress, $amount, $balance, $escrowinfo)
{
	global $forum_db, $forum_url, $forum_config, $forum_flash,  $lang_escrows;
	$errors=array();
	
	$sellerid = intval($escrowinfo['sellerid']);
	$buyerid =  intval($escrowinfo['buyerid']);
	$escrowdeclaredamount = $escrowinfo['amount']; 
	$escrowsubject = $escrowinfo['subject'];
	
	$buyername = escrow_get_username($buyerid);
	$sellername= escrow_get_username($sellerid);

	$buyermessage = sprintf($lang_escrows['Buyer payment received message'],
							$buyername, $amount, $escrowsubject , $sellername);
	$buyersubject = sprintf($lang_escrows['Buyer payment received subject'],
							$amount, $sellername);
	
	$sellermessage= sprintf($lang_escrows['Seller payment received message'],
							$sellername, $amount, $escrowsubject , $buyername);
							
	$sellersubject= sprintf($lang_escrows['Seller payment received subject'],
							$amount, $buyername);
	escrows_send_messages($buyerid,$sellerid,$buyersubject,$sellersubject,$buyermessage,$sellermessage);
}						

function escrow_notify_payment_send($receiverid , $amount, $address, $txhash)
{
	global $forum_db, $forum_url, $forum_config, $forum_flash,  $lang_escrows;
	$message = sprintf($lang_escrows['Payment send message'],escrow_get_username($receiverid),$amount,$address,$txhash);
	$subject = $lang_escrows['Payment send subject'];
	escrows_send_messages(0, $receiverid, '', $subject, '', $message);
}

function escrows_send_messages($buyerid,$sellerid,$buyersubject,$sellersubject,$buyermessage,$sellermessage)
{
	global $forum_db; 
	
	$now = time();
	if (count($buyermessage))
	{
		// ############ Send buyer message 
		$query = array(
			'INSERT'		=> 'sender_id, receiver_id, status, lastedited_at, read_at, subject, body',
			'INTO'			=> 'pun_pm_messages',
			'VALUES'		=> '0'.', '.intval($buyerid).', \'sent\', '.intval($now).', 0, \''.$forum_db->escape($buyersubject).'\', \''.$forum_db->escape($buyermessage).'\''
		);
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	}
		
	// ############ Send seller message
	if (count($sellermessage))
	{
		$query = array(
			'INSERT'		=> 'sender_id, receiver_id, status, lastedited_at, read_at, subject, body',
			'INTO'			=> 'pun_pm_messages',
			'VALUES'		=> '0'.', '.intval($sellerid).', \'sent\', '.intval($now).', 0, \''.$forum_db->escape($sellersubject).'\', \''.$forum_db->escape($sellermessage).'\''
		);
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	}
	clear_pm_cache($sellerid);
	clear_pm_cache($buyerid);
}

function escrow_notify_problem_occured($escrowinfo, $problem_link)
{
	global $forum_db, $forum_url, $forum_config, $forum_flash, $lang_escrows;
	$errors=array();
	
	$sellerid = $escrowinfo['sellerid'];
	$buyerid =  $escrowinfo['buyerid'];
	$escrowdeclaredamount = $escrowinfo['amount']; 
	$escrowsubject = $escrowinfo['subject'];
	
	$buyername = escrow_get_username($buyerid);
	$sellername= escrow_get_username($sellerid);

	$buyermessage = sprintf($lang_escrows['Buyer problem reported message'],
							$escrowinfo['subject'], $escrowinfo['amount'],$problem_link);
							
	$buyersubject = $lang_escrows['Buyer problem reported subject'];
	
	$sellermessage= sprintf($lang_escrows['Seller problem reported message'],
							$buyername, $escrowinfo['subject'], $escrowinfo['amount'],$problem_link);
							
	$sellersubject= $lang_escrows['Seller problem reported subject'];
							
	escrows_send_messages($buyerid,$sellerid,$buyersubject,$sellersubject,$buyermessage,$sellermessage);
}

function escrow_notify_problem_resolved($escrowinfo)
{
	global $forum_db, $forum_url, $forum_config, $forum_flash, $lang_escrows;
	$errors=array();
	
	$payoutamount = escrow_get_payout_amount($escrowinfo);
	
	$sellerid = $escrowinfo['sellerid'];
	$buyerid =  $escrowinfo['buyerid'];
	$escrowdeclaredamount = $escrowinfo['amount']; 
	$escrowsubject = $escrowinfo['subject'];
	
	$buyername = escrow_get_username($buyerid);
	$sellername= escrow_get_username($sellerid);

	$buyermessage = sprintf($lang_escrows['Buyer problem resolved message'],$escrowinfo['subject'],
					$payoutamount, escrow_get_username($escrowinfo['moderatorid']));
							
	$buyersubject = $lang_escrows['Buyer problem resolved subject'];
	
	$sellermessage= sprintf($lang_escrows['Seller problem resolved message'],$escrowinfo['subject'],
					$payoutamount, escrow_get_username($escrowinfo['moderatorid']));
							
	$sellersubject= $lang_escrows['Seller problem resolved subject'];
							
	escrows_send_messages($buyerid,$sellerid,$buyersubject,$sellersubject,$buyermessage,$sellermessage);	
}

function escrow_get_moderator_earning($escrowinfo)
{
	global $forum_config;
	$recivedamount = find_address_balance_by_address($escrowinfo['btcaddress']);
	if($escrowinfo['problemoccured']==1)
	{
		$moderatorearning = $forum_config['o_problem_commission_value']/100*$recivedamount;
		return $moderatorearning; 
	}
	else
	{
		return 0;	
	}
}

function escrow_get_payout_amount($escrowinfo)
{
	global $forum_db, $blockchainfee;
	$recivedamount = find_address_balance_by_address($escrowinfo['btcaddress']);
	
	$query = array(
		'SELECT' => 'c.conf_value',
		'FROM'	=>	'config AS c',
		'WHERE'=> 'c.conf_name=\'o_regular_commission_value\''
		);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$row = $forum_db->fetch_assoc($result);	
	$regular_commission = $row['conf_value'];
	
	
	$query = array(
		'SELECT' => 'c.conf_value',
		'FROM'	=>	'config AS c',
		'WHERE'=> 'c.conf_name=\'o_problem_commission_value\''
		);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$row = $forum_db->fetch_assoc($result);	
	$problem_commission	= $row['conf_value'];

	if ($escrowinfo['problemoccured']==1)  // jesli wystapil problem to wieksza prowizja
		$payoutamount = $recivedamount*(1.0-($regular_commission+$problem_commission)/100.0) - $blockchainfee;
	else
		$payoutamount = $recivedamount*(1.0-($regular_commission)/100.0) - $blockchainfee;
	
	return $payoutamount;
}
function escrow_get_seller_address($escrowinfo)
{
	global $forum_db;
	$query = array(
		'SELECT'	=>	'e.sellerid',
		'FROM'		=>	'escrows AS e',
		'WHERE'		=>	'e.index='.intval($escrowinfo['index'])
	);
	$result =$forum_db->query_build($query);
	$row = $forum_db->fetch_assoc($result);	
	
	$query = array(
		'SELECT'	=> 'u.btcaddress',
		'FROM'		=> 'users AS u',
		'WHERE'		=> 'u.id='.intval($row['sellerid']),
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$row = $forum_db->fetch_assoc($result);
	return $row['btcaddress'];
}

function escrow_get_payout_address_link($escrowinfo)
{
	
	if ($escrowinfo['status']==ESCROW_FINISHED or $escrowinfo['status']==NO_BITCOIN_RETURN or 
			$escrowinfo['status']==BITCOINS_RELEASED)
	{
		$dupa = get_user_info($escrowinfo['sellerid']);
		$return_string = "<a href=http://blockchain.info/address/".
			$dupa['btcaddress'].">"."(click)</a>";
			
		
	}
	else if ($escrowinfo['status']==PARTIAL_BITCOIN_RETURN)
	{
		$dupa = get_user_info($escrowinfo['sellerid']);
		$return_string = "<a href=http://blockchain.info/address/".
			$dupa['btcaddress'].">"."(click)</a>";
		$dupa = get_user_info($escrowinfo['buyerid']);
		$return_string=$return_string."  <a href=http://blockchain.info/address/".
			$dupa['btcaddress'].">"."(click)</a>";
	}
	else if ($escrowinfo['status']==FULL_BITCOIN_RETURN)
	{
		$dupa = get_user_info($escrowinfo['sellerid']);
		$return_string ="<a href=http://blockchain.info/address/".
			$dupa['btcaddress'].">"."(click)</a>";
	}
	else
	{
		return '---------------';
	}
	return $return_string;
}

function escrow_get_address_balance($address)
{
	global $forum_db;
	$query = array(
		'SELECT'	=>	'balance',
		'FROM'		=>	'btcaddresses',
		'WHERE'		=>	'btcaddress=\''.$address.'\''
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$row = $forum_db->fetch_assoc($result);	
	return $row['balance'];
}
function escrow_update_address_balance($address, $balance)
{
	global $forum_db;
	$query = array(
		'UPDATE'	=> 'btcaddresses',
		'SET'		=> 'balance = '.floatval($balance),
		'WHERE'		=> 'btcaddress = \''.$address.'\'',
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);	
}

function get_free_btcaddress(&$errors)
{
	global $forum_db;
	
	$query = array(
		'SELECT'	=> '*',
		'FROM'		=> 'btcaddresses',
		'WHERE'		=> 'status=0',
		'LIMIT'		=>'0, 1',
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$row = $forum_db->fetch_assoc($result);	
	if ($row)
	{
		//zajmij adres
		change_address_status($row['id'],ADDRESS_TAKEN);
		return $row['btcaddress'];
	}
	else
	{
		$errors[] = "Sorry,  no free addresses avalable, please try later";
	}
}

function get_bitcoin_data($json_url)
{
	try
	{
		$json_data = file_get_contents($json_url);
		//echo $json_data;
		$json_feed = json_decode($json_data);
	}
	catch (Exception $e)
	{
		echo "Data not received";
	}
    return $json_feed;
}
// nie dziala bo potrzebne jest 2 haslo a 2 hasla nie mozna trzymac na serwerze
/*
function generate_new_bitcoin_address($label)
{
	global $blockchainUserRoot , $blockchainpassword1;
    $json_url = $blockchainUserRoot.'new_address?password='.$blockchainpassword1.'&label='.$label;
	$response = get_bitcoin_data($json_url);
	return $response->address;
}
*/
function escrow_free_escrow_address($address, $balance, $secondarypassword)
{
	global $outgoingaddress , $blockchainfee;
	//$balance = escrow_get_address_balance($address);
	if ($balance > 5*$blockchainmovebetweenwalletsfee)
	{
		$tx_hash = escrow_make_bitcoin_payment($outgoingaddress, 
			amount($balance-$blockchainfee) , $address, $secondarypassword);
		if($tx_hash)
		{
			change_address_status_by_address($address, ADDRESS_FREE);
			escrow_update_address_balance($address,0);
		}
	}
	else
	{
		change_address_status_by_address($address, ADDRESS_FREE);
	}
}

function save_new_btcaddress($address, $status)
{
	global $forum_db;
	$query = array(
		'INSERT'		=> 'status, btcaddress',
		'INTO'			=> 'btcaddresses',
		'VALUES'		=> intval($status).','.$address,
		);
	$forum_db->query_build($querry) or error(__FILE__, __LINE__);
}

function change_address_status($id, $status)
{
	global $forum_db;
	$query = array(
		'UPDATE'	=> 'btcaddresses',
		'SET'		=> 'status = '.intval($status),
		'WHERE'		=> 'id = '.intval($id),
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

function change_address_status_by_address($address, $status)
{
	global $forum_db;
	$query = array(
		'UPDATE'	=> 'btcaddresses',
		'SET'		=> 'status = '.intval($status),
		'WHERE'		=> 'btcaddress = \''.$address.'\'',
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

function escrow_change_payment_status($id, $status)
{
	global $forum_db;
	$query = array(
		'UPDATE'	=>'payouts AS p',
		'SET'		=>'p.status='.intval($status),
		'WHERE'		=>'p.index ='.intval($id)
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

function escrow_payout_insert_transaction_hash($id, $txhash)
{
	global $forum_db;
	$query = array(
		'UPDATE'	=>'payouts AS p',
		'SET'		=>'p.txhash=\''.$txhash.'\'',
		'WHERE'		=>'p.index ='.intval($id)
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

function change_escrow_status($id, $status)
{
	global $forum_db;
	$query = array(
		'UPDATE' 	=> 	'escrows AS e',
		'SET'		=>	'e.status = '.intval($status),
		'WHERE'		=>	'e.index = '.intval($id),
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

function escrow_set_received_bitcoins_time($id, $recivedtime)
{
	global $forum_db; 
	$query = array(
		'UPDATE' 	=> 	'escrows AS e',
		'SET'		=>	'e.recivedtime ='.intval($recivedtime),
		'WHERE'		=>	'e.index = '.intval($id)
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

function find_escrow_by_address($btcaddress)
{
	global $forum_db;
	$query = array(
		'SELECT'	=>	'*',
		'FROM'		=>	'escrows',
		'WHERE'		=>	'btcaddress=\''.$btcaddress.'\' AND status!='.ESCROW_FINISHED.' AND status!='.NO_BITCOIN_RETURN.' AND status!='.PARTIAL_BITCOIN_RETURN.' AND status!='.FULL_BITCOIN_RETURN
	);
	$result = $forum_db->query_build($query);
	$row = $forum_db->fetch_assoc($result);	
	return $row;
}

function update_btcaddresses()
{
	global $forum_db;
	$addresses = '';
	$query = array(
		'SELECT'	=>	'btcaddress',
		'FROM'		=>	'btcaddresses',
		'WHERE'		=>	'status=1'
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($row = $result->fetch_assoc())
	{
		$addresses=$addresses.'|'.$row['btcaddress'];
	}
	
	$json_url = 'http://blockchain.info/multiaddr?active='.$addresses;
	//echo $json_url;
    $json_data = file_get_contents($json_url);
	$addressesinfo = json_decode($json_data)->addresses;
	$number_of_addresses = count($addressesinfo);
	if($number_of_addresses>0)
	{
		for( $i=0 ; $i<$number_of_addresses; $i++)
		{
			$btcaddressinfo = $addressesinfo[$i];
			#$balance = satoshi2bitcoin($btcaddressinfo->balance);
			$balance = satoshi2bitcoin($btcaddressinfo->final_balance);
			$btcaddress= $btcaddressinfo->address;
			$id =find_address_id($btcaddress);
			$old_balance = find_address_balance($id);	
		
			if ($balance > $old_balance+0.00000001)    //otrzymano pewna kwote
			{
				$escrowinfo = find_escrow_by_address($btcaddress);
				if ($escrowinfo['status']==ESCROW_STARTED) //zaznacz, ze otrzymano pierwsza wplate
				{
					$now = time();
					change_escrow_status($escrowinfo['index'], BITCOINS_RECEIVED);
					escrow_set_received_bitcoins_time($escrowinfo['index'], $now);
				}
				// wysylam wiadomosc do sprzedawcy i odbiorcy ,ze wplata zostala zaksiegowana
				$amount = $balance - $old_balance;
				notify_payment_received($btcaddress, $amount ,$balance, $escrowinfo);
				update_address_balance($id, $balance);
			}
			else if ($balance != $old_balance)
			{
				update_address_balance($id, $balance);
			}
		}
	}
}

function update_btcaddresses2()
{
	global $blockchainUserRoot, $blockchainpassword1;
    $json_url = $blockchainUserRoot.'list?password='.$blockchainpassword1;
    try
    {
		$json_data = file_get_contents($json_url);
		if ($json_data == false) {
			throw new Exception('Failed to open blockchain.info connection');
			return 1;
    }
    else
    {
		$addressesinfo = json_decode($json_data)->addresses;
		$number_of_addresses = count($addressesinfo);
		if($number_of_addresses>1)
		{
			for( $i=0 ; $i<$number_of_addresses; $i++)
			{
				$btcaddressinfo = $addressesinfo[$i];
				$balance = satoshi2bitcoin($btcaddressinfo->balance);
				$btcaddress= $btcaddressinfo->address;
				$id =find_address_id($btcaddress);
				$old_balance = find_address_balance($id);	
			
				if ($balance > $old_balance)    //otrzymano pewna kwote
				{
					$escrowinfo = find_escrow_by_address($btcaddress);
					if ($escrowinfo['status']==ESCROW_STARTED) //zaznacz, ze otrzymano pierwsza wplate
						{
						$now = time();
						change_escrow_status($escrowinfo['index'], BITCOINS_RECEIVED);
						escrow_set_received_bitcoins_time($escrowinfo['index'], $now);
						}
					// wysylam wiadomosc do sprzedawcy i odbiorcy ,ze wplata zostala zaksiegowana
					$amount = $balance - $old_balance;
					notify_payment_received($btcaddress, $amount ,$balance, $escrowinfo);
					update_address_balance($id, $balance);
				}
				else if ($balance != $old_balance)
				{
					update_address_balance($id, $balance);
				}
			}
		}
	}
	} catch (Exception $e) {
		echo 'Exception - addresses not updated.';
	} 
	
}


function escrow_update_old_active_escrows()
{
	//finsih active escrows
	global $forum_db, $forum_config;
	$now = time();
	$time_delta = $forum_config['o_escrow_duration']*60*60;
	$time_delta2 = $forum_config['o_empty_escrow_duration']*60*60;
	$query = array(
		'SELECT'	=> '*',
		'FROM'		=> 'escrows AS e',
		'WHERE'		=> 'e.status='.BITCOINS_RECEIVED
		);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($row = $result->fetch_assoc())
	{
		if ($row['time']+$time_delta < $now) // if its an old escrow
		{
			change_escrow_status($row['index'], BITCOINS_RELEASED);
		}
	}		
	//finish escrows that ware declared but not paid - free addresses
	
	$query = array(
		'SELECT'	=> '*',
		'FROM'		=> 'escrows AS e',
		'WHERE'		=> 'e.status='.ESCROW_STARTED
		);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($row = $result->fetch_assoc())
	{
		if ($row['time']+$time_delta2 < $now) // if its an old escrow
		{
			change_escrow_status($row['index'], ESCROW_FINISHED);
			change_address_status_by_address($row['btcaddress'], ADDRESS_FREE);
		}
	}
}

function find_address_id($address)
{
	global $forum_db;
	
	$query = array(
		'SELECT'	=> 'id',
		'FROM'		=> 'btcaddresses',
		'WHERE'		=> 'btcaddress=\''.$address.'\'',
		'LIMIT'		=>'0, 1'
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$row = $forum_db->fetch_assoc($result);	
	return $row['id'];
}

function update_address_balance($id, $balance)
{
global $forum_db;

	$query = array(
		'UPDATE'	=> 'btcaddresses',
		'SET'		=> 'balance = '.floatval($balance),
		'WHERE'		=> 'id = '.intval($id),
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);	
}

function find_address_balance($id)
{
	global $forum_db;
	
	$query = array(
		'SELECT'	=> 'balance',
		'FROM'		=> 'btcaddresses',
		'WHERE'		=> 'id='.intval($id),
		'LIMIT'		=>'0, 1',
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$row = $forum_db->fetch_assoc($result);	
	return $row['balance'];
}
function find_address_balance_by_address($address)
{
	global $forum_db;
	
	$query = array(
		'SELECT'	=> 'balance',
		'FROM'		=> 'btcaddresses',
		'WHERE'		=> 'btcaddress=\''.$address.'\'',
		'LIMIT'		=>'0, 1',
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$row = $forum_db->fetch_assoc($result);	
	return $row['balance'];
}

function amount($amount)
{
    //changes form bitcoin to satoshi
    return 100000000*$amount;
}
function satoshi2bitcoin($amount)
{
	//changes from satoshi to bitcoin
	return $amount/100000000;
}

function escrow_make_bitcoin_payment($addressTo , $amount, $addressFrom=0, $password)
{
	global $blockchainUserRoot;
    $json_url = $blockchainUserRoot.'payment?password='.$password.'&to='.$addressTo.'&amount='.$amount;
    if ($addressFrom!=0)
    {    $json_url = $json_url.'&from='.$addressFrom; }
    $json_feed = get_bitcoin_data($json_url);
    print_r($json_feed);
    return $json_feed->tx_hash; 
}

function escrow_note_new_payout($time, $receiverid , $amount , $btcaddress  , $escrowid)
{
	global $forum_db;
	$query = array(
		'INSERT' 	=> 'time , receiverid , amount , btcaddress , escrowid',
		'INTO'		=> 'payouts',
		'VALUES'	=> intval($time).', '.intval($receiverid).', '.floatval($amount).', \''.$btcaddress.'\', '.intval($escrowid)
		);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

function escrow_note_moderator_earnings($id , $amount)
{
	global $forum_db;
	$query = array(
		'SELECT' =>	'm.currentpayout, m.totalpayout',
		'FROM'	=>	'moderator_payouts AS m',
		'WHERE'	=>	'm.id ='.intval($id)
	);
	$result =$forum_db->query_build($query) or error(__FILE__, __LINE__);
	if (mysqli_num_rows($result)==0)
	{
		echo "dodaje do tabeli moderator_payouts";
		$query = array(
			'INSERT'	=>	'id, currentpayout , totalpayout',
			'INTO'		=>	'moderator_payouts',
			'VALUES'	=>	intval($id).', '.floatval($amount).', '.floatval($amount)
		);		
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
	}
	else
	{
		echo "aktualizuje moderator_payouts";
		$row = $forum_db->fetch_assoc($result);
		$newcurrent_payout = $row['currentpayout']+$amount;
		$totalpayout		=$row['totalpayout']+$amount;
		
		$query = array(
			'UPDATE' 	=> 'moderator_payouts AS m',
			'SET'		=>	'm.currentpayout='.floatval($newcurrent_payout).', m.totalpayout='.floatval($totalpayout),
			'WHERE'		=>	'm.id = '.intval($id)
		);
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
	}
}

function escrow_moderator_get_currentpayout($id)
{
	global $forum_db;
	$query = array(
		'SELECT' =>	'm.currentpayout',
		'FROM'	=>	'moderator_payouts AS m',
		'WHERE'	=>	'm.id ='.intval($id)
	);
	$result =$forum_db->query_build($query) or error(__FILE__, __LINE__);
	if (mysqli_num_rows($result)!=0)
	{
		$row = $forum_db->fetch_assoc($result);
		return $row['currentpayout'];
	}
	else
	{
		return 0;
	}
}
function escrow_update_moderator_currentpayout($id, $amount)
{
	global $forum_db;
	$query = array(
		'UPDATE' 	=> 'moderator_payouts AS m',
		'SET'		=>	'm.currentpayout='.floatval($amount),
		'WHERE'		=>	'm.id = '.intval($id)
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

function escrow_moderator_get_totalpayout($id)
{
	global $forum_db;
	$query = array(
		'SELECT' =>	'm.totalpayout',
		'FROM'	=>	'moderator_payouts AS m',
		'WHERE'	=>	'm.id ='.intval($id)
	);
	$result =$forum_db->query_build($query) or error(__FILE__, __LINE__);
	if (mysqli_num_rows($result)!=0)
	{
		$row = $forum_db->fetch_assoc($result);
		return $row['totalpayout'];
	}
	else
	{
		return 0;
	}
}





function is_valid_subject($subject)
{
	$maximal_len =255;

	$forbidden_chars = array(";","$","*","!","\'","\"");
	$minimal_lenght = 7;
	
	$errors_sum =0;
	//check pubkey lenght
	if (strlen($subject)<$minimal_lenght)
		$errors_sum=$errors_sum+1;	
		

	//check if forbidden chars occure
	foreach ($forbidden_chars as &$forbidden_character)
		{
		if (strpos($subject, $forbidden_character))
			$errors_sum=$errors_sum+1;	
		}
	//result of checking
	if ($errors_sum ==0)
		return true;
	else
		return false;
}

//
function encrypt_message($message , $pubkey, $email)
{
	$temp_file_url = '../cache/gpgtemp.php';
	if ($pubkey!='')
	{
		file_put_contents($pubkey, $temp_file_url);
		$result = shell_exec("gpg --trust-model always --yes --import $temp_file_url");
		file_put_contents($message,$temp_file_url);
		$result = shell_exec("gpg --trust-model always --yes -q -e -a -r ".escapeshellarg($email)." -o /dev/stdout $temp_file_url");
	}
	else
	{
		$result =$message;
	}
	return $result;
}
?>
