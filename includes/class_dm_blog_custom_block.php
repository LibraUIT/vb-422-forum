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

if (!class_exists('vB_DataManager', false))
{
	exit;
}

/**
* Class to do data save/delete operations for profile messages
*
* @package	vBulletin
* @version	$Revision: 26654 $
* @date		$Date: 2008-05-20 12:56:00 -0700 (Tue, 20 May 2008) $
*
*/
class vB_DataManager_Blog_Custom_Block extends vB_DataManager
{
	/**
	* Array of recognised and required fields for sidebar block, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'customblockid'  => array(TYPE_UINT,       REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'userid'         => array(TYPE_UINT,       REQ_YES,  VF_METHOD),
		'dateline'       => array(TYPE_UNIXTIME,   REQ_AUTO),
		'title'          => array(TYPE_NOHTMLCOND, REQ_YES,  VF_METHOD),
		'pagetext'       => array(TYPE_STR,        REQ_YES,  VF_METHOD),
		'allowsmilie'    => array(TYPE_UINT,       REQ_NO),
		'type'           => array(TYPE_STR,        REQ_NO,   VF_METHOD),
		'displayorder'   => array(TYPE_UINT,       REQ_NO),
		'location'       => array(TYPE_NOHTML,     REQ_NO, 'if (!in_array($data, array(\'top\', \'side\'))) { $data = \'none\'; } return true;'),
		'reportthreadid' => array(TYPE_UINT,       REQ_NO),
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('customblockid = %1$s', 'customblockid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'blog_custom_block';

	function verify_type(&$type)
	{
		if (!$this->fetch_field('userid'))
		{
			trigger_error('vB_Datamanager_Blog_Custom_Block(): Must set \'userid\' before \'type\'.', E_USER_ERROR);
		}

		if (!in_array($type, array('block', 'page')))
		{
			$type = 'block';
		}

		$blocks = $this->registry->db->query_first("
			SELECT COUNT(*) AS count
			FROM " . TABLE_PREFIX . "blog_custom_block
			WHERE
				userid = " . intval($this->fetch_field('userid')) . "
					AND
				type = '" . $this->registry->db->escape_string($type) . "'
		");

		if ($type == 'page' AND $blocks['count'] >= $this->info['user']['permissions']['vbblog_custompages'])
		{
			$this->error('limited_to_x_custom_pages', $this->info['user']['permissions']['vbblog_custompages']);
			return false;
		}
		else if ($type == 'block' AND $blocks['count'] >= $this->info['user']['permissions']['vbblog_customblocks'])
		{
			$this->error('limited_to_x_custom_blocks', $this->info['user']['permissions']['vbblog_customblocks']);
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Verifies the title is valid and sets up the title for saving (wordwrap, censor, etc).
	*
	* @param	string	Title text
	*
	* @param	bool	Whether the title is valid
	*/
	function verify_title(&$title)
	{
		// replace html-encoded spaces with actual spaces
		$title = preg_replace('/&#(0*32|x0*20);/', ' ', $title);

		$title = trim($title);

		if (empty($title))
		{
			$this->error('notitleandmessage');
			return false;
		}

		if ($this->registry->options['titlemaxchars'] AND $title != $this->existing['title'])
		{
			if (!empty($this->info['show_title_error']))
			{
				if (($titlelen = vbstrlen($title)) > $this->registry->options['titlemaxchars'])
				{
					// title too long
					$this->error('title_toolong', $titlelen, $this->registry->options['titlemaxchars']);
					return false;
				}
			}
			else if (empty($this->info['is_automated']))
			{
				// not showing the title length error, just chop it
				$title = vbchop($title, $this->registry->options['titlemaxchars']);
			}
		}

		require_once(DIR . '/includes/functions_newpost.php');
		// censor, and htmlspecialchars title
		$title = fetch_censored_text($title);

		// do word wrapping
		$title = fetch_word_wrapped_string($title);

		return true;
	}

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Blog_Custom_Block(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('blogsidebarblock_start')) ? eval($hook) : false;
	}


	/**
	 * Code to run before saving a Custom Block
	 *
	 * @param	booleaan	Should we actually do the query?
	 *
	 */
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->condition)
		{
			if ($this->fetch_field('dateline') === null)
			{
				$this->set('dateline', TIMENOW);
			}
		}

		if (!$this->info['user'])
		{
			if ($this->existing['userinfo'])
			{
				$this->set_info('user', $this->existing['userinfo']);
			}
			else
			{
				trigger_error('vB_Datamanager_Blog_Custom_Block(): "info[\'user\']" must be set.', E_USER_ERROR);
			}
		}

		if (!$this->fetch_field('type'))
		{
			trigger_error('vB_Datamanager_Blog_Custom_Block(): \'type\' must be set.', E_USER_ERROR);
		}

		if ($this->fetch_field('type') == 'block')
		{
			$this->registry->options['maximages'] = $this->registry->options['vbblog_blockmaximages'];
			$this->registry->options['maxvideos'] = $this->registry->options['vbblog_blockmaxvideos'];
		}
		else
		{
			$this->registry->options['maximages'] = $this->registry->options['vbblog_pagemaximages'];
			$this->registry->options['maxvideos'] = $this->registry->options['vbblog_pagemaxvideos'];
		}
		if (!$this->verify_image_count('pagetext', 'allowsmilie', 'blog_entry'))
		{
			return false;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('blogsidebarblock_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	 * Code to delete a Sidebar Block
	 *
	 * @param boolean $doquery Added for PHP 5.4 strict standards compliance
	 *
	 * @return	Whether this code successfully completed
	 *
	 */
	public function delete($doquery = true)
	{
		if ($customblockid = $this->existing['customblockid'])
		{
			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "blog_custom_block WHERE customblockid = $customblockid
			");

			if ($userid = $this->existing['userid'])
			{
				$this->registry->db->query_write("
					DELETE FROM " . TABLE_PREFIX . "blog_custom_block_parsed
					WHERE userid = $userid
				");
				$blocks = $this->registry->db->query_first("
					SELECT COUNT(*) AS count
					FROM " . TABLE_PREFIX . "blog_custom_block
					WHERE
						userid = $userid
							AND
						type = 'block'
				");
				$dataman =& datamanager_init('Blog_User', $this->registry, ERRTYPE_SILENT);
				$foo = array('bloguserid' => $userid);
				$dataman->set_existing($foo);
				$dataman->set('customblocks', $blocks['count']);
				$dataman->save();
			}

			if ($this->fetch_field('type') == 'block')
			{
				$this->build_block_cache('delete');
			}

			if ($this->fetch_field('type') == 'page' AND $userid)
			{
				$links = array();
				// Build datastore
				$pages = $this->registry->db->query_read("
					SELECT customblockid, location, title
					FROM " . TABLE_PREFIX . "blog_custom_block
					WHERE
						userid = $userid
							AND
						type = 'page'
							AND
						location <> 'none'
					ORDER BY displayorder
				");
				while ($page = $this->registry->db->fetch_array($pages))
				{
					$links["$page[location]"][] = array(
						'i' => $page['customblockid'],
						't' => $page['title'],
					);
				}

				$dataman =& datamanager_init('Blog_User', $this->registry, ERRTYPE_STANDARD);
				if ($this->info['user']['bloguserid'])
				{
					$foo = array('bloguserid' => $this->info['user']['bloguserid']);
					$dataman->set_existing($foo);
				}
				else
				{
					$dataman->set('bloguserid', $this->info['user']['userid']);
				}

				$dataman->set('custompages', $links);
				$dataman->save();
			}

			#($hook = vBulletinHook::fetch_hook('visitormessagedata_delete')) ? eval($hook) : false;
			return true;
		}

		return false;
	}

	/**
	* Code to run after Saving a Custom Block
	*
	* @param	boolean	Do the query?
	*/
	function post_save_once($doquery = true)
	{
		$customblockid = intval($this->fetch_field('customblockid'));

		if ($userid = $this->fetch_field('userid'))
		{
			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "blog_custom_block_parsed
				WHERE userid = $userid
			");

			$blocks = $this->registry->db->query_first("
				SELECT COUNT(*) AS count
				FROM " . TABLE_PREFIX . "blog_custom_block
				WHERE
					userid = $userid
						AND
					type = 'block'
			");
			$dataman =& datamanager_init('Blog_User', $this->registry, ERRTYPE_SILENT);
			$foo = array('bloguserid' => $userid);
			$dataman->set_existing($foo);
			$dataman->set('customblocks', $blocks['count']);
			$dataman->save();
		}

		if (!$this->condition AND $this->fetch_field('type') == 'block')
		{
			$this->build_block_cache('add');
		}

		if ($this->fetch_field('type') == 'page' AND $userid)
		{
			$links = array();
			// Build datastore
			$pages = $this->registry->db->query_read("
				SELECT customblockid, location, title
				FROM " . TABLE_PREFIX . "blog_custom_block
				WHERE
					userid = $userid
						AND
					type = 'page'
						AND
					location <> 'none'
				ORDER BY displayorder
			");
			while ($page = $this->registry->db->fetch_array($pages))
			{
				$links["$page[location]"][] = array(
					'i' => $page['customblockid'],
					't' => $page['title'],
				);
			}

			$dataman =& datamanager_init('Blog_User', $this->registry, ERRTYPE_STANDARD);
			if ($this->info['user']['bloguserid'])
			{
				$foo = array('bloguserid' => $this->info['user']['bloguserid']);
				$dataman->set_existing($foo);
			}
			else
			{
				$dataman->set('bloguserid', $this->info['user']['userid']);
			}

			$dataman->set('custompages', $links);
			$dataman->save();
		}

		($hook = vBulletinHook::fetch_hook('blogsidebarblock_postsave')) ? eval($hook) : false;
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
		if ($userid == $this->registry->userinfo['userid'])
		{
			$this->info['user'] =& $this->registry->userinfo;
			$return = true;
		}
		else if ($userinfo = fetch_userinfo($userid))
		{	// This case should hit the cache most of the time
			$this->info['user'] =& $userinfo;
			$return = true;
		}
		else
		{
			$this->error('no_users_matched_your_query');
			$return = false;
		}

		return $return;
	}

	/**
	* Verifies the page text is valid and sets it up for saving.
	*
	* @param	string	Page text
	*
	* @param	bool	Whether the text is valid
	* @param	bool	Whether to run the case stripper
	*/
	function verify_pagetext(&$pagetext, $noshouting = false)
	{
		if (empty($this->info['skip_charcount']))
		{
			switch ( $this->fetch_field('type') )
			{
				case 'block':
					$maxchars = $this->registry->options['vbblog_blockmaxchars'];
					break;
				case 'page':
					$maxchars = $this->registry->options['vbblog_pagemaxchars'];
					break;
				default:
					trigger_error('vB_Datamanager_Blog_Custom_Block(): \'type\' must be set to either \'block\' or \'page\'.', E_USER_ERROR);
					break;
			}
			
			if ($maxchars != 0 AND ($postlength = vbstrlen($pagetext)) > $maxchars)
			{
				$this->error('toolong', $postlength, $maxchars);
				return false;
			}

			$this->registry->options['postminchars'] = intval($this->registry->options['postminchars']);
			if ($this->registry->options['postminchars'] <= 0)
			{
				$this->registry->options['postminchars'] = 1;
			}
			if (vbstrlen(strip_bbcode($pagetext, $this->registry->options['ignorequotechars'])) < $this->registry->options['postminchars'])
			{
				$this->error('tooshort', $this->registry->options['postminchars']);
				return false;
			}
		}

		return parent::verify_pagetext($pagetext, $noshouting);

	}

	function build_block_cache($type = 'add')
	{
		$update = false;
		if (is_array($this->info['user']['sidebar']) AND $this->info['user']['userid'] AND isset($this->info['user']['bloguserid']))
		{
			$sidebar = $this->info['user']['sidebar'];
			if ($type == 'add')
			{
				if (!isset($sidebar['custom' . $this->fetch_field('customblockid')]) AND $this->fetch_field('type') == 'block')
				{
					$sidebar['custom' . $this->fetch_field('customblockid')] = 1;
					$update = true;
				}
			}
			else if ($type == 'delete')
			{
				unset($sidebar['custom' . $this->fetch_field('customblockid')]);
				$update = true;
			}

			if ($update)
			{
				$dataman =& datamanager_init('Blog_User', $this->registry, ERRTYPE_SILENT);
				if ($this->info['user']['bloguserid'])
				{
					$foo = array('bloguserid' => $this->info['user']['bloguserid']);
					$dataman->set_existing($foo);
				}
				else
				{
					$dataman->set('bloguserid', $this->info['user']['userid']);
				}
				$dataman->set('sidebar', $sidebar);
				$dataman->save();
			}
		}
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 26654 $
|| ####################################################################
\*======================================================================*/
?>
