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

/**
* Abstracted class that handles Blog User CSS
*
* @package	vBulletin
* @version	$Revision: 26041 $
* @date		$Date: 2008-03-11 14:32:41 -0700 (Tue, 11 Mar 2008) $
*/
class vB_UserCSS_Blog extends vB_UserCSS
{

	/**
	* Fetches the existing data for the selected user
	*
	* @return	array	Array of [selector][property] = value
	*/
	function fetch_existing()
	{
		$usercss_result = $this->dbobject->query_read("
			SELECT * FROM " . TABLE_PREFIX . "blog_usercss
			WHERE userid = " . $this->userid . "
			ORDER BY selector
		");

		$existing = array();
		while ($usercss = $this->dbobject->fetch_array($usercss_result))
		{
			$existing["$usercss[selector]"]["$usercss[property]"] = $usercss['value'];
		}

		$this->dbobject->free_result($usercss_result);

		return $existing;
	}

	/**
	* Saves the updated properties to the database.
	*/
	function save()
	{
		// First, we want to remove any properties they don't have access to.
		// This is in case they lost some permissions;
		// leaving them around leads to unexpected behavior.
		$prop_del = array();
		foreach ($this->properties AS $property => $propertyinfo)
		{
			if (!($this->permissions['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions["blog_$propertyinfo[permission]"]))
			{
				$prop_del[] = $this->dbobject->escape_string($property);
			}
		}

		if ($prop_del)
		{
			$this->dbobject->query_write("
				DELETE FROM " . TABLE_PREFIX . "blog_usercss
				WHERE userid = " . $this->userid . "
					AND property IN ('" . implode("','", $prop_del) . "')
			");
		}

		// now go for any entries that we emptied -- these are being removed
		if (!empty($this->delete))
		{
			foreach ($this->delete as $selector => $properties)
			{
				foreach ($properties as $property)
				{
					if (!empty($this->existing["$selector"]["$property"]))
					{
						$this->dbobject->query_write("
							DELETE FROM " . TABLE_PREFIX . "blog_usercss
							WHERE userid = " . $this->userid . "
								AND selector = '" . $this->dbobject->escape_string($selector) . "'
								AND property = '" . $this->dbobject->escape_string($property) . "'
						");
					}

					unset($this->existing["$selector"]["$property"]);
				}

			}
		}

		// and for new/changed ones...
		if (!empty($this->store))
		{
			$value = array();

			foreach ($this->store as $selector => $properties)
			{
				foreach ($properties as $property => $value)
				{
					$values[] = "
						(" . $this->userid . ",
						'" . $this->dbobject->escape_string($selector) . "',
						'" . $this->dbobject->escape_string($property) . "',
						'" . $this->dbobject->escape_string($value) . "')
					";

					$this->dbobject->query_write("
						DELETE FROM " . TABLE_PREFIX . "blog_usercss
						WHERE userid = " . $this->userid . "
							AND selector = '" . $this->dbobject->escape_string($selector) . "'
							AND property = '" . $this->dbobject->escape_string($property) . "'
					");
				}
			}

			if ($values)
			{
				$this->dbobject->query_write("
					INSERT INTO " . TABLE_PREFIX . "blog_usercss
						(userid, selector, property, value)
					VALUES
						" . implode(", ", $values)
				);
			}
		}

		$this->update_css_cache();
	}

	/**
	* Updates this user's CSS cache.
	*
	* @return	string	Compiled CSS
	*/
	function update_css_cache()
	{
		$buildcss = $this->build_css();

		// this only saves tborder_bgcolor for now
		$usercss_cache = $this->fetch_existing();
		if ($usercss_cache['tableborder']['background_color'])
		{
			$style = array(
				'tborder_bgcolor' => $usercss_cache['tableborder']['background_color'],
			);
		}
		else
		{
			$style = array();
		}

		$this->dbobject->query_write("
			REPLACE INTO " . TABLE_PREFIX . "blog_usercsscache
				(userid, cachedcss, csscolors, buildpermissions)
			VALUES
				(
					" . $this->userid . ",
					'" . $this->dbobject->escape_string($buildcss) . "',
					'" . $this->dbobject->escape_string(serialize($style)) . "',
					" . intval($this->permissions['vbblog_general_permissions']) . "
				)
		");

		return $buildcss;
	}

	/**
	* Checks permissions for the specified selector/property.
	*
	* @param	string	Selector
	* @param	string	Property
	* @param	array	Array of permissions to check against
	*
	* @return	boolean
	*/
	function check_css_permission($selector, $property, $permissions)
	{
		$permfield = $this->properties["$property"]['permission'];

		if ($permfield == 'caneditbgimage')
		{
			return ($this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_albums'] AND $this->permissions['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions["blog_$permfield"]) ? true : false;
		}

		return ($this->permissions['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions["blog_$permfield"]) ? true : false;
	}

	/**
	* Build an array of information about how to display the user CSS editor page
	*
	* @return	array
	*/
	function build_display_array()
	{
		$display = parent::build_display_array();

		$display['entryposter'] = array(
			'phrasename' => 'usercss_entryposter',
			'properties' => array(
				'background_color',
				'background_image',
				'background_repeat',
				'color',
				'linkcolor' => 'entryposter_a'
			)
		);

		return $display;
	}

	/**
	* Returns an array of information about selectors and properties
	*
	* @return	array
	*/
	function build_css_array()
	{
		$css = parent::build_css_array();

		$css['entryposter'] = array(
				'selectors'	=> array(
					'.entryposter',
				),
				'properties' => array(
					'background_color',
	 				'background_image',
	 				'background_repeat',
					'color',
				)
			);

		$css['entryposter_a'] = array(
			'selectors'	=> array(
				'.entryposter a',
			),
			'properties' => array(
				'linkcolor'
			)
		);

		return $css;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 26041 $
|| ####################################################################
\*======================================================================*/
?>