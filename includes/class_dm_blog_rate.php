<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 4.2.2 - Nulled By VietVBB Team
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

if (!class_exists('vB_DataManager', false))
{
	exit;
}

/**
* Class to do data save/delete operations for Blog ratings
*
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_Blog_Rate extends vB_DataManager
{
	/**
	* Array of recognised and required fields for blograte, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'blograteid' => array(TYPE_UINT, REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'blogid'     => array(TYPE_UINT, REQ_YES),
		'userid'     => array(TYPE_UINT, REQ_YES,  VF_METHOD, 'verify_userid'),
		'vote'       => array(TYPE_INT,  REQ_YES,  VF_METHOD, 'verify_vote'), # TYPE_INT to allow negative rating
		'ipaddress'  => array(TYPE_STR,  REQ_AUTO, VF_METHOD, 'verify_ipaddress')
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('blograteid = %1$s', 'blograteid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'blog_rate';

	/**
	* The maximum vote
	*
	* @var	int
	*/
	var $max_vote = 5;

	/**
	* Array to store stuff to save to blograte table
	*
	* @var	array
	*/
	var $blograte = array();

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Blog_Rate(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('blog_ratedata_start')) ? eval($hook) : false;
	}

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->condition AND $this->fetch_field('userid') == $this->registry->userinfo['userid'] AND !$this->fetch_field('ipaddress'))
		{
			$this->set('ipaddress', IPADDRESS);
		}

		if (!$this->condition AND empty($this->info['skip_dupe_check']))
		{
			if ($userid = intval($this->fetch_field('userid')))
			{
				$exists = $this->dbobject->query_first("
					SELECT *
					FROM " . TABLE_PREFIX . "blog_rate
					WHERE userid = $userid
						AND blogid = " . intval($this->blograte['blogid'])
				);
			}
			else if ($ipaddress = $this->fetch_field('ipaddress'))
			{
				$exists = $this->dbobject->query_first("
					SELECT *
					FROM " . TABLE_PREFIX . "blog_rate
					WHERE userid = 0
						AND blogid = " . intval($this->blograte['blogid']) . "
						AND ipaddress = '" . $this->dbobject->escape_string($ipaddress) . "'
				");
			}

			if ($exists)
			{
				$this->set_existing($exists);
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('blog_ratedata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}


	/**
	* Removing 1 from the rating count for the blog entry
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		if ($this->info['blog'])
		{
			$bloginfo =& $this->info['blog'];
		}
		else
		{
			$bloginfo = fetch_bloginfo($this->fetch_field('blogid'));
		}

		$blogman =& datamanager_init('Blog', $vbulletin, ERRTYPE_SILENT, 'blog');
		$blogman->set_existing($bloginfo);
		$blogman->set('ratingtotal', 'ratingtotal - ' . intval($this->fetch_field('vote')), false);
		$blogman->set('ratingnum', 'ratingnum - 1', false);
		$blogman->set('rating', 'ratingtotal / ratingnum', false);
		$blogman->save();

		build_blog_user_counters($bloginfo['userid']);

		($hook = vBulletinHook::fetch_hook('blog_ratedata_delete')) ? eval($hook) : false;

		return true;
	}


	/**
	* Updating the votecount for that thread
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		// Are we handleing a multi DM
		if (!$this->condition OR $this->existing['vote'] != $this->fetch_field('vote'))
		{
			if ($this->info['blog'])
			{
				$bloginfo =& $this->info['blog'];
			}
			else
			{ 
				$bloginfo = fetch_bloginfo($this->fetch_field('blogid'));
			}

			if (!$this->condition)
			{
				// Increment the vote count for the thread that has just been voted on
				$blogman =& datamanager_init('Blog', $this->registry, ERRTYPE_SILENT, 'blog');
				$blogman->set_existing($bloginfo);
				$blogman->set('ratingtotal', "ratingtotal + " . intval($this->fetch_field('vote')), false);
				$blogman->set('ratingnum', 'ratingnum + 1', false);
				$blogman->set('rating', 'ratingtotal / ratingnum', false);
				$blogman->save();
			}
			else
			{
				// this is an update
				$votediff = $this->fetch_field('vote') - $this->existing['vote'];

				$blogman =& datamanager_init('Blog', $this->registry, ERRTYPE_SILENT, 'blog');
				$blogman->set_existing($bloginfo);
				$blogman->set('ratingtotal', "ratingtotal + $votediff", false);
				$blogman->set('rating', "ratingtotal / ratingnum", false);
				$blogman->save();
			}

			build_blog_user_counters($bloginfo['userid']);

			if ($this->fetch_field('userid') == $this->registry->userinfo['userid'])
			{
				set_bbarray_cookie('blog_rate', $this->fetch_field('blogid'), $this->fetch_field('vote'), 1);
			}
		}

		($hook = vBulletinHook::fetch_hook('blog_ratedata_postsave')) ? eval($hook) : false;
	}


	/**
	* Verifies that the specified user exists
	*
	* @param	integer	User ID
	*
	* @return 	boolean	Returns true if user exists
	*/
	function verify_userid(&$userid)
	{
		if ($userid == 0 OR $userid == $this->registry->userinfo['userid'] OR $this->dbobject->query_first("SELECT * FROM " . TABLE_PREFIX . "user WHERE userid = $userid"))
		{
			return true;
		}
		else
		{
			global $vbphrase;
			$this->error('invalidid', $vbphrase['user'], $this->registry->options['contactuslink']);
			return false;
		}
	}


	/**
	* Checks that the vote is between 0 and 5
	*
	* @param	integer	The vote
	*
	* @return	boolean	Returns true on success
	*/
	function verify_vote(&$vote)
	{
		if (is_int($vote) AND $vote >= 0 AND $vote <= $this->max_vote)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>
