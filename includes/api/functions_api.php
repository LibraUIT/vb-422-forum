<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 4.2.2 - Nulled By VietVBB Team
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

function cleanAPIName($name)
{
	return preg_replace('/[^a-z0-9_.]/', '', $name);
}

// #############################################################################
/**
 * Load API method definitions
 *
 * @param string $scriptname vBulletin core script name (e.g. forum = /forum.php)
 * @param string $do Action name (e.g. edit)
 * @param int $version API version
 * @param bool $updatedo When the function is called within another method file, whether to update $do so that API will load another action.
 * @return string Final vBulletin core script name to load.
 */
function loadAPI($scriptname, $do = '', $version = 0, $updatedo = false)
{
	static $internalscriptname;
	global $VB_API_WHITELIST, $VB_API_WHITELIST_COMMON, $VB_API_ROUTE_SEGMENT_WHITELIST;
	global $VB_API_REQUESTS;

	if (!$version)
	{
		$version = $VB_API_REQUESTS['api_version'];
	}

	$scriptname = cleanAPIName($scriptname);
	// Setup new API
	$internalscriptname = $scriptname;
	if ($updatedo)
	{
		$_REQUEST['do'] = $_GET['do'] = $_POST['do'] = $do;
	}

	$do = cleanAPIName($do);
	$version = intval($version);
	$access = false;

	// If a v6 or greater file exists, just load it as it is a rollup of previous versions
	for ($i = $version; $i >= 6; $i--)
	{
		$api_filename = CWD_API . '/' . $i . '/' . $scriptname . (($do AND !VB_API_CMS)?'_' . $do:'') . '.php';
		if (file_exists($api_filename))
		{
			$access = true;
			require_once($api_filename);
			break;
		}
	}

	// Available files to load must be v5 or lower, load them all
	if (!$access)
	{
		for ($i = 1; $i <= $version; $i++)
		{
			$api_filename = CWD_API . '/' . $i . '/' . $scriptname . (($do AND !VB_API_CMS)?'_' . $do:'') . '.php';
			if (file_exists($api_filename))
			{
				$access = true;
				require_once($api_filename);
			}
		}
	}

	// Still don't have the api file
	if (!$access)
	{
		if (!headers_sent())
		{
			header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
		}
		die();
	}

	return $internalscriptname;
}

// #############################################################################
/**
 * Load common whitelist definitions
 *
 * @param int $version API version
 * @return array common whitelist
 */
function loadCommonWhiteList($version = 0)
{
	global $VB_API_WHITELIST_COMMON, $VB_API_REQUESTS;
	//error_log("--- path = " . CWD_API,3,"/var/www/html/facebook/error/errors.txt");
	if (!$version)
	{
		$version = $VB_API_REQUESTS['api_version'];
	}

	// Why scan for these files when we know what files are there and this is easy to maintain?
	// To add a v8 whitelist, make this array:
	// $whitelistfiles = array(8,7,6,5);
	// Descending Order!
	$whitelistfiles = array(7,6,5);

	if ($version < 5)
	{
		// for versions < 5 always load commonwhitelist_1.php
		require_once(CWD_API . '/commonwhitelist_1.php');
		if ($version >= 2)
		{
			// for versions < 5 and >= 2, always load commonwhitelist_2.php
			require_once(CWD_API . '/commonwhitelist_2.php');
		}
	}
	else	// load newest file that is <= to our $version, these version contain all changes from previous versions
	{
		foreach ($whitelistfiles AS $_version)
		{
			if ($_version <= $version)
			{
				require_once(CWD_API . "/commonwhitelist_{$_version}.php");
				break;
			}
		}
	}

	return $VB_API_WHITELIST_COMMON;
}


// #############################################################################
/**
 * Print API error in JSON.
 *
 * @param string $errorid Unique (to existed phrases names) error ID of the error
 * @param string $errormessage Human friendly error message.
 * @return void
 */
function print_apierror($errorid, $errormessage = '')
{
	echo json_encode(array('response' => array(
			'errormessage' => array(
				$errorid, $errormessage
			)
		)
	));

	exit;
}


// #############################################################################
/**
 *  Build message_plain
 *
 * @global <type> $VB_API_REQUESTS
 * @param <type> $message String to be built
 * @return <type> plain-lized message
 */
function build_message_plain($message)
{
	global $VB_API_REQUESTS;

	$newmessage = strip_bbcode($message, false, false, true, false, true);

	if ($VB_API_REQUESTS['api_version'] > 1)
	{
		$regex = '#\[(quote)(?>[^\]]*?)\](.*)(\[/\1\])#siU';
		$newmessage = preg_replace($regex, "<< $2 >>", $newmessage);
	}

	return $newmessage;
}

function processApiTextFormat(&$source)
{
	if (!$_REQUEST['apitextformat'])
	{
		return;
	}

	foreach ($source AS $k => $v)
	{
		switch ($_REQUEST['apitextformat'])
		{
			case '1': // plain
				if ($v == 'message' OR $v == 'message_bbcode')
				{
					unset($source[$k]);
				}
				break;
			case '2': // html
				if ($v == 'message_plain' OR $v == 'message_bbcode')
				{
					unset($source[$k]);
				}
				break;
			case '3': // bbcode
				if ($v == 'message' OR $v == 'message_plain')
				{
					unset($source[$k]);
				}
				break;
			case '4': // plain & html
				if ($v == 'message_bbcode')
				{
					unset($source[$k]);
				}
				break;
			case '5': // bbcode & html
				if ($v == 'message_plain')
				{
					unset($source[$k]);
				}
				break;
			case '6': // bbcode & plain
				if ($v == 'message')
				{
					unset($source[$k]);
				}
				break;
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 34655 $
|| ####################################################################
\*======================================================================*/
