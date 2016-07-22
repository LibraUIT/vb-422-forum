<?php if (!defined('VB_ENTRY')) die('Access denied.');

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
 * @package vBulletin
 * @subpackage Search
 * @author Ed Brown, vBulletin Development Team
 * @version $Id: content.php 63206 2012-06-01 18:13:27Z freddie $
 * @since $Date: 2012-06-01 11:13:27 -0700 (Fri, 01 Jun 2012) $
 * @copyright vBulletin Solutions Inc.
 */

require_once (DIR . '/vb/search/result.php');
include_once DIR . '/packages/vbcms/item/content/article.php';
require_once (DIR . '/vb/search/indexcontroller/null.php');
/**
 * Result Implementation for CMS Article
 *
 * @see vB_Search_Result
 * @package vBulletin
 * @subpackage Search
 */
class vBCms_Search_Result_Article extends vB_Search_Result
{

	/** record node id   **/
	private $itemid;

	/** database record  **/
	private $record;

	/**
	 * factory method to create a result object
	 *
	 * @param integer $id
	 * @return result object
	 */
	public static function create($id)
	{
		$contenttypeid = vb_Types::instance()->getContentTypeID('vBCms_Article');

		if ($rst = vB::$vbulletin->db->query_read("SELECT a.contentid as itemid,
		u.username, a.contentid, n.nodeid, u.userid, i.html_title,
		a.pagetext, i.title, i.description, n.publishdate, u.avatarrevision
		" . (vB::$vbulletin->options['avatarenabled'] ? ", avatar.avatarpath,
				customavatar.userid AS hascustomavatar, customavatar.dateline AS avatardateline,
				customavatar.width AS avwidth,customavatar.height AS avheight" : "") .
		" FROM " . TABLE_PREFIX . "cms_article a
		LEFT JOIN " . TABLE_PREFIX . "cms_node n ON n.contentid = a.contentid
  		LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo i ON i.nodeid = n.nodeid
  		LEFT JOIN " . TABLE_PREFIX . "user u ON u.userid = n.userid
		" . (vB::$vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX .
		"avatar AS avatar ON(avatar.avatarid = u.avatarid) LEFT JOIN " . TABLE_PREFIX .
		"customavatar AS customavatar ON(customavatar.userid = u.userid)" : "") . "
		WHERE a.contentid = $id AND n.contenttypeid = " . $contenttypeid))
		{
			if ($search_result = vB::$vbulletin->db->fetch_array($rst))
			{
				//If unpublished we hide this.
				if (!($search_result['publishdate'] < TIMENOW))
				{
					continue;
				}
				$item = new vBCms_Search_Result_Article();
				$item->itemid = $search_result['itemid'];
				$item->contenttypeid = $contenttypeid;
				$item->record = $search_result;
				return $item;
			}
			return false;
		}
	}

	/**
	 * this will create an array of result objects from an array of ids()
	 *
	 * @param array of integer $ids
	 * @return array of objects
	 */
	public static function create_array($ids)
	{
		$contenttypeid = vb_Types::instance()->getContentTypeID('vBCms_Article');

		if ($rst = vB::$vbulletin->db->query_read("SELECT a.contentid as itemid,
		u.username, a.contentid, n.nodeid, u.userid, i.html_title,
		a.pagetext, i.title, i.description, n.publishdate
		FROM " . TABLE_PREFIX . "cms_article a
		LEFT JOIN " . TABLE_PREFIX . "cms_node n ON n.contentid = a.contentid
  		LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo i ON i.nodeid = n.nodeid
  		LEFT JOIN " . TABLE_PREFIX . "user u ON u.userid = n.userid
		WHERE a.contentid IN (" . implode(', ', $ids) .	") AND n.contenttypeid = " . $contenttypeid))
		{
			while ($search_result = vB::$vbulletin->db->fetch_array($rst))
			{
				//If unpublished we hide this.
				if (!($search_result['publishdate'] < TIMENOW))
				{
					continue;
				}
				$item = new vBCms_Search_Result_Article();
				$item->itemid = $search_result['itemid'];
				$item->contenttypeid = $contenttypeid;
				$item->record = $search_result;
				$items[$search_result['itemid']] = $item;
			}

			$ordered_items = array();
			foreach($ids AS $item_key)
			{
				if(isset($items[$item_key]))
				{
					$ordered_items[$item_key] = $items[$item_key];
					unset($items[$item_key]);
				}
			}

			return $ordered_items;
		}
		return false;
	}

	/**
	 * protected constructor, to ensure use of create()
	 *
	 */
	protected function __construct()
	{}

	/**
	 * all result objects must tell their contenttypeid
	 *
	 * @return integer contenttypeid
	 */
	public function get_contenttype()
	{

		return isset($this->contenttypeid) ?
			$this->contenttypeid :
			vB_Types::instance()->getContentTypeID("vBCms_Article");
	}

	/**
	 * all result objects must tell whether they are searchable
	 *
	 * @param mixed $user: the id of the user requesting access
	 * @return bool true
	 */
	public function can_search($user)
	{
	//By definition, an article is always searchable, even
	// for a guest.
		return true;
	}

	/**
	 * function to return the rendered html for this result
	 *
	 * @param string $current_user
	 * @param object $criteria
	 * @return
	 */
	public function render($current_user, $criteria, $template_name = '')
	{
		global $vbulletin;
		global $show;
		include_once DIR . '/vb/search/searchtools.php';

		if (!strlen($template_name))
		{
			$template_name = 'vbcms_content_article_preview';
		}
		$template = vB_Template::create($template_name);

		$template->register('title', $this->record['title'] );
		$template->register('html_title', $this->record['html_title'] );
		$page_url = vB_Route::create('vBCms_Route_Content', $this->record['nodeid'])->getCurrentURL();
		$template->register('page_url', $page_url);
		$join_char = strpos($page_url,'?') ? '&amp;' : '?';
		$template->register('newcomment_url', $page_url . $join_char . "goto=newcomment");
		$template->register('username', $this->record['username']);
		$template->register('description', $this->record['description']);
		$template->register('pagetext',
			vB_Search_Searchtools::getSummary($this->record['pagetext'], 100));

		$template->register('dateline', date($vbulletin->options['dateformat']. ' '
			. $vbulletin->options['timeformat'], $this->record['dateline']));

		if (vB::$vbulletin->options['avatarenabled'])
		{
			$avatar = fetch_avatar_from_record($this->record, true);
		}
		else
		{
			$avatar = false;
		}
		$template->register('avatar', $avatar);
		$result = $template->render();
		return $result;

	}


	/**** returns the database record
	 *
	 * @return array
	 ****/
	public function get_record()
	{
		return $this->record;
	}


	/*** Returns the primary id. Allows us to cache a result item.
	 *
	 * @result	integer
	 ***/
	public function get_id()
	{
		if (isset($this->record) AND isset($this->record['nodeid']) )
		{
			return $this->record['nodeid'];
		}
		return false;
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 63206 $
|| ####################################################################
\*======================================================================*/
