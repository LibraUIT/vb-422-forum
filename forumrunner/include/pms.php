<?php
/*
 * Forum Runner
 *
 * Copyright (c) 2010-2011 to End of Time Studios, LLC
 *
 * This file may not be redistributed in whole or significant part.
 *
 * http://www.forumrunner.com
 */

chdir(MCWD);

chdir('../');

define('THIS_SCRIPT', 'forumrunner');
define('CSRF_PROTECTION', false);

require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/functions_misc.php');

if (!$vbulletin->options['enablepms'])
{
    json_error(strip_tags(fetch_error('pm_adminoff')), RV_NO_PM_ACCESS);
}

// check permission to use private messaging
if (($permissions['pmquota'] < 1 AND (!$vbulletin->userinfo['pmtotal'] OR in_array($_REQUEST['do'], array('insertpm', 'newpm')))) OR !$vbulletin->userinfo['userid'])
{
    json_error(ERR_NO_PERMISSION, RV_NO_PM_ACCESS);
}

if (!$vbulletin->userinfo['receivepm'] AND in_array($_REQUEST['do'], array('insertpm', 'newpm')))
{
    json_error(strip_tags(fetch_error('pm_turnedoff')), RV_NO_PM_ACCESS);
}

$vbulletin->input->clean_gpc('r', 'pmid', TYPE_UINT);

function parse_pm_bbcode($bbcode, $smilies = true)
{
	global $vbulletin;

	require_once(DIR . '/includes/class_bbcode.php');
	$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());
	return $bbcode_parser->parse($bbcode, 'privatemessage', $smilies);
}

