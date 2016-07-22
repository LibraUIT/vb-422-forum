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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/class_xmlrpc.php');

/**
* vBulletin XML-RPC Server Pingback Object
*
* This class provides the pingback.ping method
*/
class vB_XMLRPC_Server_Pingback extends vB_XMLRPC_Server
{
	/**
	* Constructor
	*
	* @param	object	vBulletin Registry Object
	*/
	function vB_XMLRPC_Server_Pingback(&$registry)
	{
		parent::vB_XMLRPC_Server($registry);

		// Add our methods
		$this->add_method('pingback.ping', 'pingback_ping');
	}

	/**
	* Verify parameters match
	*
	* @var	array
	*
	* @return boolean
	*/
	function verify_pingback_ping(&$pinfo)
	{
		$params = array(
			'string',
			'string',
		);

		require_once(DIR . '/includes/blog_functions_post.php');

		$checkurl = $this->registry->options['bburl'];
		($hook = vBulletinHook::fetch_hook('xmlrpc_verify_pingback')) ? eval($hook) : false;

		if ($this->build_xmlrpc_array($params, $pinfo))
		{
			// XML-RPC is valid if we are here
			// 1 - Verify that the second URL matches the URL to our blog but don't validate the blogid here
			// 2 - Insert the information into the blog_pinghistory table
			// 3 - Cron script will verify the entries and insert pingbacks
			// This allows us to kill floods for the most part

			if (!empty($this->xmlrpc_array[0]['string']))
			{
				if (preg_match('#^' . preg_quote($checkurl, '#') . '\/blog(?:_callback)?.php\?b(?:logid)?=(\d+)$#si', trim($this->xmlrpc_array[1]['string']), $matches))
				{
					$blogid = intval($matches[1]);
					$sourcemd5 = md5(trim($this->xmlrpc_array[0]['string']));

					if ($blogid)
					{
						$result = $this->registry->db->query_write("
							INSERT IGNORE INTO " . TABLE_PREFIX . "blog_pinghistory
								(blogid, sourcemd5, sourceurl, dateline)
							VALUES
								($blogid, '$sourcemd5', '" . $this->registry->db->escape_string(trim($this->xmlrpc_array[0]['string'])) . "', " . TIMENOW . ")
						");

						if ($this->registry->db->affected_rows($result))
						{
							$this->build_xml_response('accepted');

							require_once(DIR . '/includes/blog_functions.php');
							if ($bloginfo = fetch_bloginfo($blogid))
							{
								if ($bloginfo['state'] == 'visible')
								{
									cache_permissions($bloginfo, false);
									// verify user has permission to receive pingbacks
									if ($bloginfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canreceivepingback'])
									{
										$dataman =& datamanager_init('Blog_Trackback', $this->registry, ERRTYPE_ARRAY);
										$dataman->set('blogid', $blogid);
										$dataman->set('url', trim($this->xmlrpc_array[0]['string']));
										$dataman->set('userid', $bloginfo['userid']);
										$dataman->set_info('akismet_key', $bloginfo['akismet_key']);
										$dataman->pre_save();

										if (!empty($dataman->errors))
										{
											write_trackback_log('pingback', 'in', 6, array('GLOBALS' => $GLOBALS['HTTP_RAW_POST_DATA'], 'errors' => $dataman->errors));
										}
										else
										{
											$dataman->save();
											write_trackback_log('pingback', 'in', 0, $GLOBALS['HTTP_RAW_POST_DATA']);
										}
									}
									else
									{
										write_trackback_log('pingback', 'in', 4, $GLOBALS['HTTP_RAW_POST_DATA']);
									}
								}
								else
								{
									write_trackback_log('pingback', 'in', 7, $GLOBALS['HTTP_RAW_POST_DATA']);
								}
							}
							else
							{
								write_trackback_log('pingback', 'in', 5, $GLOBALS['HTTP_RAW_POST_DATA']);
							}

							return true;
						}
						else
						{
							write_trackback_log('pingback', 'in', 3, $GLOBALS['HTTP_RAW_POST_DATA']);
						}
					}
					else
					{
						write_trackback_log('pingback', 'in', 2, $GLOBALS['HTTP_RAW_POST_DATA']);
					}
				}
				else
				{
					write_trackback_log('pingback', 'in', 2, $GLOBALS['HTTP_RAW_POST_DATA']);
				}
			}
			else
			{
				write_trackback_log('pingback', 'in', 1, $GLOBALS['HTTP_RAW_POST_DATA']);
			}
		}
		else
		{
			write_trackback_log('pingback', 'in', 1, $GLOBALS['HTTP_RAW_POST_DATA']);
		}

		$xml_error_struct = $this->build_fault_struct(-32500, $this->faultcodes['-32500']);
		$this->build_xml_response($xml_error_struct, true);

		// $this->build_xmlrpc_array sets build_xml_response() on failure
		return false;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 56108 $
|| ####################################################################
\*======================================================================*/
?>
