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
* Class to do data save/delete operations for blog users
*
* @package	vBulletin
* @version	$Revision: 56108 $
* @date		$Date: 2011-12-02 13:17:10 -0800 (Fri, 02 Dec 2011) $
*/
class vB_DataManager_Blog_TrackBack extends vB_DataManager
{
	/**
	* Array of recognised and required fields for threadrate, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'blogtrackbackid' => array(TYPE_UINT,       REQ_INCR),
		'blogid'          => array(TYPE_UINT,       REQ_YES, VF_METHOD),
		'title'           => array(TYPE_NOHTMLCOND, REQ_YES, VF_METHOD),
		'snippet'         => array(TYPE_NOHTMLCOND, REQ_YES, VF_METHOD),
		'url'             => array(TYPE_STR,        REQ_YES),
		'state'           => array(TYPE_STR,        REQ_NO,  'if (!in_array($data, array(\'visible\', \'moderation\'))) { $data = \'moderation\'; } return true; '),
		'userid'          => array(TYPE_UINT,       REQ_YES),
		'dateline'        => array(TYPE_UNIXTIME,   REQ_YES),
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('blogtrackbackid= %1$s', 'blogtrackbackid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'blog_trackback';

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Blog_Trackback(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('blog_trackbackdata_start')) ? eval($hook) : false;
	}

	function verify_blogid(&$blogid)
	{
		require_once(DIR . '/includes/blog_functions.php');
		if (!($this->info['bloginfo'] = fetch_bloginfo($blogid)))
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Verifies the title. Does the same processing as the general title verifier,
	* but also requires there be a title.
	*
	* @param	string	Title text
	*
	* @return	bool	Whether the title is valid
	*/
	function verify_title(&$title)
	{
		// replace html-encoded spaces with actual spaces
		$title = preg_replace('/&#(0*32|x0*20);/', ' ', $title);

		$title = trim($title);

		if ($title == '')
		{
			$this->error('invalid_title_specified');
			return false;
		}

		return true;
	}

