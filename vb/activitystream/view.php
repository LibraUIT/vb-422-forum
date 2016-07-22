<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 4.2.2 - Nulled By VietVBB Team
  || # ---------------------------------------------------------------- # ||
  || # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

/**
 * Class to view the activity stream
 *
 * @package	vBulletin
 * @version	$Revision: 57655 $
 * @date		$Date: 2012-01-09 12:08:39 -0800 (Mon, 09 Jan 2012) $
 */
class vB_ActivityStream_View
{
	/* Group contentids
	 *
	 * 	forum, post - collect postid and threadid, add threadid to next:
	 * 	forum, thread - collect threadid and forumid
	 * 	(and poll)
	 *
	 * 	cms, article
	 * 	cms, comment
	 *
	 * 	album, album
	 * 	album, photo
	 * 	album, comment
	 *
	 * 	blog, entry
	 * 	blog, comment
	 *
	 * 	socialgroup, discussion
	 * 	socialgroup, groupmessage
	 * 	socialgroup, group
	 * 	socialgroup, photo
	 * 	socialgroup, photocomment
	 *
	 * @param	array	Activity info
	 *
	 * @return	string	Class Name
	 */

	/**
	 * Hook for constructor.
	 *
	 * @var string
	 */
	protected $hook_start = 'activity_view_start';

	/**
	 * Hook into each class before group
	 *
	 * @var string
	 */
	protected $hook_group = 'activity_view_group';

	/**
	 * Hook into the UNION query
	 *
	 * @var string
	 */
	protected $hook_union = 'activity_view_union_sql';

	/**
	 * Hook into before fetch
	 *
	 * @var string
	 */
	protected $hook_beforefetch = 'activity_view_beforefetch';

	/**
	 * SQL WHERE conditions
	 *
	 * @var string
	 */
	protected $wheresql = array(
		'stream.dateline <> 0'
	);

	/*
	 * Flip the results of the query before processing
	 *
	 * @var	bool
	 */
	protected $getnew = false;

	/**
	 * SQL LIMIT conditions
	 *
	 * @var string
	 */
	protected $limitsql = '';

	/**
	 * SQL Order By
	 *
	 * @var string
	 */
	protected $orderby = 'dateline DESC';

	/**
	 * Perpage
	 *
	 * @var int
	 */
	protected $perpage = 30;

	/**
	 * Ajax Refresh rate
	 *
	 * @var int
	 */
	protected $refresh = 1;

	/**
	 * Grouping array for content
	 *
	 * @var string
	 */
	protected $content = array();

	/**
	 * Group By
	 *
	 * @var string
	 */
	protected $groupBy = '';

	/**
	 * List of classes used by this stream instance
	 *
	 * @var array
	 */
	protected $classes = array();

	/*
	 * Retrieve subscriptions, a different query from the other filters
	 *
	 */
	protected $setSubscriptions = false;

	/**
	 *
	 */
	protected $setFilters = array();

	/*
	 * vbphrase
	 */
	protected $vbphrase = null;

	/*
	 * Allow viewing of Friends
	 */
	protected $fetchFriends = true;

	/**
	 * Constructor - set Options
	 *
	 */
	public function __construct(&$vbphrase)
	{
		$this->refresh = intval(vB::$vbulletin->options['as_refresh']);
		if (!$this->refresh)
		{
			$this->refresh = 1;
		}
		$this->vbphrase =& $vbphrase;
		$this->wheresql[] =  "stream.dateline <= " . TIMENOW;

		($hook = vBulletinHook::fetch_hook($this->hook_start)) ? eval($hook) : false;
	}

