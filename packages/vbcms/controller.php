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
 * Base CMS Page Controller
 * Sets up the node info for the page.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 77113 $
 * @since $Date: 2013-08-27 08:06:24 -0700 (Tue, 27 Aug 2013) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vBCms_Controller extends vB_Controller
{
	/*Properties====================================================================*/

	/**
	 * The full requested node string.
	 * This may be numeric, or x_title where x is the numeric node id and title is
	 * the defined url title of the node.  The title section of the node segment is
	 * not used and is discarded.
	 *
	 * @var mixed
	 */
	protected $node_segment;

	/**
	 * The content object for the requested node.
	 *
	 * @var vBCms_Item_Content
	 */
	protected $node;

	/**
	 * The full content item loaded for the node.
	 *
	 * @var vBCms_Content
	 */
	protected $content;

	/**
	 * Layout info
	 *
	 * @var vBCms_Item_Layout
	 */
	protected $layout;

	/**
	 * Whether to initialize.
	 *
	 * @var bool
	 */
	protected $auto_initialize = true;



	/*Initialization================================================================*/

	/**
	 * Constructor.
	 * The constructor grabs the requested node segment and parameters.
	 *
	 * @param array mixed $parameters			- User requested parameters.
	 * @param string $action					- User requested action
	 */
	public function __construct($parameters, $action = false)
	{
		parent::__construct($parameters, $action);

		// Evaluate the node that we're working with
		$this->node_segment = vB_Router::getSegment('node');

		if (!$this->node_segment)
		{
			throw (new vB_Exception_404());
		}

		if ($this->auto_initialize)
		{
			$this->initialize();
		}
	}


	/**
	 * Convenience factory method.
	 *
	 * @param string $package
	 * @param string $class
	 * @param array mixed $parameters
	 * @param string $action
	 * @return vB_Controller
	 */
	public function create($package, $class, array $parameters = null, $action = false)
	{
		$class = $package . '_Controller_' . $class;
		return new $class($parameters, $action);
	}


	/**
	 * Initialisation.
	 * Initialises the view, templaters and all other necessary objects for
	 * successfully creating the response.
	 */
	protected function initialize()
	{
		// Get current node info
		$this->node = new vBCms_Item_Content($this->node_segment);

		// Prenotify the node item of info we will require
		$info_flags = 	vBCms_Item_Content::INFO_NODE |
						vBCms_Item_Content::INFO_PARENTS |
						vBCms_Item_Content::INFO_CONFIG;
		$this->node->requireInfo($info_flags);

		if (!$this->node->isValid())
		{
			$this->node = new vBCms_Item_Content( vB::$vbulletin->options['default_page']);
			vBCms_NavBar::prepareNavBar($this->node);
			throw (new vB_Exception_404(new vB_Phrase('error', 'page_not_found')));
		}
		
		// Prepare navbar
		vBCms_NavBar::prepareNavBar($this->node);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77113 $
|| ####################################################################
\*======================================================================*/