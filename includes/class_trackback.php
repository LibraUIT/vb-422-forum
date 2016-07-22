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

require_once(DIR . '/includes/class_vurl.php');

/**
* vBulletin Trackback Class
*
* This class handles sending and receiving trackback responses
*/
class vB_Trackback_Client
{
	/**
	* vBulletin Registry Object
	*
	* @string
	*/
	var $registry = null;

	/**
	* Constructor
	*
	* @param	object	vBulletin Registry Object
	*/
	function vB_Trackback_Client(&$registry)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error('vB_Trackback::Registry object is not an object', E_USER_ERROR);
		}
	}

	function send_ping($pingurl, $url, $title = '', $excerpt = '', $blog_title = '')
	{
		$params = array(
			'url'        => $url,
			'title'      => $title,
			'excerpt'    => $excerpt,
			'blog_title' => $blog_title,
		);

		foreach($params AS $key => $val)
		{
			if (!empty($val))
			{
				$query[] = $key . '=' . urlencode($val);
			}
		}

		$vurl = new vB_vURL($this->registry);
		$vurl->set_option(VURL_URL, $pingurl);
		$vurl->set_option(VURL_POST, 1);
		$vurl->set_option(VURL_HEADER, 1);
		$vurl->set_option(VURL_ENCODING, 'gzip');
		$vurl->set_option(VURL_POSTFIELDS, implode('&', $query));
		$vurl->set_option(VURL_RETURNTRANSFER, 1);
		$vurl->set_option(VURL_CLOSECONNECTION, 1);
		return $vurl->exec();
	}
}

class vB_Trackback_Server
{
	/**
	* vBulletin Registry Object
	*
	* @string
	*/
	var $registry = null;

	/**
	* vBulletin XML Object
	*
	* @var	Object
	*/
	var $xml_object = null;

	/**
	* Blogid
	*
	* @var	int
	*/
	var $blogid = null;

	/**
	* Source URL
	*
	* @var	String
	*/
	var $sourceurl = null;

	/**
	* Constructor
	*
	* @param	object	vBulletin Registry Object
	*/
	function vB_Trackback_Server(&$registry)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error('vB_Trackback::Registry object is not an object', E_USER_ERROR);
		}
	}

	/**
	* Parse Blogid from SCRIPTPATH equivalent
	*
	* @param	string	String containing 'blogid=12345'
	* @param	string	Source URL
	*
	* @return	bool
	*/
	function parse_blogid($string, $url)
	{
		if (preg_match('#\?b(?:logid)?=(?!0)(\d+)#', $string, $matches))
		{
			$this->blogid = intval($matches[1]);
			$this->sourceurl = $url;
			return true;
		}
		else
		{
			return false;
		}
	}

	function send_xml_response()
	{
		require_once(DIR . '/includes/class_xml.php');
		$this->xml_object = new vB_XML_Builder($this->registry);
		$this->xml_object->doc = '';

		$this->xml_object->add_group('response');
		if ($this->sourceurl AND $this->registry->options['vbblog_trackback'])
		{
			$sourcemd5 = md5($this->sourceurl);
			$result = $this->registry->db->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "blog_pinghistory
					(blogid, sourcemd5, sourceurl, dateline)
				VALUES ({$this->blogid}, '$sourcemd5', '" . $this->registry->db->escape_string($this->sourceurl) . "', " . TIMENOW . ")
			");

			require_once(DIR . '/includes/blog_functions_post.php');
			if ($this->registry->db->affected_rows($result))
			{
				require_once(DIR . '/includes/blog_functions.php');
				if ($bloginfo = fetch_bloginfo($this->blogid))
				{
					if ($bloginfo['state'] == 'visible')
					{
						cache_permissions($bloginfo, false);
						if ($bloginfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canreceivepingback'])
						{
							// verify user has permission to receive trackbacks
							$dataman =& datamanager_init('Blog_Trackback', $this->registry, ERRTYPE_SILENT);
							$dataman->set('blogid', $this->blogid);
							$dataman->set('url', $this->sourceurl);
							$dataman->set('userid', $bloginfo['userid']);
							$dataman->set_info('akismet_key', $bloginfo['akismet_key']);

							if (!empty($dataman->errors))
							{
								write_trackback_log('trackback', 'in', 6, array('GLOBALS' => '', 'errors' => $dataman->errors), $bloginfo, $this->sourceurl);
							}
							else
							{
								$dataman->save();
								write_trackback_log('trackback', 'in', 0, '', $bloginfo, $this->sourceurl);
							}
						}
						else
						{
							write_trackback_log('trackback', 'in', 4, '', $bloginfo, $this->sourceurl);
						}
					}
					else
					{
						write_trackback_log('trackback', 'in', 7, '', $bloginfo, $this->sourceurl);
					}
				}
				else
				{
					write_trackback_log('trackback', 'in', 5, '', array(), $this->sourceurl);
				}

				if (defined('NOSHUTDOWNFUNC'))
				{
					$this->registry->db->close();
				}

				$this->xml_object->add_tag('error', 0);
				$this->xml_object->close_group('response');
				$this->xml_object->send_content_type_header();
				$this->xml_object->send_content_length_header();
				echo $this->xml_object->fetch_xml_tag() . $this->xml_object->output();
				return;
			}
			else
			{
				write_trackback_log('trackback', 'in', 3, '', array(), $this->sourceurl);
			}
		}

		if (defined('NOSHUTDOWNFUNC'))
		{
			$this->registry->db->close();
		}

		$this->xml_object->add_tag('error', 1);
		$this->xml_object->add_tag('message', 'Invalid');
		$this->xml_object->close_group('response');
		$this->xml_object->send_content_type_header();
		$this->xml_object->send_content_length_header();
		echo $this->xml_object->fetch_xml_tag() . $this->xml_object->output();
		return;
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>