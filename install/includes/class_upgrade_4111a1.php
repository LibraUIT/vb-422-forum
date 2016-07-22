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

class vB_Upgrade_4111a1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '4111a1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.11 Alpha 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.10';

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
	  Step 1 - Drop primary key on stylevardfn
	*/
	function step_1()
	{	
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'stylevardfn', 1, 2),
			"ALTER TABLE " . TABLE_PREFIX . "stylevardfn DROP PRIMARY KEY",
			self::MYSQL_ERROR_DROP_KEY_COLUMN_MISSING
		);		
	}
	
	/*
	  Step 2 - Add primary key that allows stylevardfn per styleid
	*/	
	function step_2()
	{	
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'stylevardfn', 2, 2),
			"ALTER IGNORE TABLE " . TABLE_PREFIX . "stylevardfn ADD PRIMARY KEY (stylevarid, styleid)",
			self::MYSQL_ERROR_PRIMARY_KEY_EXISTS
		);	
	}	
	
	/*
	  Step 3 - Make sure there is no -2 styles in style
	*/	
	function step_3()
	{	
		if ($this->registry->db->query_first("SELECT styleid FROM " . TABLE_PREFIX . "style WHERE styleid = -2"))
		{
			$max = $this->registry->db->query_first("
				SELECT MAX(styleid) AS styleid FROM " . TABLE_PREFIX . "style
			");

			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'style', 1, 2),
				"UPDATE " . TABLE_PREFIX . "style SET
					styleid = " . ($max['styleid'] + 1) . ",
					parentlist = '" . ($max['styleid'] + 1) . ",-1'
				WHERE styleid = -2"
			);	
			
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'style', 2, 2),
				"ALTER IGNORE TABLE  " . TABLE_PREFIX . "style CHANGE styleid styleid INT UNSIGNED NOT NULL AUTO_INCREMENT"
			);	
		}
		else
		{
			$this->skip_message();
		}
	}		
		
	/**
	 * Step #4
	 * Add a true mobile master style
	 */
	function step_4()	
	{
		$this->db->query("
			INSERT INTO " . TABLE_PREFIX . "style
				(title,  parentid, userselect, displayorder, type)
			VALUES
				('" . $this->db->escape_string($this->phrase['install']['default_mobile_style']) . "',
				 -2, 1, 1, 'mobile')
		");
		$styleid = $this->db->insert_id();

		$this->run_query(
			$this->phrase['version']['400b3']['updating_forum_styles'],
			"UPDATE " . TABLE_PREFIX . "style
			SET parentlist = '" . intval($styleid) . ",-2'
			WHERE styleid = " . intval($styleid)
		);	
	}	
	
	/*
	 * Check for an existing basic mobile style, move it to Mobile Master if it is a child of the Master Style
	 * 
	 */
	function step_5()
	{
		if ($this->registry->options['mobilestyleid_basic'] OR $this->registry->options['mobilestyleid_advanced'])
		{
			$styles = array();
			if ($this->registry->options['mobilestyleid_advanced'])
			{
				$styles[] = $this->registry->options['mobilestyleid_advanced'];
			}
			if ($this->registry->options['mobilestyleid_basic'])
			{
				$styles[] = $this->registry->options['mobilestyleid_basic'];
			}
			$styleinfo = $this->registry->db->query_read("
				SELECT styleid
				FROM " . TABLE_PREFIX . "style
				WHERE
					styleid IN (" . implode(",", $styles) . ")
						AND
					type = 'standard'
			");

			while($style = $this->registry->db->fetch_array($styleinfo))
			{
				$this->update_style($style['styleid'], '-2', $style['styleid'] . ',-2');	
			}
			if (!$this->registry->db->num_rows($styleinfo))
			{	// should not get here..
				$this->skip_message();
			}
		}
		else
		{
			$this->skip_message();
		}
	}	
	
	/*
	 * Step 6 - Set Mobile style defaults back to 0. I modifed the code that had issues with these at 0 to
	 * check if they are 0 before checking them against styleid
	 */
	function step_6()
	{		
		$this->run_query(
			sprintf($this->phrase['version']['418b1']['updating_mobile'], 1, 1),
				"UPDATE " . TABLE_PREFIX . "setting
				SET value = 0
				WHERE
					value < 0
						AND
					varname IN('mobilestyleid_basic', 'mobilestyleid_advanced')
		");
	}
	
	/*
	 * Step 7 - Updating the default mime type for bmp images.
	 */
	function step_7()
	{		
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], "attachmenttype"),
			"UPDATE " . TABLE_PREFIX . "attachmenttype
			SET mimetype = '" . $this->db->escape_string(serialize(array('Content-type: image/bmp'))) . "'
			WHERE extension = 'bmp'
		");
	}	
	
	protected function update_style($styleid, $parentid, $parentlist)
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'style', 1, 1),
			"UPDATE " . TABLE_PREFIX . "style
			SET
				parentid = {$parentid},
				parentlist = '{$parentlist}',
				type = 'mobile'
			WHERE styleid = {$styleid}"
		);	
		// Don't check 'type' here, just set them to mobile since we set the first parent to 'mobile'
		$styles = $this->registry->db->query_read("
			SELECT styleid, parentlist, parentid
			FROM " . TABLE_PREFIX . "style
			WHERE
				parentid = {$styleid}
		");			
		while ($style = $this->registry->db->fetch_array($styles))
		{
			$this->update_style($style['styleid'], $styleid, $style['styleid'] . ',' . $parentlist);
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
