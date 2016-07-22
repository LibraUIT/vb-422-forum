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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
 * Report Blog Entry Class
 *
 * @package 	vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 *
 * @final
 *
 */
class vB_ReportItem_Blog_Entry extends vB_ReportItem
{
	/**
	 * @var string	"Key" for the phrase(s) used when reporting this item
	 */
	var $phrasekey = '_blog_entry';

	/**
	 * Fetches the moderators affected by this report
	 *
	 * @return null|array	The moderators affected.
	 *
	 */
	function fetch_affected_moderators()
	{
		return $this->registry->db->query_read_slave("
			SELECT DISTINCT user.email, user.languageid, user.userid, user.username
			FROM " . TABLE_PREFIX . "blog_moderator AS blog_moderator
			INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
		");
	}

	/**
	 * Sets information to be used in the form for the report
	 *
	 * @param	array	Information to be used.
	 *
	 */
	function set_forminfo(&$iteminfo)
	{
		global $vbphrase;

		$this->forminfo = array(
			'file'         => 'blog_report',
			'action'       => 'sendemail',
			'reportphrase' => $vbphrase['report_blog_entry'],
			'reporttype'   => $vbphrase['blog'],
			'description'  => $vbphrase['only_used_to_report'],
			'itemname'     => $iteminfo['blog_title'],
			'itemlink'     => fetch_seo_url('blog', $iteminfo),
		);

		$this->set_reporting_hidden_value('b', $iteminfo['blogid']);

		return $this->forminfo;
	}

	/**
	 * Sets information regarding the report
	 *
	 * @param	array	Information regarding the report
	 *
	 */
	function set_reportinfo(&$reportinfo)
	{
		$reportinfo = array_merge($reportinfo, array(
			'blogtitle'  => unhtmlspecialchars($this->iteminfo['blog_title']),
			'blogid'     => $this->iteminfo['blogid'],
			'blogtextid' => $this->iteminfo['blogtextid'],
			'entrytitle' => unhtmlspecialchars($this->iteminfo['title']),
			'pusername'  => unhtmlspecialchars($this->iteminfo['username']),
			'puserid'    => $this->iteminfo['userid'],
			'pagetext'   => $this->iteminfo['pagetext'],
		));

		$reportinfo['bloglink'] = fetch_seo_url('blog|nosession|bburl|js', $reportinfo, null, 'puserid', 'blogtitle');
		$reportinfo['entrylink'] = fetch_seo_url('entry|nosession|bburl|js', $reportinfo, null, 'blogid', 'entrytitle');
	}

	/**
	 * Updates the Item being reported with the item report info.
	 *
	 * @param	integer	ID of the item being reported
	 *
	 */
	function update_item_reportid($newthreadid)
	{

		$blogman =& datamanager_init('BlogText', $this->registry, ERRTYPE_SILENT, 'blog');
		$blogman->set_info('skip_floodcheck', true);
		$blogman->set_info('skip_charcount', true);
		$blogman->set_info('skip_build_blog_counters', true);
		$blogman->set_info('skip_build_category_counters', true);
		$blogman->set_info('parseurl', true);
		$blogman->set('reportthreadid', $newthreadid);

		// if $this->iteminfo['reportthreadid'] exists then it means then the discussion thread has been deleted/moved
		$checkrpid = ($this->iteminfo['reportthreadid'] ? $this->iteminfo['reportthreadid'] : 0);
		$blogman->condition = "blogtextid = " . $this->iteminfo['blogtextid'] . " AND reportthreadid = $checkrpid";

		// affected_rows = 0, meaning another user reported this before us (race condition)
		return $blogman->save(true, false, true);
	}

	/**
	 * Re-fetches information regarding the reported item from the database
	 *
	 */
	function refetch_iteminfo()
	{
		$rpinfo = $this->registry->db->query_first("
			SELECT reportthreadid, userid
			FROM " . TABLE_PREFIX . "blog_text AS blog_test
			INNER JOIN " . TABLE_PREFIX . "blog AS blog USING (blogid)
			WHERE blog_text.blogtextid = " . $this->iteminfo['blogtextid']
		);
		if ($rpinfo['reportthreadid'])
		{
			$this->iteminfo['reportthreadid'] = $rpinfo['reportthreadid'];
		}
	}
}

/**
 * Report Blog Comment Class
 *
 * @package 	vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 *
 * @final
 *
 */
class vB_ReportItem_Blog_Comment extends vB_ReportItem_Blog_Entry
{
	/**
	 * @var string	"Key" for the phrase(s) used when reporting this item
	 */
	var $phrasekey = '_blog_comment';

	/**
	 * Sets information regarding the report
	 *
	 * @param	array	Information regarding the report
	 *
	 */
	function set_reportinfo(&$reportinfo)
	{
		$reportinfo = array_merge($reportinfo, array(
			'blogtitle'  => unhtmlspecialchars($this->extrainfo['blog']['blog_title']),
			'blogid'     => $this->iteminfo['blogid'],
			'blogtextid' => $this->iteminfo['blogtextid'],
			'entrytitle' => unhtmlspecialchars($this->extrainfo['blog']['title']),
			'pusername'  => unhtmlspecialchars($this->iteminfo['username']),
			'puserid'    => $this->iteminfo['userid'],
			'pagetext'   => $this->iteminfo['pagetext'],
		));
		
		$reportinfo['entrylink'] = fetch_seo_url('entry|nosession|bburl|js', $reportinfo, null, 'blogid', 'entrytitle');
		$reportinfo['commentlink'] = fetch_seo_url('entry|nosession|bburl|js', $reportinfo, 
			array('bt' => $reportinfo['blogtextid']), 'blogid', 'entrytitle');
	}

