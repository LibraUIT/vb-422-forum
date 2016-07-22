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
 * @version $Revision: 77258 $
 * @since $Date: 2013-09-02 17:14:45 -0700 (Mon, 02 Sep 2013) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Widget_RecentPosts extends vBCms_Widget
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
	protected $class = 'RecentPosts';

	/**
	 * Whether the content is configurable with getConfigView().
	 * @see vBCms_Widget::getConfigView()
	 *
	 * @var bool
	 */
	protected $canconfig = false;

	protected $default_previewlen = 150;

	/*Render========================================================================*/

	/**
	 * Returns the config view for the widget.
	 *
	 * @return vBCms_View_Widget				- The view result
	 */
	public function getConfigView($widget = false)
	{
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');
		fetch_phrase_group('search');

		$this->assertWidget();

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do'      => vB_Input::TYPE_STR,
			'forumchoice' => vB_Input::TYPE_ARRAY,
			'template_name' => vB_Input::TYPE_STR,
			'cache_ttl' => vB_Input::TYPE_INT,
			'days' => vB_Input::TYPE_INT,
			'allow_html' => vB_Input::TYPE_INT,
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

			//make sure we have actual values for forumchoice
			if (vB::$vbulletin->GPC_exists['forumchoice'])
			{
				$config['forumchoice'] = vB::$vbulletin->GPC['forumchoice'];
			}

			if (vB::$vbulletin->GPC_exists['count'])
			{
				$config['count'] = vB::$vbulletin->GPC['count'];
			}

			if (vB::$vbulletin->GPC_exists['days'])
			{
				$config['days'] = vB::$vbulletin->GPC['days'];
			}

			if (vB::$vbulletin->GPC_exists['cache_ttl'])
			{
				$config['cache_ttl'] = vB::$vbulletin->GPC['cache_ttl'];
			}

			if (vB::$vbulletin->GPC_exists['template_name'])
			{
				$config['template_name'] = vB::$vbulletin->GPC['template_name'];
			}

			$config['allow_html'] = vB::$vbulletin->GPC_exists['allow_html'] ? 1 : 0;
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
				$config['template_name'] = 'vbcms_widget_recentposts_page';
			}
			// add the config content
			$configview->template_name = $config['template_name'];
			$configview->forumchoice_select = $this->getForums($config);
			$configview->days = $config['days'];
			$configview->count = $config['count'];
			$this->addPostId($configview);
			$configview->cache_ttl = $config['cache_ttl'];
			$configview->allow_html = $config['allow_html'];

			$view->setContent($configview);

			// send the view
			$view->setStatus(vB_View_AJAXHTML::STATUS_VIEW, new vB_Phrase('vbcms', 'configuring_widget'));
		}

		return $view;
	}

	/**
	 * This lists the forums for the select list
	 *
	 @param mixed $config - array of current configuration for this widget
	 * @return
	 */
	private function getForums($config, $name = 'forumchoice')
	{
		global $vbulletin, $vbphrase, $show;
		require_once DIR . '/includes/functions_search.php';

		//this will fill out $searchforumids as well as set the depth param in $vbulletin->forumcache
		global $searchforumids;
		fetch_search_forumids_array();


		$options = "";

		foreach ($searchforumids AS $forumid)
		{
			$forum = $vbulletin->forumcache["$forumid"];

			if (trim($forum['link']))
			{
				continue;
			}

			$optionvalue = $forumid;
			$optiontitle = "$forum[depthmark] $forum[title_clean]";

			if (!($vbulletin->userinfo['forumpermissions'][$forumid] & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
			{
				$optiontitle .= '*';
				$show['cantsearchposts'] = true;
			}

			$optionselected = '';

			if ($config['forumchoice'] AND in_array($forumid, $config['forumchoice']))
			{
				$optionselected = 'selected="selected"';
				$haveforum = true;
			}

			require_once DIR . '/includes/adminfunctions.php';
			$options .= render_option_template(construct_depth_mark($forum['depth'], '--') . ' ' . $optiontitle, $forumid, $optionselected);
		}

		//"All subscribed" requires special handling.
		if (is_array($config['forumchoice']) AND in_array('subscribed', $config['forumchoice']))
		{
			$subscribed_selected = 'selected="selected"';
			$haveforum = true;
		}
		else
		{
			$subscribed_selected = '';
		}

		$select = "<select name=\"" .$name."[]\" multiple=\"multiple\" size=\"6\" $style_string>\n" .
					render_option_template($vbphrase['search_all_open_forums'], '',
						$haveforum ? '' : 'selected="selected"') .
					render_option_template($vbphrase['search_subscribed_forums'], 'subscribed', $subscribed_selected) .
					$options .
				 	"</select>\r";
		return $select;

	}



	/**
	 * Fetches the standard page view for a widget.
	 *
	 * @param bool $skip_errors					- If using a collection, omit widgets that throw errors
	 * @return vBCms_View_Widget				- The resolved view, or array of views
	 */
	public function getPageView()
	{

		require_once DIR . "/includes/functions_user.php";

		$this->assertWidget();

		// Create view
		$this->config = $this->widget->getConfig();
		if (!isset($this->config['template_name']) OR ($this->config['template_name'] == '') )
		{
			$this->config['template_name'] = 'vbcms_widget_recentthreads_page';
		}

		$hashkey = $this->getHash();
		//get the data
		$posts = vB_Cache::instance()->read($hashkey, false, true);

		//If we have an empty array, we are done.
		if (($posts === '1') OR ($posts === true))
		{
			return '';
		}

		if (!$posts)
		{
			$posts = $this->getPosts();
			if (!isset($config['cache_ttl']) OR !intval($config['cache_ttl'])
				OR (intval($config['cache_ttl'])< 1 )
				OR (intval($config['cache_ttl']) > 43200 ))
			{
				$config['cache_ttl'] = 5;
			}
			if (empty($posts))
			{
				vB_Cache::instance()->write($hashkey,
					   true, $config['cache_ttl'], true, false);
			}
			else
			{
				vB_Cache::instance()->write($hashkey,
					   $posts, $config['cache_ttl'], true, false);
			}
		}

		if (empty($posts))
		{
			return '';
		}

		// Create view
		$view = new vBCms_View_Widget($this->config['template_name']);
		$view->posts = $posts;



		$view->class = $this->widget->getClass();
		$view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();
		return $view;
	}

	/**
	 * Fetches the array of posts
	 *
	 * @return array				- the post information
	 */
	private function getPosts()
	{
		require_once DIR . "/includes/functions_user.php";
		require_once DIR . "/includes/class_bbcode.php";
		$datecut = TIMENOW - ($this->config['days'] * 86400);

		if (empty(vB::$vbulletin->userinfo['forumpermissions']))
		{
			require_once DIR . "/includes/functions.php";
			cache_permissions($userinfo);
		}
		$postarray = array();
		//we have some existing settings with config['forumchoice'] set to 0=> ''; That's no good
		if (empty($this->config['forumchoice']))
		{
			$this->config['forumchoice'] = array();
		}
		else if (is_array($this->config['forumchoice']) AND ($this->config['forumchoice'][0] == '') )
		{
			unset($this->config['forumchoice'][0]);
		}

		if (is_array($this->config['forumchoice']) AND in_array('subscribed', $this->config['forumchoice']))
		{
			$subscribejoin = " LEFT JOIN " . TABLE_PREFIX .	"subscribeforum AS subscribeforum
				ON (subscribeforum.forumid = forum.forumid AND subscribeforum.userid = " . vB::$vbulletin->userinfo['userid'] .
			" ) ";
		}
		else
		{
			$subscribejoin = '';

		}
		$forumids = array_keys(vB::$vbulletin->forumcache);
		foreach ($forumids AS $forumid)
		{
			$forumperms =& vB::$vbulletin->userinfo['forumpermissions']["$forumid"];
			if ($forumperms & vB::$vbulletin->bf_ugp_forumpermissions['canview']
				AND ($forumperms & vB::$vbulletin->bf_ugp_forumpermissions['canviewothers'])
				AND (($forumperms & vB::$vbulletin->bf_ugp_forumpermissions['canviewthreads']))
				AND verify_forum_password($forumid, vB::$vbulletin->forumcache["$forumid"]['password'], false)
				)
			{
					//Don't include the comments forum.
				if (vB::$vbulletin->options['vbcmsforumid'] > 0 AND (intval(vB::$vbulletin->options['vbcmsforumid']) == intval($forumid)))
				{
					continue;
				}
				//Or, if the user selected forums, anything not on the list.
				else if (! empty($this->config['forumchoice']) AND !in_array($forumid, $this->config['forumchoice']))
				{
					continue;
				}
				$forumchoice[] = $forumid;
			}
		}

		if (!empty($forumchoice) )
		{
			//Note that there is an opening quote in $forumsql and the matching close
			// quote in $associatedthread. We intend to rewrite this query in the
			// next release to remove this inconsistency
			$forumsql = " AND (" . (empty($subscribejoin) ? '' : "subscribeforum.forumid IS NOT NULL OR ") . " thread.forumid IN(" . implode(',', $forumchoice) . ")";
			$associatedthread = (vB::$vbulletin->options['vbcmsforumid'] ? " AND (thread.forumid <> " . vB::$vbulletin->options['vbcmsforumid'] . ") )" : ')');
		}
		else if (! empty($subscribejoin))
		{
			$forumsql = " AND subscribeforum.forumid IS NOT NULL ";
		}
		else
		{
			return $postarray;
		}
		// remove threads from users on the global ignore list if user is not a moderator
		$globalignore = '';
		if (trim(vB::$vbulletin->options['globalignore']) != '')
		{
			require_once(DIR . '/includes/functions_bigthree.php');
			if ($coventry = fetch_coventry('string'))
			{
				$globalignore = "AND post.userid NOT IN ($coventry) ";
			}
		}
		//filter out the users that are ignored
		$ignoresql = '';
		if(!empty(vB::$vbulletin->userinfo['ignorelist']))
		{
			$ignorelist = preg_split('/( )+/', trim(vB::$vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
			if(!empty($ignorelist))
			{
				$ignoresql = "AND post.userid NOT IN (" . implode(',', $ignorelist) . ")";
			}
		}
		$posts = vB::$vbulletin->db->query_read_slave($sql = "
			SELECT post.dateline, post.pagetext, post.allowsmilie, post.postid,
				thread.threadid, thread.title, thread.prefixid, post.attach, thread.replycount,
				forum.forumid, post.title AS posttitle, post.dateline AS postdateline,
				user.userid, post.username, user.avatarrevision
				" . (vB::$vbulletin->options['avatarenabled'] ? ", avatar.avatarpath,
				customavatar.userid AS hascustomavatar, customavatar.dateline AS avatardateline,
				customavatar.width AS avwidth,customavatar.height AS avheight" : "") .
		 "	FROM " . TABLE_PREFIX . "post AS post
			JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
			JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (post.userid = user.userid)
			" . (vB::$vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX .
		"avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX .
		"customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			$subscribejoin
			WHERE 1=1
				$forumsql
				$associatedthread
				AND thread.visible = 1
				AND post.visible = 1
				AND thread.open <> 10
				AND post.dateline > $datecut
				$globalignore
				$ignoresql
			ORDER BY post.dateline DESC
			LIMIT 0," . intval($this->config['count']) . "
		");
		$parser = new vB_BbCodeParser(vB::$vbulletin, fetch_tag_list());
		$optionval = vB::$vbulletin->bf_misc_forumoptions['allowhtml'];
		while ($post = vB::$vbulletin->db->fetch_array($posts))
		{
			$post['title'] = fetch_trimmed_title($post['title'], $this->config['newposts_titlemaxchars']);

			$allow_html = ((vB::$vbulletin->forumcache[$post['forumid']]['options'] & $optionval) AND $this->config['allow_html'] ? 1 : 0);
			if(!$allow_html)
			{ 	// Strip html tags completely if html is not allowed.
				$post['pagetext'] = strip_tags($post['pagetext']);
			}
			$post['previewtext'] = fetch_censored_text($parser->get_preview($post['pagetext'], $this->default_previewlen, $allow_html));
			$post['pagetext'] = fetch_censored_text($parser->do_parse($post['pagetext'], $allow_html));

			$post['url'] = fetch_seo_url('thread', $post, array('p' => $post['postid'])) . '#post' . $post['postid'];
			$post['newposturl'] = fetch_seo_url('thread', $post, array('goto' => 'newpost'));

			$post['detailedtime'] = (vB::$vbulletin->options['yestoday'] == 2);
			$post['date'] = vbdate(vB::$vbulletin->options['dateformat'], $post['dateline'], true);
			$post['time'] = vbdate(vB::$vbulletin->options['timeformat'], $post['dateline']);

			if (vB::$vbulletin->options['avatarenabled'])
			{
				$avatar = fetch_avatar_from_record($post, true);
			}
			else
			{
				$avatar = false;
			}

			$post['avatarurl'] = isset($avatar[0]) ? $avatar[0] : false;
			unset($avatar);
			$postarray[$post['postid']] = $post;
		}

		return $postarray;

	}



	/**
	 * Fetches the hash key for hashing
	 * 
	 * @param  boolean $widgetid - Added for PHP 5.4 strict standards compliance
	 * @param  boolean $nodeid   - Added for PHP 5.4 strict standards compliance
	 * 
	 * @return string				- The hash key
	 */
	public function getHash($widgetid = false, $nodeid = false)
	{
		$context = new vB_Context('widget_' . $this->widget->getId() ,
		array(
			'widgetid' => $this->widget->getId(),
			'permissions' => vB::$vbulletin->userinfo['forumpermissions'],
			'ignorelist' => vB::$vbulletin->userinfo['ignorelist'],
			THIS_SCRIPT)
		);

		return strval($context);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77258 $
|| ####################################################################
\*======================================================================*/
