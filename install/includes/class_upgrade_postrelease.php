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

class vB_Upgrade_postrelease extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = 'postrelease';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = 'postrelease';

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
		$this->caninstall = ($this->productresult =  $this->product->verify_install('postrelease'));
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

		if ($this->is_newer_version($this->product->installed_version, $version))
		{
			$this->skip_message();
			return false;
		}

		return true;
	}

	/*
	* Step 1 - Install Post Release Key Table.
	*/
	function step_1()
	{
		if (!$this->caninstall)
		{
			return;
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], 'postrelease'),
			"CREATE TABLE " . TABLE_PREFIX . "postrelease (
				secretkey VARCHAR(10) DEFAULT NULL
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$key = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',5)),0,10);
		
		// Insert new key only if table is empty //
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'],'postrelease'),
			"INSERT INTO " . TABLE_PREFIX . "postrelease
			(secretkey)
			(SELECT '$key'
			FROM DUAL
			WHERE NOT EXISTS
			(
				SELECT secretkey
				FROM " . TABLE_PREFIX . "postrelease
				LIMIT 1
			))"
		);
	}

	/*
	* Step 2 - Final Step
	* This must always be the last step. Just renumber this step as more upgrade steps are added before.
	*/
	function step_2()
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
