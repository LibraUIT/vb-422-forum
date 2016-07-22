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
 * @version $Revision: 63231 $
 * @since $Date: 2012-06-01 15:13:25 -0700 (Fri, 01 Jun 2012) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Route_List extends vB_Route
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
		'type'			=>	array (
			'optional'	=>	false,
			'values'	=>	array (
								'category',
								'section',
								'author',
								'day'
			),
			'default'	=> 'section',
		),
		'value'			=>	array (
			'default'	=>	'1'
		),
		'page'			=>	array (
			'default'	=>	'1'
			)
		);


	/**
	 * Action map.
	*/
	protected $_default_path = 'section/1/1';



	/*Response======================================================================*/

	/**
	 * Returns the response for the route.
	 *
	 * @return string							- The response
	 */
	public function getResponse()
	{
		if (!$this->isValid())
		{
			throw (new vB_Exception_404());
		}

		$controller = new vbCms_Controller_List($this->_segments);
		return $controller->getResponse();
	}



	/*URL===========================================================================*/

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
		$route = vb_Route::create('vBCms_Route_List');

		if ($absolute_path)
		{
			$route->setAbsolutePath(true);
		}

		return $route->getCurrentURL($segments, $parameters);
	}


	/**
	 * Inflate dynamic segments to canonical values.
	 */
	public function inflateSegments()
	{
		// Ensure we can resolve the value
		if (!$value = intval($this->value))
		{
			return;
		}

		// Inflate section
		if ('section' == $this->type)
		{
			$node = new vBCms_Item_Content($value);

			if (!$node->isValid())
			{
				return;
			}

			if ($this->value != ($segment = $node->getUrlSegment()))
			{
				$this->setSegment('value', $segment, true);
			}

			return;
		}

		// Inflate author
		if ('author' == $this->type)
		{
			// TODO: Need a model for users
			$result = vB::$vbulletin->db->query_first("
				SELECT username FROM " . TABLE_PREFIX . "user
				WHERE userid = $value
				AND username != ''
			");

			if ($result)
			{
				$this->setSegment('value', vBCms_Item_Content::buildUrlSegment($value, $result['username']), true);
			}

			return;
		}

		// Inflate category
		if ('category' == $this->type)
		{
			// TODO: Need a model for categories
			$result = vB::$vbulletin->db->query_first("
				SELECT category FROM " . TABLE_PREFIX . "cms_category
				WHERE categoryid = $value
				AND category != ''
			");

			if ($result)
			{
				$url = vB_Search_Searchtools::stripHtmlTags($record['category']);
				$segments['value'] .= '-' . str_replace(' ', '-', $url) ;

				$this->setSegment('value', vBCms_Item_Content::buildUrlSegment($value, $result['category']), true);
			}
		}
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
|| # SVN: $Revision: 63231 $
|| ####################################################################
\*======================================================================*/