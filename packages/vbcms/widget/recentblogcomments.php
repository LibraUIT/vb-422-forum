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
class vBCms_Widget_RecentBlogComments extends vBCms_Widget
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
	protected $class = 'RecentBlogComments';

	/**
	 * Whether the content is configurable with getConfigView().
	 * @see vBCms_Widget::getConfigView()
	 *
	 * @var bool
	 */
	protected $canconfig = false;

	/**
	 * Returns the config view for the widget.
	 *
	 * @param	vB_Widget	$widget
	 * @return vBCms_View_Widget				- The view result
	 */
	public function getConfigView($widget = false)
	{
		$this->assertWidget();
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');
		fetch_phrase_group('vbblock');
		fetch_phrase_group('vbblocksettings');

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do'     => vB_Input::TYPE_STR,
			'template_name' => vB_Input::TYPE_STR,
			'cache_ttl' => vB_Input::TYPE_INT,
			'commentusernames' => vB_Input::TYPE_STR,
			'postusernames' => vB_Input::TYPE_STR,
			'taglist' => vB_Input::TYPE_STR,
			'blogid' => vB_Input::TYPE_STR,
			'cat_case_sensitive' => vB_Input::TYPE_INT,
			'messagemaxchars' => vB_Input::TYPE_INT,
			'categories' => vB_Input::TYPE_STR,
			'days' => vB_Input::TYPE_INT,
			'count'    => vB_Input::TYPE_INT
		));


		$view = new vB_View_AJAXHTML('cms_widget_config');
		$view->title = new vB_Phrase('vbcms', 'configuring_widget_x', $this->widget->getTitle());

		$config = $this->widget->getConfig();

		if ((vB::$vbulletin->GPC['do'] == 'config') AND $this->verifyPostId())
		{

			$widgetdm = new vBCms_DM_Widget($this->widget);

			if (vB::$vbulletin->GPC_exists['template_name'])
			{
				$config['template_name'] = vB::$vbulletin->GPC['template_name'];
			}

			if (vB::$vbulletin->GPC_exists['days'])
			{
				$config['days'] = vB::$vbulletin->GPC['days'];
			}

			if (vB::$vbulletin->GPC_exists['count'])
			{
				$config['count'] = vB::$vbulletin->GPC['count'];
			}

			if (vB::$vbulletin->GPC_exists['cache_ttl'])
			{
				$config['cache_ttl'] = vB::$vbulletin->GPC['cache_ttl'];
			}

			if (vB::$vbulletin->GPC_exists['messagemaxchars'])
			{
				$config['messagemaxchars'] = vB::$vbulletin->GPC['messagemaxchars'];
			}

			if (vB::$vbulletin->GPC_exists['commentusernames'])
			{
				//We could be passed an empty string. If so, clear the existing value
				if (empty(vB::$vbulletin->GPC['commentusernames']))
				{
					$config['commentuserid'] = '';
				}
				else
				{
					//We are passed names. We need to turn those into user id's
					$usernames = explode(',', vB::$vbulletin->GPC['commentusernames']);

					foreach ($usernames as $key => $username)
					{
						$usernames[$key] = "'" . vB::$db->escape_string(trim($username)) . "'";
					}
					$sql = "SELECT username, userid FROM " . TABLE_PREFIX . "user
					WHERE username IN (" . implode(',', $usernames) . ") ORDER BY lower(username)";

					if ($rst = vB::$db->query_read($sql))
					{
						$userids = array();
						while($record = vB::$db->fetch_array($rst))
						{
							$userids[$record['userid']] = $record['username'];
						}
					}
					$config['commentuserid'] = $userids;
				}
			}

			if (vB::$vbulletin->GPC_exists['postusernames'])
			{
				//We could be passed an empty string. If so, clear the existing value
				if (empty(vB::$vbulletin->GPC['postusernames']))
				{
					$config['postuserid'] = '';
				}
				else
				{
					//We are passed names. We need to turn those into user id's
					$usernames = explode(',', vB::$vbulletin->GPC['postusernames']);

					foreach ($usernames as $key => $username)
					{
						$usernames[$key] = "'" . vB::$db->escape_string(trim($username)) . "'";
					}
					$sql = "SELECT username, userid FROM " . TABLE_PREFIX . "user
					WHERE username IN (" . implode(',', $usernames) . ") ORDER BY lower(username)";

					if ($rst = vB::$db->query_read($sql))
					{
						$userids = array();
						while($record = vB::$db->fetch_array($rst))
						{
							$userids[$record['userid']] = $record['username'];
						}
					}
					$config['postuserid'] = $userids;
				}
			}

			if (vB::$vbulletin->GPC_exists['taglist'])
			{
				//We could be passed an empty string. If so, clear the existing value
				if (empty(vB::$vbulletin->GPC['taglist']))
				{
					$config['taglist'] = '';
				}
				else
				{
					//We need to confirm these are valid tags
					$tags = explode(',', vB::$vbulletin->GPC['taglist']);

					foreach ($tags as $key => $tag)
					{
						$tags[$key] = "'" . vB::$db->escape_string(trim($tag)) . "'";
					}
					$sql = "SELECT tagid, tagtext FROM " . TABLE_PREFIX . "tag
					WHERE tagtext IN (" . implode(',', $tags) . ")
					ORDER BY tagtext";

					if ($rst = vB::$db->query_read($sql))
					{
						$tagids = array();
						while($record = vB::$db->fetch_array($rst))
						{
							$tagids[$record['tagid']] = $record['tagtext'];
						}
					}
					$config['taglist'] = $tagids;
				}
			}


			if (vB::$vbulletin->GPC_exists['blogid'])
			{
				//We could be passed an empty string. If so, clear the existing value
				if (empty(vB::$vbulletin->GPC['blogid']))
				{
					$config['blogid'] = '';
				}
				else
				{
					//We need to confirm these are valid ids
					$blogids = explode(',', vB::$vbulletin->GPC['blogid']);
					$blogid_checked = array();

					foreach ($blogids as $key => $blogid)
					{
						if (intval(intval($blogid)))
						{

						}
						$blogid_checked[] = intval($blogid);
					}

					$sql = "SELECT blogid FROM " . TABLE_PREFIX . "blog
					WHERE blogid IN (" . implode(',', $blogid_checked) . ")";

					if ($rst = vB::$db->query_read($sql))
					{
						$blogids = array();
						while($record = vB::$db->fetch_array($rst))
						{
							$blogids[] = $record['blogid'];
						}
					}
					$config['blogid'] = implode(',', $blogids);
				}
			}
			if (vB::$vbulletin->GPC_exists['categories'])
			{
				//We could be passed an empty string. If so, clear the existing value
				if (vB::$vbulletin->GPC['categories'] == '')
				{
					$config['categories'] = '';
				}
				else
				{
					$categories = explode(',', vB::$vbulletin->GPC['categories']);

					foreach ($categories as $key => $category)
					{
						$categories[$key] = "'" . vB::$db->escape_string(trim($category)) . "'";
					}

					$sql = "SELECT title, blogcategoryid FROM " . TABLE_PREFIX . "blog_category
					WHERE title IN (" . implode(',', $categories) . ")";

					if ($rst = vB::$db->query_read($sql))
					{
						$categories = array();
						while($record = vB::$db->fetch_array($rst))
						{
							$categories[$record['blogcategoryid']] = $record['title'];
						}
					}
					$config['categories'] = $categories;
				}
			}

			$widgetdm->set('config', $config);

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
				$config['template_name'] = 'vbcms_widget_recentblog_page';
			}
			// add the config content
			$configview->template_name = $config['template_name'];
			$configview->days = $config['days'];
			$configview->count = $config['count'];
			$configview->messagemaxchars = $config['messagemaxchars'];
			$configview->blogid = $config['blogid'];

			if (!empty($config['postuserid']))
			{
				$configview->postusernames = implode(',', $config['postuserid']);
			}

			if (!empty($config['commentuserid']))
			{
				$configview->commentusernames = implode(',', $config['commentuserid']);
			}

			if (!empty($config['taglist']))
			{
				$configview->taglist = implode(',', $config['taglist']);
			}

			//Case sensitivity is an interesting issue. We will do the
			// search based on the db collation, which defaults to case
			// insensitive. But let's display in the stored value case
			$categories = array();

			if (!empty($config['categories']))
			{
				$lcase_categories = array();
				foreach($config['categories'] as $category)
				{
					if (!in_array(strtolower($category), $lcase_categories))
					{
						$categories[] = $category;
						$lcase_categories[] = strtolower($category);
					}
				}
				$configview->categories = implode(',', $config['categories']);
			}
			$configview->categories = implode(',', $categories);
			$configview->cache_ttl = $config['cache_ttl'];

			$this->addPostId($configview);

			$view->setContent($configview);

			// send the view
			$view->setStatus(vB_View_AJAXHTML::STATUS_VIEW, new vB_Phrase('vbcms', 'configuring_widget'));
		}

		return $view;
	}

	/**
	 * Fetches the standard page view for a widget.
	 *
	 * @return vBCms_View_Widget				- The resolved view, or array of views
	 */
	public function getPageView()
	{

		//Make sure blogs is active
		if (!isset(vB::$vbulletin->products['vbblog']) OR !vB::$vbulletin->products['vbblog'])
		{
			return '';
		}
		$this->assertWidget();

		// Create view
		$this->config = $this->widget->getConfig();
		if (!isset($this->config['template_name']) OR ($this->config['template_name'] == '') )
		{
			$this->config['template_name'] = 'vbcms_widget_recentblog_page';
		}
		if (!isset($this->config['cache_ttl']) OR !intval($this->config['cache_ttl'])
			OR (intval($this->config['cache_ttl'])< 1 )
			OR (intval($this->config['cache_ttl']) > 43200 ))
		{
			$this->config['cache_ttl'] = 1440;
		}

		// Create view
		$view = new vBCms_View_Widget($this->config['template_name']);
		$view->class = $this->widget->getClass();
		$view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();
		$view->avatarenabled = vB::$vbulletin->options['avatarenabled'];

		$hashkey = $this->getHash();
		$comments = vB_Cache::instance()->read($hashkey);
		if (!$comments)
		{
			$comments = $this->getComments();

			if (!isset($this->config['cache_ttl']) OR !intval($this->config['cache_ttl'])
				OR (intval($this->config['cache_ttl'])< 1 )
				OR (intval($this->config['cache_ttl']) > 43200 ))
			{
				$this->config['cache_ttl'] = 5;
			}
			vB_Cache::instance()->write($hashkey,
				   $comments, $this->config['cache_ttl'], 'blogcomments_updated');
		}

		if (!$comments)
		{
			$view->setDisplayView(false);
		}
		$view->comments = $comments;
		return $view;
	}

	/**
	 * This function composes and executes the SQL query to generate the
	 * blog data.
	 *
	 * @return	array
	 */
	private function getComments()
	{
		require_once DIR . "/includes/functions_user.php";

		if (!isset($this->config['days']) OR (! intval($this->config['days'])) )
		{
			$this->config['days'] = 7;
		}

		if (!isset($this->config['count']) OR (! intval($this->config['count'])) )
		{
			$this->config['count'] = 10;
		}

		if (!isset($this->config['messagemaxchars']) OR (! intval($this->config['messagemaxchars'])) )
		{
			$this->config['messagemaxchars'] = 200;
		}

		//handle authors
		$useridsql = empty($this->config['postuserid']) ? '' : " AND(blog.userid IN (" .
			implode(',', array_keys($this->config['postuserid']))
			. "))";

		$useridsql .= empty($this->config['commentuserid']) ? '' : " AND(blog_text.userid IN (" .
			implode(',', array_keys($this->config['commentuserid']))
			. "))";

		//categories
		if (empty($this->config['categories']))
		{
			$catjoin = '';
			$categorysql = '';
		}
		else
		{
			$catjoin = "LEFT JOIN " . TABLE_PREFIX . "blog_categoryuser AS cu ON (cu.blogid = blog.blogid)";
			$categorysql = " AND cu.blogcategoryid IN (" . implode(',', array_keys($this->config['categories'])) . ")";
		}

		//and tags
		if (empty($this->config['taglist']))
		{
			$tagjoin = '';
			$tagsql = '';
		}
		else
		{
			$tagjoin = "LEFT JOIN " . TABLE_PREFIX . "tagcontent AS tc ON (tc.contentid = blog.blogid AND
				tc.contenttypeid= " . vb_Types::instance()->getContentTypeID("vBBlog_BlogEntry") . ")";
			$tagsql = " AND tc.tagid IN (" . implode(',', array_keys($this->config['taglist'])) . ")";
		}

		$datecutoffsql = "AND (blog_text.dateline > " . (TIMENOW - (86400 * $this->config['days']) ).  ")" ;

		require_once(DIR . '/includes/blog_functions_shared.php');

		prepare_blog_category_permissions(vB::$vbulletin->userinfo);

		if (!(vB::$vbulletin->userinfo['permissions']['vbblog_general_permissions'] & vB::$vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
		{
			$sql_and[] = "blog.userid = " . vB::$vbulletin->userinfo['userid'];
		}

		$state = array('visible');
		if (can_moderate_blog('canmoderateentries'))
		{
			$state[] = 'moderation';
		}

		$sql_and[] = "blog.state IN('" . implode("', '", $state) . "')";
		$sql_and[] = "blog.dateline <= " . TIMENOW;
		$sql_and[] = "blog.pending = 0";

		$sql_join = array();
		$sql_or = array();
		if (!can_moderate_blog())
		{
			if (vB::$vbulletin->userinfo['userid'])
			{
				$sql_or[] = "blog.userid = " . vB::$vbulletin->userinfo['userid'];
				$sql_or[] = "(options_ignore & " . vB::$vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND ignored.relationid IS NOT NULL)";
				$sql_or[] = "(options_buddy & " . vB::$vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL)";
				$sql_or[] = "(options_member & " . vB::$vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND (options_buddy & " . vB::$vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR buddy.relationid IS NULL) AND (options_ignore & " . vB::$vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR ignored.relationid IS NULL))";
				$sql_and[] = "(" . implode(" OR ", $sql_or) . ")";

				$sql_join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = blog.userid AND buddy.relationid = " . vB::$vbulletin->userinfo['userid'] . " AND buddy.type = 'buddy')";
				$sql_join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = blog.userid AND ignored.relationid = " . vB::$vbulletin->userinfo['userid'] . " AND ignored.type = 'ignore')";

				$sql_and[] = "
					(blog.userid = " . vB::$vbulletin->userinfo['userid'] . "
						OR
					~blog.options & " . vB::$vbulletin->bf_misc_vbblogoptions['private'] . "
						OR
					(options_buddy & " . vB::$vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL))";
			}
			else
			{
				$sql_and[] = "options_guest & " . vB::$vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
				$sql_and[] = "~blog.options & " . vB::$vbulletin->bf_misc_vbblogoptions['private'];

			}
		}

		$globalignore = '';
		if (trim(vB::$vbulletin->options['globalignore']) != '')
		{
			require_once(DIR . '/includes/functions_bigthree.php');
			if ($Coventry = fetch_coventry('string'))
			{
				$globalignore = "AND blog.userid NOT IN ($Coventry) ";
			}
		}

		$sql = "SELECT blog.blogid, blog.comments_visible as replycount, blog.title,

			blog.lastcomment, blog.lastcommenter, blog_text.userid, blog_text.username, blog_text.username AS postedby_username,
			blog_text.dateline, blog_text.blogtextid, blog_text.pagetext AS message,
			blog.ratingnum, blog.ratingtotal, blog.rating, blog.views, blog.postedby_userid AS userid,
			blog.postedby_username AS blogusername, blog_user.title as blogtitle,
			blog_user.description as blogdescription, blog.trackback_visible,
			user.*
			" . (vB::$vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid)
			AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,
			customavatar.height AS avheight" : "") . "
			FROM " . TABLE_PREFIX . "blog AS blog
			INNER JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON ((blog_text.blogid = blog.blogid) AND (blog_text.blogtextid <> blog.firstblogtextid))
			INNER JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
			INNER JOIN " . TABLE_PREFIX . "user AS user2 ON (blog.userid = user2.userid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (blog_text.userid = user.userid) " .
            implode("\r\n\t ", $sql_join) . "
			$catjoin
			$tagjoin
			" . (vB::$vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX .
            "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX .
            "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			WHERE 1=1
			$useridsql
			$categorysql
			$tagsql
			$datecutoffsql
			$globalignore
			AND " . implode("\r\n\tAND ", $sql_and) . "
			ORDER BY blog_text.dateline DESC
			LIMIT 0," . $this->config['count'] ;

		$results = vB::$db->query_read($sql);
		$array = array();
		$parser = new vBCms_BBCode_HTML(vB::$vbulletin, vBCms_BBCode_HTML::fetchCmsTags());
		while ($blogcomment = vB::$db->fetch_array($results))
		{
			$blogcomment['title'] = fetch_trimmed_title($blogcomment['title'], $this->config['blogentries_titlemaxchars']);

			$urlinfo = array('blogid' => $blogcomment['blogid'], 'blog_title' => $blogcomment['title']);
			$blogcomment['url'] = fetch_seo_url('entry', $urlinfo, array('bt' => $blogcomment['blogtextid']))
				. "#comment" . $blogcomment['blogtextid'] ;

			$blogcomment['blogtitle'] = $blogcomment['blogtitle'] ? $blogcomment['blogtitle'] : $blogcomment['blogusername'];

			$blogcomment['date'] = vbdate(vB::$vbulletin->options['dateformat'], $blogcomment['dateline'], true);
			$blogcomment['time'] = vbdate(vB::$vbulletin->options['timeformat'], $blogcomment['dateline']);

			$blogcomment['lastpostdate'] = vbdate(vB::$vbulletin->options['dateformat'], $blogcomment['lastcomment'], true);
			$blogcomment['lastposttime'] = vbdate(vB::$vbulletin->options['timeformat'], $blogcomment['lastcomment']);

			$blogcomment['message'] = $this->getSummary($blogcomment['message'], $this->config['messagemaxchars']);

			//get the avatar
			if (vB::$vbulletin->options['avatarenabled'])
			{
				$blogcomment['avatar'] = fetch_avatar_from_record($blogcomment, true);
			}
			else
			{
				$blogcomment['avatar'] = 0;
			}

			$blogcomment['tags'] = array();
			$array[$blogcomment['blogtextid']] = $blogcomment;
		}

		//let's get the tags;
		if (!empty($array))
		{
			$sql = "SELECT tag.tagid, tc.contentid, tag.tagtext
			FROM " . TABLE_PREFIX . "tagcontent AS tc INNER JOIN " .	TABLE_PREFIX .
			"tag AS tag ON tag.tagid = tc.tagid
				 WHERE tc.contentid IN (" . implode(',', array_keys($array)) . ") AND
				tc.contenttypeid= " . vb_Types::instance()->getContentTypeID("vBBlog_BlogEntry") ;
			if ($rst = vB::$db->query_read($sql))
			{
				while ($record = vB::$db->fetch_array($rst))
				{
					$array[$record['contentid']]['tags'][$record['tagid']] = $record['tagtext'];
				}
			}
		}
		return $array;

	}

	protected function getSummary($pagetext, $length)
	{
		require_once(DIR . '/includes/functions_search.php');

		//figure out how to handle the 'cancelwords'
		$display['highlight'] = array();
		$page_text =  preg_replace('#\[quote(=(&quot;|"|\'|)??.*\\2)?\](((?>[^\[]*?|(?R)|.))*)\[/quote\]#siUe',
			"process_quote_removal('\\3', \$display['highlight'])", $pagetext);

		$strip_quotes = true;

		// Deal with the case that quote was the only content of the post
		if (trim($page_text) == '')
		{
			$page_text = $pagetext;
			$strip_quotes = false;
		}

		return htmlspecialchars_uni(fetch_censored_text(
			trim(fetch_trimmed_title(strip_bbcode($page_text, $strip_quotes, false, false, true), $length))));
	}

	/**
	 * This function generates a unique hash for this item
	 *
	 * @param  mixed $widgetid - Added for PHP 5.4 strict standards compliance
	 * @param  mixed $nodeid   - Added for PHP 5.4 strict standards compliance
	 *
	 * @return	string
	 */
	protected function getHash($widgetid = false, $nodeid = false)
	{
		$context = new vB_Context('widget_' . $this->widget->getId() ,
		array(
			'widgetid' => $this->widget->getId(),
			'permissions' => vB::$vbulletin->userinfo['permissions']['vbblog_general_permissions'])
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
