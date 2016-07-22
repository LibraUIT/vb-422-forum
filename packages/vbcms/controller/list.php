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
 * Main CMS Page Controller
 * Page controller with actions to view nodes, edit nodes, edit content, add and
 * delete content.
 *
 * @TODO: Generalise some of the stuff that's done in multiple actions.  This class
 * is still a rough merge of various controllers into action methods.
 *
 * @TODO: We have to abstract the overlay stuff somehow so that config views can be
 * rendered as part of a html page; and to make overlay views easier to work with.
 *
 * @author vBulletin Development Team
 * @version $Revision: 77258 $
 * @since $Date: 2013-09-02 17:14:45 -0700 (Mon, 02 Sep 2013) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Controller_List extends vBCms_Controller
{
	/*Properties====================================================================*/

	/**
	 * The package that the controller belongs to.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	/**
	 * The class string id that identifies the controller.
	 *
	 * @var string
	 */
	protected $class = 'List';

	/**
	 * The action definitions for the controller.
	 *
	 * @var array string => bool
	 */
	protected $actions = array(
		'list'
	);


	/** styleid int ***/
	protected $styleid;

	/** layoutid, int ***/
	protected $layoutid;

	/** userid of requested author if applicable, int ***/
	protected $authorid;

	/** requested category if applicable, int ***/
	protected $categoryid;

	/** id of requested section if applicable, int ***/
	protected $sectionid;

	/** sql filter, string ***/
	protected $query_filter = '';

	/** sql joins, string ***/
	protected $joins = '';

	/** id of section which will be used to resolve style and layout,  int ***/
	protected $displaysectionid = 1;

	/** number of items to be displayed per page, int ***/
	protected $perpage = 5;

	/** title of page, string ***/
	protected $title = '';

	/** type of result- object ***/
	protected $result_type;

	protected $urlstring = '';

	protected $content;

	/*Initialization================================================================*/

	public function __construct($parameters, $action = 'list')
	{
		$this->segments = $parameters;
		$this->action = $action;
		// Evaluate the node that we're working with
		$this->initialize();
		$this->registerXHTMLTemplater();
		$this->parameters = $parameters;
	}


	/**
	 * Initialisation.
	 * Initialises the view, templaters and all other necessary objects for
	 * successfully creating the response.
	 */
	protected function initialize()
	{
		// Setup the templater.  Even XML output needs this for the html response
		//First we need a node from which we can get a style.

		//We need to set sectionid, style, and layout
		global $vbphrase;
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('vbcms');

		//we are going to use simple paging, so let' limit the number of records return.

		if (count($this->segments) < 2)
		{
			$value = 1;
		}
		else
		{
			$value = max(1, intval($this->segments['value']));
			}
			switch($this->segments['type'])
		{
			case 'author':
				//if we were passed a parameter for fromsection, we use that.
				//or it could be the fourth parameter.
				vB::$vbulletin->input->clean_array_gpc('r', array(
					'fromsection' => TYPE_INT,
					'sectionid' => TYPE_INT,
					));


				$this->query_filter = " AND node.userid = " . intval($value);

				if (vB::$vbulletin->GPC_exists['fromsection'] AND intval(vB::$vbulletin->GPC['fromsection']))
				{
					$displaysectionid = intval(vB::$vbulletin->GPC['fromsection']);
				}
				//we haven't got a section.
				else $displaysectionid = "SELECT MIN(nodeid) AS nodeid FROM " . TABLE_PREFIX . "cms_node WHERE
					parentnode IS NULL";
				;
				if ($record = vB::$vbulletin->db->query_first("SELECT username FROM " .TABLE_PREFIX .
					"user WHERE userid = $value" ))
				{
					$this->title = $record['username'];
					$this->urlstring = $this->title;
				}
				$this->result_type = $vbphrase['author'];
				break;
			case 'section':

				$this->query_filter = " AND node.parentnode = " . $value;
				$displaysectionid = $value;


				if ($record = vB::$vbulletin->db->query_first("SELECT title FROM " .TABLE_PREFIX .
					"cms_nodeinfo WHERE nodeid = $value" ))
				{
					$this->title = $record['title'];
					$this->urlstring = urlencode($this->title);
				}

				$this->result_type = $vbphrase['section'];
				break;

			case 'category':

				$record = vB::$vbulletin->db->query_first("SELECT category, parentnode FROM " .TABLE_PREFIX .
				"cms_category WHERE categoryid = $value") ;
				if (!$record)
				{
					$record = vB::$vbulletin->db->query_first("SELECT category, parentnode, categoryid FROM " .TABLE_PREFIX .
					"cms_category LIMIT 1") ;
					if ($record)
					{
						$this->segments['value'] = $value = $record['categoryid'];
					}

				}
				if (!$record)
				{
					throw (new vB_Exception_User(new vB_Phrase('error', 'no_categories_defined')));
				}
				$this->joins = " INNER JOIN " . TABLE_PREFIX . "cms_nodecategory AS nodecat ON nodecat.nodeid = node.nodeid
						 AND nodecat.categoryid = $value" ;

				$this->title = $record['category'];
				$this->urlstring = urlencode($record['category']);
				$displaysectionid = $record['parentnode'];

				$this->result_type = $vbphrase['category'];
				break;

			case 'day':
				//Here we displaying for a specific day. We need to get the date range for the
				//where clause. We have nothing to set the $displaysectionid, so set it to false
				$displaysectionid = false;

				//default date to today
				if (! (intval($value) > 1000000))
				{
					$value = TIMENOW;
				}

				$this->query_filter = " AND node.setpublish > 0 AND (node.publishdate BETWEEN $value  AND " .
					 ($value + 86399) . ") " ;
				$this->title = vbdate( vB::$vbulletin->options['dateformat'], $value);
				$this->urlstring = urlencode($value);

				$displaysectionid = "SELECT MIN(nodeid) AS nodeid FROM " . TABLE_PREFIX . "cms_node WHERE
					parentnode IS NULL";
				$this->result_type = $vbphrase['date'];
				break;

			default:
				//we haven't got a section.
				$displaysectionid = "SELECT MIN(nodeid) AS nodeid FROM " . TABLE_PREFIX . "cms_node WHERE
					parentnode IS NULL";
				;
				if ($record = vB::$vbulletin->db->query_first("SELECT title FROM " .TABLE_PREFIX .
					"cms_nodeinfo WHERE nodeid = ($displaysectionid)"))
				{
					$this->title = $record['title'];
					$this->result_type = $vbphrase['section'];
					$this->urlstring = urlencode($this->title );
				}
				$this->result_type = $vbphrase['section'];
			;
		} // switch

		if (! is_numeric($displaysectionid) )
		{

			$record = vB::$vbulletin->db->query_first($displaysectionid);
			$displaysectionid = $record['nodeid'];
		}

		$rst = vB::$vbulletin->db->query_read("SELECT parent.nodeid, parent.styleid, parent.layoutid from " .
			TABLE_PREFIX . "cms_node AS node	INNER JOIN " . TABLE_PREFIX .
			"cms_node AS parent ON (node.nodeleft >= parent.nodeleft AND node.nodeleft <= parent.noderight)
			   WHERE node.nodeid = " . $displaysectionid . "
			 ORDER BY parent.nodeleft DESC ;");

		$record = vB::$vbulletin->db->fetch_array($rst);

		$this->sectionid = $record['nodeid'];

		$node = vBCms_Item_Content::create('vBCms', 'Section', $displaysectionid);

		if (! $node->canView())
		{
			throw (new vB_Exception_AccessDenied());
		}
		$node->requireInfo(vBCms_Item_Content::INFO_NODE);
		vBCms_NavBar::prepareNavBar($node);

		while($record)
		{
			if (intval($record['layoutid']) AND !intval($this->layoutid))
			{
				$this->layoutid = $record['layoutid'];
			}
			if (intval($record['styleid']) AND !intval($this->styleid))
			{
				$this->styleid = $record['styleid'];
			}

			if (intval($this->layoutid))
			{
				$this->displaysectionid = $displaysectionid;
				return;
			}

			$record = vB::$vbulletin->db->fetch_array($rst);
		}

		if (!intval($this->styleid))
		{
			$this->styleid = vB::$vbulletin->options['styleid'];
		}

		if (!intval($this->layoutid))
		{
			$this->layoutid = 1;
		}

	}

	/*Actions=======================================================================*/


	/**** returns the title
	 *
	 * @return string
	 ****/
	public function getTitle()
	{
		return $this->title;
	}

	/**** renders the page. Called from the controller
	 * @param string
	 *
	 * @return view
	 ****/

	public function actionList($page_url)
	{
		//This is an aggregator. We can pull in three different modes as of this writing,
		// and we plan to add more. We can have passed on the url the following:
		// author=id, category=id, section=id, and format=id. "Format" should normally
		// be passed as for author only, and it defines a sectionid to be used for the format.

		global $vbphrase;
		//Load cached values as appropriate
		$metacache_key = 'vbcms_list_data_' . implode('_', $this->segments);
		vB_Cache::instance()->restoreCacheInfo($metacache_key);

		// Create the page view
		$view = new vB_View_Page('vbcms_page');

		$view->page_url = $page_url;
		$view->base_url = VB_URL_BASE_PATH;
		$view->html_title = $this->title;

		$this->content = vBCms_Content::create('vBCms', 'Section', $this->displaysectionid);
		$sectionnode = new vBCms_Item_Content($this->displaysectionid, vBCms_Item_Content::INFO_CONFIG) ;
		//There are configuration settings on the section for items per page and
		// section layout. If they are set let's use them. First items per page.
		$this->config = $sectionnode->getConfig();

		if (isset($this->config['items_perhomepage']) AND intval($this->config['items_perhomepage']))
		{
			$this->perpage = $this->config['items_perhomepage'];
		}

		// Get layout
		$this->layout = new vBCms_Item_Layout($this->layoutid);
		$this->layout->requireInfo(vBCms_Item_Layout::INFO_CONFIG | vBCms_Item_Layout::INFO_WIDGETS);
		// Create the layout view
		$layout = new vBCms_View_Layout($this->layout->getTemplate());
		$layout->contentcolumn = $this->layout->getContentColumn();
		$layout->contentindex = $this->layout->getContentIndex();

		// Get content controller
		$collection = new vBCms_Collection_Content();

		$collection->filterPublished(!$this->content->canPublish());
		$collection->setContentQueryWhere($this->query_filter . " AND node.contenttypeid <> "
			. vB_Types::instance()->getContentTypeID("vBCms_Section")	);
		$collection->setContentQueryJoins($this->joins);

		vB::$vbulletin->input->clean_array_gpc('r', array('page' => TYPE_INT));
		if ((vB::$vbulletin->GPC_exists['page'] AND intval(vB::$vbulletin->GPC['page'])))
		{
			$current_page = intval(vB::$vbulletin->GPC['page']);
		}
		elseif (intval($this->segments['page']))
		{
			$current_page = intval($this->segments['page']);
		}
		else
		{
			$current_page = 1;
		}


		$collection->paginate();
		$collection->paginateQuantity($this->perpage);
		$collection->paginatePage($current_page);

		$results = array();
		// Get the content view
		//Our templates assume a counter beginning at one, not zero.
		$counter = 0;
		foreach($collection as $id => $content)
		{
			//make sure we've loaded all the information we need
			$content->requireInfo(vBCms_Item_Content::INFO_NODE | vBCms_Item_Content::INFO_CONTENT | vBCms_Item_Content::INFO_PARENTS);
			// get the content controller
			$controller = vB_Types::instance()->getContentTypeController($content->getContentTypeID(), $content);

			// set preview length
			$controller->setPreviewLength(400);

			// get the aggregate view from the controller
			if ($result = $controller->getPreview())
			{
				if (!VB_API)
				{
					$counter++;
					$results[$counter] = $result;
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

					$results[] = $result;
				}
			}
		}

		$recordcount = $collection->getCount();
		$contentview = new stdClass();
		$contentview->contenttypeid = vB_Types::instance()->getContentTypeID("vBCms_Section");
		$contentview->contentid = $contentview->item_id = $contentview->nodeid = $this->displaysectionid;
		$contentview = new vB_View_Content('vbcms_content_list');
		$contentview->package = 'vBCms';
		$contentview->class = 'Section';
		$contentview->result_type = $this->result_type;
		$contentview->rawtitle = $this->title;
		$contentview->title = $this->title;
		$contentview->current_page = $current_page;

		if (! $recordcount)
		{
			switch($this->segments['type']){
				case 'author':
					$contentview->contents = array(1 => new vB_Phrase('vbcms', 'no_content_for_author_x', $this->title ));
					break;
				case 'section':
					$contentview->contents = array(1 => new vB_Phrase('vbcms', 'no_content_for_section_x', $this->title ));
					break;
				case 'category':
					$contentview->contents = array(1 => new vB_Phrase('vbcms', 'no_content_for_category_x', $this->title ));
					break;
				;
			} // switch

		}
		else
		{
			if (isset($this->config['content_layout']) AND intval($this->config['content_layout'])
				AND (intval($this->config['content_layout']) < 7))
			{
				$content_rendered = new vb_View('vbcms_content_section_type' . intval($this->config['content_layout']));
				$content_rendered->contents = $results;
				$content_rendered->result_count = $counter;
				$contentview->content_rendered = $content_rendered;
			}
			else
			{
				$contentview->contents = $results;
			}
			if (intval($recordcount) > intval($this->perpage))
			{
				$baseurl = vB_Route::create('vBCms_Route_List', $this->segments['type'] .
						'/' . intval($this->segments['value']) . ($this->urlstring == '' ? '' : '-' . urldecode($this->urlstring)) .
						'/')->getCurrentURL();
				$contentview->pagenav = construct_page_nav($current_page, $this->perpage, $recordcount, $baseurl);
			}
			else
			{
				$contentview->pagecount = 1;
			}

		}

		$layout->content = $contentview;

		// Get widget locations
		$layout->widgetlocations = $this->layout->getWidgetLocations();

		if (count($layout->widgetlocations))
		{

			$widgetids = $this->layout->getWidgetIds();

			if (count($widgetids))
			{
				// Get Widgets
				$widgets = vBCms_Widget::getWidgetCollection($widgetids, vBCms_Item_Widget::INFO_CONFIG, $this->displaysectionid);
				$widgets = vBCms_Widget::getWidgetControllers($widgets, true, $this->content);

				// Get the widget views
				$widget_views = array();
				foreach($widgets AS $widgetid => $widget)
				{
					try
					{
						$widget_views[$widgetid] = $widget->getPageView();
					}
					catch (vB_Exception $e)
					{
						if ($e->isCritical())
						{
							throw ($e);
						}

						if (vB::$vbulletin->debug)
						{
							$widget_views[$widgetid] = 'Exception: ' . $e;
						}
					}
				}

				// Assign the widgets to the layout view
				$layout->widgets = $widget_views;

			}
		}
		// Assign the layout view to the page view
		$view->layout = $layout;

		// Add general page info
		$view->setPageTitle($this->content->getTitle());
		$view->pagedescription = $this->content->getDescription();

		$this->resolveWolPath();
		vB_Cache::instance()->saveCacheInfo($metacache_key);

		// Render view and return
		return $view->render();
	}
	/**
	 * Views the page in edit mode
	 *
	 * @return string
	 */

	/**
	 * Builds the value segment for URLs.
	 *
	 * @param int $id							- The integer value
	 * @param string $title						- The string title
	 * @return string							- The normalized result
	 */
	protected function buildValueSegment($id, $title = false)
	{
		if (!$title)
		{
			return $id;
		}

		return $id . '-' . $title;
	}

	/**
	 * Sets up the XHTML templater.
	 */
	protected function registerXHTMLTemplater()
	{
		// Create the standard vB templater
		$templater = new vB_Templater_vB();

		global $bootstrap;
		$bootstrap->force_styleid($this->styleid);
		$bootstrap->load_style();
		
		// Register the templater to be used for XHTML
		vB_View::registerTemplater(vB_View::OT_XHTML, new vB_Templater_vB());
	}


	/**
	 * Sends an AJAXHTML save failed message.
	 *
	 * @param vB_View $view
	 * @param string $debug_message
	 */
	protected function saveError(vB_View_AJAXHTML $view, $debug_message)
	{
		if ($debug_message)
		{
			$view->addError($debug_message, 'debug');
		}

		$view->setStatus(vB_View_AJAXHTML::STATUS_MESSAGE, new vB_Phrase('vbcms', 'save_failed'));

		return $view->render(true);
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77258 $
|| ####################################################################
\*======================================================================*/