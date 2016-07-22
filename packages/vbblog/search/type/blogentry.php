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

/**
 * @package vBulletin
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 28678 $
 * @since $Date: 2008-12-03 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */

require_once (DIR . '/vb/search/type.php');
require_once (DIR . '/packages/vbforum/search/result/forum.php');

/**
 * @package vBulletin
 * @subpackage Search
 */
class vBBlog_Search_Type_BlogEntry extends vB_Search_Type
{
	public function fetch_validated_list($user, $ids, $gids)
	{
		$list = array_fill_keys($ids, false);
		$items = vBBlog_Search_Result_BlogEntry::create_array($ids);
		foreach ($items as $id => $item)
		{
			if ($item->can_search($user))
			{
				$list[$id] = $item;
			}
		}

		$retval = array('list' => $list, 'groups_rejected' => array());

		($hook = vBulletinHook::fetch_hook('search_validated_list')) ? eval($hook) : false;

		return $retval;
	}

	/**
	 * @param unknown_type $id
	 */
	public function create_item($id)
	{
		return vBBlog_Search_Result_BlogEntry::create($id);
	}

	/**
	 * You can create from an array also
	 *
	 * @param integer $id
	 * @return object
	 */
	public function create_array($ids)
	{
		return vBBlog_Search_Result_BlogEntry::create_array($ids);
	}
	public function get_display_name()
	{
		return new vB_Phrase('search', 'searchtype_blog_entries');
	}

	public function additional_pref_defaults()
	{
		$retval = array(
			'query'       => '',
			'titleonly'   => 0,
			'nocache'     => '',
			'searchuser'  => '',
			'exactname'   => '',
			'searchdate'  => 0,
			'beforeafter' => 0,
			'sortby'      => 'dateline',
			'sortorder'	  => 'descending',
			'tag'         => '',
			'ignorecomments' => ''
		);

		($hook = vBulletinHook::fetch_hook('search_pref_defaults')) ? eval($hook) : false;

		return $retval;
	}

	// ###################### Start listUi ######################

	/**
	 * vBForum_Search_Type_Post::listUi()
	 * This function generates the search elements for the user to search for posts
	 * 
	 * @param  [type] $prefs         the array of user preferences
	 * @param  [type] $contenttypeid added for PHP 5.4 strict standards compliance
	 * @param  [type] $registers     added for PHP 5.4 strict standards compliance
	 * @param  [type] $template_name added for PHP 5.4 strict standards compliance
	 * @return $html: complete html for the search elements
	 */
	public function listUi($prefs = null, $contenttypeid = null, $registers = null, $template_name = null)
	{
		$phrase = new vB_Legacy_Phrase();
		$phrase->add_phrase_groups(array('vbblogglobal', 'vbblogcat'));

		global $vbulletin;
		$template = vB_Template::create('search_input_blogentry');
		$template->register('securitytoken', $vbulletin->userinfo['securitytoken']);
		$template->register('contenttypeid', $this->get_contenttypeid());

		$prefsettings = array(
			'select'=> array('searchdate', 'beforeafter', 'sortby',
				'titleonly', 'sortorder'),
			'cb' => array('nocache', 'exactname', 'ignorecomments'),
		 	'value' => array('tag', 'query', 'searchuser')
		);

		$this->setPrefs($template, $prefs, $prefsettings);
		vB_Search_Searchtools::searchIntroRegisterHumanVerify($template);

		($hook = vBulletinHook::fetch_hook('search_listui_complete')) ? eval($hook) : false;

		return $template->render();
	}

	public function add_advanced_search_filters($criteria, $registry)
	{
		$registry->extrafilters['type'] = $this;
		$registry->extrafilters['filters'] = array();

		//this is more than a bit of a hack.  If, on the advanced search page, we choose
		//to include blog comments we want to also search the comment type (which will
		//group to this type)
		//
		//This approach allows us to do a keyword search on just the blog posts while
		//also allowing us to search comments and roll those up to the blogs in just
		//one query.
		//
		//this relys on both the fact that the blog comments group by default and that
		//the entry UI (implicitly) does a "group default". If either of those assumptions
		//change this we'll need to revisit how this operates.
		if (!$registry->GPC['ignorecomments'])
		{
			$types = array(
				$this->get_contenttypeid(),
				vB_Types::instance()->getContentTypeID('vBBlog_BlogComment')
			);
			$criteria->add_contenttype_filter($types);
		}

		($hook = vBulletinHook::fetch_hook('search_advanced_filters')) ? eval($hook) : false;
	}

	public function get_db_query_info($fieldname)
	{
		$result['corejoin']['blog'] = "
			JOIN " . TABLE_PREFIX. "blog AS blog ON searchcore.groupcontenttypeid = " .
				$this->get_contenttypeid() . " AND searchcore.groupid = blog.blogid";

		$result['groupjoin']['blog'] = "
			JOIN " . TABLE_PREFIX. "blog AS blog ON searchgroup.contenttypeid = " .
				$this->get_contenttypeid() . " AND searchgroup.groupid = blog.blogid";

		$result['table'] = 'blog';
		if($fieldname == 'bglastcomment')
		{
			$result['field'] = 'lastcomment';
		}
		elseif ($fieldname == 'views')
		{
			$result['field'] = 'views';
		}
		else
		{
			$result = false;
		}
		
		($hook = vBulletinHook::fetch_hook('search_dbquery_info')) ? eval($hook) : false;

		return $result;
	}

	public function __construct()
	{
		parent::__construct();
		//make sure that this gets initialized
		global $vbulletin;
		if (!$vbulletin->userinfo['blogcategorypermissions'])
		{
			require_once (DIR . '/includes/blog_functions_shared.php');
			prepare_blog_category_permissions($vbulletin->userinfo, true);
		}
	}

	protected $package = "vBBlog";
	protected $class = "BlogEntry";

	protected $type_globals = array (
		'ignorecomments' => TYPE_INT
	);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
