<?php
// Make modx variables global
global $database_type;
global $database_server;
global $database_user;
global $database_password;
global $database_connection_charset;
global $database_connection_method;
global $dbase;
global $table_prefix;
global $base_url;
global $base_path;
global $modx;
global $site_sessionname;

/**
 * Initialize MODx Document Parsing
 * -----------------------------
 */

include_once (dirname(__FILE__) . "/assets/cache/siteManager.php");

// get start time
$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$tstart = $mtime;

// Do not use the protect.inc.php of MODx here! If the request comes through index-exface.php, exface handles sanitization of request parameters etc.
// Doing so via exface and the protect.inc of MODx will result in conflicts: e.g. MODx would add $sanitize_seed to every
// request parameter, that includes MODx brackets - in particular this kills any nested JSON objects passed via request parameter
// becaus every "}}" gets replaced by something like "}sanitize_seed_4f3pvmy0ct2cw80sgk4ckcw00}".

// harden it
// require_once(dirname(__FILE__).'/'.MGR_DIR.'/includes/protect.inc.php');

// set some settings, and address some IE issues
@ini_set('url_rewriter.tags', '');
@ini_set('session.use_trans_sid', 0);
@ini_set('session.use_only_cookies', 1);
session_cache_limiter('');
header('P3P: CP="NOI NID ADMa OUR IND UNI COM NAV"'); // header for weird cookie stuff. Blame IE.
header('Cache-Control: private, must-revalidate');
ob_start();
error_reporting(E_ALL & ~ E_NOTICE);

/**
 * Filename: index.php
 * Function: This file loads and executes the parser.
 * *
 */

define("IN_ETOMITE_PARSER", "true"); // provides compatibility with etomite 0.6 and maybe later versions
define("IN_PARSER_MODE", "true");
define("IN_MANAGER_MODE", "false");

if (! defined('MODX_API_MODE')) {
    define('MODX_API_MODE', false);
}

// initialize the variables prior to grabbing the config file
$database_type = '';
$database_server = '';
$database_user = '';
$database_password = '';
$dbase = '';
$table_prefix = '';
$base_url = '';
$base_path = '';

// overwrite the base url
// This is neccessary because index-exface.php is called from the exface subfolder. When MODx tries to determine the base path
// and url it uses the $_SERVER variable to get script information using the following code (in the config.inc.php). Copying
// the code here allows us to remove the exface subfolder before the config is actually loaded - see the line right after the if().
if (empty($base_path) || empty($base_url) || $_REQUEST['base_path'] || $_REQUEST['base_url']) {
    $sapi = 'undefined';
    if (! strstr($_SERVER['PHP_SELF'], $_SERVER['SCRIPT_NAME']) && ($sapi = @ php_sapi_name()) == 'cgi') {
        $script_name = $_SERVER['PHP_SELF'];
    } else {
        $script_name = $_SERVER['SCRIPT_NAME'];
    }
    $a = explode("/" . MGR_DIR, str_replace("\\", "/", dirname($script_name)));
    if (count($a) > 1)
        array_pop($a);
    $url = implode(MGR_DIR, $a);
    reset($a);
    $a = explode(MGR_DIR, str_replace("\\", "/", dirname(__FILE__)));
    if (count($a) > 1)
        array_pop($a);
    $pth = implode(MGR_DIR, $a);
    unset($a);
    $base_url = $url . (substr($url, - 1) != "/" ? "/" : "");
    $base_path = $pth . (substr($pth, - 1) != "/" && substr($pth, - 1) != "\\" ? "/" : "");
}
$base_url = str_replace('/exface/', '/', $base_url);

if (!defined('MODX_BASE_PATH')) {
    define('MODX_BASE_PATH', $base_path);
}

if (!defined('MODX_BASE_URL')) {
    define('MODX_BASE_URL', $base_url);
}

if (!defined('MODX_SITE_URL')) {
    define('MODX_SITE_URL', $base_url);
}

// get the required includes
if ($database_user == "") {
    $rt = @include_once (dirname(__FILE__) . '/' . MGR_DIR . '/includes/config.inc.php');
    // Be sure config.inc.php is there and that it contains some important values
    if (! $rt || ! $database_type || ! $database_server || ! $database_user || ! $dbase) {
        throw new RuntimeException('Evolution CMS is not currently installed or the configuration file cannot be found.');
    }
}

// start session
startCMSSession();

// initiate a new document parser
include_once (MODX_MANAGER_PATH . '/includes/document.parser.class.inc.php');
$modx = new DocumentParser();
$modx->getSettings(); // load the settings here because we are not going to execute the parser
$etomite = &$modx; // for backward compatibility
                   
// set some parser options
$modx->minParserPasses = 1; // min number of parser recursive loops or passes
$modx->maxParserPasses = 10; // max number of parser recursive loops or passes
$modx->dumpSQL = false;
$modx->dumpSnippets = false; // feed the parser the execution start time
$modx->tstart = $tstart;

// Debugging mode:
$modx->stopOnNotice = false;

// Don't show PHP errors to the public
if (! isset($_SESSION['mgrValidated']) || ! $_SESSION['mgrValidated']) {
    @ini_set("display_errors", "0");
}
?>