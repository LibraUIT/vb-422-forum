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
 * @version $Revision: 28694 $
 * @since $Date: 2008-12-04 16:12:22 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Content_Article extends vBCms_Content
{
	/*Properties====================================================================*/

	/**
	 * A class identifier.
	 *
	 * @var string
	 */
	protected $class = 'Article';

	/**
	 * A package identifier.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	/**
	 * Controller Parameters.
	 *
	 * @var mixed
	 */
	protected $parameters = array('page' => 1);

	protected $parent_node = false;
	/*ViewInfo======================================================================*/
	protected $data_saved = false;

	/**
	 * Info required for view types.
	 *
	 * @var array
	 */
	protected $view_info = array(
		self::VIEW_LIST => vBCms_Item_Content::INFO_BASIC,
		self::VIEW_PREVIEW => /* vB_Item::INFO_BASIC | vBCms_Item_Content::INFO_NODE | vBCms_Item_Content::INFO_CONTENT */ 19,
		self::VIEW_PAGE => /* vB_Item::INFO_BASIC | vBCms_Item_Content::INFO_NODE | vBCms_Item_Content::INFO_CONTENT */ 19,
		self::VIEW_AGGREGATE => vBCms_Item_Content::INFO_NODE
	);

	protected $cache_ttl = 10;

	protected $editing = false;

	protected $rendered = false;

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
		$contentdm = new vBCms_DM_Article();

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'nodeid'        => vB_Input::TYPE_UINT,
			'parentnode'    => vB_Input::TYPE_UINT,
			'parentid'      => vB_Input::TYPE_UINT,
			'blogcommentid' => vB_Input::TYPE_UINT,
			'postid'        => vB_Input::TYPE_UINT,
			'blogid'        => TYPE_UINT
			));

		//We should have a nodeid, but a parentnode is even better.
		($hook = vBulletinHook::fetch_hook('vbcms_article_defaultcontent_start')) ? eval($hook) : false;

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

		$nodedm->set('contenttypeid', vB_Types::instance()->getContentTypeID("vBCms_Article"));
		$nodedm->set('parentnode', $parentnode);
		$nodedm->set('publicpreview', 1);
		$nodedm->set('comments_enabled', 1);

		if (vB::$vbulletin->GPC_exists['blogcommentid'] OR vB::$vbulletin->GPC_exists['blogid'])
		{
			$this->createFromBlogPost($nodedm);
		}
		else if (vB::$vbulletin->GPC_exists['postid'])
		{
			$this->createFromForumPost($nodedm);
		}
		else
		{
			$title = new vB_Phrase('vbcms', 'new_article');
			$nodedm->set('description', '');
			$nodedm->set('pagetext', '');
			$nodedm->set('title', '');
		}

		if (!($contentid = $nodedm->save()))
		{
			throw (new vB_Exception_Content('Failed to create default content for contenttype ' . get_class($this)));
		}
		($hook = vBulletinHook::fetch_hook('vbcms_article_defaultcontent_end')) ? eval($hook) : false;

		//at this point we have saved the data. We need to get the content id, which isn't easily available.
		if ($record = vB::$vbulletin->db->query_first("SELECT contentid FROM " . TABLE_PREFIX . "cms_node WHERE nodeid = $contentid"))
		{

			//Now there may be attachments to duplicate
			if (vB::$vbulletin->GPC_exists['blogid'])
			{
				$this->duplicateAttachments($nodedm, vB_Types::instance()->getContentTypeID('vBBlog_BlogEntry'), vB::$vbulletin->GPC['blogid'], $record['contentid'], $contentid);
			}
			else if (vB::$vbulletin->GPC_exists['postid'])
			{
				$this->duplicateAttachments($nodedm, vB_Types::instance()->getContentTypeID('vBForum_Post'), vB::$vbulletin->GPC['postid'], $record['contentid'], $contentid);
			}
		}

		return $contentid;
	}

	/*** This function sets the parent node for creating a new article
	****/
	public function setParentNode($parentnode)
	{

		$this->parent_node = $parentnode;
	}

	/*Configuration=================================================================*/

	/**
	 * Assigns a parameter value.
	 *
	 * @param string $parameter					- The key name of the parameter to set
	 * @param mixed $value						- The value to set it to
	 */
	protected function assignParameter($parameter, $value)
	{
		if ($parameter == 'page')
		{
			$this->parameters['page'] = max(intval($value), 1);
		}
		else
		{
			parent::assignParameter($parameter, $value);
		}
	}


	protected function createFromBlogPost($nodedm)
	{
		global $vbphrase;
		//make sure we are only called once;

		//let's confirm the rights
		$title = new vB_Phrase('vbcms', 'new_article');

		$sql = "
			SELECT
				starter.pagetext, starter.bloguserid, starter.title, blog.title AS blogtitle, blog.userid AS poststarter,
				txt.userid, txt.username, blog.postedby_username AS author, blog.blogid, txt.blogtextid, txt.dateline AS post_posted,
				blog.dateline AS post_started
			FROM " . TABLE_PREFIX . "blog_text AS starter
			INNER JOIN " . TABLE_PREFIX . "blog AS blog ON blog.firstblogtextid = starter.blogtextid
			INNER JOIN " . TABLE_PREFIX . "blog_text AS txt ";


		if (vB::$vbulletin->GPC_exists['blogcommentid'] )
		{
			$sql .= " ON blog.blogid = txt.blogid
		WHERE txt.blogtextid = "	. vB::$vbulletin->GPC['blogcommentid'];
		}
		else if (vB::$vbulletin->GPC_exists['blogid'])
		{
			$sql .= " ON blog.firstblogtextid = txt.blogtextid
		WHERE blog.blogid = " . vB::$vbulletin->GPC['blogid'];
		}
		else
		{
			return false;
		}

		if ($record = vB::$vbulletin->db->query_first($sql))
		{
			$nodedm->set('description', (strlen($record['title']) > 10 ? $record['title'] : $tagline));
			$nodedm->set('userid', $record['userid']);
			$nodedm->set('title', $record['title']);
			$nodedm->set('html_title', $record['title']);
			$nodedm->set('url', vB_Friendly_Url::clean_entities(htmlspecialchars_decode($record['title'])));
			$nodedm->set('contenttypeid', vB_Types::instance()->getContentTypeID("vBCms_Article"));
			$nodedm->info['skip_verify_pagetext'] = true;
			$nodedm->set('pagetext', $record['pagetext']);
			$nodedm->set('blogid', $record['blogid'] );
			$nodedm->set('posttitle', $record['blogtitle'] );
			$nodedm->set('poststarter', $record['poststarter'] );
			$nodedm->set('postauthor', $record['username'] );
			$nodedm->set('blogpostid', $record['blogtextid'] );
			$nodedm->set('post_started', $record['post_started'] );
			$nodedm->set('post_posted', $record['post_posted'] );
			($hook = vBulletinHook::fetch_hook('vbcms_articleblog_presave')) ? eval($hook) : false;

		}
	}

	protected function createFromForumPost($nodedm)
	{
		global $vbphrase;
		//make sure we are only called once;

		//let's confirm the rights

		if (vB::$vbulletin->GPC_exists['postid'] )
		{
			$sql = "
				SELECT
					post.pagetext, post.userid, post.title, post.username, post.threadid, post.dateline AS post_posted,
					thread.title AS threadtitle, thread.postuserid AS poststarter, thread.postusername AS author,
					thread.dateline AS post_started
				FROM " . TABLE_PREFIX . "post AS post
				INNER JOIN " . TABLE_PREFIX . "thread AS thread ON thread.threadid = post.threadid
				WHERE
					post.postid = " . vB::$vbulletin->GPC['postid'] . "
			";
		}
		else
		{
			return false;
		}

		if ($record = vB::$vbulletin->db->query_first($sql))
		{
			$title = strlen($record['title']) > 0 ? htmlspecialchars_decode($record['title']) : htmlspecialchars_decode($record['threadtitle']);

			$nodedm->set('description', $title);
			$nodedm->set('userid', $record['userid']);
			$nodedm->set('title', $title);
			$nodedm->set('html_title', $title);
			$url = vB_Friendly_Url::clean_entities(htmlspecialchars_decode($title));
			$nodedm->set('url', $url);
			$nodedm->set('contenttypeid', vB_Types::instance()->getContentTypeID("vBCms_Article"));
			$nodedm->info['skip_verify_pagetext'] = true;
			$nodedm->set('pagetext', $record['pagetext']);
			$nodedm->set('threadid', $record['threadid']);
			$nodedm->set('posttitle', $record['threadtitle'] );
			$nodedm->set('postauthor', $record['author'] );
			$nodedm->set('poststarter', $record['poststarter'] );
			$nodedm->set('postid', vB::$vbulletin->GPC['postid'] );
			$nodedm->set('post_started', $record['post_started'] );
			$nodedm->set('post_posted', $record['post_posted'] );
			($hook = vBulletinHook::fetch_hook('vbcms_articlepost_presave')) ? eval($hook) : false;
		}
	}

	protected function duplicateAttachments($nodedm, $sourceContenttypeid, $sourceContentid, $newcontentid, $newnodeid)
	{
		$attachids = array();
		if (! intval($sourceContentid) OR !intval($sourceContenttypeid))
		{
			return false;
		}

		$attachments = vB::$vbulletin->db->query_read("
			SELECT
				a.attachmentid, a.filedataid, a.state, a.filename, a.settings
			FROM " . TABLE_PREFIX . "attachment AS a
			WHERE
				a.contenttypeid = " . intval($sourceContenttypeid) . "
					AND
				a.contentid = " . intval($sourceContentid) . "
		");
		while ($attach = vB::$vbulletin->db->fetch_array($attachments))
		{
			vB::$vbulletin->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "attachment
					(contenttypeid, userid, dateline, filedataid, state, filename, settings, posthash, contentid)
				VALUES
					(
						" . vB_Types::instance()->getContentTypeID("vBCms_Article") . ",
						" . vB::$vbulletin->userinfo['userid'] . ",
						" . TIMENOW . ",
						$attach[filedataid],
						'" . $attach['state'] . "',
						'" . vB::$vbulletin->db->escape_string($attach['filename']) . "',
						'" . vB::$vbulletin->db->escape_string($attach['settings']) . "',
						'', $newnodeid)");
			$attachids[$attach['attachmentid']] = $attachmentid = vB::$vbulletin->db->insert_id();
		}

		if (!empty($attachids))
		{
			$search = $replace = array();
			preg_match_all('#\[attach(?:=(right|left|config))?\](\d+)\[/attach\]#i', $nodedm->getField('pagetext'), $matches);
			foreach($matches[2] AS $key => $attachmentid)
			{
				if ($attachids[$attachmentid])
				{
					$align = $matches[1]["$key"];
					$search[] = '#\[attach' . (!empty($align) ? '=' . $align : '') . '\](' . $attachmentid . ')\[/attach\]#i';
					$replace[] = '[attach' . (!empty($align) ? '=' . $align : '') . ']' . $attachids[$attachmentid] . '[/attach]';
				}
			}
			if (!empty($search))
			{
				$pagetext = preg_replace($search, $replace, $nodedm->getField('pagetext'));
				$nodedm->set('pagetext', $pagetext);
				$nodedm->set('nodeid', $newnodeid);
				$nodedm->setExisting($newnodeid);
				$nodedm->set('contentid', $newcontentid);
				$nodedm->save();
			}
		}
	}

	public function getPageTitle()
	{
		if (!$this->rendered)
		{
			$this->rendered = $this->content->getRendered($this->data_saved);
		}


		if ((count($this->rendered['pagelist']) > 1) AND (intval($this->parameters['page']) > 1)
			AND isset($this->rendered['pagelist'][$this->parameters['page']]))
		{
			return $this->content->getHtmlTitle() . '--' . $this->rendered['pagelist'][$this->parameters['page']];
		}
		return $this->content->getHtmlTitle();

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

		$this->content->requireInfo(vBCms_Item_Content::INFO_BASIC);
		$this->content->requireInfo(vBCms_Item_Content::INFO_CONTENT);
		$this->content->requireInfo(vBCms_Item_Content::INFO_CONFIG);
		$this->content->requireInfo(vBCms_Item_Content::INFO_NODE);
		$this->content->requireInfo(vBCms_Item_Content::INFO_PARENTS);

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
				exec_header_redirect($target_url . $join_char . "postid=" . $posts['postid'] . "#comments_$posts[postid]");
			}
			else
			{
				exec_header_redirect($target_url . $join_char . "postid=" . $threadinfo['lastpostid'] . "#comments_$threadinfo[lastpostid]");
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

		($hook = vBulletinHook::fetch_hook('vbcms_article_populate_start')) ? eval($hook) : false;

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
		$showpreviewonly = (($showfor_this AND !$this->content->getShowPreviewonly()))? 0 : 1;

		parent::populateViewContent($view, $viewtype);

		$segments = array('node' => vBCms_Item_Content::buildUrlSegment($this->content->getNodeId(), $this->content->getUrl()), 'action' =>'view');
		$view->page_url =  vBCms_Route_Content::getURL($segments, ($this->parameters['page'] > 1 ? array($this->parameters['page']) : array()));

		if ($this->editing)
		{
			$view->pagetext = $this->content->getPageText();
			if (defined('VB_API') AND VB_API === true)
			{
				$view->message_plain = build_message_plain($this->pagetext);
				$view->message_bbcode = $this->pagetext;
			}
			$view->message = $view->pagetext;
		}
		else
		{
			if (!$this->rendered)
			{
				$this->rendered = $this->content->getRendered($this->data_saved);
			}

			$view->pagetext = $this->rendered['pages'][$this->parameters['page']];

			if (defined('VB_API') AND VB_API === true)
			{
				$view->message_plain = build_message_plain($this->content->getPageText());
				$view->message_bbcode = $this->content->getPageText();
			}
			$view->message = $view->pagetext;

			if ($this->content->canDownload())
			{
				$view->attachments = $this->rendered['attachments'];
				$view->showattachments = empty($this->rendered['viewinfo']) ? 0 : 1 ;


				if (!empty($this->rendered['viewinfo']))
				{
					foreach ($this->rendered['viewinfo'] as $key => $viewbit)
					{
						$view->$key = $viewbit;
					}
				}
			}

			$view->parenttitle = $this->content->getParentTitle();

			$view->showattachments = empty($view->attachments) ? 0 : 1 ;

			if (!empty($viewinfo))
			{
				foreach ($viewinfo as $key => $viewbit)
				{
					$view->$key = $viewbit;
				}
			}

			$view->htmlstate = $this->content->getHtmlState();
			$view->pagelist = $this->rendered['pagelist'];
			$view->nodesegment = $this->content->getUrlSegment();
			$view->current_page = $this->parameters['page'];
			if ($this->content->canDownload())
			{
				$show['lightbox'] = (vB::$vbulletin->options['lightboxenabled'] AND vB::$vbulletin->options['usepopups']);
			}
		}

		// Only break pages for the page view
		if ((self::VIEW_PAGE == $viewtype) OR (self::VIEW_PREVIEW == $viewtype))
		{
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
					$this->content->getContentId(), $this->content);
				$view->tags = $taggable->fetch_rendered_tag_list();
				$view->tag_count = $taggable->fetch_existing_tag_count();
				$view->showtags = vB::$vbulletin->options['threadtagging'];

				// promoted threadid
				if ($promoted_threadid = $this->content->getThreadId())
				{
					if ($promoted_threadid = verify_id('thread', $promoted_threadid, false))
					{
						// get threadinfo
						$threadinfo = fetch_threadinfo($promoted_threadid);
						$forumperms = fetch_permissions($threadinfo['forumid']);
						$view->threadinfo = $threadinfo;
						// check permissions
						if ($threadinfo['visible'] != 1)
						{
							$promoted_threadid = false;
						}
						else if (!($forumperms & vB::$vbulletin->bf_ugp_forumpermissions['canview'])
							OR !($forumperms & vB::$vbulletin->bf_ugp_forumpermissions['canviewthreads'])
							OR (!($forumperms & vB::$vbulletin->bf_ugp_forumpermissions['canviewothers'])
								AND ($threadinfo['postuserid'] != vB::$vbulletin->userinfo['userid'] OR vB::$vbulletin->userinfo['userid'] == 0)
							))
						{
							$promoted_threadid = false;
						}
						else
						{
							// check forum password
							$foruminfo = fetch_foruminfo($threadinfo['forumid']);

							if ($foruminfo['password'] AND !verify_forum_password($foruminfo['forumid'], $foruminfo['password'], false))
							{
								$promoted_threadid = false;
							}
						}

						$view->promoted_threadid = $promoted_threadid;
					}
				}

				// get pagelist for navigation
				$view->postitle = $this->content->getPostTitle();
				$view->poststarter = $this->content->getPostStarter();
				$view->postauthor = $this->content->getPostAuthor();
				$view->postid = ($this->content->getPostId());
				$view->threadid = $this->content->getThreadId();
				$view->blogpostid = ($this->content->getBlogPostId());
				$view->post_started = ($this->content->getPostStarted());
				$view->post_posted = ($this->content->getPostPosted());
				$view->promoted_blogid = $this->content->getBlogId();

				//make links to original post and/or blog if appropriate
				if ($view->promoted_blogid)
				{
					$view->blog_url = fetch_seo_url('blog',
						array('userid' => $this->content->getPostStarter(),
						'blog_title' => $this->content->getPostTitle()));
				}
				else if ($view->threadid)
				{
					$threadinfo = fetch_threadinfo($view->threadid);

					if ($threadinfo)
					{
						$post_url = fetch_seo_url('thread', $threadinfo);
						$post_url .= (strpos($post_url, '?' ) ? '&amp;p=' :  '?p=') . $view->postid .
							'#post' . $view->postid;
						$view->post_url = $post_url;
					}
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

				$fb_enabled = is_facebookenabled(); // Load FB.

				// display the like button for this article?
				if ($this->content->getPublished())
				{
					$view->fblikebutton = construct_fblikebutton();
				}

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
					$view->previewtext = $this->content->getPreviewText(false, false);
					$view->preview_chopped = 1;
				}
				else
				{
					$view->previewtext = $view->pagetext;

					if (count($view->pagelist) > 1)
					{
						$view->preview_chopped = 1;
					}
				}

				$segments = array('node' => $this->content->getNodeId() . '-' . $this->content->getUrl(), 'action' =>'edit');
				$view->edit_url =  vBCms_Route_Content::getURL($segments) ;
				$view->read_more_phrase = new vB_Phrase('vbcms', 'read_more');
				$view->parenttitle = $this->content->getParentTitle();
				$view->pagetext = $pagetext;
				$view->setpublish = $view->published = $this->content->getPublished();
				$view->publishdateline = $this->content->getPublishDate();
				$view->publishdate = $this->content->getPublishDateLocal();
				$view->promoted_blogid = $this->content->getBlogId();
				$view->comment_count = $this->content->getReplyCount();
				$join_char = strpos($view->page_url,'?') ? '&amp;' : '?';
				$view->newcomment_url = $view->page_url . "#comments_start";
				$view->authorid = ($this->content->getUserId());
				$view->authorname = ($this->content->getUsername());
				$view->viewcount = ($this->content->getViewCount());
				$view->replycount = ($this->content->getReplyCount());
				$view->postid = ($this->content->getPostId());
				$view->blogpostid = $this->content->getBlogPostId();
				$view->can_edit = ($this->content->canEdit() OR $this->content->canPublish()) ? 1 : 0;
				$view->parentid = $this->content->getParentId();
				$view->post_started = $this->content->getPostStarted();
				$view->post_posted = $this->content->getPostPosted();

				//We need to check rights. If this user doesn't have download rights we hide the image.
				if ($this->content->canDownload())
				{
					if ($view->previewimage= $this->content->getPreviewImage())
					{
						$view->imagewidth= $this->content->getImageWidth();
						$view->imageheight= $this->content->getImageHeight();
					}
					if ($view->previewvideo= $this->content->getPreviewVideo())
					{
						$view->haspreviewvideo = true;
					}
				}
				else
				{
					$view->previewimage = false;
					$view->previewvideo = false;
				}

				if ($view->previewimage)
				{
					$attachmentid = 0;

					$apurlinfo = vB::$vbulletin->input->parse_url($view->previewimage);

					if ($apurlinfo['scheme'])
					{ // Pre-process external url for attachment check
						$bburlinfo = vB::$vbulletin->input->parse_url(vB::$vbulletin->options['bburl']);

						if ($apurlinfo['host'] == $bburlinfo['host'])
						{ // Link belongs to our domain so strip out bb path
							$apurlinfo['path'] = str_replace($bburlinfo['path'].'/','',$apurlinfo['path']);
						}
					}

					if ($apurlinfo['path'] == 'attachment.php' AND substr($apurlinfo['query'],0,12) == 'attachmentid')
					{
						$end = strpos($apurlinfo['query'],'&',13);
						$end = ($end ? $end : strlen($apurlinfo['query']));
						$attachmentid = intval(substr($apurlinfo['query'],13,$end-13));
					}

					if ($attachmentid)
					{
						require_once(DIR . '/packages/vbattach/attach.php');
						$attach = new vB_Attach_Display_Content(vB::$vbulletin,'vBCms_Article');
						$content_attachments = $attach->fetch_postattach(0,$this->content->getNodeId());

						$attachment = $content_attachments["$attachmentid"];

						if ($settings = @unserialize($attachment['settings']))
						{
							$view->attachment_settings = array(
								'title' => ($settings['title'] ? $settings['title'] : ''),
								'alt' => ($settings['description'] ? $settings['description'] : '')
							);
						}

						// VBIV-8308, Attempt to use thumbnail if preview is local attachment.
						// If this fails then nothing is lost, we just use the original image.
						if (vB::$vbulletin->options['cms_preview_thumb']
							AND vB::$vbulletin->options['attachthumbs']			// VBIV-12762
							AND vB::$vbulletin->options['viewattachedimages']	// Thumbnails need to be enabled and in use.
						)
						{ // Valid local attachment path.
							$view->previewimage .= '&amp;thumb=1';
						}

						$view->previewimage .= '&amp;stc=1'; //VBIV-9909
					}
				}

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
		}

		//If this was promoted from a blog or post, we need to verify the permissions.
		if (intval($view->blogpostid))
		{
			$view->can_view_post =
				(!(vB::$vbulletin->userinfo['permissions']['vbblog_general_permissions'] & vB::$vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers'])) ?
				0 : 1 ;
		}
		else if (intval($view->postid))
		{
			$user = new vB_Legacy_CurrentUser();
			if ($post = vB_Legacy_Post::create_from_id($view->postid))
			{
				$view->can_view_post = $post->can_view($user) ? 1 : 0;
			}
		}

		$view->poststarter = array('userid' => $this->content->getPostStarter(),
			'username' => $this->content->getPostAuthor());
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
		$userId = $this->content->getUserId();
		$view->memberaction_dropdown = construct_memberaction_dropdown(fetch_userinfo($userId));

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

		($hook = vBulletinHook::fetch_hook('vbcms_article_populate_end')) ? eval($hook) : false;

		$this->content->cacheNow();
		return $view;
	}


	private function getAttachData($attachmentid, &$dm, &$bbcodesearch)
	{
		$imgtypes = array('gif', 'jpg', 'jpeg', 'jpe', 'png', 'bmp');
		$attachmentid = intval($attachmentid);
		if ($attachmentid)
		{
			// removed $image_location, it's not beeing used
			$record = vB::$vbulletin->db->query_first("
				SELECT
						data.thumbnail_width, data.thumbnail_height, data.width, data.height, data.extension,
						attach.settings
						FROM " . TABLE_PREFIX . "attachment AS attach
						INNER JOIN "  . TABLE_PREFIX . "filedata AS data ON (data.filedataid = attach.filedataid)
						WHERE
						attach.attachmentid = $attachmentid
						");
			if ($record AND (in_array($record['extension'], $imgtypes)))
			{
				$image_template = new vB_View('vbcms_image_src');
				$settings = unserialize($record['settings']);
				$image_template->attachmentid = $attachmentid;
				$image_template->contenttypeid = vB_Types::instance()->getContentTypeID("vBCms_Article");
				$image_template->altattribute = empty($settings['description']) ? ' ' : $settings['description'];
				$image_template->title = empty($settings['title']) ? ' ' : $settings['title'] ;
				$image_template->contenttypeid = vB_Types::instance()->getContentTypeID("vBCms_Article");
				$image_tag = $image_template->render();

				// parse the src attribute value from the image tag
				// since that is what we want to store in the db
				if (preg_match('/src=\"([^"]*)\"/', $image_tag, $matches) && isset($matches[1]))
				{
					$dm->set('previewimage', $matches[1]);
					$bbcodesearch[] = substr($pagetext, $i, $j + 9);
				}

				if ($record['thumbnail_width'] AND $record['thumbnail_height'])
				{
					$dm->set('imagewidth', $record['thumbnail_width']);
					$dm->set('imageheight', $record['thumbnail_height']);
				}
				else
				{
					$dm->set('imagewidth', $record['width']);
					$dm->set('imageheight', $record['height']);
				}
			}
			return true;
		}
		return false;
	}

	/**** This saves the data from the form. It takes no parameters and returns no values
	 *
	 ****/
	protected function saveData($view)
	{
		if ($this->data_saved)
		{
			return true;
		}
		$this->data_saved = true;

		if (!$this->content->canEdit() AND !$this->content->canPublish() )
		{
			return $vbphrase['no_edit_permissions'];
		}

		require_once DIR . '/includes/functions.php';
		// collect error messages
		$errors = array();
		vB::$vbulletin->input->clean_array_gpc('p', array(
			'do'               => vB_Input::TYPE_STR,
			'cms_node_title'   => vB_Input::TYPE_STR,
			'cms_node_url'     => vB_Input::TYPE_STR,
			'message'          => vB_Input::TYPE_STR,
			'url'              => vB_Input::TYPE_NOHTML,
			'title'            => vB_Input::TYPE_NOHTML,
			'setpublish'       => vB_Input::TYPE_UINT,
			'publishdate'      => vB_Input::TYPE_UINT,
			'html_title'       => vB_Input::TYPE_NOHTML,
			'publicpreview'    => vB_Input::TYPE_UINT,
			'new_parentid'     => vB_Input::TYPE_UINT,
			'comments_enabled' => vB_Input::TYPE_UINT,
			'wysiwyg'          => vB_Input::TYPE_BOOL,
			'parseurl'         => vB_Input::TYPE_BOOL,
			'posthash'         => vB_Input::TYPE_NOHTML,
			'poststarttime'    => vB_Input::TYPE_UINT,
			'htmlstate'        => vB_Input::TYPE_NOHTML,
			'keepthread'       => vB_Input::TYPE_UINT,
			'allcomments'      => vB_Input::TYPE_UINT,
			'movethread'      => vB_Input::TYPE_UINT,
		));

		($hook = vBulletinHook::fetch_hook('vbcms_article_save_start')) ? eval($hook) : false;
		$dm = $this->content->getDM();
		$dm->set('contentid', $this->content->getId());

		if ($this->content->canEdit())
		{
			// get pagetext
			$pagetext = vB::$vbulletin->GPC['message'];
			$html_title = vB::$vbulletin->GPC['html_title'];
			$title = vB::$vbulletin->GPC['title'];

			// unwysiwygify the incoming data
			if (vB::$vbulletin->GPC['wysiwyg'])
			{
				require_once DIR . '/includes/class_wysiwygparser.php';
				$html_parser = new vB_WysiwygHtmlParser(vB::$vbulletin);
				$pagetext = $html_parser->parse_wysiwyg_html_to_bbcode($pagetext);
			}

			$dm->info['parseurl'] = true;
			$dm->set('pagetext', $pagetext);

			if ($title)
			{
				$dm->set('title', $pagetext);
			}

			$bbcodesearch = array();

			$video_location = stripos($pagetext, '[video');

			$found_image = false;
			// populate the preview image field with [img] if we can find one
			if (($i = stripos($pagetext, '[IMG]')) !== false and ($j = stripos($pagetext, '[/IMG]')) AND $j > $i)
			{
				$previewimage = htmlspecialchars_uni(substr($pagetext, $i+5, $j - $i - 5));
				$image_location = $i;
				if ($size = @getimagesize($previewimage))
				{
					$dm->set('imagewidth', $size[0]);
					$dm->set('imageheight', $size[1]);
				}
				$dm->set('previewimage', $previewimage);
				$bbcodesearch[] = substr($pagetext, $i, $j + 6);
				$found_image = true;
			}
			// or populate the preview image field with [attachment] if we can find one
			if (!$found_image)
			{
				$i = stripos($pagetext, "[ATTACH=CONFIG]");
				$j = stripos($pagetext, '[/ATTACH]');

				if ($j !== false)
				{
					if ($i === false)
					{
						$i = stripos($pagetext, "[ATTACH]");

						if ($i !== false AND ($j > $i))
						{
							$attachmentid = substr($pagetext, $i + 8, $j - $i - 8);
							$found_image = $this->getAttachData($attachmentid, $dm, $bbcodesearch);
						}
					}
					else if ($j > $i)
					{
						$attachmentid = substr($pagetext, $i + 15, $j - $i - 15);
						$found_image = $this->getAttachData($attachmentid, $dm, $bbcodesearch);
					}

				}
			}

			if (!$found_image AND $this->content->canDownload())
			{
				require_once(DIR . '/packages/vbattach/attach.php');
				$attach = new vB_Attach_Display_Content(vB::$vbulletin, 'vBCms_Article');
				$attachments = $attach->fetch_postattach(0, $this->content->getNodeId(), $this->content->getUserId());

				if (!empty($attachments))
				{
					foreach($attachments as $attachment)
					{
						if ($attachment['hasthumbnail'])
						{
							$found_image = $this->getAttachData($attachment['attachmentid'], $dm, $bbcodesearch);
							if ($found_image)
							{
								break;
							}
						}
					}
				}
			}

			// if there are no images in the article body, make sure we unset the preview in the db
			if (!$found_image )
			{
				$dm->set('previewimage', '');
				$dm->set('imagewidth', 0);
				$dm->set('imageheight', 0);
				$image_location = intval($video_location) + 1;
			}
			$parseurl = false;
			$providers = $search = $replace = $previewvideo = array();
			($hook = vBulletinHook::fetch_hook('data_preparse_bbcode_video_start')) ? eval($hook) : false;

			// Convert video bbcode with no option
			if ((($video_location !== false) AND (intval($video_location) < intval($image_location))) OR $parseurl)
			{
				if (!$providers)
				{
					$bbcodes = vB::$db->query_read_slave("
						SELECT
						provider, url, regex_url, regex_scrape, tagoption
					FROM " . TABLE_PREFIX . "bbcode_video
					ORDER BY priority
				");
					while ($bbcode = vB::$db->fetch_array($bbcodes))
					{
						$providers["$bbcode[tagoption]"] = $bbcode;
					}
				}

				$scraped = 0;
				if (!empty($providers) AND preg_match_all('#\[video[^\]]*\](.*?)\[/video\]#si', $pagetext, $matches))
				{
					foreach ($matches[1] AS $key => $url)
					{
						$match = false;
						foreach ($providers AS $provider)
						{
							$addcaret = ($provider['regex_url'][0] != '^') ? '^' : '';
							if (preg_match('#' . $addcaret . $provider['regex_url'] . '#si', $url, $match))
							{
								break;
							}
						}
						if ($match)
						{
							if (!$provider['regex_scrape'] AND $match[1])
							{
								$previewvideo['provider'] = $provider['tagoption'];
								$previewvideo['code'] = $match[1];
								$previewvideo['url'] = $url;
								$bbcodesearch[] = $matches[0][$key];
								break;
							}
							else if ($provider['regex_scrape'] AND vB::$vbulletin->options['bbcode_video_scrape'] > 0 AND $scraped < vB::$vbulletin->options['bbcode_video_scrape'])
							{
								require_once(DIR . '/includes/functions_file.php');
								$result = fetch_body_request($url);
								if (preg_match('#' . $provider['regex_scrape'] . '#si', $result, $scrapematch))
								{
									$previewvideo['provider'] = $provider['tagoption'];
									$previewvideo['code'] = $scrapematch[1];
									$previewvideo['url'] = $url;
									$bbcodesearch[] = $matches[0][$key];
									break;
								}
								$scraped++;
							}
						}
					}
				}
			}

			$htmlstate = vB::$vbulletin->GPC_exists['htmlstate'] ? vB::$vbulletin->GPC['htmlstate']
				: $this->content->getHtmlState();

			// Try to populate previewvideo html
			if ($previewvideo)
			{
				$templater = vB_Template::create('bbcode_video');
				$templater->register('url', $previewvideo['url']);
				$templater->register('provider', $previewvideo['provider']);
				$templater->register('code', $previewvideo['code']);
				$dm->set('previewvideo', $templater->render());
				$dm->set('previewimage', '');
				$dm->set('imagewidth', 0);
				$dm->set('imageheight', 0);
				$image_location = -1;
			}
			else
			{
				$dm->set('previewvideo', '');
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

				//if we just published, we should set the associated thread after the save.
				if (vB::$vbulletin->GPC['setpublish'] AND !($this->content->getSetPublish()))
				{
					$associate_thread_now = true;
				}
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

		if (vB::$vbulletin->GPC_exists['htmlstate'])
		{
			$dm->set('htmlstate', vB::$vbulletin->GPC['htmlstate']);
		}

		if (vB::$vbulletin->GPC_exists['allcomments'])
		{
			$dm->set('allcomments', vB::$vbulletin->GPC['allcomments']);
			$curr_threadid = $this->getAssociatedThreadId();

			if ($curr_threadid > 0)
			{
				vB_Cache::instance()->eventPurge('cms_comments_change_' . $curr_threadid);
			}
		}

		if (vB::$vbulletin->GPC_exists['keepthread'] AND !$this->getAssociatedThreadId())
		{
			$dm->set('keepthread', vB::$vbulletin->GPC['keepthread']);
		}

		if (vB::$vbulletin->GPC_exists['movethread'] AND !$this->getAssociatedThreadId())
		{
			$dm->set('movethread', vB::$vbulletin->GPC['movethread']);
		}

		//We may have some processing to do for public preview. Let's see if comments
		// are enabled. We never enable them for sections, and they might be turned off globally.
		vB::$vbulletin->input->clean_array_gpc('r', array(
			'publicpreview' => TYPE_UINT
		));

		$success = $dm->saveFromForm($this->content->getNodeId());

		$this->changed = true;

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
			clear_autosave_text('vBCms_Article', $this->content->getNew() ? 0 : $this->content->getNodeId(), 0, vB::$vbulletin->userinfo['userid']);
			$view->status = new vB_Phrase('vbcms', 'content_saved');
			$this->cleanContentCache();

			// Thread association
			if ($associate_thread_now)
			{
				//We might have a value for keepthread and movethread, or we could have a stored
				//value. We need to pass those if applicable to the associatethread function
				if ((vB::$vbulletin->GPC_exists['keepthread'] AND intval(vB::$vbulletin->GPC['keepthread']))
					OR (!vB::$vbulletin->GPC_exists['keepthread'] AND $this->content->getKeepThread()))
				{
					$keepthread = true;
				}
				else
				{
					$keepthread = false;
				}

				if ((vB::$vbulletin->GPC_exists['movethread'] AND intval(vB::$vbulletin->GPC['movethread']))
					OR (!vB::$vbulletin->GPC_exists['movethread'] AND $this->content->getMoveThread()))
				{
					$movethread = true;
				}
				else
				{
					$movethread = false;
				}

				$this->associateThread($keepthread, $movethread);
			}

			// Make sure the posthash is valid
			if (md5(vB::$vbulletin->GPC['poststarttime'] . vB::$vbulletin->userinfo['userid'] . vB::$vbulletin->userinfo['salt']) == vB::$vbulletin->GPC['posthash'])
			{
				vB::$vbulletin->db->query_write("
					UPDATE " . TABLE_PREFIX . "attachment
					SET
						posthash = '',
						contentid = " . intval($this->content->getNodeId()) . "
					WHERE
						posthash = '" . vB::$vbulletin->db->escape_string(vB::$vbulletin->GPC['posthash']) . "'
							AND
						contenttypeid = " . intval(vB_Types::instance()->getContentTypeID("vBCms_Article")) . "
				");
			}

			// only publish to Facebook if we are going from not-published to published, and the date is in the past
			if (is_facebookenabled() AND $this->content->isPublished())
			{
				$message =  new vB_Phrase('posting', 'fbpublish_message_newarticle', vB::$vbulletin->options['bbtitle']);
				$fblink =  vBCms_Route_Content::getURL(array(
					'node' => $this->content->getUrlSegment(),
					'action' =>'view'
				));
				$fblink = str_ireplace('&amp;', '&', $fblink);
				publishtofacebook_newarticle($message, $this->content->getTitle(), $this->content->getPageText(), create_full_url($fblink));
			}
		}

		($hook = vBulletinHook::fetch_hook('vbcms_article_save_end')) ? eval($hook) : false;

		//invalidate the navigation cache.
		vB_Cache::instance()->event('sections_updated');
		vB_Cache::instance()->event('articles_updated');
		vB_Cache::instance()->event(array_merge($this->content->getCacheEvents(),array($this->content->getContentCacheEvent())));

		//Make sure comment count will be updated when a comment is posted
		if ($threadid = $this->content->getAssociatedThreadId())
		{
			vB_Cache::instance()->eventPurge("cms_comments_change_$threadid");
		}

		vB_Cache::instance()->eventPurge('cms_comments_change');
		vB_Cache::instance()->eventPurge('cms_comments_add_' . $this->content->getNodeId());
		vB_Cache::instance()->cleanNow();

		$this->content->reset();
		//reset the required information
		$this->content->requireInfo(vBCms_Item_Content::INFO_BASIC);
		$this->content->requireInfo(vBCms_Item_Content::INFO_CONTENT);
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

	/**** This creates the edit user interface. It returns the edit view.
	 * @param $parameters added for PHP 5.4 strict standards compliance
	 *
	 * @return view
	 ****/
	public function getInlineEditBodyView($parameters = false)
	{
		global $vbphrase;
		require_once DIR . '/includes/functions_databuild.php';
		require_once DIR . '/includes/functions.php';
		fetch_phrase_group('cpcms');


		$this->editing = true;

		//confirm that the user has edit rights
		if (!$this->content->canEdit() AND !($this->getUserId() == vB::$vbulletin->userinfo['userid'])
			AND !$this->content->canPublish())
		{
			return $vbphrase['no_edit_permissions'];
		}


		vB::$vbulletin->input->clean_array_gpc('r', array(
			'postid' => vB_Input::TYPE_UINT,
			'blogcommentid' => vB_Input::TYPE_UINT,
			'do' => vB_Input::TYPE_STR,
			'blogid' => TYPE_UINT
		));

		if ($_REQUEST['do'] == 'delete')
		{
			$dm = $this->content->getDM();
			$dm->delete();
			$this->cleanContentCache();
			return $vbphrase['article_deleted'];
		}

		if ($_REQUEST['do'] == 'apply' OR $_REQUEST['do'] == 'update')
		{
			$this->saveData($view);
		}


		require_once DIR . '/packages/vbcms/contentmanager.php';
		// Load the content item
		if (!$this->loadContent($this->getViewInfoFlags(self::VIEW_PAGE)))
		{
			throw (new vB_Exception_404());
		}

		global $show;

		$show['img_bbcode'] = $show['video_bbcode'] = true;
		// Get smiliecache and bbcodecache
		vB::$vbulletin->datastore->fetch(array('smiliecache','bbcodecache'));

		// Create view
		$view = $this->createView('inline', self::VIEW_PAGE);

		// Add the content to the view
		$view = $this->populateViewContent($view, self::VIEW_PAGE, false);
		$pagetext = $this->content->getPageText();
		// Get postings phrasegroup
		// need posting group
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('posting');

		// Build editor
		global $messagearea;
		require_once DIR . '/includes/functions_file.php';
		require_once DIR . '/includes/functions_editor.php';
		require_once(DIR . '/packages/vbattach/attach.php');

		$view->formid = "cms_content_data";
		$view->can_edit = $this->content->canEdit();
		$view->editorid = 0;

		if ($this->content->canEdit())
		{
			$attach = new vB_Attach_Display_Content(vB::$vbulletin, 'vBCms_Article');
			//this will set a number of its parameters if they are not already set.
			$posthash = null;
			$poststarttime = null;
			$postattach = array();
			$attachcount = 0;

			$values = "values[f]=" . $this->content->getNodeId() ;

			$attachmentoption = $attach->fetch_edit_attachments($posthash, $poststarttime, $postattach, $this->content->getNodeId(), $values, '', $attachcount);

			$attachinfo = fetch_attachmentinfo($posthash, $poststarttime, $this->getContentTypeID(), array('f' => $this->content->getNodeId()));

			// do not display smiley sidebar
			vB::$vbulletin->options['smtotal'] = 0;

			$view->editorid = construct_edit_toolbar(
				htmlspecialchars_uni($pagetext),
				false,
				'article',
				true,
				true,
				true,
				'cms_article',
				'',
				$attachinfo,
				'content',
				'vBCms_Article',
				$this->content->getNew() ? 0 : $this->content->getNodeId(),
				0,
				false,
				true,
				'cms_node_title'
			);

			$templater = vB_Template::create('vbcms_article_editor');
			$templater->register('attachmentoption', $attachmentoption);
			$templater->register('attachmentoption', $attachmentoption);
			$templater->register('posthash', $posthash);
			$templater->register('poststarttime', $poststarttime);
			$templater->register('contenttypeid', $this->getContentTypeID());
			$templater->register('values', $values);
			$templater->register('contentid', $this->content->getNodeId());
			$templater->register('insertinline ', 1);
			$templater->register('checked', $checked);
			$templater->register('disablesmiliesoption', $disablesmiliesoption);
			$templater->register('editorid', $view->editorid);
			$templater->register('messagearea', $messagearea);
			$tag_delimiters = addslashes_js(vB::$vbulletin->options['tagdelimiter']);
			$templater->register('tag_delimiters', $tag_delimiters);
			$content = $templater->render();
			$view->editor = $content;
		}
		else
		{
			$view->previewtext = $this->content->getPreviewText(false, false);
		}

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
		$view->editbar = $this->content->getEditBar($view->submit_url, vBCms_Route_Content::getURL($segments), $view->formid, $view->editorid);

		$view->publisher = $this->content->getPublishEditor($view->submit_url, $view->formid,
			true, true, $this->content->getPublicPreview(), $this->content->getComments_Enabled());
		$view->authorid = ($this->content->getUserId());
		$view->authorname = ($this->content->getUsername());
		$view->viewcount = ($this->content->getViewCount());
		$view->parentid = $this->content->getParentId();
		$view->post_started = ($this->content->getPostStarted());
		$view->post_posted = ($this->content->getPostPosted());

		$view->comment_count = ($this->content->getReplyCount());
		$view->contentid = $this->content->getContentId(true);

		$view->show_threaded = true;
		$view->per_page = 10;
		$view->indent_per_level = 5;
		$view->max_level = 4;
		// Add form check
		$this->addPostId($view);
		return $view;
	}

	/**
	 * Creates a content view.
	 * The default method fetches a view based on the required result, package
	 * identifier and content class identifier.  Child classes may want to override
	 * this.  Ths method is also voluntary if the getView methods are overriden.
	 *
	 * @param string $result					- The result identifier for the view
	 * @return vB_View
	 */
	protected function createView($result, $viewtype = self::VIEW_PAGE)
	{
		$result = strtolower($this->package . '_content_' . $this->class . '_' . $result);

		return new vBCms_View_Article($result);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/
