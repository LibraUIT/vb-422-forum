<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Project Tools 4.2.2 - Nulled By VietVBB Team
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/*
* Build the raw navigation data array and save it in the datastore.
*
* @return	array	The navigation data array.
*/
function build_navigation_datastore()
{
	global $db, $vbulletin;

	$result = array();

	$data = $db->query_read_slave("
		SELECT *
		FROM " . TABLE_PREFIX . "navigation
		WHERE state & " . $vbulletin->bf_misc_navstate['deleted'] . " = 0
		ORDER BY navtype, displayorder
	");

	while ($row = $db->fetch_array($data))
	{
		$result[] = $row;
	}

	build_datastore('navdata', serialize($result), 1);

	return $result ? $result : false;
}

/*
* Build a navigation data array.
*
* @param	bool	Bypass permission checks.
* @param	bool	Force a rebuild of cached data.
*
* @return	array	The navigation data array.
*/
function build_navigation_array($bypass = false, $forced = false)
{
	global $db, $vbphrase, $vbulletin;

	static $result = array();

	if ($result AND !$forced)
	{
		return $result;
	}

	$list = array();
	$navdata = array();
	$tablist = array();

	if (is_array($vbulletin->navdata))
	{
		$data = $vbulletin->navdata;
	}
	else
	{
		$data = build_navigation_datastore();
	}

	foreach ($data AS $row)
	{
		$count = 10;
		if ($row['parent'])
		{
			do
			{
				$count++;
				if ($count > 99)
				{
					/* Something is very wrong */
					print_stop_message('internal_error','10');
				}

				/* Try and prevent key clashes. */
				$subkey = $row['displayorder'] . $count;
			}
			while (isset($list[$row['parent']][$subkey]));

			$list[$row['parent']][$subkey] = $row['name'];
		}
		else
		{
			// Dummy element, to create and order the tabs.
			$list[$row['name']][$row['displayorder'] . $count] = '#';
		}

		expand_navigation_state($row);
		$phrasename = 'vb_navigation_' . $row['navtype'] . '_' . $row['name'] . '_text';
		$row['text'] = $vbphrase[$phrasename] ? $vbphrase[$phrasename] : '#' . $row['name'] . '#';
		unset($row['username'], $row['version'], $row['dateline']); // Not needed.

		$navdata[$row['name']] = $row;
	}

	($hook = vBulletinHook::fetch_hook('build_navigation_data')) ? eval($hook) : false;

	foreach($list AS $tabname => $tab)
	{
		ksort($tab);
		foreach($tab AS $key => $element)
		{
			if(!is_array($element))
			{
				unset($tab[$key]);
				if($list[$element])
				{
					$tab[$element] = $navdata[$element];
					foreach($list[$element] AS $subkey => $subelement)
					{
						unset($list[$element][$subkey]);
						$list[$element][$subelement] = $navdata[$subelement];;
					}
					$tab[$element]['links'] = $list[$element];
					unset($list[$element]);
				}
				else if ($element != '#')
				{
					$tab[$element] = $navdata[$element];
				}
			}
			$list[$tabname] = $tab;
		}

		$tablist[$tabname] = $navdata[$tabname];
		$tablist[$tabname]['links'] = $list[$tabname];
	}

	$result = $tablist;

	($hook = vBulletinHook::fetch_hook('build_navigation_array')) ? eval($hook) : false;

	unset($navdata, $tablist, $list);

	return $result;
}

/*
* Build the navigation list array.
*
* @param	bool	Bypass permission checks.
* @param	bool	Force a rebuild of cached data.
*
* @return	array	The navigation list data array.
*/
function build_navigation_listdata($bypass = false, $forced = false)
{
	$navlistdata = array();
	$tablist = build_navigation_array($bypass, $forced);

	foreach($tablist AS $tabname => $tabdata)
	{
		if (!check_navigation_permission($tabdata, $bypass))
		{
			continue;
		}

		if ($tabdata['navtype'] == 'tab')
		{
			add_navigation_element($tabdata, $navlistdata);

			if (is_array($tabdata['links']))
			{
				add_navigation_links($tabdata['links'], $navlistdata, $bypass);
			}
		}
		else
		{
		/*	Root elements must always be Tabs.
			However, throwing an error will kill navigation control.
			This basically means there are orphaned elements in the
			database. For now we can just ignore them. At some point
			we should probably povide a way to access and edit them. */
		//	print_stop_message('x_not_a_tab',$tabname);
		}
	}

	($hook = vBulletinHook::fetch_hook('build_navigation_listdata')) ? eval($hook) : false;

	return $navlistdata;
}

/*
* Builds up the navigation links.
*
* @param	array	Array of links.
* @param	array	Parent array we are adding to.
* @param	bool	Bypass permission checks.
* @param	int		Level counter.
*
* @return	none	The passed navigation data array is updated.
*/
function add_navigation_links($navlinks, &$navlistdata, $bypass = false, $level = 1)
{
	if (!is_array($navlinks))
	{
		return;
	}

	foreach($navlinks AS $navname => $navdata)
	{
		if (is_array($navdata))
		{
			if (!check_navigation_permission($navdata, $bypass))
			{
				continue;
			}

			if ($navdata['navtype'] == 'menu')
			{
				add_navigation_element($navdata, $navlistdata, $level);
				add_navigation_links($navdata['links'], $navlistdata, $bypass, $level+1);
			}
			else
			{
				add_navigation_element($navdata, $navlistdata, $level);
			}
		}
		else
		{
			if (VB_AREA == 'AdminCP')
			{
				// The element is empty or invalid.
				print_stop_message('x_has_no_data',$navname);
			}
		}
	}
}

/*
* Adds the data to the array.
*
* @param	array	The data !
* @param	array	parent array we are adding to.
* @param	int		Level counter.
*
* @return	array	The navigation data array.
*/
function add_navigation_element($data, &$navlistdata, $level = 0)
{
	$data['level'] = $level;
	$navlistdata[] = $data;
}

/*
* Check an elements permissions.
*
* @param	array	Array of element data.
* @param	bool	Bypass permission checks.
*
* @return	bool	The element has permission to display [or not].
*/
function check_navigation_permission($data, $bypass = false)
{
	global $vbulletin, $show;

	if ($data['deleted'])
	{
		return false;
	}

	if ($bypass)
	{
		return true;
	}

	$retval = true;

	if ($data['active'] != 1)
	{
		$retval = false;
	}
	else if ($vbulletin->products[$data['productid']] != 1)
	{
		$retval = false;
	}
	else if ($showlist = explode('.',$data['showperm']))
	{
		foreach($showlist AS $perm)
		{
			if ($perm)
			{
				$not = false;
				if (substr($perm,0,1 == '!'))
				{
					$not = true;
					$perm = substr($perm,1);
				}

				if ($show[$perm])
				{
					$retval = $not ? false : $retval ;
				}
				else
				{
					$retval = $not ? $retval : false ;
				}
			}
		}
	}

	($hook = vBulletinHook::fetch_hook('check_navigation_permission')) ? eval($hook) : false;

	return $retval;
}

/*
* Extracts the defeault navigation element.
*
* @param	array	The navlist data array
*
* @return	bool	Select if returning the element name or navid.
*/
function get_navigation_default($navlist, $byname = true)
{
	global $vbulletin;

	$default = '';

	foreach ($navlist AS $element)
	{
		if ($element['state'] & $vbulletin->bf_misc_navstate['default'])
		{
			$default = $byname ? $element['name'] : $element['navid'];
			break;
		}
	}

	return $default;
}

/*
* Expands the navigation state field.
*
* @param	array	The data array.
*
* @return	none	(The $data fields are created by reference).
*/
function expand_navigation_state(&$data)
{
	global $vbulletin;

	foreach ($vbulletin->bf_misc_navstate AS $field => $bitvalue)
	{
		$data[$field] = ($data['state'] & $bitvalue) ? 1 : 0;
	}
}

/*
* collapses the navigation state field.
*
* @param	array	The data array.
*
* @return	none	(The $data fields are collapse into $data['state'] by reference).
*/
function collapse_navigation_state(&$data)
{
	global $vbulletin;

	$bits = 0;
	foreach ($vbulletin->bf_misc_navstate AS $field => $bitvalue)
	{
		$bits += ($data[$field]) ? $bitvalue : 0;
	}

	$data['state'] = $bits;
}

/*
* Build the navigation menu data array.
*
* @param	bool	Bypass permission checks.
* @param	bool	Force a rebuild of cached data.
*
* @return	array	The navigation menu data array.
*/
function build_navigation_menudata($bypass = false, $forced = false)
{
	$menudata = array();
	$tablist = build_navigation_array($bypass, $forced);

	foreach($tablist AS $tabname => $tabdata)
	{
		if (!check_navigation_permission($tabdata, $bypass))
		{
			continue;
		}

		if ($tabdata['navtype'] == 'tab')
		{
			set_navigation_menu_element($tabdata, $menudata, false, $tabdata['navid']);

			unset($menudata[$tabdata['navid']]['flag']);

			if (is_array($menudata[$tabdata['navid']]['children']))
			{
				set_navigation_menu_links($menudata[$tabdata['navid']]['children'], $bypass, $tabdata['navid']);
			}

			if ($tabdata['menuid'])
			{
				$menuid = $tabdata['links'][$tabdata['menuid']]['navid'];
				if ($menudata[$tabdata['navid']] AND $menudata[$tabdata['navid']]['children'][$menuid]['children'])
				{
					$menudata[$tabdata['navid']]['menu'] = array(
						'name'     =>  $menudata[$tabdata['navid']]['children'][$menuid]['name'],
						'children' => $menudata[$tabdata['navid']]['children'][$menuid]['children']
					);
					unset($menudata[$tabdata['navid']]['children'][$menuid]);
				}
			}

			if ($dflag)
			{
				$menudata[$tabdata['navid']]['selected'] = $dflag;
			}
		}
	}

	($hook = vBulletinHook::fetch_hook('build_navigation_menudata')) ? eval($hook) : false;

	return $menudata;
}

/*
* Builds up the navigation links.
*
* @param	array	Array of children to process links.
*
* @return	none	The array is processed by reference.
*/
function set_navigation_menu_links(&$children, $bypass, $root = 0)
{
	foreach($children AS $name => $data)
	{
		if (is_array($data))
		{
			set_navigation_menu_element($data, $children, $name, $root);

			if (is_array($children[$data['navid']]['children']))
			{
				set_navigation_menu_links($children[$data['navid']]['children'], $bypass, $root);
			}
		}
	}
}

/*
* Adds the menu elements to the array.
*
* @param	array	The data !
* @param	array	parent array we are adding to.
* @param	int		old array element to be deleted.
*
* @return	array	The navigation data array.
*/
function set_navigation_menu_element($data, &$menulist, $old = false, $root = 0)
{
	if ($menulist[$old]['flag'])
	{
		unset($menulist[$old]['flag']);
		return;
	}

	if (check_navigation_permission($data, $bypass))
	{
		// Link processing
		$data['url'] = process_navigation_linkvars($data['url']);

		// Javascript popup links
		if (substr($data['url'], 0, 13) == 'javascript://')
		{
			$data['url'] = 'javascript://" onclick="' . substr($data['url'],13);
		}

		$menu = array(
			'flag'  => true,
			'root'  => $root,
			'type'  => $data['navtype'],
			'url'   => $data['url'],
			'title' => $data['text'],
			'name'  => $data['name'],
		);

		if ($menu['type'] == 'tab')
		{
			if (!$menu['url'] OR $data['usetabid'])
			{
				$join = strpos($menu['url'], '?') ? '&' : '?';
				$menu['url'] = $menu['url'] . $join . 'tabid=' . $root;
			}
		}

		if ($data['newpage'])
		{
			$menu['target'] = 'target="_blank" ';
		}
		else
		{
			$menu['target'] = '';
		}

		if ($data['links'])
		{
			$menu['children'] = $data['links'];
		}
		else
		{
			$menu['children'] = 0;
		}

		$menulist[$data['navid']] = $menu;
	}

	($hook = vBulletinHook::fetch_hook('set_navigation_menu_element')) ? eval($hook) : false;

	if ($menulist[$old])
	{
		unset($menulist[$old]);
	}
}

/*
* Process the variable substitutions in a link.
*
* @param	str	The raw url
*
* @return	str	The processed url.
*/
function process_navigation_linkvars($url)
{
	global $vbulletin;

	//Set the session stuff.
	$session =& $vbulletin->session->vars;

	($hook = vBulletinHook::fetch_hook('process_navigation_links_start')) ? eval($hook) : false;

	$results = array();
    preg_match_all('#\{(.*?)\}#is', $url, $matches);

	// Process variable list
	if($matches[1])
	{
		foreach ($matches[1] AS $key => $var)
		{
			$results[$key] = '';
			list($varname, $index) = explode('.', $var);

			switch ($varname)
			{
				case 'session':
					$results[$key] = $index ? $vbulletin->session->vars[$index] : $vbulletin->session->vars;
					break;
				case 'vboptions':
					$results[$key] = $index ? $vbulletin->options[$index] : $vbulletin->options;
					break;
				case 'bbuserinfo':
					$results[$key] = $index ? $vbulletin->userinfo[$index] : $vbulletin->userinfo;
					break;
				default:

					if (preg_match('#^vb\#(.+)#si', $varname, $_matches))
					{
						if ($index)
						{
							if (isset($vbulletin->{$_matches[1]}[$index]))
							{
								$results[$key] = $vbulletin->{$_matches[1]}[$index];
							}
						}
						else if (isset($vbulletin->{$_matches[1]}))
						{
							$results[$key] = $vbulletin->{$_matches[1]};
						}
						continue;
					}

					if (isset($GLOBALS[$varname]))
					{
						$$varname =& $GLOBALS[$varname]; // Make the variable visible.
					}

					if ($index) // Array element
					{
						if(isset(${$varname}[$index]))
						{
							$results[$key] = ${$varname}[$index];
						}
					}
					else if ($varname) // Normal variable
					{
						if(isset($$varname))
						{
							$results[$key] = $$varname;
						}
					}
					else // Invalid, so skip
					{
						continue;
					}
			}
		}
	}

	($hook = vBulletinHook::fetch_hook('process_navigation_links_complete')) ? eval($hook) : false;

	return $matches[1] ? str_replace($matches[0], $results, $url) : $url;
}

/*
* Extracts the root element for each navigation element.
*
* @param	array	The navlist data array
* @param	bool	Select if returning the element name or navid.
*
* @return	array	Array of parents.
*/
function get_navigation_roots($navlist, $byname = true)
{
	$roots = array();

	foreach ($navlist AS $element)
	{
		$id = $byname ? $element['name'] : $element['navid'];
		$roots[$id] = $element['root'];
	}

	return $roots;
}

/*
* Build the navlist data for tab management.
*
* @param	array	Only extract sub elements for one tab.
* @param	int		The tab id to be extracted
* @return	bool	Include other tabs in the array.
*
* @return	array	created data.
*/
function build_navigation_list($listonly = false, $listid = 0, $others = true)
{
	$tabid = 0;
	$display = false;
	$lookup = $navlist = array();

	$navarray = build_navigation_listdata(true);

	if (!is_array($navarray))
	{
		return array();
	}

	foreach($navarray AS $navdata)
	{
		if ($navdata)
		{
			$lookup[$navdata['name']] = $navdata['navid'];
			$parent = $navdata['navtype'] == 'tab' ? $navdata['name'] : $navdata['parent'] ;

			if ($navdata['navtype'] == 'tab' AND $tabid != $navdata['navid'])
			{
				$tabid = $navdata['navid'];
			}

			$navdata['root'] = $tabid;
			$navdata['parentid'] = $lookup[$parent];

			if ($listonly)
			{
				if ($navdata['navtype'] == 'tab')
				{
					$display = ( $navdata['navid'] == $listid ? true : false );
				}

				if (($navdata['navtype'] == 'tab' AND !$display AND $others) OR $display)
				{
					$navlist[$navdata['navid']] = $navdata;
				}
			}
			else
			{
				$navlist[$navdata['navid']] = $navdata;
			}
		}
	}

	($hook = vBulletinHook::fetch_hook('build_navigation_list')) ? eval($hook) : false;

	return $navlist;
}

/*
* Get the landing url and verify its ok.
*
* @param	int		The tab id to be extracted
* @param	bool	process any link variables
*
* @return	string	the redirect url (or false).
*/
function get_navigation_url($tabid, $process = true)
{
	global $vbulletin;

	$data = build_navigation_list();
	$new_url = $data[$tabid]['url'];

	$urlx = $vbulletin->input->parse_url($new_url);

	// Blank or not the same host
	if (!$new_url OR (isset($urlx['host']) AND $urlx['host'] != VB_URL_HOST))
	{
		return false;
	}

	// Strip some stuff and try not to loop
	$search = array(
		VB_URL_HOST,
		VB_URL_SCRIPT_PATH,
	);

	$url = str_replace($search, '', $urlx['path']);
	if (!$url OR preg_match('#^(index|forum)\.php({|$|\?)#', $url))
	{
		return false;
	}

	return $process ? process_navigation_linkvars($new_url) : $new_url;
}

/**
* Sets the navbar tabid based on
* THIS_SCRIPT and the tabs script list.
*
* @return	int	Navbar tabid
*/
function get_navigation_tab_script()
{
	$tabid = 0;
	$tabdata = build_navigation_list(true);

	foreach ($tabdata AS $tab)
	{
		if ($tab['scripts'])
		{
			$scripts = explode('.', $tab['scripts']);

			if (in_array(THIS_SCRIPT, $scripts))
			{
				$tabid = $tab['navid'];
				break;
			}
		}
	}

	return $tabid;
}

/**
* Sets the navbar tabid.
*
* @param	int	tabid
* @param	array	tabs data array
*
* @return	int	Navbar tabid
*/
function set_navigation_tab($tabid = 0, &$tabdata)
{
	global $vbulletin;

	if ($tabdata[$tabid]['type'] != 'tab')
	{
		/* No valid tab was given so we fallback to the forum root.
		The fallback can be changed here if necessary, however products should use the 'set_navigation_tab_xxxx' hooks.
		This fallback will only ever be called if something has gone wrong and we have ended up with an invalid tabid */
		$root = 'vbtab_forum'; // Forum root tab, this should always exist.
		$roots = get_navigation_roots(build_navigation_list());

		($hook = vBulletinHook::fetch_hook('set_navigation_tab_fallback')) ? eval($hook) : false;

		$tabid = $roots[$root];

		// Final fallback.
		if ($tabdata[$tabid]['type'] != 'tab')
		{
			$tabid = $roots['vbtab_forum'];
		}
	}

	($hook = vBulletinHook::fetch_hook('navigation_tab_complete')) ? eval($hook) : false;

	return $tabid;
}

/**
* Renders the navbar tabs, menus & links.
*
* @param	int	tabid
*
* @return	string	Navbar tabs HTML
*/
function render_navigation()
{
	$root = '';
	$root_tab = $roots['vbtab_forum'];

	$tabs = build_navigation_menudata();
	$roots = get_navigation_roots(build_navigation_list());

	$request_tab = intval($_REQUEST['tabid']);
	$script_tab = get_navigation_tab_script();

	$hook_tabid = $tabid = 0;
	($hook = vBulletinHook::fetch_hook('set_navigation_tab_main')) ? eval($hook) : false;

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

	$templater = vB_Template::create('navbar_tabs');
	$templater->register('tabs', $tabs);
	$templater->register('selected', $tabid);

	return $templater->render();
}

/**
* Renders the navbar template with the specified navbits
*
* @param	array	Array of navbit information
*
* @return	string	Navbar HTML
*/
function render_navbar_template($navbits)
{
	// VB API doesn't require rendering navbar.
	if (defined('VB_API') AND VB_API === true)
	{
		return true;
	}

	$navigation = render_navigation();

	$templater = vB_Template::create('navbar');
	$templater->register('navigation', $navigation);
	$templater->register('ad_location', $GLOBALS['ad_location']);
	$templater->register('foruminfo', $GLOBALS['foruminfo']);
	$templater->register('navbar_reloadurl', $GLOBALS['navbar_reloadurl']);
	$templater->register('navbits', $navbits);
	$templater->register('notices', $GLOBALS['notices']);
	$templater->register('notifications_menubits', $GLOBALS['notifications_menubits']);
	$templater->register('notifications_total', $GLOBALS['notifications_total']);
	$templater->register('pmbox', $GLOBALS['pmbox']);
	$templater->register('return_link', $GLOBALS['return_link']);
	$templater->register('template_hook', $GLOBALS['template_hook']);

	return $templater->render();
}

/**
* Gets the max displayorder for each parent
*
* @param	array	Array of navbit information
* @param	bool	Select if returning the element name or navid.
*
* @return	string	array of results
*/
function get_navigation_ordermax($navlist, $byname = true)
{
	global $vbulletin;

	$data = array();

	foreach ($navlist AS $element)
	{
		$id = $byname ? $element['parent'] : $element['parentid'];
		$xid = $element['parent'] ? $id : '#';

		if ($element['displayorder'] > $data[$xid])
		{
			$data[$xid] = $element['displayorder'];
		}
	}

	return $data;
}

/**
* Gets the count of children for each parent
*
* @param	array	Array of navbit information
* @param	bool	Select if returning the element name or navid.
*
* @return	string	array of results
*/
function get_navigation_counts($navlist, $byname = true)
{
	global $vbulletin;

	$counts = array();

	foreach ($navlist AS $element)
	{
		$id = $byname ? $element['parent'] : $element['parentid'];
		$countid = $element['parent'] ? $id : '#';

		if (!$element['active']
		OR !$vbulletin->products[$element['productid']])
		{
			continue;
		}

		$counts[$countid]++;
	}

	return $counts;
}

/**
* Gets the parents for each element
*
* @param	array	Array of navbit information
* @param	bool	Select if specific elements or all types.
* @param	bool	Select if returning the element name or navid.
*
* @return	string	array of results
*/
function get_navigation_parents($navlist, $types = false, $byname = true)
{
	global $vbphrase;

	$parents = array();

	if (!is_array($types))
	{
		$types = array('tab','menu','link');
	}

	foreach ($navlist AS $element)
	{
		if (in_array($element['navtype'], $types))
		{
			$id = $byname ? $element['name'] : $element['navid'];
			$parents[$id] = construct_depth_mark($element['level'], '- - ') . $element['text'] . ' (' . $vbphrase[$element['navtype']] . ')';
		}
	}

	return $parents;
}

// ################### Manager Cell Functions ################### //

function build_element_cell($name, $text, $depth, $bold = false, $subtext = '', $link = '', $url, $do = '', $session = '')
{
	$cell = '<span title="'.$name.'">&nbsp;';
	$cell .= $bold ? '<b>' : '';
	$cell .= construct_depth_mark($depth, '- - ');
	$cell .= $link ? '<a href="'.$link : '';
	$cell .= ($link AND $session) ? '?'.$session : '';
	$cell .= ($link AND $do AND $session) ? '&do=' . $do : '';
	$cell .= ($link AND $do AND !$session) ? '?do=' . $do : '';
	$cell .= $link ? '">' : '';
	$cell .= $text;
	$cell .= $link ? '</a>' : '';
	$cell .= $bold ? '</b>' : '';
	$cell .= '</span>';
	$cell .= $subtext ? '&nbsp;&nbsp;<span class="smallfont" title = "'.$url.'">('.$subtext.')</span>' : '';
	return $cell;
}

function build_checkbox_cell($name, $value = 1, $id = 'id', $checked = false, $disabled = false, $onclick = false)
{
	$current = $disabled ? 3 : 0;
	$cell = '<input type="checkbox" name="'.$name.'" id="'.$id.'"';
	$cell .= ($checked ? ' checked="checked" ' : '');
	$cell .= ($disabled ? ' disabled="disabled" ' : '');
	$cell .= ($onclick ? ' onclick="'.$onclick.';"' : '');
	$cell .= ' value="'.$value.'" />';
	$cell .= '<input id="v'.$id.'" type="hidden" name="v'.$name.'" value="'.$current.'" />';
	return $cell;
}

function build_text_input_cell($name, $value = '', $size = 5, $title = '', $taborder = 1)
{
	$cell = '<input type="text" class="bginput" name="'.$name.'" value="'.$value;
	$cell .= '" tabindex="'.$taborder.'" size="'.$size.'" title="'.$title.'" />';
	return $cell;
}

function build_display_cell($text, $bold = false, $smallfont = false, $istrike = false)
{
	$cell = $smallfont ? '<span class="smallfont">' : '<span>';
	$cell .= $bold ? '<b>' : '';
	$cell .= $istrike ? '<i><s>' : '';
	$cell .= $text;
	$cell .= $istrike ? '</s></i>' : '';
	$cell .= $bold ? '</b>' : '';
	$cell .= '</span>';
	return $cell;
}

function build_action_cell($name, $options, $jsfunction = '', $button = 'Go', $onclick = false, $onchange = false)
{
	$cell = '<select name="'.$name.'"';
	$cell .= ($onchange ? ' onchange="'.$jsfunction.';"' : '') . ' >';
	$cell .= construct_select_options($options) . '</select>';
	$cell .= "\t".'<input type="button" class="button" value="'.$button.'"';
	$cell .= ($onclick ? ' onclick="'.$jsfunction.';"' : '') . ' />';
	return $cell;
}

//----------------------------------------------------//
//   ############## DEBUG  FUNCTIONS ##############   //
//----------------------------------------------------//

function debug_navigation_array($bypass = false)
{
	$navlist = build_navigation_list($bypass);

	foreach($navlist AS $navdata)
	{
		if ($navdata)
		{
			if ($navdata['navtype'] == 'tab')
			{
				echo str_repeat('-',30).'<br />';
			}
			echo str_repeat('-----',$navdata['level']);
			echo strtoupper($navdata['navtype']).': ';
			echo $navdata['navid'].': ';
			echo $navdata['text'].': URL = ';
			echo $navdata['url'].'<br />';
		}
	}

	vbstop('End of Navlist Data',0,0);
}

function debug_navigation_menu_array($bypass = false)
{
	$tabs = build_navigation_menudata($bypass);

	echo str_repeat('-',30).'<br />';
	foreach($tabs AS $tab)
	{
		echo 'Tab : ';
		if ($tab['selected'])
		{
			echo '{Selected} ';
		}
		echo $tab['title'].' , URL = ';
		echo $tab['url'].'<br />';

		if ($tab['children'])
		{
			foreach($tab['children'] AS $link)
			{
				if ($link['children'])
				{
					echo '--Menu : ';
					echo $link['title'].'<br />';
					foreach($link['children'] AS $sublink)
					{
						echo '----SubLink : ';
						echo $sublink['title'].' , URL = ';
						echo $sublink['url'].'<br />';
					}
				}
				else
				{
					echo '--Link : ';
					echo $link['title'].' , URL = ';
					echo $link['url'].'<br />';
				}
			}
		}
		echo str_repeat('-',30).'<br />';
	}

	vbstop('End of Menu Data',0,0);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # RCS: $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>
