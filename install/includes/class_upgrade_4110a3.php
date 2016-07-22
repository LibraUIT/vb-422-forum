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

class vB_Upgrade_4110a3 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '4110a3';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.10 Alpha 3';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.10 Alpha 2';

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
	
	/*
	  Step 1 - VBIV-13517 Stylevar Mapping, Part 2.
	*/
	function step_1()
	{
		require_once(DIR . '/includes/class_stylevar_mapper.php');

		$SVM = new SV_Mapping($this->registry);

		// Mappings
		$SVM->add_sv_mapping('navbar_tab_size.units','navbar_menu_height.units');
		$SVM->add_sv_mapping('navbar_tab_size.height','navbar_menu_height.size');

		// Process Mappings 
		if ($SVM->sv_load() AND $SVM->process())
		{
			$this->show_message($this->phrase['core']['sv_mappings']);
			$SVM->process_results();
		}
		else
		{
			$this->skip_message();
		}
	}	
	
	/*
	  Step 2 - Separate [VIDEO] permissions from [IMG]
	*/
	function step_2()
	{	
		// Insert settings records so that they we can set their default to the corresponding img setting
		// build_options() is executed after each version step of the upgrade so these will be processed before the final settings import
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "setting"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "setting
				(varname, value, grouptitle, defaultvalue, optioncode, displayorder, volatile, datatype, product)
			VALUES
				('unallowvideo', {$this->registry->options['unallowimg']}, 'usernote', '1', 'yesno', 35, 1, 'boolean', 'vbulletin'),
				('allowbbvideocode', {$this->registry->options['allowbbimagecode']}, 'bbcode', '1', 'yesno', 145, 1, 'boolean', 'vbulletin'),
				('privallowbbvideocode', {$this->registry->options['privallowbbimagecode']}, 'pm', '1', 'yesno', 145, 1, 'boolean', 'vbulletin')
		");	
	}
	
	/*
	 * Add field for signature max videos
	 */
	function step_3()
	{
		if (!$this->field_exists('usergroup', 'sigmaxvideos'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 1),
				'usergroup',
				'sigmaxvideos',
				'smallint',
				self::FIELD_DEFAULTS
			);	
			
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
				"UPDATE " . TABLE_PREFIX . "usergroup
				SET sigmaxvideos = 1
				WHERE
					sigmaximages > 0
			");
		}
		else
		{
			$this->skip_message();
		}
	}
	
	/*
	 * Add notice for video / img check split
	 */
	function step_4()
	{	
		$this->add_adminmessage(
			'after_upgrade_4110_video',
			array(
				'dismissible' => 1,
				'script'      => '',
				'action'      => '',
				'execurl'     => '',
				'method'      => '',
				'status'      => 'undone',
			)
		);
	}

	/*
	 * VBIV-5472 : Convert old encoded filenames
	 */
	function step_5($data = null)
	{	
		$process = 1000;
		$startat = intval($data['startat']);
		
		if ($startat == 0)
		{
			$attachments = $this->db->query_first_slave("
				SELECT COUNT(*) AS attachments
				FROM " . TABLE_PREFIX . "attachment
			");

			$total = $attachments['attachments'];

			if ($total)
			{
				$this->show_message(sprintf($this->phrase['version']['4110a3']['processing_filenames'],$total));
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
		
		$attachments = $this->db->query_read_slave("
			SELECT filename, attachmentid
			FROM " . TABLE_PREFIX . "attachment
			LIMIT $first, $process
		");

		$rows = $this->db->num_rows($attachments);

		if ($rows)
		{
			while ($attachment = $this->db->fetch_array($attachments))
			{
				$aid = $attachment['attachmentid'];
				$filename = $attachment['filename'];
				$newfilename = $this->db->escape_string(html_entity_decode($filename, ENT_QUOTES));

				if ($filename != $newfilename)
				{
					$this->db->query_write("
						UPDATE " . TABLE_PREFIX . "attachment
						SET filename = '$newfilename' 
						WHERE attachmentid = $aid
					");
				}
			}

			$this->db->free_result($attachments);
			$this->show_message(sprintf($this->phrase['version']['4110a3']['updated_attachments'],$first + $rows));

			return array('startat' => $startat + $process);
		}
		else
		{
			$this->show_message($this->phrase['version']['4110a3']['updated_attachments_complete']);
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
