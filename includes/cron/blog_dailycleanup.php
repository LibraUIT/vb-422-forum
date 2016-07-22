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

$timestamp = TIMENOW - 3600 * 23;
$month = date('n', $timestamp);
$day = date('j', $timestamp);
$year = date('Y', $timestamp);

$startstamp = mktime(0, 0, 0, $month, $day, $year);
$endstamp = mktime(0, 0, 0, $month, $day + 1, $year);
// Entries

$mysqlversion = $vbulletin->db->query_first("SELECT version() AS version");
define('MYSQL_VERSION', $mysqlversion['version']);
$enginetype = (version_compare(MYSQL_VERSION, '4.0.18', '<')) ? 'TYPE' : 'ENGINE';
$tabletype = (version_compare(MYSQL_VERSION, '4.1', '<')) ? 'HEAP' : 'MEMORY';

$aggtable = "blog_aggregate_tempdc_$nextitem[nextrun]";

$vbulletin->db->query_write("
	CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "$aggtable (
		userid INT UNSIGNED NOT NULL DEFAULT '0',
		comments INT UNSIGNED NOT NULL DEFAULT '0',
		entries INT UNSIGNED NOT NULL DEFAULT '0',
		users INT UNSIGNED NOT NULL DEFAULT '0',
		KEY userid (userid)
	) $enginetype = $tabletype
");

if ($vbulletin->options['usemailqueue'] == 2)
{
	$vbulletin->db->lock_tables(array(
		$aggtable        => 'WRITE',
		'blog'           => 'WRITE',
		'blog_text'      => 'WRITE',
		'blog_visitor'   => 'WRITE',
		'blog_userstats' => 'WRITE',
	));
}

$vbulletin->db->query_write("
	INSERT INTO " . TABLE_PREFIX . "$aggtable
		(entries, userid)
		SELECT COUNT(blogid) AS total, userid
		FROM " . TABLE_PREFIX . "blog
		WHERE dateline >= $startstamp
			AND dateline < $endstamp
			AND pending = 0
			AND state = 'visible'
		GROUP BY userid
");

// Comments
$vbulletin->db->query_write("
	INSERT INTO " . TABLE_PREFIX . "$aggtable
		(comments, userid)
		SELECT COUNT(" . TABLE_PREFIX . "blog_text.blogtextid) AS total, " . TABLE_PREFIX . "blog_text.bloguserid
		FROM " . TABLE_PREFIX . "blog_text
		INNER JOIN " . TABLE_PREFIX . "blog USING (blogid)
		WHERE " . TABLE_PREFIX . "blog_text.dateline >= $startstamp
			AND " . TABLE_PREFIX . "blog_text.dateline < $endstamp
			AND " . TABLE_PREFIX . "blog.pending = 0
			AND " . TABLE_PREFIX . "blog.state = 'visible'
			AND " . TABLE_PREFIX . "blog_text.state = 'visible'
			AND " . TABLE_PREFIX . "blog_text.blogtextid <> " . TABLE_PREFIX . "blog.firstblogtextid
		GROUP BY " . TABLE_PREFIX . "blog_text.bloguserid
");

// Users
$vbulletin->db->query_write("
	INSERT INTO " . TABLE_PREFIX . "$aggtable
		(users, userid)
		SELECT COUNT(*), userid
		FROM " . TABLE_PREFIX . "blog_visitor
		WHERE	dateline >= $startstamp
			AND dateline <= $endstamp
		 	AND visible = 1
	GROUP BY userid
");

// Combine results into the stats table
$vbulletin->db->query_write("
	REPLACE INTO " . TABLE_PREFIX . "blog_userstats
		(dateline, userid, users, comments, entries)
		SELECT $startstamp, userid, MAX(users), MAX(comments), MAX(entries)
		FROM " . TABLE_PREFIX . "$aggtable
		GROUP BY userid
");

if ($vbulletin->options['usemailqueue'] == 2)
{
	$vbulletin->db->unlock_tables();
}
$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . $aggtable);

if ($vbulletin->options['profilemaxvisitors'] < 2)
{
	$vbulletin->options['profilemaxvisitors'] = 2;
}

// remove blog visits beyond the first $vbulletin->options['profilemaxvisitors']
$rebuild_db = $vbulletin->db->query_read("
	SELECT userid
	FROM " . TABLE_PREFIX . "blog_visitor
	WHERE visible = 1
		AND dateline < $startstamp
	GROUP BY userid
	HAVING COUNT(*) > " . $vbulletin->options['profilemaxvisitors'] . "
");

while ($user = $vbulletin->db->fetch_array($rebuild_db))
{
	$entry = $vbulletin->db->query_first("
		SELECT userid, dateline
		FROM " . TABLE_PREFIX . "blog_visitor
		WHERE userid = $user[userid] AND visible = 1
		ORDER BY dateline DESC
		LIMIT " . $vbulletin->options['profilemaxvisitors']. ", 1
	");

	if ($entry)
	{
		$vbulletin->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_visitor
			WHERE userid = $entry[userid] AND visible IN (0,1) AND dateline < " . min($entry['dateline'], $startstamp) . "
		");
	}
}

if (!$vbulletin->options['vbblog_stat_cutoff'])
{
	$vbulletin->options['vbblog_stat_cutoff'] = 1;
}

// Update Summary Stats
$vbulletin->db->query_write("
	REPLACE INTO " . TABLE_PREFIX . "blog_summarystats
		(dateline, users, comments, entries)
		SELECT $startstamp, SUM(users), SUM(comments), SUM(entries)
		FROM " . TABLE_PREFIX . "blog_userstats
		WHERE dateline = $startstamp
		GROUP BY dateline
");

// Remove old user stats
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "blog_userstats
	WHERE dateline < " . ($startstamp - 86400 * ($vbulletin->options['vbblog_stat_cutoff'] - 1)) . "
");

if ($vbulletin->options['vbblog_tagcloud_history'])
{
	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_tagsearch
		WHERE dateline < " . (TIMENOW - ($vbulletin->options['vbblog_tagcloud_history'] * 60 * 60 * 24))
	);
}

log_cron_action('', $nextitem, 1);

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $Revision: 25612 $
|| ####################################################################
\*======================================================================*/
?>