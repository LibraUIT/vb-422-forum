<?php if (!defined('VB_ENTRY')) die('Access denied.');

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


require_once (DIR . '/vb/search/type.php');

/**
 * vBCms_Search_Type_Article
 *
 * @package
 * @author Ed Brown
 * @copyright Copyright (c) 2009
 * @version $Id: article.php 30550 2009-04-28 23:55:20Z ebrown $
 * @access public
 */
class vBCms_Search_Type_Article extends vB_Search_Type
{

	/** package name ***/
	protected $package = "vBCms";

	/** class name ***/
	protected $class = "Article";
	/** package name for the group item ***/
	protected $group_package = "vBCms";

	/** class name for the group item***/
	protected $group_class = "Article";

	/**
	 * determine which records are viewable by this user.
	 *
	 * @param mixed $user : current user object
	 * @param array $ids : array of article contentids
	 * @param mixed $gids : not applicable here- group id's for those types which are groupable
	 * @return array of (viewable id's, rejected groups)
	 */
	public function fetch_validated_list($user, $ids, $gids)
	{
		//We need to pull parentnode and permissionsfrom from the table.
		$sql = "SELECT node.contentid, node.nodeid, node.parentnode, node.permissionsfrom, node.setpublish,
			node.userid, node.publishdate, node.hidden, node.nosearch, node.userid FROM " .
			TABLE_PREFIX . "cms_node AS node INNER JOIN " .	TABLE_PREFIX . "cms_article AS article
			ON article.contentid = node.contentid AND node.contenttypeid = "  .
			vB_Types::instance()->getContentTypeID('vBCms_Article') . " WHERE article.contentid in ("
			. implode(', ', $ids) . ")";

		$canview = array();
		$hidden = array();

		$rst = vB::$vbulletin->db->query_read($sql);
		if ($rst)
		{
			// make sure user cms permissions are stored in the registry
			if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
			{
				vBCMS_Permissions::getUserPerms();
			}

			while($record = vB::$vbulletin->db->fetch_array($rst))
			{	// Removed 'canedit' permission from this, dont know what its purpose was, but it was
				// overriding the basic premise that you must have 'canview' to see an article (or be the author).
				if ($record['userid'] == vB::$vbulletin->userinfo['userid'])
				{
					$canview[] = $record['contentid'];
				}
				else if (in_array($record['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['canview'])
					AND ($record['setpublish'] > 0) AND ($record['publishdate'] <= TIMENOW))
				{
					$canview[] = $record['contentid'];
				}
				else
				{
					$hidden[] = $record['parentnode'];
				}

			}
			//And let's store the permissionsfrom in case we need it.
			vBCMS_Permissions::setPermissionsfrom($record['nodeid'], $record['permissionsfrom'],
				$record['hidden'], $record['setpublish'], $record['publishdate'], $record['userid']);
		}

		if (count($canview))
		{
			$articles = vBCms_Search_Result_Article::create_array($canview);
		}
		else
		{
			$articles = array();
		}

		$retval = array('list' => $articles, 'groups_rejected' => $hidden);

		($hook = vBulletinHook::fetch_hook('search_validated_list')) ? eval($hook) : false;

		return $retval;
	}

	/**
	 * set parameters before rendering
	 *
	 * @param object $user
	 * @param object $results
	 * @return
	 */
	public function prepare_render($user, $results)
	{
		$phrase = new vB_Legacy_Phrase();
		$phrase->add_phrase_groups(array('user', 'search'));

		foreach ($results AS $result)
		{
			$privs = array();
			//if we have a right for any item in the result set we have that right

			foreach ($privs AS $key => $priv)
			{
				$this->mod_rights[$key] = ($this->mod_rights[$key] OR (bool) $priv);
			}
		}

		($hook = vBulletinHook::fetch_hook('search_prepare_render')) ? eval($hook) : false;
	}

	/**
	 * set any additional header text. We don't have any
	 *
	 * @return string
	 */
	public function additional_header_text()
	{
		return '';
	}

	/**
	 * get the display name for this type
	 *
	 * @return string
	 */
	public function get_display_name()
	{
		return new vB_Phrase('search', 'searchtype_articles');
	}

	/**
	 * This function composes the html to display the user interface for this
	 * search type
	 *
	 * @param mixed $prefs : the array of user preferences
	 * @param mixed $contenttypeid : the content type for which we are going to
	 *    search
	 * @param array registers : any additional elements to be registered. These are
	 * 	just passed to the template
	 * @param string $template_name : name of the template to use for display. We have
	 *		a default template.
	 * @param boolean $groupable : a flag to tell whether the interface should display
	 * 	grouping option(s).
	 * @return $html: complete html for the search elements
	 */
	public function listUi($prefs = null, $contenttypeid = null, $registers = null, $template_name = null)
	{
		global $vbulletin, $vbphrase;


		if (! isset($template_name))
		{
			$template_name = 'search_input_default';
		}

		if (! isset($contenttypeid))
		{
			$contenttypeid = vB_Types::instance()->getContentTypeID('vBCms_Article');
		}

		$template = vB_Template::create($template_name);
		$template->register('securitytoken', $vbulletin->userinfo['securitytoken']);
		$template->register('class', $this->get_display_name());
		$template->register('contenttypeid',$contenttypeid);

		$prefsettings = array(
			'select'=> array('searchdate', 'beforeafter', 'titleonly', 'sortby', 'sortorder'),
			'cb' => array('exactname'),
		 	'value' => array('query', 'searchuser'));
		$this->setPrefs($template, $prefs, $prefsettings);
		vB_Search_Searchtools::searchIntroRegisterHumanVerify($template);

		if (isset($registers) AND is_array($registers) )
		{
			foreach($registers as $key => $value)
			{
				$template->register($key, htmlspecialchars_uni($value));
			}
		}

		($hook = vBulletinHook::fetch_hook('search_listui_complete')) ? eval($hook) : false;

		return $template->render();
	}

	/**
	 * method to create this object
	 *
	 * @param integer $id
	 * @return object
	 */
	public function create_item($id)
	{
		return vBCms_Search_Result_Article::create($id);
	}

	/**
	 * You can create from an array also
	 *
	 * @param integer $id
	 * @return object
	 */
	public function create_array($ids)
	{
		return vBCms_Search_Result_Article::create_array($ids);
	}

	/**
	 * can this type be grouped?
	 *
	 * @return boolean
	 */
	public function can_group()
	{
		return false;
	}

	// ###################### Start group_by_default ######################
	/**
	 * is this type grouped by default?
	 *
	 * @return boolean
	 */
	public function group_by_default()
	{
		return false;
	}

	/**
	 * can this type be searched?
	 *
	 * @return boolean
	 */
	public function cansearch()
	{
		return true;
	}

	/**
	 * return any inline moderation options
	 *
	 * @return options array
	 *
	 * In general this doesn't get moderated.
	 */
	public function get_inlinemod_options()
	{
		global $vbphrase, $show;

		return array();
	}


	/**
	 * what type of inline moderation is available?
	 *
	 * @return
	 */
	public function get_inlinemod_type()
	{
		return '';
	}

	/**
	 * what inline moderation actions are available?
	 *
	 * @return
	 */
	public function get_inlinemod_action()
	{
		return '';
	}

/**
 * Each search type has some responsibilities, one of which is to tell
 * what are its defaults
 *
 * @return array
 */
	public function additional_pref_defaults()
	{
		$retval = array(
			'query'         => '',
			'exactname'     => 0,
			'searchuser'    => '',
			'searchdate'    => 0,
			'titleonly'     => 0,
			'beforeafter'   => 'after',
			'sortby'        => 'dateline',
			'sortorder'     => 'descending'
		);

		($hook = vBulletinHook::fetch_hook('search_pref_defaults')) ? eval($hook) : false;

		return $retval;
	}

	public function get_db_query_info($fieldname)
	{
		if ($fieldname == 'views')
		{
			$result['corejoin']['node'] = sprintf(" INNER JOIN %scms_node AS node ON (searchcore.contenttypeid = %u AND node.contentid = searchcore.groupid)", TABLE_PREFIX,
				vB_Types::instance()->getContentTypeID("vBCms_Article"));
			$result['groupjoin']['node'] = sprintf(" INNER JOIN %scms_node AS node ON (searchgroup.contenttypeid = %u AND node.contentid = searchgroup.groupid)", TABLE_PREFIX,
				vB_Types::instance()->getContentTypeID("vBCms_Article"));
			$result['join']['nodeinfo'] = sprintf(" INNER JOIN %scms_nodeinfo AS nodeinfo ON (nodeinfo.nodeid = node.nodeid)", TABLE_PREFIX);
			$result['table'] = 'nodeinfo';
			$result['field'] = 'viewcount';
		}
		else
		{
			$result = false;
		}

		($hook = vBulletinHook::fetch_hook('search_dbquery_info')) ? eval($hook) : false;

		return $result;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 30550 $
|| ####################################################################
\*======================================================================*/
