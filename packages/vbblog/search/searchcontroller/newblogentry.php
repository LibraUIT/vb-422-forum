<?php if (!defined('VB_ENTRY')) die('Access denied.');

require_once(DIR . '/vb/search/searchcontroller.php');

class vBBlog_Search_SearchController_NewBlogEntry extends vB_Search_SearchController
{
	public function get_results($user, $criteria)
	{
		global $vbulletin;
		$db = $vbulletin->db;

		$range_filters = $criteria->get_range_filters();

		$sort = $criteria->get_sort();
		$direction = strtolower($criteria->get_sort_direction()) == 'desc' ? 'desc' : 'asc';

		$sort_join = "";
		$orderby = "";

		if($sort == 'dateline')
		{
			$orderby = 'blog.dateline ' . $direction;
		}
		else if ($sort == 'user')
		{
			$sort_join = "JOIN " . TABLE_PREFIX . "user AS user ON blog.userid = user.userid";
			$orderby = 'user.usename ' . $direction . ',blog.dateline DESC';
		}
		else
		{
			$orderby = 'blog.dateline DESC';
		}

		$results = array();

		//get thread/post results.
		if (!empty($range_filters['markinglimit'][0]))
		{
			$cutoff = $range_filters['markinglimit'][0];

			$marking_join = "
				LEFT JOIN " . TABLE_PREFIX . "blog_read AS blog_read ON
					(blog_read.blogid = blog.blogid AND blog_read.userid = " . $vbulletin->userinfo['userid'] . ")
				INNER JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
				LEFT JOIN " . TABLE_PREFIX . "blog_userread AS blog_userread ON
					(blog_userread.bloguserid = blog_user.bloguserid AND blog_userread.userid = " . $vbulletin->userinfo['userid'] . ")
			";

			$lastentry_where = "
				blog.dateline > IF(blog_read.readtime IS NULL, $cutoff, blog_read.readtime)
				AND blog.dateline > IF(blog_userread.readtime IS NULL, $cutoff, blog_userread.readtime)
				AND blog.dateline > $cutoff
			";
		}
		else
		{
			//get date cut -- but only if we're not using the threadmarking filter
			if (isset($range_filters['datecut']))
			{
				//ignore any upper limit
				$datecut = $range_filters['datecut'][0];
			}
			else
			{
				return $results;
			}

			$marking_join = '';
			$lastentry_where = "blog.dateline >= $datecut";
		}

		$contenttypeid = vB_Types::instance()->getContentTypeID('vBBlog_BlogEntry');
		$entries = $db->query_read_slave($q = "
			SELECT blog.blogid
			FROM " . TABLE_PREFIX . "blog AS blog
			$marking_join
			$sort_join
			WHERE 
				$lastentry_where
			ORDER BY $orderby
			LIMIT " . intval($vbulletin->options['maxresults'])
		);
		
		while ($entry = $db->fetch_array($entries))
		{
			$results[] = array($contenttypeid, $entry['blogid'], $entry['blogid']);
		}
	
		return $results;
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/
