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
 * CMS Content Route
 * Routing for displaying and managing CMS pages, nodes and content.
 *
 * @author vBulletin Development Team
 * @version $Revision$
 * @since $Date$
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Route_Content extends vB_Route
{
	/*Properties====================================================================*/

	/**
	 * The segment scheme
	 *
	 * @see vB_Route::$_segment_scheme
	 *
	 * @var array mixed
	 */
	protected $_segment_scheme = array(
		'node'			=>	array (
			'default'	=>	'1'
			),
		'action'		=> array (
			'values'	=> array(),
			'default'	=>	'view'
		)
	);

	/**
	 * Action map.
	*/
	protected static $actions = array();

	/*Initialization================================================================*/

	/**
	 * The constructor.
	 *
	 * A base URL is required to prepend to compiled URL.  If a route path is not
	 * specified then the default route path is assumed.
	 *
	 * @param string $route_path				- The route path to compile
	 */
	public function __construct($route_path = false)
	{
		if (vB::$vbulletin->options['default_page'])
		{
			$this->_default_path = vB::$vbulletin->options['default_page'];
		}

		parent::__construct($route_path);

	}



	/*Response======================================================================*/

	/**
	 * Returns the response for the route.
	 *
	 * @return string							- The response
	 */
	public function getResponse()
	{
		global $vbphrase;

		if (!$this->isValid())
		{
			throw (new vB_Exception_404($vbphrase['invalid_route']));
		}

		if (!($controller = vB_Router::getActionController(get_class($this), $this->action, $this->_parameters)))
		{
			throw (new vB_Exception_404($vbphrase['invalid_action']));
		}
		if (intval($this->_segments['node']))
		{
			$metacache_key = 'vbcms_view_data_' . intval($this->_segments['node']);
			vB_Cache::instance()->restoreCacheInfo($metacache_key);
		}

		return $controller->getResponse();
	}



	/*URL===========================================================================*/

	/**
	 * Validates a segment.
	 * The segment scheme is checked for constraints and boolean false is returned
	 * if the segment is not valid.
	 *
	 * Child classes can extend this if they use other validation methods.
	 *
	 * @throws vB_Exception_Router
	 *
	 * @param string $name						- The key name of the segment
	 * @param mixed $value						- The value to validate
	 * @return bool								- Success
	 */
	protected function validateSegment($name, $value)
	{
		if (!parent::validateSegment($name, $value))
		{
			return false;
		}

		if ('node' != $name)
		{
			return true;
		}

		return (bool)intval($value);
	}


	/**
	 * Returns a representative URL of a route.
	 * Optional segments and parameters may be passed to set the route state.
	 *
	 * @param array mixed $segments				- Assoc array of segment => value
	 * @param array mixed $parameters			- Array of parameter values, in order
	 * @return string							- The URL representing the route
	 */
	public static function getURL(array $segments = null, array $parameters = null, $absolute_path = false)
	{
		$route = vb_Route::create('vBCms_Route_Content');

		if ($absolute_path)
		{
			$route->setAbsolutePath(true);
		}

		return $route->getCurrentURL($segments, $parameters);
	}

	public function getCurrentURL(array $segments = null, array $parameters = null, $query_string = '', $override_method = false, $canonical = false)
	{
		return parent::getCurrentURL($segments, $parameters, $query_string, $override_method, $canonical);
	}

	protected function buildRoutePath($canonical = false)
	{
		$value = parent::buildRoutePath($chop_class_segment, $inflate, $canonical);
		return $value;
	}

	protected function isDefaultContentPage($segments)
	{
		if (empty($segments['action']) OR $segments['action'] == "view")
		{
			if (empty($segments['node']) AND empty($this->_route_path))
			{
					return true;
			}

			//if this is the default content value
			$nodevals = explode('-', $segments['node'], 2);
			$defaultvals = explode('-', $this->_segment_scheme['node']['default'], 2);
			if ($nodevals[0] == $defaultvals[0])
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Inflate dynamic segments to canonical values.
	 */
	public function inflateSegments()
	{
		$node = new vBCms_Item_Content($this->node);

		if (!$node->isValid())
		{
			return;
		}

		if ($this->node != ($segment = $node->getUrlSegment()))
		{
			$this->setSegment('node', $segment, true);
		}
	}


	/*Segments======================================================================*/

	/**
	 * Builds dynamic segment schemes.
	 */
	protected function buildSegmentScheme()
	{
		$actions = vB_Router::getRouteActions(get_class($this));

		$this->_segment_scheme['node']['default'] = vB::$vbulletin->options['default_page'];
		$this->_segment_scheme['action']['values'] = $actions;
	}

	public function assertSubdirectoryUrl()
	{
		//logic is shared with the core app
		verify_subdirectory_url(vB::$vbulletin->options['vbcms_url']);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
