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
 * CMS Navbar View
 * View for rendering the legacy navbar.
 * Wraps up some global assignments and uses the legacy template.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: $
 * @since $Date: $
 * @copyright vBulletin Solutions Inc.
 */
class vB_View_NavBar extends vB_View
{
	/*Render========================================================================*/

	/**
	 * Prepare the widget block locations and other info.
	 */
	protected function prepareProperties()
	{
		// Legacy globals needed by navbar template
		$this->prepareLegacyGlobals();
	}


	/**
	 * Prepares the legacy output.
	 * Registers the globals required for the legacy output such as the header,
	 * footer and navbar.
	 */
	protected function prepareLegacyGlobals()
	{
		global $stylevar, $vbphrase, $vboptions, $session, $navbar_reloadurl, $show,
		$bbuserinfo, $pmbox, $notifications_total, $template_hook, $return_link, $notices,
		$foruminfo, $notifications_menubits, $ad_location;

		$globals = array(
			'stylevar' => $stylevar,
			'vbphrase' => $vbphrase,
			'vboptions' => $vboptions,
			'session' => $session,
			'navbar_reloadurl' => $navbar_reloadurl,
			'show' => $show,
			'bbuserinfo' => $bbuserinfo,
			'pmbox' => $pmbox,
			'notifications_total' => $notifications_total,
			'template_hook' => $template_hook,
			'return_link' => $return_link,
			'notices' => $notices,
			'foruminfo' => $foruminfo,
			'notifications_menubits' => $notifications_menubits,
			'ad_location' => $ad_location,
			'bbmenu' => vB::$vbulletin->options['bbmenu'],
			'navigation' => $this->getNavigation(),
		);

		$this->_properties = array_merge($this->_properties, $globals);
	}

	/**
	 * Renders the navigation tabs & links.
	*/
	protected function getNavigation()
	{
		global $vbulletin;

		$root = '';
		$root_tab = $roots['vbtab_forum'];

		$tabs = build_navigation_menudata();
		$roots = get_navigation_roots(build_navigation_list());

		$request_tab = intval($_REQUEST['tabid']);
		$script_tab = get_navigation_tab_script();

		$hook_tabid = $tabid = 0; 
		($hook = vBulletinHook::fetch_hook('set_navigation_tab_vbview')) ? eval($hook) : false;

		if ($root)
		{
			$tabid = $roots[$root];
		}

		/* Tab setting logic, using above choices. Preference order
		is (low > high) root > script > hookroot > hookid > request */
		$current_tab = $script_tab ? $script_tab : $root_tab;
		$current_tab = $tabid ? $tabid : $current_tab;
		$current_tab = $hook_tabid ? $hook_tabid : $current_tab;
		$current_tab = $request_tab ? $request_tab : $current_tab;

		$tabid = set_navigation_tab($current_tab, $tabs);

		$view = new vB_View('navbar_tabs');
		$view->tabs = $tabs;
		$view->selected = $tabid;

		return $view->render();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28709 $
|| ####################################################################
\*======================================================================*/
