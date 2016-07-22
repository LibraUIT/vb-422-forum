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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

if (defined('BLOG_ADMIN_USER_EDIT'))
{
	if ($user['userid'])
	{
		if ($blog = $db->query_first("
			SELECT blog_user.*
			FROM " . TABLE_PREFIX . "blog_user AS blog_user
			WHERE bloguserid = $user[userid]
		"))
		{
			$blog = array_merge($blog, convert_bits_to_array($blog['options'], $vbulletin->bf_misc_vbbloguseroptions));

			foreach ($vbulletin->bf_misc_vbblogsocnetoptions AS $optionname => $optionval)
			{
				$blog["member_$optionname"] = ($blog['options_member'] & $optionval ? 1 : 0);
				$blog["guest_$optionname"] = ($blog['options_guest'] & $optionval ? 1 : 0);
				$blog["buddy_$optionname"] = ($blog['options_buddy'] & $optionval ? 1 : 0);
				$blog["ignore_$optionname"] = ($blog['options_ignore'] & $optionval ? 1 : 0);
				$checked["member_$optionname"] = $blog["member_$optionname"] ? 'checked="checked"' : '';
				$checked["guest_$optionname"] = $blog["guest_$optionname"] ? 'checked="checked"' : '';
				$checked["buddy_$optionname"] = $blog["buddy_$optionname"] ? 'checked="checked"' : '';
				$checked["ignore_$optionname"] = $blog["ignore_$optionname"] ? 'checked="checked"' : '';
			}
		}
		else
		{
			// Set defaults
			$blog = array(
				'allowcomments'    => $vbulletin->bf_misc_vbblogregoptions['allowcomments'] & $vbulletin->options['vbblog_defaultoptions'] ? true : false,
				'moderatecomments' => $vbulletin->bf_misc_vbblogregoptions['moderatecomments'] & $vbulletin->options['vbblog_defaultoptions'] ? true : false,
				'allowpingback'    => $vbulletin->bf_misc_vbblogregoptions['allowpingback'] & $vbulletin->options['vbblog_defaultoptions'] ? true : false,
			);

			if ($vbulletin->bf_misc_vbblogregoptions['subscribe_none_entry'] & $vbulletin->options['vbblog_defaultoptions'])
			{
				$blog['subscribeown'] = 'none';
			}
			else if ($vbulletin->bf_misc_vbblogregoptions['subscribe_nonotify_entry'] & $vbulletin->options['vbblog_defaultoptions'])
			{
				$blog['subscribeown'] = 'usercp';
			}
			else
			{
				$blog['subscribeown'] = 'email';
			}

			if ($vbulletin->bf_misc_vbblogregoptions['subscribe_none_comment'] & $vbulletin->options['vbblog_defaultoptions'])
			{
				$blog['subscribeothers'] = 'none';
			}
			else if ($vbulletin->bf_misc_vbblogregoptions['subscribe_nonotify_comment'] & $vbulletin->options['vbblog_defaultoptions'])
			{
				$blog['subscribeothers'] = 'usercp';
			}
			else
			{
				$blog['subscribeothers'] = 'email';
			}

			$checked['member_canviewmyblog'] = $vbulletin->bf_misc_vbblogregoptions['viewblog_member'] & $vbulletin->options['vbblog_defaultoptions'] ? 'checked="checked"' : '';
			$checked['guest_canviewmyblog'] = $vbulletin->bf_misc_vbblogregoptions['viewblog_guest'] & $vbulletin->options['vbblog_defaultoptions'] ? 'checked="checked"' : '';
			$checked['buddy_canviewmyblog'] = $vbulletin->bf_misc_vbblogregoptions['viewblog_buddy'] & $vbulletin->options['vbblog_defaultoptions'] ? 'checked="checked"' : '';
			$checked['ignore_canviewmyblog'] = $vbulletin->bf_misc_vbblogregoptions['viewblog_ignore'] & $vbulletin->options['vbblog_defaultoptions'] ? 'checked="checked"' : '';
			$checked['member_cancommentmyblog'] = $vbulletin->bf_misc_vbblogregoptions['commentblog_member'] & $vbulletin->options['vbblog_defaultoptions'] ? 'checked="checked"' : '';
			$checked['guest_cancommentmyblog'] = $vbulletin->bf_misc_vbblogregoptions['commentblog_guest'] & $vbulletin->options['vbblog_defaultoptions'] ? 'checked="checked"' : '';
			$checked['buddy_cancommentmyblog'] = $vbulletin->bf_misc_vbblogregoptions['commentblog_buddy'] & $vbulletin->options['vbblog_defaultoptions'] ? 'checked="checked"' : '';
			$checked['ignore_cancommentmyblog'] = $vbulletin->bf_misc_vbblogregoptions['commentblog_ignore'] & $vbulletin->options['vbblog_defaultoptions'] ? 'checked="checked"' : '';
		}

		print_table_break('', $INNERTABLEWIDTH);
		print_table_header($vbphrase['blog']);

		print_input_row($vbphrase['title'], 'blog[title]', $blog['title'], 0);
		print_textarea_row($vbphrase['description'], 'blog[description]', $blog['description'], 8, 45);

		print_yes_no_row($vbphrase['allow_comments_to_be_posted'], 'blog[options][allowcomments]', $blog['allowcomments']);
		print_yes_no_row($vbphrase['moderate_comments_before_displaying'], 'blog[options][moderatecomments]', $blog['moderatecomments']);
		print_yes_no_row($vbphrase['allow_trackback_pingback'], 'blog[options][allowpingback]', $blog['allowpingback']);
		print_yes_no_row($vbphrase['show_others_custom_blog_style'], 'user[showblogcss]', $user['showblogcss']);

		print_input_row($vbphrase['wordpress_api_key'], 'blog[akismet_key]', $blog['akismet_key']);

		print_radio_row("$vbphrase[default_subscription_mode] $vbphrase[blog_entries]", 'blog[subscribeown]', array(
			'none'   => $vbphrase['subscribe_choice_none'],
			'usercp' => $vbphrase['subscribe_choice_0'],
			'email'  => $vbphrase['subscribe_choice_1'],
		), $blog['subscribeown'], 'smallfont');

		print_radio_row("$vbphrase[default_subscription_mode] $vbphrase[blog_comments]", 'blog[subscribeothers]', array(
			'none'   => $vbphrase['subscribe_choice_none'],
			'usercp' => $vbphrase['subscribe_choice_0'],
			'email'  => $vbphrase['subscribe_choice_1'],
		), $blog['subscribeothers'], 'smallfont');

		print_label_row($vbphrase['members_on_buddy_list_may'],
			"<span class=\"smallfont\">
			 <label><input type=\"checkbox\" name=\"blog[options_buddy][canviewmyblog]\" $checked[buddy_canviewmyblog] value=\"1\" />$vbphrase[view_blog]</label><br />
			 <label><input type=\"checkbox\" name=\"blog[options_buddy][cancommentmyblog]\" $checked[buddy_cancommentmyblog] value=\"1\" />$vbphrase[leave_comments_on_blog_entries]</label></span>"
		);

		print_label_row($vbphrase['members_on_ignore_list_may'],
			"<span class=\"smallfont\">
			 <label><input type=\"checkbox\" name=\"blog[options_ignore][canviewmyblog]\" $checked[ignore_canviewmyblog] value=\"1\" />$vbphrase[view_blog]</label><br />
			 <label><input type=\"checkbox\" name=\"blog[options_ignore][cancommentmyblog]\" $checked[ignore_cancommentmyblog] value=\"1\" />$vbphrase[leave_comments_on_blog_entries]</label><br /></span>"
		);

		print_label_row($vbphrase['other_members_may'],
			"<span class=\"smallfont\">
			 <label><input type=\"checkbox\" name=\"blog[options_member][canviewmyblog]\" $checked[member_canviewmyblog] value=\"1\" />$vbphrase[view_blog]</label><br />
			 <label><input type=\"checkbox\" name=\"blog[options_member][cancommentmyblog]\" $checked[member_cancommentmyblog] value=\"1\" />$vbphrase[leave_comments_on_blog_entries]</label><br /></span>"
		);

		print_label_row($vbphrase['guests_may'],
			"<span class=\"smallfont\">
			 <label><input type=\"checkbox\" name=\"blog[options_guest][canviewmyblog]\" $checked[guest_canviewmyblog] value=\"1\" />$vbphrase[view_blog]</label><br />
			 <label><input type=\"checkbox\" name=\"blog[options_guest][cancommentmyblog]\" $checked[guest_cancommentmyblog] value=\"1\" />$vbphrase[leave_comments_on_blog_entries]</label><br /></span>"
		);

		print_description_row($vbphrase['user_customizations'], false, 2, 'optiontitle');
		print_description_row(
			'<input type="submit" class="button" tabindex="1" name="modifyblogcss" value="' . $vbphrase['edit_blog_customizations'] . '" />',
			false, 2, '', 'center'
		);
	}
}
else
{
	$vbulletin->input->clean_array_gpc('p', array(
		'modifyblogcss' => TYPE_NOCLEAN,
	));

	if ($vbulletin->GPC['modifyblogcss'])
	{
		$handled = true;
		define('CP_REDIRECT', "blog_admin.php?do=usercss&amp;u=$userid");
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 17991 $
|| ####################################################################
\*======================================================================*/
?>