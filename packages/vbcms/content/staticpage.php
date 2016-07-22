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
 * Article Content Controller
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 77608 $
 * @since $Date: 2013-09-17 17:34:03 -0700 (Tue, 17 Sep 2013) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Content_StaticPage extends vBCms_Content
{
	/*Properties====================================================================*/

	/**
	 * A class identifier.
	 *
	 * @var string
	 */
	protected $class = 'StaticPage';

	/**
	 * A package identifier.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';
	protected $parent_node = false;
	/*ViewInfo======================================================================*/
	/**
	 * Info required for view types.
	 *
	 * @var array
	 */
	protected $view_info = array(
		self::VIEW_LIST => 91,
		self::VIEW_PREVIEW => /* vB_Item::INFO_BASIC | vBCms_Item_Content::INFO_NODE  */ 91,
		self::VIEW_PAGE =>  91,
		self::VIEW_AGGREGATE => 91
	);

	protected $config = array(
		'template' => 'vbcms_content_staticpage_inline',
		'previewtemplate' => 'vbcms_content_staticpage_preview',
		'pagetext' => '$pagetext = \'\'<br />',
		'previewtext' => '$pagetext = \'\'<br />',
		'preview_image' => ''
	);

	protected $cache_ttl = 1440;

	protected $editing = false;

	protected $pagelist = false;

	protected $default_template = 'vbcms_content_staticpage_page';
	protected $default_previewtemplate = 'vbcms_content_staticpage_preview';
	protected $content_start_hook = 'vbcms_staticpage_defaultcontent_start';
	protected $content_end_hook = 'vbcms_staticpage_defaultcontent_end';
	protected $startpopulatehook = 'vbcms_staticpage_populate_start';
	protected $endpopulatehook = 'vbcms_staticpage_populate_end';
	protected $savestarthook = 'vbcms_staticpage_save_start';
	protected $saveendhook = 'vbcms_staticpage_save_end';



	/*Creation======================================================================*/

	/**
	 * Creates a new, empty content item to add to a node.
	 *
	 * @param vBCms_DM_Node $nodedm				- The DM of the node that the content is being created for
	 * @return int | false						- The id of the new content or false if not applicable
	 */
	public function createDefaultContent(vBCms_DM_Node $nodedm)
	{
		global $vbphrase;
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'nodeid'        => vB_Input::TYPE_UINT,
			'parentnode'    => vB_Input::TYPE_UINT,
			'parentid'      => vB_Input::TYPE_UINT,
			'pagecontent'   => vB_Input::TYPE_STR,
			));

		//We should have a nodeid, but a parentnode is even better.
		($hook = vBulletinHook::fetch_hook($this->content_start_hook)) ? eval($hook) : false;

		if ($this->parent_node)
		{
			$parentnode = $this->parent_node;
		}
		else if (vB::$vbulletin->GPC_exists['parentnode'] AND intval(vB::$vbulletin->GPC['parentnode'] ))
		{
			$parentnode = vB::$vbulletin->GPC['parentnode'];
		}
		else if (vB::$vbulletin->GPC_exists['parentid'] AND intval(vB::$vbulletin->GPC['parentid'] ))
		{
			$parentnode = vB::$vbulletin->GPC['parentid'];
		}
		else if (vB::$vbulletin->GPC_exists['nodeid'] AND intval(vB::$vbulletin->GPC['nodeid'] )
			and $record = vB::$vbulletin->db->query_first("SELECT contenttypeid, nodeid, parentnode FROM " .
			TABLE_PREFIX . "cms_node where nodeid = " . vB::$vbulletin->GPC['nodeid'] ))
		{
			$parentnode = vB_Types::instance()->getContentTypeID("vBCms_Section") == $record['contenttypeid'] ?
				$record['nodeid'] : $record['parentnode'];
		}
		else
		{
			throw (new vB_Exception_Content('No valid parent node'));
		}
		$contenttypeid = vB_Types::instance()->getContentTypeID($this->package . '_'  . $this->class);

		//Verify Permissions
		if (!vBCMS_Permissions::canUseHtml($parentnode, $contenttypeid, vB::$vbulletin->userinfo['userid']))
		{
			throw (new vB_Exception_AccessDenied());
		}

		$this->config = array('pagetext' => $vbphrase['pagetext_goes_here'],
			'previewtext' => $vbphrase['preview_goes_here_desc']);
		$nodedm->set('config', $this->config);
		$nodedm->set('contenttypeid', $contenttypeid);
		$nodedm->set('parentnode', $parentnode);
		$nodedm->set('publicpreview', 1);
		$nodedm->set('comments_enabled', 1);
		$title = new vB_Phrase('vbcms', 'new_static_page');
		$nodedm->set('description', $title);
		$nodedm->set('title', $title);
		if (!($contentid = $nodedm->save()))
		{
			throw (new vB_Exception_Content('Failed to create default content for contenttype ' . get_class($this)));
		}

		vB::$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "cms_node SET
				contentid = $contentid
			WHERE nodeid = " . $contentid
		);

		($hook = vBulletinHook::fetch_hook($this->content_end_hook)) ? eval($hook) : false;
		return $contentid;
	}


	/*Render========================================================================*/

	/**
	 * Populates a view with the expected info from a content item.
	 *
	 * @param vB_View $view
	 * @param int $viewtype
	 */
	protected function populateViewContent(vB_View $view, $viewtype = self::VIEW_PAGE, $increment_count = true)
	{
		global $show;

		if (empty($this->config))
		{
			$this->content->requireInfo(vBCms_Item_Content::INFO_CONFIG);
			 $this->config = $this->content->getConfig();
		}

		if ($_REQUEST['do']== 'apply' OR $_REQUEST['do'] == 'update' OR $_REQUEST['do'] == 'movenode')
		{
			$this->saveData($view);
			$this->content->requireInfo(vBCms_Item_Content::INFO_BASIC);
			$this->content->requireInfo(vBCms_Item_Content::INFO_CONFIG);
			$this->content->requireInfo(vBCms_Item_Content::INFO_NODE);
			$this->content->requireInfo(vBCms_Item_Content::INFO_PARENTS);
			$this->content->requireInfo(vBCms_Item_Content::INFO_NAVIGATION);
			$this->config = $this->content->getConfig();
		}
		else
		{
			$this->content->requireInfo(vBCms_Item_Content::INFO_BASIC);
			$this->content->requireInfo(vBCms_Item_Content::INFO_NODE);
			$this->content->requireInfo(vBCms_Item_Content::INFO_PARENTS);
			$this->content->requireInfo(vBCms_Item_Content::INFO_NAVIGATION);
		}

		if ($_REQUEST['goto'] == 'newcomment')
		{
			require_once DIR . '/includes/functions_bigthree.php' ;

			$record = vB::$vbulletin->db->query_first("SELECT associatedthreadid
				FROM " . TABLE_PREFIX . "cms_nodeinfo WHERE nodeid = " . $this->getNodeId());
			$threadid = $record['associatedthreadid'];
			$threadinfo = verify_id('thread', $threadid, 1, 1);

			if (vB::$vbulletin->options['threadmarking'] AND vB::$vbulletin->userinfo['userid'])
			{
				vB::$vbulletin->userinfo['lastvisit'] = max($threadinfo['threadread'], $threadinfo['forumread'], TIMENOW - (vB::$vbulletin->options['markinglimit'] * 86400));
			}
			else if (($tview = intval(fetch_bbarray_cookie('thread_lastview', $threadid))) > vB::$vbulletin->userinfo['lastvisit'])
			{
				vB::$vbulletin->userinfo['lastvisit'] = $tview;
			}

			$coventry = fetch_coventry('string');
			$posts = vB::$vbulletin->db->query_first("
				SELECT MIN(postid) AS postid
				FROM " . TABLE_PREFIX . "post
				WHERE threadid = $threadinfo[threadid]
					AND visible = 1
					AND dateline > " . intval(vB::$vbulletin->userinfo['lastvisit']) . "
					". ($coventry ? "AND userid NOT IN ($coventry)" : "") . "
				LIMIT 1
			");

			$target_url = vB_Router::getURL();
			$join_char = strpos($target_url,'?') ? '&amp;' : '?';
			if ($posts['postid'])
			{
				exec_header_redirect($target_url . $join_char . "commentid=" . $posts['postid'] . "#post$posts[postid]");
			}
			else
			{
				exec_header_redirect($target_url . $join_char . "commentid=" . $threadinfo['lastpostid'] . "#post$threadinfo[lastpostid]");
			}
		}

		if ($_REQUEST['commentid'])
		{
			vB::$vbulletin->input->clean_array_gpc('r', array(
				'commentid' => vB_Input::TYPE_INT,
			));
			$postinfo = verify_id('post', vB::$vbulletin->GPC['commentid'], 1, 1);
			$record = vB::$vbulletin->db->query_first("SELECT associatedthreadid
				FROM " . TABLE_PREFIX . "cms_nodeinfo WHERE nodeid = " . $this->getNodeId());
			$threadid = $record['associatedthreadid'];

			// if comment id and node id do not match, we ignore commentid
			if ($postinfo['threadid'] == $threadid)
			{
				$getpagenum = vB::$vbulletin->db->query_first("
					SELECT COUNT(*) AS posts
					FROM " . TABLE_PREFIX . "post AS post
					WHERE threadid = $threadid AND visible = 1
					AND dateline <= $postinfo[dateline]
				");
				$_REQUEST['commentpage'] = ceil($getpagenum['posts'] / 20);
			}
		}

		if ($_REQUEST['do']== 'apply' OR $_REQUEST['do'] == 'update' OR $_REQUEST['do'] == 'movenode')
		{
			$this->saveData($view);
		}

		($hook = vBulletinHook::fetch_hook($this->startpopulatehook)) ? eval($hook) : false;

		//Now we need to get the settings for turning off content. There is the "settingsforboth" flag, which says whether we even apply
		// the settings to the current page, and there are the six "show" variables.

		if ($_REQUEST['do'] == 'delete' AND $this->content->canEdit())
		{
			$dm = $this->content->getDM();
			$dm->delete();
			$this->cleanContentCache();

			// Create route to redirect the user to
			$route = new vBCms_Route_Content();
			$route->node = $this->content->getParentId();
			$_REQUEST['do'] = '';
			throw (new vB_Exception_Reroute($route));
		}

		//When we come from the link to upgrade a blog post, blog, or forum post, the
		// router puts us here.
		$settings_for = $this->content->getSettingsForboth();
		$showfor_this = (((self::VIEW_PAGE == $viewtype)
			AND ($settings_for == 0)) OR ((self::VIEW_PREVIEW == $viewtype)
			AND ($settings_for == 2))) ? 0 : 1;

		$view->showtitle = (($showfor_this AND !$this->content->getShowTitle()))? 0 : 1;
		$view->showpreviewonly = (($showfor_this AND !$this->content->getShowPreviewonly()))? 0 : 1;
		$view->showuser = (($showfor_this AND !$this->content->getShowUser()))? 0 : 1;
		$view->showupdated = (($showfor_this AND !$this->content->getShowUpdated()))? 0 : 1;
		$view->showviewcount = (($showfor_this AND !$this->content->getShowViewcount()))? 0 : 1;
		$view->showpublishdate = (($showfor_this AND !$this->content->getShowPublishdate()))? 0 : 1;
		$view->lastupdated = $this->content->getLastUpdated();
		$view->previewtext = $this->config['previewtext'];

		if ((self::VIEW_PREVIEW != $viewtype) OR !$view->showpreviewonly)
		{
			$view->pagetext = $this->config['pagetext'];
		}
		$view->previewimage = $this->config['preview_image'];
		$view->nodeid = $this->content->getNodeId();

		parent::populateViewContent($view, $viewtype);

		$segments = array('node' => vBCms_Item_Content::buildUrlSegment($this->content->getNodeId(), $this->content->getUrl()), 'action' =>'view');
		$view->page_url =  vBCms_Route_Content::getURL($segments);
		$view->pagetext = $this->config['pagetext'];

		if (self::VIEW_PAGE == $viewtype)
		{
			if ($increment_count)
			{
				//update the view count
				vB::$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX .
						"cms_nodeinfo set viewcount = viewcount + 1 where nodeid = " . $this->content->getNodeId());
			}

			//tagging code
			require_once DIR . '/includes/class_taggablecontent.php';

			$taggable = vB_Taggable_Content_Item::create(vB::$vbulletin, $this->content->getContentTypeID(),
				$this->content->getNodeId(), $this->content);

			if ($taggable)
			{
				$view->tags = $taggable->fetch_rendered_tag_list();
				$view->tag_count = $taggable->fetch_existing_tag_count();
				$view->showtags = vB::$vbulletin->options['threadtagging'];
			}
			else
			{
				$view->showtags = false;
			}

			$view->comment_count = $this->content->getReplyCount();
			$join_char = strpos($view->page_url,'?') ? '&amp;' : '?';
			$view->newcomment_url = $view->page_url . "#comments_start";
			$view->authorid = ($this->content->getUserId());
			$view->authorname = ($this->content->getUsername());
			$view->viewcount = ($this->content->getViewCount());
			$view->replycount = ($this->content->getReplyCount());
			$view->can_edit = ($this->content->canEdit() OR $this->content->canPublish()) ? 1 : 0;
			$view->parentid = $this->content->getParentId();

			//check to see if there is an associated thread.
			if ($associatedthreadid = $this->content->getAssociatedThreadId()
				and $this->content->getComments_Enabled())
			{
				$comment_block = new vBCms_Comments();
				$view->comment_block = $comment_block->getPageView($this->content->getNodeId(),
					$view->page_url);
			}

		}
		else if (self::VIEW_PREVIEW == $viewtype)
		{

			if ($showpreviewonly)
			{
				$view->previewtext = isset($this->config['previewtext']) ? $this->config['previewtext'] :
					 substr(strip_tags( $this->config['pagetext'], '<br />'), 0, $this->config['previewlength']);
				$view->preview_chopped = 1;

			}
			else
			{
				$view->previewtext = $view->pagetext;
			}

			$segments = array('node' => $this->content->getNodeId() . '-' . $this->content->getUrl(), 'action' =>'edit');
			$view->edit_url =  vBCms_Route_Content::getURL($segments) ;
			$view->read_more_phrase = new vB_Phrase('vbcms', 'read_more');
			$view->parenttitle = $this->content->getParentTitle();
			$view->pagetext = $pagetext;
			$view->setpublish = $view->published = $this->content->getPublished();
			$view->publishdate = $this->content->getPublishDateLocal();
			$view->comment_count = $this->content->getReplyCount();
			$join_char = strpos($view->page_url,'?') ? '&amp;' : '?';
			$view->newcomment_url = $view->page_url . "#comments_start";
			$view->authorid = ($this->content->getUserId());
			$view->authorname = ($this->content->getUsername());
			$view->viewcount = ($this->content->getViewCount());
			$view->replycount = ($this->content->getReplyCount());
			$view->can_edit = ($this->content->canEdit() OR $this->content->canPublish()) ? 1 : 0;
			$view->parentid = $this->content->getParentId();

			if (($associatedthreadid = $this->content->getAssociatedThreadId())
				AND $this->content->getComments_Enabled() AND intval($this->content->getReplyCount()) > 0)
			{
				$view->echo_comments = 1;
				$view->comment_count = $this->content->getReplyCount();
			}
			else
			{
				$view->echo_comments = 0;
				$view->comment_count = 0;
			}
		}

		$view->setpublish = $this->content->getSetPublish();
		$view->publishdate = $this->content->getPublishDate();
		$view->published = $this->content->getPublished() ?
			1 : 0;

		$view->publishdatelocal = vbdate(vB::$vbulletin->options['dateformat'], $this->content->getPublishDate());
		$view->publishtimelocal = vbdate( vB::$vbulletin->options['timeformat'], $this->content->getPublishDate() );

		//Get links to the author, section, and categories search pages
		//categories- this comes as an array
		$view->categories = $this->content->getCategories();
		$route_info = 'author/' . $this->content->getUserid() .
			($this->content->getUsername() != '' ? '-' . str_replace(' ', '-',
				vB_Search_Searchtools::stripHtmlTags($this->content->getUsername())) : '');
		$view->author_url = vB_Route::create('vBCms_Route_List', "$route_info/1")->getCurrentURL();

		// prepare the member action drop-down menu
		$userid = $this->content->getUserId();
		$view->memberaction_dropdown = construct_memberaction_dropdown(fetch_userinfo($userid));

		//Section
		$route_info = 'section/' .$this->content->getParentId() .
			($this->content->getParentURLSegment() != '' ? '-' . str_replace(' ', '-',
				vB_Search_Searchtools::stripHtmlTags($this->content->getParentURLSegment())) : '');
		$view->section_list_url = vB_Route::create('vBCms_Route_List', "$route_info")->getCurrentURL();
		//and the content
		$route_info = $this->content->getParentId() .
			($this->content->getParentURLSegment() != '' ? '-' . str_replace(' ', '-',
				vB_Search_Searchtools::stripHtmlTags($this->content->getParentURLSegment())) : '');
		$view->section_url = vB_Route::create('vBCms_Route_Content', $route_info)->getCurrentURL();

		$view->html_title = $this->content->getHtmlTitle();
		$view->title = $this->content->getTitle();
		$view->contenttypeid = vB_Types::instance()->getContentTypeID("vBCms_Article");
		$view->dateformat = vB::$vbulletin->options['dateformat'];
		$view->showrating = $this->content->getShowRating();
		($hook = vBulletinHook::fetch_hook($this->endpopulatehook)) ? eval($hook) : false;

		if (method_exists($this->content, 'cacheNow'))
		{
			$this->content->cacheNow();
		}
		return $view;
	}

	/**** This saves the data from the form. It takes no parameters and returns no values
	 *
	 ****/
	protected function saveData($view)
	{

		//confirm that the user has edit rights
		if (!(($this->content->canEdit() OR ($this->getUserId() == vB::$vbulletin->userinfo['userid']))
				AND $this->content->canUseHtml(vB::$vbulletin->userinfo['userid']))
			AND !$this->content->canPublish())
		{
			return new vB_Phrase('global', 'no_edit_permissions');
		}

		$this->config = $this->content->getConfig();
		require_once DIR . '/includes/functions.php';
		// collect error messages
		$errors = array();
		//We don't need to change the cache lifetime for static page, our descendants will
		vB::$vbulletin->input->clean_array_gpc('p', array(
			'do'               => vB_Input::TYPE_STR,
			'message'          => vB_Input::TYPE_STR,
			'url'              => vB_Input::TYPE_NOHTML,
			'title'            => vB_Input::TYPE_NOHTML,
			'cms_node_title'            => vB_Input::TYPE_NOHTML,
			'setpublish'       => vB_Input::TYPE_UINT,
			'html_title'       => vB_Input::TYPE_NOHTML,
			'publicpreview'    => vB_Input::TYPE_UINT,
			'new_parentid'     => vB_Input::TYPE_UINT,
			'cache_ttl'		    => vB_Input::TYPE_UINT,
			'comments_enabled' => vB_Input::TYPE_UINT,
			'parseurl'         => vB_Input::TYPE_BOOL,
			'posthash'         => vB_Input::TYPE_NOHTML,
			'htmlstate'        => vB_Input::TYPE_NOHTML,
			'pagetext' 		    => vB_Input::TYPE_STR,
			'previewtext' 	    => vB_Input::TYPE_STR,
			'previewtemplate'  => vB_Input::TYPE_STR,
			'template'         => vB_Input::TYPE_STR,
			'preview_image' 	 => vB_Input::TYPE_STR,
		));
		($hook = vBulletinHook::fetch_hook($this->savestarthook)) ? eval($hook) : false;
		$dm = $this->content->getDM();

		if ($this->content->canEdit() AND $this->content->canUseHtml(vB::$vbulletin->userinfo['userid']))
		{

			$html_title = vB::$vbulletin->GPC['html_title'];

			if (vB::$vbulletin->GPC_exists['pagetext'])
			{
				$this->config['pagetext'] = vB::$vbulletin->GPC['pagetext'];
			}

			if (vB::$vbulletin->GPC_exists['preview_image'])
			{
				$this->config['preview_image'] = vB::$vbulletin->GPC['preview_image'];

			}

			if (vB::$vbulletin->GPC_exists['previewtext'])
			{
				$this->config['previewtext'] = vB::$vbulletin->GPC['previewtext'];
			}

			//make sure we have preview text.
			if (empty($this->config['previewtext']) AND !empty($this->config['pagetext']))
			{
				$this->config['previewtext'] =
				 htmlspecialchars_uni(fetch_censored_text(
					trim(fetch_trimmed_title(strip_bbcode($this->config['pagetext'],
					true, false, false), vB::$vbulletin->options['default_cms_previewlength']))));
			}

			if (vB::$vbulletin->GPC_exists['previewtemplate'])
			{
				$this->config['previewtemplate'] = vB::$vbulletin->GPC['previewtemplate'];

			}

			if (vB::$vbulletin->GPC_exists['template'])
			{
				$this->config['template'] = vB::$vbulletin->GPC['template'];

			}

			//For the descendants, at least phpeval
			if (vB::$vbulletin->GPC_exists['cache_ttl'])
			{
				$this->config['cache_ttl'] = vB::$vbulletin->GPC['cache_ttl'];

			}

			if (count($this->config))
			{
				$dm->set('config', $this->config);
				$this->content->setConfig($this->config);
			}

			if (vB::$vbulletin->GPC_exists['cms_node_title'])
			{
				$title = vB::$vbulletin->GPC['cms_node_title'];
				$dm->set('title', $title);
			}
		}


		if ($this->content->canPublish())
		{
			$old_sectionid = $this->content->getParentId();

			//set the values, for the dm and update the content.
			if ( vB::$vbulletin->GPC_exists['new_parentid'] AND intval(vB::$vbulletin->GPC['new_parentid']))
			{
				vBCms_ContentManager::moveSection(array($this->content->getNodeId()), vB::$vbulletin->GPC['new_parentid']);
				$new_sectionid = vB::$vbulletin->GPC['new_parentid'];
			}

			if (vB::$vbulletin->GPC_exists['publicpreview'])
			{
				$dm->set('publicpreview', vB::$vbulletin->GPC['publicpreview']);
			}

			if (vB::$vbulletin->GPC_exists['comments_enabled'])
			{
				$dm->set('comments_enabled', vB::$vbulletin->GPC['comments_enabled']);
			}

			if (vB::$vbulletin->GPC_exists['setpublish'])
			{
				$dm->set('setpublish', vB::$vbulletin->GPC['setpublish']);
			}
		}
		else
		{
			// No publish date exists, and we dont have publish
			// permission, so we need to set a default date.
			if (intval($this->content->getPublishDate()) == 0)
			{
				$dm->set('publishdate', TIMENOW);
			}
		}

		if (vB::$vbulletin->GPC_exists['html_title'])
		{
			$dm->set('html_title', vB::$vbulletin->GPC['html_title']);
		}

		if (vB::$vbulletin->GPC_exists['url'])
		{
			$dm->set('url', vB::$vbulletin->GPC['url']);
		}

		//We may have some processing to do for public preview. Let's see if comments
		// are enabled. We never enable them for sections, and they might be turned off globally.
		vB::$vbulletin->input->clean_array_gpc('r', array(
			'publicpreview' => TYPE_UINT));

		$dm->set('contentid', $this->content->getNodeId());
		$success = $dm->saveFromForm($this->content->getNodeId());
		//Make sure that items we render don't decide to save random data.
		$_POST['do'] = '';
		$_REQUEST['do'] = '';
		vB::$vbulletin->GPC['do'] = '';
		vB::$vbulletin->GPC_exists['do'] = false;

		if ($dm->hasErrors())
		{
			$fieldnames = array(
				'html_title' => new vB_Phrase('vbcms', 'html_title'),
				'title' => new vB_Phrase('global', 'title')
			);

			$view->errors = $dm->getErrors(array_keys($fieldnames));
			$view->error_summary = self::getErrorSummary($dm->getErrors(array_keys($fieldnames)), $fieldnames);
			$view->status = $view->error_view->title;
		}
		else
		{
			$view->status = new vB_Phrase('vbcms', 'content_saved');
			$this->cleanContentCache();
		}
		($hook = vBulletinHook::fetch_hook($this->saveendhook)) ? eval($hook) : false;

		//invalidate the appropriate cache entries.
		vB_Cache::instance()->event(array_merge($this->content->getCacheEvents(),array('sections_updated','articles_updated',
			$this->content->getContentCacheEvent())));

		//Make sure comment count will be updated when a comment is posted
		if ($threadid = $this->content->getAssociatedThreadId())
		{
			vB_Cache::instance()->eventPurge("cms_comments_change_$threadid");
		}

		vB_Cache::instance()->eventPurge('cms_comments_change');
		vB_Cache::instance()->eventPurge('cms_comments_add_' . $this->content->getNodeId());
		vB_Cache::instance()->cleanNow();

		$view->html_title = $html_title;
		$view->title = $title;

		$this->content->reset();
		$this->changed = true;

		//reset the required information
		$this->content->requireInfo(vBCms_Item_Content::INFO_BASIC);
		$this->content->requireInfo(vBCms_Item_Content::INFO_CONFIG);
		$this->content->requireInfo(vBCms_Item_Content::INFO_NODE);
		$this->content->requireInfo(vBCms_Item_Content::INFO_PARENTS);
		$this->content->invalidateCached();
		if ($this->content->isValid())
		{
			//if we are caching, force the comment thread self heal to run first.
			//this prevents a bad threadid from getting into the cache, which
			//causes the self heal code to run extra times creating bad threads.
			$this->getAssociatedThread();
			$this->content->cacheNow();
		}
	}


	/**
	 * This creates the edit user interface. It returns the edit view.
	 * 
	 * @param boolean $parameters added for PHP 5.4 strict standards compliance
	 *
	 * @return view
	 * 
	 */
	public function getInlineEditBodyView($parameters = false)
	{
		global $vbphrase;
		require_once DIR . '/includes/functions_databuild.php';
		require_once DIR . '/includes/functions.php';
		fetch_phrase_group('cpcms');

		$this->config = $this->content->getConfig();
		$this->editing = true;

		//confirm that the user has edit rights
		if ( (!$this->content->canEdit()
			AND !$this->content->canUseHtml(vB::$vbulletin->userinfo['userid'])
			) OR !$this->content->canPublish() )
		{
			return $vbphrase['no_edit_permissions'];
		}

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do' => vB_Input::TYPE_STR,
		));

		if ($_REQUEST['do'] == 'delete')
		{
			$dm = $this->content->getDM();
			$dm->delete();
			$this->cleanContentCache();
			return $vbphrase['article_deleted'];
		}

		$view = new vB_View('vbcms_content_' . strtolower($this->class). '_inline');
		if ($_REQUEST['do'] == 'apply' OR $_REQUEST['do'] == 'update')
		{
			$this->saveData($view);
		}

		$this->config = $this->content->getConfig();

		require_once DIR . '/packages/vbcms/contentmanager.php';
		// Load the content item
		if (!$this->loadContent($this->getViewInfoFlags(self::VIEW_PAGE)))
		{
			throw (new vB_Exception_404());
		}

		global $show;

		$show['img_bbcode'] = $show['video_bbcode'] = true;
		// Create view

		//make sure we have template names.
		if (empty($this->config['previewtemplate']))
		{
			$this->config['previewtemplate'] = $this->default_previewtemplate;
		}

		if (empty($this->config['template']))
		{
			$this->config['template'] = $this->default_template;
		}
		// Add the content to the view
		$view = $this->populateViewContent($view, self::VIEW_PAGE, false);
		//the configuration settings
		foreach ($this->config as $key => $value)
		{
			if (in_array($key, array('preview_image', 'title')))
			{
				$view->$key = htmlspecialchars_uni($value);
			}
			else
			{
				$view->$key = $value;
			}
		}

		$view->formid = "cms_content_data";
		$view->can_edit = $this->content->canEdit() AND $this->content->canUseHtml(vB::$vbulletin->userinfo['userid']);

		$view->url = $this->content->getUrl();
		$view->type = new vB_Phrase('vbcms', 'content');
		$view->adding = 	new vB_Phrase('cpcms', 'adding_x', $vbphrase['article']);
		$view->html_title = $this->content->getHtmlTitle();
		$view->title = $this->content->getTitle();
		$view->metadata = $this->content->getMetadataEditor();
		$segments = array('node' => $this->content->getUrlSegment(),
							'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'View'));
		$view->view_url = vBCms_Route_Content::getURL($segments);
		// Add URL to submit to
		$segments = array('node' => $this->content->getUrlSegment(),
							'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'EditPage'));
		$view->submit_url = vBCms_Route_Content::getURL($segments);
		$segments = array('node' => $this->content->getUrlSegment(),
							'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'View'));
		$view->editbar = $this->content->getEditBar($view->submit_url, vBCms_Route_Content::getURL($segments), $view->formid);

		$view->publisher = $this->content->getPublishEditor($view->submit_url, $view->formid,
			true, true, $this->content->getPublicPreview(), $this->content->getComments_Enabled());
		$view->authorid = ($this->content->getUserId());
		$view->authorname = ($this->content->getUsername());
		$view->viewcount = ($this->content->getViewCount());
		$view->parentid = $this->content->getParentId();

		$view->comment_count = ($this->content->getReplyCount());
		$view->contentid = $this->content->getContentId(true);

		$view->pagetext = htmlspecialchars_uni($view->pagetext);
		$view->previewtext = htmlspecialchars_uni($view->previewtext);

		$view->show_threaded = true;
		$view->per_page = 10;
		$view->indent_per_level = 5;
		$view->max_level = 4;
		// Add form check
		$this->addPostId($view);
		return $view;
	}

	public function getPreview($parameters = false)
	{
		$this->content->requireInfo(vBCms_Item_Content::INFO_BASIC);
		$this->content->requireInfo(vBCms_Item_Content::INFO_PARENTS);
		$this->content->requireInfo(vBCms_Item_Content::INFO_CONFIG);
		$this->content->requireInfo(vBCms_Item_Content::INFO_NAVIGATION);
		$this->config = $this->content->getConfig();

		if (!$this->config['previewtemplate'])
		{
			$this->config['previewtemplate'] = $this->default_previewtemplate;
		}
		$view = new vB_View($this->config['previewtemplate']);
		$this->populateViewContent($view);
		return $view;
	}

	public function getPageView($parameters = false)
	{
		if ($parameters)
		{
			$this->setParameters($parameters);
		}

		// Load the content item
		if (!$this->loadContent($this->getViewInfoFlags(self::VIEW_PAGE))
			//If we fail the first time, try forcing a reload
			AND !$this->loadContent($this->getViewInfoFlags(self::VIEW_PAGE), true))
		{
			throw (new vB_Exception_404(new vB_Phrase('error', 'page_not_found')));
		}
		$this->content->requireInfo(vBCms_Item_Content::INFO_CONFIG);
		$this->config = $this->content->getConfig();

		if (!$this->config['template'])
		{
			$this->config['template'] = $this->default_template;
		}
		$view = new vB_View($this->config['template']);
		$this->populateViewContent($view);
		return $view;
	}


	/*** Returns the user's ability to create a new item of this type
	 *
	 * @param		int	Permissionsfrom value- where this node gets its assigned permissions.
	 *
	 * @return	bool
	 ***/
	public function canCreateHere($sectionid)
	{
		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']) )
		{
			self::getUserPerms();
		}
		return (in_array($sectionid,
					vB::$vbulletin->userinfo['permissions']['cms']['cancreate'])
			AND in_array($sectionid,
					vB::$vbulletin->userinfo['permissions']['cms']['canusehtml']));
	}
}