function build_pm_counters()
{
	global $vbulletin;

	$pmcount = $vbulletin->db->query_first("
		SELECT
			COUNT(pmid) AS pmtotal,
			SUM(IF(messageread = 0 AND folderid >= 0, 1, 0)) AS pmunread
		FROM " . TABLE_PREFIX . "pm AS pm
		WHERE pm.userid = " . $vbulletin->userinfo['userid'] . "
	");

	$pmcount['pmtotal'] = intval($pmcount['pmtotal']);
	$pmcount['pmunread'] = intval($pmcount['pmunread']);

	if ($vbulletin->userinfo['pmtotal'] != $pmcount['pmtotal'] OR $vbulletin->userinfo['pmunread'] != $pmcount['pmunread'])
	{
		// init user data manager
		$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
		$userdata->set_existing($vbulletin->userinfo);
		$userdata->set('pmtotal', $pmcount['pmtotal']);
		$userdata->set('pmunread', $pmcount['pmunread']);
		$userdata->save();
	}
}

function
do_get_pm_folders ()
{
    global $vbulletin, $db;

    if (!$vbulletin->userinfo['userid']) {
	json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
    }

    $folders = $db->query_read_slave("
	SELECT pmfolders
	FROM " . TABLE_PREFIX . "usertextfield
	WHERE userid = " . $vbulletin->userinfo['userid']
    );
    $pmfolders = array();
    if ($folder = $db->fetch_array($folders)) {
	$pmfolders = unserialize($folder['pmfolders']);
	if (!is_array($pmfolders)) {
	    $pmfolders = array();
	}
	$pmfolders = array_map('prepare_utf8_string', $pmfolders);
    }
    return array(
	'folders' => $pmfolders,
    );
}

function
do_get_pms ()
{
    global $vbulletin, $db, $messagecounters;

    if (!$vbulletin->userinfo['userid']) {
	json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
    }

    $vbulletin->input->clean_array_gpc('r', array(
	'folderid'    => TYPE_INT,
	'perpage'     => TYPE_UINT,
	'pagenumber'  => TYPE_UINT,
    ));

    // Fetch PM unread count
    $result = $db->query_read_slave("
	SELECT COUNT(messageread) AS unread
	FROM " . TABLE_PREFIX . "pm
	WHERE userid = " . $vbulletin->userinfo['userid'] . "
	AND messageread = 0"
    );
    $unread = 0;
    if ($row = $db->fetch_array($result)) {
	$unread = $row['unread'];
    }

    $pm_out = array();

    // vBulletin Code Begin

	$folderjump = construct_folder_jump(0, $vbulletin->GPC['folderid']);
	$foldername = $foldernames["{$vbulletin->GPC['folderid']}"];

	// count receipts
	$receipts = $db->query_first_slave("
		SELECT
			SUM(IF(readtime <> 0, 1, 0)) AS confirmed,
			SUM(IF(readtime = 0, 1, 0)) AS unconfirmed
		FROM " . TABLE_PREFIX . "pmreceipt
		WHERE userid = " . $vbulletin->userinfo['userid']
	);

	// get ignored users
	$ignoreusers = preg_split('#\s+#s', $vbulletin->userinfo['ignorelist'], -1, PREG_SPLIT_NO_EMPTY);

	$totalmessages = intval($messagecounters["{$vbulletin->GPC['folderid']}"]);

	// build pm counters bar, folder is 100 if we have no quota so red shows on the main bar
	$tdwidth = array();
	$tdwidth['folder'] = ($permissions['pmquota'] ? ceil($totalmessages / $permissions['pmquota'] * 100) : 100);
	$tdwidth['total'] = ($permissions['pmquota'] ? ceil($vbulletin->userinfo['pmtotal'] / $permissions['pmquota'] * 100) - $tdwidth['folder'] : 0);
	$tdwidth['quota'] = 100 - $tdwidth['folder'] - $tdwidth['total'];

	$show['thisfoldertotal'] = iif($tdwidth['folder'], true, false);
	$show['allfolderstotal'] = iif($tdwidth['total'], true, false);
	$show['pmicons'] = iif($vbulletin->options['privallowicons'], true, false);

	// build navbar
	$navbits[''] = $foldernames["{$vbulletin->GPC['folderid']}"];

	if ($totalmessages == 0)
	{
		$show['messagelist'] = false;
	}
	else
	{
		$show['messagelist'] = true;

		$vbulletin->input->clean_array_gpc('r', array(
			'sort'        => TYPE_NOHTML,
		    'order'       => TYPE_NOHTML,
		    'searchtitle' => TYPE_NOHTML,
		    'searchuser'  => TYPE_NOHTML,
		    'startdate'   => TYPE_UNIXTIME,
			'enddate'     => TYPE_UNIXTIME,
			'searchread'  => TYPE_UINT
		));

		$search = array(
			'sort'       => (('sender' == $vbulletin->GPC['sort']) ? 'sender'
							 : (('title' == $vbulletin->GPC['sort']) ? 'title' : 'date')),
		    'order'      => (($vbulletin->GPC['order'] == 'asc') ? 'asc' : 'desc'),
		    'searchtitle'=> $vbulletin->GPC['searchtitle'],
		    'searchuser' => $vbulletin->GPC['searchuser'],
		    'startdate'  => $vbulletin->GPC['startdate'],
			'enddate'    => $vbulletin->GPC['enddate'],
			'read'       => $vbulletin->GPC['searchread']
		);

		// make enddate inclusive
		$search['enddate'] = ($search['enddate'] ? ($search['enddate'] + 86400) : 0);

		$show['openfilter'] = ($search['searchtitle'] OR $search['searchuser'] OR $search['startdate'] OR $search['enddate']);

		$sortfield = (('sender' == $search['sort']) ? 'pmtext.fromusername'
					  : (('title' == $search['sort'] ? 'pmtext.title' : 'pmtext.dateline')));
		$desc = ($search['order'] == 'desc');

		//($hook = vBulletinHook::fetch_hook('private_messagelist_filter')) ? eval($hook) : false;

		// get a sensible value for $perpage
		sanitize_pageresults($totalmessages, $vbulletin->GPC['pagenumber'], $vbulletin->GPC['perpage'], $vbulletin->options['pmmaxperpage'], $vbulletin->options['pmperpage']);

		// work out the $startat value
		$startat = ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->GPC['perpage'];
		$perpage = $vbulletin->GPC['perpage'];
		$pagenumber = $vbulletin->GPC['pagenumber'];

		// array to store private messages in period groups
		$pm_period_groups = array();

		$need_sql_calc_rows = ($search['searchtitle'] OR $search['searchuser'] OR $search['startdate'] OR $search['enddate'] OR $search['read']);

		$readstatus = array(0 => '', 1 => '= 0', 2 => '> 0', 3 => '< 2', 4 => '= 2');
		$readstatus = ($search['read'] == 0 ? '' : 'AND pm.messageread ' . $readstatus[$search['read']]);

		// query private messages
		$pms = $db->query_read_slave("
			SELECT " . ($need_sql_calc_rows ? 'SQL_CALC_FOUND_ROWS' : '') . " pm.*, pmtext.*
				" . iif($vbulletin->options['privallowicons'], ", icon.title AS icontitle, icon.iconpath") . "
			FROM " . TABLE_PREFIX . "pm AS pm
			LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
			" . iif($vbulletin->options['privallowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = pmtext.iconid)") . "
			WHERE pm.userid=" . $vbulletin->userinfo['userid'] . " AND pm.folderid=" . $vbulletin->GPC['folderid'] .
			($search['searchtitle'] ? " AND pmtext.title LIKE '%" . $vbulletin->db->escape_string($search['searchtitle']) . "%'" : '') .
			($search['searchuser'] ? " AND pmtext.fromusername LIKE '%" . $vbulletin->db->escape_string($search['searchuser']) . "%'" : '') .
			($search['startdate'] ? " AND pmtext.dateline >= $search[startdate]" : '') .
			($search['enddate'] ? " AND pmtext.dateline <= $search[enddate]" : '') . "
			$readstatus
			ORDER BY $sortfield " . ($desc ? 'DESC' : 'ASC') . "
			LIMIT $startat, " . $vbulletin->GPC['perpage'] . "
		");

		while ($pm = $db->fetch_array($pms))
		{
			if ('title' == $search['sort'])
			{
				$pm_period_groups[ fetch_char_group($pm['title']) ]["$pm[pmid]"] = $pm;
			}
			else if ('sender' == $search['sort'])
			{
				$pm_period_groups["$pm[fromusername]"]["$pm[pmid]"] = $pm;
			}
			else
			{
				$pm_period_groups[ fetch_period_group($pm['dateline']) ]["$pm[pmid]"] = $pm;
			}
		}
		$db->free_result($pms);

		// ensure other group is last
		if (isset($pm_period_groups['other']))
		{
			$pm_period_groups = ($desc)  ? array_merge($pm_period_groups, array('other' => $pm_period_groups['other']))
										 : array_merge(array('other' => $pm_period_groups['other']), $pm_period_groups);
		}

		// display returned messages
		$show['pmcheckbox'] = true;

		require_once(DIR . '/includes/functions_bigthree.php');

		foreach ($pm_period_groups AS $groupid => $pms)
		{
			if (('date' == $search['sort']) AND preg_match('#^(\d+)_([a-z]+)_ago$#i', $groupid, $matches))
			{
				$groupname = construct_phrase($vbphrase["x_$matches[2]_ago"], $matches[1]);
			}
			else if ('title' == $search['sort'] OR 'date' == $search['sort'])
			{
				if (('older' == $groupid) AND (sizeof($pm_period_groups) == 1))
				{
					$groupid = 'old_messages';
				}

				$groupname = $vbphrase["$groupid"];
			}
			else
			{
				$groupname = $groupid;
			}

			$groupid = $vbulletin->GPC['folderid'] . '_' . $groupid;
			$collapseobj_groupid =& $vbcollapse["collapseobj_pmf$groupid"];
			$collapseimg_groupid =& $vbcollapse["collapseimg_pmf$groupid"];

			$messagesingroup = sizeof($pms);
			$messagelistbits = '';

			foreach ($pms AS $pmid => $pm)
			{
				if (in_array($pm['fromuserid'], $ignoreusers))
				{
					// from user is on Ignore List
					//eval('$messagelistbits .= "' . fetch_template('pm_messagelistbit_ignore') . '";');
				}
				else
				{
					switch($pm['messageread'])
					{
						case 0: // unread
							$pm['statusicon'] = 'new';
						break;

						case 1: // read
							$pm['statusicon'] = 'old';
						break;

						case 2: // replied to
							$pm['statusicon'] = 'replied';
						break;

						case 3: // forwarded
							$pm['statusicon'] = 'forwarded';
						break;
					}

					$pm['senddate'] = vbdate($vbulletin->options['dateformat'], $pm['dateline']);
					$pm['sendtime'] = vbdate($vbulletin->options['timeformat'], $pm['dateline']);

					// get userbit
					if ($vbulletin->GPC['folderid'] == -1)
					{
						$users = unserialize($pm['touserarray']);
						$touser = array();
						$tousers = array();
						if (!empty($users))
						{
							foreach ($users AS $key => $item)
							{
								if (is_array($item))
								{
									foreach($item AS $subkey => $subitem)
									{
										$touser["$subkey"] = $subitem;
									}
								}
								else
								{
									$touser["$key"] = $item;
								}
							}
							uasort($touser, 'strnatcasecmp');
						}
						foreach ($touser AS $userid => $username)
						{
							//eval('$tousers[] = "' . fetch_template('pm_messagelistbit_user') . '";');
						}
						$userbit = implode(', ', $tousers);
					}
					else
					{
						$userid =& $pm['fromuserid'];
						$username =& $pm['fromusername'];
						//eval('$userbit = "' . fetch_template('pm_messagelistbit_user') . '";');
					}

					$show['pmicon'] = iif($pm['iconpath'], true, false);
					$show['unread'] = iif(!$pm['messageread'], true, false);

					//($hook = vBulletinHook::fetch_hook('private_messagelist_messagebit')) ? eval($hook) : false;

					//eval('$messagelistbits .= "' . fetch_template('pm_messagelistbit') . '";');
				}

				$to_users = unserialize($pm['touserarray']);
				$users = array();
				if ($to_users !== false) {
				    if ($to_users['cc']) {
					$users = $to_users['cc'];
				    }
				}
				if (!is_array($users)) {
				    $users = array();
				}

				$pm_new = 0;
				switch($pm['messageread']) {
				case 0: $pm_new = 1; break;
				case 1: $pm_new = 0; break;
				case 2: $pm_new = 2; break;
				}
				
				$avatarurl = '';
				$userinfoavatar = fetch_userinfo($pm['fromuserid'], FETCH_USERINFO_AVATAR);
				fetch_avatar_from_userinfo($userinfoavatar, true, false);
				if ($userinfoavatar['avatarurl'] != '') {
				    $avatarurl = process_avatarurl($userinfoavatar['avatarurl']);
				}
				unset($userinfoavatar);

				$tmp = array(
				    'id' => $pm['pmid'],
				    'new_pm' => $pm_new,
				    'username' => prepare_utf8_string(strip_tags($pm['fromusername'])),
				    'to_usernames' => prepare_utf8_string(implode('; ', $users)),
				    'title' => prepare_utf8_string($pm['title']),
				    'message' => prepare_utf8_string(htmlspecialchars_uni(fetch_censored_text(strip_bbcode(strip_quotes($pm['message']), false, true)))),
				    'pm_timestamp' => prepare_utf8_string(date_trunc($pm['senddate'] . ' ' . $pm['sendtime'])),
				);
				if ($avatarurl != '') {
				    $tmp['avatarurl'] = $avatarurl;
				}
				$pm_out[] = $tmp;
			}

			// free up memory not required any more
			unset($pm_period_groups["$groupid"]);

			//($hook = vBulletinHook::fetch_hook('private_messagelist_period')) ? eval($hook) : false;

			// build group template
			//eval('$messagelist_periodgroups .= "' . fetch_template('pm_messagelist_periodgroup') . '";');
		}

		if ($desc)
		{
			unset($search['order']);
		}
		$sorturl = urlimplode($search);

		// build pagenav
		if ($need_sql_calc_rows)
		{
			list($totalmessages) = $vbulletin->db->query_first_slave("SELECT FOUND_ROWS()", DBARRAY_NUM);
		}

		$pagenav = construct_page_nav($pagenumber, $perpage, $totalmessages, 'private.php?' . $vbulletin->session->vars['sessionurl'] . 'folderid=' . $vbulletin->GPC['folderid'] . '&amp;pp=' . $vbulletin->GPC['perpage'] . '&amp;' . $sorturl);

		$sortfield = $search['sort'];
		unset($search['sort']);

		$sorturl = 'private.php?' . $vbulletin->session->vars['sessionurl'] . 'folderid=' . $vbulletin->GPC['folderid'] . ($searchurl = urlimplode($search) ? '&amp;' . $searchurl : '');
		$oppositesort = $desc ? 'asc' : 'desc';

		$orderlinks = array(
			'date' => $sorturl . '&amp;sort=date' . ($sortfield == 'date' ? '&amp;order=' . $oppositesort : ''),
			'title' => $sorturl . '&amp;sort=title' . ($sortfield == 'title' ? '&amp;order=' . $oppositesort : '&amp;order=asc'),
			'sender' => $sorturl . '&amp;sort=sender' . ($sortfield == 'sender' ? '&amp;order=' . $oppositesort : '&amp;order=asc')
		);

		//eval('$sortarrow["$sortfield"] = "' . fetch_template('forumdisplay_sortarrow') . '";');

		// values for filters
		$startdate = fetch_datearray_from_timestamp(($search['startdate'] ? $search['startdate'] : strtotime('last month', TIMENOW)));
		$enddate = fetch_datearray_from_timestamp(($search['enddate'] ? $search['enddate'] : TIMENOW));
		$startmonth[$startdate[month]] = 'selected="selected"';
		$endmonth[$enddate[month]] = 'selected="selected"';
		$readselection[$search['read']] = 'selected="selected"';

		//eval('$sortfilter = "' . fetch_template('pm_filter') . '";');
	}

	if ($vbulletin->GPC['folderid'] == -1)
	{
		$show['sentto'] = true;
		$show['movetofolder'] = false;
	}
	else
	{
		$show['sentto'] = false;
		$show['movetofolder'] = true;
	}

	return array(
	    'pms' => $pm_out,
	    'total_pms' => $totalmessages,
	    'unread_pms' => $unread,
	);
}

function
do_get_pm ()
{
    global $vbulletin, $db;

	require_once(DIR . '/includes/class_postbit.php');
	require_once(DIR . '/includes/functions_bigthree.php');

	$vbulletin->input->clean_array_gpc('r', array(
		'pmid'        => TYPE_UINT,
		'showhistory' => TYPE_BOOL
	));

	($hook = vBulletinHook::fetch_hook('private_showpm_start')) ? eval($hook) : false;

	$pm = $db->query_first_slave("
		SELECT
			pm.*, pmtext.*,
			" . iif($vbulletin->options['privallowicons'], "icon.title AS icontitle, icon.iconpath,") . "
			IF(ISNULL(pmreceipt.pmid), 0, 1) AS receipt, pmreceipt.readtime, pmreceipt.denied,
			sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight
		FROM " . TABLE_PREFIX . "pm AS pm
		LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
		" . iif($vbulletin->options['privallowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = pmtext.iconid)") . "
		LEFT JOIN " . TABLE_PREFIX . "pmreceipt AS pmreceipt ON(pmreceipt.pmid = pm.pmid)
		LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = pmtext.fromuserid)
		WHERE pm.userid=" . $vbulletin->userinfo['userid'] . " AND pm.pmid=" . $vbulletin->GPC['pmid'] . "
	");

	if (!$pm)
	{
		json_error(strip_tags(fetch_error('invalidid', $vbphrase['private_message'], $vbulletin->options['contactuslink'])));
	}

	$folderjump = construct_folder_jump(0, $pm['folderid']);

	// do read receipt
	$show['receiptprompt'] = $show['receiptpopup'] = false;
	if ($pm['receipt'] == 1 AND $pm['readtime'] == 0 AND $pm['denied'] == 0)
	{
		if ($permissions['pmpermissions'] & $vbulletin->bf_ugp_pmpermissions['candenypmreceipts'])
		{
			// set it to denied just now as some people might have ad blocking that stops the popup appearing
			$show['receiptprompt'] = $show['receiptpopup'] = true;
			$receipt_question_js = addslashes_js(construct_phrase($vbphrase['x_has_requested_a_read_receipt'], unhtmlspecialchars($pm['fromusername'])), '"');
			$db->shutdown_query("UPDATE " . TABLE_PREFIX . "pmreceipt SET denied = 1 WHERE pmid = $pm[pmid]");
		}
		else
		{
			// they can't deny pm receipts so do not show a popup or prompt
			$db->shutdown_query("UPDATE " . TABLE_PREFIX . "pmreceipt SET readtime = " . TIMENOW . " WHERE pmid = $pm[pmid]");
		}
	}
	else if ($pm['receipt'] == 1 AND $pm['denied'] == 1)
	{
		$show['receiptprompt'] = true;
	}

	$postbit_factory = new vB_Postbit_Factory();
	$postbit_factory->registry =& $vbulletin;
	$postbit_factory->cache = array();
	$postbit_factory->bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	$postbit_obj =& $postbit_factory->fetch_postbit('pm');
	$pm_postbit = $pm;
	$postbit = $postbit_obj->construct_postbit($pm_postbit);

	// update message to show read
	if ($pm['messageread'] == 0)
	{
		$db->shutdown_query("UPDATE " . TABLE_PREFIX . "pm SET messageread=1 WHERE userid=" . $vbulletin->userinfo['userid'] . " AND pmid=$pm[pmid]");

		if ($pm['folderid'] >= 0)
		{
			$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
			$userdm->set_existing($vbulletin->userinfo);
			$userdm->set('pmunread', 'IF(pmunread >= 1, pmunread - 1, 0)', false);
			$userdm->save(true, true);
			unset($userdm);
		}
	}

	$cclist = array();
	$bcclist = array();
	$ccrecipients = '';
	$bccrecipients = '';
	$touser = unserialize($pm['touserarray']);
	if (!is_array($touser))
	{
		$touser = array();
	}

	foreach($touser AS $key => $item)
	{
		if (is_array($item))
		{
			foreach($item AS $subkey => $subitem)
			{
				$userinfo = array(
					'userid'   => $subkey,
					'username' => $subitem,
				);
				$templater = vB_Template::create('pm_messagelistbit_user');
					$templater->register('userinfo', $userinfo);
				${$key . 'list'}[] = $templater->render();
			}
		}
		else
		{
			$userinfo = array(
				'username' => $item,
				'userid'   => $key,
			);
			$templater = vB_Template::create('pm_messagelistbit_user');
				$templater->register('userinfo', $userinfo);
			$bcclist[] = $templater->render();
		}
	}

	if (count($cclist) > 1 OR (is_array($touser['cc']) AND !in_array($vbulletin->userinfo['username'], $touser['cc'])) OR ($vbulletin->userinfo['userid'] == $pm['fromuserid'] AND $pm['folderid'] == -1))
	{
		if (!empty($cclist))
		{
			$ccrecipients = implode("\r\n", $cclist);
		}
		if (!empty($bcclist) AND $vbulletin->userinfo['userid'] == $pm['fromuserid'] AND $pm['folderid'] == -1)
		{
			if (empty($cclist) AND count($bcclist == 1))
			{
				$ccrecipients = implode("\r\n", $bcclist);
			}
			else
			{
				$bccrecipients = implode("\r\n", $bcclist);
			}
		}

		$show['recipients'] = true;
	}

	$pm['senddate'] = vbdate($vbulletin->options['dateformat'], $pm['dateline']);
	$pm['sendtime'] = vbdate($vbulletin->options['timeformat'], $pm['dateline']);

	list ($text, $nuked_quotes, $images) = parse_post($pm['message'], $vbulletin->options['privallowsmilies'] && $usesmiles);
	
	$fr_images = array();
	foreach ($images as $image) {
	    $fr_images[] = array(
		'img' => $image,
	    );
	}

	// Avatar work
	$avatarurl = '';
	if ($pm_postbit['avatarurl']) {
	    $avatarurl = process_avatarurl($pm_postbit['avatarurl']);
	}

	$to_users = unserialize($pm['touserarray']);
	$users = array();
	if ($to_users !== false) {
	    if ($to_users['cc']) {
		$users = $to_users['cc'];
	    } else {
		$users = $to_users;
	    }
	}

	$userinfo = fetch_userinfo($pm['fromuserid']);

	$out = array(
	    'id' => $pm['pmid'],
	    'pm_unread' => ($pm['messageread'] == 0),
	    'username' => prepare_utf8_string(strip_tags($pm['fromusername'])),
	    'to_usernames' => prepare_utf8_string(implode('; ', $users)),
	    'userid' => $pm['fromuserid'],
	    'title' => prepare_utf8_string($pm['title']),
	    'online' => fetch_online_status($userinfo, false),
	    'message' => $text,
	    'quotable' => $nuked_quotes,
	    'fr_images' => $fr_images,
	    'pm_timestamp' => prepare_utf8_string(date_trunc($pm['senddate'] . ' ' . $pm['sendtime'])),
	);

	if ($avatarurl != '') {
	    $out['avatarurl'] = $avatarurl;
	}

	return $out;
}

