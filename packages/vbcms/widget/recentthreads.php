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
class vBCms_Widget_RecentThreads extends vBCms_Widget
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
	protected $class = 'RecentThreads';

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
			'do'     => vB_Input::TYPE_STR,
			'forumchoice'      => vB_Input::TYPE_ARRAY,
			'template_name' => vB_Input::TYPE_STR,
			'days' => vB_Input::TYPE_INT,
			'threads_type' => vB_Input::TYPE_INT,
			'cache_ttl' => vB_Input::TYPE_INT,
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

			if (vB::$vbulletin->GPC_exists['cache_ttl'])
			{
				$config['cache_ttl'] = vB::$vbulletin->GPC['cache_ttl'];
			}

			if (vB::$vbulletin->GPC_exists['days'])
			{
				$config['days'] = vB::$vbulletin->GPC['days'];
			}

			if (vB::$vbulletin->GPC_exists['count'])
			{
				$config['count'] = vB::$vbulletin->GPC['count'];
			}

			if (vB::$vbulletin->GPC_exists['threads_type'])
			{
				$config['threads_type'] = vB::$vbulletin->GPC['threads_type'];
			}

			if (vB::$vbulletin->GPC_exists['threads_type'])
			{
				$config['threads_type'] = vB::$vbulletin->GPC['threads_type'];
			}

			$config['allow_html'] = vB::$vbulletin->GPC_exists['allow_html'] ? 1 : 0;

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
				$config['template_name'] = 'vbcms_widget_recentthreads_page';
			}
			// add the config content
			$configview->template_name = $config['template_name'];
			$configview->forumchoice_select = $this->getForums($config);
			$configview->days = $config['days'];
			$configview->count = $config['count'];
			$configview->cache_ttl = $config['cache_ttl'];
			$configview->threads_type = $config['threads_type'];
			$configview->allow_html = $config['allow_html'];

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
		$threads = vB_Cache::instance()->read($hashkey, true, false);

		//If we an empty array, we are done.
		if (($threads === '1') OR ($threads === true))
		{
			return '';
		}

		if (!$threads)
		{
			$threads = $this->getThreads();

			// todo: have a verify_cache_ttl function in the base class to take care of this
			// for all widgets, no sense in repeating ourselves in all widgets

			// default ttl
			$config['cache_ttl'] = isset($config['cache_ttl']) ? intval($config['cache_ttl']) : 5;

			// constrain ttl to the range of 1 to 43200
			$config['cache_ttl'] = min(max($config['cache_ttl'], 1), 43200);

			if (empty($threads))
			{
				vB_Cache::instance()->write($hashkey, true, $config['cache_ttl'], true, false);
			}
			else
			{
				vB_Cache::instance()->write($hashkey, $threads, $config['cache_ttl'], true, false);
			}
		}

		if (empty($threads))
		{
			return '';
		}

		// Create view
		$view = new vBCms_View_Widget($this->config['template_name']);

		$view->threads = $threads;
		$view->class = $this->widget->getClass();
		$view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();

		return $view;
	}

	/**
	 * This lists the forums for the select list
	 *
	 * @param mixed $config - array of current configuration for this widget
	 * @return
	 */
	private function getForums($config, $name = 'forumchoice')
	{
		global $vbulletin, $vbphrase, $show;
		require_once DIR . '/includes/functions_search.php';

		//this will fill out $searchforumids as well as set the depth param in $vbulletin->forumcache
		global $searchforumids;
		fetch_search_forumids_array();

		//"All subscribed" requires special handling.
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
	private function getThreads()
	{
		$datecut = TIMENOW - ($this->config['days'] * 86400);

		if (empty(vB::$vbulletin->userinfo['forumpermissions']))
		{
			require_once DIR . "/includes/functions.php";
			cache_permissions($userinfo);
		}

		switch (intval($this->config['threads_type']))
		{
			case 0:
				$ordersql = " thread.dateline DESC";
				$datecutoffsql = " AND thread.dateline > $datecut";
				break;
			case 1:
				$ordersql = " thread.lastpost DESC";
				$datecutoffsql = " AND thread.lastpost > $datecut";
				break;
			case 2:
				$ordersql = " thread.replycount DESC";
				$datecutoffsql = " AND thread.dateline > $datecut";
				break;
			case 3:
				$ordersql = " thread.views DESC";
				$datecutoffsql = " AND thread.dateline > $datecut";
				break;
		}

		//we have some existing settings with config['forumchoice'] set to 0=> ''; That's no good
		if (empty($this->config['forumchoice']))
		{
			$this->config['forumchoice'] = array();
		}
		else if (is_array($this->config['forumchoice']) AND ($this->config['forumchoice'][0] == '') )
		{
			unset($this->config['forumchoice'][0]);
		}
		$subscribejoin = '';

		if (in_array('subscribed', $this->config['forumchoice']))
		{
			$subscribejoin = " INNER JOIN " . TABLE_PREFIX .	"subscribeforum AS subscribeforum
				ON (subscribeforum.forumid = forum.forumid AND subscribeforum.userid = " . vB::$vbulletin->userinfo['userid'] .
			" ) ";
		}

		$forumsql = '';
		$forumids = array_keys(vB::$vbulletin->forumcache);
		$forumchoice = array();
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
		$threadarray = array();
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
			return $threadarray;
		}


		// remove threads from users on the global ignore list if user is not a moderator
		$globalignore = '';
		if (trim(vB::$vbulletin->options['globalignore']) != '')
		{
			require_once(DIR . '/includes/functions_bigthree.php');
			if ($Coventry = fetch_coventry('string'))
			{
				$globalignore = "AND thread.postuserid NOT IN ($Coventry) ";
			}
		}
		//filter out the users that are ignored
		$ignoresql = '';
		if(!empty(vB::$vbulletin->userinfo['ignorelist']))
		{
			$ignorelist = preg_split('/( )+/', trim(vB::$vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
			if(!empty($ignorelist))
			{
				$ignoresql = "AND thread.postuserid NOT IN (" . implode(',', $ignorelist) . ")";
			}
		}
				// query last threads from visible / chosen forums
		$threads = vB::$vbulletin->db->query_read_slave($sql = "
			SELECT thread.threadid, thread.title, thread.prefixid, post.attach, post.userid AS postuserid, post.username AS postusername,
				thread.postusername, thread.dateline, thread.lastpostid, thread.lastpost, thread.lastposterid, thread.lastposter, thread.replycount,
				forum.forumid, forum.title_clean as forumtitle,
				post.pagetext AS message, post.allowsmilie, post.postid,
				user.userid, user.username, user.avatarrevision, thread.lastposter AS lastpostername
				" . (vB::$vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			FROM " . TABLE_PREFIX . "thread AS thread
			INNER JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
			LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = thread.firstpostid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (thread.postuserid = user.userid)
			" . (vB::$vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			$subscribejoin
			WHERE 1=1
				$forumsql
				AND thread.visible = 1
				AND post.visible = 1
				AND open <> 10
				$datecutoffsql
				$globalignore
				$associatedthread
				$ignoresql
			ORDER BY $ordersql
			LIMIT 0," . intval($this->config['count']) . "
		");
		require_once(DIR . '/includes/class_bbcode.php');
		$parser = new vB_BbCodeParser(vB::$vbulletin, fetch_tag_list());
		$optionval = vB::$vbulletin->bf_misc_forumoptions['allowhtml'];
		while ($thread = vB::$vbulletin->db->fetch_array($threads))
		{
			$thread['title'] = fetch_trimmed_title($thread['title'], $this->config['threads_titlemaxchars']);

			$thread['url'] = fetch_seo_url('thread', $thread);
			$thread['newposturl'] = fetch_seo_url('thread', $thread, array('goto' => 'newpost'));
			$thread['lastposturl'] = fetch_seo_url('thread', $thread, array('p' => $thread['lastpostid'])) . '#post' . $thread['lastpostid'];
			$thread['date'] = vbdate(vB::$vbulletin->options['dateformat'], $thread['dateline'], true);
			$thread['time'] = vbdate(vB::$vbulletin->options['timeformat'], $thread['dateline']);

			$thread['detailedtime'] = (vB::$vbulletin->options['yestoday'] == 2);
			$thread['lastpostdate'] = vbdate(vB::$vbulletin->options['dateformat'], $thread['lastpost'], true);
			$thread['lastposttime'] = vbdate(vB::$vbulletin->options['timeformat'], $thread['lastpost']);
			$forumid = $thread['forumid'];
			$allow_html = ((vB::$vbulletin->forumcache[$forumid]['options'] & $optionval) AND $this->config['allow_html'] ? 1 : 0);
			$thread['previewtext'] = fetch_censored_text($parser->get_preview($thread['message'], $this->default_previewlen, $allow_html));
			$thread['pagetext'] = fetch_censored_text($parser->do_parse($thread['message'], $allow_html));

			// get avatar
			if (intval($thread['userid']) AND vB::$vbulletin->options['avatarenabled'])
			{
				$avatar = fetch_avatar_from_record($thread, true);
			}

			if (!isset($avatar))
			{
				$avatar = false;
			}
			$thread['avatarurl'] = isset($avatar[0]) ?$avatar[0] : false;
			unset($avatar);
			$threadarray[$thread['threadid']] = $thread;
		}

		return $threadarray;

	}


	/**
	 * [getHash description]
	 * 
	 * @param  boolean $widgetid - Added for PHP 5.4 strict standards compliance
	 * @param  boolean $nodeid   - Added for PHP 5.4 strict standards compliance
	 * @return string
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
