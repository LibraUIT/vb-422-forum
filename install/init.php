<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.2 - Nulled By VietVBB Team
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// Force PHP 5.3.0+ to take time zone information from OS
if (version_compare(phpversion(), '5.3.0', '>='))
{
	@date_default_timezone_set(date_default_timezone_get());
}

// set the current unix timestamp
define('TIMENOW', time());
define('SAPI_NAME', php_sapi_name());
define('SAFEMODE', (@ini_get('safe_mode') == 1 OR strtolower(@ini_get('safe_mode')) == 'on') ? true : false);

// try to force display_errors on
@ini_set('display_errors', true);

// define current directory
if (!defined('CWD'))
{
	define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));
}

// #############################################################################
// fetch the core classes
require_once(CWD . '/includes/class_core.php');
require_once(CWD . '/includes/functions.php');

// initialize the data registry
$vbulletin = new vB_Registry();

// parse the configuration ini file
$vbulletin->fetch_config();

if (CWD == '.')
{
	// getcwd() failed and so we need to be told the full forum path in config.php
	if (!empty($vbulletin->config['Misc']['forumpath']))
	{
		define('DIR', $vbulletin->config['Misc']['forumpath']);
	}
	else
	{
		// This can not be phrased as it appears before phrase load and phrases won't load if we move this after ..
		trigger_error('<strong>Configuration</strong>: You must insert a value for <strong>forumpath</strong> in config.php', E_USER_ERROR);
	}
}
else
{
	define('DIR', CWD);
}

// Load Phrases
$phrases = vB_Upgrade::fetch_language();

if (!defined('VB_AREA') AND !defined('THIS_SCRIPT'))
{
	echo $phrases['core']['VB_AREA_not_defined'];
	exit;
}

if (isset($_REQUEST['GLOBALS']) OR isset($_FILES['GLOBALS']))
{
	echo $phrases['core']['request_tainting_attempted'];
	exit;
}

if (!empty($vbulletin->config['Misc']['datastorepath']))
{
		define('DATASTORE', $vbulletin->config['Misc']['datastorepath']);
}
else
{
		define('DATASTORE', DIR . '/includes/datastore');
}

$dbtype = strtolower($vbulletin->config['Database']['dbtype']);

// MySQL is Deprecated in PHP 5.5+, Force MySQLi
if (version_compare(phpversion(), '5.5.0', '>='))
{
	if ($dbtype == 'mysql')
	{
		$dbtype = 'mysqli';
	}
	else if ($dbtype == 'mysql_slave')
	{
		$dbtype = 'mysqli_slave';
	}
}

//If type is missing, Force MySQLi 
$dbtype = $dbtype ? $dbtype : 'mysqli';

// Load database class
switch ($dbtype)
{
	// Load standard MySQL class
	case 'mysql':
	case 'mysql_slave':
	{
		$db = new vB_Database($vbulletin);
		break;
	}

	// Load MySQLi class
	case 'mysqli':
	case 'mysqli_slave':
	{
		$db = new vB_Database_MySQLi($vbulletin);
		break;
	}

	// Load extended, non MySQL class (Not Implemented)
	default:
	{
//		$dbclass = "vB_Database_$dbtype";
//		$db = new $dbclass($vbulletin);
		die('Fatal error: Database class not found');
	}
}

$db->appshortname = 'vBulletin (' . VB_AREA . ')';

// make $db a member of $vbulletin
$vbulletin->db =& $db;

if (!defined('SKIPDB'))
{
	// we do not want to use the slave server at all during this process
	// as latency problems may occur
	$vbulletin->config['SlaveServer']['servername'] = '';
	// make database connection
	$db->connect(
		$vbulletin->config['Database']['dbname'],
		$vbulletin->config['MasterServer']['servername'],
		$vbulletin->config['MasterServer']['port'],
		$vbulletin->config['MasterServer']['username'],
		$vbulletin->config['MasterServer']['password'],
		$vbulletin->config['MasterServer']['usepconnect'],
		$vbulletin->config['SlaveServer']['servername'],
		$vbulletin->config['SlaveServer']['port'],
		$vbulletin->config['SlaveServer']['username'],
		$vbulletin->config['SlaveServer']['password'],
		$vbulletin->config['SlaveServer']['usepconnect'],
		$vbulletin->config['Mysqli']['ini_file'],
		$vbulletin->config['Mysqli']['charset']
	);

	//30443 Right now the product doesn't work in strict mode at all.  Its silly to make people have to edit their
	//config to handle what appears to be a very common case (though the mysql docs say that no mode is the default)
	//we no longer use the force_sql_mode parameter, though if the app is fixed to handle strict mode then we
	//may wish to change the default again, in which case we should honor the force_sql_mode option.
	//added the force parameter
	//The same logic is in includes/init.php and should stay in sync.
	//if (!empty($vbulletin->config['Database']['force_sql_mode']))
	if (empty($vbulletin->config['Database']['no_force_sql_mode']))
	{
		$db->force_sql_mode('');
	}

	// #############################################################################
	// fetch options and other data from the datastore

	// grab the MySQL Version once and let every script use it.
	$mysqlversion = $db->query_first("SELECT version() AS version");
	define('MYSQL_VERSION', $mysqlversion['version']);

	if (VB_AREA == 'Upgrade')
	{
		$optionstemp = false;

		$db->hide_errors();
			$optionstemp = $db->query_first("SELECT template FROM template WHERE title = 'options' AND templatesetid = -1");
		$db->show_errors();

		// ## Found vB2 Options so use them...
		if ($optionstemp)
		{
			eval($optionstemp['template']);
			$vbulletin->options =& $vboptions;
			$vbulletin->versionnumber = $templateversion;
		}
		else
		{
			// we need our datastore table to be updated properly to function
			$db->hide_errors();
			$db->query_write("ALTER TABLE " . TABLE_PREFIX . "datastore ADD unserialize SMALLINT NOT NULL DEFAULT '2'");
			$db->show_errors();

			$datastore_class = (!empty($vbulletin->config['Datastore']['class']) AND !defined('STDIN')) ? $vbulletin->config['Datastore']['class'] : 'vB_Datastore';

			if ($datastore_class != 'vB_Datastore')
			{
				require_once(DIR . '/includes/class_datastore.php');
			}
			$vbulletin->datastore = new $datastore_class($vbulletin, $db);
			$vbulletin->datastore->fetch($specialtemplates);
		}
	}
	else if (VB_AREA == 'Install' OR VB_AREA == 'tools')
	{ // load it up but don't actually call fetch, we need the ability to overwrite fields.
		$datastore_class = (!empty($vbulletin->config['Datastore']['class']) AND !defined('STDIN')) ? $vbulletin->config['Datastore']['class'] : 'vB_Datastore';

		if ($datastore_class != 'vB_Datastore')
		{
			require_once(DIR . '/includes/class_datastore.php');
		}
		$vbulletin->datastore = new $datastore_class($vbulletin, $db);
		$vbulletin->datastore->fetch($specialtemplates);
	}
}

// setup an empty hook class in case we run some of the main vB code
require_once(DIR . '/includes/class_hook.php');
$vbulletin->pluginlist = '';

bootstrap_framework(); // load the vB Framework.

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 39181 $
|| ####################################################################
\*======================================================================*/
?>
