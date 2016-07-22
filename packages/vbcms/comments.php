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
 * vBCms_Comments
 *
 * @package
 * @author ebrown
 * @copyright Copyright (c) 2009
 * @version $Id: comments.php 77670 2013-09-20 19:09:23Z pmarsden $
 * @access public
 */
class vBCms_Comments
{
	/*Properties====================================================================*/

	/**
	 * A package identifier.
	 * This is used to resolve any related class names.
	 */
	protected $package = 'vBCms';

	/**
	 * A class identifier.
	 */
	protected $class = 'Comments';

	/**** Caching time in minutes ********/
	protected static $static_cache_ttl = 1440;
	/*Render========================================================================*/

	/**
	 * Fetches the standard page view .
	 * @param integer $nodeid - node for which we are displaying comments
	 *
	 * @return vBCms_View				- The resolved view, or array of views
	 */
	public function getPageView($nodeid, $target_url)
	{
		global $vbphrase;

		require_once DIR . '/includes/functions_editor.php';

			vB::$vbulletin->input->clean_array_gpc('r', array(
			'nodeid'     => vB_Input::TYPE_INT,
			'page' => vB_Input::TYPE_INT,
			'direction' => vB_Input::TYPE_STR,
			'postid' => vB_Input::TYPE_UINT
		));

		if (!$row = vB::$vbulletin->db->query_first("
			SELECT node.comments_enabled, node.setpublish, node.publishdate,
			nodeinfo.associatedthreadid, thread.forumid, article.allcomments
			FROM "	. TABLE_PREFIX . "cms_node AS node
			LEFT JOIN "	. TABLE_PREFIX . "cms_nodeinfo AS nodeinfo ON (node.nodeid = nodeinfo.nodeid)
			LEFT JOIN "	. TABLE_PREFIX . "cms_article AS article ON (article.contentid = node.contentid)
			LEFT JOIN "	. TABLE_PREFIX . "thread AS thread ON (thread.threadid = nodeinfo.associatedthreadid)
			WHERE	nodeinfo.nodeid = $nodeid
			LIMIT 1;" ))
		{
			return false;
		}

		if (!$row['comments_enabled'] OR !$row['setpublish'] OR ($row['publishdate'] > TIMENOW))
		{
			return false;
		}

		if (!intval($row['forumid']))
		{
			self::repairComments($row['associatedthreadid'], $nodeid);
		}

		if (!intval($row['associatedthreadid']))
		{
			return false;
		}

		$associatedthreadid = $row['associatedthreadid'];

		$base_url = empty($target_url) ? vB_Router::getCurrentURL() : $target_url;


		// Create view
		$view = new vB_View('vbcms_comments_page');
		$view->nodeid = $nodeid;
		$view->threadid = $row['associatedthreadid'];
		$view->this_url = str_replace('&amp;', '&', $base_url);

		// display publish to Facebook checkbox in quick editor?
		if (is_facebookenabled())
		{
			$view->fbpublishcheckbox = construct_fbpublishcheckbox();
		}

		$this_user = new vB_Legacy_CurrentUser();

		$pageno = vB::$vbulletin->GPC_exists['page'] ?
			vB::$vbulletin->GPC['page'] : 1;
		$view->pageno = $pageno;

		$view->node_comments = self::showComments($view->nodeid,
			$this_user, $pageno, 20, $target_url, $row['allcomments'], $associatedthreadid);

		// make sure user has permission to post comment before displaying comment editor
		if (self::canPostComment($view->threadid, $this_user))
		{
			// prepare the wyswiwig editor for comments
			$view->show_comment_editor = true;
			global $messagearea;
			$editor_name = construct_edit_toolbar(
				'',
				false,
				'article_comment',
				true,
				true,
				false,
				'qr',
				'',
				array(),
				'content',
				'vBCms_ArticleComment',
				0,
				$nodeid
			);
			$view->messagearea = $messagearea;
			$view->editor_name = $editor_name;

			// include captcha validation and guest username field
			if (fetch_require_hvcheck('post'))
			{
				require_once(DIR . '/includes/class_humanverify.php');
				$reg = vB::$vbulletin;
				$verification =& vB_HumanVerify::fetch_library($reg);
				$human_verify = $verification->output_token();
			}
			else
			{
				$human_verify = '';
			}
			$view->human_verify = $human_verify;
			$view->usernamecode = new vB_View('newpost_usernamecode');
		}
		else
		{
			$view->show_comment_editor = false;
		}

		return $view;
	}

	/** If somebody deletes a forum or thread, the function does cleanup ***/
	private static function repairComments($threadid, $nodeid)
	{
		if (!$threadid OR !$nodeid)
		{
			return false;
		}

		vB::$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "cms_nodeinfo
			SET associatedthreadid = 0
			WHERE associatedthreadid = $threadid
		");

		vB_Cache::instance()->eventPurge('cms_comments_change');
		vB_Cache::instance()->eventPurge('cms_comments_change_' . $threadid);
		vB_Cache::instance()->eventPurge('cms_comments_add_' . $nodeid);

		//we also need to clear the item cache.
		$node = new vBCms_Item_Content($nodeid);
		$class  = vB_Types::instance()->getContentClassFromId($node->getContentTypeId());
		$classname = $class['package']. "_Item_Content_" . $class['class'];

		if (class_exists($classname))
		{
			$node = new $classname($nodeid);
		}

		vB_Cache::instance()->event($node->getCacheEvents());
	}

	/*******
	* This function gets a list of comment ids and caches the list.
	***/
	private static function getComments($nodeid, $userinfo, &$permissions, $allcomments, $associatedthreadid, $ajax = false)
	{
		require_once DIR . '/vb/cache.php';
		$comments = vB_Cache::instance()->read(self::getStaticHash($nodeid));
		if ($comments AND !empty($comments) AND !$ajax)
		{
			return $comments;
		}

		//This should be moved to the boot process, but currently we're only
		// using the db_assertor class here. So no sense spending the
		// cpu cycles to initialize on every page load. But in the future
		// we should move it.

		vB_dB_Assertor::init(vB::$vbulletin->db, vB::$vbulletin->userinfo);

		//There are two different queries, if we display all comments, or just those since we started.

		if (intval($allcomments))
		{
			$comments = vB_dB_Assertor::assertQuery('get_all_comments', array('nodeid' => $nodeid));
		}
		else
		{
			$comments = vB_dB_Assertor::assertQuery('get_comments', array('nodeid' => $nodeid));
		}

		if (!$comments OR !$comments->valid())
		{
			return false;
		}

		$ids = array();

		//Now we compare the fields. We need to check fields from the third
		// to the end of the row. If the value is different from the previous row,
		// we add a record.
		$row = $comments->current();
		while( $comments->valid())
		{
			if (self::canViewPost($row, $permissions))
			{
				$ids[] = $row['postid'];
			}
			$row = $comments->next();
		}

		if ((count($ids) == 1) and !intval($ids[0]))
		{
			$ids = false;
		}

		//Now we have a list of posts.
		vB_Cache::instance()->write(self::getStaticHash($nodeid),
			   $ids, self::$static_cache_ttl,
			   array('cms_comments_change_' . $associatedthreadid,
			  'cms_comments_add_' . $nodeid) );
		return $ids;

	}

	/**********
	* To determine if we can display the results, there are two
	* checks- first we check to see our rights at the thread level.
	* Then we check each post. Here's the first function
	* Note that in the future I expect to call this from ajax, and
	* in that case we don't have an item. So these function have to be
	* callable as static.
	*
	* @param nodeid integer : The node.
	*
	* @return mixed :  can be false, or an array of privileges
	***/
	public static function canViewThread($nodeid, $user)
	{
		require_once DIR . '/vb/legacy/thread.php';

		if (! $row = vB::$vbulletin->db->query_first("
			SELECT nodeinfo.associatedthreadid AS threadid, thread.forumid
			FROM " . TABLE_PREFIX . "cms_nodeinfo AS nodeinfo
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = nodeinfo.associatedthreadid)
			WHERE nodeinfo.nodeid = $nodeid;"
			)
		)
		{
			return false;
		}

		//we have to worry about people deleting the thread or the forum. Annoying.
		if (intval($row['associatedthreadid']) AND !intval($row['forumid']))
		{
			self::repaircomments($record['associatedthreadid'], $nodeid);
			return false;
		}

		// Trust me, it's just a temp fix -- Xiaoyu
		// A temp fix for what ? Need to look at this sometime (Paul).
		global $thread;
		$thread = vB_Legacy_Thread::create_from_id($row['threadid']);
		if (!$thread)
		{
			return false;
		}

		if (!$thread->can_view($user))
		{
			return false;
		}

		$can_moderate_forums = $user->canModerateForum($thread->get_field('forumid'));
		$can_moderate_posts = $user->canModerateForum($thread->get_field('forumid'), 'canmoderateposts');
		$is_coventry = false;

		if (!$can_moderate_forums)
		{
			//this is cached.  Should be fast.
			require_once (DIR . '/includes/functions_bigthree.php');
			$conventry = fetch_coventry();

			$is_coventry = (in_array($user->get_field('userid'), $conventry));

		}

		if (!$can_moderate_forums AND $is_coventry)
		{
			return false;
		}

		// If we got here, the user can at least see the thread. We still have
		// to check the individual records;
		return array('can_moderate_forums' => $can_moderate_forums,
		'is_coventry' => $is_coventry,
		'can_moderate_posts' => $can_moderate_posts);
	}

	/******
	* Here we check each post, using the thread privileges we got from
	* canViewThread.
	*
	* @param $postid  integer  	The post
	* @param $privileges array		from canViewThread
	* @return boolean
	***/
	private static function canViewPost($post, $privileges)
	{

		if (!$privileges['can_moderate_forums'] )
		{
			if ( $privileges['is_coventry'] OR ($post['visible'] == 2))
			{
				return false;
			}
		}

		// post/thread is deleted by moderator and we don't have permission to see it
		return ($post['visible'] OR $privileges['can_moderate_posts']);
	}

	/******
	* Check if a user has permission to post in the comment thread
	*
	* @param $threadid  integer  	The comment threadid
	* @param $user vB_Legacy_CurrentUser
	* @return boolean
	***/
	private function canPostComment($threadid, $user)
	{
		$commentinfo = fetch_threadinfo($threadid);

		// if user did not submit the article or user is guest, check reply others permission
		$userid = $user->get_field('userid');
		if ( empty($userid) OR ($userid != $commentinfo['postuserid']) )
		{
			return $user->hasForumPermission($commentinfo['forumid'], 'canreplyothers');
		}

		// if current user submitted article, check reply own forum permission
		else
		{
			return $user->hasForumPermission($commentinfo['forumid'], 'canreplyown');
		}
	}

	/**********************
	* This function just gets the thread id for a given post
	* @param integer $postid
	* @return integer $threadid
	*
	****/
	private static function getThreadId($postid)
	{
		if ($record = vB::$vbulletin->db->query_first( "SELECT threadid FROM " . TABLE_PREFIX
		. "post AS post where postid = $postid"))
		{
			return $record['threadid'];
		}
		return false;
	}

	/*********************
	* This function creates HTML for a user interface. It is called by
	* ajax.php so we can add a user interface.
	* @param integer $postid
	* @return nothing.
	*********/

	public static function GetCommentUIXml($postid)
	{
		require_once DIR . '/includes/functions_misc.php';
		require_once DIR . '/includes/functions_editor.php';
		global $vbphrase;
		global $messagearea;
		global $sessionhash;
		fetch_phrase_group('posting');
		fetch_phrase_group('vbcms');

		$editor_name = construct_edit_toolbar(
			'',
			false,
			'article_comment',
			true,
			true,
			false,
			'fe_nofocus',
			'editor_' . $postid,
			array(),
			'content',
			'vBCms_ArticleComment',	// Todo - not sure which contenttype goes here
			$postid
		);
		$template = vB_Template::create('vbcms_comments_editor');
		$template->register('messagearea', $messagearea	);
		$template->register('sessionhash', $sessionhash	);
		$template->register('editor_name', $editor_name	);
		$template->register('postid', $postid	);
		$template->register('threadid', self::getThreadId($postid));

		$xml = new vB_AJAX_XML_Builder( vB::$vbulletin, 'text/xml');
		$xml->add_group('root');

		//todo handle prefs for xml types
		$xml->add_tag('html', $template->render());

		$xml->close_group();
		$xml->print_xml();
	}
	/**This does the actual work of creating the navigation elements. This needs some
	 * styling, but we'll do that later.
	 * @param int 		node
	 * @param array 	user info (vbulletin->userinfo normally)
	 * @param int 		page number
	 * @param int		items per page
	 * @param string	page url
	 * @param int		thread id for comments
	 *
	 * @return string;
	 */
	private static function showComments($nodeid, $userinfo, $pageno,
		$perpage, $target_url, $allcomments, $associatedthreadid, $ajax = false)
	{

		require_once DIR . '/includes/functions_misc.php';
		require_once DIR . '/includes/functions.php';
		require_once DIR . '/includes/functions_databuild.php';
		require_once DIR . '/includes/functions_bigthree.php';

		global $vbphrase;
		global $show;
		global $sessionhash;

		fetch_phrase_group('posting');

		//First let's see if we have forum/thread view permissions. If not,
		// we're done
		if (! $permissions = self::canViewThread($nodeid, $userinfo))
		{
			return false;
		}
		$forumperms = fetch_permissions(self::getForumId($nodeid));

		//Normally this thread will be wide open, so let's get the list first
		// without checking. We'll verify each post anyway.

		//get our results
		$results = self::getComments($nodeid, $userinfo, $permissions, $allcomments, $associatedthreadid, $ajax);
		$record_count = count($results);

		if (!$results OR !count($results))
		{
			return '';
		}

		//If we are passed a postid, we'll display just that comment.
		if (vB::$vbulletin->GPC_exists['postid'] AND intval(vB::$vbulletin->GPC['postid'])
			AND ($record_count > $perpage) AND in_array(vB::$vbulletin->GPC['postid'], $results))
		{
			$index = array_search(vB::$vbulletin->GPC['postid'], $results);
			$pageno = max(1,ceil(($index+1)/$perpage));
			$first = ($pageno -1) * $perpage;
		}
		else
		{
			//we accept the parameter "last" for pageno.
			if ($pageno == 'last')
			{
				$pageno = intval(($record_count + $perpage -1) / $perpage);
				$first = ($pageno -1) * $perpage;
			}
			else
			{
				$pageno = max(1, intval($pageno) );
				$first = $perpage * ($pageno -1) ;
			}
		}

		//Let's trim off the results we need.
		//This also tells us if we should show the "next" button.
		$results = array_slice($results, $first, $perpage, true);

		//Now format the overall block.
		$ajax_last_post = 0;
		if (!count($results) OR !$comments = self::renderResult($userinfo, $results, $permissions,
				$forumperms, $target_url, $nodeid, $ajax_last_post)
			OR ($comments == ''))
		{
			return false;
		}

		if (strpos($target_url,'?') === false)
		{
			$target_url .= '?';
		}

		$pagenav = construct_page_nav($pageno, $perpage, $record_count, $target_url, '', 'comments');

		$allow_ajax_qr = (($pageno == ceil($record_count / $perpage)) ? 1 : 0); // On last page ?

		$template = vB_Template::create('vbcms_comments_block');
		$template->register('comment_count', $record_count	);
		$template->register('sessionhash', $sessionhash	);
		$template->register('pagenav', $pagenav);
		$template->register('cms_comments', $comments);
		$template->register('this_url', $target_url);
		$template->register('nodeid', $nodeid);
		$template->register('target_url', $target_url);
		$template->register('allow_ajax_qr', $allow_ajax_qr);
		$template->register('ajax_last_post', $ajax_last_post);
		return $template->render() ;
	}

	/**This does the actual work of creating the navigation elements and returns the results
	*  as XML
	* @param int 		node
	* @param array 	user info (vbulletin->userinfo normally)
	* @param int 		page number
	* @param int		items per page
	* @param string	page url
	* @param int		thread id for comments
	* @return string;
	*/
	public static function showCommentsXml($nodeid, $userinfo, $pageno = 1,
		$perpage = 20, $target_url = '', $associatedthreadid = '', $all_comments)
	{
		require_once DIR . '/includes/functions_misc.php';
		global $show;

		$xml = new vB_AJAX_XML_Builder( vB::$vbulletin, 'text/xml');
		$xml->add_group('root');

		//todo handle prefs for xml types
		$xml->add_tag('html', $check_val = self::showComments($nodeid, $userinfo,  "last",
		$perpage, $target_url, $all_comments, $associatedthreadid, true));

		$xml->close_group();
		return $xml->fetch_xml();
		//$xml->print_xml();
	}

	/******
	* This function gets the forum id, which we get for fetch_permissions
	* @param integer $nodeid
	* @return object permissions
	***/
	private static function getForumId($nodeid)
	{
		if (! $row = vB::$vbulletin->db->query_first("SELECT forumid FROM "
			. TABLE_PREFIX . "cms_nodeinfo AS nodeinfo INNER JOIN " . TABLE_PREFIX
			. "thread AS thread ON thread.threadid = nodeinfo.associatedthreadid
			WHERE nodeinfo.nodeid = $nodeid"))
		{
			return false;
		}
		return $row['forumid'];
	}


	/*****
	* This function renders the results for display . It is basically copied
	*  from showthread.php. We have a minor problem with keeping order, because
	*  we can't use any sql sort. So we put the html into the array,
	*  and then dump it in order.
	*
	* @param array $userinfo
	* @param array $array of sequence => array(postid, level, html)
	* @param array $permissions : What are this user's permissions for this thread?
	*
	* @return string : the html results of the render.
	*/
	private static function renderResult($userinfo, $post_array, $permissions,
		$forumperms, $target_url, $nodeid, &$finalposttime)
	{

		if (!count($post_array))
		{
			return '';
		}
		require_once DIR . '/includes/functions_bigthree.php' ;
		require_once DIR . '/includes/class_postbit.php' ;

		fetch_phrase_group('showthread');
		fetch_phrase_group('postbit');

		global $vbphrase;
		global $template_hook;
		global $show;
		global $thread;
		$thread = $thread->get_record();
		$threadinfo = verify_id('thread', $thread['threadid'], 1, 1);
		$foruminfo = verify_id('forum', $threadinfo['forumid'], 1, 1);
		$firstpostid = false;

		$displayed_dateline = $threadinfo['lastpost'];
		$finalposttime = intval($threadinfo['lastpost']); // pass this back for ajax.

		if (vB::$vbulletin->options['threadmarking'] AND vB::$vbulletin->userinfo['userid'])
		{
			$threadview = max($threadinfo['threadread'], $threadinfo['forumread'], TIMENOW - (vB::$vbulletin->options['markinglimit'] * 86400));
		}
		else
		{
			$threadview = intval(fetch_bbarray_cookie('thread_lastview', $threadinfo['threadid']));
			if (!$threadview)
			{
				$threadview = vB::$vbulletin->userinfo['lastvisit'];
			}
		}
		require_once DIR . '/includes/functions_user.php';
		$show['inlinemod'] = false;

		if (!isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}

		$postids = ' post.postid in ('
 			. implode(', ', $post_array) .')';

		$posts =  vB::$vbulletin->db->query_read($sql = "
			SELECT
			post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
			user.*, userfield.*, usertextfield.*,
			" . iif($foruminfo['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
			" . iif( vB::$vbulletin->options['avatarenabled'] AND vB::$vbulletin->userinfo['showavatars'], 'avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight,') . "
			" . ((can_moderate($threadinfo['forumid'], 'canmoderateposts') OR can_moderate($threadinfo['forumid'], 'candeleteposts')) ? 'spamlog.postid AS spamlog_postid,' : '') . "
				editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline,
				editlog.reason AS edit_reason, editlog.hashistory,
				postparsed.pagetext_html, postparsed.hasimages,
				sigparsed.signatureparsed, sigparsed.hasimages AS sighasimages,
				sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight,
				IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid,
			 	customprofilepic.userid AS profilepic, customprofilepic.dateline AS profilepicdateline, customprofilepic.width AS ppwidth, customprofilepic.height AS ppheight
				" . iif(!($permissions['genericpermissions'] &  vB::$vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']),  vB::$vbulletin->profilefield['hidden']) . "
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
			LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
			" . iif($foruminfo['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
			" . iif( vB::$vbulletin->options['avatarenabled'] AND vB::$vbulletin->userinfo['showavatars'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") . "
			" . ((can_moderate($threadinfo['forumid'], 'canmoderateposts') OR can_moderate($threadinfo['forumid'], 'candeleteposts')) ? "LEFT JOIN " . TABLE_PREFIX . "spamlog AS spamlog ON(spamlog.postid = post.postid)" : '') . "
			LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
			LEFT JOIN " . TABLE_PREFIX . "postparsed AS postparsed ON(postparsed.postid = post.postid AND postparsed.styleid = " . intval(STYLEID) . " AND postparsed.languageid = " . intval(LANGUAGEID) . ")
			LEFT JOIN " . TABLE_PREFIX . "sigparsed AS sigparsed ON(sigparsed.userid = user.userid AND sigparsed.styleid = " . intval(STYLEID) . " AND sigparsed.languageid = " . intval(LANGUAGEID) . ")
			LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = post.userid)
			LEFT JOIN " . TABLE_PREFIX . "customprofilepic AS customprofilepic ON (user.userid = customprofilepic.userid)
			WHERE $postids
			ORDER BY post.dateline
		");

		if (!($forumperms & vB::$vbulletin->bf_ugp_forumpermissions['canseethumbnails']))
		{
			vB::$vbulletin->options['attachthumbs'] = 0;
		}

		if (!($forumperms & vB::$vbulletin->bf_ugp_forumpermissions['cangetattachment']))
		{
			vB::$vbulletin->options['viewattachedimages'] = ((vB::$vbulletin->options['viewattachedimages'] AND vB::$vbulletin->options['attachthumbs']) ? 1 : 0);
		}

		$postcount = count($post_array);

		$counter = 0;
		$postbits = '';
		vB::$vbulletin->noheader = true;

		$postbit_factory = new vB_Postbit_Factory();
		$postbit_factory->registry = vB::$vbulletin;
		$postbit_factory->forum = $foruminfo;
		$postbit_factory->thread = $threadinfo;
		$postbit_factory->cache = array();
		$postbit_factory->bbcode_parser = new vB_BbCodeParser( vB::$vbulletin, fetch_tag_list());
		//We need to tell the parser to handle quotes differently.
		$postbit_factory->bbcode_parser->set_quote_template('vbcms_bbcode_quote');
		$postbit_factory->bbcode_parser->set_quote_vars(array('page_url' => $target_url .
			(strpos($target_url, '?') == false ? '?' : '&')));
		$show['return_node'] = $nodeid;
		$show['avatar'] = 1;

		$ignore = array();
		if (trim(vB::$vbulletin->userinfo['ignorelist']))
		{
			$ignorelist = preg_split('/( )+/', trim(vB::$vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
			foreach ($ignorelist AS $ignoreuserid)
			{
				$ignore["$ignoreuserid"] = 1;
			}
		}

		while ($post = vB::$vbulletin->db->fetch_array($posts))
		{
			if (!self::canViewPost($post, $permissions) )
			{
				continue;
			}

			if (vB::$vbulletin->options['avatarenabled'] AND vB::$vbulletin->userinfo['showavatars'] AND !$post['hascustomavatar'] AND !$post['avatarid'])
			{
				$post['hascustomavatar'] = 1;
				$post['avatarid'] = true;
				// explicity setting avatarurl to allow guests comments to show unknown avatar
				$post['avatarurl'] = $post['avatarpath'] = vB_Template_Runtime::fetchStyleVar('imgdir_misc') . '/unknown.gif';
				$post['avwidth'] = 60;
				$post['avheight'] = 60;
			}

			if ($tachyuser = in_coventry($post['userid']) AND !can_moderate($threadinfo['forumid']))
			{
				continue;
			}

			if ($post['visible'] == 1 AND !$tachyuser)
			{
				++$counter;
				if ($postorder)
				{
					$post['postcount'] = --$postcount;
				}
				else
				{
					$post['postcount'] = ++$postcount;
				}
			}

			if ($tachyuser)
			{
				$fetchtype = 'post_global_ignore';
			}
			else if ($ignore["$post[userid]"])
			{
				$fetchtype = 'post_ignore';
			}
			else if ($post['visible'] == 2)
			{
				$fetchtype = 'post_deleted';
			}
			else
			{
				$fetchtype = 'post';
			}

			// Not convinced this hook should be here, Left in for now. //
			($hook = vBulletinHook::fetch_hook('showthread_postbit_create')) ? eval($hook) : false;

			$postbit_obj = $postbit_factory->fetch_postbit($fetchtype);
			$postbit_obj->set_template_prefix('vbcms_');

			if ($fetchtype == 'post')
			{
				$postbit_obj->highlight = $replacewords;
			}

			if (!$firstpostid)
			{
				$firstpostid = $post['postid'];
			}

			$post['islastshown'] = ($post['postid'] == $lastpostid);
			$post['isfirstshown'] = ($counter == 1 AND $fetchtype == 'post' AND $post['visible'] == 1);
			$post['islastshown'] = ($post['postid'] == $lastpostid);
			$post['attachments'] = $postattach["$post[postid]"];

			$parsed_postcache = array('text' => '', 'images' => 1, 'skip' => false);

			$this_postbit = $postbit_obj->construct_postbit($post);

			$this_template = vB_Template::create('vbcms_comments_detail');
			$this_template->register('postid', $post['postid'] );
			$this_template->register('postbit', $this_postbit);
			$this_template->register('indent', $post_array[$this_key]['level']);

			$postbits .= $this_template->render();
			$LASTPOST = $post;

			if ($post['dateline'] > $finalposttime)
			{
				$finalposttime = $post['dateline']; // for ajax.
			}

			// Only show after the first post, counter isn't incremented for deleted/moderated posts

			if ($post_cachable AND $post['pagetext_html'] == '')
			{
				if (!empty($saveparsed))
				{
					$saveparsed .= ',';
				}
				$saveparsed .= "($post[postid], " . intval($threadinfo['lastpost']) . ', ' . intval($postbit_obj->post_cache['has_images']) . ", '" . vB::$vbulletin->db->escape_string($postbit_obj->post_cache['text']) . "', " . intval(STYLEID) . ", " . intval(LANGUAGEID) . ")";
			}

			if (!empty($postbit_obj->sig_cache) AND $post['userid'])
			{
				if (!empty($save_parsed_sigs))
				{
					$save_parsed_sigs .= ',';
				}
				$save_parsed_sigs .= "($post[userid], " . intval(STYLEID) . ", " . intval(LANGUAGEID) . ", '" . vB::$vbulletin->db->escape_string($postbit_obj->sig_cache['text']) . "', " . intval($postbit_obj->sig_cache['has_images']) . ")";
			}
		}

		if ($LASTPOST['dateline'] > $displayed_dateline)
		{
			$displayed_dateline = $LASTPOST['dateline'];
			if ($displayed_dateline <= $threadview)
			{
				$updatethreadcookie = true;
			}
		}

		if ($firstpostid)
		{
			$this_template->register('FIRSTPOSTID', $firstpostid );
		}

		if ($lastpostid)
		{
			$this_template->register('LASTPOSTID', $lastpostid);
		}
		// Set thread last view
		if ($displayed_dateline AND $displayed_dateline > $threadview)
		{
			mark_thread_read($threadinfo, $foruminfo, vB::$vbulletin->userinfo['userid'], $displayed_dateline);
		}

		vB::$vbulletin->db->free_result($posts);
		unset($post);
		return $postbits;
	}


	/**** This creates the editor view for entering the comments
	*
	* @return mixed
	****/
	public function getConfigEditorView()
	{
		require_once DIR . '/includes/functions_databuild.php' ;
		fetch_phrase_group('posting');
		global $show;

		require_once DIR . '/includes/functions_editor.php' ;
		require_once(DIR . '/includes/functions_file.php');

		$config = self::getConfig();

		$attachmentoption = '';
		$attachcount = 0;
		$posthash = 0;
		$poststarttime = 0;
		$postattach = 0;
		$contenttypeid = 0;
		$attachinfo = fetch_attachmentinfo($posthash, $poststarttime, $contenttypeid);

		$view->editorid = construct_edit_toolbar(
			$pagetext,
			0,
			'blog_entry',
			1,
			1,
			true,
			'fe',
			'',
			array(),
			'content'
		);

		$templater = vB_Template::create('vbcms_comments_editor');
		$templater->register('attachmentoption', $attachmentoption);
		$templater->register('checked', $checked);
		$templater->register('disablesmiliesoption', $disablesmiliesoption);
		$templater->register('editorid', $view->editorid);
		$templater->register('messagearea', $messagearea);
		$tag_delimiters = addslashes_js(vB::$vbulletin->options['tagdelimiter']);
		$templater->register('tag_delimiters', $tag_delimiters);
		$content = $templater->render();

		return $GLOBALS['messagearea'];
	}

	/**
	 * Returns a hash function for caching. Each user must have a unique
	 * view because their permissions might vary.
	 *
	 * @param integer $nodeid
	 * @return hash that will identify this content for this user
	 */
	protected static function getStaticHash($nodeid, $postid = false)
	{
		$context = new vB_Context("vbcms_comments_$nodeid" , array('nodeid' => $nodeid,
			'permissions' => vB::$vbulletin->userinfo['permissions']));
		return strval($context);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77670 $
|| ####################################################################
\*======================================================================*/
