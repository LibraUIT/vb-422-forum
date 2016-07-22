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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('style');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/adsense_templates.php');
require_once(DIR . '/includes/adminfunctions_template.php');

// ################### CHECK ADMIN PERMISSIONS AND DEPLOY #################
if (empty($vbulletin->adsense_pub_id))
{
	print_stop_message('adsense_not_deployed_upgrade_error');
}

if (!can_administer('canadminstyles'))
{
	print_cp_no_permission();
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['google_adsense_advertising']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'intro';
}

// ######################## Confirm Intent ######################
if ($_REQUEST['do'] == 'intro')
{
	$selected_deployment = $db->query_first("SELECT data FROM " . TABLE_PREFIX . "datastore WHERE title = 'adsensedeployed'");

	switch ($selected_deployment['data'])
	{
		case 'low-text':
			$deployment_description = "$vbphrase[adsense_deployment_low] ($vbphrase[text_ads_only])";
			$deployment_option = 'low';
			$ad_type = 'text';
			break;
		case 'low-image':
			$deployment_description = "$vbphrase[adsense_deployment_low] ($vbphrase[text_and_image_ads])";
			$deployment_option = 'low';
			$ad_type = 'image';
			break;

		case 'high-text':
			$deployment_description = "$vbphrase[adsense_deployment_high] ($vbphrase[text_ads_only])";
			$deployment_option = 'high';
			$ad_type = 'text';
			break;
		case 'high-image':
			$deployment_description = "$vbphrase[adsense_deployment_high] ($vbphrase[text_and_image_ads])";
			$deployment_option = 'high';
			$ad_type = 'image';
			break;

		case 'remove':
		default:
			$deployment_description = $vbphrase['adsense_deployment_none'];
			$deployment_option = 'remove';
			$ad_type = 'image';
			break;
	}

	print_form_header('deployads', 'deploy');
	print_table_header($vbphrase['google_adsense_advertising']);

	print_description_row($vbphrase['adsense_advertising_intro']);
	print_description_row("<div style=\"padding:20px; text-align:center\">$deployment_description</div>");

	print_label_row($vbphrase['google_adsense_publisher_id'], $vbulletin->adsense_pub_id);

	print_radio_row($vbphrase['change_google_adsense_package'], 'deployment', array(
		'remove' => $vbphrase['no_google_adsense_ads'],
		'low' => $vbphrase['google_adsense_package_low'],
		'high' => $vbphrase['google_adsense_package_high']
	), $deployment_option);

	print_radio_row($vbphrase['type_of_ads_to_show'], 'type', array(
		'text' => $vbphrase['text_ads_only'],
		'image' => $vbphrase['text_and_image_ads']
	), $ad_type);

	print_submit_row($vbphrase['change_google_adsense_package'], '');
}

// ######################## Deploy ########################
if ($_POST['do'] == 'deploy')
{

	$vbulletin->input->clean_array_gpc('p', array(
		'deployment' => TYPE_STR,
		'type' => TYPE_STR
	));

	if ($vbulletin->GPC['deployment'] == 'remove')
	{
		$deployment = 'remove';
	}
	else
	{
		$deployment = strtolower($vbulletin->GPC['deployment'] . '-' . $vbulletin->GPC['type']);
	}

	$ad_template = get_adsense_templates();

	// Type check
	if (isset($ad_template[$deployment]))
	{
		// Over write with the new ones
		foreach($ad_template[$deployment] AS $title => $new_template)
		{
			echo "<br />{$vbphrase['done']} {$title}";
			vbflush();

			$db->query_write("
			UPDATE " . TABLE_PREFIX . "template
			SET
				template = '" . $vbulletin->db->escape_string(compile_template($new_template)) . "',
				template_un = '" . $db->escape_string($new_template) . "',
				dateline = " . TIMENOW . ",
				username = '" . $db->escape_string($vbulletin->userinfo['username']) . "'
			WHERE
				title = '" . $db->escape_string($title) . "' AND
				styleid = -1 AND
				product IN ('', 'vbulletin')
			");
		}

		// Flag what has been done
		if ($deployment != 'remove')
		{
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "datastore
					(title, data, unserialize)
				VALUES
					('adsensedeployed', '" . $vbulletin->db->escape_string($deployment) . "', 0)
			");
		}
		else
		{
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "datastore WHERE title = 'adsensedeployed'");
		}

		// 	Log it
		log_admin_action($deployment);

		echo "<br /><br />{$vbphrase['okay']}";
		vbflush();
	}

	print_cp_redirect("deployads.php?" . $vbulletin->session->vars['sessionurl'] . "do=intro");
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision:  $
|| ####################################################################
\*======================================================================*/
?>