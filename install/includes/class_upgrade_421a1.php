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
/*
if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}
*/

class vB_Upgrade_421a1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '421a1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.2.1 Alpha 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.2.0';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '';

	/**
	 * Step #1 - Sync navigation url value
	 *
	 */
	function step_1()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'navigation', 1, 1),
				"ALTER TABLE " . TABLE_PREFIX . "navigation CHANGE url url VARCHAR(255) NOT NULL DEFAULT ''"
		);
	}

	/**
	 * Step #2
	 *
	 */
	function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'template', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "template CHANGE mergestatus mergestatus ENUM('none', 'merged', 'conflicted', 'ignored') NOT NULL DEFAULT 'none'"
		);
	}

	/*
	 * Step 3 - Add field to track titles that have been converted
	 * this ensures that no field gets double encoded if the upgrade is executed multiple times
	 */
	function step_3()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'event', 1, 1),
			'event',
			'title_encoded',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/*
	 * Step 4 - encode event titles
	 */
	function step_4($data = null)
	{
		$process = 1000;
		$startat = intval($data['startat']);

		if ($startat == 0)
		{
			$events = $this->db->query_first_slave("
				SELECT COUNT(*) AS events
				FROM " . TABLE_PREFIX . "event
				WHERE title_encoded = 0
			");

			$total = $events['events'];

			if ($total)
			{
				$this->show_message(sprintf($this->phrase['version']['421a1']['processing_event_titles'], $total));
				return array('startat' => 1);
			}
			else
			{
				$this->skip_message();
				return;
			}
		}
		else
		{
			$first = $startat - 1;
		}

		$events = $this->db->query_read_slave("
			SELECT title, eventid
			FROM " . TABLE_PREFIX . "event
			WHERE title_encoded = 0
			LIMIT $first, $process
		");

		$rows = $this->db->num_rows($events);

		if ($rows)
		{
			while ($event = $this->db->fetch_array($events))
			{
				$newtitle = htmlspecialchars_uni($event['title']);

				$this->db->query_write("
					UPDATE " . TABLE_PREFIX . "event
					SET
						title = '" . $this->db->escape_string($newtitle) . "',
						title_encoded = 1
					WHERE
						eventid = {$event['eventid']}
							AND
						title_encoded = 0
				");

			}

			$this->db->free_result($events);
			$this->show_message(sprintf($this->phrase['version']['421a1']['updated_event_titles'], $first + $rows));

			return array('startat' => $startat + $process);
		}
		else
		{
			$this->show_message($this->phrase['version']['421a1']['updated_event_titles_complete']);
		}
	}

	/*
	 * Step 5 - change default on title_encoded to 1 so any events added after this upgrade
	 * won't get double encoded if the upgrade is executed again
	 *
	 */
	function step_5()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'event', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "event CHANGE title_encoded title_encoded SMALLINT NOT NULL DEFAULT '1'"
		);
	}

	/*
	 * Step 6
	 *
	 */
	function step_6()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'navigation', 1, 1),
			'navigation',
			'menuid',
			'varchar',
			array('length' => 20, 'attributes' => self::FIELD_DEFAULTS)
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/