<?php
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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
* Order Categories
*
* @param	bool		Force cache to be rebuilt, ignoring copy that may already exist
*
* @return	void
*/
function fetch_ordered_layout_categories($force = false)
{
	global $vbulletin;

	if (isset($vbulletin->cms_layout['categorycache']) AND !$force)
	{
		return;
	}

	$vbulletin->cms_layout['categorycache'] = array();
	$vbulletin->cms_layout['icategorycache'] = array();
	$vbulletin->cms_layout['categorycount'] = 0;

	$categorydata = array();

	$cats = $vbulletin->db->query_read_slave("
		SELECT *
		FROM " . TABLE_PREFIX . "cms_layout_category
		ORDER BY displayorder
	");

	while ($cat = $vbulletin->db->fetch_array($cats))
	{
		$vbulletin->cms_layout['icategorycache']["$cat[parentid]"]["$cat[categoryid]"] = $cat['categoryid'];
		$categorydata["$cat[categoryid]"] = $cat;
	}

	$vbulletin->cms_layout['categoryorder'] = array();
	fetch_layout_category_order();

	foreach ($vbulletin->cms_layout['categoryorder'] AS $categoryid => $depth)
	{
		$vbulletin->cms_layout['categorycache']["$categoryid"] = $categorydata["$categoryid"];
		$vbulletin->cms_layout['categorycache']["$categoryid"]['depth'] = $depth;
		if ($categorydata["$categoryid"])
		{
			$vbulletin->cms_layout['categorycount']++;
		}
	}
}

/**
* Recursive function to build category order
*
* @param	integer	Initial parent forum ID to use
* @param	integer	Initial depth of categories
*
* @return	void
*/
function fetch_layout_category_order($parentid = 0, $depth = 0)
{
	global $vbulletin;

	if (is_array($vbulletin->cms_layout['icategorycache']["$parentid"]))
	{
		foreach ($vbulletin->cms_layout['icategorycache']["$parentid"] AS $categoryid)
		{
			$vbulletin->cms_layout['categoryorder']["$categoryid"] = $depth;
			fetch_layout_category_order($categoryid, $depth + 1);
		}
	}
}

/**
* Function to output select bits
*
* @param integer	The category parent id to select by default
*
* @return	void
*/
function construct_category_select($parentid = 0)
{
	global $vbulletin;

	if (!isset($vbulletin->cms_layout['categorycache']))
	{
		fetch_ordered_layout_categories();
	}

	if (empty($vbulletin->cms_layout['categorycache']))
	{
		return;
	}

	foreach ($vbulletin->cms_layout['categorycache'] AS $categoryid => $category)
	{
		$optionvalue = $categoryid;
		$optiontitle = $category[title];
		$optionclass = 'd' . ($category['depth'] > 4) ? 4 : $category['depth'];
		$optionselected = ($categoryid == $parentid) ? 'selected="selected"' : '';

		$jumpcategorybits .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);
	}

	return $jumpcategorybits;
}

/**
* Function to output select bits
*
* @return	void
*/
function build_layout_category_genealogy()
{
	global $vbulletin;

	fetch_ordered_layout_categories(true);

	// build parent/child lists
	foreach ($vbulletin->cms_layout['categorycache'] AS $categoryid => $category)
	{
		// parent list
		$i = 0;
		$curid = $categoryid;

		$vbulletin->cms_layout['categorycache']["$categoryid"]['parentlist'] = '';

		while ($curid != 0 AND $i++ < 1000)
		{
			if ($curid)
			{
				$vbulletin->cms_layout['categorycache']["$categoryid"]['parentlist'] .= (!empty($vbulletin->cms_layout['categorycache']["$categoryid"]['parentlist']) ? ',' : '') . $curid;
				$curid = $vbulletin->cms_layout['categorycache']["$curid"]['parentid'];
			}
			else
			{
				global $vbphrase;
				if (!isset($vbphrase['invalid_category_parenting']))
				{
					$vbphrase['invalid_category_parenting'] = 'Invalid category parenting setup. Contact vBulletin support.';
				}
				trigger_error($vbphrase['invalid_category_parenting'], E_USER_ERROR);
			}
		}

		// child list
		$vbulletin->cms_layout['categorycache']["$categoryid"]['childlist'] = $categoryid;
		fetch_layout_category_child_list($categoryid, $categoryid);
	}

	$parentsql = '';
	$childsql = '';
	foreach ($vbulletin->cms_layout['categorycache'] AS $categoryid => $category)
	{
		$parentsql .= "	WHEN $categoryid THEN '$category[parentlist]'
		";
		$childsql .= "	WHEN $categoryid THEN '$category[childlist]'
		";
	}

	if (!empty($vbulletin->cms_layout['categorycache']))
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "cms_layout_category SET
				parentlist = CASE categoryid
					$parentsql
					ELSE parentlist
				END,
				childlist = CASE categoryid
					$childsql
					ELSE childlist
				END
		");
	}
}

/**
* Recursive function to populate categorycache with correct child list fields
*
* @param	integer		Category ID to be updated
* @param	integer		Parent forum ID
*
* @return	void
*/
function fetch_layout_category_child_list($maincategoryid, $parentid)
{
	global $vbulletin;

	if (is_array($vbulletin->cms_layout['icategorycache']["$parentid"]))
	{
		foreach ($vbulletin->cms_layout['icategorycache']["$parentid"] AS $categoryid => $categoryparentid)
		{
			$vbulletin->cms_layout['categorycache']["$maincategoryid"]['childlist'] .= ',' . $categoryid;
			fetch_layout_category_child_list($maincategoryid, $categoryid);
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 27303 $
|| ####################################################################
\*======================================================================*/
?>
