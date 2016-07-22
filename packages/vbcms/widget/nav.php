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
 * @version $Id: nav.php 77408 2013-09-06 20:02:39Z pmarsden $
 * @access public
 */
class vBCms_Widget_Nav extends vBCms_Widget
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
	protected $class = 'Nav';

	/*** cache lifetime, minutes ****/
	protected $cache_ttl = 5;


	/*Render========================================================================*/

	/**
	 * Returns the config view for the widget.
	 *
	 * @return vBCms_View_Widget				- The view result
	 */
	public function getConfigView($widget = false)
	{
		$this->assertWidget();

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do'      => vB_Input::TYPE_STR,
			'show_top'    => vB_Input::TYPE_UINT,
			'show_siblings'    => vB_Input::TYPE_UINT,
			'show_parent'    => vB_Input::TYPE_UINT,
			'template_name'    => vB_Input::TYPE_STR
			));

		$view = new vB_View_AJAXHTML('cms_widget_config');
		$view->title = new vB_Phrase('vbcms', 'configuring_widget_x', $this->widget->getTitle());
		$config = $this->widget->getConfig();

		if ((vB::$vbulletin->GPC['do'] == 'config') AND $this->verifyPostId())
		{
			if (vB::$vbulletin->GPC_exists['show_top'])
			{
				$config['show_top'] = (bool)vB::$vbulletin->GPC['show_top'];
			}
			if (vB::$vbulletin->GPC_exists['template_name'])
			{
				$config['template_name'] = vB::$vbulletin->GPC['template_name'];
			}

			if (vB::$vbulletin->GPC_exists['show_siblings'])
			{
				$config['show_siblings'] = (bool)vB::$vbulletin->GPC['show_siblings'];
			}

			if (vB::$vbulletin->GPC_exists['show_parent'])
			{
				$config['show_parent'] = (bool)vB::$vbulletin->GPC['show_parent'];
			}

			$widgetdm = $this->widget->getDM();
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
		// add the config content
		$configview = $this->createView('config');

		if (!isset($config['template_name']) OR ($config['template_name'] == '') )
		{
			$config['template_name'] = 'vbcms_widget_nav_page';
		}
		// add the config content
		$configview->template_name = $config['template_name'];

		$showchecked = array();
		$showchecked[0] = $config['show_siblings'] ?  '' : 'checked="checked"';
		$showchecked[1] = $config['show_siblings'] ? 'checked="checked"' : '';
		$configview->show_siblings_checked = $showchecked;
		$showchecked = array();
		$showchecked[0] = $config['show_top'] ?  '' : 'checked="checked"';
		$showchecked[1] = $config['show_top'] ? 'checked="checked"' : '';
		$configview->show_top_checked = $showchecked;
		$showchecked = array();
		$showchecked[0] = $config['show_parent'] ?  '' : 'checked="checked"';
		$showchecked[1] = $config['show_parent'] ? 'checked="checked"' : '';
		$configview->show_parent_checked = $showchecked;

		// item id to ensure form is submitted to us
		$this->addPostId($configview);

		$view->setContent($configview);

		// send the view
		$view->setStatus(vB_View_AJAXHTML::STATUS_VIEW, new vB_Phrase('vbcms', 'configuring_widget'));

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
		$this->assertWidget();
		$config = $this->widget->getConfig();

		// Create view
		if (!isset($config['template_name']) OR ($config['template_name'] == '') )
		{
			$config['template_name'] = 'vbcms_widget_nav_page';
		}

		// Create view
		$view = new vBCms_View_Widget($config['template_name']);
		$view->class = $this->widget->getClass();
		$view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();

		if (!$link_nodes = vB_Cache::instance()->read($cache_key = $this->getHash($this->widget->getId()), false, true))
		{

			$parentnodes = $config['show_parent'] ?
				$this->content->getParentNodes() : array();
			$childnodes = $this->content->getChildNodes();
			$siblingnodes = $config['show_siblings']?
				$this->content->getSiblingNodes() : array();
			$topnodes = $config['show_top'] ?
				$this->content->getTopLevelNodes($config['show_siblings']) : array();
			$link_nodes = array('parentnodes' => $parentnodes,
				'childnodes' => $childnodes,
				'siblingnodes' => $siblingnodes,
				'topnodes' => $topnodes);

			vB_Cache::instance()->write($cache_key,
				   $link_nodes, $this->cache_ttl, 'sections_updated');
		}
		$nodes = $this->makeNav($link_nodes);
		$view->prior_nodes = $nodes['prior_nodes'];
		$view->this_node = $nodes['this_node'];
		$view->child_nodes = $nodes['child_nodes'];
		$view->after_nodes = $nodes['after_nodes'];
		$view->widget_title = $this->widget->getTitle();

		return $view;
	}

	/**
	 * This does the actual work of creating the navigation elements. This needs some
	 * styling, but I'll do that later.
	 * The logic is:
	 * depending on the flags, get the appropriate arrays.
	 * Get the configuration
	 * If showtop is on, then show any top-level elements that sort above our top
	 * If showparent is on, then show the trail to our level.
	 * If showsiblings is on, then show siblings that sort above us.
	 * Show our children.
	 * If showsiblings is on, then show siblings that sort below us.
	 * If showtop is on, then show any top-level elements that sort below our top
	 *
	 * @return string;
	 */
	private function makeNav($link_nodes)
	{
		$router = new vBCms_Route_Content;

		$result = '';
		$space = '&nbsp;&nbsp;';
		$spacer = '';
		$this_nodeid = $this->content->getNodeId();
		$split_title = false;
		//Now at this point we mostly can ignore the config settings, because the
		//nodes we don't want to show aren't there.
		//First home. If we are using parentnodes, then home will be the first.
		$prior_nodes = array();
		$parent_nodes = array();
		$child_nodes = array();
		$after_nodes = array();


		if (count($link_nodes['parentnodes']))
		{
			$home = current($link_nodes['parentnodes']);
			$url = $router->getURL(array('node' => $home['nodeid'] . '-' . $home['url'],
				'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'View')));
			$node = array();
			$node['url'] = $url;
			$node['title'] = $home['title'];
			$prior_nodes[] = $node;

			$home = next($link_nodes['parentnodes']);
			reset($link_nodes['parentnodes']);
			$split_title = $home['title'];
			$split_nodeid = $home['nodeid'];
			$spacer = $space;
		}

		//next the top-level items.
		foreach ($link_nodes['topnodes'] as $nodeinfo)
		{
			if ($split_nodeid AND ($nodeinfo['title'] > $split_title OR $nodeinfo['nodeid'] == $split_nodeid) )
			{
				$split_title = $nodeinfo['title'];
				break;
			}

			$url = $router->getURL(array('node' => $nodeinfo['nodeid'] . '-' . $nodeinfo['url'],
				'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'View')));
			$node = array();
			$node['url'] = $url;
			$node['title'] = $spacer .  $nodeinfo['title'];
			$prior_nodes[] = $node;
	}

		//Now the parent tree.
		if (count($link_nodes['parentnodes']))
		{
			$spacer = '';
			//we can skip the first, which is the root and we've already displayed.

			foreach($link_nodes['parentnodes'] as $counter => $nodeinfo)
			{
				if ($counter == 0)
				{
					continue;
				}
				$url = $router->getURL(array('node' => $nodeinfo['nodeid'] . '-' . $nodeinfo['url'],
					'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'View')));
				$node = array();
				$node['url'] = $url;
				$node['title'] = $spacer .  $nodeinfo['title'];
				$parent_nodes[] = $node;
				$node = array();

				if ($nodeinfo['nodeid'] == $firstid)
				{
					$node['url'] = $url;
					$node['title'] = $spacer .  $nodeinfo['title'];
				}
				else
				{
					$node['title'] =  $spacer . '&nbsp;&nbsp;'. $nodeinfo['title'];
				}
				$parent_nodes[] = $node;
				$spacer .= $space;
			}
		}

		//Now siblings.
		foreach ($link_nodes['siblingnodes'] as $nodeid => $nodeinfo)
		{
			if ($nodeinfo['title'] > $this->content->getTitle() OR $nodeid == $this->widgetid)
			{
				break;
			}
			$url = $router->getURL(array('node' => $nodeid . '-' . $nodeinfo['url'],
				'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'View')));
			$node = array();
			$node['url'] = $url;
			$node['title'] = $spacer .  $nodeinfo['title'];
			$prior_nodes[] = $node;
		}
		//Now me, just for reference

		$this_node .= "$spacer" . $this->content->getTitle() ;

		//Now children
		foreach ($link_nodes['childnodes'] as $nodeid => $nodeinfo)
		{
			$url = $router->getURL(array('node' => $nodeid . '-' . $nodeinfo['url'],
				'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'View')));
			$node = array();
			$node['url'] = $url;
			$node['title'] = $spacer .  $nodeinfo['title'];
			$child_nodes[] = $node;
		}
		//Now remaining siblings.
		foreach ($link_nodes['siblingnodes'] as $nodeid => $nodeinfo)
		{
			if ($nodeinfo['title'] < $this->content->getTitle() OR $nodeid == $this->widgetid)
			{
				continue;
			}
			$url = $router->getURL(array('node' => $nodeid . '-' . $nodeinfo['url'],
				'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'View')));
			$node = array();
			$node['url'] = $url;
			$node['title'] = $spacer .  $nodeinfo['title'];
			$after_nodes[] = $node;
		}

		//Finally, any remaining top-level nodes
		foreach ($link_nodes['topnodes'] as $nodeinfo)
		{
			if ($nodeinfo['title'] <= $split_title)
			{
				continue;
			}
			$url = $router->getURL(array('node' => $nodeinfo['nodeid'] . '-' . $nodeinfo['url'],
				'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'View')));
			$node = array();
			$node['url'] = $url;
			$node['title'] = $space .  $nodeinfo['title'];
			$after_nodes[] = $node;
		}
		return array( 'this_node' => $this_node, 'prior_nodes' => $prior_nodes,
		'child_nodes' => $child_nodes, 'after_nodes' => $after_nodes );
	}

	/**
	 * This returns a hash for widget caching. We include nodeid because
	 * we get called on different pages, and
	 * we need to cached each page differently.
	 *
	 * @param integer $widgetid
	 * @param  mixed $nodeid   - Added for PHP 5.4 strict standards compliance
	 * 
	 * @return hash that will identify this widget content for this page
	 */
	protected function getHash($widgetid = false, $nodeid = false)
	{
		$context = new vB_Context("widget_$widgetid" , array('widgetid' => $widgetid,
			'nodeid' => $this->content->getNodeId(), 'cancreate' => vB::$vbulletin->check_user_permission('vbcmspermissions', 'cancreatecontent') ));
		return strval($context);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77408 $
|| ####################################################################
\*======================================================================*/