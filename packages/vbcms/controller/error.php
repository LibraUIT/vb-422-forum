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
 * Default error controller.
 * Error controller with default implementations for providing error responses for
 * 403 (Access Denied), 404 (File not Found) and 500 (Internal Server Error)
 * responses.
 *
 * @author vBulletin Development Team
 * @version $Revision: 29533 $
 * @since $Date: 2009-02-12 16:00:09 +0000 (Thu, 12 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Controller_Error extends vB_Controller
{
	/*Properties====================================================================*/

	/**
	 * The package that the controller belongs to.
	 *
	 * @var string
	 */
	protected $package = 'vB';

	/**
	 * The class string id that identifies the controller.
	 *
	 * @var string
	 */
	protected $class = 'Error';



	/*Initialization================================================================*/

	/**
	 * Main entry point for the controller.
	 *
	 * @return string							- The final page output
	 */
	public function getResponse($parameters)
	{
		// Resolve rerouted error
		$error = in_array($parameters[0], array('403', '404', '500')) ? $parameters[0] : '404';

		// Create the standard vB templater
		$templater = new vB_Templater_vB();

		// TODO: Check what happens to style when undefined.

		// Register the templater to be used for XHTML
		vB_View::registerTemplater(vB_View::OT_XHTML, new vB_Templater_vB());

		// Create the page view
		$page_view = new vB_View_Page('page');

		// Create the body view
		$error_view = new vB_View('error_' . $error);

		// Get original requested url so we can link to retry or redirect to it after login
		$error_view->initial_url = vB_Router::getInitialURL();

		// Add the body view to the page
		$page_view->setBodyView($error_view);

		// Add general page info
		// TODO: $view->setBreadcrumbInfo(); // May not be needed
		$page_view->setPageTitle(new vB_Phrase('error', 'error_' . $error));

		return $page_view->render();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 29533 $
|| ####################################################################
\*======================================================================*/