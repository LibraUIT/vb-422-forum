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

/**
*	This file is a bucket in which functions common to both the
* install and the upgrade can be located.
*/

if (!defined('VB_AREA') AND !defined('THIS_SCRIPT'))
{
	echo 'VB_AREA or THIS_SCRIPT must be defined to continue';
	exit;
}

// #####################################################################

function get_engine($db, $allow_memory)
{
	$memory = $innodb = false;
	$engines = $db->query('SHOW ENGINES');

	while ($row = $db->fetch_array($engines))
	{
		if ($allow_memory 
			AND strtoupper($row['Engine']) == 'MEMORY' 
			AND strtoupper($row['Support']) == 'YES')
		{
			$memory = true;
		}
		
		if (strtoupper($row['Engine']) == 'INNODB' 
			AND (strtoupper($row['Support']) == 'YES' 
			OR strtoupper($row['Support']) == 'DEFAULT'))
		{
			$innodb = true;
		}
	}

	if ($memory) 
	{ // Use Memory if possible, and allowed
		return 'MEMORY';
	}
	else if ($innodb)
	{ // Otherise try Innodb
		return 'InnoDB';
	}
	return 'MyISAM'; // Otherwise default to MyISAM.
}

// Choose Engine for Session Tables.
function get_session_engine($db)
{
	return get_engine($db, true);
}

// Determines which mysql engine to use for high concurrency tables
// Will use InnoDB if its available, otherwise MyISAM
function get_high_concurrency_table_engine($db)
{
	if (defined('SKIPDB'))
	{
		return 'MyISAM';
	}

	return get_engine($db, false);
}

function should_install_suite()
{
	$suite_products = array('vbblog', 'vbcms');

	foreach ($suite_products as $productid)
	{
		if (!file_exists(DIR . "/includes/xml/product-$productid.xml"))
		{
			return false;
		}
	}

	return true;
}

function print_admin_stop_exception($e)
{
		$args = $e->getParams();
		$message = fetch_phrase($args[0], 'error', '', false);

		if (sizeof($args) > 1)
		{
			$args[0] = $message;
			$message = call_user_func_array('construct_phrase', $args);
		}

		echo "<p>$message</p>\n";
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 29116 $
|| ####################################################################
\*======================================================================*/
