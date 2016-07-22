<?php

class vBCms_Item_Rate extends vB_Item
{
	/*Properties====================================================================*/

	/**
	 * A package identifier.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	/**
	 * A class identifier.
	 *
	 * @var string
	 */
	protected $class = 'Rate';

	/**
	 * The class name of the most appropriate DM for managing the item's data.
	 *
	 * @var string
	 */
	protected $dm_class = 'vBCms_DM_Rate';

	/**
	 * Whether the model info can be cached.
	 *
	 * @var bool
	 */
	protected $cachable = false;

	/*InfoFlags=====================================================================*/

	/**
	 * Flags for required item info.
	 * These are used for $required_info and $loaded_info.
	 *
	 * Note: INFO_CONTENT is a placeholder for child implementations.
	 */
	//const INFO_NODE = 2;

	/**
	 * The total flags for all info.
	 * This would be a constant if we had late static binding.
	 *
	 * @var int
	 */
	//protected $INFO_ALL = 3;

	/**
	 * Map of query => info.
	 *
	 * @var array int => int
	 */
//	protected $query_info = array(
//		self::QUERY_BASIC => /* self::INFO_BASIC | self::INFO_NODE */ 3,
//	);

	/*ModelProperties===============================================================*/

	/**
	 * Rate model properties.
	 *
	 * @var array string
	 */
	protected $item_properties = array(
		'rateid', 'nodeid', 'userid', 'vote', 'ipaddress'
	);

	/*INFO_BASIC==================*/

	/**
	 * The id of the rate.
	 *
	 * @var int
	 */
	protected $rateid;

	/**
	 * The id of the node.
	 *
	 * @var int
	 */
	protected $nodeid;

	/**
	 * The userid of the voter.
	 *
	 * @var int
	 */
	protected $userid;

	/**
	 * The vote.
	 *
	 * @var int
	 */
	protected $vote;

	/**
	 * The ipaddress of the voter.
	 *
	 * @var string
	 */
	protected $ipaddress;

	protected $query_hook = 'vbcms_rate_querydata';
	/*LoadInfo======================================================================*/

	/**
	 * Fetches the SQL for loading.
	 *
	 * @param int $required_query				- The required query
	 * @param boolean $force_rebuild			- Added for PHP 5.4 strict standards compliance
	 * 
	 * @return string
	 */
	protected function getLoadQuery($required_query = '', $force_rebuild = false)
	{
		// Hooks should check the required query before populating the hook vars
		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook($this->query_hook)) ? eval($hook) : false;

		if (self::QUERY_BASIC == $required_query)
		{
			$sql = "SELECT rate.rateid,
						rate.nodeid, rate.userid, rate.vote, rate.ipaddress
						$hook_query_fields
					FROM " . TABLE_PREFIX . "cms_rate AS rate
					$hook_query_joins
					WHERE";

			if (is_numeric($this->itemid))
			{
				$sql .= ' rate.rateid = ' . intval($this->itemid);
			}
			else if (is_numeric($this->nodeid))
			{
				$sql .= ' rate.rateid = ' . intval($this->rate.rateid);
			}
			$sql .= ' ' . $hook_query_where;

			return $sql;
		}

		return parent::getLoadQuery($required_query);
	}


	/**** returns the rating ID from the record
	 *
	 * @return int
	 ****/
	public function getRateId()
	{
		$this->Load();
		return $this->rateid;
	}


	/**** returns the nodeid from the record
	 *
	 * @return int
	 ****/
	public function getNodeId()
	{
		$this->Load();
		return $this->nodeid;
	}


	/**** returns the user id from the record
	 *
	 * @return int
	 ****/
	public function getUserId()
	{
		$this->Load();
		return $this->userid;
	}


	/**** returns the vote from the record
	 *
	 * @return int
	 ****/
	public function getVote()
	{
		$this->Load();
		return $this->vote;
	}


	/**** returns the ip address from the record
	 *
	 * @return string
	 ****/
	public function getIPAddress()
	{
		$this->Load();
		return $this->ipaddress;
	}
}