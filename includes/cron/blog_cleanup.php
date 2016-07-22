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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
if (!is_object($vbulletin->db))
{
	exit;
}

// hashes are only valid for 5 minutes
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "blog_hash
	WHERE dateline < " . (TIMENOW - 300)
);

$mysqlversion = $vbulletin->db->query_first("SELECT version() AS version");
define('MYSQL_VERSION', $mysqlversion['version']);

//searches expire after one hour
$vbulletin->db->query_write("
	DELETE blog_search, blog_searchresult
	FROM " . TABLE_PREFIX . "blog_search AS blog_search
	INNER JOIN " . TABLE_PREFIX . "blog_searchresult AS blog_searchresult ON (blog_searchresult.blogsearchid = blog_search.blogsearchid)
	WHERE blog_search.dateline < " . (TIMENOW - 3600)
);

require_once(DIR . '/includes/blog_functions.php');
build_blog_stats();

log_cron_action('', $nextitem, 1);

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $Revision: 37230 $
|| ####################################################################
\*======================================================================*/
?>