	/**
	 * Sets information to be used in the form for the report
	 *
	 * @param	array	Information to be used.
	 *
	 */
	function set_forminfo(&$iteminfo)
	{
		global $vbphrase;

		$this->forminfo = array(
			'file'         => 'blog_report',
			'action'       => 'sendemail',
			'reportphrase' => $vbphrase['report_comment'],
			'reporttype'   => $vbphrase['blog_entry'],
			'description'  => $vbphrase['only_used_to_report'],
			'itemname'     => $this->extrainfo['blog']['title'],
			'itemlink'     => fetch_seo_url('entry', $this->extrainfo['blog']),
		);

		$this->set_reporting_hidden_value('bt', $iteminfo['blogtextid']);

		return $this->forminfo;
	}
}

/**
 * Report Blog Entry Class
 *
 * @package 	vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 *
 * @final
 *
 */
class vB_ReportItem_Blog_Custom_Page extends vB_ReportItem
{
	/**
	 * @var string	"Key" for the phrase(s) used when reporting this item
	 */
	var $phrasekey = '_blog_custompage';

	/**
	 * Fetches the moderators affected by this report
	 *
	 * @return null|array	The moderators affected.
	 *
	 */
	function fetch_affected_moderators()
	{
		return $this->registry->db->query_read_slave("
			SELECT DISTINCT user.email, user.languageid, user.userid, user.username
			FROM " . TABLE_PREFIX . "blog_moderator AS blog_moderator
			INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
		");
	}

	/**
	 * Sets information to be used in the form for the report
	 *
	 * @param	array	Information to be used.
	 *
	 */
	function set_forminfo(&$iteminfo)
	{
		global $vbphrase;

		$this->forminfo = array(
			'file'         => 'blog_report',
			'action'       => 'sendemail',
			'reportphrase' => $vbphrase['report_custom_page'],
			'reporttype'   => $vbphrase['blog'],
			'description'  => $vbphrase['only_used_to_report'],
			'itemname'     => $this->extrainfo['user']['blog_title'],
			'itemlink'     => fetch_seo_url('blog', $iteminfo),
		);

		$this->set_reporting_hidden_value('cp', $iteminfo['customblockid']);

		return $this->forminfo;
	}

	/**
	 * Sets information regarding the report
	 *
	 * @param	array	Information regarding the report
	 *
	 */
	function set_reportinfo(&$reportinfo)
	{
		$reportinfo = array_merge($reportinfo, array(
			'customblockid' => $this->iteminfo['customblockid'],
			'blogtitle'     => unhtmlspecialchars($this->extrainfo['user']['blog_title']),
			'pagetitle'     => unhtmlspecialchars($this->iteminfo['title']),
			'pusername'     => unhtmlspecialchars($this->extrainfo['user']['username']),
			'puserid'       => $this->extrainfo['user']['userid'],
			'pagetext'      => $this->iteminfo['pagetext'],
		));
	
		$reportinfo['bloglink'] = fetch_seo_url('blog|nosession|bburl|js', $reportinfo, null, 'puserid', 'blogtitle');
		$reportinfo['custompagelink'] = fetch_seo_url('blogcustompage|nosession|bburl|js', $reportinfo);
		
	}

	/**
	 * Updates the Item being reported with the item report info.
	 *
	 * @param	integer	ID of the item being reported
	 *
	 */
	function update_item_reportid($newthreadid)
	{

		$blockman =& datamanager_init('Blog_Custom_Block', $this->registry, ERRTYPE_SILENT);
		$blockman->set_existing($this->iteminfo);
		$blockman->set('reportthreadid', $newthreadid);

		// if $this->iteminfo['reportthreadid'] exists then it means then the discussion thread has been deleted/moved
		$checkrpid = ($this->iteminfo['reportthreadid'] ? $this->iteminfo['reportthreadid'] : 0);
		$blockman->condition = "customblockid = " . $this->iteminfo['customblockid'] . " AND reportthreadid = $checkrpid";

		// affected_rows = 0, meaning another user reported this before us (race condition)
		return $blockman->save(true, false, true);
	}

	/**
	 * Re-fetches information regarding the reported item from the database
	 *
	 */
	function refetch_iteminfo()
	{
		$rpinfo = $this->registry->db->query_first("
			SELECT bcb.reportthreadid, bu.bloguserid
			FROM " . TABLE_PREFIX . "blog_custom_block AS bcb
			INNER JOIN " . TABLE_PREFIX . "blog_user AS bu ON (bu.bloguserid = bcb.userid)
			WHERE bcb.customblockid = " . $this->iteminfo['customblockid']
		);
		if ($rpinfo['reportthreadid'])
		{
			$this->iteminfo['reportthreadid'] = $rpinfo['reportthreadid'];
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 26376 $
|| ####################################################################
\*======================================================================*/
?>
