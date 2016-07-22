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
 * @version $Id: staticpage.php 77608 2013-09-18 00:34:03Z pmarsden $
 * @since $Date: 2013-09-17 17:34:03 -0700 (Tue, 17 Sep 2013) $
 * @copyright vBulletin Solutions Inc.
 */

/**
 * Result Implementation for CMS StaticHtml
 *
 * @see vB_Search_Result
 * @package vBulletin
 * @subpackage Search
 */
class vBCms_Search_Result_StaticPage extends vB_Search_Result
{

	/** record contenttypeid  **/
	private $contenttypeid;

	/** record node id   **/
	private $itemid;

	/** database record  **/
	private $record;

	/** tags for this record  **/
	private $tags;

	protected $package = 'vBCms';

	protected $class = 'StaticPage';

// ###################### Start create ######################
	/**
	 * factory method to create a result object
	 *
	 * @param integer $id
	 * @return result object
	 */
	public function create($id)
	{
		return $this->create_array(array($id));
	}

	/**
	 * this will create an array of result objects from an array of ids()
	 *
	 * @param array of integer $ids
	 * @return array of objects
	 */
	public static function create_array($ids)
	{
		$contenttypeid = vB_Types::instance()->getContentTypeID('vBCms_StaticPage');
		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}

		$id_list = array();

