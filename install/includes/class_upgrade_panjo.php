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

class vB_Upgrade_panjo extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = 'panjo';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = 'panjo';

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
		$this->caninstall = ($this->productresult =  $this->product->verify_install('panjo'));
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
	* Step 1 - Remove 'enthusify' (Old Panjo).
	*/
	function step_1()
	{
		if (!$this->caninstall)
		{
			$this->skip_message();
			return;
		}

		/* Remove Old Product */
		if (delete_product('enthusify'))
		{
			$db_alter = new vB_Database_Alter_MySQL($this->db);

			if ($db_alter->fetch_table_info('user')) 
			{
				$db_alter->drop_field(array(
					'enthusify_selling'
				));
			}	

			if ($db_alter->fetch_table_info('usergroup')) 
			{
				$db_alter->drop_field(array(
					'enthusify_can_sell',
					'enthusify_transaction_fee',
					'enthusify_listing_fee'
				));
			}

			if ($db_alter->fetch_table_info('thread')) 
			{
				$db_alter->drop_field(array(
					'enthusify_listing_id'
				));
			}

			if ($db_alter->fetch_table_info('forum')) 
			{
				$db_alter->drop_field(array(
					'enthusify_redirect_newthread',
					'enthusify_newthread_text'
				));
			}

			$this->show_message(sprintf($this->phrase['vbphrase']['product_x_removed'],'enthusify'));
		}
		else
		{
			$this->skip_message();
		}
	}

	/*
	* Step 1 - Panjo Table Changes.
	*/
	function step_2()
	{
		if (!$this->caninstall)
		{
			$this->skip_message();
			return;
		}

		/* Install */
		$db_alter = new vB_Database_Alter_MySQL($this->db);

		if ($db_alter->fetch_table_info('user')) {
			$db_alter->add_field(array(
				'name' => 'panjo_selling',
				'type' => 'BOOLEAN',
				'null' => true
			));
		}

		if ($db_alter->fetch_table_info('usergroup')) {
			$db_alter->add_field(array(
				array(
					'name' => 'panjo_can_sell',
					'type' => 'BOOLEAN',
					'null' => false,
					'default' => true
				),
				array(
					'name' => 'panjo_transaction_fee',
					'type' => 'DECIMAL',
					'length' => '10, 2',
					'null' => true
				),
				array(
					'name' => 'panjo_listing_fee',
					'type' => 'DECIMAL',
					'length' => '10, 2',
					'null' => true
				)
			));
		}
	
		if ($db_alter->fetch_table_info('forum')) {
			$db_alter->drop_field(array(
				'panjo_redirect_newthread',
				'panjo_newthread_text'
			));
		}

		if ($db_alter->fetch_table_info('thread')) {
			$db_alter->add_field(array(
				array(
					'name' => 'panjo_listing_id',
					'type' => 'VARCHAR',
					'length' => 255,
					'null' => true
				)
			));
		}

		$this->show_message(sprintf($this->phrase['final']['installing_product_x'],'panjo'));
	}

	/*
	* Step 3 - Final Step
	* This must always be the last step. Just renumber this step as more upgrade steps are added before.
	*/
	function step_3()
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
