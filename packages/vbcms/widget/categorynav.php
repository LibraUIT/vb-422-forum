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
 * @version $Id: categorynav.php 77258 2013-09-03 00:14:45Z pmarsden $
 * @access public
 */
class vBCms_Widget_CategoryNav extends vBCms_Widget
{
	/**** There are at the time of this writing two category navigation widgets. One
	 * displays categories for the current section and all sections above it in the
	 * section hierarchy. We call that "bottom up". The other displays for the current
	 * section and all sections that descend from it. "Top-Down".
	 *
	 * This is the Top Down widget.
	 *
	 * Note that neither widget currently displays the categories with hierarchy. Although
	 * categories are stored as a hierarchy, they are displayed as flat.
	 ****/

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
	protected $class = 'CategoryNav';

	/** The id of the section for which we are displaying content. ***/
	protected $sectionid;


	/*** cache lifetime, minutes ****/
	protected $cache_ttl = 1440;


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
			'template_name'    => vB_Input::TYPE_STR
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
		$configview = $this->createView('config');

		if (!isset($config['template_name']) OR ($config['template_name'] == '') )
		{
			$config['template_name'] = 'vbcms_widget_categorynav_page';
		}
		// add the config content
		$configview->template_name = $config['template_name'];


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
		if (!isset($config['template_name']) OR ($config['template_name'] == '') )
		{
			$config['template_name'] = 'vbcms_widget_categorynav_page';
		}

		// Create view
		$view = new vBCms_View_Widget($config['template_name']);
		$this->sectionid = $this->content->getContentTypeID() == vb_Types::instance()->getContentTypeID("vBCms_Section") ?
			$this->content->getNodeId() : $this->content->getParentId();

		try
		{
			$categoryid = max(1, intval(vB_Router::getSegment('value')));
		}
		catch (vB_Exception_Router $e)
		{
			$categoryid = 0;
		}

		if (!$nodes = vB_Cache::instance()->read($cache_key = $this->getHash($this->widget->getId(), $this->sectionid), true, true
			))
		{
			//First we'll generate the category list

			//compose the sql
			$rst = vB::$vbulletin->db->query_read($sql = "SELECT parent.category AS parentcat, cat.categoryid, cat.category,
			cat.catleft, cat.catright, info.title AS node, parentnode.nodeid, count(nodecat.nodeid) as qty
      	FROM " . TABLE_PREFIX . "cms_node AS node
			INNER JOIN " . TABLE_PREFIX . "cms_node AS parentnode ON (node.nodeleft >= parentnode.nodeleft AND node.nodeleft <= parentnode.noderight)
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = parentnode.nodeid
      	INNER JOIN " . TABLE_PREFIX . "cms_category AS parent on parent.parentnode = node.nodeid
			INNER JOIN " . TABLE_PREFIX . "cms_category AS cat ON (cat.catleft >= parent.catleft AND cat.catleft <= parent.catright)
			LEFT JOIN " . TABLE_PREFIX . "cms_nodecategory AS nodecat ON nodecat.categoryid = cat.categoryid
			WHERE parentnode.nodeid = " . $this->sectionid . " AND " . vBCMS_Permissions::getPermissionString() . "
			GROUP BY parent.category, cat.categoryid, cat.category,
			cat.catleft, cat.catright, info.title, parentnode.nodeid
			ORDER BY node.nodeleft, catleft;");

			$parents = array();
			$level = 0;
			$nodes = array();
			if ($record = vB::$vbulletin->db->fetch_array($rst))
			{
				$record['level'] = $level;
				$record['route_info'] = $record['categoryid'] .
					($record['category'] != '' ? '-' . str_replace(' ', '-', $record['category']) : '');
				$nodes[strtolower($record['category'])] = $parents[0] = $record;
				$last_category = -1;

				while($record = vB::$vbulletin->db->fetch_array($rst))
				{
					$record['route_info'] = $record['categoryid'] .
						($record['category'] != '' ? '-' . str_replace(' ', '-', $record['category']) : '');

					if ($record['categoryid'] == $last_category )
					{
						continue;
					}

					//note that since we're already sorted by by catleft we don't need to check that.
					while((intval($record['catright']) > intval($parents['level']['catright'])) AND $level > 0)
					{
						$level--;
					}
					$level++;
					$record['level'] = $level;

					$nodes[strtolower($record['category'])] = $parents[$level] = $record;
					$last_category = $record['categoryid'];
				}
			}
			ksort($nodes);
			$key = array_keys($nodes);
			$size = sizeOf($key);
			for ($i = 0; $i < $size; $i++)
			{
				if ($categoryid == $nodes[$key[$i]]['categoryid'])
				{
					$nodes[$key[$i]]['myself'] = true;
				}
				else
				{
					$nodes[$key[$i]]['myself'] = false;
				}
			}
			vB_Cache::instance()->write($cache_key,
				$nodes, $this->cache_ttl, 'categories_updated');
		}

		foreach ($nodes as $nodeid => $record)
		{
			$route = vB_Route::create('vBCms_Route_List', "category/" . $record['route_info'] . "/1")->getCurrentURL();
			$nodes[$nodeid]['view_url'] = $route;

		}
		// Modify $nodes to add myself var (currently selected category)


		$view->widget_title = $this->widget->getTitle();
		$view->nodes = $nodes;
		return $view;
	}

	/**
	 * This returns a hash for widget caching. We include nodeid and userid b
	 *
	 *
	 * @param integer $widgetid
	 * @param  boolean $nodeid   - Added for PHP 5.4 strict standards compliance
	 * 
	 * @return hash that will identify this widget content for this page
	 */
	protected function getHash($widgetid = false, $nodeid = false)
	{
		if (!$widgetid)
		{
			$widgetid = $this->widget->getId();
		}

		$context = new vB_Context("widget_$widgetid" , array('widgetid' => $widgetid,
			'permissions' => vB::$vbulletin->userinfo->permissions['cms'],
			'sectionid' => $this->sectionid));
		return strval($context);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77258 $
|| ####################################################################
\*======================================================================*/