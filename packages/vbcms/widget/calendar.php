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
 * Test Widget Controller
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 77606 $
 * @since $Date: 2013-09-17 17:07:09 -0700 (Tue, 17 Sep 2013) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Widget_Calendar extends vBCms_Widget
{
	/*Properties====================================================================*/

	/**
	 * A package identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this widget.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	/**
	 * A class identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this widget.
	 *
	 * @var string
	 */
	protected $class = 'Calendar';


	/*Render========================================================================*/

	/**
	 * Returns the config view for the widget.
	 *
	 * @return vBCms_View_Widget				- The view result
	 */
	public function getConfigView()
	{
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');
		$this->assertWidget();

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do' => vB_Input::TYPE_STR,
			'template_name' => vB_Input::TYPE_STR
		));

		$view = new vB_View_AJAXHTML('cms_widget_config');
		$view->title = new vB_Phrase('vbcms', 'configuring_widget_x', $this->widget->getTitle());
		$config = $this->widget->getConfig();

		if ((vB::$vbulletin->GPC['do'] == 'config') AND $this->verifyPostId())
		{

			if (vB::$vbulletin->GPC_exists['template_name'])
			{
				$config['template_name'] = vB::$vbulletin->GPC['template_name'];
			}

			$widgetdm = $this->widget->getDM();
			$widgetdm->set('config', $config);

			$widgetdm->save();

			if (!$widgetdm->hasErrors())
			{
				if ($this->content)
				{
					$segments = array('node' => $this->content->getNodeURLSegment(),
										'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'EditPage'));
					$view->setUrl(vB_View_AJAXHTML::URL_FINISHED, vBCms_Route_Content::getURL($segments));
				}

				$view->setStatus(vB_View_AJAXHTML::STATUS_FINISHED, new vB_Phrase('vbcms', 'configuration_saved'));
			}
			else
			{
				if (vB::$vbulletin->debug)
				{
					$view->addErrors($widgetdm->getErrors());
				}

				// only send a message
				$view->setStatus(vB_View_AJAXHTML::STATUS_MESSAGE, new vB_Phrase('vbcms', 'configuration_failed'));
			}
		}
		else
		{
			// add the config content
			$configview = $this->createView('config');

			if (!isset($config['template_name']) OR ($config['template_name'] == '') )
			{
				$config['template_name'] = 'vbcms_widget_calendar_page';
			}

			$configview->template_name = $config['template_name'];
			// item id to ensure form is submitted to us
			$this->addPostId($configview);

			$view->setContent($configview);


			// send the view
			$view->setStatus(vB_View_AJAXHTML::STATUS_VIEW, new vB_Phrase('vbcms', 'configuring_widget'));
		}

		return $view;
	}


	/**
	 * Fetches the standard page view for a widget.
	 *
	 * @return vBCms_View_Widget				- The resolved view, or array of views
	 */
	public function getPageView()
	{
		global $vbphrase;

		$this->assertWidget();
		//get the template to be used.
		$config = $this->widget->getConfig();

		if (!isset($config['template_name']) OR ($config['template_name'] == '') )
		{
			$config['template_name'] = 'vbcms_widget_calendar_page';
		}
		$view = new vBCms_View_Widget($config['template_name']);

		$view->calendar_table = self::getCalendar(false, false);
		$view->widget_title = $this->widget->getTitle();
		$view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();

		return $view;
	}

	/*** Gets the list of published articles in the select month
	***/
	private static function getPublished($year, $month)
	{
		//Getting the start date is easy. Getting the end date is a bit complex. Leap years and all that.
		//Easiest way is to get the start of the next month and subract a second.
		//Ensure permissions are loaded

		$hash = self::getMyHash($year, $month);

		if (!($articles = vB_Cache::instance()->read($hash, true, false)))
		{
			$offset = vBCms_ContentManager::getTimeOffset(vB::$vbulletin->userinfo);
			$start = gmmktime (0, 0, 0, $month, 1, $year);
			$weekday = gmdate('w', $start);
			$start -= $offset;
			$end = gmmktime (0, 0, 0, ($month == 12 ? 1 : $month + 1 ), 1, ($month == 12 ? $year + 1 : $year )) - $offset - 1;
			$articles = array();
			$rst = vB::$vbulletin->db->query_read($sql = "SELECT node.nodeid, node.publishdate, node.setpublish FROM " .
			TABLE_PREFIX . "cms_node AS node INNER JOIN "  . TABLE_PREFIX . "cms_nodeinfo AS info
			ON info.nodeid = node.nodeid WHERE node.setpublish > 0 AND node.publishdate BETWEEN $start AND $end
			AND node.contenttypeid <> " . vB_Types::instance()->getContentTypeID("vBCms_Section") .
			" AND " . vBCMS_Permissions::getPermissionString() .  " AND hidden = 0
			ORDER BY node.publishdate LIMIT 5000" );

			$nextday = $start + 86400;
			$dom = 1;
			$articles[1] = array('data' => array(), 'time' => $start + 1, 'wday' => $weekday);
			//Now we want to end with an array of day => array('data ' => array, 'time' => unixtime)
			//So we need to build the array as we go.

			while($record = vB::$vbulletin->db->fetch_array($rst))
			{
				//see if we need to advance to a new date
				if (intval($record['publishdate']) > $nextday)
				{
					while (intval($record['publishdate']) > $nextday)
					{
						$nextday += 86400;
						$start += 86400;
						$dom ++;
						$weekday = ($weekday == 6 ? 0 : ($weekday + 1));
						$articles[$dom] = array('data' => array(), 'time' => $start + 1, 'wday' => $weekday);
					}

				}

				if ($record['setpublish'])
				{
					$articles[$dom]['data'][] = $record;
				}
			}

			//we may have some days at the end without articles.
			while($end > $start + 86400 )
			{
				$dom++;
				$weekday = ($weekday == 6 ? 0 : ($weekday + 1));
				$articles[$dom] = array('data' => array(), 'time' => $start + 1, 'wday' => $weekday);
				$start += 86400;
			}

			vB_Cache::instance()->write($hash ,
				$articles, 1440, array('cms_calendar_published', 'sections_updated'));
		}

		//Now we want to turn this into an array of week=>(array(1-7);
		$week = 1;
		$calendar = array(1 => array());

		//Pad the start with empty records as needed
		if ($articles[1]['wday'] != 0)
		{
			for ($i = 0; $i < $articles[1]['wday']; $i++)
			{
				$calendar[1][$i] = array('count' => 0, 'url' => '', 'day' => '');
			}

		}
		$monthday = 1;
		$route = new vBCms_Route_List;
		while($monthday <= count($articles))
		{
			//If we've filled a week, we need to advance
			$count = 0;
			foreach ($articles[$monthday]['data'] as $record)
			{
				$count = 1;
				$url = $route->getCurrentUrl(array('type' =>'day', 'value' => $articles[$monthday]['time'])) ;
				break;
			}

			$calendar[$week][$articles[$monthday]['wday']] = array('count' => $count,
			'url' => $url,
			'day' =>($monthday ? $monthday : '') );

			if (($articles[$monthday]['wday'] == 6) AND ($monthday < count($articles)))
			{
				$week++;
				$calendar[$week] = array();
			}
			$monthday++;
		}


		//We need to fill out a full week. Note that monthday is now one past the last day of the month
		if ($articles[$monthday - 1]['wday'] < 6)
		{
			for ($i = $articles[$monthday - 1]['wday'] + 1; $i <= 6 ; $i++)
			$calendar[$week][$i] = array('count' => 0,
				'url' => '', 'day' => '');
		}
		unset($route);
		return $calendar;
	}

	/*** Generates the calendar for the select month
	 ***/
	public static function getCalendar($year, $month)
	{
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('vbcms');
		global $vbphrase;
		// Create view
		$view = new vB_View('vbcms_widget_calendar_table');

		if (!$year OR !$month)
		{
			$today = getdate(TIMENOW);
			$year = $today['year'];
			$month = $today['mon'];
		}

		//Let's get the text representation of the month.
		$view->textmonth =  $vbphrase[strtolower(date('F', gmmktime(1, 1, 1, $month, 15, $year)))];
		$view->weeks = self::getPublished($year, $month);
		$view->year = $year;
		$view->month = $month;
		$prevyear = ($month == 1 ? $year - 1 : $year);
		$prevmonth = ($month == 1 ? 12 : $month - 1);
		$nextyear = ($month == 12 ? $year + 1 : $year);
		$nextmonth = ($month == 12 ? 1 : $month + 1);

		//Get the links to next and previous months
		$view->prev_month_link = "ajax.php?do=calwidget&amp;month=$prevmonth&amp;year=$prevyear" ;
		$view->next_month_link = "ajax.php?do=calwidget&amp;month=$nextmonth&amp;year=$nextyear";


		return $view;
	}


	/**** returns a hash string that allows us to cache the calendar for this user.
	* It has to be per-user because each user has different access rights and can see
	* content they created but otherwise could not see.
	*
	* @param int
	* @param int
	*
	* @return string
	****/
	private static function getMyHash($year, $month)
	{
		$context = new vB_Context('widget_calendar' ,
		array(
			'year' => $year, 'month' =>$month, 'userid' =>vB::$vbulletin->userinfo['userid']
			)
		);
		return strval($context);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77606 $
|| ####################################################################
\*======================================================================*/
