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

class vB_Upgrade_420a1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '420a1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.2.0 Alpha 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.12';

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
	* Step #1 - Create Navigation Table
	*
	*/
	function step_1()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], 'navigation') ,
				"CREATE TABLE " . TABLE_PREFIX . "navigation
				(
					navid INT UNSIGNED NOT NULL AUTO_INCREMENT,
					name VARCHAR(20) NOT NULL DEFAULT '',
					navtype ENUM('tab','menu','link') NOT NULL DEFAULT 'tab',
					displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '0',
					parent VARCHAR(20) NOT NULL DEFAULT '',
					url VARCHAR(255) NOT NULL DEFAULT '',
					state TINYINT UNSIGNED NOT NULL DEFAULT '0',
					scripts VARCHAR(30) NULL DEFAULT '',
					showperm VARCHAR(30) NULL DEFAULT '',
					productid VARCHAR(25) NOT NULL DEFAULT '',
					username VARCHAR(100) NOT NULL DEFAULT '',
					version VARCHAR(30) NOT NULL DEFAULT '',
					dateline INT NOT NULL DEFAULT '0',
					PRIMARY KEY (navid),
					UNIQUE KEY identity (name, productid),
					KEY productid (productid, state)
				)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/*
	  Step 2 - Add E-Mail Scheduled Task
	*/
	function step_2()
	{
		$this->add_cronjob(
			array(
				'varname'  => 'cronmail',
				'nextrun'  => 1320000000,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => -1,
				'minute'   => 'a:6:{i:0;i:0;i:1;i:10;i:2;i:20;i:3;i:30;i:4;i:40;i:5;i:50;}',
				'filename' => './includes/cron/mailqueue.php',
				'loglevel' => 1,
				'volatile' => 1,
				'product'  => 'vbulletin'
			)
		);
	}

	/*
	  Step 3 - Add Reputation Count Field
	*/
	function step_3()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 1),
			'user',
			'newrepcount',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/*
	  Step 4 - Add Index to User Table
	*/
	function step_4()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'lastactivity', 'user'),
			'user',
			'lastactivity',
			'lastactivity'
		);
	}

	/*
	  Step 5 - Create content read table.
	*/
	function step_5()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], 'contentread') ,
				"CREATE TABLE " . TABLE_PREFIX . "contentread
				(
					readid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					contenttypeid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
					contentid INT(10) UNSIGNED NOT NULL DEFAULT '0',
					userid INT(10) UNSIGNED NOT NULL DEFAULT '0',
					readtype ENUM('read','view','other') NOT NULL DEFAULT 'other',
					dateline INT(10) UNSIGNED NOT NULL DEFAULT '0',
					ipid INT(10) UNSIGNED NOT NULL DEFAULT '0',
					PRIMARY KEY (contenttypeid, contentid, userid, readtype),
					KEY utd (userid, contenttypeid, dateline),
					KEY tcd (contenttypeid, contentid, dateline),
					KEY dateline (dateline),
					UNIQUE KEY readid (readid)
				)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/*
	  Step 6 - Create IP table.
	*/
	function step_6()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], 'ipdata') ,
				"CREATE TABLE " . TABLE_PREFIX . "ipdata
				(
					ipid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					contenttypeid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
					contentid INT(10) UNSIGNED NOT NULL DEFAULT '0',
					userid INT(10) UNSIGNED NOT NULL DEFAULT '0',
					rectype ENUM('content','read','view','visit','register','logon','logoff','other') NOT NULL DEFAULT 'other',
					dateline INT(10) UNSIGNED NOT NULL DEFAULT '0',
					ip VARCHAR(40) NOT NULL DEFAULT '0.0.0.0',
					altip VARCHAR(40) NOT NULL DEFAULT '0.0.0.0',
					PRIMARY KEY (contenttypeid, contentid, userid, rectype),
					KEY usertype (userid, contenttypeid),
					KEY dateline (dateline),
					UNIQUE KEY ipid (ipid)
				)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/*
	  Step 7 - Disable or delete old products.
	*/
	function step_7()
	{
		require_once(DIR . '/includes/adminfunctions_plugin.php');
		$this->show_message($this->phrase['version']['420a1']['disable_products']);

		/* Remove any old 3.x versions,
		most simply will not work on 4.x */
		$products = array(
			'apiclean_01', 'paulm_trm_37', 'paulm_trm_38', 'xenon_prevdoublepost', 'paulm_pdp_38',
			'paulm_20050705', 'paulm_drg_37', 'paulm_drg_38', 'paulm_20060709', 'paulm_cmq_37',
			'paulm_cmq_38', 'paulm_20050918', 'paulm_dup_37', 'paulm_dup_38', 'paulm_20060801',
			'paulm_mpr_37', 'paulm_mpr_38', 'paulm_20050716', 'paulm_wrt_37', 'paulm_wrt_38',
			'paulm_20061211', 'paulm_erc_37', 'paulm_erc_38', 'paulm_20051014', 'paulm_20050610',
			'paulm_wvt_37', 'paulm_wvt_38',
		);

		// Turn off echo.
		remove_products($products, false, false);

		/* Disable any 4.x versions, admins can still choose to use
		these if they want, so lets leave it to them to remove them */
		$products = array(
			'paulm_trm_40', 'paulm_trm_41', 'paulm_pdp_40', 'paulm_pdp_41',
			'paulm_drg_40', 'paulm_drg_41', 'paulm_cmq_40', 'paulm_cmq_41',
			'paulm_dup_40', 'paulm_dup_41', 'paulm_mpr_40', 'paulm_mpr_41',
			'paulm_wrt_40', 'paulm_wrt_41', 'paulm_erc_40', 'paulm_erc_41',
			'paulm_wvt_40', 'paulm_wvt_41',
		);

		// Use the disable only flag.
		remove_products($products, false, false, true, $this->phrase['version']['420a1']['disabled_by_42']);
	}

	/*
	  Step 8 - Add Product Field to Blocks
	*/
	function step_8()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'block', 1, 1),
			'block',
			'product',
			'varchar',
			array('length' => 25, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/*
	  Step 9 - Set Default Usergroup Permissions
	*/
	function step_9()
	{
		// Doublepost Bypass permission = 33554432.
		$this->run_query(
			$this->phrase['version']['380a2']['updating_usergroup_permissions'], "
			UPDATE " . TABLE_PREFIX . "usergroup
			SET forumpermissions = forumpermissions | 33554432
			WHERE usergroupid IN (6)"
		);

		// Who Read permission = 67108864.
		$this->run_query(
			$this->phrase['version']['380a2']['updating_usergroup_permissions'], "
			UPDATE " . TABLE_PREFIX . "usergroup
			SET forumpermissions = forumpermissions | 67108864
			WHERE usergroupid IN (5,6,7)"
		);

		// Profile Reputation permission = 2.
		$this->run_query(
			$this->phrase['version']['380a2']['updating_usergroup_permissions'], "
			UPDATE " . TABLE_PREFIX . "usergroup
			SET genericpermissions2 = genericpermissions2 | 2
			WHERE usergroupid IN (5,6,7)"
		);

		// Who Read permission = 4.
		$this->run_query(
			$this->phrase['version']['380a2']['updating_usergroup_permissions'], "
			UPDATE " . TABLE_PREFIX . "usergroup
			SET genericpermissions2 = genericpermissions2 | 4
			WHERE usergroupid IN (2,5,6,7)"
		);
	}

	/*
	  Step 10 - Set Default Forum Permissions
	*/
	function step_10()
	{
		// Who Read permission = 1048576.
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "forum"), "
			UPDATE " . TABLE_PREFIX . "forum
			SET options = options | 1048576
		");

		// Can Give Reputation = 2097152.
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "forum"), "
			UPDATE " . TABLE_PREFIX . "forum
			SET options = options | 2097152
		");
	}

	/*
	  Step 11 - Add Index to Upgrade Log
	*/
	function step_11()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'script', 'upgradelog'),
			'upgradelog',
			'script',
			'script'
		);
	}

	/**
	* Step 12
	*/
	function step_12()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], 'activitystream') ,
				"CREATE TABLE " . TABLE_PREFIX . "activitystream (
					activitystreamid INT UNSIGNED NOT NULL AUTO_INCREMENT,
					userid INT UNSIGNED NOT NULL DEFAULT '0',
					dateline INT UNSIGNED NOT NULL DEFAULT '0',
					data MEDIUMTEXT NOT NULL,
					contentid INT UNSIGNED NOT NULL DEFAULT '0',
					typeid INT UNSIGNED NOT NULL DEFAULT '0',
					action enum('create','edit','delete') NOT NULL DEFAULT 'create',
					score DECIMAL(13,3) NOT NULL DEFAULT '0.000',
					PRIMARY KEY (activitystreamid),
					KEY score (score, dateline),
					KEY dateline (dateline),
					KEY typeid (typeid, dateline),
					KEY typeid_2 (typeid, score, dateline),
					KEY contentid (contentid, typeid),
					KEY userid (userid, dateline)
				)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #13
	*
	*/
	function step_13()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], 'activitystreamtype') ,
				"CREATE TABLE " . TABLE_PREFIX . "activitystreamtype (
					typeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
					packageid INT UNSIGNED NOT NULL,
					section CHAR(25) NOT NULL DEFAULT '',
					type CHAR(25) NOT NULL DEFAULT '',
					enabled SMALLINT NOT NULL DEFAULT '1',
					PRIMARY KEY (typeid),
					UNIQUE KEY section (section, type)
				)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #14 - Activity Stream Type default data
	*
	*/
	function step_14()
	{
		$packageinfo = $this->db->query_first("
			SELECT packageid
			FROM " . TABLE_PREFIX . "package
			WHERE productid = 'vbulletin'
		");
		$packageid = $packageinfo['packageid'];

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], 'activitystreamtype') ,
				"INSERT IGNORE INTO " . TABLE_PREFIX . "activitystreamtype
					(packageid, section, type, enabled)
				VALUES
					({$packageid}, 'album', 'album', 1),
					({$packageid}, 'album', 'comment', 1),
					({$packageid}, 'album', 'photo', 1),
					({$packageid}, 'calendar', 'event', 1),
					({$packageid}, 'forum', 'post', 1),
					({$packageid}, 'forum', 'thread', 1),
					({$packageid}, 'forum', 'visitormessage', 1),
					({$packageid}, 'socialgroup', 'discussion', 1),
					({$packageid}, 'socialgroup', 'group', 1),
					({$packageid}, 'socialgroup', 'groupmessage', 1),
					({$packageid}, 'socialgroup', 'photo', 1),
					({$packageid}, 'socialgroup', 'photocomment', 1)
		");

		/*
		 * Have to do these inserts here since we need to ask about setting defaults later but before product upgrade
		 * They are also insert ignored in the product scripts for sanity
		 */
		if ($this->registry->products['vbblog'])
		{
			$packageinfo = $this->db->query_first("
				SELECT packageid
				FROM " . TABLE_PREFIX . "package
				WHERE productid = 'vbblog'
			");
			$packageid = $packageinfo['packageid'];

			if ($packageid)
			{
				$this->run_query(
					sprintf($this->phrase['vbphrase']['update_table'], 'activitystreamtype') ,
						"INSERT IGNORE INTO " . TABLE_PREFIX . "activitystreamtype
							(packageid, section, type, enabled)
						VALUES
							({$packageid}, 'blog', 'entry', 1)
				");

				$this->run_query(
					sprintf($this->phrase['vbphrase']['update_table'], 'activitystreamtype') ,
						"INSERT IGNORE INTO " . TABLE_PREFIX . "activitystreamtype
							(packageid, section, type, enabled)
						VALUES
							({$packageid}, 'blog', 'comment', 1)
				");
			}
		}

		if ($this->registry->products['vbcms'])
		{
			$packageinfo = $this->db->query_first("
				SELECT packageid
				FROM " . TABLE_PREFIX . "package
				WHERE productid = 'vbcms'
			");
			$packageid = $packageinfo['packageid'];

			if ($packageid)
			{
				$this->run_query(
					sprintf($this->phrase['vbphrase']['update_table'], 'activitystreamtype') ,
						"INSERT IGNORE INTO " . TABLE_PREFIX . "activitystreamtype
							(packageid, section, type, enabled)
						VALUES
							({$packageid}, 'cms', 'article', 1)
				");

				$this->run_query(
					sprintf($this->phrase['vbphrase']['update_table'], 'activitystreamtype') ,
						"INSERT IGNORE INTO " . TABLE_PREFIX . "activitystreamtype
							(packageid, section, type, enabled)
						VALUES
							({$packageid}, 'cms', 'comment', 1)
				");
			}
		}
	}

	/**
	* Step #15 - Add phrasegroup to language table
	*
	*/
	function step_15()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 1, 1),
			'language',
			'phrasegroup_activitystream',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step 16 - Add phrasetype for Activity Stream phrases
	*
	*/
	function step_16()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "phrasetype"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype
				(title, editrows, fieldname, special)
			VALUES
				('" . $this->db->escape_string($this->phrase['phrasetype']['activitystream']) . "', 3, 'activitystream', 0)
			"
		);
	}

	/**
	* Step #17 - Add way to track the source of a picturecomment .. was it originally posted from an album or a group?
	*
	*/
	function step_17()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'picturecomment', 1, 3),
			'picturecomment',
			'sourcecontenttypeid',
			'INT',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #18 - Add way to track the source of a picturecomment .. was it originally posted from an album or a group?
	*
	*/
	function step_18()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'picturecomment', 2, 3),
			'picturecomment',
			'sourcecontentid',
			'INT',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #19 - Add way to track the source of a picturecomment .. was it originally posted from an album or a group?
	*
	*/

	function step_19()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'picturecomment', 3, 3),
			'picturecomment',
			'sourceattachmentid',
			'INT',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #20 - Add postercount to thread
	*
	*/
	function step_20()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'thread', 1, 1),
			'thread',
			'postercount',
			'INT',
			self::FIELD_DEFAULTS
		);
	}

	/*
	 * Step #21
	 * Build Activity Stream
	 *
	 */
	function step_21($data = null)
	{
		/*
		 * Can't display checkbox options in the cli without a lot of mess so just skip
		 */
		if ($this->caller == 'cli')
		{
			$this->skip_message();
		}
		else if ($data['htmlsubmit'])
		{
			require_once(DIR . '/includes/class_bootstrap_framework.php');
			vB_Bootstrap_Framework::init('../');

			$expire = intval($data['htmldata']['expire']);
			if ($expire > 180)
			{
				$expire = 180;
			}
			if ($expire < 1)
			{
				$expire = 1;
			}

			vB::$vbulletin->options['as_expire'] = $expire;
			unset($data['htmldata']['expire']);
			$this->db->query_write("
				UPDATE " . TABLE_PREFIX . "activitystreamtype
				SET enabled = 0
			");
			foreach (array_keys($data['htmldata']) AS $type)
			{
				$values = explode('_', $type);
				$section = $values[0];
				$type = $values[1];
				$this->db->query_write("
					UPDATE " . TABLE_PREFIX . "activitystreamtype
					SET enabled = 1
					WHERE
						section = '{$section}'
							AND
						type = '{$type}'
				");
				vB::$vbulletin->options['as_content'] = vB::$vbulletin->options['as_content'] | vB::$vbulletin->bf_misc_ascontent[$type];
			}

			build_activitystream_datastore();
			vB_ActivityStream_Manage::rebuild();

			// build_options() is executed after each version step of the upgrade so this will be processed before the final settings import
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "setting"),
				"REPLACE INTO " . TABLE_PREFIX . "setting
					(varname, value, grouptitle, defaultvalue, optioncode, displayorder, volatile, datatype, product)
				VALUES
					('as_expire', {$expire}, 'activitystream', '30', '', 10, 1, 'number', 'vbulletin')
			");
		}
		else
		{
			$row = $this->db->query_first("
				SELECT COUNT(*) AS count
				FROM " . TABLE_PREFIX . "activitystream
			");

			if ($row['count'] == 0)
			{
				return $this->asform();
			}
			else
			{
				$this->skip_message();
			}
		}
	}

	/**
	* Step 22 - Add popularity cronjob
	*
	*/
	function step_22()
	{
		$this->add_cronjob(
			array(
				'varname'  => 'activitypopularity',
				'nextrun'  => 1178750700,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => -1,
				'minute'   => 'a:4:{i:0;i:0;i:1;i:15;i:2;i:30;i:3;i:45;}',
				'filename' => './includes/cron/activity.php',
				'loglevel' => 0,
				'volatile' => 1,
				'product'  => 'vbulletin'
			)
		);
	}


	/*
	 * Show activity stream options form
	 */
	private function asform()
	{
		$fields = $this->phrase['version']['420a1_as'];
		if ($this->registry->products['vbblog'])
		{
			$fields = array_merge($fields, $this->phrase['version']['420a1_as_blog']);
		}
		if ($this->registry->products['vbcms'])
		{
			$fields = array_merge($fields, $this->phrase['version']['420a1_as_cms']);
		}

		$html = '<div style="padding:5px">' . $this->phrase['version']['420a1']['select_as_time'] . '</div>';
		$html .= '<div style="padding:5px"><input type="text" name="htmldata[expire]" size="5" value="30" tabindex="1" /></div>';
		$html .= '<div style="padding:5px">' . $this->phrase['version']['420a1']['activitystream'] . '</div>';
		$html .= '<ul style="list-style:none">';
		$template = '<li><label for="%1$s"><input type="checkbox" value="1" tabindex="1" name="htmldata[%1$s]" id="%1$s" checked="checked" />%2$s</label></li>';
		foreach ($fields AS $name => $phrase)
		{
			$html .= construct_phrase($template, $name, $phrase);;
		}
		$html .= "</ul>";

		return array(
			'html'       => $html,
			'confirm'    => $this->phrase['vbphrase']['okay'],
			'hidecancel' => true,
			'height'     => '40px',
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
