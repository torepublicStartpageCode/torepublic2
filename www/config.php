<?php

$db_type = 'mysqli';
$db_host = 'localhost';
$db_name = 'forum';
$db_username = 'cipa';
$db_password = 'chuj';
$db_prefix = '';
$p_connect = false;

$base_url = 'http://nco5ranerted3nkt.onion';

$cookie_name = '';
$cookie_domain = '';
$cookie_path = '/';
$cookie_secure = 0;

$blockchain_root = "https://blockchain.info/"; 
$mysite_root = "http://nco5ranerted3nkt.onion";
$secret = "";
//$my_bitcoin_address = "1PkN6Gyu9SiBwEC2ZVy2n3HqfCWc2an15o";
$my_bitcoin_address = "1MheSpE61f76DjkjkY5nHkQoQNiwLc9vzR";

////////////////////////////////////////////////
$guid = '940a7896-3dcc-4fff-aae5-9d869aa5f991';

$blockchainRoot = 'https://blockchain.info/merchant/';
$blockchainUserRoot = $blockchainRoot.$guid.'/';
$outgoingaddress = '1CVKD3LDVKeX5bH8V3Q58cYHj1oJQGREoW';

/////////////////////////////////////////////////
$addressfileurl = 'data/addressinfo';
$takenaddressesfileurl = 'data/takenaddressesinfo';

$blockchainoutgoingaddresslabel = 'outgoing';
//nie zmieniac
$blockchainminimumtransaction = 0.001;
$blockchainfee = 0.0001;
$blockchainmovebetweenwalletsfee = 0.0001;
/////////////////////////////////////////////

//Database
$mysql_host = $db_host;
$mysql_username = $db_username;
$mysql_password = $db_password;
$mysql_database = 'test';
$mysql_forum_database = $db_name;


//gpg
$gpgtempfileurl = '/var/www/forum/cache/gpgtemp.php';

define('FORUM', 1);

// Enable DEBUG mode by removing // from the following line
//define('FORUM_DEBUG', 1);

// Enable show DB Queries mode by removing // from the following line
//define('FORUM_SHOW_QUERIES', 1);

// Enable forum IDNA support by removing // from the following line
//define('FORUM_ENABLE_IDNA', 1);

// Disable forum CSRF checking by removing // from the following line
//define('FORUM_DISABLE_CSRF_CONFIRM', 1);

// Disable forum hooks (extensions) by removing // from the following line
//define('FORUM_DISABLE_HOOKS', 1);

// Disable forum output buffering by removing // from the following line
//define('FORUM_DISABLE_BUFFERING', 1);

// Disable forum async JS loader by removing // from the following line
//define('FORUM_DISABLE_ASYNC_JS_LOADER', 1);

// Disable forum extensions version check by removing // from the following line
//define('FORUM_DISABLE_EXTENSIONS_VERSION_CHECK', 1);
