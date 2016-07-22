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

abstract class vB_ActivityStream_View_Perm_Calendar_Base extends vB_ActivityStream_View_Perm_Base
{
	protected function fetchCanViewCalendarEvent($eventid)
	{
		if (!($eventinfo = $this->content['event'][$eventid]))
		{
			return false;
		}

		if (!vB::$vbulletin->userinfo['calendarpermissions'])
		{
			cache_calendar_permissions(vB::$vbulletin->userinfo);
		}

		if (
			$eventinfo['userid'] != vB::$vbulletin->userinfo['userid']
				AND
			!(vB::$vbulletin->userinfo['calendarpermissions']["$eventinfo[calendarid]"] & vB::$vbulletin->bf_ugp_calendarpermissions['canviewothersevent'])
		)
		{
			return false;
		}

		return $this->fetchCanViewCalendar($eventinfo['calendarid']);
	}

	protected function fetchCanViewCalendar($calendarid)
	{
		if (!($calendarinfo = $this->content['calendar'][$calendarid]))
		{
			return false;
		}

		if (!vB::$vbulletin->userinfo['calendarpermissions'])
		{
			cache_calendar_permissions(vB::$vbulletin->userinfo);
		}
		if (!(vB::$vbulletin->userinfo['calendarpermissions'][$calendarid] & vB::$vbulletin->bf_ugp_calendarpermissions['canviewcalendar']))
		{
			return false;
		}

		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 57655 $
|| ####################################################################
\*======================================================================*/