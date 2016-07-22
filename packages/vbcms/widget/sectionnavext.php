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
 * vBCms_Widget_Nav
 *
 * @package
 * @author ebrown
 * @copyright Copyright (c) 2009
 * @version $Id: sectionnavext.php 77258 2013-09-03 00:14:45Z pmarsden $
 * @access public
 */
class vBCms_Widget_SectionNavExt extends vBCms_Widget
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
	protected $class = 'SectionNavExt';

	/*** cache lifetime, minutes ****/
	protected $cache_ttl = 1440;

	/*** default template name ****/
	protected $default_template = 'vbcms_widget_sectionnavext_page';


	/**
	 * Returns the config view for the widget.
	 *
	 * @return vBCms_View_Widget				- The view result
	 */
	public function getConfigView($widget = false)
	{
		global $vbphrase;
		$this->assertWidget();
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do'      => vB_Input::TYPE_STR,
			'template_name'    => vB_Input::TYPE_STR,
			'menu_type'    => vB_Input::TYPE_INT,
			'show_all_tree_elements_threshold' => vB_Input::TYPE_INT
		));

		$view = new vB_View_AJAXHTML('cms_widget_config');
		$view->title = new vB_Phrase('vbcms', 'configuring_widget_x', $this->widget->getTitle());
		$config = $this->widget->getConfig();
		$widgetdm = $this->widget->getDM();

		if ((vB::$vbulletin->GPC['do'] == 'config') AND $this->verifyPostId())
		{
			if (vB::$vbulletin->GPC_exists['template_name'])
			{
				$config['template_name'] = vB::$vbulletin->GPC['template_name'];
			}

			if (vB::$vbulletin->GPC_exists['menu_type'])
			{
				$config['menu_type'] = (vB::$vbulletin->GPC['menu_type'] == 2 ? 2 : 1);
			}

			if (vB::$vbulletin->GPC_exists['show_all_tree_elements_threshold'])
			{
				$config['show_all_tree_elements_threshold'] = vB::$vbulletin->GPC['show_all_tree_elements_threshold'];
			}

			if ($this->content)
			{
				$widgetdm->setConfigNode($this->content->getNodeId());
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
		if (!isset($config['template_name']) OR ($config['template_name'] == '') )
		{
			$config['template_name'] = 'vbcms_widget_sectionnavext_page';
		}
		// add the config content
		$configview = $this->createView('config');

		$configview->template_name = $config['template_name'];
		$configview->one_selected = (intval($config['menu_type']) != 2 ? 'selected="selected"' : '');
		$configview->two_selected = (intval($config['menu_type']) == 2 ? 'selected="selected"' : '');
		$configview->show_all_tree_elements_threshold = $config['show_all_tree_elements_threshold'];


		// item id to ensure form is submitted to us
		$this->addPostId($configview);

		$view->setContent($configview);

		// send the view
		$view->setStatus(vB_View_AJAXHTML::STATUS_VIEW, new vB_Phrase('vbcms', 'configuring_widget'));

		return $view;	}



	/******** This function generates a hierarchical array of section lists, which is best done as a recursive task.
	* @param $nodes : straight list of viewable sections. Note that this is ordered by nodeleft from the node table,
	* so we must sort as we compose the lists
	* @currentnodeid : id of the current node. When we hit that we add class="active" to the <li> callout
	*
	* @return : string of all the children of the current node
	* ******/
	private function arrangeSection(&$nodes, $currentnodeid)
	{
		//We start the <ol> text;

		$currentnode = current($nodes);
		//If we are at the root node, which has no parentid, let's just advance.
		if (intval($currentnode['parentnode']) == 0)
		{
			$currentnode = next($nodes);
		}

		$node = vBCms_Item_Content::buildUrlSegment($currentnode['nodeid'], $currentnode['url']);
		$segments = array('node' => $node, 'action' => 'view');

		$result = "<ul >\n<li" . (intval($currentnode['nodeid']) == intval($currentnodeid) ? ' class="active" ' : '')
			. '><a href="' . vBCms_Route_Content::getURL($segments) . '" title="' . $currentnode['title'] . '">' . $currentnode['title'] . "</a>\n" ;

		$lastnodeid = $currentnode['nodeid'];
		$parentnodeid = $currentnode['parentnode'];
		// Now walk the list of nodes
		while($currentnode = next($nodes))
		{

			//If this node is our child, we call this function recursively.
			if ($currentnode['parentnode'] == $lastnodeid)
			{
				$result .=   $this->arrangeSection($nodes, $currentnodeid) . "\n";
			}

			//If the parent node has changed then it's time to return
			if ($currentnode = current($nodes) AND $currentnode['parentnode'] != $parentnodeid)
			{
				return $result . "</li>\n</ul>\n ";
			}
			//If we got here and we aren't at the end of the list, we are at the same level. Just generate another link
			if ($currentnode = current($nodes) AND $currentnode['parentnode'] == $parentnodeid)
			{
				$node = $currentnode['nodeid'] . ($currentnode['url'] ? '-'.$currentnode['url'] : '');
				$segments = array('node' => $node, 'action' =>'view');
				$result .= "</li>\n<li" . (intval($currentnode['nodeid']) == intval($currentnodeid) ? ' class="active" ' : '')
				. '><a href="' . vBCms_Route_Content::getURL($segments) . '" title="' . $currentnode['title'] . '">' . $currentnode['title'] . "</a>\n" ;
			}
			$lastnodeid = $currentnode['nodeid'];
		}
		//we get here if we are at the top level and we've hit the last node;
		if ($currentnode = current($nodes) AND $currentnode['parentnode'] == $parentnodeid)
		{
			$segments = array('node' => $currentnode['nodeid'], 'action' =>'view');
			$result .= "</li>\n<li" . (intval($currentnode['nodeid']) == intval($currentnodeid) ? ' class="active" ' : '')
			. '><a href="' . vBCms_Route_Content::getURL($segments, array('url' => $currentnode['url'])) . '" title="' . $currentnode['title'] . '">' . $currentnode['title'] . "</a>\n" ;
		}
		return $result . "</li></ul>\n ";

	}

	/**
	* This function adds node url & indent for non-javascript navigation
	*
	* @param	array	$nodes
	*/
	public function setNavArray($nodes)
	{
		//We need to set the indent level and the url
		$indentlevel = array();
		//What is the current section
		$sectionid = ($this->content->getContentTypeID() == vb_Types::instance()->getContentTypeID("vBCms_Section")) ?
			$this->content->getNodeId() : $this->content->getParentId();

		//because we're ordered by nodeleft, we'll always see parents before children
		foreach ($nodes as $key => $node)
		{
			//get the url
			$nodeurl = $node['nodeid'] . ($node['url'] ? '-'. $node['url'] : '');
			$segments = array('node' => $nodeurl, 'action' => 'view');
			$nodes[$key]['url'] = vBCms_Route_Content::getURL($segments);
			//get the indent
			if (isset($node['parentnode']))
			{
				if (array_key_exists($node['parentnode'], $indentlevel))
				{
					//This is the root node
					$indent = $indentlevel[$node['parentnode']] + 1;
					$indentlevel[$node['nodeid']] = $indent;
					$nodes[$key]['indent'] = $indent;
				}
				else
				{
					$nodes[$key]['indent'] = 1;
					$indentlevel[$node['nodeid']] = 1;
				}
			}
			else
			{
				//This is the root node
				unset($nodes[$key]);
				continue;
			}
			//Set a flag to tell the template if it's the current page.
			//In my experience with templates, 0/1 is more reliable than true-false
			$nodes[$key]['current_page'] = $sectionid == $node['nodeid'] ? 1 : 0;
		}
		return $nodes;
	}
	/*Render========================================================================*/
	/**
	 * Fetches the standard page view for a widget.
	 *
	 * @param bool $skip_errors					- If using a collection, omit widgets that throw errors
	 * @return vBCms_View_Widget				- The resolved view, or array of views
	 */
	public function getPageView()
	{

		$this->assertWidget();

		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}
		$config = $this->widget->getConfig();

		if (!isset($config['template_name']) OR ($config['template_name'] == '') )
		{
			$config['template_name'] = $this->default_template;
		}

		$view = new vBCms_View_Widget($config['template_name']);
		$view->widget_title = $this->widget->getTitle();
		$view->menu_static = ($config['menu_type'] == 1 ? 'true' : 'false');
		$view->show_all_tree_elements_threshold = $config['show_all_tree_elements_threshold'];


		//see if we can get from cache;
		if ($sectionlist = vB_Cache::instance()->read($this->getHash($this->widget->getId(), 'all'), true, true))
		{
			$view->nodelist = $this->arrangeSection($sectionlist, $this->content->getNodeId());
			$view->nodes = $this->setNavArray($sectionlist);
			return $view;
		}

		$publishlist = implode(', ', vB::$vbulletin->userinfo['permissions']['cms']['canpublish']);
		$viewlist = implode(', ', vB::$vbulletin->userinfo['permissions']['cms']['allview']);
			$rst = vB::$vbulletin->db->query_read("SELECT node.nodeid, node.parentnode, node.url, node.permissionsfrom,
			node.setpublish, node.publishdate, node.noderight, info.title FROM " . TABLE_PREFIX .
			"cms_node AS node INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid
			 WHERE node.contenttypeid = " .
		vB_Types::instance()->getContentTypeID("vBCms_Section") . "  AND
		((node.permissionsfrom IN ($viewlist)  AND node.hidden = 0 ) OR (node.permissionsfrom IN ($publishlist)))
			 ORDER BY node.nodeleft");
		$nodes = array();
		$noderight = 0;

		while($record = vB::$vbulletin->db->fetch_array($rst))
		{
			if (($record['noderight'] < $noderight))
			{
				continue;
			}
			if (/** This user doesn have permissions to view this record **/
				(! in_array($record['permissionsfrom'],vB::$vbulletin->userinfo['permissions']['cms']['canedit'])
				AND !(in_array($record['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['canview'] )
				AND $record['setpublish'] == '1' AND $record['publishdate'] < TIMENOW ))
				)
			{
				//We need to skip this record and all its children
				$noderight = $record['noderight'];
				continue;
			}
			$nodes[] = $record;
		}

		if (count($nodes))
		{
			vB_Cache::instance()->write($this->getHash($this->widget->getId(), 'all'),
				$nodes, $this->cache_ttl, array('sections_updated'));
			reset($nodes);
			$view->nodelist = $this->arrangeSection($nodes, $this->content->getNodeId());
			$view->nodes = $this->setNavArray($nodes);
			return $view;
		}
		return false;
	}

	/**
	 * 
	 *
	 * @param integer $widgetid
	 * @return 
	 */
	/**
	 * This returns a hash for widget caching. We include nodeid and userid b
	 * 
	 * @param  integer $widgetid
	 * @param  mixed $nodeid   
	 * @return string  			 hash that will identify this widget content for this page          
	 */
	protected function getHash($widgetid = false, $nodeid = false)
	{
		if (!$widgetid)
		{
			$widgetid = $this->widget->getId();
		}

		if (!$nodeid)
		{
			$nodeid = 'all';
		}

		$context = new vB_Context("widget_$widgetid" , array('widgetid' => $widgetid,
		'permissions' => vB::$vbulletin->userinfo['permissions']['cms'],
		'nodeid' => $nodeid));
		return strval($context);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77258 $
|| ####################################################################
\*======================================================================*/