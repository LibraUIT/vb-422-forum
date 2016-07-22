<?php
if (!VB_API) die;

class vB_APIMethod_search_findusers extends vBI_APIMethod
{
	public function output()
	{
		global $vbulletin, $db;
		$vbulletin->input->clean_array_gpc('r', array('userids' => TYPE_STR, 'contenttypeids' => TYPE_STR));

        $vbulletin->GPC['userids'] = convert_urlencoded_unicode($vbulletin->GPC['userids']);
        $userids = $vbulletin->GPC['userids'];
		$vbulletin->GPC['contenttypeids'] = convert_urlencoded_unicode($vbulletin->GPC['contenttypeids']);
        $contenttypeids = $vbulletin->GPC['contenttypeids'];
		
		require_once(DIR . "/vb/search/core.php");
		require_once(DIR . "/vb/legacy/currentuser.php");
		require_once(DIR . "/vb/search/resultsview.php");
		require_once(DIR . "/vb/search/searchtools.php");

		$search_core = vB_Search_Core::get_instance();
		$current_user = new vB_Legacy_CurrentUser();

		if (!$vbulletin->options['enablesearches'])
		{
			return $this->error('searchdisabled');
		}
		
		$criteria = $search_core->create_criteria(vB_Search_Core::SEARCH_ADVANCED);
		
		$userids_a = explode(',', $userids);
		$contenttypeids_a = explode(',', $contenttypeids);
		
		if(empty($userids_a)) {
			return $this->error('invalidid');
		}
		
		$criteria->add_userid_filter($userids_a, vB_Search_Core::GROUP_NO);
		
		if(!empty($contenttypeids_a)) {
			$criteria->add_contenttype_filter($contenttypeids_a);
		}
		
		$results = null;
		if (!($vbulletin->debug OR ($vbulletin->GPC_exists['nocache'] AND $vbulletin->GPC['nocache'])))
		{
			$results = vB_Search_Results::create_from_cache($current_user, $criteria);
		}

		if (!$results)
		{
			$results = vB_Search_Results::create_from_criteria($current_user, $criteria);
		}

		return array("response" => array("errormessage" => "search"), "show" => array("searchid" => $results->get_searchid()));
	}
}
