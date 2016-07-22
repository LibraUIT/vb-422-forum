<?php if (!defined('VB_ENTRY')) die('Access denied.');

require_once DIR . '/vb/search/searchcontroller.php' ;

class vBCms_Search_SearchController_NewContentNode extends vB_Search_SearchController
{
	const MAX_DAYS = 2;
	/** This isn't an actual content type. It's a generic wrapper for non-aggregator nodes **/
	private $contenttypeid = false;

	/*****
	* This function returns the results set.
	****/
	public function get_results($user, $criteria)
	{
		global $vbulletin;
		$db = $vbulletin->db;

		$range_filters = $criteria->get_range_filters();
		$equals_filters = $criteria->get_equals_filters();
		$sort = $criteria->get_sort();
		$direction = strtolower($criteria->get_sort_direction()) == 'desc' ? 'desc' : 'asc';

		$sort_join = "";
		$orderby = "";
		$section_join = "";
		$where = array();

		//verify permissions
		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}

		if (! count(vB::$vbulletin->userinfo['permissions']['cms']['canview']))
		{
			return array();
		}

		if ($sort == 'dateline')
		{
			$orderby = 'node.publishdate ' . $direction;
		}
		else if ($sort == 'user')
		{
			$sort_join = "JOIN " . TABLE_PREFIX . "user AS user ON node.userid = user.userid";
			$orderby = "user.username " . $direction . ", node.publishdate DESC";
		}
		else
		{
			$orderby = " node.publishdate DESC";
		}

		$results = array();

		$where[] = " node.publishdate <= " . TIMENOW;
		//get date cut -- but only if we're not using the threadmarking filter
		if (isset($range_filters['datecut']))
		{
			//ignore any upper limit
			$where[] = " node.publishdate >= " . $range_filters['datecut'][0];
		}
		else if (isset($range_filters['dateline']))
		{
			$where[] = " node.publishdate >= " . $range_filters['dateline'][0];
		}
		else if (isset($range_filters['days']))
		{
			$where[] = " node.publishdate >= " . $range_filters['days'][0];
		}
		else
		{
			$where[] = " node.publishdate >= " . TIMENOW - 86400 *
				($vbulletin->GPC_exists['days'] ? $vbulletin->GPC['days'] : self::MAX_DAYS);
		}

		if (isset($equals_filters['userid']))
		{
			$where[] = " node.userid " .
				(is_array($equals_filters['userid'][vB_Search_Core::OP_EQ]) ?
					"in (" . implode(', ', $equals_filters['userid'][vB_Search_Core::OP_EQ])
						. ") " :
					" = " . $equals_filters['userid'][vB_Search_Core::OP_EQ]
				);
		}
		else if ($vbulletin->GPC_exists['userid'])
		{
			$where[] = " node.userid = " . $vbulletin->GPC['userid'];
		}

		if ($vbulletin->GPC_exists['sectionid'])
		{
			$where[] = " parent.nodeid = " . $vbulletin->GPC['sectionid'];
			$section_join = "INNER JOIN " . TABLE_PREFIX . "cms_node AS parent ON
				(node.nodeleft >= parent.nodeleft AND node.nodeleft <= parent.noderight)";
		}

		if ($keywords = $criteria->get_keywords())
		{
			$searchcore_join = " INNER JOIN " . TABLE_PREFIX . "searchcore AS searchcore
				ON searchcore.primaryid = node.contentid
				AND searchcore.contenttypeid = node.contenttypeid";
			$where[] = " MATCH  (title, keywordtext) against "
				 . $db->escape_string($keywords) . " IN BOOLEAN MODE ";
		}

		if ($this->contenttypeid = $criteria->get_contenttypeid())
		{
			$where[] = " node.contenttypeid = " . $this->contenttypeid;
		}

		$q = "
			SELECT node.nodeid, node.contenttypeid, node.contentid
			FROM " . TABLE_PREFIX . "cms_node as node
			$searchcore_join
			$sort_join
			$section_join
			WHERE node.new != 1 AND node.nosearch != 1 AND ((node.permissionsfrom in (
			" . implode(', ', vB::$vbulletin->userinfo['permissions']['cms']['canview']) .
			") AND node.setpublish > 0 AND node.publishdate <= " . TIMENOW . " ) OR (node.permissionsfrom in (
			" . implode(', ', vB::$vbulletin->userinfo['permissions']['cms']['canedit']) .
			")) OR (node.userid = " . intval(vB::$vbulletin->userinfo['userid']) . ") )"  .
			($where ? " AND " : '') . implode(' AND ', $where) . "
			ORDER BY $orderby
			LIMIT " . intval($vbulletin->options['maxresults']);

		$entries = $db->query_read_slave($q);

		while ($entry = $db->fetch_array($entries))
		{
			$results[] = array($entry['contenttypeid'], $entry['contentid'], $entry['nodeid']);
		}

		return $results;
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 38992 $
|| ####################################################################
\*======================================================================*/