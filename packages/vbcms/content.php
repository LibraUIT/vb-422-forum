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
 * CMS Content Controller
 * Base content controller for CMS specific content types.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28694 $
 * @since $Date: 2008-12-04 16:12:22 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vBCms_Content extends vB_Content
{
	/*Properties====================================================================*/

	/**
	 * Controller Parameters.
	 * This should be defined by the child class using key => values.
	 * The keys are used for reading and must be in the order that the content
	 * handler expects from the end user, as they will be populated in that order.
	 *
	 * @var mixed
	 */
	protected $parameters = array();

	/**
	 * CMS Content Item.
	 * Repeated here for phpDoc type change.
	 *
	 * @var vBCms_Item_Content
	 */
	protected $content;

	/**
	 * Config values.
	 * These can be set externally by a page controller, or fetched internally.
	 *
	 * @var mixed
	 */
	protected $config = array();

	/**
	 * The amount of characters to show in preview text of the content.
	 *
	 * @var int
	 */
	protected $preview_length;

	/**
	 * Whether the content supports a special editing UI.
	 * This should not determine if the content supports an inline editing UI or if
	 * the user is allowed to edit content, but instead a specific edit UI that can
	 * be fetched with getEditView().
	 * @see vBCms_Content::getEditView()
	 *
	 * @var bool
	 */
	protected $is_editable;

	/**
	 * Whether the content supports a config UI.
	 * Determines if the content supports a config UI retreivable with getConfigView().
	 * @see vBCms_Content::getConfigView()
	 *
	 * @var bool
	 */
	protected $is_configurable;

	/**
	 * Whether the contenttype is an aggregator to display child content nodes.
	 * This is used to determine whether to skip content of this type during
	 * aggregation if a parent aggregator is configured with 'do not show
	 * sections'.
	 *
	 * @var bool
	 */
	protected $is_section;

	/**
	 * Nodeleft value of the content node.
	 * @TODO: Move to node item.
	 *
	 * @var int
	 */
	protected $nodeleft = false;

	/**
	 * Noderight value of the content node.
	 * @TODO: Move to node item.
	 *
	 * @var int
	 */
	protected $noderight = false;

	protected $changed = false;
	/*ViewInfo======================================================================*/

	/**
	 * Info required for view types.
	 *
	 * @var array
	 */
	protected $view_info = array(
		self::VIEW_LIST => vBCms_Item_Content::INFO_BASIC,
		self::VIEW_AGGREGATE => vBCms_Item_Content::INFO_NODE,
		self::VIEW_PREVIEW => vBCms_Item_Content::INFO_NODE,
		self::VIEW_PAGE => vBCms_Item_Content::INFO_CONTENT
	);


	/*Initialisation================================================================*/

	/**
	 * Factory method to create a CMS content controller.
	 * This is defined here to provide a PHPDoc hint on the return type.
	 *
	 * @param string $package
	 * @param string $class
	 * @param string $contentid
	 * @return vBCms_Content
	 */
	public static function create($package, $class, $contentid = false)
	{
		return parent::create($package, $class, $contentid);
	}

	/** We need to set the contenttypeid immediately, so the item
	 * will be handled properly
	 *
	 * @param mixed $content
	 */
	protected function __construct($content = false)
	{
		if (!$this->package OR !$this->class)
		{
			throw (new vB_Exception_Content('No package or contenttype class defined for content item '));
		}

		if ($content instanceof vB_Item_Content)
		{
			$this->content = $content;
			$this->contentid = $content->getId();
		}
		else
		{
			$this->contentid = $content;
			$this->contenttypeid = vB_Types::instance()->getContentTypeID(array('package' => $this->package, 'class' => $this->class));

		}
	}

	/*Configuration=================================================================*/

	/**
	 * Validates and sets request parameters.
	 * Parameters passed in are unkeyed and in a specific order.
	 * @see vBCms_Content::$parameters
	 *
	 * @param mixed								- The parameters to set
	 */
	public function setParameters($in_parameters)
	{
		if (!is_array($in_parameters))
		{
			throw (new vB_Exception_Content('Parameters passed to content handler for \'' . $this->package . '_' . $this->class . '\' is not an array'));
		}

		foreach (array_keys($this->parameters) AS $parameter)
		{
			if (!sizeof($in_parameters))
			{
				break;
			}

			$this->setParameter($parameter, array_shift($in_parameters));
		}
	}


	/**
	 * Validates and sets a single parameter value.
	 *
	 * @param string $parameter					- The key name of the parameter to set
	 * @param mixed $value						- The value to set it to
	 */
	public function setParameter($parameter, $value)
	{
		if (!isset($this->parameters[$parameter]))
		{
			throw (new vB_Exception_Content('Attempting to set invalid parameter \'' . htmlspecialchars_uni($parameter) . '\' on content handler \'' . $this->package . '_' . $this->class));
		}

		$this->assignParameter($parameter, $value);
	}


	/**
	 * Assigns a parameter value.
	 * Any validation, transformations or limits should be applied here.
	 *
	 * @param string $parameter					- The key name of the parameter to set
	 * @param mixed $value						- The value to set it to
	 */
	protected function assignParameter($parameter, $value)
	{
		$this->parameters[$parameter] = $value;
	}


	/**
	 * Allows the config to be set by client code.
	 *
	 * @param cvar => mixed $config				- The config to set
	 * @param mixed $value						- A specific value to set for a single cvar
	 * @param bool $ignore_errors				- Whether to ignore invalid cvars
	 */
	public function setConfig($config, $value = null, $ignore_errors = false)
	{
		if (is_array($config) OR ($config instanceof ArrayAccess))
		{
			foreach ($config AS $cvar => $value)
			{
				$this->setConfig($cvar, $value, $ignore_errors);
			}
		}
		else
		{
			if (!isset($this->config[$config]) AND !$ignore_errors)
			{
				throw (new vB_Exception_Content('Attempting to set invalid cvar \'' . htmlspecialchars_uni($config) . '\' on content handler \'' . $this->package . '_' . $this->class));
			}
			else
			{
				$this->config[$config] = $value;
			}
		}
	}


	/**
	 * Loads the config for the current content.
	 */
	public function loadConfig()
	{
		$this->loadContent(vBCms_Item_Content::INFO_CONFIG);

		$this->setConfig($this->content->getConfig(), null, true);
	}


	/**
	 * Sets the character length to use for preview text.
	 * Note: This is just a guide and may be used differently by implementations.
	 *
	 * @param int $length
	 */
	public function setPreviewLength($length)
	{
		$this->preview_length = abs(intval($length));
	}


	/*Creation======================================================================*/

	/**
	 * Creates a new, empty content item to add to a node.
	 * Content must
	 * Note: Do NOT save the node dm!
	 *
	 * @param vBCms_DM_Node $nodedm				- The DM of the node that the content is being created for
	 * @return int | false						- The id of the new content or false if not applicable
	 */
	abstract public function createDefaultContent(vBCms_DM_Node $nodedm);


	/**
	 * Deletes content.
	 */
	public function deleteContent()
	{
		if ($this->use_item)
		{
			$this->assertContent();

			$dm = $this->content->getDM();

			$dm->delete();
		}
	}



	/*Render========================================================================*/

	/**
	 * Fetches a preview of the specified content item using only the generic nodeinfo.
	 *
	 * @return vB_View
	 */
	public function getAggregateView()
	{
		$load_flags = self::getViewInfoFlags(self::VIEW_AGGREGATE);

		// Load the content item
		$this->loadContent($load_flags);

		if (!$this->canPreview())
		{
			throw (new vB_Exception_AccessDenied());
		}

		$view = $this->createView('aggregate', self::VIEW_AGGREGATE);
		$this->populateViewContent($view, self::VIEW_AGGREGATE);

		return $view;
	}


	/**
	 * Returns the inline edit view for the content.
	 * The inline edit view allows the current user to edit the contents within the
	 * body of the page.  This should only be allowed for users with permission.
	 *
	 * Contenttypes do not have to support inline editing, however the inline edit
	 * view also provides the page view within a tool block view with links to
	 * configure and edit the content.  This is provided by the default
	 * implementation.
	 *
	 * @param array mixed $parameters			- Request parameters
	 * @return vBCms_View_Content				- The view result
	 */
	public function getInlineEditView($parameters = false)
	{
		$this->assertContent();

		if ($parameters)
		{
			$this->setParameters($parameters);
		}

		// add the tool block
		$view = new vBCms_View_EditBlock('vbcms_edit_block');

		if ($this->canEdit() OR $this->canPublish())
		{
			if ($this->is_editable)
			{
				$view->edit_url = vBCms_Route_Content::getURL(array('node' => $this->content->getUrlSegment(),
															'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'EditContent')));
			}

			if ($this->is_configurable)
			{
				$view->config_url = vBCms_Route_Content::getURL(array('node' => $this->content->getUrlSegment(),
															  'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'ConfigContent')));
			}

			$view->content = $this->getInlineEditBodyView($parameters);
		}
		else
		{
			$view->content = $this->getPageView($parameters);
		}


		return $view;
	}


	/**
	 * Gets the body view for the widget inline edit view.
	 * By default, the standard page view is returned within the tool block view.
	 * Some widgets may offer inline editing in the page view.
	 *
	 * @param array mixed $parameters			- Request parameters
	 * @return vBCms_View_Widget
	 */
	protected function getInlineEditBodyView($parameters = false)
	{
		return $this->getPageView($parameters);
	}


	/**
	 * Fetches the view for configuring a content item.
	 *
	 * @param mixed $parameters					- Request parameters
	 * @return vB_View | bool					- Returns a view or false
	 */
	public function getConfigView($parameters = false)
	{
		throw (new vB_Exception_Content('A config view was requested from \'' . $this->package . '_Content_' . $this->class . '\' but getConfigView is undefined.  Check the db definition for the contenttype \'' . $this->class . '\''));
	}

	/**Tells the controller whether it needs to reload data
	**/
	public function getChanged()
	{
		return $this->changed;
	}

	/**
	 * Fetches the view for a popup edit view of a content item.
	 *
	 * @param mixed $parameters					- Request parameters
	 * @return vB_View | bool					- Returns a view or false
	 */
	public function getEditView($parameters = false)
	{
		throw (new vB_Exception_Content('An edit view was requested from \'' . $this->package . '_Content_' . $this->class . '\' but getEditView is undefined.  Check the db definition for the contenttype \'' . $this->class . '\''));
	}


	/**
	 * Populates a view with the expected info from a content item.
	 *
	 * @param vB_View $view
	 * @param int $viewtype
	 */
	protected function populateViewContent(vB_View $view, $viewtype = self::VIEW_PAGE)
	{
		($hook = vBulletinHook::fetch_hook('vbcms_content_populate_start')) ? eval($hook) : false;
		parent::populateViewContent($view, $viewtype);

		$view->node = $this->content->getNodeId();
		$view->type = new vB_Phrase('global', 'content');

		if (vB::$vbulletin->userinfo['userid'])
		{
			$view->published = $this->content->isPublished();
		}
		else
		{
			$view->published = true;
		}

		$view->page_url = $this->getPageUrl();
		//Let's pass an url for editing.
		$view->edit_url = $this->getEditUrl();

		if ($date = $this->content->getPublishDate())
		{
			$view->publishdate = $date;
		}

		$view->publish_phrase = new vB_Phrase('vbcms', 'page_not_published');

		// Get comments
		if (self::VIEW_PAGE == $viewtype AND vB::$vbulletin->options['vbcmsforumid'] > 0 AND $this->content->isPublished() AND !$this->isSection())
		{
			try
			{
				$thread = $this->getAssociatedThread();
				$view->show_comments = ($thread AND $thread->can_view(new vB_Legacy_CurrentUser()));

				if ($view->show_comments)
				{
					$postids = $this->getCommentPosts($thread);
					require_once DIR . '/includes/functions_forumdisplay.php' ;
					$view->thread = process_thread_array($thread->get_record());
				}
			}
			catch (Exception $e)
			{
				if (vB::$vbulletin->config['debug'])
				{
					throw ($e);
				}
			}
		}

		// Check if the user has voted
		$check = true;
		if (vB::$vbulletin->userinfo['userid'])
		{
			$row = vB::$db->query_first("SELECT vote FROM " . TABLE_PREFIX . "cms_rate WHERE userid = " . vB::$vbulletin->userinfo['userid'] . " AND nodeid = " . $view->node);
			if ($row[0])
			{
				$check = false;
			}
		}
		$rated = intval(fetch_bbarray_cookie('cms_rate', $view->node));

		// voted already
		if ($row[0] OR $rated)
		{
			$rate_index = $rated;
			if ($row[0])
			{
				$rate_index = $row[0];
			}
			$view->voteselected["$rate_index"] = 'selected="selected"';
			$view->votechecked["$rate_index"] = 'checked="checked"';
		}
		else
		{
			$view->voteselected[0] = 'selected="selected"';
			$view->votechecked[0] = 'checked="checked"';
		}

		$view->showratenode =
		(
			(
				$check
			OR
				(!$rated AND !vB::$vbulletin->userinfo['userid'])
			)
			OR
				vB::$vbulletin->options['votechange']
		);

		// Get ratings
		if ($this->content->getRatingNum() >= vB::$vbulletin->options['showvotes'])
		{
			$view->ratingtotal = $this->content->getRatingTotal();
			$view->ratingnum = $this->content->getRatingNum();
			$view->ratingavg = vb_number_format($view->ratingtotal / $view->ratingnum, 2);
			$view->rating = intval(round($view->ratingtotal / $view->ratingnum));
			$view->showrating = true;
		}
		else
		{
			$view->showrating = false;
		}
		vB_Cache::instance()->event(vBCms_NavBar::getCacheEventId($this->content->getNodeId()));
		($hook = vBulletinHook::fetch_hook('vbcms_content_populate_end')) ? eval($hook) : false;

	}


	/**
	 * Checks if a post request was intended for this item controller.
	 *
	 * @return bool
	 */
	public function verifyPostId()
	{
		vB::$vbulletin->input->clean_array_gpc('r', array(
			'item_type' => vB_Input::TYPE_NOCLEAN,
			'item_class' => vB_Input::TYPE_STR,
			'item_id' => vB_Input::TYPE_NOCLEAN
		));

		return ((vB::$vbulletin->GPC['item_type'] == 'content')
				AND (vB::$vbulletin->GPC['item_class'] == vBCms_Types::instance()->getTypeKey($this->content->getPackage(), $this->content->getClass()))
				AND vB::$vbulletin->GPC['item_id'] == $this->content->getContentId());
	}


	/**
	 * Adds item id info to a view for submitting via post.
	 *
	 * @param vB_View $view
	 */
	protected function addPostId(vB_View $view)
	{
		$view->item_type = 'content';
		$view->item_class = vBCms_Types::instance()->getTypeKey($this->content->getPackage(), $this->content->getClass());
		$view->item_id = $this->content->getContentId();
		$view->nodeid = $this->content->getNodeId();
	}

	/** Returns the nodeleft value**/
	public function getNodeLeft()
	{
		$this->loadContent();
		return $this->content->getNodeLeft();
	}

	/** Returns the nodeleft value**/
	public function getNodeRight()
	{
		$this->loadContent();
		return $this->content->getNodeRight();
	}

	/*Permissions===================================================================*/

	/**
	 * Determines if the current user can edit the content.
	 *
	 * @return bool
	 */
	public function canEdit()
	{
		//See canView for the logic
		$this->assertContent();

		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}

		return $this->content->canEdit();

	}

	public function canCreate()
	{
		$this->assertContent();
		return $this->content->canCreate();
	}

	/**
	 * Determines whether the current user can view the content.
	 *
	 * @return bool
	 */
	public function canView()
	{
		//We make sure that the content has been instantiated and that the
		// user CMS permissions have been set. Then we read from the user's information.
		//The key to understanding permissions is the permissionsfrom flag in the
		// node table. That is set to a node which has permissions in the
		// cms_permissions table. Then the user has a set of permission information
		// in $vbulletin->userinfo['permissions']['cms'].  The actual determination
		// is made in item_content, which knows the internals of this item.
		$this->assertContent();

		if (!isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}
		return $this->content->canView();
	}

	/**
	 * Determines whether the current user can publish the content.
	 *
	 * @return bool
	 */
	public function canPublish()
	{
		//See canView for the logic
		$this->assertContent();

		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}
		return $this->content->canPublish();

	}



	/*Accessors=====================================================================*/

	/**
	 * Gets the nodeid of the content.
	 *
	 * @return int
	 */
	public function getNodeId()
	{
		$this->loadContent();

		return $this->content->getNodeId();
	}

	/**
	 * Gets the nodeid of the content.
	 *
	 * @return int
	 */
	public function getParentId()
	{
		$this->loadContent();

		return $this->content->getParentId();
	}



	/**
	 * Returns the description of the set content.
	 * This allows content handlers to transform or resolve the description before
	 * providing it from the content item.
	 *
	 * @return string
	 */
	public function getDescription()
	{
		$this->loadContent();

		return $this->content->getDescription();
	}


	/**
	 * Fetches the URL segment of the content.
	 *
	 * @return string
	 */
	public function getNodeURLSegment()
	{
		$this->loadContent();

		return $this->content->getUrlSegment();
	}


	/**
	 * Fetches the default page URL to the content.
	 *
	 * @return string							- The url or boolean false
	 */
	public function getPageURL($parameters = array(), $force_absolute = false)
	{
		$this->loadContent();

		return vBCms_Route_Content::getURL(array('node' => $this->getNodeURLSegment()), $parameters, $force_absolute);
	}

	/**
	 * Fetches the default page URL to the content.
	 *
	 * @return string							- The url or boolean false
	 */
	public function getEditURL($parameters = false)
	{
		$this->loadContent();

		return vBCms_Route_Content::getURL(array('node' => $this->getNodeURLSegment(), 'action' => 'edit' ));
	}


	/**
	 * Returns whether content of this type can be considered a section.
	 * @see vBCms_Content::$is_section.
	 *
	 * @return bool
	 */
	public function isSection()
	{
		return $this->is_section;
	}


	/**
	 * Returns a publish date for the content.
	 *
	 * @return int								- UNIX Timestamp
	 */
	public function getPublishDate()
	{
		if ($this->use_item AND $this->content)
		{
			return $this->content->getPublishDate();
		}

		return false;
	}


	/**
	 * Returns whether the content is published.
	 *
	 * @return bool
	 */
	public function isPublished()
	{
		if ($this->use_item AND $this->content)
		{
			return $this->content->isPublished();
		}

		throw (new vB_Exception_Content('Checking if content is published but no content is set in \'' . get_class($this) . '\''));
	}


	/**
	 * Get breadcrumbinfo from content.
	 *
	 * @return array
	 */
	public function getBreadCrumbInfo()
	{
		$this->loadContent(vBCms_Item_Content::INFO_PARENTS);

		return $this->content->getBreadcrumbInfo();
	}


	/*Comments======================================================================*/

	/**
	 * Get Id of any associated thread id for commenting on this content.
	 *
	 * @return int
	 */
	public function getAssociatedThreadId()
	{
		$this->assertContent();

		if ($this->use_item AND $this->content AND $this->content->getSetPublish()
			AND ($this->content->getPublishDate() <= TIMENOW))
		{
			return $this->content->getAssociatedThreadId();
		}

		return false;
	}


	/**
	 * Gets the associated thread for commenting on this content.
	 *
	 * @return vB_Legacy_Thread
	 */
	protected function getAssociatedThread()
	{
		//first verify that we are in a published state
		if (!$this->content->getSetPublish() OR ($this->content->getPublishDate() > TIMENOW))
		{
			return false;
		}

		if ($this->content->getClass() == 'Article')
		{
			$movethread = $this->content->getMoveThread();
			$keepthread = $this->content->getKeepThread();
		}
		else
		{
			$movethread = false;
			$keepthread = false;
		}

		// Resolve the thread id
		if (intval(vB::$vbulletin->options['vbcmsforumid']) > 0 
			AND intval($this->content->getSetPublish())
			AND ($this->content->getPublishDate() <= TIMENOW)
			AND intval($this->content->getComments_Enabled()))
		{
			$threadid = $this->content->getAssociatedThreadId();

			if (!$threadid)
			{
				$threadid = $this->associateThread($keepthread, $movethread);

				//If we failed here, there's something wrong. Probably the forum id is invalid.
				if (!$threadid)
				{
					return false;
				}
			}

			//Verify that the thread is active.
			$thread = vB_Legacy_Thread::create_from_id($threadid);

			if (!$thread)
			{
				//Try again
				$threadid = $this->associateThread($keepthread, $movethread);

				//If we failed here, there's something wrong. Probably the forum id is invalid.
				if (!$threadid)
				{
					return false;
				}
				$thread = vB_Legacy_Thread::create_from_id($threadid);

				if (!$thread)
				{
					return false;
				}
			}
			//If we got here, we have a valid thread.
			if (! $thread->get_field('visible'))
			{
				require_once DIR . '/includes/functions_databuild.php';
				undelete_thread($threadid);
			}
		}

		return $thread;
	}

	/** This function moves a thread to the CMS Comments thread
	*
	*	@param	integer
	*
	***/
	protected function moveThreadToComments($threadid, $forumid)
	{
		//Delete any redirects for this thread
		vB_dB_Assertor::assertQuery('delete_redirect_threads', array('threadid' => $threadid));

		// update canview status of thread subscriptions
		require_once DIR . '/includes/functions_databuild.php';
		update_subscriptions(array('threadids' => array($threadid)));

		// kill the post cache for these threads
		delete_post_cache_threads(array($threadid));
		vB_dB_Assertor::assertQuery('move_thread', array('threadid' => $threadid, 'forumid' => $forumid));
	}


	/**
	 * Associate a new thread with this content, and clean the cache entry for the content
	 *
	 *	@param	bool (Post promotions only) Use the posts current thread as the comment thread.
	 *	@param	bool (Post promotions only) Move the current thread to the CMS comments forum. 
	 *
	 * @return int								- The threadid of the new thread
	 */
	protected function associateThread($keepthread = false, $movethread = false)
	{
		//If it isn't published, do nothing
		if (!$this->content->getSetPublish() 
			OR ($this->content->getPublishDate() > TIMENOW))
		{
			return false;
		}

		// CMS Comments are not enabled.
		if(!(vB::$vbulletin->options['vbcmsforumid'] > 0))
		{
			return false;
		}

		// We already have a thread, dont create another.
		if ($this->getAssociatedThreadId())
		{
			vB_Cache::instance()->eventPurge('cms_comments_add_' . $this->content->getNodeId());
			return false;
		}

		if ($this->content->getClass() == 'Article')
		{
			$postid = $this->content->getPostId();
			$threadid = $this->content->getThreadId();
		}
		else
		{
			$postid = false;
			$threadid = false;
		}

			if ($threadid)
			{
				vB_dB_Assertor::init(vB::$db, vB::$vbulletin->userinfo);
			$threadresult = vB_dB_Assertor::assertQuery('get_threadid_from_post', array('postid' => $postid), false);
			$threadinfo = $threadresult->current();
			$threadforumid = $threadinfo['forumid'];

			if($movethread)
			{
				$forumid = vB::$vbulletin->options['vbcmsforumid'];
				}
			else
			{
				$forumid = $threadforumid;
			}
		}
		else
		{
			$keepthread = false;
			$movethread = false;
			$forumid = vB::$vbulletin->options['vbcmsforumid'];
		}

		//This might be set to keep the existing thread.
		//Skip if the thread is already in use by a node.
		if ($keepthread AND !get_nodeFromThreadid($threadid))
		{
			if ($movethread AND $forumid AND $threadid)
			{
				$this->moveThreadToComments($threadid, $forumid);

				build_forum_counters($forumid);
				build_forum_counters($threadforumid);
			}

			$this->content->setAssociatedThread($threadid);
			return;
		}
		else if ($id = $this->createAssociatedThread($forumid, $this->content))
		{
			if ($this->content->setAssociatedThread($id))
			{
				build_forum_counters($forumid);
			}
			else
			{
				throw new vB_Exception_Content('Could not set comments thread for content');
			}
		}
		else
		{
			return false;
		}

		// clean the article cache
		vB_Cache::instance()->eventPurge(array(
			'cms_comments_add_' . $this->content->getNodeId(),
			$this->content->getCacheEvents(),
			$this->content->getContentCacheEvent(),
		));
		
		vB_Cache::instance()->cleanNow();

		return $id;
	}


	/**
	 * Creates a new thread to use for comments
	 *
	 * @param int $forumid						- The forum to create the thread in
	 * @param int $node							- The node to associate with the thread
	 * @return int								- The id of the new thread
	 */
	protected function createAssociatedThread($forumid, $node)
	{
		$foruminfo = fetch_foruminfo($forumid);

		if (!$foruminfo)
		{
			return false;
		}

		$dataman =& datamanager_init('Thread_FirstPost', vB::$vbulletin, ERRTYPE_ARRAY, 'threadpost');
		//$dataman->set('prefixid', $post['prefixid']);

		// set info
		$dataman->set_info('skip_activitystream', true);
		$dataman->set_info('preview', '');
		$dataman->set_info('parseurl', true);
		$dataman->set_info('posthash', '');
		$dataman->set_info('forum', $foruminfo);
		$dataman->set_info('thread', array());
		$dataman->set_info('show_title_error', false);

		// set options
		$dataman->set('showsignature', true);
		$dataman->set('allowsmilie', false);

		// set data

		//title and message are needed for dupcheck later
		$title = new vB_Phrase('vbcms', 'comment_thread_title', htmlspecialchars_decode($node->getTitle()));
		$message = new vB_Phrase('vbcms', 'comment_thread_firstpost', $this->getPageURL(array(), true));
		$dataman->set('userid', $node->getUserId());
		$dataman->set('title', $title);
		$dataman->set('pagetext', $message);
		$dataman->set('iconid', '');
		$dataman->set('visible', 1);

		$dataman->setr('forumid', $foruminfo['forumid']);

		$errors = array();

		// done!
		//($hook = vBulletinHook::fetch_hook('newpost_process')) ? eval($hook) : false;

		$dataman->pre_save();
		$errors = array_merge($errors, $dataman->errors);
		vB_Cache::instance()->event($this->content->getCacheEvents());

		if (sizeof($errors) > 0)
		{
			return false;
		}

		if (!($id = $dataman->save()))
		{
			throw (new vB_Exception_Content('Could not create comments thread for content'));
		}
		return $id;
	}


	/**
	 * Gets the posts for the comments thread.
	 * Note: Deleted and moderated comments are simply skipped.  Admins can use the
	 * forum moderation to view / manage them.
	 * @TODO: Move this to an item or property of Item_Content or XData
	 *
	 * @return array int
	 */
	protected function getCommentPosts($thread)
	{
		$threadid = $thread->get_field('threadid');

		if (!$threadid)
		{
			return array();
		}

		$hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('showthread_query_postids')) ? eval($hook) : false;

		require_once DIR . '/includes/functions_bigthree.php' ;
		$coventry = fetch_coventry('string');
		$getpostids = vB::$db->query_read("
			SELECT post.postid
			FROM " . TABLE_PREFIX . "post AS post 
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON post.threadid = thread.threadid
			$hook_query_joins
			WHERE post.threadid = $threadid
				AND post.visible = 1
				AND thread.visible = 1
				AND thread.firstpostid <> post.postid
				" . ($coventry ? "AND post.userid NOT IN ($coventry)" : '') . "
				$hook_query_where
			ORDER BY post.dateline DESC
			LIMIT 5
		");

		$posts = array();
		while ($row = vB::$db->fetch_row($getpostids))
		{
			$posts[] = $row[0];
		}
		return $posts;
	}


	/*ClassInteraction================================================================*/

	/**
	 * Gets an array of controllers for a collection.
	 *
	 * @param vBCms_Collection_Content $content	- The content to fetch controllers for
	 * @param bool $skip_errors					- Whether to skip content that throws exceptions
	 * @return array vBCms_Content				- Array of content controllers.
	 */
	public static function getControllers(vBCms_Collection_Content $collection, $skip_errors = true)
	{
		$controllers = array();

		if (!$collection->isValid())
		{
			return $controllers;
		}

		foreach ($collection AS $id => $content)
		{
			try
			{
				$controller = self::create($content->getPackage(), $content->getClass(), $content);
			}
			catch (vB_Exception $e)
			{
				if (!$skip_errors OR $e->isCritical())
				{
					throw ($e);
				}

				continue;
			}

			$controllers[$content->getId()] = $controller;
		}

		return $controllers;
	}


	/*Nodes=========================================================================*/
	/* TODO: These methods should be filter methods for vBCms_Collection_Content */


	/**
	 * Asserts node left and noderight for the content item.
	 * @TODO: Add nodeleft and noderight as INFO properties vBCms_Item_Content and
	 * remove this.
	 *
	 * @return bool
	 */
	private function checkNodeLR()
	{
		if (!$this->nodeleft OR !$this->noderight)
		{
			if (! $nodeid = $this->content->getNodeId())
			{
				return false;
			}

			if (!$record = vB::$vbulletin->db->query_first("
				SELECT nodeleft, noderight FROM " .TABLE_PREFIX . "cms_node AS node
				WHERE nodeid = $nodeid;"))
			{
				return false;
			}
			$this->nodeleft = $record['nodeleft'];
			$this->noderight = $record['noderight'];
		}
		return true;
	}


	/**
	 * This is one of four functions that let us create a navigation structure.
	 * This function lets us query for all the children of the current node.
	 *
	 * @return array($nodeid => array(title, url), ordered by title
	 */
	public function getChildNodes()
	{
		$result = false;

		if (!($nodeid = $this->content->getNodeId()) OR !$this->checkNodeLR())
		{
			return $result;
		}


		//If this user has canedit, then we show everything published or not.
		$user_where = vB::$vbulletin->check_user_permission('vbcmspermissions', 'cancreatecontent') ?
			'' : " AND cms_node.setpublish = '1' AND cms_node.publishdate <= " . TIMENOW;

		if ($rst = vB::$db->query_read("SELECT cms_node.nodeid, cms_nodeinfo.title, cms_node.url
			FROM " . TABLE_PREFIX . "cms_node as cms_node
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS cms_nodeinfo ON cms_nodeinfo.nodeid = cms_node.nodeid
			WHERE cms_node.new != 1 AND cms_node.parentnode = $nodeid  $user_where
			ORDER BY cms_nodeinfo.title; "))
		{
			$result = array();
			while($row = vB::$db->fetch_row($rst))
			{
				$result[$row[0]] = array('title' =>$row[1], 'url' => $row[2], 'nodeid' => $row[0]);
			}

		}

		return $result;
	}


	/**
	 * This is the second function for creating navigation. It gives us
	 * the parent of the current node, and the top-level parent.
	 * We return a tree, from the parent of this node to the top-level parent
	 *
	 * @return array($nodeid => array(title, url), ordered by title
	 */
	public function getParentNodes($nodeid = null, $parents = null)
	{
		if (! isset($this->content))
		{
			return false;
		}
		if (is_null($nodeid))
		{
			$parents = array();

			if (! $nodeid = $this->content->getNodeId() OR !$this->checkNodeLR())
			{
				return false;
			}
		};

		$user_where = vBCMS_Permissions::getPermissionString() ;

		if ($rst = vB::$db->query_read("SELECT node2.nodeid, cms_nodeinfo.title, node2.url
			FROM " . TABLE_PREFIX . "cms_node AS node
			INNER JOIN " . TABLE_PREFIX . "cms_node AS node2 ON (node.nodeleft >= node2.nodeleft AND node.nodeleft <=node2.noderight)
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS cms_nodeinfo ON cms_nodeinfo.nodeid = node2.nodeid
			WHERE node.nodeid = $nodeid AND node2.nodeid <> $nodeid AND ($user_where)
			order by node.nodeleft DESC ;"))
		{
			while ($row = vB::$db->fetch_row($rst))
			{
				$parents[] =  array('title' =>$row[1], 'url' => $row[2], 'nodeid' => $row[0]);
			}
		}
		return $parents;
	}


	/**
	 * This is the third function for creating a navigation structure. It returns
	 * an array of siblings of the current node.
	 *
	 * @return array($nodeid => array(title, url), ordered by title
	 */
	public function getSiblingNodes()
	{
		$result = false;

		if (! $nodeid = $this->content->getNodeId() OR !$this->checkNodeLR())
		{
			return $result;
		}

		$user_where = vB::$vbulletin->check_user_permission('vbcmspermissions', 'cancreatecontent') ?
			'' : " AND cms_node3.setpublish = '1' AND cms_node3.publishdate <= " . TIMENOW;

		if ($rst = vB::$db->query_read("SELECT cms_node3.nodeid,
			cms_nodeinfo.title, cms_node3.url
			FROM " . TABLE_PREFIX . "cms_node AS cms_node
			INNER JOIN " . TABLE_PREFIX . "cms_node AS cms_node2 on cms_node2.nodeid = cms_node.parentnode
			INNER JOIN " . TABLE_PREFIX . "cms_node AS cms_node3 on cms_node3.parentnode = cms_node2.nodeid AND cms_node3.new != 1
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS cms_nodeinfo ON cms_nodeinfo.nodeid = cms_node3.nodeid
			WHERE cms_node.nodeid = $nodeid  AND cms_node3.nodeid <> $nodeid
			$user_where ORDER BY cms_nodeinfo.title;"))
		{
			$result = array();
			while($row = vB::$db->fetch_row($rst))
			{
				$result[$row[0]] = array('title' =>$row[1], 'url' => $row[2], 'nodeid' => $row[0]);
			}

		}

		return $result;
	}


	/**
	 * This is the fourth (at least in order in this file) of the functions
	 * for creating a navigation structure. It returns an array of the top-level
	 * nodes. (There may be only one)
	 *
	 * @return array($nodeid => array(title, url), ordered by title
	 */
	public function getTopLevelNodes($config_show_siblings = false)
	{
		$result = false;

		if (! $nodeid = $this->content->getNodeId() OR !$this->checkNodeLR())
		{
			return $result;
		}
		//Now we have two special cases to handle.
		// If this is the root node, then the top-level nodes are the children. So since we already have the children
		//  (we always do the children) we should return an empty array here.
		// Also, if this is level 1 (recognizable because our parent node has a null parentnode
		//  and we have show_siblings true), then the siblings are the top level and
		//  we should return an empty array.

		if ($row = vB::$vbulletin->db->query_first("SELECT CASE WHEN n1.parentnode IS NULL
		 	THEN 0 ELSE n1.parentnode END AS parentnode, CASE WHEN n2.parentnode IS NULL
		 	THEN 0 ELSE n2.parentnode END AS parentnode2 FROM "
			. TABLE_PREFIX . "cms_node AS n1 LEFT JOIN "
			. TABLE_PREFIX . "cms_node AS n2 ON n2.nodeid = n1.parentnode
			WHERE n1.nodeid = $nodeid
			")
			and $row['parentnode'] == 0 OR $row['parentnode2'] == 0)
		{
			return array();
		}



		$user_where = vB::$vbulletin->check_user_permission('vbcmspermissions', 'cancreatecontent') ?
			'' : " AND cms_node.setpublish = '1' AND cms_node.publishdate <= " . TIMENOW;

		//Now we have one special case to handle. If this is the root node, then
		// the top-level nodes are the children. So since we already have the children
		//  (we always do the children) we should return an empty array here.

		if ($rst = vB::$db->query_read("SELECT cms_node.nodeid, cms_nodeinfo.title, cms_node.url
			FROM " . TABLE_PREFIX . "cms_node as cms_node
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS cms_nodeinfo ON cms_nodeinfo.nodeid = cms_node.nodeid
			INNER JOIN " . TABLE_PREFIX . "cms_node AS node2 ON node2.nodeid = cms_node.parentnode
			WHERE node2.parentnode IS NULL AND cms_node.nodeid <> $nodeid AND cms_node.new != 1 AND $user_where
			ORDER BY cms_nodeinfo.title; "))
		{
			$result = array();
			while($row = vB::$db->fetch_row($rst))
			{
				$result[$row[0]] = array('title' =>$row[1], 'url' => $row[2], 'nodeid' => $row[0]);
			}

		}
		return $result;
	}

	/** This returns the content permission setting for can view
	 *
	 * @return boolean
	 */
	public function publicCanView()
	{
		return $this->content->publicCanView();
	}

	/**  This returns the content permissionsfrom value
	 *
	 * @return boolean
	 */
	public function getPermissionsFrom()
	{
		return $this->content->getPermissionsFrom();
	}


	/*Cache=========================================================================*/

	/**
	 * Cleans the cache of all of the parent sections.
	 */
	protected function cleanContentCache()
	{
		$events = $this->getCleanCacheEvents();
		vB_Cache::instance()->eventPurge($events);
		vB_Cache::instance()->cleanNow();
	}


	/**
	 * Gets the events that need to be cleaned when the content is updated.
	 */
	protected function getCleanCacheEvents()
	{
		$this->assertContent();

		//we have an event
		// clear cache of affected sections
		$events = $this->content->getParentIds(true);

		$section_contenttype = vB_Types::instance()->getContentTypeID('vBCms_Section');

		foreach ($events AS &$sectionid)
		{
			$sectionid = 'content_' . $section_contenttype . '_' . $sectionid;
		}

		$events[] = 'vbcms_item_' . $this->content->getNodeId() . '_updated';

		return array_merge($events, $this->content->getCacheEvents());
	}

	/** Checks to see if the user can create a specific type in a specific section
	 *	@param 	int sectionid
	 *
	 *	@return	bool
	 ***/
	public function canCreateHere($sectionid)
	{
		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getPerms();
		}

		return in_array($sectionid, vB::$vbulletin->userinfo['permissions']['cms']['cancreate']) ;	}

	public function getLayout()
	{
		return $this->content->getLayout();
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/