		if ($rst = vB::$vbulletin->db->query_read("SELECT n.nodeid as itemid, n.setpublish,
		u.username,n.nodeid, u.userid, i.html_title, n.permissionsfrom, n.hidden, n.url,
		nc1.value AS previewtext, nc.value AS pagetext, i.title, i.description, n.publishdate, n.parentnode,
		parent.title AS parenttitle, parent.html_title AS parent_html_title, u.avatarrevision
		" . (vB::$vbulletin->options['avatarenabled'] ? ", avatar.avatarpath,
				customavatar.userid AS hascustomavatar, customavatar.dateline AS avatardateline,
				customavatar.width AS avwidth,customavatar.height AS avheight" : "") .
		" FROM " . TABLE_PREFIX . "cms_node AS n
  		LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo i ON i.nodeid = n.nodeid
  		LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo AS parent ON parent.nodeid = n.parentnode
  		LEFT JOIN " . TABLE_PREFIX . "user u ON u.userid = n.userid
  		LEFT JOIN " . TABLE_PREFIX . "cms_nodeconfig AS nc ON nc.nodeid = n.nodeid AND nc.name = 'pagetext'
  		LEFT JOIN " . TABLE_PREFIX . "cms_nodeconfig AS nc1 ON nc1.nodeid = n.nodeid AND nc1.name = 'previewtext'
		" . (vB::$vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX .
		"avatar AS avatar ON(avatar.avatarid = u.avatarid) LEFT JOIN " . TABLE_PREFIX .
		"customavatar AS customavatar ON(customavatar.userid = u.userid)" : "") . "
		WHERE n.nodeid IN (" . implode(', ', $ids) . ")"))
		{
			while ($search_result = vB::$vbulletin->db->fetch_array($rst))
			{
				vBCMS_Permissions::setPermissionsfrom($search_result['nodeid'], $search_result['$permissionsfrom'], $search_result['hidden'],
					$search_result['setpublish'], $search_result['publishdate'] );

				//check permissions
				if (!vBCMS_Permissions::canView($search_result['nodeid']))
				{
					continue;
				}
				$item = new vBCms_Search_Result_StaticPage();
				$item->itemid = $search_result['itemid'];
				$item->contenttypeid = $contenttypeid;
				$id_list[$search_result['nodeid']] = $search_result['itemid'];

				if ($rst1 = vB::$vbulletin->db->query_read("SELECT cat.categoryid, cat.category FROM " .
					TABLE_PREFIX . "cms_nodecategory nc INNER JOIN " .	TABLE_PREFIX .
					"cms_category cat ON nc.categoryid = cat.categoryid WHERE nc.nodeid = " .
					$search_result['nodeid']))
				{
					while($record = vB::$vbulletin->db->fetch_array($rst1))
					{
						$record['category_url'] = vB_Route::create('vBCms_Route_List', "category/" . $record['route_info'] . "/1")->getCurrentURL();
						$categories[$record['categoryid']] = $record;
					}
				}

				$search_result['categories'] = $categories;
				$item->record = $search_result;
				$items[$search_result['itemid']] = $item;
			}

			//avoid database error when all cms items are filtered out.
			if (!count($id_list))
			{
				return array();
			}

			$idlist = implode(', ', array_keys($id_list));
			if ($rst1 = vB::$vbulletin->db->query_read("SELECT tag.tagid, tag.tagtext, node.contentid FROM " .
			TABLE_PREFIX . "cms_node AS node INNER JOIN " .	TABLE_PREFIX .
			"tagcontent AS tc ON (tc.contentid = node.contentid AND  tc.contenttypeid = node.contenttypeid)
			INNER JOIN " .	TABLE_PREFIX .
			"tag AS tag ON tag.tagid = tc.tagid
			 WHERE node.nodeid IN ($idlist) " ))
			{
				while($record = vB::$vbulletin->db->fetch_array($rst1))
				{
					$items[$record['contentid']]->addTag($record['tagid'], $record);
				}
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
	*   **/
	protected function canView($permissionsfrom, $hidden)
	{
		if ($hidden)
		{
			return (in_array($permissionsfrom, vB::$vbulletin->userinfo['permissions']['cms']['canpublish']));
		}
		return (in_array($permissionsfrom, vB::$vbulletin->userinfo['permissions']['cms']['canview']));

	}

	/**** adds a tag for this record. temporary, not saved
	 *
	 * @param int tagid
	 * @param string tag
	 ****/
	public function addTag($tagid, $tag)
	{
		$this->tags[$tagid] = $tag;
	}

	/**
	 * all result objects must tell their contenttypeid
	 *
	 * @return integer contenttypeid
	 */
	public function get_contenttype()
	{
		return vB_Types::instance()->getContentTypeID('vBCms_StaticPage');
	}

	/**
	 * all result objects must tell whether they are searchable
	 *
	 * @param mixed $user: the id of the user requesting access
	 * @return bool true
	 */

	public function can_search($user)
	//By definition, StaticPage is always searchable, even
	// for a guest.
	{
		return true;
	}


	/**** returns the database record
	 *
	 * @return array
	 ****/

	public function get_record()
	{
		return $this->record;
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
		include_once DIR . '/includes/functions_user.php';

		if (!strlen($template_name))
		{
			$template_name = strtolower($this->package) . '_searchresult_' .  strtolower($this->class) . '_general';
		}
		$view = new vB_View($template_name);
		$view->title = $this->record['title'];
		$view->html_title =  $this->record['html_title'];
		$view->categories = $this->record['categories'];
		$view->published = $this->record['publishdate'] >= TIMENOW ?
		 true : false ;
		$view->publishdate = $this->record['publishdate'];
		$view->previewtext = $this->record['previewtext'];
		$view->pagetext = $this->record['pagetext'];
		$view->parent_html_title = $this->record['parent_html_title'];
		$view->dateformat = $vbulletin->options['dateformat'];
		$view->parenttitle = $this->record['parenttitle'];
		$view->timeformat = $vbulletin->options['timeformat'];
		$view->parentnode = $this->record['parentnode'];
		$view->username = $this->record['username'];
		$view->user = array(
			'username' => $this->record['username'],
			'userid' => $this->record['userid']);
		$view->page_url = vB_Route::create('vBCms_Route_Content', $this->record['nodeid'])->getCurrentURL();
		$view->nodeid = $this->record['nodeid'];
		$view->memberlink = fetch_seo_url('member', $this->record['username']);
		$view->tags = $this->tags;
		if (vB::$vbulletin->options['avatarenabled'])
		{
			$view->avatar = fetch_avatar_from_record($this->record, true);
		}
		$view->record = $this->record;

		//When we can we'll return the view, but right now the calling objects
		//want strings.

		// Create the standard vB templater
		$templater = new vB_Templater_vB();

		// Register the templater to be used for XHTML
		vB_View::registerTemplater(vB_View::OT_XHTML, $templater);
		return $view->render();

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
|| # SVN: $Revision: 77608 $
|| ####################################################################
\*======================================================================*/
