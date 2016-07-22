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
* Class to manage the activity stream
*
* @package	vBulletin
* @version	$Revision: 57655 $
* @date		$Date: 2012-01-09 12:08:39 -0800 (Mon, 09 Jan 2012) $
*/
class vB_ActivityStream_Manage
{
	/**
	 * Array to store the values of core fields
	 *
	 *
	 * @var	array
	 */
	private $corefields = array();

	/**
	 * Array to store the content specific data
	 *
	 * @var	array
	 */
	private $contentdata = array();

	/**
	 * Hook for constructor.
	 *
	 * @var string
	 */
	private $hook_start = 'activity_manage_start';

	/**
	 * Hook for pre save.
	 *
	 * @var string
	 */
	private $hook_presave = 'activity_manage_presave';

	/**
	 * Hook for save.
	 *
	 * @var string
	 */
	private $hook_save = 'activity_manage_save';

	/**
	 * Hook for delete.
	 *
	 * @var string
	 */
	private $hook_delete = 'activity_manage_delete';

	/**
	 * Hook for update.
	 *
	 * @var string
	 */
	private $hook_update = 'activity_manage_update';

	/**
	 * Constructor - set Options
	 *
	 * @param	string	Content Section (filter)
	 * @param	string	Content Type
	 *
	 */
	public function __construct($section, $type)
	{
		$this->corefields['section'] = $section;
		$this->corefields['type'] = $type;

		($hook = vBulletinHook::fetch_hook($this->hook_start)) ? eval($hook) : false;
	}

	/**
	 * Sets the supplied data to be part of the data to be saved.
	 *
	 * @param	string	The name of the field to which the supplied data should be applied
	 * @param	mixed	The data itself
	 *
	 */
	public function set($fieldname, $value)
	{
		$this->corefields[$fieldname] = $value;
	}

	/**
	 * Sets the content specific data array
	 *
	 * @param	mixed	The data itself
	 *
	 */
	public function set_data($value)
	{
		$this->contentdata = $value;
	}

	/**
	 * Inserts entry into the activity stream
	 *
	 * @return	int	Activty Stream ID
	 */
	public function save()
	{
		$bypass = false;
		($hook = vBulletinHook::fetch_hook($this->hook_presave)) ? eval($hook) : false;

		// If this option isn't enabled then just return
		$action = $this->corefields['section'] . '_' . $this->corefields['type'];
		if (!$bypass AND !(vB::$vbulletin->activitystream[$action]['enabled']))
		{
			return;
		}

		if (!isset($this->corefields['dateline']))
		{
			$this->corefields['dateline'] = TIMENOW;
		}

		vB::$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "activitystream
				(contentid, typeid, userid, dateline, data, action)
			VALUES
				(
					" . intval($this->corefields['contentid']) . ",
					" . intval(vB::$vbulletin->activitystream[$action]['typeid']) . ",
					" . intval($this->corefields['userid']) . ",
					" . intval($this->corefields['dateline']) . ",
					'" . vB::$db->escape_string($this->contentdata ? @serialize($this->contentdata) : '') . "',
					'" . vB::$db->escape_string($this->corefields['action']) . "'
				)
		");

		($hook = vBulletinHook::fetch_hook($this->hook_save)) ? eval($hook) : false;

		return vB::$db->insert_id();
	}

	/**
	 * Deletes an entry from the activity stream. contentid can be set as a single int or an array of ints
	 * Don't check if this content is enabled, just delete in case the content existed before this type was disabled
	 *
	 */
	public function delete()
	{
		if (!$this->corefields['contentid'])
		{
			return;
		}

		if (!is_array($this->corefields['contentid']))
		{
			$this->corefields['contentid'] = array($this->corefields['contentid']);
		}

		$action = $this->corefields['section'] . '_' . $this->corefields['type'];
		$typeid = intval(vB::$vbulletin->activitystream[$action]['typeid']);

		vB::$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "activitystream
			WHERE
				typeid = {$typeid}
					AND
				contentid IN (" . implode(",", array_map('intval', $this->corefields['contentid'])) . ")
		");

		($hook = vBulletinHook::fetch_hook($this->hook_delete)) ? eval($hook) : false;
	}

	protected static function readdir($dir)
	{
		$files = scandir($dir);
		$output = array();
		foreach ($files AS $file)
		{
			if ($file !== 'base.php' AND $file !== '.' AND $file !== '..' AND (substr($file, -strlen('.php')) === '.php' OR is_dir("{$dir}/{$file}")))
			{
				//echo $file;
				if (is_dir("{$dir}/{$file}"))
				{
					$output = array_merge($output, self::readdir("{$dir}/{$file}"));
				}
				else
				{
					$output[] = "{$dir}/{$file}";
				}
			}
		}

		return $output;
	}

	/*
	 * Rebuild Activity Stream
	 *
	 */
	public static function rebuild()
	{
		$files = self::readdir(DIR . '/vb/activitystream/populate');
		foreach ($files AS $file)
		{
			$file = preg_match('#^(' . preg_quote(DIR, '#') . '/vb/activitystream/populate/([a-z]*)/([a-z]*)(\.php)$)#si', $file, $matches);
			$classname = 'vB_ActivityStream_Populate_' . $matches[2] . '_' . $matches[3];
			if (is_subclass_of($classname, 'vB_ActivityStream_Populate_Base'))
			{
				$class = new $classname();
				$class->populate();
			}
		}
	}

	/*
	 * Update Activity Stream Scores
	 *
	 */
	public static function updateScores()
	{
		$files = self::readdir(DIR . '/vb/activitystream/popularity');
		foreach ($files AS $file)
		{
			$file = preg_match('#^(' . preg_quote(DIR, '#') . '/vb/activitystream/popularity/([a-z]*)/([a-z]*)(\.php)$)#si', $file, $matches);
			$classname = 'vB_ActivityStream_Popularity_' . $matches[2] . '_' . $matches[3];
			if (is_subclass_of($classname, 'vB_ActivityStream_Popularity_Base'))
			{
				$class = new $classname();
				$class->updateScore();
			}
		}
	}

	/**
	 * Update Activity Stream
	 *
	 */
	 public function update()
	 {
		if (!$this->corefields['contentid'])
		{
			return;
		}

		if (!is_array($this->corefields['contentid']))
		{
			$this->corefields['contentid'] = array($this->corefields['contentid']);
		}

		$sql = array();

		if ($this->contentdata)
		{
			$sql[] = 'data = \'' . vB::$db->escape_string(@serialize($this->contentdata)) . '\'';
		}

		foreach($this->corefields AS $key => $value)
		{
			switch($key)
			{
				case 'userid':
				case 'dateline':
				case 'action':
					$sql[] = "$key = '" . vB::$db->escape_string($value) . "'";
					break;
				case 'contentid':
					break;
			}
		}

		if ($sql)
		{
			$action = $this->corefields['section'] . '_' . $this->corefields['type'];
			$typeid = intval(vB::$vbulletin->activitystream[$action]['typeid']);

			vB::$db->query_write("
				UPDATE " . TABLE_PREFIX . "activitystream
				SET
					" . implode(",", $sql) . "
				WHERE
					typeid = {$typeid}
						AND
					contentid IN (" . implode(",", array_map('intval', $this->corefields['contentid'])) . ")
			");
		}

		($hook = vBulletinHook::fetch_hook($this->hook_update)) ? eval($hook) : false;
	 }
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 57655 $
|| ####################################################################
\*======================================================================*/