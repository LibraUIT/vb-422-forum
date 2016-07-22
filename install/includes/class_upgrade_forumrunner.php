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

if (VB_AREA != 'Install' AND !isset($GLOBALS['vbulletin']->db))
{
	exit;
}

class vB_Upgrade_forumrunner extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = 'forumrunner';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = 'forumrunner';

	/**
	* Can we install the product?
	*
	* @var	bool
	*/
	private $caninstall = true;

	/**
	* Result of $product->verify_install
	*
	* @var	mixed
	*/
	private $productresult = null;

	/**
	* Product Obj
	*
	* @var	string
	*/
	private $product = null;

	/*Properties====================================================================*/

	/**
	* Constructor.
	*
	* @param	vB_Registry	Reference to registry object
	*/
	public function __construct(&$registry, $phrase, $maxversion)
	{
		parent::__construct($registry, $phrase, $maxversion);

		if (defined('SKIPDB'))
		{
			$this->caninstall = true;
			return;
		}

		require_once(DIR . '/includes/class_upgrade_product.php');
		$this->product = new vB_Upgrade_Product($registry, $phrase['vbphrase'], true, $this->caller);
		$this->caninstall = ($this->productresult =  $this->product->verify_install('forumrunner'));
	}

	/**
	*	Verify if product upgrade step needs to be executed
	*
	* @param	string	version string
	*/
	private function verify_product_version($version = null)
	{
		if (!$this->caninstall)
		{
			$this->add_error($this->productresult, self::PHP_TRIGGER_ERROR, true);
			return false;
		}

		if (is_newer_version($this->product->installed_version, $version))
		{
			$this->skip_message();
			return false;
		}

		return true;
	}

	/**
	* Step #1 - Install New Forum Runner
	* NOTE!! This step does not get updated with schema changes which differs from the Blog.
	*
	*/
	function step_1()
	{
		if (!$this->verify_product_version())
		{
			return;
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], 'forumrunner_push_data'),
			"CREATE TABLE " . TABLE_PREFIX . "forumrunner_push_data (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				vb_userid INT UNSIGNED NOT NULL DEFAULT '0',
				vb_pmid INT UNSIGNED NOT NULL DEFAULT '0',
				vb_threadid INT NOT NULL DEFAULT '0',
				vb_threadread INT NOT NULL DEFAULT '0',
				PRIMARY KEY (id)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
		
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], 'forumrunner_push_data'),
			"CREATE TABLE " . TABLE_PREFIX . "forumrunner_push_users (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				vb_userid INT UNSIGNED NOT NULL,
				fr_username VARCHAR(45) NOT NULL,
				last_login DATETIME DEFAULT NULL,
				PRIMARY KEY (id)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);		
	}

	/**
	* Step #2 - Table Update
	*
	*/
	function step_2()
	{
		if (!$this->verify_product_version('1.3.0'))
		{
			return;
		}

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'forumrunner_push_users', 1, 1),
			'forumrunner_push_users',
			'b',
			'tinyint',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'forumrunner_push_data', 1, 1),
			'forumrunner_push_data',
			'vb_subsent',
			'tinyint',
			self::FIELD_DEFAULTS
		);		
	}

	/**
	* Step #3 - Admin Message about sitekey
	*
	*/
	function step_3()
	{	
		$this->add_adminmessage(
			'after_upgrade_4112_update_sitekey',
			array(
				'dismissable' => 1,
				'script'      => '',
				'action'      => '',
				'execurl'     => '',
				'method'      => '',
				'status'      => 'undone',
			)
		);	
	}
	
	/**
	* Step #4 - Final Step
	*	This must always be the last step. Just renumber this step as more upgrade steps are added before
	*
	*/
	function step_4()
	{
		if ($this->caninstall)
		{
			$result = $this->product->post_install();
			if (!is_array($result))
			{
				$this->add_error($result, self::PHP_TRIGGER_ERROR, true);
				return false;
			}
			$this->show_message($this->phrase['final']['product_installed']);
		}
		else
		{
			$this->skip_message();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
