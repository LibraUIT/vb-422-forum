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
/*
if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}
*/

class vB_Upgrade_4110a1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '4110a1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.10 Alpha 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.9';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '';	
	
	/*
	  Step 1 - VBIV-13685 Transition from vB3 Mobile API Product to vB4 Mobile API (which should have been done in 4.1.0a1)
	 * Run this step even if 'vbapi' doesn't show to be installed, just to be safe.
	*/
	function step_1()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "setting"),
				"UPDATE " . TABLE_PREFIX . "setting
				SET product = 'vbulletin'
				WHERE
					product = 'vbapi'
						AND
					varname IN ('apikey', 'enableapi', 'enableapilog', 'apilogpostparam', 'mobilehomemaxitems',
								'mobilehomethreaddatecut', 'mobilehomethreadforumids', 'mobilehomeblogdatecut',
								'mobilehomeblogcatids', 'mobilehomebloguserids')
				"
		);	
	}	
	
	/*
	  Step 2 - VBIV-13685 Transition from vB3 Mobile API Product to vB4 Mobile API (which should have been done in 4.1.0a1)
 	 * Run this step even if 'vbapi' doesn't show to be installed, just to be safe.
	*/
	function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "settinggroup"),
				"UPDATE " . TABLE_PREFIX . "settinggroup
				SET product = 'vbulletin'
				WHERE
					product = 'vbapi'
						AND
					grouptitle = 'api'
				"
		);	
	}	
	
	/*
	  Step 3 - VBIV-13685 Transition from vB3 Mobile API Product to vB4 Mobile API (which should have been done in 4.1.0a1)
	  * Remove 'vbapi' product - this will not execute the product uninstall code, which is what we want.
	*/
	function step_3()
	{
		if ($existingprod = $this->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "product
			WHERE productid = 'vbapi'"
		))		
		{
			$this->show_message($this->phrase['version']['4110a1']['remove_vbapi_product']);
			require_once(DIR . '/includes/adminfunctions_plugin.php');
			delete_product('vbapi');
		}
		else
		{
			$this->skip_message();
		}
	}			

	/*
	  Step 4 - VBIV-13517 Stylevar Mapping.
	*/
	function step_4()
	{
		require_once(DIR . '/includes/class_stylevar_mapper.php');

		$SVM = new SV_Mapping($this->registry);

		// Mappings
		$SVM->add_sv_mapping('body_background','vbblog_body_background','vbblog');
		$SVM->add_sv_mapping('body_background','vbcms_body_background','vbcms');
		$SVM->add_sv_mapping('bodyheader_margin','header_margin');
		$SVM->add_sv_mapping('calendarwidget_day_font','vbcms_calendarwidget_day_font','vbcms');
		$SVM->add_sv_mapping('calendarwidget_monthnav_background','vbcms_calendarwidget_monthnav_background','vbcms');
		$SVM->add_sv_mapping('calendarwidget_monthnav_font','vbcms_calendarwidget_monthnav_font','vbcms');
		$SVM->add_sv_mapping('calendarwidget_weekdays_background','vbcms_calendarwidget_weekdays_background','vbcms');
		$SVM->add_sv_mapping('calendarwidget_weekdays_border','vbcms_calendarwidget_weekdays_border','vbcms');
		$SVM->add_sv_mapping('calendarwidget_weekdays_font','vbcms_calendarwidget_weekdays_font','vbcms');
		$SVM->add_sv_mapping('content3_background','bbcode_code_background');
		$SVM->add_sv_mapping('control_focus_background','input_focus_background');
		$SVM->add_sv_mapping('control_font','input_font');
		$SVM->add_sv_mapping('editor_wysiwyg_table_borderColor','editor_wysiwyg_table_border.color');
		$SVM->add_sv_mapping('editor_wysiwyg_table_borderSize','editor_wysiwyg_table_border.units');
		$SVM->add_sv_mapping('editor_wysiwyg_table_borderSize.size','editor_wysiwyg_table_border.width');
		$SVM->add_sv_mapping('formrow_background','navbar_popupmenu_link_background');
		$SVM->add_sv_mapping('formrow_background','popupmenu_link_background');
		$SVM->add_sv_mapping('forum_msg_font','postbit_font');
		$SVM->add_sv_mapping('forum_sidebar_link_color','sidebar_content_link_color');
		$SVM->add_sv_mapping('forum_sidebar_linkhover_color','sidebar_content_link_hover_color');
		$SVM->add_sv_mapping('image_large_max','attachment_image_large_max');
		$SVM->add_sv_mapping('image_medium_max','image_max_size');
		$SVM->add_sv_mapping('image_small_max','attachment_image_medium_max');
		$SVM->add_sv_mapping('image_thumbnail_max','attachment_image_thumbnail_max');
		$SVM->add_sv_mapping('imodhilite_backgroundColor','general_hilite_color');
		$SVM->add_sv_mapping('imodhilite_backgroundColor','navbar_popupmenu_link_hover_background');
		$SVM->add_sv_mapping('imodhilite_backgroundColor','popupmenu_link_hover_background');
		$SVM->add_sv_mapping('lightweightbox_background','picture_background');
		$SVM->add_sv_mapping('lightweightbox_border','picture_border');
		$SVM->add_sv_mapping('mid_border.color','input_border.color');
		$SVM->add_sv_mapping('navbar_background_notify','toplinks_hilite_background');
		$SVM->add_sv_mapping('navbar_padding','navbar_tab_padding');
		$SVM->add_sv_mapping('navbar_selected_popup_body_a_Color','navbar_popupmenu_link_color');
		$SVM->add_sv_mapping('navbar_selected_popup_body_a_Color','navbar_popupmenu_link_hover_color');
		$SVM->add_sv_mapping('navbar_tab_linkhover_color','navbar_tab_selected_color');
		$SVM->add_sv_mapping('navbar_tab_selected_top_width','navbar_tab_selected_top_height');
		$SVM->add_sv_mapping('navbar_tab_size.height','popupmenu_height.size');
		$SVM->add_sv_mapping('navbar_tab_size.units','popupmenu_height.units');
		$SVM->add_sv_mapping('popupmenu_color','popupmenu_link_color');
		$SVM->add_sv_mapping('popupmenu_color','popupmenu_link_hover_color');
		$SVM->add_sv_mapping('popupmenu_label_color','popupmenu_color');
		$SVM->add_sv_mapping('postbit_boxed_background','attachment_box_background');
		$SVM->add_sv_mapping('postbit_boxed_border','attachment_box_border');
		$SVM->add_sv_mapping('postbit_boxed_fontSize','attachment_box_fontsize');
		$SVM->add_sv_mapping('postbit_boxed_padding','attachment_box_padding');
		$SVM->add_sv_mapping('postfoot_separator_color','postbit_foot_separator.color');
		$SVM->add_sv_mapping('postfoot_separator_width','postbit_foot_separator.units');
		$SVM->add_sv_mapping('postfoot_separator_width.size','postbit_foot_separator.width');
		$SVM->add_sv_mapping('tabslight_border','formrow_border');
		$SVM->add_sv_mapping('texthilite_color','highlight_color');
		$SVM->add_sv_mapping('toplinks_text','toplinks_color');
		$SVM->add_sv_mapping('vbcms_widget_content_padding','sidebar_content_padding');
		$SVM->add_sv_mapping('vbcms_widget_postbit_header_font','sidebar_postbit_header_font');
		$SVM->add_sv_mapping('vbcms_widget_postbit_small_fontSize','sidebar_postbit_small_fontSize');
		$SVM->add_sv_mapping('vbcms_wysiwyg_table_borderColor','bbcode_table_border.color');

		// Presets
		$SVM->add_sv_preset('input_border.width','1');
		$SVM->add_sv_preset('input_border.units','px');
		$SVM->add_sv_preset('input_border.style','solid');
		$SVM->add_sv_preset('bbcode_table_border.width','3');
		$SVM->add_sv_preset('bbcode_table_border.units','px');
		$SVM->add_sv_preset('bbcode_table_border.style','solid');
		$SVM->add_sv_preset('postbit_foot_separator.style','solid');
		$SVM->add_sv_preset('editor_wysiwyg_table_border.style','dotted');

		// Process Mappings 
		if ($SVM->sv_load() AND $SVM->process())
		{
			$this->show_message($this->phrase['core']['sv_mappings']);
			$SVM->process_results();
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
