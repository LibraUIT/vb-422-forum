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
class vB_ActivityStream_View_Home extends vB_ActivityStream_View
{
	/*
	 * Process the activity stream home page as well as handle ajax requests for further pages
	 *
	 */
	public function process()
	{
		global $show;

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'pagenumber'  => TYPE_UINT,
			'sortby'      => TYPE_NOHTML,
			'time'        => TYPE_NOHTML,
			'show'        => TYPE_NOHTML,
			'ajax'        => TYPE_BOOL,
			'mindateline' => TYPE_UNIXTIME,
			'maxdateline' => TYPE_UNIXTIME,
			'minscore'    => TYPE_NUM,
			'minid'       => TYPE_STR,
			'maxid'       => TYPE_STR,
		));

		$selected = array();
		$filters = array();

		$activitybits = '';

		/* I did not have time to make the filter options more dynamic. I wanted to base the presented filter options on the unqiue section contents of the
		 * activity stream datastore.  You will have to use the provided hooks to get your filter items in.
		 */

		$show['as_blog'] = (vB::$vbulletin->products['vbblog']);
		$show['as_cms'] = (vB::$vbulletin->products['vbcms']);
		$show['as_socialgroup'] = (
			vB::$vbulletin->options['socnet'] & vB::$vbulletin->bf_misc_socnet['enable_groups']
				AND
			vB::$vbulletin->userinfo['permissions']['socialgrouppermissions'] & vB::$vbulletin->bf_ugp_socialgrouppermissions['canviewgroups']
		);

		switch(vB::$vbulletin->GPC['sortby'])
		{
			case 'popular':
				$filters['sortby'] = $this->vbphrase['popular'];
				$this->orderby = 'score DESC, dateline DESC';
				break;
			default: // recent
				vB::$vbulletin->GPC['sortby'] = 'recent';
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
		}

		switch (vB::$vbulletin->GPC['show'])
		{
			case 'photos':
				if (vB::$vbulletin->GPC['sortby'] != 'popular')
				{
					if (!defined('VB_API') OR VB_API !== true)
					{
						$this->setGroupBy('date');
					}
				}
				$this->setWhereFilter('type', 'photo');
				$filters['show'] = $this->vbphrase['photos'];
				break;
			case 'forum':
				$this->setWhereFilter('section', 'forum');
				$filters['show'] = $this->vbphrase['forums'];
				break;
			case 'cms':
				if ($show['as_cms'])
				{
					$this->setWhereFilter('section', 'cms');
					$filters['show'] = $this->vbphrase['articles'];
				}
				else
				{
					vB::$vbulletin->GPC['show'] = 'all';
				}
				break;
			case 'blog':
				if ($show['as_blog'])
				{
					$this->setWhereFilter('section', 'blog');
					$filters['show'] = $this->vbphrase['blogs'];
				}
				else
				{
					vB::$vbulletin->GPC['show'] = 'all';
				}
				break;
			case 'socialgroup':
				$this->setWhereFilter('section', 'socialgroup');
				$filters['show'] = $this->vbphrase['social_groups'];
				break;
			default: // all
				vB::$vbulletin->GPC['show'] = 'all';
		}

		switch(vB::$vbulletin->GPC['time'])
		{
			case 'today':
				$this->setWhereFilter('maxdateline', TIMENOW - 24 * 60 * 60);
				$filters['time'] = $this->vbphrase['last_24_hours'];
				break;
			case 'week':
				$this->setWhereFilter('maxdateline', TIMENOW - 7 * 24 * 60 * 60);
				$filters['time'] = $this->vbphrase['last_7_days'];
				break;
			case 'month':
				$this->setWhereFilter('maxdateline', TIMENOW - 30 * 24 * 60 *60);
				$filters['time'] = $this->vbphrase['last_30_days'];
				break;
			default: // anytime
				vB::$vbulletin->GPC['time'] = 'anytime';
		}

		$selected = array(
			vB::$vbulletin->GPC['time']   => ' class="selected" ',
			vB::$vbulletin->GPC['show']   => ' class="selected" ',
			vB::$vbulletin->GPC['sortby'] => ' class="selected" ',
		);

		$unselected = array(
			'popular'     => ' class="unselected" ',
			'recent'      => ' class="unselected" ',
			'anytime'     => ' class="unselected" ',
			'today'       => ' class="unselected" ',
			'week'        => ' class="unselected" ',
			'month'       => ' class="unselected" ',
			'all'         => ' class="unselected" ',
			'photos'      => ' class="unselected" ',
			'forum'       => ' class="unselected" ',
			'cms'         => ' class="unselected" ',
			'blog'        => ' class="unselected" ',
			'socialgroup' => ' class="unselected" ',
			'on'          => ' class="unselected" ',
			'off'         => ' class="unselected" ',
		);

		$unselected = array_diff_key($unselected, $selected);

		($hook = vBulletinHook::fetch_hook($this->hook_beforefetch)) ? eval($hook) : false;

		$arguments = array(
			'sortby' => array(
				'show=' . vB::$vbulletin->GPC['show'],
				'time=' . vB::$vbulletin->GPC['time'],
			),
			'time'   => array(
				'show=' . vB::$vbulletin->GPC['show'],
				'sortby=' . vB::$vbulletin->GPC['sortby'],
			),
			'show'   => array(
				'time=' . vB::$vbulletin->GPC['time'],
				'sortby=' . vB::$vbulletin->GPC['sortby'],
			)
		);

		foreach ($arguments AS $key => $values)
		{
			$arguments[$key] = implode("&amp;", $values);
		}

		$filter = array();
		foreach ($filters AS $type => $string)
		{
			$filter[] = array(
				'phrase'    => $string,
				'arguments' => $arguments[$type]
			);
		}
		$show['filterbar'] = !empty($filter);

		if (!vB::$vbulletin->GPC['pagenumber'])
		{
			vB::$vbulletin->GPC['pagenumber'] = 1;
		}

		$moreactivity = array(
			'type' => vB::$vbulletin->GPC['type'],
			'page' => vB::$vbulletin->GPC['pagenumber'] + 1,
		);

		$this->setPage(vB::$vbulletin->GPC['pagenumber'], vB::$vbulletin->options['as_perpage']);

		if (vB::$vbulletin->GPC['ajax'])
		{
			$this->processExclusions(vB::$vbulletin->GPC['sortby']);
			$result = $this->fetchStream(vB::$vbulletin->GPC['sortby']);
			$this->processAjax($result);
		}
		else
		{
			$result = $this->fetchStream(vB::$vbulletin->GPC['sortby']);
			$actdata = array(
				'mindateline' => $result['mindateline'],
				'maxdateline' => $result['maxdateline'],
				'minscore'    => $result['minscore'],
				'minid'       => $result['minid'],
				'maxid'       => $result['maxid'],
				'count'       => $result['count'],
				'totalcount'  => $result['totalcount'],
				'perpage'     => $result['perpage'],
				'time'        => vB::$vbulletin->GPC['time'],
				'show'        => vB::$vbulletin->GPC['show'],
				'sortby'      => vB::$vbulletin->GPC['sortby'],
				'refresh'     => $this->refresh,
			);

			$show['noactivity'] = false;
			$show['nomoreresults'] = false;
			$show['moreactivity'] = false;
			if ($result['totalcount'] == 0)
			{
				$show['noactivity'] = true;
			}
			else if ($result['totalcount'] < $result['perpage'])
			{
				$show['nomoreresults'] = true;
			}
			else
			{
				$show['moreactivity'] = true;
			}

			foreach ($result['bits'] AS $bit)
			{
				$activitybits .= $bit;
			}

			$navbits = construct_navbits(array(
				vB::$vbulletin->options['forumhome'] . '.php?' . vB::$vbulletin->session->vars['sessionurl']=> $this->vbphrase['home'],
				'' => $this->vbphrase['activity_stream']
			));
			$navbar = render_navbar_template($navbits);

			$templater = vB_Template::create('activitystream_home');
				$templater->register_page_templates();
				$templater->register('selected', $selected);
				$templater->register('unselected', $unselected);
				$templater->register('activitybits', $activitybits);
				$templater->register('arguments', $arguments);
				$templater->register('filter', $filter);
				$templater->register('actdata', $actdata);
				$templater->register('navbar', $navbar);
				$templater->register('template_hook', $template_hook);
			print_output($templater->render());
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 57655 $
|| ####################################################################
\*======================================================================*/