function
do_send_pm ()
{
    global $vbulletin, $db, $permissions;

    if (!$vbulletin->userinfo['userid']) {
	json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
    }

	$vbulletin->input->clean_array_gpc('r', array(
		'wysiwyg'        => TYPE_BOOL,
		'title'          => TYPE_NOHTML,
		'message'        => TYPE_STR,
		'parseurl'       => TYPE_BOOL,
		'savecopy'       => TYPE_BOOL,
		'signature'      => TYPE_BOOL,
		'disablesmilies' => TYPE_BOOL,
		'receipt'        => TYPE_BOOL,
		'preview'        => TYPE_STR,
		'recipients'     => TYPE_STR,
		'bccrecipients'  => TYPE_STR,
		'iconid'         => TYPE_UINT,
		'forward'        => TYPE_BOOL,
		'folderid'       => TYPE_INT,
		'sendanyway'     => TYPE_BOOL,
	));

	if ($vbulletin->GPC['message']) {
	    $vbulletin->GPC['message'] = prepare_remote_utf8_string($vbulletin->GPC['message']);
	}
	if ($vbulletin->GPC['title']) {
	    $vbulletin->GPC['title'] = prepare_remote_utf8_string($vbulletin->GPC['title']);
	}
	if ($vbulletin->GPC['recipients']) {
	    $vbulletin->GPC['recipients'] = prepare_remote_utf8_string($vbulletin->GPC['recipients']);
	}

    $vbulletin->GPC['savecopy'] = true;

	if ($permissions['pmquota'] < 1)
	{
	    json_error(ERR_NO_PERMISSION);
	}
	else if (!$vbulletin->userinfo['receivepm'])
	{
	    json_error(strip_tags(fetch_error('pm_turnedoff')), RV_POST_ERROR);
	}

	if (fetch_privatemessage_throttle_reached($vbulletin->userinfo['userid']))
	{
	    json_error(strip_tags(fetch_error('pm_throttle_reached', $vbulletin->userinfo['permissions']['pmthrottlequantity'], $vbulletin->options['pmthrottleperiod'])), RV_POST_ERROR);
	}

	// include useful functions
	require_once(DIR . '/includes/functions_newpost.php');

	// parse URLs in message text
	if ($vbulletin->options['privallowbbcode'] AND $vbulletin->GPC['parseurl'])
	{
		$vbulletin->GPC['message'] = convert_url_to_bbcode($vbulletin->GPC['message']);
	}

	$pm['message'] =& $vbulletin->GPC['message'];
	/* VBIV-15839 : prepare_remote_utf8_string() seems to reverse
	the effects of the NOHTML cleanup, so clean it up again here */
	$pm['title'] = htmlspecialchars_uni($vbulletin->GPC['title']);
	$pm['parseurl'] =& $vbulletin->GPC['parseurl'];
	$pm['savecopy'] =& $vbulletin->GPC['savecopy'];
	$pm['signature'] =& $vbulletin->GPC['signature'];
	$pm['disablesmilies'] =& $vbulletin->GPC['disablesmilies'];
	$pm['sendanyway'] =& $vbulletin->GPC['sendanyway'];
	$pm['receipt'] =& $vbulletin->GPC['receipt'];
	$pm['recipients'] =& $vbulletin->GPC['recipients'];
	$pm['bccrecipients'] =& $vbulletin->GPC['bccrecipients'];
	$pm['pmid'] =& $vbulletin->GPC['pmid'];
	$pm['iconid'] =& $vbulletin->GPC['iconid'];
	$pm['forward'] =& $vbulletin->GPC['forward'];
	$pm['folderid'] =& $vbulletin->GPC['folderid'];

	// *************************************************************
	// PROCESS THE MESSAGE AND INSERT IT INTO THE DATABASE

	$errors = array(); // catches errors

	if ($vbulletin->userinfo['pmtotal'] > $permissions['pmquota'] OR ($vbulletin->userinfo['pmtotal'] == $permissions['pmquota'] AND $pm['savecopy']))
	{
	    json_error(strip_tags(fetch_error('yourpmquotaexceeded')), RV_POST_ERROR);
	}

	// create the DM to do error checking and insert the new PM
	$pmdm =& datamanager_init('PM', $vbulletin, ERRTYPE_ARRAY);

	$pmdm->set_info('savecopy',      $pm['savecopy']);
	$pmdm->set_info('receipt',       $pm['receipt']);
	$pmdm->set_info('cantrackpm',    $cantrackpm);
	$pmdm->set_info('forward',       $pm['forward']);
	$pmdm->set_info('bccrecipients', $pm['bccrecipients']);
	if ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	{
		$pmdm->overridequota = true;
	}

	$pmdm->set('fromuserid', $vbulletin->userinfo['userid']);
	$pmdm->set('fromusername', $vbulletin->userinfo['username']);
	$pmdm->setr('title', $pm['title']);
	$pmdm->set_recipients($pm['recipients'], $permissions, 'cc');
	$pmdm->set_recipients($pm['bccrecipients'], $permissions, 'bcc');
	$pmdm->setr('message', $pm['message']);
	$pmdm->setr('iconid', $pm['iconid']);
	$pmdm->set('dateline', TIMENOW);
	$pmdm->setr('showsignature', $pm['signature']);
	$pmdm->set('allowsmilie', $pm['disablesmilies'] ? 0 : 1);
	if (!$pm['forward'])
	{
		$pmdm->set_info('parentpmid', $pm['pmid']);
	}
	$pmdm->set_info('replypmid', $pm['pmid']);
	
	($hook = vBulletinHook::fetch_hook('private_insertpm_process')) ? eval($hook) : false;
	
	$pmdm->pre_save();

	// deal with user using receivepmbuddies sending to non-buddies
	if ($vbulletin->userinfo['receivepmbuddies'] AND is_array($pmdm->info['recipients']))
	{
		$users_not_on_list = array();

		// get a list of super mod groups
		$smod_groups = array();
		foreach ($vbulletin->usergroupcache AS $ugid => $groupinfo)
		{
			if ($groupinfo['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'])
			{
				// super mod group
				$smod_groups[] = $ugid;
			}
		}

		// now filter out all moderators (and super mods) from the list of recipients
		// to check against the buddy list
		$check_recipients = $pmdm->info['recipients'];
		$mods = $db->query_read_slave("
			SELECT user.userid
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "moderator AS moderator ON (moderator.userid = user.userid)
			WHERE user.userid IN (" . implode(',', array_keys($check_recipients)) . ")
				AND ((moderator.userid IS NOT NULL AND moderator.forumid <> -1)
				" . (!empty($smod_groups) ? "OR user.usergroupid IN (" . implode(',', $smod_groups) . ")" : '') . "
				)
		");
		while ($mod = $db->fetch_array($mods))
		{
			unset($check_recipients["$mod[userid]"]);
		}

		if (!empty($check_recipients))
		{
			// filter those on our buddy list out
			$users = $db->query_read_slave("
				SELECT userlist.relationid
				FROM " . TABLE_PREFIX . "userlist AS userlist
				WHERE userid = " . $vbulletin->userinfo['userid'] . "
					AND userlist.relationid IN(" . implode(array_keys($check_recipients), ',') . ")
					AND type = 'buddy'
			");
			while ($user = $db->fetch_array($users))
			{
				unset($check_recipients["$user[relationid]"]);
			}
		}

		// what's left must be those who are neither mods or on our buddy list
		foreach ($check_recipients AS $userid => $user)
		{
				$users_not_on_list["$userid"] = $user['username'];
		}

		if (!empty($users_not_on_list) AND (!$vbulletin->GPC['sendanyway'] OR !empty($errors)))
		{
			$users = '';
			foreach ($users_not_on_list AS $userid => $username)
			{
				$users .= "<li><a href=\"member.php?" . $vbulletin->session->vars['sessionurl'] . "u=$userid\" target=\"profile\">$username</a></li>";
			}
			$pmdm->error('pm_non_contacts_cant_reply', $users);
		}
	}

	// check for message flooding
	if ($vbulletin->options['pmfloodtime'] > 0 AND !$vbulletin->GPC['preview'])
	{
		if (!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) AND !can_moderate())
		{
			$floodcheck = $db->query_first("
				SELECT pmtextid, title, dateline
				FROM " . TABLE_PREFIX . "pmtext AS pmtext
				WHERE fromuserid = " . $vbulletin->userinfo['userid'] . "
				ORDER BY dateline DESC
			");

			if (($timepassed = TIMENOW - $floodcheck['dateline']) < $vbulletin->options['pmfloodtime'])
			{
			    json_error(strip_tags(fetch_error('pmfloodcheck', $vbulletin->options['pmfloodtime'], ($vbulletin->options['pmfloodtime'] - $timepassed))), RV_POST_ERROR);
			}
		}
	}

	// process errors if there are any
	$errors = array_merge($errors, $pmdm->errors);

	if (!empty($errors))
	{
	    json_error(strip_tags($errors[0]), RV_POST_ERROR);
	}
	else if ($vbulletin->GPC['preview'] != '')
	{
		define('PMPREVIEW', 1);
		$foruminfo = array(
			'forumid' => 'privatemessage',
			'allowicons' => $vbulletin->options['privallowicons']
		);
		$preview = process_post_preview($pm);
		$_REQUEST['do'] = 'newpm';
	}
	else
	{
		// everything's good!
		$pmdm->save();

		// force pm counters to be rebuilt
		$vbulletin->userinfo['pmunread'] = -1;
		build_pm_counters();
	}

	return array(
	    'success' => 1,
	);
}

function
do_delete_pm ()
{
    global $vbulletin, $db;

    $vbulletin->input->clean_array_gpc('r', array(
	'pm'       => TYPE_UINT,
    ));

    // get selected via post
    $messageids = array();
    $messageids[$vbulletin->GPC['pm']] = $vbulletin->GPC['pm'];

    $pmids = array();
    $textids = array();

    // get the pmid and pmtext id of messages to be deleted
    $pms = $db->query_read_slave("
	SELECT pmid
	FROM " . TABLE_PREFIX . "pm
	WHERE userid = " . $vbulletin->userinfo['userid'] . "
	AND pmid IN(" . implode(', ', $messageids) . ")
	");

    // check to see that we still have some ids to work with
    if ($db->num_rows($pms) == 0)
    {
	json_error(ERR_INVALID_PM, RV_POST_ERROR);
    }

    // build the final array of pmids to work with
    while ($pm = $db->fetch_array($pms))
    {
	$pmids[] = $pm['pmid'];
    }

    // delete from the pm table using the results from above
    $deletePmSql = "DELETE FROM " . TABLE_PREFIX . "pm WHERE pmid IN(" . implode(', ', $pmids) . ")";
    $db->query_write($deletePmSql);

    build_pm_counters();

    return array(
	'success' => 1,
    );
}

?>