	/**
	* Verifies the snippet is valid
	*
	* @param	string	Snippet
	*
	* @param	bool	Whether the text is valid
	*/
	function verify_snippet(&$snippet)
	{
		if (empty($this->info['skip_charcount']))
		{
			// replace html-encoded spaces with actual spaces
			$snippet = preg_replace('/&#(0*32|x0*20);/', ' ', $snippet);
			$snippet = trim($snippet);

			// should this be a setting?
			$minchars = 1;
			if (vbstrlen($snippet) < $minchars)
			{
				$this->error('tooshort', $minchars);
				return false;
			}
		}

		return true;
	}

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->condition)
		{
			if (!($blogid = $this->fetch_field('blogid')))
			{
				global $vbphrase;
				$this->error('invalidid', $vbphrase['blog'], $this->registry->options['contactuslink']);
				return false;
			}

			if (!($url = $this->fetch_field('url')))
			{
				$this->error('no_url_specified');
				return false;
			}

			if (!$this->fetch_field('state'))
			{
				$this->set('state', 'moderation');
			}
			if (!$this->fetch_field('dateline'))
			{
				$this->set('dateline', TIMENOW);
			}

			$checkurl = $this->registry->options['bburl'];
			($hook = vBulletinHook::fetch_hook('blog_trackbackdata_presave_start')) ? eval($hook) : false;

			if (!$this->fetch_field('title') OR !$this->fetch_field('snippet'))
			{
				require_once(DIR . '/includes/functions_file.php');
				if ($bodyresult = fetch_body_request($url, 100000))
				{
					if (preg_match('#<head[^>]*>.*<title>(.*)</title>.*</head>.*<body(.*?)#siU', $bodyresult, $matches))
					{
						$body =& $matches[2];
						if (!$this->fetch_field('title'))
						{
							$this->set('title', $matches[1]);
						}
						else
						{
							$this->error('invalid_title_specified');
							return false;
						}

						if (!$this->fetch_field('snippet'))
						{
							if (preg_match('#(<a[^>]+href=(\'|")' . preg_quote($checkurl, '#') . '\/blog(?:_callback)?.php\?b(?:logid)?=' . $blogid . '\\2[^>]*>(.*)</a>)#siU', $body, $matches))
							{
								$hash = md5(TIMENOW . SCRIPTPATH . SESSION_IDHASH . SESSION_HOST . vbrand(1, 1000000));
								$body = str_replace($matches[1], "<$hash>" . $matches[3] . "</$hash>", $body);
								$body = strip_tags($body, "<$hash>");
								$start = strpos($body, "<$hash>" . $matches[3] . "</$hash>");
								$length = strlen("<$hash>" . $matches[3] . "</$hash>");
								$snippet = str_replace(
									array(
										"<$hash>",
										"</$hash>",
									),
									array(
										'',
										'',
									),
									trim(substr($body, $start - 100, $length + 200))
								);
								$this->set('snippet', $snippet);
							}
							else
							{
								$this->error('could_not_parse_link_href_from_link');
								return false;
							}
						}

						return true;
					}
					else
					{
						$this->error('failed_to_parse_html_body');
						return false;
					}
				}
				else
				{
					$this->error('failed_to_retrieve_body_' . $url);
					return false;
				}
			}

			if ($this->fetch_field('state') == 'visible' AND !$this->info['skip_akismet'])
			{
				$akismet_url = fetch_seo_url('bloghome|nosession|bburl', array());
				$permalink = fetch_seo_url('entry|nosession|bburl', fetch_bloginfo($this->fetch_field('blogid')));
				if (!empty($this->registry->options['vb_antispam_key']))
				{ // global key, use the global URL aka blog.php
					$akismet_key = $this->registry->options['vb_antispam_key'];
				}
				else
				{
					//if feels like chaning the format of this url is a bad idea, so we'll force it to basic.
					//we don't have the blog title and we won't use it for basic urls anyway, but if we switch 
					//the format we'll need to fix that.
					require_once(DIR . '/includes/class_friendly_url.php');
					$akismet_url = vB_Friendly_Url::fetchLibrary($vbulletin, 'blog|nosession|bburl', 
						array('userid' => $this->fetch_field('userid')));
					$akismet_url = $akismet_url->get_url(FRIENDLY_URL_OFF);

					$akismet_key = $this->info['akismet_key'];
				}

				if (!empty($akismet_key))
				{
					// these are taken from the Akismet API: http://akismet.com/development/api/
					$akismet_data = array();
					$akismet_data['user_ip'] = IPADDRESS;
					$akismet_data['user_agent'] = USER_AGENT;
					$akismet_data['permalink'] = $permalink;
					$akismet_data['comment_type'] = 'trackback';
					$akismet_data['comment_author_url'] = $this->fetch_field('url');
					$akismet_data['comment_content'] = $this->fetch_field('snippet');
					if (verify_akismet_status($akismet_key, $akismet_url, $akismet_data) == 'spam')
					{
						$this->set('state', 'moderation');
					}
				}
			}

		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('blog_trackbackdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		if (!$this->info['skip_build_blog_entry_counters'] AND $blogid = $this->existing['blogid'])
		{
				build_blog_entry_counters($blogid);
		}

		if ($blogid = intval($this->existing['blogid']) AND $this->existing['url'] AND $this->info['delete_ping_history'])
		{
			$this->dbobject->query_write("
				DELETE FROM " . TABLE_PREFIX . "blog_pinghistory
				WHERE blogid = $blogid AND
					sourcemd5 = '" . md5($this->existing['url']) . "'
			");
		}

		($hook = vBulletinHook::fetch_hook('blog_trackbackdata_delete')) ? eval($hook) : false;
	}


	/**
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		build_blog_entry_counters($this->fetch_field('blogid'));

		($hook = vBulletinHook::fetch_hook('blog_trackbackdata_postsave')) ? eval($hook) : false;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 56108 $
|| ####################################################################
\*======================================================================*/
?>
