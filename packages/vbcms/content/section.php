<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.2 - Nulled By VietVBB Team
|| # ---------------------------------------------------------------- # ||
|| # Copyright ?2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * Section Content Controller
 * The section controller aggregates the content below it in the node tree.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28694 $
 * @since $Date: 2008-12-04 16:12:22 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Content_Section extends vBCms_Content
{
	/*Properties====================================================================*/

	/**
	 * A class identifier.
	 *
	 * @var string
	 */
	protected $class = 'Section';

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
	protected $parameters = array(
								'page' => 1,
								'unpublished' => 0
							);

	/**
	 * Config values.
	 *
	 * @var mixed
	 */
	protected $config = array(
							'quantity' => 5,
							'content_layout' => 4,
							'items_perhomepage' => 7,
							'pagination_links' => 1,
							'contentfrom' => 1,
							'simple_paging' => 0,
							'section_priority' => 1
						);

	protected $parent_node = false;

	/**
	 * Whether the contenttype is an aggregator to display child content nodes.
	 *
	 * @var bool
	 */
	protected $is_section = true;

	/** cache life, minutes ***/
	protected $cache_ttl = 1440;

	/** items displayed per edit page ***/
	protected $perpage = 50;

	/** Are we editing? **/
	protected $editing = false;

	/** current displayed page ***/
	protected $current_page = 1;

	/*ViewInfo======================================================================*/

	/**
	 * Info required for view types.
	 *
	 * @var array
	 */
	protected $view_info = array(
		self::VIEW_LIST => vBCms_Item_Content::INFO_BASIC,
		self::VIEW_PREVIEW => vBCms_Item_Content::INFO_NODE,
		self::VIEW_PAGE => vBCms_Item_Content::INFO_NODE,
		self::VIEW_AGGREGATE => vBCms_Item_Content::INFO_NODE
		);

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
			'nodeid' => vB_Input::TYPE_UINT,
			'parentnode' => vB_Input::TYPE_UINT
		));


			//We should have a nodeid, but a parentnode is even better.
		($hook = vBulletinHook::fetch_hook('vbcms_section_defaultcontent_start')) ? eval($hook) : false;

		if ($nodedm->getSet('parentnode'))
		{
			$parentnode = $nodedm->getField('parentnode');
		}
		else
		{
			if ($this->parent_node)
			{
				$parentnode = $this->parent_node;
			}
			else if (vB::$vbulletin->GPC_exists['parentnode'] AND intval(vB::$vbulletin->GPC['parentnode'] ))
			{
				$parentnode = vB::$vbulletin->GPC['parentnode'];
			}
			else if (vB::$vbulletin->GPC_exists['nodeid'] AND intval(vB::$vbulletin->GPC['nodeid'] )
				and $record = vB::$vbulletin->db->query_first("SELECT contenttypeid, nodeid, parentnode FROM " .
				TABLE_PREFIX . "cms_node where nodeid = " . vB::$vbulletin->GPC['nodeid'] ))
			{
				$parentnode = vB_Types::instance()->getContentTypeID("vBCms_Section") == $record['contenttypeid'] ?
					$record['nodeid'] : $record['parentnode'];
			}

		}

		if (!($nodedm instanceof vBCms_DM_Section))
		{
			$nodedm = new vBCms_DM_Section();
			$nodedm->set('parentnode', $parentnode);
		}
		$nodedm->set('contenttypeid', vB_Types::instance()->getContentTypeID("vBCms_Section"));
		$nodedm->set('contentid', 0);
		$nodedm->set('item_id', 0);

		$title = (vB::$vbulletin->GPC_exists['section_title']?
			vB::$vbulletin->GPC['section_title'] :
			(vB::$vbulletin->GPC_exists['title']?
			vB::$vbulletin->GPC['title'] : $vbphrase['new_section']));
		$nodedm->set('title', $title);
		$nodedm->getValidURL($title);
		$nodedm->set('html_title', $title);

		//set the default configuration.
		$this->config = array();
		$this->config['items_perhomepage'] = 7;
		$this->config['section_priority'] = 2;
		$this->config['content_layout'] = 1;
		$nodedm->set('config', $this->config);

		if (!($nodeid = $nodedm->save()))
		{
			throw (new vB_Exception_Content('Failed to create default content for contenttype ' . get_class($this)));
		}
		($hook = vBulletinHook::fetch_hook('vbcms_section_defaultcontent_end')) ? eval($hook) : false;

		return $nodeid;
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
			$this->parameters['page'] = max(intval($value), 0);
		}
		else if ($parameter == 'unpublished')
		{
			$this->parameters['unpublished'] = (bool)$value AND vB::$vbulletin->check_user_permission('vbcmspermissions', 'cancreatecontent');
		}
		else
		{
			parent::assignParameter($parameter, $value);
		}
	}



	/*ItemHandling==================================================================*/

	/**
	 * Sets preloaded info from an existing item to the current content.
	 *
	 * @param vBCms_Item_Content $node
	 */
	public function castFrom(vB_Item_Content $source)
	{
		$this->content = $source;
		$this->contentid = $source->getNodeId();
	}

	/**
	 * Populates a view with the expected info from a content item.
	 *
	 * @param vB_View $view
	 * @param int $viewtype
	 */
	protected function populateViewContent(vB_View $view, $viewtype = self::VIEW_PAGE)
	{
		global $vbphrase;
		global $show;

		if ($_REQUEST['do'] == 'apply' OR $_REQUEST['do'] == 'update' OR $_REQUEST['do'] == 'movenode')
		{
			$this->checkSaveData($view);
		}
		//Make sure the proper data is loaded.
		$this->content->requireInfo(vBCms_Item_Content::INFO_BASIC  &
			vBCms_Item_Content::INFO_DEPTH & vBCms_Item_Content::INFO_CONFIG & vBCms_Item_Content::INFO_NODE &
			vBCms_Item_Content::INFO_NAVIGATION & vBCms_Item_Content::INFO_PARENTS);
		$this->content->isValid();

		($hook = vBulletinHook::fetch_hook('vbcms_section_populate_start')) ? eval($hook) : false;

		//See if we're deleting
		if ($_REQUEST['do'] == 'delete')
		{

			//We can't delete if there is content below
			if ($record = vB::$vbulletin->db->query_first("SELECT nodeid FROM " . TABLE_PREFIX .
				"cms_node WHERE parentnode = " . $this->content->getNodeId() . " limit 1")
				and intval($record['nodeid']))
			{
				return $vbphrase['cannot_delete_with_subnodes'];
			}
			$dm = $this->content->getDM();
			$dm->delete();

			$events = $this->getCleanCacheEvents();
			vB_Cache::instance()->event($events);
			vB_Cache::instance()->cleanNow();
			return $vbphrase['section_deleted'];
		}

		//We don't want the child nodes trying to save data.
		$_REQUEST['do'] = 'view';

		$view->nodeid = $this->content->getNodeId();
		$this->config = $this->getConfig();
		$view->contenttypeid = vB_Types::instance()->getContentTypeID("vBCms_Section");

		$route_info = 'section/' .	$this->content->getURLSegment();
		$view->section_list_url = vB_Route::create('vBCms_Route_List', "$route_info")->getCurrentURL();
		$view->showall = $this->content->getShowall();

		parent::populateViewContent($view, $viewtype);
		$view->publishdatelocal = vbdate(vB::$vbulletin->options['dateformat'], $this->content->getPublishDateLocal());
		$view->publishtimelocal = date(vB::$vbulletin->options['timeformat'], $this->content->getPublishDateLocal());
		$view->setpublish = $this->content->getSetPublish();
		$view->published = ($this->content->getSetPublish() AND ($this->content->getPublishDate() < TIMENOW)) ?
			1 : 0;

		if (self::VIEW_PAGE == $viewtype)
		{
			$view->unpublished = $this->parameters['unpublished'];

			if (!$this->content->canView())
			{
				return '';
			}

			$page_nav = false;
			$aggregate =  $this->aggregateContent($viewtype, $page_nav);
			$results = $aggregate['results'];
			$view_content = new vB_View_Content('vbcms_content_section_type' . $this->config['content_layout']);
			$view->result_count = count($results);
			$view_content->class = 'Section';
			$view_content->package = 'vBCms';
			$view->can_create = $this->content->canCreate();
			$view->can_publish = $this->content->canPublish();

			if ($results AND sizeof($results))
			{
				$contents = array();
				$i = 1;
				foreach ($results as $id => $result)
				{
					if (!VB_API)
					{
						$contents[$i++] = $result;
					}
					else
					{
						require_once DIR . "/includes/functions_user.php";

						//get the avatar
						if (intval($result->authorid) AND vB::$vbulletin->options['avatarenabled'])
						{
							$result->avatar = fetch_avatar_url($result->authorid);
						}

						if (!isset($result->avatar) )
						{
							$result->avatar = false;
						}

						$contents[] = $result;
					}
				}

				$view_content->result_count = count($results);
				$view_content->contents = $contents;
				$show['lightbox'] = (vB::$vbulletin->options['lightboxenabled'] AND vB::$vbulletin->options['usepopups']);

				$view->content = $view_content;

		 		$page_info = $aggregate['aggregate']->getCounts();
				if ($this->config['pagination_links'])
				{
					if ($this->config['simple_paging'])
					{
						// from $rawcount we know how many more we definitely have,
						// so we construct from that.
						$route = vB_Route::create('vBCms_Route_Content');
						$route->node = $this->content->getUrlSegment();
						$pageurl = $route->getCurrentURL();
						$view->pagenav = construct_window_page_nav (
						$this->current_page,
						6,
						intval($this->config['items_perhomepage']),
						$page_info['total'],
						$pageurl);
					}
					else if (intval($page_info['total']))
					{
						$route = vB_Route::create('vBCms_Route_Content');
						$route->node = $this->content->getUrlSegment();
						$pageurl = $route->getCurrentURL();
						$view->pagenav = construct_page_nav($this->current_page, intval($this->config['items_perhomepage']), $page_info['total'], '','','','vbcms');
					}
				}
			}
			else
			{
				$view_content->result_count = 0;
				$view->no_results_phrase = new vB_Phrase('vbcms', 'no_content_in_section');
				$view->title = $view_content->title = $this->content->getTitle();
			}
		}
		$this->content->cacheNow();
		($hook = vBulletinHook::fetch_hook('vbcms_section_populate_end')) ? eval($hook) : false;
	}


	/**
	 * Fetches views from aggregated content.
	 * Uses a minimum set of collections to fetch the required info for the content
	 * types specified.
	 *
	 * @param int $viewtype						- The viewtype to aggregate
	 * @return array vB_View
	 */
	protected function aggregateContent($viewtype = self::VIEW_PREVIEW, &$page_info = null)
	{
		if ((self::VIEW_AGGREGATE != $viewtype) AND (self::VIEW_PREVIEW != $viewtype) AND (self::VIEW_PAGE != $viewtype))
		{
			throw (new vB_Exception_Content('Viewtype specified for section aggregation is not valid: \'' . htmlspecialchars_uni($viewtype) . '\''));
		}

		$this->config = $this->content->getConfig();
		// Only filter to published if section is published and user can't edit
		$filter_published = (!$this->content->canPublish() OR ($this->content->isPublished() AND !$this->content->canEdit() AND !$this->content->canCreate()));
		$aggregate = new vBCms_Collection_Content_Section();
		$aggregate->requireInfo(vB_Model::QUERY_BASIC);

		$filter_node = $this->content->getIncludeChildren();
		//If this is a hidden section we ignore the hidden flag. Otherwise we don't show
		//hidden articles.
		$aggregate->setFilterHidden = (!$this->content->getHidden());

		if (!$this->config['pagination_links'] OR $this->config['simple_paging'])
		{
			$aggregate->setCount(false);
		}


		//This changes depending on whether we are displaying an edit or view page;
		if ($this->editing)
		{
			if (!$filter_node)
			{
				$aggregate->setFilterNodeExact($this->content->getNodeId());
			}
			else
			{
				$aggregate->filterNode($this->content->getNodeId());
			}
		}
		else //We're in view mode
		{
			//And what content to show. If the setting is 2, then that means show
			// subsection content. Otherwise only the section will show.
			if ($this->config['contentfrom'] != 2)
			{
				$aggregate->setFilterNodeExact($this->content->getNodeId());
			}
			else
			{
				$aggregate->filterNode($this->content->getNodeId());
			}
			$aggregate->setIncludepreview(true);

		}

		$aggregate->filterPublished($filter_published);
		$aggregate->requireInfo(vBCms_Item_Content::INFO_BASIC | vBCms_Item_Content::INFO_NODE);

		if ($this->canPublish())
		{
			$aggregate->filterVisible(false);
		}

		if (!intval($this->config['section_priority']) OR (intval($this->config['section_priority'])> 20) )
		{
			$this->config['section_priority'] = 1;
		}

		// Let's set the order.
		$aggregate->setOrderBy($this->config['section_priority']);

		// Set items per page. Default to 7; enforce min 1, max 100
		$this->config['items_perhomepage'] = intval($this->config['items_perhomepage']);
		$this->config['items_perhomepage'] = $this->config['items_perhomepage'] == 0 ? 7 : $this->config['items_perhomepage'];
		$this->config['items_perhomepage'] = min(max($this->config['items_perhomepage'], 1), 100);

		$aggregate->paginate();
		$aggregate->paginateQuantity(intval($this->config['items_perhomepage']));

		if ($this->config['simple_paging'])
		{
			$aggregate->setMaxRecords(10 * $this->config['items_perhomepage']);
		}

		if ($this->editing)
		{
			$aggregate->paginatePage(1);
			$this->current_page = 1;
		}
		else
		{
			//what page are we rendering?
			vB::$vbulletin->input->clean_array_gpc('r', array('page' => TYPE_INT	));
			$this->current_page = (vB::$vbulletin->GPC_exists['page'] AND intval(vB::$vbulletin->GPC['page'])) ?
				vB::$vbulletin->GPC['page'] : 1;
			$aggregate->paginatePage($this->current_page);
		}
		$results = array();

		// If we only need the aggregate view then we don't need to get specific collections
		if (self::VIEW_AGGREGATE == $viewtype)
		{
			// get info flags for generic aggregate view
			$aggregate->requireInfo($this->getViewInfoFlags(self::VIEW_AGGREGATE));

			if (!$aggregate->getShown() AND $aggregate->getTotal())
			{
				throw (new vB_Exception_404());
			}
			$rawcount = $aggregate->getTotal();

			foreach ($aggregate AS $id => $content)
			{
				// get the content controller
				$controller = vB_Types::instance()->getContentTypeController($content->getContentTypeID(), $content);

				// set preview length
				$controller->setPreviewLength(400);

				// get the aggregate view from the controller
				$results[$id] = $controller->getAggregateView();
				if ($this->config['simple_paging'] AND count($results) >= intval($this->config['items_perhomepage']) )
				{
					break;
			}
		}
		}
		else
		{
			// Aggregated collection info for individual contenttypes.
			$collection_infos = array();

			// Individual content controllers
			$controllers = array();

			// Check that there were results for the selected page
			if (!$aggregate->getShown() AND $aggregate->getTotal())
			{
				throw (new vB_Exception_404());
			}

			// Get the individual collections required for each contenttype
			foreach ($aggregate AS $id => $content)
			{
				if ($this->config['simple_paging'] AND count($results) >= intval($this->config['items_perhomepage']) )
				{
					break;
				}

				// save an ordered space for the result
				$results[$id] = true;

				// get a controller for the specific type
				$controllers[$id] = vB_Types::instance()->getContentTypeController($content->getContentTypeID(), $content);

				// get required info flags for a preview
				$info_flags = $controllers[$id]->getViewInfoFlags(self::VIEW_PREVIEW);

				// get the appropriate collection class required for the preview
				$collection_class = $controllers[$id]->getCollectionClass($info_flags);

				// create the collection
				if (!isset($collection_infos[$collection_class]))
				{
					$collection_infos[$collection_class] = array();
				}

				// don't use the same collection where the required info differs
				if (!isset($collection_infos[$collection_class][$info_flags]))
				{
					$collection_infos[$collection_class][$info_flags] =
						array('collection' => new $collection_class, 'items' => array());
				}

				// add loaded content item to appropriate collection based on the class and required info
				$collection_infos[$collection_class][$info_flags]['items'][$id] = $content;
			}

			if (!sizeof($collection_infos))
			{
				return false;
			}
			vBCMS_Permissions::loadPermissionsfrom(array_keys($results));

			$nodeids = array();
			foreach ($collection_infos AS $collection_info)
			{
				foreach ($collection_info AS $info_flags => $collection_objects)
				{
					// add the loaded items to the collection
					$collection_objects['collection']->setCollection($collection_objects['items'], $aggregate->getLoadedInfoFlags());

					// require the rich preview info
					$collection_objects['collection']->requireInfo($info_flags);

					foreach ($collection_objects['collection'] AS $id => $item)
					{
						$nodeids[] = $id;
						if (count($results) > $this->config['items_perhomepage'])
						{
							break;
						}
					}
				}
			}

			// get the views from the unique collections
			foreach ($collection_infos AS $collection_info)
			{
				foreach ($collection_info AS $info_flags => $collection_objects)
				{
					// add the loaded items to the collection
					$collection_objects['collection']->setCollection($collection_objects['items'], $aggregate->getLoadedInfoFlags());

					// require the rich preview info
					$collection_objects['collection']->requireInfo($info_flags);

					// get the final item views
					foreach ($collection_objects['collection'] AS $id => $item)
					{
						if (isset($results[$id]))
						{
							// set preview length
							$controllers[$id]->setPreviewLength(400);

							// theoretically the updated item should already be assigned to it's controller
							if (!($results[$id] = $controllers[$id]->getPreview($this->config['preview_length'])))
							{
								unset($results[$id]);
							}

						}
					}
				}
			}
		}

		return array('aggregate' => $aggregate, 'results' => $results) ;

	}


	/*** This saves the data **/
	public function checkSaveData($view)
	{
		require_once DIR . '/includes/functions_databuild.php';
		require_once DIR . '/includes/functions.php';
		fetch_phrase_group('cpcms');
		($hook = vBulletinHook::fetch_hook('vbcms_section_save_start')) ? eval($hook) : false;

		// Check if inline form was submitted
		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do' => vB_Input::TYPE_STR
		));

		//let's make sure we use the Post value of do
		vB::$vbulletin->input->clean_array_gpc('p', array(
			'do' => vB_Input::TYPE_STR,
			'per_page' => TYPE_INT,
			'new_parentid' => TYPE_INT,
			'html_title' => TYPE_STR,
			'title' => TYPE_STR,
			'displayorder' => TYPE_INT,
			'content_layout' => TYPE_INT,
			'pagination_links' => TYPE_INT,
			'contentfrom' => TYPE_INT,
			'new_parentid' => TYPE_INT,
			'simple_paging' => TYPE_INT,
			'perpage' => TYPE_INT,
		));

		if (vB::$vbulletin->GPC_exists['new_parentid'] AND intval(vB::$vbulletin->GPC['new_parentid'])
			AND (intval(vB::$vbulletin->GPC['new_parentid'] != $this->content->getParentId())) )
		{
			vBCms_ContentManager::moveSection(array($this->content->getNodeId()), vB::$vbulletin->GPC['new_parentid']);
		}

		if ($_REQUEST['do'] == 'apply' OR $_REQUEST['do'] == 'update' )
		{
			// collect error messages
			$errors = array();

			// create dm
			$dm = $this->content->getDM();
			$this->config = array();

			if (vB::$vbulletin->GPC_exists['perpage']) //This is the number displayed on the edit page
			{
				$current_user = new vB_Legacy_CurrentUser();
				if (!$stored_prefs = $current_user->getSearchPrefs())
				{
					$stored_prefs = array();
				}

				if (vB::$vbulletin->GPC_exists['perpage'] AND intval(vB::$vbulletin->GPC['perpage']))
				{
					$stored_prefs['cmsadmin_showperpage'] = intval(vB::$vbulletin->GPC['perpage']);
					$current_user->saveSearchPrefs($stored_prefs);
				}
			}

			if (vB::$vbulletin->GPC_exists['per_page']) //This is the number of items displayed to the end user in the view
			{
				$this->config['items_perhomepage'] = vB::$vbulletin->GPC['per_page'];

				// Set items per page. Default to 7; enforce min 1, max 100
				$this->config['items_perhomepage'] = intval($this->config['items_perhomepage']);
				$this->config['items_perhomepage'] = $this->config['items_perhomepage'] == 0 ? 7 : $this->config['items_perhomepage'];
				$this->config['items_perhomepage'] = min(max($this->config['items_perhomepage'], 1), 100);
			}

			if (vB::$vbulletin->GPC_exists['displayorder'])
			{
				$this->config['section_priority'] = vB::$vbulletin->GPC['displayorder'];
			}

			if (vB::$vbulletin->GPC_exists['content_layout'])
			{
				$this->config['content_layout'] = vB::$vbulletin->GPC['content_layout'];
			}

			if (vB::$vbulletin->GPC_exists['title'])
			{
				$this->config['title'] = vB::$vbulletin->GPC['title'];
			}

			if (vB::$vbulletin->GPC_exists['pagination_links'])
			{
				$this->config['pagination_links'] = vB::$vbulletin->GPC['pagination_links'];
			}


			if (vB::$vbulletin->GPC_exists['simple_paging'])
			{
				$this->config['simple_paging'] = 1;
			}

			$this->config['contentfrom'] = (vB::$vbulletin->GPC_exists['contentfrom']
				AND (vB::$vbulletin->GPC['contentfrom'] == 1)) ? 1 : 2;

			if (count($this->config))
			{
				$dm->set('config', $this->config);
				$this->content->setConfig($this->config);
			}
			$dm->saveFromForm($this->content->getNodeId());

			if ($dm->hasErrors())
			{
				$fieldnames = array(
					'title' => new vB_Phrase('global', 'title')
				);

				$view->errors = $dm->getErrors(array_keys($fieldnames));
				$view->error_summary = self::getErrorSummary($dm->getErrors(array_keys($fieldnames)), $fieldnames);
				$view->status = $view->error_view->title;
			}
			else
			{
				$view->status = new vB_Phrase('vbcms', 'content_saved');

				// reroute to the section
				$route = new vBCms_Route_Content();
				$route->node = $this->content->getUrlSegment();
				$url = $route->getCurrentUrl();

				($hook = vBulletinHook::fetch_hook('vbcms_section_save_end')) ? eval($hook) : false;
			}

		}
		$this->changed = true;
		($hook = vBulletinHook::fetch_hook('vbcms_section_save_end')) ? eval($hook) : false;
		//invalidate the navigation cache.
		vB_Cache::instance()->event('sections_updated');
		$this->cleanContentCache();
		$this->content->requireInfo(vBCms_Item_Content::INFO_BASIC  &
			vBCms_Item_Content::INFO_DEPTH & vBCms_Item_Content::INFO_CONFIG & vBCms_Item_Content::INFO_NODE &
			vBCms_Item_Content::INFO_NAVIGATION & vBCms_Item_Content::INFO_PARENTS);
		$this->content->isValid();
		$this->content->invalidateCached();
	}

	/**
	 * Fetches a rich page view of the specified content item.
	 * This method can accept parameters from the client code which are usually
	 * derived from user input.  Parameters are passed as an array in the order that
	 * they were received.  Parameters do not normally have assoc keys.
	 *
	 * Note: Parameters are always passed raw, so ensure that validation and
	 * escaping is performed where required.
	 *
	 * Skip permissions should allow content to be rendered regardless of the
	 * current user's permissions.
	 *
	 * Child classes will inevitably override this with wildly different
	 * implementations.
	 *
	 * @param array mixed $parameters			- Request parameters
	 * @param bool $skip_permissions			- Whether to skip can view permission checking
	 * @return vB_View | bool					- Returns a view or false
	 */
	public function getInlineEditBodyView($parameters = false)
	{
		global $vbphrase;
		require_once DIR . '/includes/functions.php';
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');
		$this->editing = true;

		//confirm that the user has edit rights
		if (!$this->content->canPublish())
		{
			return new vB_Phrase('cpcms', 'no_edit_permissions');
		}

		$this->content->requireInfo(vBCms_Item_Content::INFO_BASIC  &
			vBCms_Item_Content::INFO_CONFIG & vBCms_Item_Content::INFO_NODE &
			vBCms_Item_Content::INFO_NAVIGATION & vBCms_Item_Content::INFO_PARENTS);
		$this->content->isValid();
		$config = $this->content->getConfig();

		if ($_REQUEST['do'] == 'apply' OR $_REQUEST['do'] == 'update' OR $_REQUEST['do'] == 'movenode')
		{
			$this->checkSaveData($view);
			unset($_REQUEST['do']);
		}

		$this->content->requireInfo(vBCms_Item_Content::INFO_BASIC  &
			vBCms_Item_Content::INFO_CONFIG & vBCms_Item_Content::INFO_NODE &
			vBCms_Item_Content::INFO_NAVIGATION & vBCms_Item_Content::INFO_PARENTS);
		$this->content->isValid();
		$config = $this->content->getConfig();

		//See if we're deleting
		if ($_REQUEST['do'] == 'delete')
		{
			//We can't delete if there is content below
			if ($record = vB::$vbulletin->db->query_first("SELECT nodeid FROM " . TABLE_PREFIX .
				"cms_node WHERE parentnode = " . $this->content->getNodeId() . " limit 1")
				and intval($record['nodeid']))
			{
				return new vB_Phrase('cpcms', 'cannot_delete_with_subnodes');
			}
			$dm = $this->content->getDM();
			$dm->delete();
			$events = $this->getCleanCacheEvents();
			vB_Cache::instance()->event($events);
			vB_Cache::instance()->cleanNow();
			return new vB_Phrase('cpcms', 'section_deleted');
		}
		vB::$vbulletin->input->clean_array_gpc('r', array(
		'sortby' => vB_Input::TYPE_STR,
		'dir' => vB_Input::TYPE_STR,
		'page' => vB_Input::TYPE_INT,
		'item_count' => vB_Input::TYPE_INT,
		'per_page' => TYPE_INT,
		'simple_paging' => TYPE_INT,
		'page' => TYPE_INT
			));

		// Load the content item
		if (!$this->loadContent($this->getViewInfoFlags(self::VIEW_PAGE)))
		{
			throw (new vB_Exception_404());
		}

		// Create view
		$view = $this->createView('inline', self::VIEW_PAGE);

		// Add the content to the view
		parent::populateViewContent($view, self::VIEW_PAGE);

		$this->config = $this->getConfig();

		$view->formid = 'cms_content_data';
		$view->title = $this->content->getTitle();
		$view->html_title = $this->content->getHtmlTitle();
		$view->url = $this->content->getUrl();
		$view->contentfrom = $this->config['contentfrom'];
		$view->editshowchildren = $this->content->getEditShowchildren() ? 1 : 0;
		$view->layout_select = vBCms_ContentManager::getLayoutSelect($this->content->getLayoutSetting(), $this->getParentId());
		$view->style_select = vBCms_ContentManager::getStyleSelect($this->content->getStyleSetting()) ;
		$view->display_order_select = vBCms_ContentManager::getSectionPrioritySelect($this->config['section_priority']) ;
		$view->content_layout_select = $tmp = vBCms_ContentManager::getContentLayoutSelect($this->config['content_layout']);
		$view->simple_paging = $this->config['simple_paging'];
		$view->per_page = $this->config['items_perhomepage'];
		$view->nodeid = $this->content->getNodeId();
		$view->dateformat = vB::$vbulletin->options['dateformat'] . " " . vB::$vbulletin->options['timeformat'];

		if (intval($this->content->getPublishDate))
		{
			$view->publishdate = $this->content->getPublishDate();
		}

		$aggregate = new vBCms_Collection_Content_Section();
		switch(vB::$vbulletin->GPC['sortby'])
		{
			case 'title' :
				$aggregate->setSortBy('ORDER BY title ' . vB::$vbulletin->GPC['dir']);
				break;
			case 'setpublish' :
				$aggregate->setSortBy('ORDER BY setpublish ' . vB::$vbulletin->GPC['dir']);
				break;
			case 'displayorder' :
				$aggregate->setSortBy('ORDER BY displayorder ' . vB::$vbulletin->GPC['dir']);
				break;
			case 'username' :
				$aggregate->setSortBy('ORDER BY username ' . vB::$vbulletin->GPC['dir']);
				break;
			case 'publishdate' :
				$aggregate->setSortBy('ORDER BY publishdate ' . vB::$vbulletin->GPC['dir']);
				break;
			case 'pageviews' :
				$aggregate->setSortBy('ORDER BY viewcount ' . vB::$vbulletin->GPC['dir']);
				break;
			case 'replycount':
				$aggregate->setSortBy('ORDER BY replycount ' . vB::$vbulletin->GPC['dir']);
				;
				break;
			case 'section':
				$aggregate->setSortBy('ORDER BY parenttitle ' . vB::$vbulletin->GPC['dir']);
				;
				break;
			default:
		$aggregate->setOrderBy(1);
			;
		} // switch

		//See if we need to hide the children
		$filter_node = $this->content->getEditShowchildren();
		if (!$filter_node)
		{
			$aggregate->setFilterNodeExact($this->content->getNodeId());
		}
		else
		{
			$aggregate->filterNode($this->content->getNodeId());
		}
		$nodes = array();
		$sequence = 0;

		$candelete = 1;

		// Disallow deleting the root cms node
		// This uses nodeid == 1 to follow how the Admin CP content manager identifies
		// the root node. It should probably check for a null parent node or if it is the last
		// node record for the section content type instead of hard-coding the node id
		if ($this->content->getNodeId() == 1)
		{
			$candelete = 0;
		}

		if (vB::$vbulletin->GPC_exists['perpage'] AND intval(vB::$vbulletin->GPC['perpage']))
		{
			$perpage = vB::$vbulletin->GPC['perpage'];
		}
		else
		{
			$perpage = vBCms_ContentManager::getPerPage(new vB_Legacy_CurrentUser());
		}

		$current_page = (vB::$vbulletin->GPC_exists['page'] AND intval(vB::$vbulletin->GPC['page']) ) ?
			vB::$vbulletin->GPC['page'] : 1;
		$aggregate->paginate();
		$aggregate->paginateQuantity($perpage);
		$aggregate->paginatePage($current_page);

		foreach ($aggregate as $id => $content_node)
		{
			$candelete = 0;

			if ($content_node->getContentTypeid() != vb_Types::instance()->getContentTypeID("vBCms_Section") )
			{
				$sequence++;
				$nodes[] = array('sequence' => $sequence,
				'class' => $content_node->getClass(),
				'title' => $content_node->getTitle(),
				'html_title' => $content_node->getHtmlTitle(),
				'nodeid' => $content_node->getNodeid(),
				'prev_checked' =>	($content_node->getPublicPreview() ? " checked=\"checked\" " : ''),
				'publicpreview' => $content_node->publicpreview,
				'parenttitle' => $content_node->getParentTitle(),
				'published_select' => vBCms_ContentManager::getPublishedSelect($content_node->getSetPublish(), $content_node->getPublishDate()),
				'order_select' =>  vBCms_ContentManager::getOrderSelect($content_node->getDisplayOrder($this->content->getNodeId()),
					$this->content->getNodeId()),
				'author' => $content_node->getUsername(),
				'pub_date' =>  (intval($content_node->getPublishDate()) ? vbdate(vB::$vbulletin->options['dateformat'], $content_node->getPublishDate()) : '') ,
				'viewcount' => $content_node->getViewCount(),
				'view_url' => vBCms_Route_Content::getURL(array('node' => $content_node->getUrlSegment())),
				'replycount' => $content_node->getReplyCount());
			}
		}

		if (vB::$vbulletin->GPC_exists['item_count'])
		{
				$item_count = vB::$vbulletin->GPC['item_count'];
		}
		else
		{
			$aggregate->filterNoSections(1);
			$item_count = $aggregate->getCount();
		}

		$segments = array('node' => $this->content->getUrlSegment(),
							'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'View'));
		$view->view_url = vBCms_Route_Content::getURL($segments);
		$segments = array('node' => $this->content->getUrlSegment(),
							'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'EditPage'));
		$view->submit_url = vBCms_Route_Content::getURL($segments);
		$base_url = $view->submit_url;
		$base_url .=  strpos($base_url, '?') ? '&amp;' : '?';

		$view->record_count = count($aggregate);
		$view->item_count = $item_count;

		$pagination = construct_page_nav($current_page, $perpage, $item_count, $view->submit_url);
		$view->pagination = $pagination;

		$perpage_select .= '<select name="perpage" onchange="checkShouldSave(\'' .
			$view->formid . '\', \'perpage\', \'' . vB_Template_Runtime::escapeJS(new vB_Phrase('cpcms', 'confirm_save_section')) .
			 '\', \'' . vB_Template_Runtime::escapeJS($view->submit_url)  . '\');">' . "\n";
		foreach (array(5,10,15,20,25,50,75,100,200, 250, 500) as $this_perpage)
		{
			$perpage_select .= "<option value=\"$this_perpage\""
				. (intval($this_perpage) == intval($perpage) ? ' selected="selected" ' : '')
				. ">$this_perpage</option>\n" ;
		}
		$perpage_select	.= "</select>";
		$view->perpage_select = $perpage_select;

		$record = vB::$vbulletin->db->query_first("SELECT SUM(childinfo.viewcount) AS viewcount,
          SUM(CASE when child.contenttypeid <> " . vb_Types::instance()->getContentTypeID("vBCms_Section") ." THEN 1 ELSE 0 END) AS content,
          SUM(CASE when (child.parentnode = node.nodeid AND child.contenttypeid <> " . vb_Types::instance()->getContentTypeID("vBCms_Section") .") THEN 1 ELSE 0 END) AS children,
          SUM(CASE when child.contenttypeid =" . vb_Types::instance()->getContentTypeID("vBCms_Section") ." AND child.parentnode = node.nodeid THEN 1 ELSE 0 END) AS subsections
				FROM " . TABLE_PREFIX . "cms_node AS node
				LEFT JOIN " . TABLE_PREFIX . "cms_node AS child ON (child.nodeleft >= node.nodeleft AND child.nodeleft <= node.noderight AND child.nodeid <> node.nodeid AND child.new != 1)
				LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo AS childinfo ON childinfo.nodeid = child.nodeid AND child.contenttypeid <> " . vb_Types::instance()->getContentTypeID("vBCms_Section") ."
				WHERE node.nodeid = " . $this->content->getNodeId());
		$view->viewcount = $record['viewcount'];
		$view->content = $record['content'];
		$view->children = $record['children'];
		$view->subsections = $record['subsections'];

		$view->nodes = $nodes;

		$view->metadata = $this->content->getMetadataEditor();

		//Here we create some url's. This should allow to sort in reverse direction

		$view->sorttitle_url = $base_url . 'sortby=title&dir=' .
			((vB::$vbulletin->GPC_exists['sortby'] AND vB::$vbulletin->GPC['sortby'] == 'title'
				AND vB::$vbulletin->GPC['dir'] == 'asc') ? 'desc' : 'asc');
		$view->sortpub_url = $base_url . 'sortby=setpublish&dir=' .
			((vB::$vbulletin->GPC_exists['sortby'] AND vB::$vbulletin->GPC['sortby'] == 'setpublish'
				AND vB::$vbulletin->GPC['dir'] == 'asc') ? 'desc' : 'asc');
		$view->sortorder_url = $base_url . 'sortby=displayorder&dir=' .
			((vB::$vbulletin->GPC_exists['sortby'] AND vB::$vbulletin->GPC['sortby'] == 'displayorder'
				AND vB::$vbulletin->GPC['dir'] == 'asc') ? 'desc' : 'asc');
		$view->sortauthor_url = $base_url . 'sortby=username&dir=' .
			((vB::$vbulletin->GPC_exists['sortby'] AND vB::$vbulletin->GPC['sortby'] == 'username'
				AND vB::$vbulletin->GPC['dir'] == 'asc') ? 'desc' : 'asc');
		$view->sortdate_url = $base_url . 'sortby=publishdate&dir=' .
			((vB::$vbulletin->GPC_exists['sortby'] AND vB::$vbulletin->GPC['sortby'] == 'publishdate'
				AND vB::$vbulletin->GPC['dir'] == 'asc') ? 'desc' : 'asc');
		$view->sorthits_url = $base_url . 'sortby=pageviews&dir=' .
			((vB::$vbulletin->GPC_exists['sortby'] AND vB::$vbulletin->GPC['sortby'] == 'pageviews'
				AND vB::$vbulletin->GPC['dir'] == 'asc') ? 'desc' : 'asc');
		$view->sortreplycount_url = $base_url . 'sortby=replycount&dir=' .
			((vB::$vbulletin->GPC_exists['sortby'] AND vB::$vbulletin->GPC['sortby'] == 'replycount'
				AND vB::$vbulletin->GPC['dir'] == 'asc') ? 'desc' : 'asc');
		$view->sortsection_url = $base_url . 'sortby=section&dir=' .
			((vB::$vbulletin->GPC_exists['sortby'] AND vB::$vbulletin->GPC['sortby'] == 'section'
				AND vB::$vbulletin->GPC['dir'] == 'asc') ? 'desc' : 'asc');
		$view->editbar = $this->content->getEditBar($view->submit_url, $view->view_url, $view->formid, 0,
				(intval($this->content->getNodeId()) ? 'edit' : 'add'), $candelete);
		$view->publisher = $this->content->getPublishEditor($view->submit_url, $view->formid, false,
			false, false, false, $this->config['pagination_links']);

		$view->contenttypeid = vB_Types::instance()->getContentTypeID("vBCms_Section");

		$this->addPostId($view);

		// Sub menu
		if (!$this->content->isRoot())
		{
			$view->inherit_section = $this->content->getNavigationParentTitle();
			$view->inherited = !$this->content->hasNavigation();
		}

		$navigation_nodes = $this->content->getNavigationNodes();

		$subnav_nodes = vBCms_ContentManager::getSections(false, true);


		// array for the navigation display order drop-down menu
		$displayorder_array = array(0 => '');
		$count = max(count($nodes), 40);
		for ($i=1; $i <= $count; $i++)
		{
			$displayorder_array[$i] = $i;
		}

		// populate sub-nav configuration menu with all cms sections
		$sections = array();

		$subnav = new vB_View('vbcms_content_section_subnavedit');
		$subnav->displayorder_array = $displayorder_array;
		foreach ($subnav_nodes AS $node)
		{
			$nodeid = $node['nodeid'];

			// check if the section has already been selected for the menu nav
			// if so, its position in the array (key+1) is its display order
			$displayorder = 0; //default display order is 0
			$selected = false;
			if (isset($navigation_nodes) AND is_array($navigation_nodes))
			{
				if ($selected = in_array($nodeid, $navigation_nodes))
				{
					$displayorder = array_search($nodeid, $navigation_nodes) + 1;
				}
			}

			$sections[] = array('id' => $nodeid, 'title' => $node['title'], 'depth' => $node['depth'], 'selected' => $selected, 'displayorder' => $displayorder);
		}
		$subnav->sections = $sections;
		$subnav_rendered = $subnav->render();
		$view->subnav = $subnav_rendered;
		unset($nodes, $subnav_nodes, $sections);
		return $view;
	}
	/*** This function sets the parent node for creating a new article
	 ****/
	public function setParentNode($parentnode)
	{

		$this->parent_node = $parentnode;
	}


	/*Accessors=====================================================================*/

	/**
	 * Gets the config for the section.
	 *
	 * @return array mixed
	 */
	public function getConfig()
	{
		if (!isset($this->content))
		{
				return false;
		}
		$this->config = $this->content->getConfig();

		$this->config['content_layout'] = (isset($this->config['content_layout']) AND $this->config['content_layout']) ? $this->config['content_layout'] : '1';

		return $this->config;
	}


	/**
	 * Gets the class identifier of the content.
	 *
	 * @return string
	 */
	public function getClass()
	{
		return $this->class;
	}


	/**
	 * Gets the package identifier of the content.
	 *
	 * return string
	 */
	public function getPackage()
	{
		return $this->package;
	}


	/**
	 * Get the preview.
	 *
	 * return string
	 */
	public function getPreview()
	{
		return false;
	}

	/*Cache=========================================================================*/

	/**
	 * Gets the events that need to be cleaned when the content is updated.
	 * Add a generic 'sections_updated' event.  Useful for widgets.
	 */
	protected function getCleanCacheEvents()
	{
		$events = parent::getCleanCacheEvents();
		foreach ($this->content->getCacheEvents() as $event)
		{
			$events[] = $event;
		}
		$events[] = 'sections_updated';

		return array_unique($events);
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/
