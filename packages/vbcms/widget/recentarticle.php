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
 * @version $Revision: 77408 $
 * @since $Date: 2013-09-06 13:02:39 -0700 (Fri, 06 Sep 2013) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Widget_RecentArticle extends vBCms_Widget
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


	/**
	 * A class identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this widget.
	 *
	 * @var string
	 */
	protected $class = 'RecentArticle';

	protected $view_class = 'Article';

	/**
	 * Whether the content is configurable with getConfigView().
	 * @see vBCms_Widget::getConfigView()
	 *
	 * @var bool
	 */
	protected $canconfig = true;

	protected $update_cacheevent = 'articles_updated';

	protected $config = array();


	/*Render========================================================================*/

	/**
	 * Returns the config view for the widget.
	 *
	 * @param	object	$widget
	 * @return 	vBCms_View_Widget															- The view result
	 */

	public function getConfigView($widget = false)
	{
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');
		global $messagearea, $vbphrase;

		$this->assertWidget();

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do'      => vB_Input::TYPE_STR,
			'cache_ttl' => vB_Input::TYPE_INT,
			'categories' => vB_Input::TYPE_ARRAY,
			'sections' => vB_Input::TYPE_ARRAY,
			'template_name' => vB_Input::TYPE_STR,
			'days'    => vB_Input::TYPE_INT,
			'count'   => vB_Input::TYPE_INT
			));

		$view = new vB_View_AJAXHTML('cms_widget_config');
		$view->title = new vB_Phrase('vbcms', 'configuring_widget_x', $this->widget->getTitle());

		$config = $this->widget->getConfig();

		if ((vB::$vbulletin->GPC['do'] == 'config') AND $this->verifyPostId())
		{
			$widgetdm = new vBCms_DM_Widget($this->widget);

			if (vB::$vbulletin->GPC_exists['cache_ttl'] AND intval(vB::$vbulletin->GPC['cache_ttl']))
			{
				$config['cache_ttl'] = intval(vB::$vbulletin->GPC['cache_ttl']);
			}

			if (vB::$vbulletin->GPC_exists['categories'])
			{
				$config['categories'] = implode(',', vB::$vbulletin->GPC['categories']);
			}

			if (vB::$vbulletin->GPC_exists['sections'])
			{
				$config['sections'] = implode(',', vB::$vbulletin->GPC['sections']);
			}

			if (vB::$vbulletin->GPC_exists['template_name'])
			{
				$config['template_name'] = vB::$vbulletin->GPC['template_name'];
			}

			if (vB::$vbulletin->GPC_exists['days'] AND intval(vB::$vbulletin->GPC['days']))
			{
				$config['days'] = vB::$vbulletin->GPC['days'];
			}

			if (vB::$vbulletin->GPC_exists['count'] AND intval(vB::$vbulletin->GPC['count']))
			{
				$config['count'] = vB::$vbulletin->GPC['count'];
			}

			$widgetdm->set('config', $config);

			if ($this->content)
			{
				$widgetdm->setConfigNode($this->content->getNodeId());
			}

			$widgetdm->save();

			if (!$widgetdm->hasErrors())
			{
				if ($this->content)
				{
					$segments = array('node' => $this->content->getNodeURLSegment(),
										'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'EditPage'));
					$view->setUrl(vB_View_AJAXHTML::URL_FINISHED, vBCms_Route_Content::getURL($segments));
				}

				$view->setStatus(vB_View_AJAXHTML::STATUS_FINISHED, new vB_Phrase('vbcms', 'configuration_saved'));
			}
			else
			{
				if (vB::$vbulletin->debug)
				{
					$view->addErrors($widgetdm->getErrors());
				}

				// only send a message
				$view->setStatus(vB_View_AJAXHTML::STATUS_MESSAGE, new vB_Phrase('vbcms', 'configuration_failed'));
			}
		}
		else
		{
			// add the config content
			$configview = $this->createView('config');

			if (!isset($config['template_name']) OR ($config['template_name'] == '') )
			{
				$config['template_name'] = 'vbcms_widget_' . strtolower($class) . '_page';
			}
			// add the config content
			$configview->template_name = $config['template_name'];
			$configview->cache_ttl = $config['cache_ttl'];
			$configview->days = $config['days'];
			$configview->count = $config['count'];
			$configview->categories = $this->getCategoryList($config['categories']);
			$configview->sections = $this->getSectionList($config['sections']);


			$this->addPostId($configview);

			$view->setContent($configview);

			// send the view
			$view->setStatus(vB_View_AJAXHTML::STATUS_VIEW, new vB_Phrase('vbcms', 'configuring_widget'));
		}

		return $view;
	}

	/** generates the category options list
	*
	 * @param	string	$category_str
	 * @return string
	 */
	private function getCategoryList($category_str)
	{
		$current_categories = explode(',', $category_str);
		$categories = vBCms_ContentManager::getCategories(false, false, 2000, 0, true);
		$select = '<option value="0">' . new vB_Phrase('global', 'all') . "</option>\n";

		foreach ($categories['results'] as $category)
		{
			$select .= "<option value=\"" . $category['categoryid'] . '"' .
				(in_array($category['categoryid'], $current_categories) ? ' selected="selected"' : '' ) .
				'>' . $category['category'] . " (" .
				$category['item_count'] . ")</option>\n";
		}

		return $select;
	}


	/** generates the section options list
	 * @param	string	$section_str
	 * @return	string
	 */
	private function getSectionList($section_str)
	{
		$current_sections = explode(',', $section_str);
		$sections = vBCms_ContentManager::getSections(false);
		$select = '<option value="0">' . new vB_Phrase('global', 'all') . "</option>\n";
		foreach ($sections as $section)
		{
			$select .= "<option value=\"" . $section['nodeid'] . '" ' .
				(in_array($section['nodeid'], $current_sections) ? 'selected="selected"' : '' )  .
				'>' . $section['title'] . " (" .
				$section['publish_count'] . ")</option>\n";
		}

		return $select;
	}

	/**
	 * Fetches the standard page view for a widget.
	 *
	 * @return vBCms_View_Widget				- The resolved view, or array of views
	 */
	public function getPageView()
	{
		require_once DIR . "/includes/functions_user.php";
		require_once DIR . '/includes/functions.php';
		$this->assertWidget();

		// Get the configuration
		$this->config = $this->widget->getConfig();

		if (!isset($this->config['template_name']) OR ($this->config['template_name'] == '') )
		{
			$this->config['template_name'] = 'vbcms_widget_recent' . $this->class. '_page';
		}

		// Create view
		$view = new vBCms_View_Widget($this->config['template_name']);

		$view->class = $this->widget->getClass();
		$view->widget_title = $view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();
		$hashkey = $this->getHash();
		$contentlist = vB_Cache::instance()->read($hashkey);

		if (!$contentlist)
		{

			if (!isset($this->config['days']) OR (! intval($this->config['days'])) )
			{
				$this->config['days'] = 7;
			}

			if (!isset($this->config['count']) OR (! intval($this->config['count'])) )
			{
				$this->config['count'] = 10;
			}

			if (!isset($this->config['cache_ttl']) OR !intval($this->config['cache_ttl'])
				OR (intval($this->config['cache_ttl'])< 1 )
				OR (intval($this->config['cache_ttl']) > 43200 ))
			{
				$this->config['cache_ttl'] = 5;
			}
			$contentlist = $this->getContent();
			vB_Cache::instance()->write($hashkey,
				   $contentlist, $this->config['cache_ttl'], $this->update_cacheevent);
		}

		foreach ($contentlist as $key => $content)
		{
			$contentlist[$key]['page_url'] = vB_Route::create('vBCms_Route_Content', $content['nodeid'] .
				($content['url'] == '' ? '' : '-' . $content['url'] )	)->getCurrentURL();

			//and category url's
			foreach($content['categories'] AS $categoryid => $record)
			{
				$route_info = $record['categoryid'] .
					($record['category'] != '' ? '-' . $record['category'] : '');
				$contentlist[$key]['categories'][$categoryid]['category_url'] =
					vB_Route::create('vBCms_Route_List', "category/" . $route_info . "/1")->getCurrentURL();
			}
		}

		$view->articles = $contentlist;
		return $view;
	}

	/** This function gets the article information based on the defined criteria
	*
	 * @return	array
	 */
	protected function getContent()
	{

		// First, compose the sql
		$sql = "SELECT article.pagetext, article.previewimage, article.imagewidth,
		article.imageheight, article.previewvideo, article.htmlstate, node.url, node.publishdate, node.userid,
		node.setpublish, node.publicpreview, info.title, user.username, node.showuser, info.creationdate AS dateline,
		node.settingsforboth, node.showpublishdate, node.showtitle,
		node.nodeid, node.contenttypeid, thread.replycount, user.avatarrevision " .
		(vB::$vbulletin->options['avatarenabled'] ? ", avatar.avatarpath,
		customavatar.userid AS hascustomavatar, customavatar.dateline AS avatardateline,
		customavatar.width AS avwidth,customavatar.height AS avheight" : "") .
		" FROM "
		. TABLE_PREFIX . "cms_article AS article INNER JOIN "
		. TABLE_PREFIX . "cms_node AS node ON (node.contentid = article.contentid
		AND node.contenttypeid = " . vb_Types::instance()->getContentTypeID("vBCms_Article") .
		") INNER JOIN "	. TABLE_PREFIX . "cms_nodeinfo AS info on info.nodeid = node.nodeid "
		. ( (($this->config['categories'] != '') AND ($this->config['categories'] != '0')) ?
			" INNER JOIN " . TABLE_PREFIX .
		"cms_nodecategory nc ON nc.nodeid = node.nodeid " : '') .	"
		LEFT JOIN "	. TABLE_PREFIX . "user AS user ON user.userid = node.userid
		LEFT JOIN "	. TABLE_PREFIX . "thread AS thread ON thread.threadid = info.associatedthreadid
		" . (vB::$vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX .
		"avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX .
		"customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
		WHERE " . vBCMS_Permissions::getPermissionString() ;

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
		$articles = array();

		//Execute
		if ($rst = vB::$db->query_read($sql))
		{
			$current_record = array('contentid' => -1);
			$contenttypeid = vb_Types::instance()->getContentTypeID($this->package . '_' . $this->view_class);
			//now build the results array
			$bbcode_parser = new vBCms_BBCode_HTML(vB::$vbulletin,  vBCms_BBCode_HTML::fetchCmsTags());
			while($article = vB::$db->fetch_array($rst))
			{
				$article['categories'] = array();
				$article['tags'] = array();
				$article['pagetext'] = strip_tags($article['pagetext']);
				$allow_html = vBCMS_Permissions::canUseHtml($article['nodeid'], $contenttypeid, $article['userid']);
				$pagetext = $bbcode_parser->get_preview(fetch_censored_text($article['pagetext']),
					vB::$vbulletin->options['default_cms_previewlength'], $allow_html);
				$article['previewtext'] = strip_bbcode($pagetext);

				//get the avatar
				if (vB::$vbulletin->options['avatarenabled'])
				{
					$article['avatar'] = fetch_avatar_from_record($article, true);
				}

				$articles[$article['nodeid']]  = $article;
			}

			//Let's get the tags and the categories
			// we can do that with one query each.
			if (count($articles))
			{
				//first let's get categories
				$nodeids = implode(', ', array_keys($articles));
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

						$articles[$record['nodeid']]['categories'][$record['categoryid']] = $record;
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
						$articles[$record['nodeid']]['tags'][$record['tagid']] = $record['tagtext'];
					}
				}
			}
		}
		return $articles;
	}

	/**
	*  This function generates a unique hash for this item
	*
	* @param mixed - Added for PHP 5.4 strict standards compliance 
	* @param mixed - Added for PHP 5.4 strict standards compliance 
	*
	 * @return	string
	 */
	protected function getHash($widgetid = false, $nodeid = false)
	{
		$context = new vB_Context('widget_' . $this->widget->getId() ,
		array(
			'widgetid' => $this->widget->getId(),
			'permissions' => vB::$vbulletin->userinfo['permissions']['cms'])
		);

		return strval($context);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77408 $
|| ####################################################################
\*======================================================================*/
