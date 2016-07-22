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
 * Content Manager class.
 * This provides useful functions for generating a user interface
 * to allow browsing, moving, copying, editing, etc. of CMS content
 * It is intended for admins or for those with substantial privileges.
 * @author Ed Brown, vBulletin Development Team
 * @version 4.2.2
 * @since 1st Dec, 2008
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_ContentManager
{
	/** array of styles in use **/
	private static $styles = array();
	/** array of layouts in use **/
	private static $layouts = array();
	/** number of items per page **/
	private static $items_perhomepage;
	/** section id for which we are displaying/editing **/
	private static $sectionid;
	/** items per section page- used in layout **/
	private static $content_layout_count = 6;
	/** useful for array of layouts is use **/
	private static $cl_array = array();
	/** time offset, used in publish/edit time handling **/
	private static $time_offset = false;

	//Want to prevent instantiation. This has private methods only.
	private function __construct()
	{
	}

	/************
	* This returns the shared javascript used for all three managers
	************/
	public static function showJs($relative_location = '..')
	{
		global $vbulletin;
		return "
		<script type=\"text/javascript\" src=\"$relative_location/clientscript/vbulletin_ajax_htmlloader.js?v=" . $vbulletin->options['simpleversion'] . "\"></script>
		<script type=\"text/javascript\" src=\"$relative_location/clientscript/vbulletin_overlay.js?v=" . $vbulletin->options['simpleversion'] . "\"></script>
		<script type=\"text/javascript\" src=\"$relative_location/clientscript/vbulletin_cms.js?v=" . $vbulletin->options['simpleversion'] . "\"></script>
		<script type=\"text/javascript\" src=\"$relative_location/clientscript/vbulletin_cms_management.js?v=" . $vbulletin->options['simpleversion'] . "\"></script>

		<script type=\"text/javascript\" >
		var script_location = '$relative_location';
		</script>
	";
	}

	//this is called from ajax to see if an url entered by the user is valid ***/
	public static function checkUrlAvailable()
	{
		global $vbulletin;
		global $vbphrase;
		require_once DIR . '/includes/functions_databuild.php';
		require_once DIR . '/includes/functions_misc.php';
		fetch_phrase_group('cpcms');
		$vbulletin->input->clean_array_gpc('r', array(
			'url' => TYPE_STR,
			'nodeid' => TYPE_INT));

		$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		$xml->add_group('root');
		$url_conflict = '';

		if (strlen($vbulletin->GPC['url'])
			and $row = $vbulletin->db->query_first($sql="SELECT nodeid FROM " . TABLE_PREFIX .
			"cms_node WHERE new != 1 AND lower(url)='" . $vbulletin->db->escape_string(strtolower($vbulletin->GPC['url'])) ."'"
			. ($vbulletin->GPC_exists['nodeid'] ? " and nodeid <> " . $vbulletin->GPC['nodeid'] : "" ) )
			and intval($row['nodeid']))
		{
			$url_conflict = $vbphrase['url_in_use'];
		}

		$xml->add_tag('html', $url_conflict);
		$xml->close_group();
		$xml->print_xml();
		return '';
	}

	/*************************
	 * We use a drilldown approach to select a specific node group. This function creates
	 * the html to create the div including the javascript calls that will populate its
	 * descendant. It uses the javascript load_html call in vbulletin_ajax_htmlloader.js
	 *
	 * @param integer parentid - the id of the parent
	 * @param string $divId - the name/id of the div into which we place the results.
	 * @return string
	 ******************/
	public static function getNodePanel($divId)
	{
		global $vbphrase;
		global $phrasegroups;
		global $sect_js_varname;
		require_once DIR . '/includes/functions_databuild.php';
		require_once DIR . '/includes/functions.php';
		fetch_phrase_group('cpcms');

		$result = "<div id=\"$divId\" style=\"position: absolute;
				display: none;	width:600px;height:380px;background-color:white; text-align:" . vB_Template_Runtime::fetchStyleVar('left') . ";
				overflow: auto;" . vB_Template_Runtime::fetchStyleVar('left') . ":100px;top:100px; border:1px solid #000;position:absolute;clear:both;
				\">
				<div id=\"cms_sections_list\" style=\"width:600px;\">
				<div class=\"tcat\" style=\"height:12px;position:relative;padding:5px 0;\" ><br /><br />
				<div style=\"" . vB_Template_Runtime::fetchStyleVar('left') . ":5px;top:2px;position:absolute;text-align:" . vB_Template_Runtime::fetchStyleVar('left') . ";\"><strong>" . $vbphrase['section_navigator'] . "</strong></div><br />
				<div style=\"" . vB_Template_Runtime::fetchStyleVar('left') . ":50%;width:50%;top:0px;position:absolute;text-align:" . vB_Template_Runtime::fetchStyleVar('right') . ";\">
				<span style=\"text-align:" . vB_Template_Runtime::fetchStyleVar('right') . "\">
				<input type=\"button\" value=\"" . $vbphrase['close'] . "\"
				onclick=\"document.getElementById('$divId').style.display='none'\"/>";
		$result .= "</span></div><br>\n
				</div>
				<div class=\"tcat\" style=\"position:relative;top:9px;" . vB_Template_Runtime::fetchStyleVar('left') . ":10px;font-size:14px;
				font-weight:bold;padding:2px;float:" . vB_Template_Runtime::fetchStyleVar('left') . ";padding:5px;border-style:solid;border-width:1px 1px 0 1px;
				border-color:#000000;\">" . $vbphrase['choose_a_section'] . "</div>
				<div class=\"picker_overlay\" style=\"height:300px;width:570px;" . vB_Template_Runtime::fetchStyleVar('left') . ":10px;top:60px;overflow:auto;position:absolute;display:block;\">" ;
		$result .= self::getSectionList();
		$result .= "</div>\n</div>\n";
		return $result;
	}
	/*************************
	 * On the CMS page we make a javascript call to return a list of clickable nodes
	 * that will add a
	 * @param string $divId - the name/id of the div into which we place the results.
	 * @param integer $sectionid - the id of the parent
	 * @return string
	 ******************/
	public static function getNodeSearchResults()
	{
		global $vbulletin;
		global $vbphrase;
		global $phrasegroups;

		require_once DIR . '/includes/functions_databuild.php';
		require_once DIR . '/includes/functions.php';
		fetch_phrase_group('cpcms');

		if (! isset($vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}

		$vbulletin->input->clean_array_gpc('r', array(
			'title_filter'  => TYPE_STR,
			'contenttypeid' => TYPE_UINT,
			'state_filter'  => TYPE_UINT,
			'formid'        => TYPE_STR,
			'author_filter' => TYPE_UINT));

		$filters = array("node.permissionsfrom in (" . implode(',', array_unique(
			array_merge($vbulletin->userinfo['permissions']['cms']['cancreate'],
			$vbulletin->userinfo['permissions']['cms']['canedit'],
			$vbulletin->userinfo['permissions']['cms']['canpublish']))) . ") ");

		if ($vbulletin->GPC_exists['title_filter'])
		{
			$filters[] = " lower(info2.title) like '%" . strtolower($vbulletin->GPC['title_filter']) . "%' ";
		}

		if ($vbulletin->GPC_exists['state_filter'])
		{
			switch(intval($vbulletin->GPC['state_filter']))
			{
				case 1:
					$filters[] = " node2.setpublish = 0 ";
					break;
				case 2:
					$filters[] = " node2.setpublish > 0 AND node.publishdate <= " . TIMENOW;
					break;
				case 3:
					$filters[] = " node2.setpublish > 0 AND node.publishdate > " . TIMENOW;
					break;
			} // switch
		}

		if ($vbulletin->GPC_exists['author_filter'])
		{
			$filters[] = "node2.userid =" . intval($vbulletin->GPC['author_filter']);
		}

		if ($vbulletin->GPC_exists['contenttypeid'])
		{
			$filters[] = "node2.contenttypeid =" . $vbulletin->GPC['contenttypeid'];
		}

		$filters[] = "node2.new != 1";

		$sql = "SELECT DISTINCT info.title AS section, node.nodeid AS parentid,
			node2.nodeid, user.username, node2.setpublish, node2.publishdate, node2.nodeleft, node2.noderight
			FROM " . TABLE_PREFIX . "cms_node AS node INNER JOIN " .
			TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid
			INNER JOIN " . TABLE_PREFIX .
			"cms_node node2 ON (node2.nodeleft >= node.nodeleft AND node2.nodeleft <= node.noderight)
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info2 ON info2.nodeid = node2.nodeid
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = node2.userid
			WHERE " . implode (" AND ", $filters) .
			"	ORDER BY node2.nodeleft, node.nodeleft";

		if ($rst = $vbulletin->db->query_read($sql))
		{
			//Now it's simple. We walk down the list, composing the
			// parentage as we go.
			$results = array();
			$counter = 0;
			$row = $vbulletin->db->fetch_array($rst);
			$current_nodeid = intval(-1);
			$parentnames = array();
			$lastnode = $row;
			while($row)
			{
					// If the current record isn't a child of the last record,
				// put out the current record. Since we're already sorted by nodeleft, we
				// only need to worry about noderight
				if (intval($row['nodeid']) != $current_nodeid)
				{
					$counter++;
					$published = (intval($lastnode['setpublish']) ? $vbphrase['published'] . ' ' .
						vbdate($vbulletin->options['dateformat'], $lastnode['publishdate']) : $vbphrase['unpublished']);
					$results [$lastnode['nodeid']] = array('leaf' => $lastnode['section'],
						'contenttype' => $vbphrase[strtolower($lastnode['class'])],
						'nodeid' => $lastnode['nodeid'], 'counter' => $counter,
						'author' => $lastnode['username'], 'published' => $published, 'parent' => implode('>', $parentnames) );
					$current_nodeid = intval($row['nodeid']);
					$parentnames = array();
					$lastnode = $row;
				}
				else
				{
					$parentnames[] = $lastnode['section'];
					$lastnode = $row;
				}
				$row = $vbulletin->db->fetch_array($rst);
			}
		}
		//at the end we have to display one more record.
		$counter++;
		$published = (intval($lastnode['setpublish']) ? $vbphrase['published'] . ' ' .
			vbdate($vbulletin->options['dateformat'], $lastnode['publishdate']) : $vbphrase['unpublished']);
		$results [$lastnode['nodeid']] = array('leaf' => $lastnode['section'],
			'contenttype' => $vbphrase[strtolower($lastnode['class'])],
			'nodeid' => $lastnode['nodeid'], 'counter' => $counter,
			'author' => $lastnode['username'], 'published' => $published, 'parent' => implode('>', $parentnames) );

		$template = vB_Template::create('vbcms_ajax_leafresult');
		$template->register('nodelist', $results) ;
		$template->register('count', $counter);
		$template->register('formid',($vbulletin->GPC_exists['formid']? $vbulletin->GPC['formid'] : 'cms_section_data'));
		return $template->render();
	}

	/*********
	* This function creates a list of nodes  based on type and any filters
	* @param string orderby : the sort for the list : 0 = name, 1 = hierarchy
	* @param array $filters : each filter should be a string containing a filter
	*
	* @return array : An associative array
	* each array element is an array of the form ('parent', 'leaf').  The
	* 	array key is the nodeid
	 *********/
	public static function getNodes($orderby = 1, $filters = array())
	{
		global $vbulletin;
		$sql = "SELECT DISTINCT info.title AS section, info2.title,
			node2.nodeid, node.nodeid AS parentid, info2.viewcount, thread.replycount
			FROM " . TABLE_PREFIX . "cms_node AS node INNER JOIN " .
			TABLE_PREFIX . "cms_nodeinfo info ON info.nodeid = node.nodeid
			INNER JOIN " . TABLE_PREFIX .
			"cms_node AS node2 on (node2.nodeleft >= node.nodeleft AND node2.nodeleft <= node.noderight)
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info2 ON info2.nodeid = node2.nodeid
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON thread.threadid = info2.associatedthreadid
			";

		if (count($filters))
		{
			$sql .= "	INNER JOIN " . TABLE_PREFIX . "contenttype AS type
				ON type.contenttypeid = node2.contenttypeid
				WHERE " . implode (" AND ", $filters);
		}
		else
		{
			$sql .= " WHERE node2.contenttypeid = " .  vb_Types::instance()->getContentTypeID("vBCms_Section");
		}

		$sql .= "	ORDER BY " . ($orderby == 0 ? "info2.title" : "node2.nodeleft") .
			" , node.nodeleft;";
		$result = '';
		if ($rst = $vbulletin->db->query_read($sql))
		{
			$count = 0;
			$stack = array();
			$thistitle = '';
			while($row = $vbulletin->db->fetch_array($rst))
			{
				if (intval($row['nodeid']) == intval($row['parentid']) )
				{
					$stack[$count][] = $row;
					$count++;
				}
				else
				{
					$stack[$count][] = $row['section'];
				}
			}
		}
		return $stack;
	}

	public static function getAllCategories()
	{
		$context = new vB_Context('widget_categories' , array('permissions' => vB::$vbulletin->userinfo->permissions['cms']));
		$cache_key = strval($context);

		if (!$nodes = vB_Cache::instance()->read($cache_key,  true, true))
		{
			//First we'll generate the category list
			$permString = vBCMS_Permissions::getPermissionString();
			//compose the sql
			$sql = "SELECT parent.category AS parentcat, cat.categoryid, cat.category, parent.categoryid AS parentid,
			cat.catleft, cat.catright, node.nodeid, info.title, count(nodecat.nodeid) as qty, cat.parentcat as parentcatid
			FROM " . TABLE_PREFIX . "cms_node AS node
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info on info.nodeid = node.nodeid
			INNER JOIN " . TABLE_PREFIX . "cms_category AS parent on parent.parentnode = node.nodeid
			INNER JOIN " . TABLE_PREFIX . "cms_category AS cat ON (cat.catleft >= parent.catleft AND cat.catleft <= parent.catright)
			LEFT JOIN " . TABLE_PREFIX . "cms_nodecategory AS nodecat ON nodecat.categoryid = cat.categoryid
			WHERE node.setpublish > 0 AND node.publishdate <= " . TIMENOW . " AND " . $permString . "
			GROUP BY parent.category, cat.categoryid, cat.category,	cat.catleft, cat.catright, info.title
			ORDER BY node.nodeleft, cat.catleft;";

			$level = 0;
			$nodes = array();
			$dupemap = array();
			$parents = array();
			$rst = vB::$vbulletin->db->query_read($sql);

			$dupemap = array();
			$parents = array();

			$rst = vB::$vbulletin->db->query_read($sql);
			if ($record = vB::$vbulletin->db->fetch_array($rst))
			{
				$record['duplicate'] = 0;
				$record['level'] = $level;
				$dupemap[$record['category']][] = strtolower($record['category'] . ' #' . $record['categoryid']);
				$record['route_info'] = $record['categoryid'] .	($record['category'] != '' ? '-' . str_replace(' ', '-', $record['category']) : '');
				$nodes[strtolower($record['category'] . ' #' . $record['categoryid'])] = $parents[0] = $record;
				$last_category = -1;

				while($record = vB::$vbulletin->db->fetch_array($rst))
				{
					$record['route_info'] = $record['categoryid'] . ($record['category'] != '' ? '-' . str_replace(' ', '-', $record['category']) : '');

					if ($record['categoryid'] == $last_category )
					{
						continue;
					}

					//note that since we're already sorted by by catleft we don't need to check that.
					while((intval($record['catright']) >= intval($parents[$level]['catright'])) AND $level >= 0)
					{
						$level--;
					}
					$level++;
					$record['level'] = $level;

					$record['duplicate'] = 0;
					$dupemap[$record['category']][] = strtolower($record['category'] . ' #' . $record['categoryid']);
					$nodes[strtolower($record['category'] . ' #' . $record['categoryid'])] = $parents[$level] = $record;
					$last_category = $record['categoryid'];
				}
			}
			
			// Flag duplicate names.
			foreach($dupemap AS $data)
			{
				if (sizeOf($data) > 1)
				{
					foreach($data AS $cat)
					{
						$nodes[$cat]['duplicate'] = 1;
					}					
				}
			}

			$keys = array_keys($nodes);
			$size = sizeOf($key);
			for ($i = 0; $i < $size; $i++)
			{
				if ($categoryid == $nodes[$keys[$i]]['categoryid'])
				{
					$nodes[$keys[$i]]['myself'] = true;
				}
				else
				{
					$nodes[$keys[$i]]['myself'] = false;
				}
			}

			vB_Cache::instance()->write($cache_key, $nodes, 1440, 'categories_updated');
		}
		return $nodes;
	}

	/*****
	* This function takes a stylevar name and makes sure it is an absolute path
	*
	* @param	string	stylevar name
	*
	* @return string
	***/
	public static function getAbsolutePath($stylevar_name)
	{
		//We need this because we can call images from admincp or the end pages.
		//The image paths in stylevars will normally be relative paths, but we need
		// absolute paths.

		if (!$stylevar_name)
		{
			return ('');
		}

		$path = vB_Template_Runtime::fetchStyleVar($stylevar_name);

		if ((strtolower(substr($path, 0, 5)) == 'http:') OR (strtolower(substr($path, 0, 6)) == 'https:'))
		{
			return $path;
		}

		$bburl = vB::$vbulletin->options['bburl'];

		//we need to know if we have a trailing slash. Easiest to just remove it
		// if it's there
		if (substr($bburl, -1, 1) == '/')
		{
			$bburl =  substr($bburl, 0, strlen($bburl) - 1);
		}

		if (substr($path, 0, 2) == './')
		{
			$path = $bburl . substr($path, 1);
		}
		else if (strtolower(substr($path, 0, 1)) == '/')
		{
			$path = $bburl . $path;
		}
		else
		{
			$path = $bburl . '/' . $path;
		}

		return $path;
	}



	/** This function is called by javascript for the article editor. It creates a
	 * select list of categories for use by load_html to populate the category selector.
	 *
	 * @param 	none
	 * @return 	string
	 */
	public static function getCategorySelector()
	{

			//parse the data
		vB::$vbulletin->input->clean_array_gpc('r', array(
		'type' =>TYPE_NOHTML,
		'name' =>TYPE_NOHTML,
		'checkedcat' =>TYPE_ARRAY,
		'sort' =>TYPE_INT,
		'value' => TYPE_NOHTML
		));

		$categories = self::getAllCategories();
		$result = '';
		$template_cat = vB_Template::create('vbcms_edit_categorybit');
		$template_section = vB_Template::create('vbcms_edit_sectionbit');

		//if we don't have a name let's default to the current standard. Also make sure
		// we're creating an array
		if (vB::$vbulletin->GPC_exists['name'])
		{
			if (substr(vB::$vbulletin->GPC['name'] ,-2) != '[]')
			{
				vB::$vbulletin->GPC['name'] .= '[]';
			}
		}
		else
		{
			vB::$vbulletin->GPC['name'] = 'categoryids[]';
		}

		$already_checked = vB::$vbulletin->GPC_exists['checkedcat'] ? vB::$vbulletin->GPC['checkedcat'] : array();

		//we have all categories. First let's see if we have a parent category.
		if (vB::$vbulletin->GPC_exists['type'] AND vB::$vbulletin->GPC['type'] == 'catid'
			AND vB::$vbulletin->GPC_exists['value'] AND intval(vB::$vbulletin->GPC['value']))
		{

			//We have a parent category.
			reset($categories);
			$category = current($categories);
			while($category)
			{
				if ($category['parentid'] == intval(vB::$vbulletin->GPC['value'])
					OR in_array($category['categoryid'] ,$already_checked))
				{
					//We got one.
					if (in_array($category['categoryid'] ,$already_checked) )
					{
						$category['checked'] = 'checked="checked"';
					}
						$template_cat->register('category', $category) ;
						$result .= $template_cat->render();
				}
						$category = next($categories);
					}
				}
		else if (vB::$vbulletin->GPC_exists['type'] AND vB::$vbulletin->GPC['type'] == 'section'
			AND vB::$vbulletin->GPC_exists['value'])
		{
				//we have a section.
			$section = false;
			reset($categories);
			$category = current($categories);

			while($category )
			{
				if ($category['nodeid'] == vB::$vbulletin->GPC['value']
					OR in_array($category['categoryid'] , $already_checked))
				{
					if (!$sort AND $category['nodeid'] != $section)
					{
						$template_section->register('category', $category);
					$result .= $template_section->render();
						$section = $category['nodeid'];
					}
					//We got one.
					if (in_array($category['categoryid'] ,$already_checked) )
					{
						$category['checked'] = 'checked="checked"';
					}
						$template_cat->register('category', $category) ;
						$result .= $template_cat->render();
				}
						$category = next($categories);
					}
				}
		else if (vB::$vbulletin->GPC_exists['type'] AND vB::$vbulletin->GPC['type'] == 'search'
			AND vB::$vbulletin->GPC_exists['value'] AND (strlen(vB::$vbulletin->GPC['value']) > 0))
		{
			asort($categories);
			reset($categories);
			$category = current($categories);
			//we have a string. Scan for that.
			while($category)
			{
				if (stripos($category['category'], vB::$vbulletin->GPC['value']) !== false
					OR in_array($category['categoryid'] ,$already_checked) )
				{
					$category['level'] = 0;
					if (in_array($category['categoryid'] ,$already_checked) )
					{
						$category['checked'] = 'checked="checked"';
					}
					$template_cat->register('category', $category) ;
					$template_cat->register('section', $category['title']) ;
					$result .= $template_cat->render();
				}
				$category = next($categories);
			}
		}
		else //we don't have anything that will let us limit. Just return everything.
		{
			// Expand children.
			$names = array();
			foreach ($categories as $nodeid => $record)
			{
				$names[$categories[$nodeid]['categoryid']] = $categories[$nodeid]['category'];

				// Get expanded children.
				if ($categories[$nodeid]['parentcatid'])
				{
					$names[$categories[$nodeid]['categoryid']] = $names[$categories[$nodeid]['parentcatid']] . ' > ' . $names[$categories[$nodeid]['categoryid']];
				}
			}

			$sort = (vB::$vbulletin->GPC_exists['sort'] AND intval(vB::$vbulletin->GPC['sort']));

			if ($sort)
			{
				foreach ($categories as $nodeid => $record)
				{
					// Add section title for duplicates.
					if ($categories[$nodeid]['duplicate'])
					{
						$names[$categories[$nodeid]['categoryid']] = $categories[$nodeid]['title'] . ': ' . $names[$categories[$nodeid]['categoryid']];
					}

					// Set expanded children.
					$categories[$nodeid]['category'] = $names[$categories[$nodeid]['categoryid']];
				}

				ksort($categories);
			}

			$section = false;

			foreach ($categories as $category)
			{
				if (!$sort AND $category['nodeid'] != $section)
				{
					$template_section->register('category', $category);
					$result .= $template_section->render();
					$section = $category['nodeid'];
				}

				if ($sort)
				{
					$category['level'] = 0;
				}
				if (in_array($category['categoryid'] ,$already_checked) )
				{
					$category['checked'] = 'checked="checked"';
				}
				$template_cat->register('category', $category) ;
				$template_cat->register('section', $category['title']) ;
				$result .= $template_cat->render();
			}
		}
		return $result;
	}


	/****************
	 * This function makes a select list of the sections or categories (or any other leaf-type
	 * content type) with their parentage
	 *
	 * @param string orderby : the sort for the list : 0 = name, 1 = hierarchy
	 * @param array $filters : each filter should be a string containing a filter
	 *
	 * @return string : the html for the inner portion of the select
	 ****************/
	public static function getCategoryList($sectionid = false, $show_images = true)
	{
		//first get the array of values;
		global $vbphrase;
		global $phrasegroups;
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');
		$categories = self::getCategories($sectionid);
		$data = $categories['results'];
		$imgpath = self::getImagePath('imgdir_misc');
		if ($categories['count'])
		{
			//Now we walk down the list, composing the
			// parentage as we go.
			$thisline = '';
			$result = ($show_images ? "<img id=\"catchecked_img_0\" style=\"display:none\"
				src=\"" . $imgpath . "/subscribed.png\">" : '')
				. "<a href=\"javascript:flagCategory(-1)\">" .
				$vbphrase['new_top_category'] . "</a><br />\n";
			$lastnodeid = 0;
			$sequence_no = 0;
			foreach ($data as $record)
			{
				if (! intval($record['categoryid']))
				{
					continue;
				}

				if ($lastnodeid != intval($record['nodeid']))
				{
					$result .= $record['parent_title']."<br />\n";
					$lastnodeid = intval($record['nodeid']);
				}
				$sequence_no++;
				$result .= "<input type=\"hidden\" id=\"catedit_id_$sequence_no\" value=\""
					 . $record['categoryid'] ."\">";
				$result .= str_repeat('&nbsp;', $record['cat_level'] * 3) .
					($show_images ? "<img id=\"catchecked_img_$sequence_no\" style=\"display:none\"
					src=\"" . $imgpath . "/subscribed.png\">" : '') .
					 "<a href=\"javascript:flagCategory(" . $record['categoryid'] . ")\">"
					 . $record['category'] ."</a><br />\n";
				$array_index++;
				$parents[$array_index] = $record;
				$last_val = $record;

				$record['parent_title'] = $last_val['parent_title'] . $record['title'] .
					((vB_Template_Runtime::fetchStyleVar('textdirection') == 'ltr') ?
					'&gt;' : '&lt;') ;

				if (intval($record['mast_categoryid']))
				{
					$cat_level++;
				}

			}
			return $result;
		}
		return $vbphrase['no_categories'];
	}


	function sortOrder()
	{

	}

	/****************
	* This function makes a select list of the sections with their parentage
	*
	* @param integer contenttypeid : The type you want
	* @param string orderby : the sort for the list : 0 = name, 1 = hierarchy
	*
	* @return string : the html for the inner portion of the select
	****************/
	public static function getSectionList($orderby = 1)
	{
		global $vbphrase;


		$sortable = vB_Cache::instance()->read('admin_sectionlist_sortable', false, true) ;

		if (!$sortable)
		{
			$sections = self::getSections(false);

			//Now because we allow sorting we need to compose a list which we can optionally sort
			// So let's build an array of $nodeid-> array($title, $parents[]);

			$sortable = array();
			$parents = array();
			$level = -1;
			foreach($sections as $key => $section)
			{
				while(($level > 0) AND $section['noderight'] > $sections[$parents[$level]]['noderight'])
				{
					unset($parents[$level]);
					$level--;
				}
				//Now we could do some more complex sorting, but since we may or may not have to sort- let's just
				// set the key to be the title. We don't need the key particularly and it allows us to do a
				// ksort if we need to.
				$sortable[strtolower($section ['title'])] = array('key' => $key, 'parents' => $parents, 'title' => $section ['title'], 'nodeid' => $section['nodeid']);
				$level++;
				$parents[$level] = $key;
			}
			vB_Cache::instance()->write('admin_sectionlist_sortable', $sortable, 1440, array('sections_updated'));
		}

		//We may need to sort
		if ($orderby == 0)
		{
			ksort($sortable, SORT_STRING);
		}

		//Now it's simple. We walk down the list building the parentage.
		$count = 0;
		$stack = array();
		foreach($sortable as $sorted_section)
		{
			foreach($sorted_section['parents'] AS $parent)
			{
				$thisline .= $sections[$parent]['title'] . ((vB_Template_Runtime::fetchStyleVar('textdirection') == 'ltr') ?
					'&gt;' : '&lt;') ;
				$stack[$count][] = $sections[$parent]['title'];
			}

			$stack[$count][] = '<a href="javascript: setSection('
				. $sorted_section['nodeid'] . ",'"
				. vB_Template_Runtime::escapeJS($sorted_section['title']) . "'); return false;\" onclick=\"javascript:void setSection("
				. $sorted_section['nodeid'] . ",'"
				. vB_Template_Runtime::escapeJS($sorted_section['title']). "');return false;\" style=\"font-weight:bold;\">"
				. $sorted_section['title'] . '</a>';
				$count++;
		}

		$result = '';
		$left_str = vB_Template_Runtime::fetchStyleVar('left');
		$spacer = '<li style="float:' . $left_str. '">&nbsp;&gt;&nbsp; </li>';
		foreach ($stack AS $values)
		{
			$result .= '<ul class="floatcontainer floatlist">';
			foreach ($values AS $value)
			{
				$result .= $spacer;
		 		$result .= '<li style="float:' . $left_str . '">' . $value . '</li>';
			}
			$result .= '</ul>';
		}

		return $result;

	}


	/******************
	 * This gets a category list. If you pass
	 * a nodeid the results will include a flag telling you whether
	 * that node currently belongs in that category
	 **************/
	public static function getCategories($nodeid = false, $title_filter = false, $max_records = 500, $first_record = 0,
		$all_categories = false)
	{
		global $vbulletin;
		global $vbphrase;
		//There is a nasty issue here. We can't use SQL's limit function to select the records we display,
		// because there is no relationship between the number of records we get from the database
		// and the number of category records we return.

		//If we aren't passed a nodeid, let's get the lowest parentnode from the
		//table. This will usually be one.

		if (! $nodeid AND !$all_categories)
		{
			$row = $vbulletin->db->query_first("SELECT MIN(parentnode) AS minval FROM " .
				TABLE_PREFIX . "cms_category");
			$nodeid = $row['minval'];
		}
		$sql = "SELECT node.nodeid, node.nodeleft, node.noderight, info.title,
		 ca_master.categoryid AS mast_categoryid, ca.categoryid, ca.enabled,
		ca_master.category, ca.contentcount, ca.catleft, ca.catright, count(nc.nodeid) as item_count
		FROM " . TABLE_PREFIX . "cms_node AS node
		INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info on info.nodeid = node.nodeid
		LEFT JOIN " . TABLE_PREFIX . "cms_category AS ca on ca.parentnode = node.nodeid
		LEFT JOIN " . TABLE_PREFIX . "cms_category AS ca_master ON (ca.catleft >= ca_master.catleft AND ca.catleft <= ca_master.catright)
			AND ca.parentnode = ca_master.parentnode
		LEFT JOIN " . TABLE_PREFIX . "cms_nodecategory AS nc ON nc.categoryid = ca.categoryid
		WHERE node.contenttypeid = " . vb_Types::instance()->getContentTypeID("vBCms_Section") .
		(intval($nodeid) ? " AND node.nodeid = $nodeid" : '') .
			($title_filter ? " AND LOWER(ca.category) LIKE ('%" .
			$vbulletin->db->escape_string( strtolower($title_filter)) .
			"%')"  : '') .
		" GROUP BY node.nodeid, node.nodeleft, node.noderight, info.title,
		 ca_master.categoryid, ca.categoryid, ca.enabled,
		ca_master.category, ca.contentcount, ca.catleft, ca.catright
		ORDER BY node.nodeleft, ca.catleft, ca_master.catleft ;" ;

 		if ($rst = $vbulletin->db->query_read($sql))
		{
			$result = array();

 			$parentnames = array();
			$cat_level = 0;
			$sequence = 0;
			$start_no = $first_record;
			$end_no = $first_record + $max_records;
 			$lastid = -1;
			while($record = $vbulletin->db->fetch_array($rst))
			{
				//We have these values so when mast_categoryid = categoryid we are done with the category.

				if (intval($lastid) != intval($record['categoryid']) )
				{
					if (intval($lastid) > 0)
					{
						//We can create a record
						$title = array_slice($parentnames, count($parentnames) - 1,1);
						$this_result['category'] = $title[0];
						$this_result['parent_title'] = implode('>', array_slice($parentnames, 0, count($parentnames) - 1)) ;
						$this_result['cat_level'] = $cat_level;
						$result[$lastid] = $this_result;
					}
					$parents = array($record);
					$parentnames = array($record['title']);
					$parentnames[] = $record['category'];
					$cat_level = 0;
					$lastid = $record['categoryid'];
					$this_result = $record;
				}
				else
				{
					$parentnames[] = $record['category'];
					$cat_level++;
				}

			}
 			//we have one more to do at the end.
			if (intval($lastid) > 0)
			{
				//We can create a record
				$title = array_slice($parentnames, count($parentnames) - 1,1);
				$this_result['category'] = $title[0];
				$this_result['parent_title'] = implode('>', array_slice($parentnames, 0, count($parentnames) - 1)) ;
				$this_result['cat_level'] = $cat_level;
				$result[$lastid] = $this_result;
			}

		}
		return array('count' => count($result),
			'results' =>array_slice($result, $first_record, $max_records) );
	}

	/******************
	 * This gets a section list
	 **************/
	protected static function getSection($sectionid)
	{
		global $vbulletin;

		//We always want the first record to be the one the user selected, or the
		// home page if they didn't select one.
		$contenttypeid = vb_Types::instance()->getContentTypeID("vBCms_Section");
		if ($sectionid)
		{
			$where = " parent.nodeid = $sectionid" ;
			$result =  array($vbulletin->db->query_first( $sql = "SELECT  node.nodeid,
			node.nodeleft, node.parentnode, node.setpublish, node.publishdate,
			config.value AS per_page, info.title AS title, config2.value as priority,
			config3.value as content_layoutid, info2.title as section_title,
			sum(info.viewcount) AS viewcount,
			SUM(CASE when n2.contenttypeid = $contenttypeid THEN 1 ELSE 0 END) AS section_count,
	  		SUM(CASE WHEN n2.contenttypeid <> $contenttypeid THEN 1 ELSE 0 END) AS item_count
	    	FROM "
			. TABLE_PREFIX . "cms_node AS node
			LEFT JOIN "
			. TABLE_PREFIX . "cms_node AS n2 ON n2.parentnode = node.nodeid AND n2.new != 1
	    	LEFT JOIN "
			. TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid
	    	LEFT JOIN "
			. TABLE_PREFIX . "cms_nodeinfo AS info2 ON info2.nodeid = n2.nodeid
	    	LEFT JOIN "
			. TABLE_PREFIX . "cms_nodeconfig AS config ON config.nodeid = node.nodeid AND config.name = 'items_perhomepage'
	    	LEFT JOIN "
			. TABLE_PREFIX . "cms_nodeconfig AS config2 ON config2.nodeid = node.nodeid AND config2.name = 'section_priority'
	    	LEFT JOIN "
			. TABLE_PREFIX . "cms_nodeconfig AS config3 ON config3.nodeid = node.nodeid AND config3.name = 'content_layout'
	    	WHERE node.nodeid = $sectionid
			GROUP BY node.nodeid, node.nodeleft, node.parentnode, node.setpublish, node.publishdate,
			config.value"));
		}
		else
		{
			//We want to have the first record be the home page.
			$result =  array($vbulletin->db->query_first($sql = "SELECT node.nodeid,
			node.nodeleft, node.parentnode, node.setpublish, node.publishdate,
			config.value AS per_page, info.title AS title, config2.value as priority,
			config3.value as content_layoutid, info2.title as section_title,
			sum(info.viewcount) AS viewcount,
			SUM(CASE when n2.contenttypeid = $contenttypeid THEN 1 ELSE 0 END) AS section_count,
	  		SUM(CASE WHEN n2.contenttypeid <> $contenttypeid THEN 1 ELSE 0 END) AS item_count
	    	FROM "
			. TABLE_PREFIX . "cms_node AS node
			LEFT JOIN "
			. TABLE_PREFIX . "cms_node AS n2 ON n2.parentnode = node.nodeid AND n2.new != 1
	    	LEFT JOIN "
			. TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid
	    	LEFT JOIN "
			. TABLE_PREFIX . "cms_nodeinfo AS info2 ON info2.nodeid = n2.nodeid
	    	LEFT JOIN "
			. TABLE_PREFIX . "cms_nodeconfig AS config ON config.nodeid = node.nodeid AND config.name = 'items_perhomepage'
	    	LEFT JOIN "
			. TABLE_PREFIX . "cms_nodeconfig AS config2 ON config2.nodeid = node.nodeid AND config2.name = 'section_priority'
	    	LEFT JOIN "
			. TABLE_PREFIX . "cms_nodeconfig AS config3 ON config3.nodeid = node.nodeid AND config3.name = 'content_layout'
	    	WHERE node.parentnode IS NULL
			GROUP BY node.nodeid, node.nodeleft, node.parentnode, node.setpublish, node.publishdate,
			config.value"));
			$where = " parent.parentnode IS NULL ";
		}

		if ($rst = $vbulletin->db->query_read($sql = "SELECT node.nodeid,
		node.nodeleft, node.parentnode, node.setpublish, node.publishdate,
		config.value AS per_page, info.title AS title, config2.value as priority,
		config3.value as content_layoutid, info.title as section_title,
		sum(info.viewcount) AS viewcount,
		SUM(CASE when n2.contenttypeid = $contenttypeid THEN 1 ELSE 0 END) AS section_count,
  		SUM(CASE WHEN n2.contenttypeid <> $contenttypeid THEN 1 ELSE 0 END) AS item_count,
		node.layoutid, node.styleid
    	FROM "
			. TABLE_PREFIX . "cms_node AS parent
    	INNER JOIN "
			. TABLE_PREFIX . "cms_node AS node on parent.nodeid = node.parentnode
		LEFT JOIN "
			. TABLE_PREFIX . "cms_node AS n2 ON n2.parentnode = node.nodeid AND n2.new != 1
    	LEFT JOIN "
			. TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid
    	LEFT JOIN "
			. TABLE_PREFIX . "cms_nodeconfig AS config ON config.nodeid = node.nodeid AND config.name = 'items_perhomepage'
    	LEFT JOIN "
			. TABLE_PREFIX . "cms_nodeconfig AS config2 ON config2.nodeid = node.nodeid AND config2.name = 'section_priority'
    	LEFT JOIN "
			. TABLE_PREFIX . "cms_nodeconfig AS config3 ON config3.nodeid = node.nodeid AND config3.name = 'content_layout'
     	WHERE node.contenttypeid = $contenttypeid AND $where
		GROUP BY node.nodeid, node.nodeleft, node.parentnode, node.setpublish, node.publishdate,
		config.value,  info.title , config2.value,
		config3.value
		ORDER BY node.nodeleft;"))
		{
			while($record = $vbulletin->db->fetch_array($rst))
			{
				$result[] = $record;
			}
			$vbulletin->db->free_result($rst);
			return $result;
		}
		return false;
	}


	/******************
	 * This gets a list of leaves of the current node, with content type
	 **************/
	protected static function getLeaves($sectionid)
	{
		global $vbulletin;

		$sql = "SELECT node.nodeid,
		node.nodeleft, node.parentnode, type.isaggregator
		info.title, COUNT(distinct n2.nodeid) AS level2_count,
  		COUNT(distinct n3.parentnode) AS section_count,
		node.layoutid, node.styleid
    	FROM "
			. TABLE_PREFIX . "cms_node AS node
		INNER JOIN " . TABLE_PREFIX . "contenttype AS type ON type.contenttypeid = node.contenttypeid";

		if ($sectionid)
		{
			$sql .= " INNER JOIN " . TABLE_PREFIX . "cms_node AS node2 ON (node.nodeleft >=
			node2.nodeleft AND node.nodeleft <= node2.noderight) WHERE node.new != 1 AND node2.nodeid = $sectionid";
		}
		else
		{
			$sql .= " WHERE node.new != 1 AND node.parentnode IS NULL ";
		}

		if ($rst = $vbulletin->db->query_read($sql))
		{
			$result = array();
			while($record = $vbulletin->db->fetch_array($rst))
			{
				$result[] = $record;
			}

			return $result;
		}
		return false;
	}


	/*****
	* This function lists the sections in an indented heirarchy so
	* the user can select a category
	*****/
	public static function showSections($per_page = 50)
	{
		global $vbulletin;
		global $vbphrase;
		$vbulletin->input->clean_array_gpc('r', array(
			'page' =>TYPE_INT,
			'sectionid' =>TYPE_INT,
			'contenttypeid' => TYPE_INT
			));

		$page = $vbulletin->GPC_exists['page'] ?
			$vbulletin->GPC['page'] : 1;


		return self::showJs() . "\n" .
			self::listSections($page, $per_page);
	}

	/***************
	 * This creates the interface for managing categories
	 *
	 * @param integer $perpage : The number of items to display per page.
	 *
	 * @return string
	 ***************/
	public static function showCategories($nodeid, $per_page = 50, $page = 1)
	{
		global $vbulletin;
		global $vbphrase;
		global $phrasegroups;
		global $sect_js_varname;
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');

		$page = max($page, 1);
		$categories = self::getCategories($nodeid,
			($vbulletin->GPC_exists['title_filter'] ? $vbulletin->GPC['title_filter'] : false),
			$per_page, ($page -1) * $per_page );
		$data = $categories['results'];
		$sequence_no = ($page - 1) * $per_page;
		if (! count($data))
		{
			//This has no sub-categories, so let's pull the section title.
			$data = $vbulletin->db->query_first("SELECT title FROM " . TABLE_PREFIX . "cms_nodeinfo
				WHERE nodeid " . (intval($nodeid) ? " = $nodeid" : "IS NULL") );
			$data['category'] = $vbphrase['no_categories'];
			$data = array($data);
		}
		$result = self::showJs() . "\n" ;

		$result .= self::getCategoryHeaders($nodeid) . "\n\n\n";
		$result .= print_form_header('cms_content_admin', 'save_categories', false, true, 'cms_data', '90%', '_self',true, 'post', 0, false);

		$result .= "<tr><td>\n\n\n\n\n";

		$result.="<table id=\"category_info\" class=\"tborder\" cellpadding=\"4\" border=\"0\" width=\"100%\" align=\"center\"> ";

		$result .= "<tr class=\"tcat\">
			<td class=\"feature_management_header\" colspan=\"4\" align=\"" . vB_Template_Runtime::fetchStyleVar('left') . "\" style=\"padding:5px;\">"
			.$vbphrase['you_are_managing_sub_categories_for']
			. ' ' . $vbphrase['section'] . ': <span class="section_name">'
			. $data[0]['title'] . '</span>' .
			" <input type=\"button\"  value=\"" .
					$vbphrase['change_section'] . " \" onclick=\"javascript:showCatEdit('filter', " . ($nodeid ? $nodeid : 1) . ", -1, '','" .
					vB_Template_Runtime::escapeJS($vbphrase['select_section']) . "' );\" />
			</td>
		</tr>";

		//

		$result .= "<tr class=\"thead\">
				<td class=\"thead\">#</td>
				<td class=\"thead\">"	. $vbphrase['table_header_category_name'] . " </td>
				<td class=\"thead\" style=\"display:none;\">"	. $vbphrase['published'] . "</td>
				<td class=\"thead\">"	. $vbphrase['item_count'] . "</td>
			</tr>";

		$bgclass = fetch_row_bgclass();

		//We have both section and category, both of which are hierarchical. We need to store the hierarchy in an
		// array, and use array_push and array_pop to handle the hierarchy.
		$last_val = array('nodeleft' => 0, 'noderight' => 9999999, 'parent_title' => '',
			'section' => '', 'catleft' => 0, 'catright' => 9999999, 'level' => 0 );
		$parents = array(0 => $last_val);
		$array_index = 0;
		foreach ($data as $record)
		{
			//We need to go up the hierarchy until we find a parent of the current node.

			//This record is a child of the previous category. The section doesn't change,
			// but we need to set the category.
			$cat_edit_node_id = $record['nodeid'] ? $record['nodeid'] : -1;
			$cat_edit_cat_id = $record['categoryid'] ? $record['categoryid'] : -1;

			$category_name_display = str_repeat('&mdash;' , $record['cat_level'] * 1) . ' ' . $record['category'];

			if (($cat_edit_node_id == -1) && ($cat_edit_cat_id == -1)) {
				$category_name_display =
					$vbphrase['no_categories_in_section']
					. ', ' . "<a href=\"javascript:showCatEdit('new',  " . ($nodeid ? $nodeid : 1) . ",-1,'','". vB_Template_Runtime::escapeJS($vbphrase['pick_a_section_below']). "')\">"
					. $vbphrase['click_here_to_create_category'] . '</a>.';
			}

			$sequence_no++ ;
			//If we have child categories then we show the "+" and javascript to expand.
			$result .= "
			<tr class=\"$bgclass\" align=\"center\" valign=\"middle\"><input type=\"hidden\" name=\"id_$sequence_no\" value=\"" .
			$record['categoryid']  . "\"/>
			<td>$sequence_no </td>
			<td align=\"" . vB_Template_Runtime::fetchStyleVar('left') . "\"><span align=\"" . vB_Template_Runtime::fetchStyleVar('left') . "\">" .	$category_name_display . "</span>
			<div style=\"float:" . vB_Template_Runtime::fetchStyleVar('right') . "\">
				<a href=\"javascript:showCatEdit('new'," . $cat_edit_node_id. ', ' .
				$cat_edit_cat_id . ", '', '" . vB_Template_Runtime::escapeJS($vbphrase['pick_a_section_below']) . "')\">
				<img src=\"" . self::getImagePath('imgdir_cms') . "/add_small.png\" style=\"border-style:none\"
				 title=\"" . $vbphrase['add_subcategory'] . "\" alt=\"" . $vbphrase['add_subcategory'] . "\"></a>
				<a href=\"javascript:showCatEdit('edit',". $cat_edit_node_id. ', ' . $cat_edit_cat_id . ", '"
					.  vB_Template_Runtime::escapeJS($record['category']) . "', '" .
					vB_Template_Runtime::escapeJS($vbphrase['pick_a_section_below']) . "')\")\">
					<img src=\"" . self::getImagePath('imgdir_cms') . "/edit_small.png\" style=\"border-style:none\" title=\"" .
					$vbphrase['edit_move_category'] . "\" alt=\"" . $vbphrase['edit_move_category'] . "\"></a>
				<a href=\"javascript:confirmCategoryDelete(" . $record['categoryid'] . ', \'' .
					addslashes_js($vbphrase['confirm_deletion']). "');\"><img src=\"" . self::getImagePath('imgdir_cms') . "/delete_small.png\" style=\"border-style:none\"  alt=\"" .
					$vbphrase['delete_category'] . "\" title=\"" . $vbphrase['delete_category'] . "\"></a>
				</div>
			</td>
					<td style=\"display:none;\"><select name=\"state_" . $record['nodeid']. "\" id=\"state_" . $record['nodeid']. "\"
					onchange=\"setFormValue('do', 'saveonecategorystate');
					setFormValue('nodeid', '" . $record['nodeid']. "');document.getElementById('cms_data').submit();\">" .
					self::getPublishedSelect($record['categoryid'], $record['enabled'], TIMENOW - 10000). "
			</td>
			<td>" . $record['item_count'] . "</td>\n";
			$result .= "</tr>";
			//NOTE: -ch: getPublishedSelect above outputs a close </select> tag already. removed extra </select>
		}

		//we need the total record count.
		if ($record = $vbulletin->db->query_first($sql = "SELECT COUNT(*) AS count
			FROM " . TABLE_PREFIX . "cms_category "))
		{
			$record_count = $record['count'];
		}
		$result .= "
		</table>
		<input type=\"hidden\" id=\"sectionid\" name=\"sectionid\" value=\"" . $nodeid . "\">
		<input type=\"hidden\" id=\"title\" name=\"title\">
		<input type=\"hidden\" id=\"sentfrom\" name=\"sentfrom\" value=\"category\">
		<input type=\"hidden\" id=\"target_categoryid\" name=\"target_categoryid\">
		<input type=\"hidden\" id=\"page\" name=\"page\" value=\"$page\">
		<input type=\"hidden\" id=\"categoryid\" name=\"categoryid\" value=\"\">
		". self::getNav($per_page, $categories['count'], $page, 'category', 100, 'page',
			true,('cms_content_admin.php' .
			($vbulletin->GPC_exists['sectionid'] ? '?sectionid=' . $vbulletin->GPC['sectionid'] : ''))) . "
		</div>\n\n\n\n\n</td></tr></table>";

		$result .= self::getCategoryEditPanel() ."\n";
		return $result;
	}

	/***********************
	 * This is a high-level function which generates a standard list page.
	 * @param object $currentuser.
	 *
	 * @return nothing
	 **********************/
	public static function showNodes($per_page)
	{
		global $vbulletin;
		global $vbphrase;
		$vbulletin->input->clean_array_gpc('r', array(
			'page' =>TYPE_INT,
			'contenttypeid' => TYPE_INT
			));

		$page = $vbulletin->GPC_exists['page'] ?
			$vbulletin->GPC['page'] : 1;


		return self::showJs() . "\n" .
			self::listNodes($page, $per_page);

	}

	/********
	* This function creates a panel for editing a category title and the location. It's opened by
	*  a javascript call
	*********/
	private static function getCategoryEditPanel()
	{
		global $vbphrase;
		$result = "
		<div style=\"position:absolute;top:30px;" . vB_Template_Runtime::fetchStyleVar('left') . ":50px;height:450px;display:none;background-color:#ffffff;border:3px solid black;width:700px;\"
		id=\"title_editor\" onblur=\"this.style.display=none;\">
		<div class=\"tcat\" style=\"height:12px;position:relative;padding:5px;\" id=\"category_nav_header\">
		<div style=\"" . vB_Template_Runtime::fetchStyleVar('left') . ":0px;top:0px;position:absolute;text-align:" . vB_Template_Runtime::fetchStyleVar('left') . ";\"><strong>" .  $vbphrase['category_editor_section_navigator'] . "</strong>
		</div><br />&nbsp;
		<div style=\"" . vB_Template_Runtime::fetchStyleVar('left') . ":50%;width:50%;top:0px;position:absolute;text-align:" . vB_Template_Runtime::fetchStyleVar('right') . ";\">
			<input type=\"button\" value=\"" . $vbphrase['save'] . "\" onclick=\"setCategory('" .
			str_replace('"', '\"', vB_Template_Runtime::escapeJS(htmlspecialchars_uni($vbphrase['need_category_title']))) . "', '" .
			str_replace('"', '\"', vB_Template_Runtime::escapeJS(htmlspecialchars_uni($vbphrase['need_section_or_category']))) . "' );\">
			<input type=\"button\" value=\"" . $vbphrase['close'] .
			"\" onclick=\"document.getElementById('title_editor').style.display='none';\">
		</div>
	</div><!--category_nav_header -->

	<div id=\"category_title_controls\">" . $vbphrase['enter_a_category_title'] .
		": <input type=\"text\" style=\"width:200px\" maxlength=\"40\" id=\"category_title\" name=\"category_title\" />" . '' /* -ch: redundant/confusing: $vbphrase['limit_by_section'] */ . "
	</div>

	<div id=\"section_picker_controls\" style=\"width:330px;\">
		<div id=\"section_tab\" style=\"font-size:14px;font-weight:bold;padding:2px;padding-bottom:5px;position:absolute;" . vB_Template_Runtime::fetchStyleVar('left') . ":5px;top:50px;background-color:#b2b2ba;
			border-style:solid;border-width:1px 1px 0px 1px;width:332px;height:38px;\"
			onclick=\"javascript:setSectionView()\">". $vbphrase['pick_a_section_below']. "
		</div>
		<div class=\"picker_overlay\" id=\"catedit_section_list\" style=\"height:340px;width:330px;" . vB_Template_Runtime::fetchStyleVar('left') . ":5px;top:95px;overflow:auto;position:absolute;display:block;\">
	";

	$sections = self::getNodes();
	//Here is the section list
	$counter = 0;
	$spacer = '<li style="float:' . vB_Template_Runtime::fetchStyleVar('left') . '">&nbsp;&gt;&nbsp; </li>';
	foreach ($sections AS $section)
	{
		$counter++;
		$result .= '<ul class="floatcontainer floatlist" id="sectchecked_ul_' . $counter . '">';
		$path = '';

		$subcount = 0;
		foreach ($section AS $value)
		{
			if ($subcount++ > 0)
			{
				$result .= $spacer;
	}
			else
			{
				$result .= "<img class=\"selected_marker\" id=\"sectchecked_img_$counter\" style=\"display:none\"
					src=\"" . self::getImagePath('imgdir_misc') . "/subscribed.png\" alt=\"" . $vbphrase['you_are_managing_sub_categories_for'] . " " . $section . "\">";
			}
			if (is_array($value))
			{
				$section = $value['section'];
				$path .= $section;
				$final_value = "<input type=\"hidden\" id=\"sectedit_id_$counter\" value=\"$value[nodeid]\">" .
					"<a class=\"section_switch_link\" title=\"" . $vbphrase['click_here_to_switch_to']. " " . $section . "\" style=\"font-weight:bold;\" href=\"javascript:setSection($value[nodeid], '');\">"
					. $section . "</a>";
			}
			else
			{
				$final_value = $value;
				$path .= $final_value . '&nbsp;&gt;&nbsp;';
	 		}
	 		$result .= '<li style="float:' . vB_Template_Runtime::fetchStyleVar('left') . '">' . $final_value . '</li>';
		}
		$result .= '</ul>';
	}

	$result .= "
	</div><!--catedit_section_list -->
	</div><!--section_picker_controls -->
	<div id=\"category_selector\" style=\"position:absolute;width:330px;" . vB_Template_Runtime::fetchStyleVar('left') . ":350px\">
		<div id=\"category_tab\"  style=\"font-size:14px;font-weight:bold;padding:2px;padding-bottom:5px;position:absolute;" . vB_Template_Runtime::fetchStyleVar('left') . ":5px;top:5px;background-color:#b2b2ba;
			border-style:solid;border-width:1px 1px 0px 1px;width:335px;height:38px;\"
			onclick=\"javascript:setCategoryView()\" >". $vbphrase['choose_category_to_create'] ."
		</div>
		<div id=\"catedit_category_list\" style=\"height:345px;width:350px;" . vB_Template_Runtime::fetchStyleVar('left') . ":5px;top:50px;overflow:auto;position:absolute;display:block;border-style:solid;border-width:1px 1px 1px 1px;\">
			" . self::getCategoryList().  "
		</div><!--catedit_category_list -->
	</div><!--category_selector -->
</div><!-- title_editor -->";
		return $result;
	}

	/********
	 * This function creates a panel for editing a section title and location. It's opened by
	 *  a javascript call
	 *********/
	private static function getSectionEditPanel()
	{
		global $vbphrase;
		$result = "<div
			style=\"text-align:" . vB_Template_Runtime::fetchStyleVar('left') . ";position:absolute;width:500px;top:30px;" . vB_Template_Runtime::fetchStyleVar('left') . ":50px;height:400px;display:none;background-color:#ffffff\"
			id=\"title_editor\"
			onblur=\"this.style.display=none;\">
		<div class=\"tcat\" style=\"height:12px;position:relative;padding:5px;\" ><br /><br />
		<div style=\"" . vB_Template_Runtime::fetchStyleVar('left') . ":0px;top:0px;position:absolute;text-align:" . vB_Template_Runtime::fetchStyleVar('left') . ";padding:5px;\"><strong>" . $vbphrase['edit_section'] . "</strong></div></div><br />&nbsp;
		<div style=\"" . vB_Template_Runtime::fetchStyleVar('left') . ":50%;width:50%;top:0px;position:absolute;text-align:" . vB_Template_Runtime::fetchStyleVar('right') . ";\">";
		$result .= "<input type=\"button\" value=\"" . $vbphrase['save'] . "\" onclick=\"setEditedSection('" . $vbphrase['need_title'] . " ');\">
		<input type=\"button\" value=\"" . $vbphrase['close'] .
		"\" onclick=\"document.getElementById('title_editor').style.display='none';\"></div>
		<div class=\"picker_overlay\" id=\"catedit_section_list\" style=\"height:300px;width:480px;" . vB_Template_Runtime::fetchStyleVar('left') . ":10px;top:35px;overflow:auto;position:absolute;font-weight:bold;\">
		" . $vbphrase['enter_section_title'] . "&nbsp;<input type=\"text\" size=\"20\" id=\"section_title\" name=\"section_title\" onclick=\"if(this.value=='".$vbphrase['new_section']."'){this.value='';}\"><br>
		";
		$sections = self::getNodes();
		//Here is the section list
		$counter = 0;
		$spacer = '<li style="float:' . vB_Template_Runtime::fetchStyleVar('left') . '">&nbsp;&gt;&nbsp; </li>';
		foreach ($sections AS $section)
		{
			$counter++;
			$result .= '<ul class="floatcontainer floatlist" id="sectchecked_ul_' . $counter . '">';
			$path = '';

			$subcount = 0;
			foreach ($section AS $value)
			{
				if ($subcount++ > 0)
				{
					$result .= $spacer;
		}
				else
				{
					$result .= "<img class=\"selected_marker\" id=\"sectchecked_img_$counter\" style=\"display:none\"
						src=\"" . self::getImagePath('imgdir_misc') . "/subscribed.png\" alt=\"" . $vbphrase['you_are_managing_sub_categories_for'] . " " . $section . "\">";
				}
				if (is_array($value))
				{
					$section = $value['section'];
					$path .= $section;
					$final_value = "<input type=\"hidden\" id=\"sectedit_id_$counter\" value=\"$value[nodeid]\">" .
						"<a class=\"section_switch_link\" title=\"" . $vbphrase['click_here_to_switch_to']. " " . $section . "\" style=\"font-weight:bold;\" href=\"javascript:setSection($value[nodeid], '');\">"
						. $section . "</a>";
				}
				else
				{
					$final_value = $value;
					$path .= $final_value . '&nbsp;&gt;&nbsp;';
		 		}
		 		$result .= '<li style="float:' . vB_Template_Runtime::fetchStyleVar('left') . '">' . $final_value . '</li>';
			}
			$result .= '</ul>';
		}
		$result .= "
		</div>
		</div>\n";
		return $result;
	}

	/******
	* This function makes a select list to set the order this cms item is displayed on
	* this section's home page. Note that we can be sure that all the values will be for the same
	* section, so we can store the quantity
	******/
	public static function getOrderSelect($displayorder, $sectionid)
	{
		//global $vbulletin;

//		if (!isset(self::$items_perhomepage))
//		{
//			self::$sectionid = $sectionid;
//
//			if (intval($sectionid) AND ($record = $vbulletin->db->query_first("SELECT value FROM ". TABLE_PREFIX .
//			"cms_nodeconfig WHERE nodeid = $sectionid AND name = 'items_perhomepage';" )))
//			{
//				self::$items_perhomepage = max(min(intval($record['value']), 20),3);
//			}
//			else
//			{
//				self::$items_perhomepage = 7;
//			}
//		}
		$result = "<option value=\"0\"> </option>\n";

		for ($i = 1; $i <= 100; $i++)
		{
			$result .= "<option value=\"$i\"" .
				(intval($displayorder) == $i ? ' selected="selected">' : '>')
				."$i</option>\n";
		}

		return $result;
	}

	/**Thre are several places we would like to get the user's timezone offset
	* The logic is pulled from vbdate()
	***/
	public static function getTimeOffset($userinfo, $adjust_for_server = false)
	{
		if (is_array($userinfo) AND isset($userinfo['timezoneoffset']))
		{
			if (isset($userinfo['dstonoff']) AND $userinfo['dstonoff'])
			{
				// DST is on, add an hour
				$userinfo['timezoneoffset']++;
				if ((substr($userinfo['timezoneoffset'], 0, 1) != '-') AND (substr($userinfo['timezoneoffset'], 0, 1) != '+'))
				{
					// recorrect so that it has a + sign, if necessary
					$userinfo['timezoneoffset'] = '+' . $userinfo['timezoneoffset'];
				}

			}
			if (vB::$vbulletin->options['dstonoff'] AND $adjust_for_server)
			{
				$userinfo['timezoneoffset']--;
			}
			$hourdiff = $userinfo['timezoneoffset'] * 3600;
		}
		else
		{
			$hourdiff = vB::$vbulletin->options['hourdiff'];
		}
		return $hourdiff;
	}

	/*********************
	 * This function displays an array of nodes.
	 *
	 * @param none
	 *
	 * @return array of node records.
	 ****/
	private static function getContent($page = 1, $per_page = 10)
	{
		global $vbulletin;
		require_once DIR . '/vb/cache.php';

		$where = array();

		if ($vbulletin->GPC_exists['title_filter'] AND $vbulletin->GPC['title_filter'] != '')
		{
			$where[] = "lower(nodeinfo.title) like '%"
				. $vbulletin->db->escape_string(strtolower($vbulletin->GPC['title_filter']))
				. "%'";
		}

		if ($vbulletin->GPC_exists['contenttypeid'] AND $vbulletin->GPC['contenttypeid'])
		{
			$where[] = "node.contenttypeid = " . intval($vbulletin->GPC['contenttypeid']);
		}
		else
		{
			$where[] =" contenttype.isaggregator = '0'";
		}

		if ($vbulletin->GPC_exists['state_filter'] AND $vbulletin->GPC['state_filter'])
		{
			switch($vbulletin->GPC['state_filter'])
			{
				case 1 : //Not published
					$where[] = "node.setpublish = '0'";
					break;
				case 2: //Published
					$where[] = "node.setpublish = '1' AND node.publishdate <=" . TIMENOW;
					break;
				case 3: //Published with future date
					$where[] = "node.setpublish = '0' AND node.publishdate >" . TIMENOW;
					break;
				default:
				;
			} // switch
		}

		if ($vbulletin->GPC_exists['author_filter'] AND $vbulletin->GPC['author_filter'] )
		{
			$where[] = "node.userid =" . intval($vbulletin->GPC['author_filter']);
		}


		if ($vbulletin->GPC_exists['filter_section'] AND $vbulletin->GPC['filter_section'])
		{
			$where[] = "parent.nodeid =" . intval($vbulletin->GPC['filter_section']);
		}
		else if ($vbulletin->GPC_exists['sectionid'] AND intval($vbulletin->GPC['sectionid']) > 0)
		{
			$where[] = "parent.nodeid =" . intval($vbulletin->GPC['sectionid']);
		}
		else
		{
			$where[] = "parent.parentnode IS NULL ";
		}


		if ($vbulletin->GPC_exists['nodegroup'])
		{
			$where[] = "node.parentnode =" . $vbulletin->GPC['nodegroup'];
		}

		$where[] = "node.new != 1";

		$where = (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');

		$order = 'ASC';
		switch ($vbulletin->GPC['sortby'])
		{
			case 'title':
			case 'class':
			case 'publicpreview':
			case 'setpublish':
			case 'username':
			case 'publishdate':
			case 'replycount':
				$sortby = $vbulletin->GPC['sortby'];
				break;
			case 'viewcount':
				$sortby = $vbulletin->GPC['sortby'];
				$order = 'DESC';
				break;
			default:
				$sortby = "
					CASE
						WHEN disporder.displayorder > 0
							THEN disporder.displayorder
						ELSE 99999
					END,
					node.lastupdated DESC, nodeinfo.title
				";
		}

		if ($recordset = $vbulletin->db->query_read($sql = "SELECT node.nodeid,
			nodeinfo.title, node.userid, user.username, node.publishdate, node.setpublish,
			node.parentnode, parentinfo.title AS parent_title, disporder.displayorder, contenttype.class,
			node.contenttypeid, node.layoutid, node.styleid, node.onhomepage, nodeinfo.viewcount AS viewcount,
			thread.replycount, parent.nodeid as parentid, disporder.displayorder, node.publicpreview
			FROM " . TABLE_PREFIX . "cms_node AS node
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS nodeinfo ON nodeinfo.nodeid = node.nodeid
			INNER JOIN " . TABLE_PREFIX . "contenttype AS contenttype ON contenttype.contenttypeid = node.contenttypeid
			INNER JOIN " . TABLE_PREFIX . "cms_node AS parent ON (node.nodeleft >= parent.nodeleft AND node.nodeleft <= parent.noderight)
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS parentinfo ON parentinfo.nodeid = parent.nodeid
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = node.userid
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread on thread.threadid = nodeinfo.associatedthreadid
			LEFT JOIN " . TABLE_PREFIX . "cms_sectionorder AS disporder ON disporder.nodeid = node.nodeid
				AND disporder.sectionid = parent.nodeid
			$where
			ORDER BY $sortby $order
			LIMIT " . (($page - 1) * $per_page) . ", " . ($per_page * 10)
		))
		{
			$result = array();

			while($row = $vbulletin->db->fetch_array($recordset))
			{

				$result[$row['nodeid']] = $row;
			}

			return $result;
		}
		return false;
	}

	/****************************
	 * This function creates the state select for a
	 * specific node. If the current state is either
	 * published/available unpublished, then the select
	 * options are just those. If it's currently published
	 * in the future, we leave that option plus the two others.
	 *
	 * @param integer nodeid
	 * @param integer published- this is node.setpublish
	 * @param integer date - this is node.publishdate
	 *
	 *@return string
	 ******/
	public static function getPublishedSelect($published, $pubdate)
	{
		global $vbphrase;
		global $vbulletin;

		$selected_unpublished = intval($published) < 1 ? ' selected="selected" ' : '';

		$selected_published = ((intval($published) > 0) AND ($pubdate <= TIMENOW))  ? ' selected="selected" ' : '';

		$selected_future =  ((intval($published) > 0) AND ($pubdate >= TIMENOW))  ?
			'<option value="3" selected="selected" >' . $vbphrase['publish'] . '&nbsp;'
			. vbdate($vbulletin->options['dateformat'], $pubdate)
			. "</option>\n" : '';

		$result = "	<option value=\"1\"$selected_unpublished>" . $vbphrase['unpublished'] . "</option>
			<option value=\"2\"$selected_published>" . $vbphrase['published'] . "</option>$selected_future
			</select>"	;
		return $result;
	}

	/****************************
	 * This function creates the state select for a
	 * specific node. If the current state is either
	 * published/available unpublished, then the select
	 * options are just those. If it's currently published
	 * in the future, we leave that option plus the two others.
	 *
	 * @param integer nodeid
	 * @param integer published- this is node.setpublish
	 * @param integer date - this is node.publishdate
	 *
	 *@return string
	 ******/
	private static function getEnabledSelect($categoryid, $enabled)
	{
		global $vbphrase;

		$result = "<select name=\"state_$categoryid\" id=\"state_$categoryid\"
			onchange=\"setFormValue('do','saveonecategorystate');
			document.getElementById('categoryid').value=$categoryid;
			document.getElementById('cms_data').submit();\">
			<option value=\"1\" " .
			(intval($enabled) < 1 ? ' selected="selected" ' : '') .
			">" . $vbphrase['disabled'] . "</option>
				<option value=\"2\"" .
			((intval($enabled) > 0) ? ' selected="selected" ' : '') .
			">" . $vbphrase['enabled'] . "</option>
			</select>"	;
		return $result;
	}

	/************************
	 * This function saves the preferred number of pages for the
	 * current user.
	 * @param object $current_user
	 *
	 * @return per_page
	 *************/
	public static function savePerPage($current_user)
	{
		global $vbulletin	;

		if (!$stored_prefs = $current_user->getSearchPrefs())
		{
			$stored_prefs = array();
		}
		$vbulletin->input->clean_array_gpc('r', array(
			'perpage' => TYPE_UINT));

		if ($vbulletin->GPC_exists['perpage'] AND intval($vbulletin->GPC['perpage'])
			and intval($vbulletin->GPC['perpage']) < 400)
		{
			$stored_prefs['cmsadmin_showperpage'] = intval($vbulletin->GPC['perpage']);
			$current_user->saveSearchPrefs($stored_prefs);

			$result = print_form_header('cms_content_admin', '', false, true, 'cms_data', '100%', '_self',
				true, 'post', 0, false );


			return intval($vbulletin->GPC['perpage']);
		}

		return 20;
	}

	/************************
	 * This function gets the number to display per page.
	 *
	 * @param object $current_user
	 *
	 * @return integer
	 ********/
	public static function getPerPage($current_user)
	{
		if ($stored_prefs = $current_user->getSearchPrefs()
			and isset($stored_prefs['cmsadmin_showperpage'])
			and intval($stored_prefs['cmsadmin_showperpage']))
		{
			return intval($stored_prefs['cmsadmin_showperpage']);
		}
		return 20;
	}

	/**************************
	 * This function creates the select list of authors
	 * @param none
	 * @result string
	 *********/
	public static function getAuthorSelect()
	{
		global $vbulletin;
		global $vbphrase;

		$authorfilter = intval($vbulletin->GPC['author_filter']);

		if ($rst = $vbulletin->db->query_read("SELECT DISTINCT user.userid, user.username
			FROM " . TABLE_PREFIX . "cms_node AS node INNER JOIN " . TABLE_PREFIX .
			"user AS user ON user.userid = node.userid WHERE node.new != 1 ORDER BY user.username;"))
		{
			$result = "<option value=\"\">" . $vbphrase['any_author'] . '</option>';

			while($row = $vbulletin->db->fetch_row($rst))
			{
				$result .= "<option value=\"" . $row[0] . "\"" . (($authorfilter AND $authorfilter == $row[0]) ? 'selected="selected"' : '') . ">" . $row[1] ."</option>\n";
			}
			return $result;
		}
		return false;
	}

	/**************************
	 * This function creates the select list of states
	 * for searching
	 *
	 * @param none
	 * @result string
	 *********/
	public static function getMasterStateSelect()
	{
		global $vbulletin;
		global $vbphrase;
		$result = "<option value=\"\">" . $vbphrase['all_content'] . "</option>
			<option value=\"1\" " . (($vbulletin->GPC_exists['state_filter']
				and $vbulletin->GPC['state_filter'] == 1) ? ' selected="selected"' : ''
				) . ">" . $vbphrase['unpublished'] . "</option>
			<option value=\"2\"" . (($vbulletin->GPC_exists['state_filter']
				and $vbulletin->GPC['state_filter'] == 2) ? ' selected="selected"' : ''
				) . ">" . $vbphrase['published'] . "</option>
			<option value=\"3\"" . (($vbulletin->GPC_exists['state_filter']
				and $vbulletin->GPC['state_filter'] == 3) ? ' selected="selected"' : ''
				) . ">" . $vbphrase['published_future'] . "</option>"	;
		return $result;
	}

	/*********************************
	 * This function creates the headers for the standard section list form
	 *
	 * @param none
	 *
	 * @return string : the html
	 **********/
	private static function getNodeHeaders()
	{
		global $vbphrase;
		// we need a select list of content types;

		$return = "
		<div class=\"tcat\" align=\"" . vB_Template_Runtime::fetchStyleVar('right') . "\" style=\"margin:auto;text-align:" . vB_Template_Runtime::fetchStyleVar('right') . ";padding:5px;\" id=\"node_mgr_header\" >
			<input id=\"button_publish\" type=\"button\" value=\"" . $vbphrase[publish] . "\"
				onclick=\"javascript:setFormValue('do', 'publish_nodes'); document.getElementById('cms_data').submit();\"/>
			<input id=\"button_unpublish\" name=\"do_unpublish\" type=\"button\" value=\"" . $vbphrase["unpublish"] . "\"
				onclick=\"setFormValue('do','unpublish_nodes'); document.getElementById('cms_data').submit();\"/>
			<input id=\"button_move\" name=\"do_move\" type=\"button\" value=\"" . $vbphrase['move'] . "\"
				onclick=\"javascript:showNodeWindow('move_node');\"/>
			<input id=\"button_delete\" name=\"do_delete\" type=\"submit\" value=\"" . $vbphrase['delete'] . "\"
				onclick=\"javascript:if (confirm('" .  $vbphrase['confirm_deletion']. "')){setFormValue('do', 'delete_nodes')} else {return false;}\"/>
			<input id=\"button_save\" type=\"button\" value=\"" . $vbphrase['save_changes'] ."\"
				onclick=\"setFormValue('do','save_nodes'); document.getElementById('cms_data').submit();\" />
			<br/>\n
		</div>
		";

		$return .=  self::getNodePanel('sel_node_0') . "\n";//-ch removing </div>

		return $return;
	}

	/*********************************
	 * This function creates the headers for the standard section list form
	 *
	 * @param none
	 *
	 * @return string : the html
	 **********/
	private static function getSectionHeaders($sectionid = 1)
	{
		global $vbphrase;
		// we need a select list of content types;

		if (! $sectionid)
		{
			$sectionid = 1;
		}

		$return = "
			<div class=\"tcat\" align=\"" . vB_Template_Runtime::fetchStyleVar('right') . "\" style=\"margin:auto;padding:5px;\" id=\"node_mgr_header\" >";

		$return .=
			"<input id=\"button_new_section\" name=\"do_new\" type=\"button\" value=\"" . $vbphrase['new_section']
				. "\" onclick=\"javascript:showSectionEdit('new_section', $sectionid, $sectionid, '" .
				vB_Template_Runtime::escapeJS($vbphrase['new_section']) . "');\"/>" ;

		$return .= "<br/>\n</div>
			" ;
		$return .=  self::getNodePanel('sel_node_0') . "</div>\n";
		return $return;
	}

	/*********************************
	 * This function creates the headers for the standard category list form
	 *
	 * @param none
	 *
	 * @return string : the html
	 **********/
	private static function getCategoryHeaders($nodeid)
	{
		global $vbphrase;

		//We default nodeid  to 1;
		if (! $nodeid)
		{
			$nodeid = 1;
		}
		// we need a select list of content types;
		$return = "<tr class=\"tcat\"><td colspan=\"4\">
			<div align=\"center\" style=\"width:100%;margin:auto;\" id=\"cat_mgr_header\" >
			<div style=\"width:48%;text-align:" . vB_Template_Runtime::fetchStyleVar('right') . ";float:" . vB_Template_Runtime::fetchStyleVar('right') . ";top:0px\">";

		/* -ch: moving this bit to the category table header
		$return .= "
				<a href=\"javascript:showCatEdit('new', -1, -1, '');\">&nbsp;
				<img src=\"" . self::getImagePath('imgdir_cms') . "/add_small.png\" style=\"border:none\">&nbsp;" .
				$vbphrase['new_category'] . "</a>\n";
		*/

				//"<a href=\"javascript:showCatEdit('new', -1, -1, '');\" style=\"text-decoration:none;\">".
				//"<img src=\"" . self::getImagePath('imgdir_cms') . "/add_small.png\" style=\"border:none;\" /></a>".
		$return .= "<input type=\"button\"onclick=\"javascript:showCatEdit('new', $nodeid, -1, '', '". vB_Template_Runtime::escapeJS($vbphrase['pick_a_section_below']) .
				"');\" value=\"" .	$vbphrase['new_category'] . "\" />\n";
		$return .= "	</div><br/><br/>\n</div>
			" ;
		$return .=  self::getNodePanel('sel_node_0') . "</div>\n</td></tr>\n\n";
		return $return;
	}

	/**************************
	 * This function creates the search filters and populates them with current values.
	 * @param none
	 * @result string
	 *********/
	private static function getSearchFilters($displayfor)
	{
		global $vbulletin;
		global $vbphrase;

		$result = "
			<strong style=\"padding-" . vB_Template_Runtime::fetchStyleVar('left') . ":5px\">" . $vbphrase['filter'] . "</strong>&nbsp;&nbsp;
			<input type=\"text\" name=\"title_filter\" id=\"title_filter\"
			value=\"" . $vbulletin->GPC['title_filter'] . "\" />
			<select id=\"contenttypeid\" name=\"contenttypeid\">\n"
				. self::getContentTypeSelect() . "</select>
				<select name=\"state_filter\"  id=\"state_filter\">
				" . self::getMasterStateSelect() . "
				&nbsp;&nbsp;\n" . $vbphrase ['author'] . " <select name=\"author_filter\" id=\"author_filter\">
				" . self::getAuthorSelect() ."</select>
				<input type=\"button\" value=\"" . $vbphrase['limit_results'] . "\"
				onclick=\"javascript:setFormValue('do','filter');document.getElementById('cms_data').submit();\"/>
				<input type=\"button\" value=\"" . $vbphrase['clear'] . "\"
				onclick=\"javascript:clearSearch();\"/>
				<input type=\"hidden\" name=\"filter_section\" id=\"filter_section\" value=\"0\" />
			" ;
		;
		return $result;
	}

	/*************************
	 * This function figures, for each element, what the array of parents is.
	 * For each element, it display this as an indented list of every place it
	 * is shown in the tree. It inserts this into the array.
	 *
	 * @param array $nodes (by reference) : the array of records from the database
	 *
	 * @return boolean
	 ****/
	private static function getParentage($nodes, $indent_perlevel = 2)
	{
		//Here's how we are going to run this. We compose a query that returns all the
		// parentage records. The sort order is crucial. Because we have it ordered this
		// way, the first record is the root node for the first item. The next record is
		// its child, and the next is its child. When the first and second fields of the
		// record are the same, we have reached
		//Then we walk the tree- we find our record, then by walking to the left we find
		//our path to the top.
		global $vbulletin;
		$nodeids = array();

		foreach ($nodes as $node)
		{
			$nodeids[] = $node['nodeid'];
		}
		$sql = "SELECT node2.nodeid as childnode, node.nodeid, node.nodeleft, node.noderight, info.title, node.parentnode
			FROM " . TABLE_PREFIX . "cms_node AS node
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid
			CROSS JOIN " . TABLE_PREFIX . "cms_node AS node2
			WHERE (node2.nodeleft >= node.nodeleft AND node2.nodeleft <= node.noderight)
	    	AND node2.nodeid in
			("	. implode(",", $nodeids)
			. ") AND node2.new != 1
		    ORDER BY childnode, node.nodeleft;";

		if (! $rst = $vbulletin->db->query_read($sql))
		{
			return false;
		}

		//Now
		$this_nodeid = false;
		while($record = $vbulletin->db->fetch_array($rst))
		{
			if (intval($this_nodeid) != intval($record['childnode']))
			{
				if ($this_nodeid AND isset($nodes[$this_nodeid]))
					//we need to set a value
				{
					$nodes[$this_nodeid]['parentage'] = $this_html;
				}
				$indent = '';
				$this_nodeid = $record['childnode'];
				$this_html = "<a href=\"cms_content_admin.php?do=nodecontent&nodegroup="
					. $record['nodeid'] . "\" target=\"_self\">" .$record['title'] . "</a><br />\n";
			}
			else if (intval($record['childnode']) != intval($record['nodeid']))
			{
				$indent .= str_repeat('&nbsp;', $indent_perlevel);
				$this_html .= $indent . "<a href=\"cms_content_admin.php?do=nodecontent&nodegroup="
					. $record['nodeid'] . "\" target=\"_self\">" .$record['title'] . "</a><br />\n";
			}
		}

		if ($this_nodeid AND isset($nodes[$this_nodeid]))
		{
			$nodes[$this_nodeid]['parentage'] = $this_html;
		}

	}

	/***
	* This creates a new category based on parameters from $vbulletin->GPC
	****/
	private static function makeCategory()
	{
		global $vbulletin;
		global $vbphrase;

		//We may be creating a new subnode, or we may be creating a new node.
		//First we need to have a place to put this. Let's see whether we have a specific
		//place to put it.

		if ($vbulletin->GPC_exists['target_categoryid'] AND (intval($vbulletin->GPC['target_categoryid']) > 0))
		{
				//We are putting in a specific category.
			if (!$record = $vbulletin->db->query_first("SELECT parentnode, catleft, catright FROM "
			. TABLE_PREFIX . "cms_category WHERE categoryid = " . $vbulletin->GPC['target_categoryid'] ))
			{
				print_cp_message($vbphrase['invalid_data']);
				return false;
			}
			$parentnode = $record['parentnode'];
			//Now we're going to open a space. We'll set the values to what we're going to create;
			$catleft =  intval($record['catright']);
			$parentcat = $vbulletin->GPC['target_categoryid'];
		}
		else if ($vbulletin->GPC_exists['sectionid'] AND intval($vbulletin->GPC['sectionid']) > 0)
		{
 //We are creating a new node. We'll just set its value at one larger than the largest node
			{
				$record = $vbulletin->db->query_first("SELECT max(catright) AS catright FROM "
				. TABLE_PREFIX . "cms_category");
				$catleft = intval($record['catright']) + 1;
				$parentcat = 'NULL';
			}
			$parentnode = $vbulletin->GPC['sectionid'];
		}
		else
		{
			print_cp_message($vbphrase['invalid_data_submitted']);
			return false;
		}
		$category = $vbulletin->db->escape_string(
				($vbulletin->GPC_exists['title'] AND strlen($vbulletin->GPC['title']))?
			$vbulletin->GPC['title'] : $vbphrase['new_category'] );
		$catright = intval($catleft) + 1;
		//Now at this point we are ready. We have to first open a space, then insert the record.
		$sql = "UPDATE " . TABLE_PREFIX . "cms_category SET catleft = catleft + 2 where catleft >= $catleft";
		$vbulletin->db->query_write($sql);
		$sql = "UPDATE " . TABLE_PREFIX . "cms_category SET catright = catright + 2 where catright >= $catleft";
		$vbulletin->db->query_write($sql);
		$sql = "INSERT INTO " . TABLE_PREFIX . "cms_category (parentnode, parentcat, catleft, catright, category)
			values($parentnode, $parentcat, $catleft, $catright, '$category')" ;
		$vbulletin->db->query_write($sql);

	}

	/****************************************
	* This function handles all the category data updates.
	*
	*************************************/
	public static function updateCategories()
	{
		//There are a number of options- publish or unpublish (which means enable),
		//move, copy, and delete. We'll do a switch
	 	global $vbulletin;

		$vbulletin->input->clean_array_gpc('p', array(
			'categoryid' => TYPE_INT,
			'sectionid' => TYPE_INT,
			'title' => TYPE_NOHTML,
			'id' => TYPE_INT,
			'ids' => TYPE_ARRAY,
			));

		$ids = array();
		switch($vbulletin->GPC['do'])
		{
			case 'delete_category' :
				if ($vbulletin->GPC_exists['categoryid'] AND intval($vbulletin->GPC['categoryid']))
				{
					$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX .
					"cms_nodecategory where categoryid  = " . $vbulletin->GPC['categoryid']);
					$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX .
					"cms_category where categoryid  = " . $vbulletin->GPC['categoryid']);

					vB_Cache::instance()->event('categories_updated');
				}

				break;

			case 'save_category':

				if (! $vbulletin->GPC_exists['categoryid'])
				{
					return false;
				}

				if ($vbulletin->GPC_exists['title'] AND intval($vbulletin->GPC['categoryid']))
				{
					$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX .
					"cms_category SET category = '" . $vbulletin->db->escape_string($vbulletin->GPC['title']) .
					 "' WHERE categoryid = " . $vbulletin->GPC['categoryid'] );
					vB_Cache::instance()->event('categories_updated');
				}

				if ($vbulletin->GPC['target_categoryid']
					OR $vbulletin->GPC['sectionid'])
				{
					//We don't have to do the check as in moving a section, because
					// we can't have children
					self::moveCategory();
				}
				break;

				;
			break;

			case 'move_category' :

				if ($vbulletin->GPC['target_categoryid']
					OR $vbulletin->GPC['sectionid'])
				{
					//We don't have to do the check as in moving a section, because
					// we can't have children
					self::moveCategory();
				}
				break;

			case 'delete_category' :

				if ($vbulletin->GPC_exists['ids'] AND count($ids))
				{
					$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX .
					"cms_nodecategory WHERE categoryid in (" . implode(', ', $ids) .
					")");
					$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX .
					"cms_category WHERE categoryid in (" . implode(', ', $ids) .
					")");
					vB_Cache::instance()->event('categories_updated');
				}
				break;

			case 'new_category' :
				//We have two possibilities. If we have a parentcat that means the user
				// wants to create a subcategory. If we have only a sectionid, we're a
				// new parent category under a section.

				if ($vbulletin->GPC_exists['target_categoryid'] OR $vbulletin->GPC_exists['sectionid'])
				{
						self::makeCategory();
						vB_Cache::instance()->event('categories_updated');
				}
				break;

			case 'save_categories' :

				// The only thing we have to change here is the sequence
				if ($vbulletin->GPC['ids'])
				{
					foreach ($vbulletin->GPC['ids'] as $id)
					{
						$values = array();

						if ($vbulletin->GPC_exists['sequence_' . $id])
						{
							$values[$id] = array('displayorder' => $vbulletin->GPC['sequence_' . $id]);
						}

						if ($vbulletin->GPC_exists['onhomepage_' . $id])
						{
							$values[$id] = array('onhomepage' => $vbulletin->GPC['onhomepage_' . $id]);
						}

						if (count($values))
						{
							self::saveRecords('cms_category', 'categoryid', $values);
						}

					}
					vB_Cache::instance()->event('categories_updated');
				}
				break;

			case 'saveonecategorystate' :
				$id = $vbulletin->GPC_exists['categoryid']?
					$vbulletin->GPC['categoryid'] : $vbulletin->GPC['id'];
				$vbulletin->input->clean_array_gpc('p', array(
					'id_' . $id  => TYPE_INT,
					'state_' . $id => TYPE_INT,
					));
				if ($id)
				{
					$sql = "UPDATE " . TABLE_PREFIX .
					"cms_category  ";

					switch($vbulletin->GPC['state_' . $id])
					{
						case 1 : $sql .= "set enabled = 0
							WHERE categoryid = $id";
							break;
						case 2 : $sql .= "set enabled = 1
							WHERE categoryid = $id";
							break;

					} // switch

					$vbulletin->db->query_write($sql);
					vB_Cache::instance()->event('categories_updated');
				}
				break;



			} // switch

	}

	/*********
	* This function updates the display order including opening and closing holes so
	* records maintain sequential order
	*****/
	public static function setDisplayOrder($sectionid, $nodeid, $displayorder)
	{
		global $vbulletin;

		/****
		* Here is the logic.
		* We didn't get here unless we had a sectionid and a nodeid
		* If we don't have a display order than the user intentionally removed the order.
		*   We should check, and if we have a display order in the database we should remove that. We're done
		*
		* If we're here we have an order. Now, check the existing record. If it has a number lower than
		* the new number, set every record with display order greater than that to one lower
		*
		* Then, if there was a record
		*  decrease by one every record with the display order greater than the original
		*
		* Then increase by one every record with display order greater than the new number
		*
		* Then set the current record to the new number.
		*
		* And now we're done
		* ***/
		//first get the existing data
		$record = $vbulletin->db->query_first($sql = "SELECT displayorder FROM " . TABLE_PREFIX .
			"cms_sectionorder WHERE nodeid = $nodeid AND sectionid = $sectionid");
		//Check to see if we should remove
		if (!intval($displayorder))
		{
			if ($record AND intval($record['displayorder'] ) > 0)
			{
				$vbulletin->db->query_write($sql = "DELETE FROM " . TABLE_PREFIX .
					"cms_sectionorder WHERE nodeid = $nodeid AND sectionid = $sectionid");
				//And move following nodes down one number
				$vbulletin->db->query_write($sql = "UPDATE " . TABLE_PREFIX .
					"cms_sectionorder SET displayorder = displayorder - 1 WHERE
					sectionid = $sectionid AND displayorder > $displayorder");
			}
			return;
		}

		if (intval($record['displayorder']) < 1)
		{
			$vbulletin->db->query_write($sql = "DELETE FROM " . TABLE_PREFIX .
			"cms_sectionorder  WHERE displayorder < 1");
		}
		if (intval($displayorder) == intval($record['displayorder']))
		{
			//nothing to do
			return true;
		}
		//Should we remove a hole?
		if ($record AND intval($record['displayorder']) > 0)
		{
			$vbulletin->db->query_write($sql = "DELETE FROM " . TABLE_PREFIX .
			"cms_sectionorder  WHERE  sectionid = $sectionid
			 AND displayorder = " . $record['displayorder']);
			$vbulletin->db->query_write($sql = "UPDATE " . TABLE_PREFIX .
			"cms_sectionorder SET displayorder = displayorder - 1 WHERE  sectionid = $sectionid
			 AND displayorder > " . $record['displayorder']);
		}
		//open a place for the new value
		$vbulletin->db->query_write($sql = "UPDATE " . TABLE_PREFIX .
			"cms_sectionorder SET displayorder = displayorder + 1
			WHERE sectionid = $sectionid AND displayorder >= $displayorder" );

		//set the new value
		$vbulletin->db->query_write($sql = "INSERT INTO " . TABLE_PREFIX .
			"cms_sectionorder (sectionid, nodeid, displayorder) values($sectionid
			, $nodeid, $displayorder)");


		$vbulletin->db->free_result($record);
	}

	/****************************************
		 * This function handles all the section data updates.
		 *
		 *************************************/
	public static function updateSections()
	{
		// There are several possibilities. First, if the user edits a title, published,
		//layout, or style, we save immediately.
		vB::$vbulletin->input->clean_array_gpc('p', array(
			'sectionid' => TYPE_INT,
			'nodeid' => TYPE_INT,
			'nodeid2' => TYPE_INT,
			'ids' => TYPE_ARRAY,
			'new_contenttype' => TYPE_INT,
			'title' => TYPE_NOHTML,
			'section_title' => TYPE_NOHTML,
			'sentfrom' => TYPE_NOHTML
			));

		$msg = '';
		//let's see if we need a list of ID's
		if (vB::$vbulletin->GPC['do'] == 'publish_section'
			OR vB::$vbulletin->GPC['do'] == 'unpublish_section'
			OR vB::$vbulletin->GPC['do'] == 'publish_nodes'
			OR vB::$vbulletin->GPC['do'] == 'unpublish_nodes'
			OR vB::$vbulletin->GPC['do'] == 'delete_section'
			OR vB::$vbulletin->GPC['do'] == 'move_section'
			OR vB::$vbulletin->GPC['do'] == 'delete_nodes'
			OR vB::$vbulletin->GPC['do'] == 'move_node'
			OR vB::$vbulletin->GPC['do'] == 'save_nodes')
		{
			$ids = array();
			foreach (vB::$vbulletin->GPC['ids'] as $id)
			{
				if ($_POST["cb_$id"] == 'on')
				{
					$ids[] = $id;
				}
			}
		}

		switch(vB::$vbulletin->GPC['do'])
		{
			case 'saveonetitle':

					if (vB::$vbulletin->GPC_exists['sectionid'])
				{
					$this_id = vB::$vbulletin->GPC['sectionid'];
					//We still need a title
					vB::$vbulletin->input->clean_array_gpc('p', array(
						'title_' . $this_id => TYPE_STR));

					if (vB::$vbulletin->GPC_exists['title_' . $this_id])
					{
						self::saveRecords('cms_node', 'nodeid',
							array($this_id => array('title' =>vB::$vbulletin->GPC['title_' . $this_id])));
						vB_Cache::instance()->purge('section_nav_' . $this_id);
						//we need to purge the data cache record for this id
						$content = vBCms_Item_Content::create('vBCms', 'Section', $this_id);
						vB_Cache::instance()->event($content->getCacheEvents());
						unset($content);
						vB_Cache::instance()->event('sections_updated');
						vB_Cache::instance()->cleanNow();
					}
				}
				;
				break;

			case 'set_order':
			//First, we validate that we have an id, and an order
				vB::$vbulletin->input->clean_array_gpc('p', array(
					'id' => TYPE_INT,
					'displayorder' => TYPE_INT,
					'sectionid' => TYPE_INT
					));
				if (vB::$vbulletin->GPC_exists['id'] AND vB::$vbulletin->GPC_exists['sectionid'])
				{
					self::setDisplayOrder(vB::$vbulletin->GPC['sectionid'], vB::$vbulletin->GPC['id'], vB::$vbulletin->GPC['displayorder']);
					vB_Cache::instance()->event('sections_updated');

					//we need to purge the data cache record for this id
					$content = vBCms_Item_Content::create('vBCms', 'Section', vB::$vbulletin->GPC['sectionid']);
					vB_Cache::instance()->event($content->getCacheEvents());
					vB_Cache::instance()->cleanNow();
					unset($content);
				}
				;
			break;

			case 'saveonesectionstate':
			case 'saveonenodestate':

				// We need a sectionid and state. 1 is published, 2 is unpublished,
				// and 3 means it's currently published in the future and we should leave it
				// that way.
				if (vB::$vbulletin->GPC_exists['nodeid'])
				{
					$this_id = vB::$vbulletin->GPC['nodeid'];
					//We still need a title
					vB::$vbulletin->input->clean_array_gpc('p', array(
						'state_' . $this_id => TYPE_INT));

					if (vB::$vbulletin->GPC['state_' . $this_id])
					{
						if (vB::$vbulletin->GPC['state_' . $this_id] == 2)
						{
							self::saveRecords('cms_node', 'nodeid', array($this_id => array('setpublish' =>1,
							'publishdate' => TIMENOW - 1)));
						}
						elseif (vB::$vbulletin->GPC['state_' . $this_id] == 1)
						{
							self::saveRecords('cms_node', 'nodeid', array($this_id => array('setpublish' =>0)));
						}
						vB_Cache::instance()->event('section_nav_' . $this_id);
						vB_Cache::instance()->event('articles_updated');
						vB_Cache::instance()->event('sections_updated');
						//we need to purge the data cache record for this id
						$content = vBCms_Item_Content::create('vBCms', 'Section', $this_id);
						vB_Cache::instance()->event($content->getCacheEvents());
						vB_Cache::instance()->cleanNow();
						unset($content);
					}
				}
				break;

			case 'delete_section':
				//first check to see if there is content under this section
				vB::$vbulletin->input->clean_array_gpc('p', array(
					'delete_sectionid' => TYPE_INT));
				if (vB::$vbulletin->GPC_exists['delete_sectionid'])

					//If this is id 1, don't allow deletion.
					if ((intval(vB::$vbulletin->GPC['delete_sectionid']) == 1) OR $record = vB::$vbulletin->db->query_first('SELECT COUNT(*) AS qty FROM ' .
					TABLE_PREFIX . "cms_node WHERE new != 1 AND parentnode = " . vB::$vbulletin->GPC['delete_sectionid'])
						and intval($record['qty']))
					{
						return false;
					}
				vB::$vbulletin->db->query_write($sql = "DELETE FROM ".
					TABLE_PREFIX . "cms_nodeconfig WHERE nodeid = " . vB::$vbulletin->GPC['delete_sectionid']);
				vB::$vbulletin->db->query_write($sql = "DELETE FROM ".
					TABLE_PREFIX . "cms_nodeinfo WHERE nodeid = " . vB::$vbulletin->GPC['delete_sectionid']);
				vB::$vbulletin->db->query_write($sql = "DELETE FROM ".
					TABLE_PREFIX . "cms_node WHERE nodeid = " . vB::$vbulletin->GPC['delete_sectionid']);
				vB::$vbulletin->db->query_write("DELETE FROM " .
					TABLE_PREFIX .	"cms_category WHERE parentnode = " . vB::$vbulletin->GPC['delete_sectionid']);
				vB_Cache::instance()->event('sections_updated');
				vB_Cache::instance()->cleanNow();
				break;

			case 'delete_nodes':
				foreach(vB::$vbulletin->GPC['ids'] as $this_id)
				{
					if (isset($_POST["cb_$this_id"]))
					{

						$node = new vBCms_Item_Content($this_id);
						$class  = vB_Types::instance()->getContentClassFromId($node->getContentTypeID());
						$classname = $class['package']. "_Item_Content_" . $class['class'];

						if (class_exists($classname))
						{
							$node = new $classname($this_id);
						}
						else
						{
							$node = new vBCms_Item_Content($this_id);
						}

						$nodedm = $node->getDM();
						$nodedm ->delete();

					}
				}
				vB_Cache::instance()->event('sections_updated');
				vB_Cache::instance()->event('articles_updated');
				vB_Cache::instance()->cleanNow();
				break;


			case 'saveonelayout':

				// We need a nodeid and a layoutid.
				if (vB::$vbulletin->GPC_exists['nodeid'])
				{
					$this_id = vB::$vbulletin->GPC['nodeid'];
					//We still need a title
					vB::$vbulletin->input->clean_array_gpc('p', array(
						'layout_' . $this_id => TYPE_INT));

					if (vB::$vbulletin->GPC['layout_' . $this_id])
					{
						self::saveRecords('cms_node', 'nodeid',
							array($this_id => array('layoutid' =>vB::$vbulletin->GPC['layout_' . $this_id]	)));
					}
					else
					{
						self::saveRecords('cms_node', 'nodeid',
							array($this_id => array('layoutid' => null)));

					}
					//we need to purge the data cache record for this id
					$content = vBCms_Item_Content::create('vBCms', 'Section', $this_id);
					vB_Cache::instance()->event($content->getCacheEvents());
					unset($content);
				}
				break;

			case 'saveonecl':
				// We need a nodeid and a clid.
				if (vB::$vbulletin->GPC_exists['nodeid'])
				{
					$this_id = vB::$vbulletin->GPC['nodeid'];
					//We still need a title
					vB::$vbulletin->input->clean_array_gpc('p', array(
						'cl_' . $this_id => TYPE_INT));

					if (vB::$vbulletin->GPC_exists['cl_' . $this_id] AND intval(vB::$vbulletin->GPC['cl_' . $this_id]))
					{
						self::saveConfig ($this_id, 'content_layout', vB::$vbulletin->GPC['cl_' . $this_id], false);
					}
					//we need to purge the data cache record for this id
					$content = vBCms_Item_Content::create('vBCms', 'Section', $this_id);
					vB_Cache::instance()->event($content->getCacheEvents());
					unset($content);
				}
				break;
			case 'sectionpriority':
				// We need a nodeid and a sect_pr_XX value
				if (vB::$vbulletin->GPC_exists['nodeid'])
				{
					$this_id = vB::$vbulletin->GPC['nodeid'];
					//We still need a title
					vB::$vbulletin->input->clean_array_gpc('p', array(
						'sect_pr_' . $this_id => TYPE_INT));

					if (vB::$vbulletin->GPC_exists['sect_pr_' . $this_id] )
					{
						if (intval(vB::$vbulletin->GPC['sect_pr_' . $this_id]))
						{
							self::saveConfig ($this_id, 'section_priority', vB::$vbulletin->GPC['sect_pr_' . $this_id], false);
						}
						else
						{
							//The we go back to default.
							self::saveConfig ($this_id, 'section_priority', 0, false);
						}
					}
					//we need to purge the data cache record for this id
					$content = vBCms_Item_Content::create('vBCms', 'Section', $this_id);
					vB_Cache::instance()->event($content->getCacheEvents());
					unset($content);
				}
				break;

			case 'sectionpp':
				// We need a nodeid and a sect_pp_XX value
				if (vB::$vbulletin->GPC_exists['nodeid'])
				{
					$this_id = vB::$vbulletin->GPC['nodeid'];
					//We still need a title
					vB::$vbulletin->input->clean_array_gpc('p', array(
						'section_pp_' . $this_id => TYPE_INT));

					if (vB::$vbulletin->GPC_exists['section_pp_' . $this_id] AND intval(vB::$vbulletin->GPC['section_pp_' . $this_id]))
					{
						self::saveConfig ($this_id, 'items_perhomepage', vB::$vbulletin->GPC['section_pp_' . $this_id], false);
					}
					//we need to purge the data cache record for this id
					$content = vBCms_Item_Content::create('vBCms', 'Section', $this_id);
					vB_Cache::instance()->event($content->getCacheEvents());
					unset($content);
				}
				break;

			case 'saveonestyle':

				// We need a sectionid and state. 1 is published, 2 is unpublished,
				// and 3 means it's currently published in the future and we should leave it
				// that way.
				if (vB::$vbulletin->GPC_exists['nodeid'])
				{
					$this_id = vB::$vbulletin->GPC['nodeid'];
					//We still need a title
					vB::$vbulletin->input->clean_array_gpc('p', array(
						'style_' . $this_id => TYPE_INT));

					if (vB::$vbulletin->GPC['style_' . $this_id])
					{
						self::saveRecords('cms_node', 'nodeid',
							array($this_id => array('styleid' =>vB::$vbulletin->GPC['style_' . $this_id])));
					}
					else
					{
						self::saveRecords('cms_node', 'nodeid',
							array($this_id => array('styleid' => null)));
					}
					vB_Cache::instance()->event('section_nav_' . $this_id);
					vB_Cache::instance()->event('sections_updated');				}
					//we need to purge the data cache record for this id
					$content = vBCms_Item_Content::create('vBCms', 'Section', $this_id);
					vB_Cache::instance()->event($content->getCacheEvents());
					vB_Cache::instance()->cleanNow();
					unset($content);
				break;

			case 'publish_section':
			case 'publish_nodes':
				self::savePreview();
				foreach(vB::$vbulletin->GPC['ids'] as $id)
				{
					vB::$vbulletin->input->clean_array_gpc('r', array('cb_' . $id => TYPE_STR ));

					if (vB::$vbulletin->GPC_exists['cb_' . $id])
					{
						$checked[] = $id;
					}

				}

				if (count($checked))
				{
					self::saveRecords('cms_node', 'nodeid', array('setpublish'=> 1),
						$checked);
					foreach($checked as $id)
					{
						vB_Cache::instance()->event('section_nav_' . $id);
					//we need to purge the data cache record for this id
					$content = vBCms_Item_Content::create('vBCms', 'Section', $id);
					vB_Cache::instance()->event($content->getCacheEvents());
					unset($content);
					}
					break;
					vB_Cache::instance()->event('sections_updated');
					vB_Cache::instance()->event('articles_updated');
					vB_Cache::instance()->cleanNow();
				}
				break;

			case 'unpublish_nodes':
			case 'unpublish_section':
				self::savePreview();
				foreach(vB::$vbulletin->GPC['ids'] as $id)
				{
					vB::$vbulletin->input->clean_array_gpc('r', array('cb_' . $id => TYPE_STR ));

					if (vB::$vbulletin->GPC_exists['cb_' . $id])
					{
						$checked[] = $id;
					}

				}

				if (count($checked))
				{
					self::saveRecords('cms_node', 'nodeid', array('setpublish'=> 0),
						$checked);
					foreach($checked as $id)
					{
						vB_Cache::instance()->event('section_nav_' . $id);
						//we need to purge the data cache record for this id
						$content = vBCms_Item_Content::create('vBCms', 'Section', $id);
						vB_Cache::instance()->event($content->getCacheEvents());
						unset($content);
					}
					vB_Cache::instance()->event('sections_updated');
					vB_Cache::instance()->event('sections_updated');
					vB_Cache::instance()->event('articles_updated');
					vB_Cache::instance()->cleanNow();
				}
				break;

			case 'move_node':
			case 'move_section':

				//sectionid is where we're moving the records.
				if (vB::$vbulletin->GPC_exists['sectionid'] and
					$nodelist = self::getNodeList($ids))
				{
					$msg = self::moveSection($nodelist, vB::$vbulletin->GPC['sectionid']);
				}
				foreach ($nodelist as $sectionid)
				{

					vB_Cache::instance()->event('section_nav_' . $sectionid);
				}
				vB_Cache::instance()->event('sections_updated');
				vB_Cache::instance()->event('articles_updated');
				vB_Cache::instance()->cleanNow();
				break;

			case 'save_section':
				//We should have a title, a sectionid, and a target_sectionid
				vB::$vbulletin->input->clean_array_gpc('p',
					array('title' => TYPE_STR ,
					'nodeid' => TYPE_INT,
					'target_sectionid' => TYPE_INT));

				if (vB::$vbulletin->GPC_exists['title'] AND vB::$vbulletin->GPC_exists['nodeid'])
				{
					$this_id = vB::$vbulletin->GPC['nodeid'];
						//First we save the title. Then try a move.
					//The move subroutine will check to see if it's necessary.
					self::saveRecords('cms_nodeinfo', 'nodeid',
						array($this_id => array('title' =>vB::$vbulletin->GPC['title'], 'html_title' =>vB::$vbulletin->GPC['title'])));
				}

				if (vB::$vbulletin->GPC_exists['target_sectionid']
					and vB::$vbulletin->GPC['target_sectionid'])
				{
					$msg .= self::moveSection(array($this_id), vB::$vbulletin->GPC['target_sectionid']);
				}
				vB_Cache::instance()->event('section_nav_' . $this_id);
				vB_Cache::instance()->event('sections_updated');
				vB_Cache::instance()->event('articles_updated');
				//we need to purge the data cache record for this id
				$content = vBCms_Item_Content::create('vBCms', 'Section', $this_id);
				vB_Cache::instance()->event($content->getCacheEvents());
				vB_Cache::instance()->cleanNow();
				unset($content);

				break;

			case 'swap_sections':
				if (vB::$vbulletin->GPC_exists['nodeid'] AND vB::$vbulletin->GPC_exists['nodeid2'])
				{
					$node1 = vB::$vbulletin->GPC['nodeid'];
					$node2 = vB::$vbulletin->GPC['nodeid2'];

					self::swapNodes($node1, $node2);

					vB_Cache::instance()->event('section_nav_' . $node1);
					vB_Cache::instance()->event('section_nav_' . $node2);
					vB_Cache::instance()->event('sections_updated');
					vB_Cache::instance()->event('articles_updated');
					//we need to purge the data cache record for these ids
					$content = vBCms_Item_Content::create('vBCms', 'Section', $node1);
					vB_Cache::instance()->event($content->getCacheEvents());
					$content = vBCms_Item_Content::create('vBCms', 'Section', $node2);
					vB_Cache::instance()->event($content->getCacheEvents());
					vB_Cache::instance()->cleanNow();
					unset($content);
				}
				break;

			case 'new_section':
				global $vbulletin;
				vB::$vbulletin->input->clean_array_gpc('p',
					array('title' => TYPE_STR ,
					'sectionid' => TYPE_INT,
					'target_sectionid' => TYPE_INT,
					'section_title' => TYPE_STR
					));
				if (!vB::$vbulletin->GPC_exists['parentnode'] AND vB::$vbulletin->GPC_exists['target_sectionid'])
				{
					$vbulletin->GPC_exists['parentnode'] = 1;
					$vbulletin->GPC['parentnode'] = $vbulletin->GPC['target_sectionid'];
				}
				elseif (!vB::$vbulletin->GPC_exists['parentnode'] AND vB::$vbulletin->GPC_exists['sectionid'])
				{
					$vbulletin->GPC_exists['parentnode'] = 1;
					vB::$vbulletin->GPC['parentnode'] = vB::$vbulletin->GPC['sectionid'];
				}

				if (vB::$vbulletin->GPC_exists['title'] AND vB::$vbulletin->GPC_exists['parentnode']
					and vB::$vbulletin->GPC['parentnode'])
				{
					{

						if ($content = vBCms_Content::create('vBCms', 'Section'))
						{
							$nodedm = new vBCms_DM_Section();
							$nodedm->set('parentnode', $vbulletin->GPC['parentnode']);
							if (! $nodeid = $content->createDefaultContent($nodedm))
							{
								throw (new vB_Exception_DM('Could not create new node for content: ' . print_r($nodedm->getErrors())));
							}
						}
						vB_Cache::instance()->event('sections_updated');
						vB_Cache::instance()->cleanNow();
						//now let's display the new section.
						vB::$vbulletin->GPC_exists['sectionid'] = 1;
						vB::$vbulletin->GPC['sectionid'] = $nodeid;
					}
				}
				break;

			case 'new':

				//
				if (vB::$vbulletin->GPC_exists['sectionid'] AND vB::$vbulletin->GPC_exists['new_contenttype'])
				{
					$contenttypeid = vB::$vbulletin->GPC['new_contenttype'];
					try
					{
						// create the nodedm
						$class  =vB_Types::instance()->getContentClassFromId($contenttypeid);
						$classname = "vBCms_DM_" . $class['class'];

						if (class_exists($classname))
						{
							$nodedm = new $classname;
						}
						else
						{
							$nodedm = new vBCms_DM_Node();
						}

						// create content handler
						$content = vBCms_Content::create(vB_Types::instance()->getContentTypePackage($contenttypeid), vBCms_Types::instance()->getContentTypeClass($contenttypeid));


						// insert default content for the contenttype and get the new contentid
						$content->setParentNode(vB::$vbulletin->GPC['sectionid']);

						$contentid = $content->createDefaultContent($nodedm);
					}
					catch (vB_Exception $e)
					{
						throw (new vB_Exception_DM('Could not create default content.  Exception thrown with message: \'' . $e->getMessage() . '\''));
					}

					// Create new content node
					$nodedm->set('contenttypeid', $contenttypeid);
					$nodedm->set('contentid', $contentid);

					$nodedm->set('parentnode', vB::$vbulletin->GPC['sectionid']);
					$nodedm->set('title', (vB::$vbulletin->GPC_exists['section_title']?
						vB::$vbulletin->GPC['section_title'] : vB::$vbulletin->GPC['section_title']) );


					//allow child nodes to set the author. This is necessary when we
					//promote a post
					if (! $nodedm->getSet('userid'))
					{
						$nodedm->set('userid', vB::$vbulletin->userinfo['userid']);
					}

					if (!($nodeid = $nodedm->save()))
					{
						throw (new vB_Exception_DM('Could not create new node for content: ' . print_r($nodedm->getErrors())));
					}
					vB_Cache::instance()->event('section_nav_' . vB::$vbulletin->GPC['sectionid']);
					vB_Cache::instance()->event('sections_updated');
					vB_Cache::instance()->event('articles_updated');
					vB_Cache::instance()->cleanNow();
				}
				break;

			case 'save_nodes':
				//The only thing we're updating is the publicpreview flag

				self::savePreview();
				vB_Cache::instance()->event('articles_updated');
			default:
				;
		} // switch
		return $msg;
	}

	/*****
	* This saves the preview field. We call this from two places,
	****/
	private static function savePreview()
		//The only thing we're updating is the publicpreview flag
		//We can keep it down to two updates if we generate a list of
		// not on homepage and .
	{
		global $vbulletin;
		$unchecked = array();
		$checked = array();

		foreach($vbulletin->GPC['ids'] as $id)
		{
			$vbulletin->input->clean_array_gpc('r', array('cb_pp_' . $id => TYPE_STR ));

			if ($vbulletin->GPC_exists['cb_pp_' . $id])
			{
				$checked[] = $id;
			}
			else
			{
				$unchecked[] = $id;
			}
		}

		if (count($checked))
		{
			self::saveRecords('cms_node', 'nodeid', array('publicpreview'=> 1),
				$checked);
		}

		if (count($unchecked))
		{
			self::saveRecords('cms_node', 'nodeid', array('publicpreview'=> 0),
				$unchecked);
		}

	}


	/***********
	* When moving or deleting, it is useful to get the nodes in an ordered list
	* With the nodeleft as key. We remove anything that is a child of another
	*  record in the list.
	*
	* @param array of integer ids  the list of ids
	*
	* @return array of integer
	***********/
	private static function getNodeList($ids)
	{
		global $vbulletin;

		if (count($ids))
		{
			$result = array();

			if ($rst = $vbulletin->db->query_read($sql = "SELECT nodeleft,
			noderight, nodeid
			FROM " . TABLE_PREFIX . "cms_node WHERE nodeid in("
			. implode(', ', $ids) . ") order by nodeleft;"))
			{
				while($row = $vbulletin->db->fetch_array($rst))
				{
					$result[intval($row['nodeid'])] = array(
					'nodeleft' => intval($row['nodeleft']),
					'noderight' => intval($row['noderight']),
					'nodeid' => intval($row['nodeid']));
				}
			}
			//Now we'll scan the array from left to right looking for children.
			$max = count($result);
			for ($i = 0 ; $i < $max -1 ; $i++)
			{
				for ($j = $i + 1; $j < $max; $j++)
				{
					if (! isset($result[$j]))
					{
						continue;
					}

					if (($result[$j]['nodeleft'] > $result[$i]['nodeleft']) and
						($result[$j]['noderight'] < $result[$i]['noderight']))
					{
						// this is a child. unset the value
						unset ($result[$j]);
					}
					else if ($result[$j]['nodeleft'] > $result[$i]['nodeleft'])
					{
						//at this point nothing else can be a child.
						break;
					}
				}
			}
			return array_keys($result);
		}
		return false;
	}
	/************
	* Deleting a block of content from a preorder tree traversal table
	* must be done carefully. That's the function of this routine.
	* @param array of integers $ids : the ids to be deleted
	************/
	private function deleteSections($ids)
	{
		//We do each element one at a time.
		//	First we get its left and right and parentnode.
		// For its children, we set their parent to the deleted's parentid
		// Then we can delete it, and its nodeconfig and nodeinfo entries.
		// Then we set all nodeleft to nodeleft -1 where nodeleft is greater than the
		// deleted's nodeleft
		// Then we set all nodeleft to nodeleft -1 where nodeleft is greater than the
		// deleted's noderight
		// Then we set all noderight to noderight -1 where noderight is greater than the
		// deleted's nodeleft
		// Then we set all noderight to noderight -1 where noderight is greater than the
		// deleted's noderight
		global $vbulletin;
		foreach($ids as $sectionid)
		{
			if ($record = $vbulletin->db->query_first("SELECT nodeleft, noderight, parentnode
			FROM " . TABLE_PREFIX . "cms_node WHERE nodeid = $sectionid"))
			{
				$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX .
					"cms_node SET parentnode = " . $record['parentnode'] .
					" WHERE (nodeleft BETWEEN " . $record['nodeleft'] . " AND " .
					 $record['noderight']);

				$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX .
					"cms_category WHERE parentnode = $sectionid");
				$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX .
					"cms_nodeinfo WHERE nodeid = $sectionid");
				$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX .
					"cms_nodeconfig WHERE nodeid = $sectionid");
				$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX .
					"cms_node WHERE nodeid = $sectionid");
				$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX .
					"cms_node WHERE nodeid = $sectionid");
				$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX .
					"cms_node SET nodeleft = nodeleft - 1 WHERE nodeleft >" .
					$record['nodeleft']);
				$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX .
					"cms_node SET nodeleft = nodeleft - 1 WHERE nodeleft >" .
					$record['noderight']);
				$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX .
					"cms_node SET noderight = noderight - 1 WHERE noderight >" .
					$record['nodeleft']);
				$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX .
					"cms_node SET noderight = noderight - 1 WHERE noderight >" .
					$record['noderight']);
			}
		}

	}

	/************
	 * Thi function swaps two nodes in the node tree while preserving the nested set model
	 *
	 * @param integer id1 - node to swap
	 * @param integer id2 - node to swap
	 * @return boolean - did we succeed?
	 ************/
	public static function swapNodes($id1, $id2)
	{
		$node1 = vb::$vbulletin->db->query_first("SELECT  nodeid, nodeleft , noderight
				FROM " . TABLE_PREFIX . "cms_node WHERE nodeid = $id1");
		$node2 = vb::$vbulletin->db->query_first("SELECT  nodeid, nodeleft, noderight
				FROM " . TABLE_PREFIX . "cms_node WHERE nodeid = $id2");


		// we need node1 to come before node2 in the nested set for this algorithm to work
		// so swap the nodes if they are not in order
		if ($node1['nodeleft'] > $node2['nodeleft'])
		{
			list($node2, $node1) = array($node1, $node2);
		}


		// grab the nodeleft and right values for our calculations
		$L1 = $node1['nodeleft'];
		$R1 = $node1['noderight'];

		$L2 = $node2['nodeleft'];
		$R2 = $node2['noderight'];


		// calculate the offsets for the children of node 1 and 2
		$node1_offset = $R2 - $R1;
		$node2_offset = $L1 - $L2;

		// offset for nodes in between 1 and 2, but not subnodes of either
		$between_offset = ($L1 + $R2) - ($R1 + $L2);


		vb::$vbulletin->db->query_write("UPDATE ". TABLE_PREFIX . "cms_node
				SET nodeleft = CASE WHEN nodeleft BETWEEN $L1 AND $R1 THEN nodeleft + $node1_offset
									WHEN nodeleft BETWEEN $L2 AND $R2 THEN nodeleft + $node2_offset
									ELSE nodeleft + $between_offset
								END,
				noderight = CASE WHEN noderight BETWEEN $L1 AND $R1 THEN noderight + $node1_offset
									WHEN noderight BETWEEN $L2 AND $R2 THEN noderight + $node2_offset
									ELSE noderight + $between_offset
								END
				WHERE nodeleft BETWEEN $L1 AND $R2");

	}

	/************
	 * Moving a block of content from a preorder tree traversal table
	 * must be done carefully. That's the function of this routine.
	 *
	 * @param array of nodes being moved- each node is an array, and the list is indexed and with no nesting
	 * @param integer to_id- This is where we are moving to
	 * @return boolean- did we succeed?
	 ************/
	public static function moveSection($ids, $to_id)
	{
		//Here's the approach
		//
		// for each of the nodes being moved:
		// - Get the left and right for new home.
		// - Get the space we need- right - left for the node being moved.
		// - For every node right value in the table equal to or above the parent noderight,
		// 	increase by the count.
		// - Ditto for every nodeleft value
		// - Calculate the offset- the new nodeleft will be the parent's current noderight
		// - For every record whose nodeleft is between the current record's nodeleft and noderight,
		//		increase both values by "offset"
		// - Set the parentid of the current record to the new parentid
		// - We are almost done. We now have a hole where the record used to be. Remove that
		//		by reducing the nodeleft and noderight values for every record with nodeleft greater than the original nodeleft
		// Oh- I know the 'ids' array has nodeleft and noderight values. Forget them, we can't trust them. As we do this
		//		we'll be moving everything around, so those values are worthless.

		global $vbulletin;
		global $vbphrase;

		if (!count($ids))
		{
			return false;
		}

		foreach ($ids as $id)
		{
			//structure is 'leftnode', 'rightnode', 'id'
			//We can't move from ourselves to ourselves
			if (intval($id) == intval($to_id))
			{
				continue;
			}
			if ($parent = $vbulletin->db->query_first($sql = "SELECT  nodeid, nodeleft , noderight, contenttypeid, permissionsfrom
				FROM " . TABLE_PREFIX . "cms_node WHERE nodeid = $to_id") and
				$move_record = $vbulletin->db->query_first("SELECT  nodeid, nodeleft, noderight, permissionsfrom
				FROM " . TABLE_PREFIX . "cms_node WHERE nodeid = " . $id) )
			{
				//we can only move to a section.
				if (intval($parent['contenttypeid']) != intval( vb_Types::instance()->getContentTypeID("vBCms_Section")))
				{
					continue;
				}
				//One check. If we are trying to move a parent to its own child, that won't work.
				//
				if ((intval($move_record['nodeleft']) <= intval($parent['nodeleft'])) AND (intval($move_record['noderight']) >= intval($parent['noderight'])))
				{
					return $vbphrase['cannot_move_to_subnode'];

				}


				//We have our records. First get the space we need to open
				$space = intval($move_record['noderight']) - intval($move_record['nodeleft']) + 1 ;
				//Now open the space
				$vbulletin->db->query_write($sql = "UPDATE ". TABLE_PREFIX . "cms_node SET noderight = noderight + $space
					WHERE noderight >= " . ($parent['noderight'])  );
				$vbulletin->db->query_write($sql = "UPDATE ". TABLE_PREFIX . "cms_node SET nodeleft = nodeleft + $space
					WHERE nodeleft > " . (intval($parent['noderight']) - 1));

				//We need to re-query because we may have just changed the records
				$move_record = $vbulletin->db->query_first("SELECT  nodeid, nodeleft, noderight, permissionsfrom
					FROM " . TABLE_PREFIX . "cms_node WHERE nodeid = " . $id);
				$offset = intval($parent[noderight]) - intval($move_record[nodeleft])  ;

				//Let's see if we need to change the permissionsfrom value.
				if (($parent['permissionsfrom'] != $move_record['permissionsfrom'])
					AND ($move_record['permissionsfrom'] != $move_record['nodeid']))
				{
					$sql = "UPDATE ". TABLE_PREFIX . "cms_node SET permissionsfrom = " .
						$parent['permissionsfrom'] . " WHERE permissionsfrom = " . $move_record['permissionsfrom'] .
						" AND nodeleft between " . $move_record['nodeleft'] . " AND " . $move_record['noderight'];
					$vbulletin->db->query_write($sql);
				}

				//Now move the records.
				$vbulletin->db->query_write($sql = "UPDATE ". TABLE_PREFIX . "cms_node SET nodeleft = nodeleft + ($offset),
					noderight = noderight + ($offset)
					WHERE nodeleft between " . $move_record['nodeleft'] . " AND " . $move_record['noderight']);
				//set the parent id
				$vbulletin->db->query_write($sql = "UPDATE ". TABLE_PREFIX . "cms_node SET parentnode = $to_id
					where nodeid =" . $id);
				//and close the hole
				$vbulletin->db->query_write($sql = "UPDATE ". TABLE_PREFIX . "cms_node
					SET nodeleft = nodeleft - $space
					WHERE nodeleft > " . $move_record['nodeleft']);
				$vbulletin->db->query_write($sql = "UPDATE ". TABLE_PREFIX . "cms_node
					SET noderight = noderight - $space
					WHERE noderight > " . $move_record['nodeleft']);


			}
		}

	}

	/************
	 * Moving a block of content from a preorder tree traversal table
	 * must be done carefully. That's the function of this routine.
 	 *
 	 * This is somewhat more complex than moving a Section because we have
 	 * three options.
 	 * We might be moving from one section to a different section, or
 	 * we might be moving from a subcategory to a section,
 	 * or we might be moving from one subcategory to another.
	 ************/
	private static function moveCategory()
	{
		//Here's the approach
		//
		// verify and set the new values;
		// call fixCategoryLR()

		global $vbulletin;
		//First let's make sure that we actually need to do a move. Pull the current values
		//from the database
		if (!$record = $vbulletin->db->query_first("SELECT parentnode, parentcat, catleft, catright FROM "
			. TABLE_PREFIX . "cms_category where categoryid = " . $vbulletin->GPC['categoryid']))
		{
			print_cp_message($vbphrase['invalid_data_submitted']);
			return false;
		}

		$fromleft = intval($record['catleft']);
		$fromright = intval($record['catright']);
		$space = $fromright - $fromleft + 1 ;

		//See what we need to change. Check categoryid first, because if we have one
		// we'll use that in preference to the sectionid.
		if (intval($vbulletin->GPC['target_categoryid'] > 0)
				and intval($vbulletin->GPC['target_categoryid']) != intval($record['parentcat']))
		{
			$newparentcat = intval($vbulletin->GPC['target_categoryid']);
			if (! $newparent = $vbulletin->db->query_first("SELECT parentnode, parentcat, catleft, catright FROM "
			. TABLE_PREFIX . "cms_category where categoryid = $newparentcat" ))
			{
				print_cp_message($vbphrase['invalid_data_submitted']);
				return false;
			}

			//Check to make sure we aren't trying to move a parent to it's own child. That would
			// cause an explosion.
			if (intval($newparent['catleft']) > intval($record['catleft'])
					and intval($newparent['catleft']) < intval($record['catright']) )
			{
				return false;
			}
			$newparentnode = $newparent['parentnode'];
		}
		else if (intval($vbulletin->GPC['sectionid'])
				and intval($vbulletin->GPC['sectionid']) != intval($record['parentnode']))
		{
			$newparentnode = intval($vbulletin->GPC['sectionid']);
			$newparentcat = 'NULL' ;

		}
		else if (-1 == $vbulletin->GPC['target_categoryid'])
		{
			if ($vbulletin->GPC_exists['sectionid'] AND intval($vbulletin->GPC['sectionid']) > 0)
			{
				$newparentnode = $vbulletin->GPC['sectionid'];

			}
			else
			{
				$newparentnode = $record['parentnode'];
			}
			$newparentcat = 'NULL' ;
		}

		if (!isset($newparentcat) AND ! isset($newparentnode))
		{
			//nothing to change
			return true;
		}

		if ($newparent == $record)
		{
			return true; // They are the same, nothing to do
		}
		//If we got here, we have valid data and we're ready to move.

		//If we have a new parentnode, let's set it.
		$sql = "UPDATE ". TABLE_PREFIX . "cms_category SET parentnode = $newparentnode, parentcat = $newparentcat
					WHERE categoryid = " . $vbulletin->GPC['categoryid'];
		$vbulletin->db->query_write($sql);
		self::fixCategoryLR();
	}

	/**********************
	 *This function handles all the data saves to the nodeconfig table. This is
	 * different because it doesn't have an identity column
	 **********************/
	public static function saveConfig($nodeid, $name, $value, $serialize = false)
	{
		global $vbulletin;
		$new_value = $vbulletin->db->escape_string( ($serialize? serialize($value) : $value)) ;
		$sql = "INSERT INTO " . TABLE_PREFIX . "cms_nodeconfig(nodeid, name, value, serialized) values($nodeid, '"
			. $vbulletin->db->escape_string($name) . "', '" . $new_value
			.	"', " . ($serialize? "1" : "0") . ") ON DUPLICATE KEY UPDATE value = '"
			. $new_value ."' ;";
		$vbulletin->db->query_write($sql);
	}

	/**********************
	*This function handles all the data interactions except those to cms_nodeconfig.
	**********************/
	public static function saveRecords($table, $idfield_name, $new_values, $ids = false)
	{
		global $vbulletin;
		//There are two ways we can run. If we have an array in $ids that means we have
		// a series of $field => value pairs in new_values and we apply those to every record.
		//Otherwise, we expect an array in $new_values, with each being
		// id => array($field => value)
		// then compose a "where" clause. If not, we do a foreach, composing and running the SQL
		// for each.
		if (is_array($ids))
		{
			$fieldvals = array();
			foreach($new_values as $field => $value)
			{
				$fieldvals[] = $field . ' = ' .
				(is_numeric($value) ? $value :
				"'" . $vbulletin->db->escape_string($value) . "'");
			}
			$vbulletin->db->query_write( $sql =  "UPDATE " . TABLE_PREFIX . "$table SET " . implode (', ', $fieldvals)
				. " WHERE $idfield_name in (" . implode(', ', $ids) . ")" ) ;
		}
		else
		{
			foreach ($new_values as $key =>  $fields)
			{
				$fieldvals = array();
				foreach($fields as $field => $value)
				{
					$fieldvals[] = $field . ' = ' .
					(is_numeric($value) ? $value :
					("'" . $vbulletin->db->escape_string($value) . "'") );
				}
				$vbulletin->db->query_write( $sql = "UPDATE " . TABLE_PREFIX . "$table set " . implode (', ', $fieldvals)
					. " WHERE $idfield_name = $key" ) ;

			}
		}
	}

	/**************************
	 * This function creates a style select list
	 *
	 * @param integer : the current style id
	 *
	 * @return string : the html select string
	 *********/
	public static function getStyleSelect($curr_styleid)
	{
		global $vbulletin;
		global $vbphrase;

		if (! count(self::$styles))
		{
			if (! $rst = $vbulletin->db->query_read("SELECT styleid, title
			FROM " . TABLE_PREFIX . "style ORDER BY title;") )
			{
				return '';
			}
			while($row = $vbulletin->db->fetch_row($rst))
			{
				self::$styles[$row[0]] = $row[1];
			}
		}

		if (! count(self::$styles))
		{
			return '';
		}
		$result = "
				<option value=\"-1\"" . (($curr_styleid === NULL) ? ' selected="selected"' : '') . ">" . $vbphrase['inherit'] . '</option>' . "
				<option value=\"0\"" .	(("0" === $curr_styleid) ? ' selected="selected"' : '') .
			">" . $vbphrase['board_defaults'] . "</option><option disabled=\"disabled\">--------</option>\n";
		foreach (self::$styles as $styleid => $title)
		{
			$result .= "<option value=\"$styleid\" "
			. ($styleid == $curr_styleid ? 'selected="selected"' : '')
			.">$title</option>\n";
		}
		return $result;
	}

	/**************************
	 * This function creates a "content Layout select list
	 *
	 * @param integer : the current style id
	 *
	 * @return string : the html select string
	 *********/
	public static function getContentLayoutSelect($curr_Clid)
	{
		global $vbphrase;

		if (! count(self::$cl_array))
		{
			for ($i = 1; $i <= self::$content_layout_count; $i++)
			{
				self::$cl_array[$i] = $vbphrase['cl_type_' . $i];
			}
		}
		$result = '';

		foreach (self::$cl_array as $cl_id => $title)
		{
			$result .= "<option value=\"$cl_id\" "
			. (intval($cl_id) == intval($curr_Clid) ? 'selected="selected"' : '')
			.">$title</option>\n";
		}
		return $result;
	}
	/******
	* This function generates the html for a section per-page edit box
	* @param integer $current : current setting
	******/
	public static function getSectionPPEdit($current, $nodeid, $max_val = 20)
	{
		global $vbphrase;
		require_once DIR . '/includes/functions.php';

		$result = "<input type=\"text\" size=\"2\" name=\"section_pp_" . $nodeid . "\"
			value=\"$current\" onblur=\"
			if (parseInt(this.value) > parseInt($max_val)){alert('"
			. construct_phrase($vbphrase['max_perpage_x'], $max_val)
			.	"')}
		else {setFormValue('nodeid', $nodeid);
			setFormValue('do', 'sectionpp');document.getElementById('cms_data').submit();}
		\" >\n";
		return $result;
	}

	/******
	 * This function generates the html for a section per-page edit box
	 * @param integer $current : current setting
	 ******/
	public static function getSectionPrioritySelect($current)
	{
		global $vbphrase;

		$result .= "<option value=\"1\"" .
				(intval($current) == 1 ? 'selected="selected"' : '')
				. ">" . $vbphrase['manual_then_by_date'] . "</option>\n";
		$result .= "<option value=\"5\"" .
				(intval($current) == 5 ? 'selected="selected"' : '')
				. ">" . $vbphrase['manual_only'] . "</option>\n";
		$result .= "<option value=\"2\"" .
				(intval($current) == 2 ? 'selected="selected"' : '')
				. ">" . $vbphrase['newest_first'] . "</option>\n";
		$result .= "<option value=\"3\"" .
				(intval($current) == 3 ? 'selected="selected"' : '')
				. ">" . $vbphrase['newest_per_section'] . "</option>\n";
		$result .= "<option value=\"4\"" .
				(intval($current) == 4 ? 'selected="selected"' : '')
				. ">" . $vbphrase['list_alphabetically'] . "</option>\n";
		return $result;
	}

	/**************************
	 * This function creates a layout select list
	 *
	 * @param integer : the current layout id
	 *
	 * @return string : the html select string
	 *********/
	public static function getLayoutSelect($curr_layoutid, $parentid = 1)
	{
		global $vbulletin;
		global $vbphrase;

		if (! count(self::$layouts) )
		{
			if (! $rst = $vbulletin->db->query_read("SELECT layoutid, title
			FROM " . TABLE_PREFIX . "cms_layout ORDER BY title;") )
			{
				return '';
			}
			while($row = $vbulletin->db->fetch_row($rst))
			{
				self::$layouts[$row[0]] = $row[1];
			}
		}

		if (! count(self::$layouts))
		{
			return '';
		}

		//If we are the root, there is no parent node from which we can inherit a
		// layout, so let's not show 'default'
		$result = intval($parentid) ? "
			<option value=\"\">" .$vbphrase['default'] ."</option>\n" : '';

		foreach (self::$layouts as $layoutid => $layout)
		{
			$result .= "<option value=\"" . $layoutid . "\" "
				. (intval($layoutid) == intval($curr_layoutid) ? 'selected="selected"'
					: '')
				. ">"
				. $layout . "</option>\n";
		}
		return $result;
	}

	/**************************
	 * This function creates the select list we can show
	 * @param integer $currval : the current value
	 *
	 *@return string  : the html for the select
	 **************************/
	public static function getPerpageSelect($currval = 20, $formname = 'cms_data')
	{
		$result = "<select name=\"perpage\" onchange=\"javascript:setFormValue('do', 'perpage');
			document.getElementById('$formname').submit();\">\n";

		foreach (array(5,10,15,20,25,50,75,100,200) as $per_page)
		{
			$result .= "<option value=\"$per_page\""
				. (intval($per_page) == intval($currval) ? ' selected="selected" ' : '')
				. ">$per_page</option>\n" ;
		}
		$result .= "</select>";
		return $result;
	}

	/****************************
	 * This makes the content type select list
	 *
	 * @param none
	 *
	 * @return string html
	 *******/
	public static function getContentTypeSelect()
	{
		global $vbulletin;
		global $vbphrase;

		$result = "	<option value=\"\">" .$vbphrase['any_type'] . "</option>";

		$rst = $vbulletin->db->query_read($sql = "SELECT contenttypeid, class FROM "
			. TABLE_PREFIX . "contenttype where canplace = '1' AND isaggregator = '0'
			 ORDER BY class;");
		while ($row = $vbulletin->db->fetch_row($rst))
		{
			$result .= "<option value=\"" . $row[0] ."\" "
			.	(($vbulletin->GPC_exists['contenttypeid']
				and $vbulletin->GPC['contenttypeid'] == $row[0]) ? ' selected="selected"' : '')
			. "> " . $vbphrase[strtolower($row[1])]
			. "</option>\n";
		}
		return $result;
	}

	/**********************
	 * This function lists the nodes.
	 * @param integer $page : The current page number
	 * @param integer $per_page : the number of nodes to display per page.
	 * @param string $perpage_select : the
	 * @return nothing
	 ****/
	public static function listNodes($page, $per_page = 10)
	{
		global $vbphrase;
		global $vbulletin;

		$nodes = self::getContent($page, $per_page);
		$filtersection = intval($vbulletin->GPC['filter_section']);

		//We may or may not have started at page 1

		$total_records = (($page - 1) * $per_page) + sizeof($nodes);

		$nodes = array_slice($nodes, 0, $per_page, true);
		if ($record_count = count($nodes))
		{
		$section = $vbulletin->db->query_first($sql = "SELECT info.title FROM " . TABLE_PREFIX . "cms_node AS node INNER JOIN
			" . TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid WHERE " .
			( $vbulletin->GPC_exists['sectionid'] ?
				" node.nodeid = " . $vbulletin->GPC['sectionid'] :
					($vbulletin->GPC_exists['filter_section'] ?
						" node.nodeid = " . $filtersection :
						" node.nodeid IS NULL" )));
			$i = (($page - 1) * $per_page) + 1;
		}
		$result = print_form_header('cms_content_admin', '', false, true, 'cms_data', '100%', '_self',
				true, 'post', 0, false);
		$result .= "<input type=\"hidden\" id=\"sectionid\" value=\"" .
			( $vbulletin->GPC_exists['sectionid'] ?
			" node.parentnode = " . $vbulletin->GPC['sectionid'] :
				($vbulletin->GPC_exists['filter_section'] ?
				" node.parentnode = " . $filtersection :
				" node.parentnode IS NULL" )) .
		"\" name=\"sectionid\"/>
		<input type=\"hidden\" name=\"sentfrom\" id=\"nodes\" value=\"nodes\"/>
		<input type=\"hidden\" name=\"id\" id=\"id\" value=\"0\"/>";

		$result .= "<div class=\"tcat nodeHeaders\" style=\"width: 100%;margin:auto;\">" . self::getNodeHeaders() . "</div><br />\n";
		$result .= "<tr class=\"tcat\">
				<td colspan=\"10\" align=\"" . vB_Template_Runtime::fetchStyleVar('left') . "\" class=\"feature_management_header\" style=\"padding:5px;\">

				"  . $vbphrase['you_are_managing'] . " " . $vbphrase['section'] . ": <span class=\"section_name\">" . $section['title'] .
				($vbulletin->GPC_exists['sectionid'] ? '' : '(' . $vbphrase['all_sections'] .')') . "</span>"
				.  "
				<input type=\"button\" id=\"button_filter_by_section\" onclick=\"showNodeWindow('filter_nodesection')\" value=\"" . $vbphrase['filter_by_section'] ."\">
				</td>
				</tr>";
		$result .= "<tr><td>
		<table class=\"tborder\" cellpadding=\"4\" border=\"0\" width=\"100%\" align=\"center\" style=\"font-size:11px\">\n";
		$bgclass = fetch_row_bgclass();
		if ($record_count = count($nodes))
		{
			$result .= "<tr align=\"center\" class=\"thead\">\n";
			$result .= "<td class=\"thead\">#</td>
			<td class=\"thead\">&nbsp;</td>
			<td align=\"" . vB_Template_Runtime::fetchStyleVar('left') . "" . vB_Template_Runtime::fetchStyleVar('left') . "\" width=\"33%\" class=\"thead\"><a href=\"cms_content_admin.php?do=sort&sentfrom=nodes&sortby=title\" target=\"_self\">" . $vbphrase['title'] . "</a></td>
			<td class=\"thead\"><a href=\"cms_content_admin.php?do=sort&sentfrom=nodes&sortby=class\" target=\"_self\">" . $vbphrase['content_type'] . "</a></td>
			<td class=\"thead\" width=\"60\"><a href=\"cms_content_admin.php?do=sort&sentfrom=nodes&sortby=publicpreview\" target=\"_self\">" . $vbphrase['public_preview'] . "</a></td>
			<td class=\"thead\"><a href=\"cms_content_admin.php?do=sort&sentfrom=nodes&sortby=setpublish\">" . $vbphrase['published'] . "</a></td>
			<td class=\"thead\">" . $vbphrase['order'] . "</td>
			<td class=\"thead\"><a href=\"cms_content_admin.php?do=sort&sentfrom=nodes&sortby=username\">" . $vbphrase['author'] . "</a></td>
			<td class=\"thead\" width=\"120\"><a href=\"cms_content_admin.php?do=sort&sentfrom=nodes&sortby=publishdate\">" . $vbphrase['date'] . "</a></td>
			<td class=\"thead\"><a href=\"cms_content_admin.php?do=sort&sentfrom=nodes&sortby=viewcount\">" . $vbphrase['viewcount'] . "</a></td>
			<td class=\"thead\"><a href=\"cms_content_admin.php?do=sort&sentfrom=nodes&sortby=replycount\">" . $vbphrase['comments'] . "</a></td>
			</tr>";
			self::getParentage($nodes, 4);

			vB_Router::setRelativePath('../');

			foreach($nodes as $node)
			{
				$content_url = vBCms_Route_Content::getUrl( array('node' =>$node['nodeid'], 'action' => edit), null, true);
				$bgclass = fetch_row_bgclass();
				$result .= "<tr id=\"row_nodeid_$node[nodeid]\" align=\"center\">\n <input type=\"hidden\" name=\"ids[]\" value=\"" .
					$node['nodeid'] . "\" /> <td class=\"$bgclass\">" . $i++ . "</td>\n";
				$result .= "  <td class=\"$bgclass\"><input type=\"checkbox\" name=\"cb_" . $node['nodeid'] . '"/>' . "</td>\n";
				$result .= "  <td align=\"" . vB_Template_Runtime::fetchStyleVar('left') . "\" class=\"$bgclass\"><a href=\"" . $content_url . "\" target=\"_blank\" >" . self::stripScript($node['title']) 	. "</a></td>\n";
				$result .= "  <td class=\"$bgclass\">" . $vbphrase[strtolower($node['class'])] 	. "</td>\n";
				$result .= "  <td class=\"$bgclass\"><input type=checkbox name=\"cb_pp_" . $node['nodeid'] . '" ' .
					(0 == intval($node['publicpreview']) ? '' : 'checked="checked"') . " /></td>\n";;
				$result .= "  <td class=\"$bgclass\"><select name=\"state_" . $node['nodeid']. "\" id=\"state_" . $node['nodeid']. "\"
					onchange=\"setFormValue('do', 'saveonenodestate');
					setFormValue('nodeid', '" . $node['nodeid']. "');document.getElementById('cms_data').submit();\">" . self::getPublishedSelect(
						intval($node['setpublish']), $node['publishdate']) . "</select></td>\n";
				$result .= "  <td class=\"$bgclass\"><select name=\"displayorder_" . $node['nodeid'] .
					"\" onchange=\"setOrder(" . $node['nodeid'] . ", " . $node['parentid'] . ", this.value);\">\n " .
					self::getOrderSelect($node['displayorder'], $node['parentnode'])	. "</select>\n</td>\n";

				$result .= "  <td align=\"" . vB_Template_Runtime::fetchStyleVar('left') . "\" class=\"$bgclass\">" . $node['username'] . "</td>\n";
				$result .= "  <td class=\"$bgclass\">" . vbdate($vbulletin->options['dateformat'],
					$node['publishdate']). "</td>\n";
				$result .= "  <td class=\"$bgclass\">" . $node['viewcount'] . "</td>\n";
				$result .= "  <td class=\"$bgclass\">" . intval($node['replycount']) . "</td>\n";
				$result .= "</tr>\n";
			}


			//moving the lines below outside of if/else -ch
			//print_hidden_fields();
			//$result .= "</table></td></tr>";
			//$result .= self::getNav($per_page, $total_records, $page, $displayfor);

		}
		else
		{
			vB_Router::setRelativePath('../');
			$content_url = vB_Route::create('vBCms_Route_Content');

			$result .= "\n<tr><td>\n\n<div style=\"text-align:middle;font-size:16px;font-weight:bold;\" class=\"notFoundMessage\">"
					. $vbphrase['no_articles_in_this_section'] . " </div>\n\n</td></tr>\n";
		}
		print_hidden_fields();
		$result .= "</table></td></tr>\n";
		$result .= "</table>";
		$result .= self::getNav($per_page, $total_records, $page, 'filter_nodesection', 100, 'page',
			true, ('cms_content_admin.php' .
			($vbulletin->GPC_exists['sectionid'] ? '?sectionid=' . $vbulletin->GPC['sectionid'] : '')));
		global $echoform;
		$echoform = false;
		$result .= "</form>";
		return $result;
	}

	//Remove <script></script> tags.
	public static function stripScript($input)
	{
		$pattern = '#<(\s*)script\b[^>]*>(.*?)</(\s*)script\b[^>]*>#i';
		return preg_replace($pattern, '', $input);
	}

	/*** This function fixes the node table. The one catch with a preorder tree
	* traversal data structure is that it is more easily damaged than a recursive
	* structure. We have to be recursive of course.
	* ***/
	public static function fixNodeLR($sectionid = false, $nodeleft = 0, $sectiontypeid = false, $findmissing = true)
	{
		//We pull the list of items that are children of the current node.
		global $vbulletin;
		global $vbphrase;
		$result = '';

		if (! intval($sectiontypeid))
		{
			$sectiontypeid = vB_Types::instance()->getContentTypeID("vBCms_Section");
		}

		//There can be nodes that don't have a valid parent. In that case, we make a
		//"lost and found" section and put them into it. If we do this first, then
		//we will automatically fix it.
		if ($findmissing)
		{
			if ($rst = $vbulletin->db->query_read("SELECT n1.nodeid FROM " .
			TABLE_PREFIX . "cms_node n1 LEFT JOIN " .
			TABLE_PREFIX . "cms_node n2 ON n2.nodeid = n1.parentnode AND n2.nodeid <> n1.nodeid
				AND n2.contenttypeid = $sectiontypeid
 			WHERE n2.nodeid IS NULL AND n1.contenttypeid <> $sectiontypeid;"))
			{
				$orphans = array();
				while($record = $record = $vbulletin->db->fetch_array($rst))
				{
					$orphans[] = $record['nodeid'];
				}
			}

			//Do we have orphans?
			if (count($orphans))
			{
				//We need to make a lost and found folder.
				$record = $vbulletin->db->query_first("SELECT node.nodeid FROM " .
				TABLE_PREFIX . "cms_node AS node WHERE	node.parentnode IS NULL LIMIT 1");
				//Now create a node.
				$vbulletin->GPC_exists['parentnode'] = $vbulletin->GPC_exists['sectionid'] = 1;
				$vbulletin->GPC['parentnode'] = $vbulletin->GPC['sectionid'] = $record['nodeid'];

				if ($content = vBCms_Content::create('vBCms', 'Section'))
				{
					require_once DIR . '/includes/functions_misc.php';
					$nodedm = new vBCms_DM_Section();
					$nodedm->set('parentnode', $record['nodeid']);
					$nodedm->set('contenttypeid', $sectiontypeid);
					vB::$vbulletin->GPC_exists['title'] = 1;
					vB::$vbulletin->GPC['title'] = fetch_phrase('lost_found', 'cpcms');
					// create content handler
					$content = vBCms_Content::create('vBCms', 'Section');
					$content->setParentNode( $record['nodeid']);
					if (! $sectionid = $content->createDefaultContent($nodedm))
					{
							throw (new vB_Exception_DM('Could not create new node for content: ' . print_r($nodedm->getErrors())));
					}
					$result = fetch_phrase('check_lost_found', 'cpcms');
				}
				vB_Cache::instance()->event('sections_updated');
				vB_Cache::instance()->cleanNow();

				//We have everything we need. Let's update
				$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "cms_node SET parentnode = $sectionid, permissionsfrom = 1
				WHERE nodeid IN(" . implode(',', $orphans) . ")");

			}
			self::fixNodeLR(false, 0, $sectiontypeid, false);
			return $result;
		}
		else
		{
			$rst = $vbulletin->db->query_read($sql = "SELECT node.nodeid, contenttypeid FROM " .
			TABLE_PREFIX . "cms_node AS node INNER JOIN " .
			TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid WHERE
			node.parentnode " . (intval($sectionid) ? "= $sectionid" : "IS NULL")  . " ORDER BY nodeleft" );

			$nodes = array();
			while ($record = $vbulletin->db->fetch_array($rst))
			{
				$nodes[] = $record;
			}
			$vbulletin->db->free_result($rst);

			$childnodeleft = intval($nodeleft) + 1;

			if ($sectionid)
			{
				//Find out if we should assign permissionsfrom for this record, or inherit from a parent.
				if ($permission_record = $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX .
					"cms_permissions WHERE nodeid = $sectionid LIMIT 1;" ))
				{
					$permissionsfrom = $sectionid;
				}
				else
				{
					$permission_record = $vbulletin->db->query_first("SELECT permissionsfrom FROM " . TABLE_PREFIX .
					"cms_node WHERE nodeid = $sectionid LIMIT 1;" );
					$permissionsfrom = $permission_record['permissionsfrom'];
				}
			}
			else
			{
				//We are at the root. Our early code created orphan nodecategory records. Let's find and
				// remove any.
				$vbulletin->db->query_write("CREATE TEMPORARY TABLE cms_nc_orphans AS
					select nc.nodeid FROM " . TABLE_PREFIX .
					"cms_nodecategory nc LEFT JOIN " . TABLE_PREFIX .
					"cms_node AS node on node.nodeid = nc.nodeid
						WHERE node.nodeid IS NULL;");

				$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX .
					"cms_nodecategory WHERE nodeid IN (SELECT nodeid from cms_nc_orphans);");

				$vbulletin->db->query_write("DROP TEMPORARY TABLE cms_nc_orphans");

				$permissionsfrom = false;
			}

			foreach ($nodes as $node)
			{
				if (intval($node['contenttypeid']) == intval($sectiontypeid))
				{
					if ($permissionsfrom)
					{
						$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "cms_node SET permissionsfrom =
						 $permissionsfrom WHERE nodeid = " . $node['nodeid']);
					}
					$childnodeleft = self::fixNodeLR($node['nodeid'], $childnodeleft, $sectiontypeid, false);
				}
				else
				{
					$rst = $vbulletin->db->query_write($sql = "UPDATE " .
					TABLE_PREFIX . "cms_node SET nodeleft = $childnodeleft, noderight = " .
					($childnodeleft + 1) . ", permissionsfrom = $permissionsfrom WHERE nodeid = " . $node['nodeid'] );
					$childnodeleft += 2;
				}
			}
			if (intval($sectionid))
			{
				$rst = $vbulletin->db->query_write($sql = "UPDATE " .
				TABLE_PREFIX . "cms_node set nodeleft = $nodeleft, noderight = " .
				$childnodeleft . ($permissionsfrom ? ", permissionsfrom = $permissionsfrom" : '') .
				" WHERE nodeid = $sectionid " );
				return $childnodeleft + 1;
			}
		}

		self::fixCategoryLR();

		return $result;
	}
	/*** This function fixes the category table. The one catch with a preorder tree
	 * traversal data structure is that it is more easily damaged than a recursive
	 * structure. We have to be recursive of course.
	 * ***/
	public static function fixCategoryLR($parentcat = false, $parentnode = false, $catleft = 1)
	{
		global $vbulletin;
		//are we starting from the root, or are we at a sub level

		if ($parentcat)
		{
			//We at a subnode level
			$rst = $vbulletin->db->query_read($sql = "SELECT categoryid FROM " .
			TABLE_PREFIX . "cms_category WHERE
			parentcat = $parentcat  ORDER BY category " );

			$nodes = array();
			while ($record = $vbulletin->db->fetch_array($rst))
			{
				$nodes[] = $record;
			}

			if (! count($nodes))
			{
				$vbulletin->db->query_write ("UPDATE " . TABLE_PREFIX . "cms_category
					SET catleft = $catleft, catright = $catleft + 1, parentnode = $parentnode
					where categoryid = $parentcat");
			return $catleft + 2;
			}

			$child_left = intval($catleft) + 1;
			//this has subcategories. We'll do the current category last.

			foreach ($nodes as $node)
			{
				$child_left = self::fixCategoryLR($node['categoryid'], $parentnode , $child_left);
			}

			$vbulletin->db->query_write ("UPDATE " . TABLE_PREFIX . "cms_category
					SET catleft = $catleft, catright =  $child_left, parentnode = $parentnode
					where categoryid = $parentcat");
			return intval($child_left) + 1;

		}

		else if ($parentnode)
		{
			//We're at the top category level
			//first, let's delete orphans.
			$rst = vB::$vbulletin->db->query_read("SELECT DISTINCT cat.parentnode FROM " . TABLE_PREFIX . "cms_category AS cat LEFT JOIN
			" . TABLE_PREFIX . "cms_node AS node ON node.nodeid = cat.parentnode
			WHERE node.nodeid IS NULL");
			if ($rst)
			{
				$ids = array();
				while($record = vB::$vbulletin->db->fetch_array($rst))
				{
					$ids[] = $record['parentnode']	;
				}

				if (count($ids))
				{
					vB::$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "cms_category
					WHERE parentnode in (" . implode(',', $ids) . ")");
				}
			}


			$rst = $vbulletin->db->query_read($sql = "SELECT categoryid FROM " .
			TABLE_PREFIX . "cms_category WHERE
			parentnode = $parentnode AND (ifnull(parentcat,0) = 0) ORDER BY category " );

			$nodes = array();
			while ($record = $vbulletin->db->fetch_array($rst))
			{
				$nodes[] = $record;
			}

			$vbulletin->db->free_result($rst);
			foreach($nodes as $record)
			{
				$catleft = self::fixCategoryLR($record['categoryid'], $parentnode, $catleft);
			}
			return $catleft;
		}
		else
		{
			//In case we have records with invalid parentcat's, we'll set them to zero.
			$rst = $vbulletin->db->query_read("SELECT DISTINCT cat.parentcat FROM " . TABLE_PREFIX . "cms_category cat
			LEFT JOIN " . TABLE_PREFIX . "cms_category AS parent ON parent.categoryid = cat.parentcat
			WHERE parent.categoryid IS NULL AND cat.parentcat IS NOT NULL;");
			if ($rst)
			{
				$parentids = array();
				while($record = $vbulletin->db->fetch_array($rst))
				{
					$parentids[] = $record['parentcat'];
				}
				if (count($parentids))
				{
					$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "cms_category SET parentcat = NULL where
					parentcat in (" . implode(', ', $parentids) . ")");
				}
			}

			//We start by pulling a list of the categories. We'll handle them one at a time.
			$rst = $vbulletin->db->query_read("SELECT DISTINCT parentnode FROM " . TABLE_PREFIX . "cms_category");

			while($record = $vbulletin->db->fetch_array($rst))
			{
				$nodes[] = $record;
			}
			$vbulletin->db->free_result($rst);

			if (count($nodes))
			{
				foreach($nodes as $record)
				{
					$catleft = self::fixCategoryLR(false, $record['parentnode'], $catleft);
				}
			}
		}

		return true;
	}
	/**********************
	 * This function lists the sections.
	 * @param integer $page : The current page number
	 * @param integer $per_page : the number of nodes to display per page.
	 * @param string $perpage_select : the
	 * @return nothing
	 ****/
	public static function listSections($page, $per_page = 10)
	{
		global $vbphrase;
		global $vbulletin;
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');

		$sectionid = ( ($vbulletin->GPC_exists['sectionid'] AND intval($vbulletin->GPC['sectionid']))?
			$vbulletin->GPC['sectionid'] : false);

		$sections = self::getSection($sectionid);

		if ($record_count = count($sections))
		{
			$sections = array_slice($sections, ($page-1) * $per_page, $per_page, true);
			$parent = $vbulletin->db->query_first($sql = "SELECT info.title FROM " .
				TABLE_PREFIX . "cms_node AS node INNER JOIN " . TABLE_PREFIX .
				"cms_nodeinfo AS info ON info.nodeid = node.nodeid
				WHERE " .
				( $sectionid ?
					" node.nodeid = " . $sectionid : " node.nodeid IS NULL" ));
			$i = ($page-1) * $per_page;

			$result = print_form_header('cms_content_admin', '', false, true, 'cms_data', '100%', '_self',
					true, 'post', 0, false);
			$result .= "<input type=\"hidden\" id=\"sectionid\" value=\"" .
				( $sectionid ? $sectionid :'') .	"\" name=\"sectionid\"/>
			<input type=\"hidden\" name=\"sentfrom\" id=\"section\" value=\"section\"/>
			<input type=\"hidden\" name=\"id\" id=\"id\" value=\"0\"/>";

			$result .= self::getSectionHeaders($sectionid) . "<br />\n";
			$result .= "<tr class=\"tcat\">
					<td class=\"feature_management_header\" style=\"padding:5px;float:" . vB_Template_Runtime::fetchStyleVar('left') . ";\"><div style=\"float:" . vB_Template_Runtime::fetchStyleVar('left') . "\">
					" . $vbphrase['you_are_managing'] . " " . $vbphrase['section'] . ": <span class=\"section_name\">" . $parent['title'] .
					($vbulletin->GPC_exists['sectionid'] ? '' : '(' . $vbphrase['all_sections'] .')') . "</span>
					<input type=\"button\" onclick=\"showNodeWindow('filter_section')\" value=\"" . $vbphrase['navigate_to_section'] ."\">
					"
					.  "
					</div>
					</td>
					</tr>";
			$result.= "<tr><td>\n";
			$result .= "<div style=\"overflow:auto;margin: auto;\">
			<table class=\"tborder\" cellpadding=\"4\" border=\"0\" width=\"100%\" align=\"center\">\n";
			$bgclass = fetch_row_bgclass();
			$result .= "<tr align=\"center\" class=\"thead\">\n";
			$result .= "<td class=\"thead\" width=\"20\">#</td>
				<td class=\"thead\" align=\"" . vB_Template_Runtime::fetchStyleVar('left') . "\" width=\"400\"><a href=\"cms_content_admin.php?do=sort&sentfrom=section&sortby=config4.value\" target=\"_self\">" . $vbphrase['title'] . "</a></td>
				<td class=\"thead\"><a href=\"cms_content_admin.php?do=sort&sentfrom=section&sortby=setpublish\">" . $vbphrase['published'] . "</a></td>
				<td class=\"thead\">" . $vbphrase['content_layout'] . "</td>
				<td class=\"thead\"><a href=\"cms_content_admin.php?do=sort&sentfrom=section&sortby=auto_displayorder\" target=\"_self\">" . $vbphrase['display_order'] . "</a></td>
				<td class=\"thead\" width=\"50\">" . $vbphrase['records_per_page'] . "</td>
				<td class=\"thead\">" . $vbphrase['subsections'] . "</td>
				<td class=\"thead\">" . $vbphrase['content'] . "</td>
				<td class=\"thead\">" . $vbphrase['viewcount'] . "</td>".
/*				<td class=\"thead\">" . $vbphrase['layout'] . "</td>
				<td class=\"thead\">" . $vbphrase['style'] . "</td> */
			" </tr>";
			$sequence = 0;

			foreach($sections as $key => $section)
			{
				$sequence++;
				$i++;
				$first_selected_parent_row_class = "";
				$change_display_order_buttons = "";
				$section_name_prefix = ((vB_Template_Runtime::fetchStyleVar('textdirection') == 'ltr') ?
						'&gt;' : '&gt;');

				if ($sequence == 1 AND $page == 1)
				{
					$first_selected_parent_row_class = " class=\"selected_parent_row\"";
					$section_name_prefix = "";
				}

				// for sub-sections, display up or down arrows to change the display order
				else
				{
					$change_display_order_buttons = "<div style=\"float:" . vB_Template_Runtime::fetchStyleVar('left') . "; width:32px;\">";
					// dont display up arrow if its already first section in list
					if ($sequence > 2 AND isset($sections[$key-1]))
					{
						$change_display_order_buttons .= "<a style=\"float:" . vB_Template_Runtime::fetchStyleVar('left') . ";\" href=\"javascript:swapSections(".$section['nodeid'].", ".$sections[$key-1]['nodeid'].")\"><img src=\"" . self::getImagePath('imgdir_cms') . "/arrow_up.png\" style=\"border-style:none\" /></a>";
					}
					// dont display down arrow is its already last section in list
					if ($sequence < count($sections) AND isset($sections[$key+1]))
					{
						$change_display_order_buttons .= "<a style=\"float:right;\" href=\"javascript:swapSections(".$section['nodeid'].", ".$sections[$key+1]['nodeid'].")\"><img src=\"" . self::getImagePath('imgdir_cms') . "/arrow_down.png\" style=\"border-style:none\" /></a>";
					}
					$change_display_order_buttons .= "</div>";
				}

				$bgclass = fetch_row_bgclass();
				$result .= "<tr" . $first_selected_parent_row_class . " align=\"center\">\n <input type=\"hidden\" name=\"ids[]\" value=\"" .
					$section['nodeid'] . "\" />\n";
				$result .= "  <td class=\"$bgclass\" style=\"font-size:80%;\">$i</td>\n";
				$result .= "  <td align=\"" . vB_Template_Runtime::fetchStyleVar('left') . "\" class=\"$bgclass\" style=\"font-size:80%;width:400px;\"><div class=\"sectionTitleWrapper\" style=\"width:400px;\">
					" . $change_display_order_buttons . $section_name_prefix . "<a href=\"./cms_content_admin.php?do=filter&sectionid=" . $section['nodeid'] . "&contenttypeid=" .
					vb_Types::instance()->getContentTypeID("vBCms_Section") .
						"\" target=\"_self\" >" . $section['title'] . "</a>
						<div style=\"float:" . vB_Template_Runtime::fetchStyleVar('right') . "\">
						<a href=\"javascript:showSectionEdit('new_section'," . (intval($section['parentnode']) ? $section['parentnode'] : '0') .
						 ", " . $section['nodeid'] . ",'')\"><img src=\"" . self::getImagePath('imgdir_cms') . "/add_small.png\" style=\"border-style:none\"></a>
						<a href=\"javascript:showSectionEdit('save_section',". (intval($section['parentnode']) ? $section['parentnode'] : '0') . ', ' .
						 $section['nodeid'] . ", '"
					.  vB_Template_Runtime::escapeJS($section['title']) . "')\")\"><img src=\"" . self::getImagePath('imgdir_cms') . "/edit_small.png\" style=\"border-style:none\"></a>"
					. ((intval($section['nodeid']) != 1 AND intval($section['section_count']) == 0 AND intval($section['item_count']) == 0) ?
					"<a href=\"javascript:confirmSectionDelete(" . $section['nodeid'] . ', \'' . vB_Template_Runtime::escapeJS($vbphrase['confirm_deletion']). "');\">
					<img src=\"" . self::getImagePath('imgdir_cms') . "/delete_small.png\" style=\"border-style:none\"></a>" : '')
				. "
				</div>
				</div></td>\n";
				$result .= "  <td class=\"$bgclass\" style=\"font-size:80%;\"><select name=\"state_" .
					$section['nodeid']. "\" id=\"state_" . $section['nodeid']. "\"
					onchange=\"setFormValue('do', 'saveonesectionstate');
					setFormValue('nodeid', " . $section['nodeid']. ");document.getElementById('cms_data').submit();\">" . self::getPublishedSelect(
						intval($section['setpublish']), $section['publishdate']) . "</select></td>\n";
				$result .= "  <td class=\"$bgclass\" style=\"font-size:80%;\"><select id=\"cl_" . $section['nodeid'] . "\" name=\"cl_" . $section['nodeid'] .
				"\" onchange=\"setFormValue('do','saveonecl');	setFormValue('nodeid'," . $section['nodeid'] . ");
					document.getElementById('cms_data').submit();\">" . self::getContentLayoutSelect($section['content_layoutid']) ;
				$result .= "  <td class=\"$bgclass\" style=\"font-size:80%;\"><select name=\"sect_pr_" . $section['nodeid'] .
					 "\" onchange=\"setFormValue('nodeid', " . $section['nodeid']. ");
					setFormValue('do', 'sectionpriority');document.getElementById('cms_data').submit();\">\n" .
					self::getSectionPrioritySelect($section['priority'], $section['nodeid']) . "</select></td>\n";
				$result .= "  <td class=\"$bgclass\" style=\"font-size:80%;\">" . self::getSectionPPEdit($section['per_page'], $section['nodeid']) . "</td>\n";
				$result .= "  <td class=\"$bgclass\" style=\"font-size:80%;\">" . $section['section_count'] . "</td>\n";
				$result .= "  <td class=\"$bgclass\" style=\"font-size:80%;\">" . $section['item_count'] . "</td>\n";
				$result .= "  <td class=\"$bgclass\" style=\"font-size:80%;\">" . $section['viewcount'] . "</td>\n";
				$result .= "</tr>\n";
			}

			print_hidden_fields();
			$result .= "</table>";
			$result .= "</div></td></tr>";
			$result .= "</table>\n";
			$result .= self::getNav($per_page, $record_count, $page, 'section', 100, 'page', true,
				('cms_content_admin.php' . ($sectionid ? "?sectionid=$sectionid" : '')));
			global $echoform;
			$echoform = false;
			$result .= "</form>";
			$result .= self::getSectionEditPanel();
			return $result;
		}
	}

	/********************
	* When we have a lot of pages (20? 50?) we can't put links to them all.
	* This function will generate the appropriate list of links so we can get,
	* eventually, to any page with a reasonable number of clicks
	* @param integer $perpage : items per page
	* @param integer $record_count : total number of records
	* @param integer  $this_page : the current page number
	* @return array of integers : the page numbers.
	*********************/
	public static function getPageList($perpage, $record_count, $this_page)
	{
		$page_numbers = array();
		$pagecount =  ceil($record_count/$perpage);

		if ($pagecount == 2)
		{
			return array(1,2);
		}

		if ($pagecount <=  10)
		{

			for ($i = 1; $i <= $pagecount; $i++)
			{
				$page_numbers [] = $i;
			}
		}
		else
		{
			//We need to build an array of pages we'll display. We need to separately build
			// forward and backward. We'll start with smallish steps and then go larger.
			if ($this_page > 1)
			{
				$step = 1;
				$thiscount = 5;
				$curr_page = $this_page - 1 ;
				while($curr_page > 0)
				{
					$page_numbers[] = $curr_page;

					if ($thiscount <= 0)
					{
						$thiscount = 3;
						$step = $step * 10;
					}
					$curr_page -= $step;
					$thiscount --;
				}

			}

			$page_numbers[] = $this_page;

			if ($this_page < $pagecount)
			{
				$step = 1;
				$thiscount = 5;
				$curr_page = $this_page + 1 ;
				while($curr_page <= $pagecount)
				{
					$page_numbers[] = $curr_page;

					if ($thiscount <= 0)
					{
						$thiscount = 3;
						$step = $step * 10;
					}
					$curr_page += $step;
					$thiscount --;
				}
			}
			//Let's make sure 1 and the last page are in the list
			if (! in_array(1, $page_numbers))
			{
				$page_numbers[] = 1;
			}

			if (! in_array($pagecount, $page_numbers))
			{
				$page_numbers[] = $pagecount;
			}

			sort($page_numbers, SORT_NUMERIC);

		}

		return $page_numbers;
	}

	/**********************
	 * This function creates the navigation links for when we have multiple nodes.
	 ************/
	public static function getNav($perpage, $record_count, $this_page, $displayfor, $width_pct = 90, $page_link = 'page',
		$showframe = true, $page_url = false, $show_perpage_select = true)
	{
		$page_url = $page_url ? $page_url : 'cms_content_admin.php';
		$q = strpos($page_url, '?') ? '&amp;' : '?';

		global $vbphrase;
		$result = '';

		if ($show_perpage_select)
		{
			$perpage_select = self::getPerpageSelect($perpage);

			$result =  "<div class=\"tcat\" align=\"center\" id=\"page_nav\" style=\"width:$width_pct%;margin:auto;\">\n" .
				$vbphrase['records_per_page'] . "&nbsp;$perpage_select\n" ;

			if (intval($record_count) <= intval($perpage))
			{
				$result .= "</div>\n";
				return $result;
			}
		}

		if (intval($record_count) <= intval($perpage))
		{
			return '';
		}

		$pagecount =  ceil($record_count/$perpage);
		$result .= 	new vB_Phrase('global', 'page_x_of_y', $this_page, $pagecount) .
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' .
			$vbphrase['page'] . "&nbsp;&nbsp;";

		$result .= "<a href=\"{$page_url}{$q}" . ($displayfor ? "do=$displayfor&" : "")  .
			"$page_link=1" . ($showframe ? "\" frame=\"_self\"" : "")  .
			"\">"
			. $vbphrase['first'] . "</a>&nbsp;\n" ;
		foreach (self::getPageList($perpage, $record_count, $this_page) as $page_number)
		{
			$result .= "<a href=\"{$page_url}{$q}" . ($displayfor ? "do=$displayfor&" : "")  .
				"$page_link=$page_number" . ($showframe ? "\" frame=\"_self\"" : "")  .
				"\">
				$page_number</a>&nbsp;\n";
		}
		$result .= "<a href=\"{$page_url}{$q}" . ($displayfor ? "do=$displayfor&" : "")  .
			"$page_link=$pagecount" . ($showframe ? "\" frame=\"_self\"" : "")  .
			"\">"
			. $vbphrase['last'] . "</a>&nbsp;\n" ;

		return $result;
	}

	//This function builds an array of all section nodes in the hierarchy that
	// have children, and caches it.
	public static function getSections($withcontent = true, $all_nav = false, $show_parent_titles = true)
	{

		$cachekey = 'vbcms_sectionlist' . (!$withcontent ? "_all" : '') .
			($all_nav ? '_allnav' : '') .
			($show_parent_titles ?  '_long' : '_short');
		$result = vB_Cache::instance()->read($cachekey, false, true) ;

		if ($result AND count($result))
		{
			return $result;
		}

		$result = array();
		$rst = vB::$vbulletin->db->query_read($sql = "SELECT node.nodeid, node.url, node.parentnode, node.nodeleft, node.noderight,
			node.setpublish, node.publishdate, info.title, node.permissionsfrom, node.hidden, COUNT(child.nodeid) AS children,
  			SUM(CASE WHEN (child.setpublish > 0 AND child.publishdate < " . TIMENOW . " AND child.contenttypeid <> " .
		  	vb_Types::instance()->getContentTypeID("vBCms_Section") . ") THEN 1 ELSE 0 END) AS publish_count
			FROM " . TABLE_PREFIX . "cms_node AS node
			LEFT JOIN " . TABLE_PREFIX . "cms_node AS child ON child.parentnode = node.nodeid AND child.new != 1
			LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid
			WHERE (node.contenttypeid = " . vb_Types::instance()->getContentTypeID("vBCms_Section") .
			($all_nav ? " OR node.shownav > 0 "  : '') . ")
			GROUP BY node.nodeid, node.url, node.parentnode, node.nodeleft, node.noderight,
			node.setpublish, node.publishdate, info.title, node.permissionsfrom, node.hidden " .
			($withcontent ? " HAVING COUNT(child.nodeid) > 0 " : '') . "
			ORDER by node.nodeleft;");

		$parentage = array();
		$parentage[0] = array('noderight' => 9999999);
		$level = 0;
		$titles = array();
		while($record =  vB::$vbulletin->db->fetch_array($rst))
		{
			//get the level
			while(($level > 0) AND ($record['noderight'] > $parentage[$level]['noderight']))
			{
				$level--;
			}
			$level++;
			$record['depth'] = $level - 1;

			//get the parentage
			if ($level <= 2)
			{
				$record['parent'] = '';
			}
			else
			{
				if ($show_parent_titles)
				{
					$record['parent'] = implode('> ', array_slice($titles, 1, $level - 2, true)) . '> ';
				}
				else
				{
					$record['parent'] = str_repeat( ((vB_Template_Runtime::fetchStyleVar('textdirection') == 'ltr') ? '>' : '<'),$level - 2 ) . '&nbsp;' ;
				}
			}
			$record['leaf'] = $record['title'];
			$result[] = $record;
			$parentage[$level] = array('noderight' => $record['noderight']);
			$titles[$level] = $record['title'];
		}
		vB_Cache::instance()->write($cachekey, $result, 1440, array('sections_updated'));
		return $result;
	}

	/**********************
	 * This generates a hash consisting of this sort plus the filters plus
	 * userid. This lets us cache search results.
	 *
	 * @param none
	 * @return string
	 ********************/
	private static function getContentHash($sortby = '' , $filter = array())
	{
		global $vbulletin;
		$context = new vB_Context('cms_contentmgr' , array('sortby' => $sortby,
			'filter' => $filter,
			'userid' => $vbulletin->userinfo['userid']));

		return strval($context);

	}

	/**
	 * This gets a list of the publicly viewable "leaf" nodes. It was created for
	 * use by the sitemap builder but it seems it could have other uses.
	 *
	 * @param	int	$sortby	1:section order, then title, 2: title, 3:publish_date
	 * @return array
	 */
	public static function getPublicContent($startat = 0, $qty = 10000, $sortby = 1)
	{
		$perms = vBCMS_Permissions::getPerms(0);

		$sql = "SELECT node.nodeid, node.contenttypeid, node.hidden, info.title, parentinfo.title AS section,
		parent.nodeid AS sectionid, node.setpublish, node.publishdate, node.url FROM " . TABLE_PREFIX .
		"cms_node AS node INNER JOIN " . TABLE_PREFIX .	"cms_nodeinfo AS info ON info.nodeid = node.nodeid
		INNER JOIN " . TABLE_PREFIX .	"cms_node AS parent ON parent.nodeid = node.parentnode
		INNER JOIN " . TABLE_PREFIX .	"cms_nodeinfo AS parentinfo ON parentinfo.nodeid = parent.nodeid
		WHERE node.setpublish > 0 AND parent.setpublish > 0 AND parent.publishdate < " . TIMENOW .
		" AND node.publishdate < " . TIMENOW . " AND node.permissionsfrom IN (" .
		implode(',', $perms['canview']) . ") AND (node.contenttypeid <> " .
		vb_Types::instance()->getContentTypeID("vBCms_Section") . ") ";

		switch($sortby){
			case 3 :
				$sql .= " ORDER BY node.setpublish DESC";
				break;
			case 2 :
				$sql .= " ORDER BY info.title";
				break;
			default:
				$sql .= " ORDER BY parent.nodeleft, info.title";
		} // switch

		$sql .= " LIMIT $startat, $qty ";
		$rst = vB::$db->query_read($sql);
		$nodes = array();
		while($node = vB::$db->fetch_array($rst))
		{
			$nodes[$node['nodeid']] = $node;
		}
		return $nodes;
	}

	/** This function returns the correct imagepath, which is different
	 * for admincp and front end.
	 *
	 * @param	string	stylevar
	 * @return	string	imagepath
	 */
	public static function getImagePath($imagepath)
	{
		global $vbulletin;
		if (VB_AREA == 'AdminCP' OR defined('CMS_ADMIN'))
		{
			return '../cpstyles/' . $vbulletin->options['cpstylefolder'];
		}
		else
		{
			return vB_Template_Runtime::fetchStyleVar($imagepath);
		}
	}

	/** This function prepares the preview text for an article or other bbcode text
	*
	*	@param	string	the text to be parsed
	 *	@param	int		the number of chars to be returned
	 *	@param	bool		can this user use HTML in their content
	 *	@param	str		the html state of the text - on, off, or on_nl2br
	 *
	 *	@return	string
	 * ***/
	public static function makePreviewText($pagetext, $chars, $canUseHtml, $htmlstate = null, $strip_quotes = true)
	{
		//We don't want any table content to display when we generate the preview- unless there
		// is nothing else
		$pagetext = trim(preg_replace('/\<(\s*)TABLE(.+)\<\/TABLE\>/is', ' ', $pagetext));
		$tableless_text = trim(preg_replace('/\[TABLE(.+)\[\/TABLE\]/is', ' ', $pagetext));

		if ($tableless_text =='')
		{
			$tableless_text = $pagetext;
		}

		$parser = new vBCms_BBCode_HTML(vB::$vbulletin, vBCms_BBCode_HTML::fetchCmsTags());
		$previewtext = $parser->get_preview(
			$tableless_text,
			$chars,
			$canUseHtml,
			true,
			$htmlstate,
			$strip_quotes);

		if ($previewtext =='')
		{
			$previewtext = $parser->get_preview(
				$pagetext,
				$chars,
				$canUseHtml,
				true,
				$htmlstate,
				$strip_quotes);
		}
		//We tend to get some blank lines that we don't need.
		$previewtext = preg_replace('/^\<br\>$/i', '', $previewtext);
		$previewtext = preg_replace('/^\<br\/\>$/i', '', $previewtext);
		$previewtext = preg_replace('/^\<br \/\>$/i', '', $previewtext);
		return $previewtext;

	}
}
