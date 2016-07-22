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
 * @version $Id: article.php 30550 2009-04-28 23:55:20Z ebrown $
 * @since $Date: 2009-04-28 16:55:20 -0700 (Tue, 28 Apr 2009) $
 * @copyright vBulletin Solutions Inc.
 */

require_once (DIR . '/vb/search/result.php');
require_once (DIR . '/packages/vbcms/item/content/article.php');
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

	/** categories to which this record belongs  **/
	private $categories;

	/** tags for this record  **/
	private $tags;

	/** record node id   **/
	private $itemid;
	/** database record  **/
	private $record;
	protected $default_previewlen = 120;

	/**
	 * factory method to create a result object
	 *
	 * @param integer $id
	 * @return result object
	 */
	public static function create($id)
	{
		return self::create_array(array($id));
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
		$rst = vB::$vbulletin->db->query_read_slave($sql = "
			SELECT a.contentid as itemid, a.htmlstate,
				u.username, a.contentid, n.nodeid, u.userid, i.html_title, a.blogid, n.setpublish AS published,
				n.url, n.showtitle, n.showuser, n.showpreviewonly, u.avatarrevision,
				n.showupdated, n.showviewcount, n.settingsforboth,
				a.pagetext, i.title, i.description, n.publishdate, parent.title as parenttitle, i.viewcount,
				n.parentnode as parentid, a.threadid, a.postauthor, a.poststarter, a.blogpostid,
				a.postid, a.post_started, a.post_posted, thread.threadid AS comment_threadid ,
				thread.title AS threadtitle, thread.replycount,
				thread.lastposterid, thread.lastposter, thread.dateline, thread.views, thread.lastpost"
				. (vB::$vbulletin->options['avatarenabled'] ? ", avatar.avatarpath,
				customavatar.userid AS hascustomavatar, customavatar.dateline AS avatardateline,
				customavatar.width AS avwidth,customavatar.height AS avheight" : "") .
				" FROM " . TABLE_PREFIX . "cms_article a
				LEFT JOIN " . TABLE_PREFIX . "cms_node n ON n.contentid = a.contentid
  			LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo i ON i.nodeid = n.nodeid
  			LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo AS parent ON parent.nodeid = n.parentnode
  			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON thread.threadid = i.associatedthreadid
  			LEFT JOIN " . TABLE_PREFIX . "user u ON u.userid = n.userid
			" . (vB::$vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX .
			"avatar AS avatar ON(avatar.avatarid = u.avatarid) LEFT JOIN " . TABLE_PREFIX .
			"customavatar AS customavatar ON(customavatar.userid = u.userid)" : "") . "
			WHERE a.contentid IN (" . implode(', ', $ids) .") AND n.contenttypeid = " . $contenttypeid
		);

		$id_list = array();
		$items = array();

		$nodelist = vB::$vbulletin->db->query_first_slave($sql = "
			SELECT GROUP_CONCAT(nodeid) AS nodes
			FROM " . TABLE_PREFIX . "cms_node
			WHERE contenttypeid = $contenttypeid
			AND contentid IN (" . implode(',',$ids) . ")
		");

		require_once(DIR . '/packages/vbattach/attach.php');
		$attach = new vB_Attach_Display_Content(vB::$vbulletin, 'vBCms_Article');
		$attachmentcache = $attach->fetch_postattach(0, array($nodelist['nodes']));

		if ($rst)
		{
			$bbcode_parser = new vBCms_BBCode_HTML(vB::$vbulletin, vBCms_BBCode_HTML::fetchCmsTags());
			$bbcode_parser->setOutputPage(1);
			while ($search_result = vB::$vbulletin->db->fetch_array($rst))
			{
				//If unpublished we hide this.
				if (!($search_result['publishdate'] < TIMENOW))
				{
					continue;
				}
				$item = new vBCms_Search_Result_Article();
				$item->itemid = $search_result['itemid'];
				$categories = array();
				$tags = array();
				$item->contenttypeid = $contenttypeid;
				$nodeid = $search_result['nodeid'];
				$search_result['attachments'] = $attachmentcache[$nodeid];
				$bbcode_parser->unsetattach = true;
				$bbcode_parser->attachments =& $search_result['attachments'];
				$search_result['pagetext'] = $bbcode_parser->do_parse($search_result['pagetext'], true);
				$search_result['categories'] = $categories;
				$item->record = $search_result;
				$id_list[$search_result['nodeid']] = $search_result['itemid'];
				$items[$search_result['itemid']] = $item;
			}

			//avoid database error when all cms items are filtered out.
			if (!count($id_list))
			{
				return array();
			}

			$idlist = implode(', ', array_keys($id_list));
			$rst1 = vB::$vbulletin->db->query_read_slave(
				"SELECT cat.categoryid, cat.category, nc.nodeid FROM " .
				TABLE_PREFIX . "cms_nodecategory AS nc INNER JOIN " .	TABLE_PREFIX .
				"cms_category AS cat ON nc.categoryid = cat.categoryid WHERE nc.nodeid IN ($idlist)"
			);

			if ($rst1)
			{
				$route = new vBCms_Route_List();
				$route->setParameter('action', 'list');

				while($record = vB::$vbulletin->db->fetch_array($rst1))
				{
					$itemid = $id_list[$record['nodeid']];
					$route_info = $record['categoryid'] .
						($record['category'] != '' ? '-' . $record['category'] : '');
					$record['category_url'] = vBCms_Route_List::getUrl(array('type' =>'category',
					 'value' => $route_info , 'page' => 1));
					$items[$itemid]->addCategory($record['categoryid'], $record)  ;
				}
			}

			if ($rst1 = vB::$vbulletin->db->query_read_slave("SELECT tag.tagid, tag.tagtext, node.contentid FROM " .
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
		require_once DIR . '/vb/search/searchtools.php';
		require_once DIR . "/includes/functions_user.php";
		require_once DIR . "/includes/functions.php";

		if (!strlen($template_name))
		{
			$template_name = 'vbcms_searchresult_article_general';
		}
		$template = vB_Template::create($template_name);

		$template->register('title', vBCMS_Permissions::canUseHtml($this->record['nodeid'], vb_Types::instance()->getContentTypeID('vBCms_Article'),
			 $this->record['userid']) ? $this->record['title'] : htmlspecialchars_uni($this->record['title']));
		$template->register('html_title', vBCMS_Permissions::canUseHtml($this->record['nodeid'], vb_Types::instance()->getContentTypeID('vBCms_Article'),
			 $this->record['userid']) ? $this->record['html_title'] : htmlspecialchars_uni($this->record['html_title']));

		// Bug 35855: due to a different bug, 35413, users are able to save articles with
		// invalid seo url aliases. this causes the getCurrentUrl to throw a vB_Exception_Router
		// exception when attempting to build article URL's for search. so, to prevent
		// the search from blowing up on these articles results, we will trap these exceptions,
		// and generate the url without the alias in that case
		try
		{
			$page_url = vB_Route::create('vBCms_Route_Content', $this->record['nodeid'] .
				($this->record['url'] == '' ? '' : '-' . $this->record['url'] ))->getCurrentURL();
		}
		catch (vB_Exception_Router $e)
		{
			$page_url = vB_Route::create('vBCms_Route_Content', $this->record['nodeid'])->getCurrentURL();
		}
		$template->register('page_url', $page_url);
		$this->record['page_url'] = $page_url;
		try
		{
			$parent_url = vB_Route::create('vBCms_Route_Content', $this->record['parentid'] .
				($this->record['parenttitle'] == '' ? '' : '-' . $this->record['parenttitle'] )	)->getCurrentURL();
		}
		catch (vB_Exception_Router $e)
		{
			$parent_url = vB_Route::create('vBCms_Route_Content', $this->record['parentid'])->getCurrentURL();
		}
		$template->register('parent_url', $parent_url);

		$template->register('lastcomment_url', $page_url . "#new_comment");
		$template->register('username', $this->record['username']);
		$template->register('description', $this->record['description']);
		$template->register('parenttitle' , htmlspecialchars_uni($this->record['parenttitle']) );
		$template->register('parentid' , $this->record['parentid'] );
		$template->register('threadid' , $this->record['threadid'] );
		$template->register('postauthor' , $this->record['postauthor'] );
		$template->register('poststarter' , $this->record['poststarter'] );
		$template->register('blogpostid' , $this->record['blogpostid'] );
		$template->register('parentnode' , $this->record['parentnode'] );
		$template->register('postid' , $this->record['postid'] );
		$template->register('post_started' , $this->record['post_started'] );
		$template->register('post_posted' , $this->record['post_posted'] );
		$can_use_html = vBCMS_Permissions::canUseHtml($this->record['nodeid'], vb_Types::instance()->getContentTypeID('vBCms_Article'),
			 $this->record['userid']) ;
		$template->register('previewtext', $this->getPreviewText($this->record));
		$template->register('pagetext',
			 $can_use_html ? fetch_censored_text($this->record['pagetext']) :
			 fetch_censored_text(htmlspecialchars_uni($this->record['pagetext'])));
		$template->register('publish_phrase', ($this->record['publishdate'] ?
			$vbphrase['page_published'] : $vbphrase['page_unpublished']) );
		$template->register('author_phrase', 'author');
		$template->register('published', ($this->record['publishdate'] ?
			true : false));
		$template->register('categories', $this->categories);
		$template->register('tags', $this->tags);
		$template->register('replycount', ($this->record['replycount'] ?
			$this->record['replycount'] : '0'));
		$template->register('article', $this->record);
		$template->register('publishdate', vbdate($vbulletin->options['dateformat'], $this->record['publishdate'], true));
		$template->register('publishtime', vbdate($vbulletin->options['timeformat'], $this->record['publishdate']));
		$template->register('publishdateline', $this->record['publishdate']);
		$template->register('lastpostdate', vbdate($vbulletin->options['dateformat'], $this->record['lastpost'], true));
		$template->register('lastpostdatetime', vbdate($vbulletin->options['timeformat'], $this->record['lastpost']));
		$template->register('lastpostdateline', $this->record['lastpost']);
		$template->register('lastposter', $this->record['lastposter']);
		$template->register('lastposterinfo', array('userid'=>$this->record['lastposterid'], 'username'=>$this->record['lastposter']));
		$template->register('dateformat', $vbulletin->options['dateformat']);
		$template->register('timeformat', $vbulletin->options['timeformat']);
		$user = vB_Legacy_User::createFromId($this->record['userid']);

		//get the avatar
		if (intval($this->record['userid']) AND vB::$vbulletin->options['avatarenabled'])
		{
			$avatar = fetch_avatar_from_record($this->record, true);
		}

		if (!isset($avatar) )
		{
			$avatar = false;
		}
		$template->register('avatar', $avatar);
		$result = $template->render();

		return $result;
	}


	/**** returns the categories to which this record belongs
	 *
	 * @return array
	 ****/
	public function getCategories()
	{
		return $this->categories;
	}


	/**** returns the tags for this record
	 *
	 * @return array
	 ****/
	public function getTags()
	{
		return $this->tags;
	}


	/**** adds a category for this record. temporary, not saved
	 *
	 * @param int categoryid
	 * @param string category
	 ****/
	public function addCategory($categoryid, $category)
	{
		$this->categories[$categoryid] = $category;
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

	/** Gets the preview text for the article
	 *
	 * @return	string	previewtext
	 ***/
	public function getPreviewText($article)
	{
		$item =  new vBCms_Item_Content_Article($article['nodeid'], vBCms_Item_Content::INFO_CONTENT);
		return $item->getPreviewText();
	}

	/*** Returns the primary id. Allows us to cache a result item.
	 *
	 * @result	integer
	 ***/
	public function get_id()
	{
		if (isset($this->record) AND isset($this->record['nodeid']) )
		{
			return $this->record['contentid'];
		}
		return false;
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 30550 $
|| ####################################################################
\*======================================================================*/
