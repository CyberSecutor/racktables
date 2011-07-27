<?php
/*
*
* This file performs RackTables initialisation. Include it
* from 1st-level page. pageno, tabno and op variables are set here.
* pageno and tabno together participate in forming security context by
* generating related autotags.
*
*/

require_once 'pre-init.php';
require_once 'config.php';
require_once 'functions.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'navigation.php';
require_once 'triggers.php';
require_once 'gateways.php';
require_once 'remote.php';
require_once 'IPv6.php';
require_once 'caching.php';

// secret.php may be missing, in which case this is a special fatal error
if (! fileSearchExists ($path_to_secret_php))
	throw new RackTablesError
	(
		"Database connection parameters are read from ${path_to_secret_php} file, " .
		"which cannot be found.<br>You probably need to complete the installation " .
		"procedure by following <a href='?module=installer'>this link</a>.",
		RackTablesError::MISCONFIGURED
	);

connectDB();
transformRequestData();
loadConfigDefaults();
$tab['reports']['local'] = getConfigVar ('enterprise');

if (getConfigVar ('DB_VERSION') != CODE_VERSION)
{
	echo '<p align=justify>This Racktables installation seems to be ' .
		'just upgraded to version ' . CODE_VERSION . ', while the '.
		'database version is ' . getConfigVar ('DB_VERSION') . '.<br>No user will be ' .
		'either authenticated or shown any page until the upgrade is ' .
		"finished.<br>Follow <a href='?module=upgrade'>this link</a> and " .
		'authenticate as administrator to finish the upgrade.</p>';
	exit (1);
}

if (!mb_internal_encoding ('UTF-8'))
	throw new RackTablesError ('Failed setting multibyte string encoding to UTF-8', RackTablesError::INTERNAL);

$rackCodeCache = loadScript ('RackCodeCache');
if ($rackCodeCache == NULL or !strlen ($rackCodeCache))
{
	$rackCode = getRackCode (loadScript ('RackCode'));
	saveScript ('RackCodeCache', base64_encode (serialize ($rackCode)));
}
else
{
	$rackCode = unserialize (base64_decode ($rackCodeCache));
	if ($rackCode === FALSE) // invalid cache
	{
		saveScript ('RackCodeCache', '');
		$rackCode = getRackCode (loadScript ('RackCode'));
	}
}

// Depending on the 'result' value the 'load' carries either the
// parse tree or error message. The latter case is a bug, because
// RackCode saving function was supposed to validate its input.
if ($rackCode['result'] != 'ACK')
	throw new RackTablesError ($rackCode['load'], RackTablesError::INTERNAL);
$rackCode = $rackCode['load'];
// Only call buildPredicateTable() once and save the result, because it will remain
// constant during one execution for constraints processing.
$pTable = buildPredicateTable ($rackCode);
// Constraints parse trees aren't cached in the database, so the least to keep
// things running is to maintain application cache for them.
$parseCache = array();
$entityCache = array();
// used by getExplicitTagsOnly()
$tagRelCache = array();

$taglist = getTagList();
$tagtree = treeFromList ($taglist);
sortTree ($tagtree, 'taginfoCmp');

// Initial security context is empty. It is filled by functions authenticate() and fixContext()
$context = array (
	'user' => array(),
	'target' => array(),
	'view' => array(),
);

// This also can be modified in local.php.
$pageheaders = array
(
	100 => "<link rel='ICON' type='image/x-icon' href='?module=chrome&uri=pix/favicon.ico' />",
);
addCSS ('css/pi.css');

if (!isset ($script_mode) or $script_mode !== TRUE)
{
	// A successful call to authenticate() fills 'user' part of context.
	// It also sets remote_username and remote_displayname.
	authenticate();
	// Authentication passed.
	// Note that we don't perform autorization here, so each 1st level page
	// has to do it in its way, e.g. by calling authorize().
	session_start();
}
else
{
	// Some functions require remote_username to be set to something to act correctly,
	// even though they don't use the value itself.
	$admin_account = spotEntity ('user', 1);
	addTagChainToContext ('user', $admin_account);
	$remote_username = $admin_account['user_name'];
	unset ($admin_account);
}

alterConfigWithUserPreferences();
$pageno = '';
$tabno = '';
$op = '';
// local.php may be missing, this case requires no special treatment
// and must not generate any warnings
ob_start();
if (fileSearchExists ($path_to_local_php))
	include_once $path_to_local_php;
$tmp = ob_get_clean();
if ($tmp != '' and ! preg_match ("/^\n+$/D", $tmp))
	echo $tmp;
unset ($tmp);

prepareNavigation();
fixContext(); // fill 'view' and 'target' parts of context

?>
