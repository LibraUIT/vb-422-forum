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
 * Test Widget Controller
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 48079 $
 * @since $Date: 2011-08-12 12:55:39 -0700 (Fri, 12 Aug 2011) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Widget_RecentContent extends vBCms_Widget_RecentArticle
{
	/*Properties====================================================================*/

	/**
	 * A package identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this widget.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	protected $view_class = 'Content';

	/**
	 * A class identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this widget.
	 *
	 * @var string
	 */
	protected $class = 'RecentContent';

	/**
	 * Whether the content is configurable with getConfigView().
	 * @see vBCms_Widget::getConfigView()
	 *
	 * @var bool
	 */
	protected $canconfig = true;

	protected $config = array();

	/*Render========================================================================*/


	/** This function gets the article information based on the defined criteria
	*
	 * @return	array
	 */
	protected function getContent()
	{
		// First, compose the sql
		$sql = "SELECT node.contenttypeid, node.url, node.publishdate, node.userid,
		node.setpublish, node.publicpreview, info.title, user.username, node.showuser,
		node.nodeid, node.contenttypeid, thread.replycount, user.avatarrevision
		" . (vB::$vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, info.creationdate AS dateline,
		NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,
		customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
		FROM "
		. TABLE_PREFIX . "cms_node AS node
		INNER JOIN "	. TABLE_PREFIX . "contenttype AS type on type.contenttypeid = node.contenttypeid
		INNER JOIN "	. TABLE_PREFIX . "cms_nodeinfo AS info on info.nodeid = node.nodeid "
		. ( (($this->config['categories'] != '') AND ($this->config['categories'] != '0')) ?
			" INNER JOIN " . TABLE_PREFIX .
		"cms_nodecategory nc ON nc.nodeid = node.nodeid " : '') .	"
		LEFT JOIN "	. TABLE_PREFIX . "user AS user ON user.userid = node.userid
		LEFT JOIN "	. TABLE_PREFIX . "thread AS thread ON thread.threadid = info.associatedthreadid
		" . (vB::$vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX .
		"avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX .
		"customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
		WHERE type.isaggregator = '0' AND " . vBCMS_Permissions::getPermissionString() ;

		if (($this->config['categories'] != '') AND ($this->config['categories'] != '0') )
		{
			$sql .= "\n AND nc.categoryid IN (" . $this->config['categories'] . ")\n";
		}

		if (($this->config['sections'] != '') AND ($this->config['sections'] != '0'))
		{

			$sql .= "\n AND node.parentnode IN (" . $this->config['sections'] . ")\n";
		}

		if (isset($this->config['days']) AND (intval($this->config['days'])) )
		{
			$sql .= "\n AND node.publishdate > " . (TIMENOW - (86400 * $this->config['days'])) . "\n";
		}

		$sql .= "\n ORDER BY node.publishdate DESC LIMIT " . $this->config['count'];
		$items = array();

		//Execute
		if ($rst = vB::$db->query_read($sql))
		{
			$current_record = array('contentid' => -1);
			//now build the results array
			while($item = vB::$db->fetch_array($rst))
			{
				$item['categories'] = array();
				$item['tags'] = array();
				$class = vB_Types::instance()->getContentTypeClass($item['contenttypeid']);
				$package = vB_Types::instance()->getContentTypePackage($item['contenttypeid']);
				$node = vBCms_Content::create($package, $class, $item['nodeid']);
				$item['pagetext'] = $item['previewtext'] = '';

				//get the avatar
				if (vB::$vbulletin->options['avatarenabled'])
				{
					$item['avatar'] = fetch_avatar_from_record($item, true);
				}

				if (method_exists($node, 'getPageText'))
				{
					$item['pagetext'] = fetch_censored_text($node->getPageText());
				}

				if (method_exists($node, 'getPreviewText'))
				{
					$item['previewtext'] = fetch_censored_text($node->getPreviewText());
				}
				else if (!empty($item['pagetext']))
				{
					$item['previewtext'] = vB_Search_Searchtools::getSummary($item['pagetext'], 200);
				}

				if (method_exists($node, 'getPreviewImage'))
				{
					$item['pagetext'] = fetch_censored_text($node->getPageText());
				}

				$items[$item['nodeid']]  = $item;
			}

			//Let's get the tags and the categories
			// we can do that with one query each.
			if (count($articles))
			{
				//first let's get categories
				$nodeids = implode(', ', array_keys($item));
				$sql = "SELECT nc.nodeid, nc.categoryid, category.category FROM " . TABLE_PREFIX . "cms_nodecategory AS nc
				INNER JOIN "	. TABLE_PREFIX . "cms_category AS category ON category.categoryid = nc.categoryid
				WHERE nc.nodeid IN ($nodeids)";
				if ($rst = vB::$db->query_read($sql))
				{
					while ($record = vB::$db->fetch_array($rst))
					{
						$route_info = $record['categoryid'] .
							($record['category'] != '' ? '-' . str_replace(' ', '-', $record['category']) : '');
						$record['route_info'] = $route_info;
						$record['category_url'] = vB_Route::create('vBCms_Route_List', "category/" . $record['route_info'] . "/1")->getCurrentURL();

						$items[$record['nodeid']]['categories'][$record['categoryid']] = $record;
					}
				}

				//next tags;
				$sql = "SELECT tag.tagid, node.nodeid, tag.tagtext FROM " .
				TABLE_PREFIX . "cms_node AS node INNER JOIN " .	TABLE_PREFIX .
				"tagcontent AS tc ON (tc.contentid = node.contentid AND  tc.contenttypeid = node.contenttypeid)
				INNER JOIN " .	TABLE_PREFIX .
				"tag AS tag ON tag.tagid = tc.tagid
				 WHERE node.nodeid IN ($nodeids) ";
				if ($rst = vB::$db->query_read($sql))
				{
					while ($record = vB::$db->fetch_array($rst))
					{
						$items[$record['nodeid']]['tags'][$record['tagid']] = $record['tagtext'];
					}
				}
			}
		}
		return $items;
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 48079 $
|| ####################################################################
\*======================================================================*/