	/*
	 * Set Where Filter
	 *
	 */
	public function setWhereFilter($filtertype, $value = null, $argument = 0)
	{
		$this->setFilters[$filtertype] = 1;
		switch ($filtertype)
		{
			case 'ignoredusers':
				require_once(DIR . '/includes/functions_bigthree.php');
				$coventry = fetch_coventry();
				$ignorelist = array();
				if (trim(vB::$vbulletin->userinfo['ignorelist']))
				{
					$ignorelist = preg_split('/( )+/', trim(vB::$vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
				}
				if ($ignored = array_merge($coventry, $ignorelist))
				{
					$this->wheresql[] = "stream.userid NOT IN (" . implode(",", $ignored) . ")";
				}
				break;
			case 'minscore':
				if (!$value) { return; }
				$this->wheresql[] = "stream.score <= $value";
				break;
			case 'mindateline':
				if (!$value) { return; }
				$this->wheresql[] = "stream.dateline <= " . intval($value);
				break;
			case 'maxdateline':
				if (!$value) { return; }
				/* Don't put >= here */
				$this->wheresql[] = "stream.dateline > " . intval($value);
				break;
			case 'excludeid':
				if (!$value) { return; }
				$ids = explode(',', $value);
				$ids = array_map('intval', $ids);
				if ($ids)
				{
					$this->wheresql[] = "stream.activitystreamid NOT IN (" . implode(',', $ids) . ")";
				}
				break;
			case 'userid':
				if (!$value) { return; }
				if (!is_array($value))
				{
					$value = array($value);
				}
				$value = array_map('intval', $value);

				$this->wheresql[] = "stream.userid IN (" . implode(",", $value) . ")";
				break;
			case 'type':	// this only supports photo ..
				if (!$value) { return; }
				if ($photos = vB::$vbulletin->activitystream['photo'])
				{
					$this->wheresql[] = "stream.typeid IN (" . implode(", ", $photos) . ")";
				}
				else
				{
					$this->wheresql[] = "stream.typeid = 0";
				}
				break;
			case 'section':
				if (!$value) { return; }
				if ($sections = vB::$vbulletin->activitystream['enabled'][$value])
				{
					$this->wheresql[] = "stream.typeid IN (" . implode(", ", $sections) . ")";
				}
				else
				{
					$this->wheresql[] = "stream.typeid = 0";
				}
				break;
			case 'friends':
				if (!$value) { return; }
				if ($this->fetchFriends AND vB::$vbulletin->options['socnet'] & vB::$vbulletin->bf_misc_socnet['enable_friends'] AND $friends = $this->fetchFriends($value))
				{
					if (!$friends)
					{
						$this->wheresql[] = "stream.userid = 0";
						return false;
					}
					else
					{
						$this->wheresql[] = "stream.userid IN (" . implode(",", $friends) . ")";
						return true;
					}
				}
				else
				{
					$this->wheresql[] = "stream.userid = 0";
					return false;
				}
				break;
			case 'all':
				if (!$value) { return; }
				if (!is_array($value))
				{
					$value = array($value);
				}
				$value = array_map('intval', $value);

				if ($this->fetchFriends AND vB::$vbulletin->options['socnet'] & vB::$vbulletin->bf_misc_socnet['enable_friends'])
				{
					$friends = $this->fetchFriends($value);
					$value = array_merge($value, $friends);
				}

				$this->wheresql[] = "stream.userid IN (" . implode(",", $value) . ")";
				break;
		}
	}

	protected function fetchFriends($userid)
	{
		if (!is_array($userid))
		{
			$userid = array($userid);
		}

		static $result = array();
		if ($result)
		{
			return $result;
		}

		$userids = array_map('intval', $userid);
		$result = array();
		$friends = vB::$db->query("
			SELECT relationid
			FROM " . TABLE_PREFIX . "userlist
			WHERE
				userid IN (" . implode(",", $userids) . ")
					AND
				type = 'buddy'
					AND
				friend = 'yes'
		");
		while($friend = vB::$db->fetch_array($friends))
		{
			$result[] = $friend['relationid'];
		}

		return $result;
	}

	/*
	 * Set Pagenumber & perpage (More ....)
	 *
	 */
	public function setPage($pagenumber, $perpage = 30)
	{
		$pagenumber = intval($pagenumber);
		if (!$pagenumber)
		{
			$pagenumber = 1;
		}
		$perpage = intval($perpage);
		if (!$perpage OR $perpage > 150)
		{
			$perpage = 150;
		}
		$startat = $perpage * ($pagenumber - 1);

		if ($this->getnew)
		{
			$this->limitsql = "LIMIT 0, 200";
		}
		else
		{
			$this->limitsql = "LIMIT {$startat}, {$perpage}";
		}
		$this->perpage = $perpage;
	}

	/*
	 * Set Group by .. only works for date / photos
	 *
	 */
	public function setGroupBy($method)
	{
		$this->groupBy = $method;
	}

	/*
	 * Fetch subscribed item activity - only executed for viewing logged in user
	 *
	 * - Subscribed Threads (Posts)
	 * - Subscribed Groups (Discussions and Photos)
	 * - Subscribed Discussions (replies)
	 * - Subscribed Blog Entries (Comments)
	 * - Subscribed Blog Users (Entries)
	 * - Subscribed Events (unsupported at present)
	 * - Subscribed Forums (unsupported at present)
	 */
	protected function fetchSubscribeUnionSql()
	{
		$sqlbits = array();

		if (vB::$vbulletin->activitystream['forum_post']['enabled'])
		{
			$sqlbits[] = "
				### Threads ###
				SELECT stream.*, type.section, type.type
				FROM " . TABLE_PREFIX . "activitystream AS stream
				INNER JOIN " . TABLE_PREFIX . "activitystreamtype AS type ON (stream.typeid = type.typeid)
				INNER JOIN " . TABLE_PREFIX . "post AS p ON (p.postid = stream.contentid)
				INNER JOIN " . TABLE_PREFIX . "subscribethread AS st ON (
					p.threadid = st.threadid
						AND
					stream.typeid = " . intval(vB::$vbulletin->activitystream['forum_post']['typeid']) . "
						AND
					st.userid = " . vB::$vbulletin->userinfo['userid'] . "
				)
				" . ($this->wheresql ? "WHERE " . implode(" AND ", $this->wheresql) : "") . "
			";
		}

		/*
		 * Blog specific bits
		 */
		if (vB::$vbulletin->products['vbblog'])
		{
			if (vB::$vbulletin->activitystream['blog_comment']['enabled'])
			{
				$sqlbits[] = "
					### Blog Entries###
					SELECT stream.*, type.section, type.type
					FROM " . TABLE_PREFIX . "activitystream AS stream
					INNER JOIN " . TABLE_PREFIX . "activitystreamtype AS type ON (stream.typeid = type.typeid)
					INNER JOIN " . TABLE_PREFIX . "blog_text AS bt ON (bt.blogtextid = stream.contentid)
					INNER JOIN " . TABLE_PREFIX . "blog_subscribeentry AS se ON (
						bt.blogid = se.blogid
							AND
						stream.typeid = " . intval(vB::$vbulletin->activitystream['blog_comment']['typeid']) . "
							AND
						se.userid = " . vB::$vbulletin->userinfo['userid'] . "
					)
					" . ($this->wheresql ? "WHERE " . implode(" AND ", $this->wheresql) : "") . "
				";
			}

			/*
			 * The query below filters out any entries in the blog_subscribeentry table since they
			 * will be populated in the above query
			 */

			if( vB::$vbulletin->activitystream['blog_entry']['enabled'])
			{
				$sqlbits[] = "
					### Blog User###
					SELECT stream.*, type.section, type.type
					FROM " . TABLE_PREFIX . "activitystream AS stream
					INNER JOIN " . TABLE_PREFIX . "activitystreamtype AS type ON (stream.typeid = type.typeid)
					INNER JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = stream.contentid)
					INNER JOIN " . TABLE_PREFIX . "blog_subscribeuser AS su ON (
						blog.userid = su.bloguserid
							AND
						stream.typeid = " . intval(vB::$vbulletin->activitystream['blog_entry']['typeid']) . "
							AND
						su.userid = " . vB::$vbulletin->userinfo['userid'] . "
					)
					" . ($this->wheresql ? "WHERE " . implode(" AND ", $this->wheresql) : "") . "
				";
			}
		}

		/*
		 * Social Group specific bits
		 */
		if (
			(vB::$vbulletin->options['socnet'] & vB::$vbulletin->bf_misc_socnet['enable_groups'])
				AND
			(vB::$vbulletin->userinfo['permissions']['socialgrouppermissions'] & vB::$vbulletin->bf_ugp_socialgrouppermissions['canviewgroups'])
		)
		{
			if (vB::$vbulletin->activitystream['socialgroup_groupmessage']['enabled'])
			{
				$sqlbits[] = "
					### Social Group Messages ###
					SELECT stream.*, type.section, type.type
					FROM " . TABLE_PREFIX . "activitystream AS stream
					INNER JOIN " . TABLE_PREFIX . "activitystreamtype AS type ON (stream.typeid = type.typeid)
					INNER JOIN " . TABLE_PREFIX . "groupmessage AS gm ON (gm.gmid = stream.contentid)
					INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON (
						gm.discussionid = sd.discussionid
							AND
						stream.typeid = " . intval(vB::$vbulletin->activitystream['socialgroup_groupmessage']['typeid']) . "
							AND
						sd.userid = " . vB::$vbulletin->userinfo['userid'] . "
					)
					" . ($this->wheresql ? "WHERE " . implode(" AND ", $this->wheresql) : "") . "
				";
			}

			if (vB::$vbulletin->activitystream['socialgroup_discussion']['enabled'])
			{
				$sqlbits[] = "
					### Social Group Discussions ###
					SELECT stream.*, type.section, type.type
					FROM " . TABLE_PREFIX . "activitystream AS stream
					INNER JOIN " . TABLE_PREFIX . "activitystreamtype AS type ON (stream.typeid = type.typeid)
					INNER JOIN " . TABLE_PREFIX . "discussion AS d ON (d.discussionid = stream.contentid)
					INNER JOIN " . TABLE_PREFIX . "subscribegroup AS sg ON (
						d.groupid = sg.groupid
							AND
						stream.typeid = " . intval(vB::$vbulletin->activitystream['socialgroup_discussion']['typeid']) . "
							AND
						sg.userid = " . vB::$vbulletin->userinfo['userid'] . "
					)
					" . ($this->wheresql ? "WHERE " . implode(" AND ", $this->wheresql) : "") . "
				";
			}

			if (vB::$vbulletin->activitystream['socialgroup_photo']['enabled'])
			{
				$contenttypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroup');
				$sqlbits[] = "
					### Social Group Photos ###
					SELECT stream.*, type.section, type.type
					FROM " . TABLE_PREFIX . "activitystream AS stream
					INNER JOIN " . TABLE_PREFIX . "activitystreamtype AS type ON (stream.typeid = type.typeid)
					INNER JOIN " . TABLE_PREFIX . "attachment AS a ON (a.attachmentid = stream.contentid AND a.contenttypeid = {$contenttypeid})
					INNER JOIN " . TABLE_PREFIX . "subscribegroup AS sg ON (
						a.contentid = sg.groupid
							AND
						stream.typeid = " . intval(vB::$vbulletin->activitystream['socialgroup_photo']['typeid']) . "
							AND
						sg.userid = " . vB::$vbulletin->userinfo['userid'] . "
					)
					" . ($this->wheresql ? "WHERE " . implode(" AND ", $this->wheresql) : "") . "
				";
			}
		}

		($hook = vBulletinHook::fetch_hook($this->hook_union)) ? eval($hook) : false;

		if (!$sqlbits)
		{
			$sqlbits[] = "
				SELECT stream.*
				FROM " . TABLE_PREFIX . "activitystream AS stream
				WHERE stream.activitystreamid = 0
			";
		}

		return vB::$vbulletin->db->query_read_slave("
			(" . implode(") UNION ALL (", $sqlbits) . ")
			ORDER BY dateline DESC
			{$this->limitsql}
		");
	}

	protected function fetchNormalSql()
	{
		/* INNER JOIN here on activystreamtype causes a temporary table .. !??! */
		return vB::$vbulletin->db->query_read_slave("
			SELECT stream.*, type.section, type.type
			FROM " . TABLE_PREFIX . "activitystream AS stream
			LEFT JOIN " . TABLE_PREFIX . "activitystreamtype AS type ON (stream.typeid = type.typeid)
			" . ($this->wheresql ? "WHERE " . implode(" AND ", $this->wheresql) : "") . "
			ORDER BY {$this->orderby}
			{$this->limitsql}
		");
	}

	public function setSubscriptionFilter()
	{
		$this->fetchSubscriptions = true;
	}

	protected function fetchRecords($sort = 'recent')
	{
		if (!$this->limitsql)
		{
			trigger_error('Must call perPage() before fetchStream()', E_USER_ERROR);
		}

		$records = array();
		$requiredExist = array();

		if ($this->fetchSubscriptions)
		{
			$activities = $this->fetchSubscribeUnionSql();
		}
		else
		{
			$activities = $this->fetchNormalSql();
		}

		while ($activity = vB::$vbulletin->db->fetch_array($activities))
		{
			/*
			 * The UNION query can generate duplicate records so assigning by streamid below takes care
			 * of the problem without resorting to DISTINCT in the query
			 */
			$records[] = $activity;
			if (!$activity['typeid'])
			{
				continue;
			}

			$classname = 'vB_ActivityStream_View_Perm_' . ucfirst($activity['section']) . '_' . ucfirst($activity['type']);
			if (!$this->classes[$classname])
			{
				$this->classes[$classname] = new $classname($this->content, $this->vbphrase);
				($hook = vBulletinHook::fetch_hook($this->hook_group)) ? eval($hook) : false;
			}

			$this->classes[$classname]->group($activity);
			$requiredExist = array_merge($requiredExist, $this->classes[$classname]->fetchRequiredExist());
		}

		if ($this->getnew)
		{
			$records = array_reverse($records);
		}

		// Initiate Required classes that don't exist yet
		foreach (array_keys($requiredExist) AS $classname)
		{
			$this->classes[$classname] = new $classname($this->content, $this->vbphrase);
		}

		$done = array();
		$classcount = count($this->classes);
		$count = 0;

		while ($classcount > count($done))
		{
			$count++;
			foreach ($this->classes AS $classname => $class)
			{
				if ($done[$classname])
				{
					continue;
				}

				/*
				 * Check that the required first classes have executed
				 * If not skip process() and reorder the classes
				 * Don't create a circular requirement as that will end badly
				 */
				if (!$class->verifyRequiredFirst($this->classes, $done))
				{
					continue;
				}

				$class->process();
				$done[$classname] = true;
			}
			if (($count + 1) > ($classcount * 2))
			{
				trigger_error('Runaway fetchStream()!', E_USER_ERROR);
			}
		}

		$return = array(
			'total'       => 0,
			'records'     => array(),
			'mindateline' => 0,
			'maxdateline' => 0,
			'minscore'    => 0,
			'minid'       => array(),
			'maxid'       => array(),
		);

		foreach ($records AS $activity)
		{
			$classname = 'vB_ActivityStream_View_Perm_' . ucfirst($activity['section']) . '_' . ucfirst($activity['type']);
			$class = $this->classes[$classname];

			if ($class->fetchCanView($activity))
			{
				$return['records'][] = $activity;
			}
			$return['total']++;

			if (!$return['maxdateline'])
			{
				$return['maxdateline'] = $activity['dateline'];
			}
			if ($return['maxdateline'] == $activity['dateline'])
			{
				$return['maxid'][] = $activity['activitystreamid'];
			}

			if ($sort == 'popular')
			{
				if ($return['minscore'] != $activity['score'])
				{
					$return['minid'] = array();
					$return['minscore'] = $activity['score'];
				}
			}
			else
			{
				if ($return['mindateline'] != $activity['dateline'])
				{
					$return['minid'] = array();
					$return['mindateline'] = $activity['dateline'];
				}
			}

			$return['minid'][] = $activity['activitystreamid'];
		}

		return $return;
	}

	/**
	 * Retrieve Activity Stream
	 *
	 */
	public function fetchStream($sort = 'recent', $fetchphrase = false)
	{
		$this->setWhereFilter('ignoredusers');

		$stop = false;
		$records = array();

		/* Fetch more records when we
		 * A. Have not set a 'maxdateline' filter (future request - new activity since page load)
		 * B. Received less than 50% (valid results) of our perpage value
		 * C. Have not requested more than 3 times already
		 */
		$iteration = 0;
		$totalcount = 0;
		$count = 0;
		$maxdateline = $mindateline = $minscore = 0;
		$maxid = $minid_score = $minid_dateline = array();
		while (!$stop AND $iteration < 4)
		{
			$result = $this->fetchRecords($sort);
			$records = array_merge($records, $result['records']);
			$totalcount += $result['total'];
			$count += count($result['records']);
			$iteration++;

			if (!$maxdateline)
			{
				$maxdateline = $result['maxdateline'];
			}
			if ($maxdateline == $result['maxdateline'])
			{
				$maxid = $result['maxid'];
			}

			if ($sort == 'popular')
			{
				if ($minscore != $result['minscore'])
				{
					$minid = array();
					$minscore = $result['minscore'];
				}
			}
			else
			{
				if ($mindateline != $result['mindateline'])
				{
					$minid = array();
					$mindateline = $result['mindateline'];
				}
			}
			$minid = $result['minid'];

			if ($count / $this->perpage > .5 OR $result['total'] < $this->perpage OR $this->setFilters['maxdateline'])
			{
				$stop = true;
			}
			else
			{
				if ($sort == 'popular')
				{
					$this->setWhereFilter('minscore', $result['minscore']);
				}
				else
				{
					$this->setWhereFilter('mindateline', $result['mindateline']);
				}
				$this->setWhereFilter('excludeid', implode(',', $result['minid']));
			}

			$moreresults = ($result['total'] == $this->perpage) ? 1 : 0;
		}

		$bits = array();
		$groupby = array();
		$count = 0;
		foreach ($records AS $activity)
		{
			$classname = 'vB_ActivityStream_View_Perm_' . ucfirst($activity['section']) . '_' . ucfirst($activity['type']);
			$class = $this->classes[$classname];
			$count++;

			// Call templater!
			if ($this->groupBy)
			{
				switch($this->groupBy)
				{
					case 'date':
					default:
						$foo = vB::$vbulletin->options['yestoday'];
						vB::$vbulletin->options['yestoday'] = 1;
						$date = vbdate(vB::$vbulletin->options['dateformat'], $activity['dateline'], true);
						vB::$vbulletin->options['yestoday'] = $foo;
						$templatename = 'activitystream_' . $activity['type'] . '_' . $this->groupBy . '_bit';
						$groupby[$date] .= $class->fetchTemplate($templatename, $activity, true);
				}
			}
			else
			{
				$templatename = 'activitystream_' . $activity['section'] . '_' . $activity['type'];
				if (!$fetchphrase)
				{
					$bits[] = $class->fetchTemplate($templatename, $activity, $sort == 'popular');
				}
				else
				{
					$bits[] = $class->fetchPhrase($templatename, $activity, $sort == 'popular');
				}
			}
		}

		if ($this->groupBy)
		{
			switch ($this->groupBy)
			{
				case 'date':
				default:
					foreach ($groupby AS $date => $bit)
					{
						$templater = vB_Template::create('activitystream_' . $this->groupBy . '_group');
							$templater->register('activitybits', $bit);
							$templater->register('date', $date);
						$bits[] = $templater->render();
					}
			}
		}

		if (count($minid) == $this->perpage AND vB::$vbulletin->GPC['minid'] AND $ids = explode(',', vB::$vbulletin->GPC['minid']))
		{
			$ids = array_map('intval', $ids);
			$minid = implode(',', array_merge($minid, $ids));
		}
		else
		{
			$minid = implode(',', $minid);
		}

		$return = array(
			'iteration'   => $iteration,
			'totalcount'  => $totalcount,
			'count'       => $count,
			'mindateline' => $mindateline,
			'maxdateline' => $maxdateline,
			'minid'       => $minid,
			'maxid'       => implode(',', $maxid),
			'moreresults' => $moreresults,
			'perpage'     => $this->perpage,
			'bits'        => $bits,
			'minscore'    => $minscore,
			'refresh'     => $this->refresh,
		);

		return $return;
	}

	protected function fetchMemberStreamSql($type, $userid)
	{
		if (vB::$vbulletin->GPC['maxdateline'])
		{
			$this->getnew = true;
			$this->orderby = 'dateline ASC';
		}
		else
		{
			$this->getnew = false;
			$this->orderby = 'dateline DESC';
		}

		switch($type)
		{
			case 'user':
			case 'asuser':
				$this->setWhereFilter('userid', $userid);
				break;
			case 'friends':
			case 'asfriend':
				if (!($this->setWhereFilter('friends', $userid)))
				{
					if (vB::$vbulletin->GPC['ajax'])
					{
						$this->processAjax(false);
					}
				}
				break;
			case 'subs':
			case 'assub':
				$this->setSubscriptionFilter();
				break;
			case 'photos':
			case 'asphoto':
				$this->setGroupBy('date');
				$this->setWhereFilter('userid', $userid);
				$this->setWhereFilter('type', 'photo');
				break;
			case 'all':
			case 'asasll':
			default:
				$type = 'all';
				$this->setWhereFilter('all', $userid);
		}

		return $type;
	}

	protected function processExclusions($sort = 'recent')
	{
		if ($sort == 'popular')
		{
			$this->setWhereFilter('minscore', vB::$vbulletin->GPC['minscore']);
		}
		if (vB::$vbulletin->GPC['mindateline'])
		{
			$this->setWhereFilter('mindateline', vB::$vbulletin->GPC['mindateline']);
		}
		if (vB::$vbulletin->GPC['maxdateline'])
		{
			$this->setWhereFilter('maxdateline', vB::$vbulletin->GPC['maxdateline']);
		}
		if (vB::$vbulletin->GPC['minid'])
		{
			$this->setWhereFilter('excludeid', vB::$vbulletin->GPC['minid']);
		}
		if (vB::$vbulletin->GPC['maxid'])
		{
			$this->setWhereFilter('excludeid', vB::$vbulletin->GPC['maxid']);
		}
	}

	/*
	 * Output an ajax result of fetchStream()
	 *
	 */
	protected function processAjax($result)
	{
		require_once(DIR . '/includes/class_xml.php');
		$xml = new vB_AJAX_XML_Builder(vB::$vbulletin, 'text/xml');

		if (!$result)
		{
			$xml->add_tag('nada', '~~No Results Found~~');
			$xml->print_xml();
		}

		$xml->add_group('results');
		$xml->add_tag('count', $result['count']);
		$xml->add_tag('totalcount', $result['totalcount']);
		$xml->add_tag('minid', $result['minid']);
		$xml->add_tag('maxid', $result['maxid']);
		$xml->add_tag('mindateline', $result['mindateline']);
		$xml->add_tag('maxdateline', $result['maxdateline']);
		$xml->add_tag('minscore', $result['minscore']);
		$xml->add_tag('moreresults', $result['moreresults']);

		if ($result['bits'])
		{
			$xml->add_group('bits');
			foreach($result['bits'] AS $bit)
			{
				$xml->add_tag('bit', $bit);
			}
			$xml->close_group('bits');
		}

		$xml->close_group('results');
		$xml->print_xml();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 57655 $
|| ####################################################################
\*======================================================================*/
