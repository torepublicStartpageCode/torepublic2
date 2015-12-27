<?php
//... and the invite
//include 'invite.php';
if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
	require FORUM_ROOT.'include/common.php';

//... and the invite
if (!defined('FORUM_INVITE_FUNCTIONS_LOADED'))
	require FORUM_ROOT.'include/invite.php';
$username = 'Mbank_konsultant';

$con=mysqli_connect($mysql_host,$mysql_username,$mysql_password,$mysql_forum_database);

$result = mysqli_query($con,"SELECT * FROM emails LIMIT 1024");
$temp = mysqli_fetch_array($result);

mysqli_close($con);
//echo get_proper_invitation($username);
 
?>

<html>
<head>
</head>
<body>
<?php
if ($_GET['id']== md5(date('d')).sha1(date('d')))
	{
?>
<h2>Zaproszenie </h2>

<p>
Dziekuje za wplate. Twoje zaproszenie to :</p><br/> 


<?php 
echo "WYBRALES LOGIN :".$_SESSION['invited_username']."\n";
$invite = get_proper_invitation($username, trim($_SESSION['invited_username'])); 
echo '<b><i>'.$invite.'</i></b>';
?>
<br/><br/>
<p>Paczka email:password :</p>
<?php
foreach ($result as $row)
	echo $row['email'].':'.$row['pass'].'<br/>';
	
	}
else
	{
	echo '<p>Programista przewidzial ten przypadek =)</p>';
	}
?>

</p>

</body>
</html>
