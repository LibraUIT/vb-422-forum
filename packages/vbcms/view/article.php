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
 * CMS Article View
 * Default view for rendering an article.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: $
 * @since $Date: $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_View_Article extends vB_View_Content
{
	/**
	 * Result id's of inner views for prenotification.
	 *
	 * @var array mixed
	 */
	protected $_cache_results = array('vbcms_article_css');



	/*Render========================================================================*/

	/**
	 * Prepares properties for rendering.
	 */
	protected function prepareProperties()
	{
		parent::prepareProperties();

		// vB_View_Content has already htmlspecialchars_uni($this->title) so we should not htmlspecialchars_uni again here. Fixed bug #29663
		// $this->title = htmlspecialchars_uni($this->title);
		$this->css = new vB_View('vbcms_article_css');
		$this->author_phrase = new vB_Phrase('vbcms', 'author');

		if ($this->pagelist AND sizeof($this->pagelist) > 1)
		{
			// create a route
			$route = new vBCms_Route_Content();
			$route->setSegments(array('node' => $this->nodesegment, 'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'View')));

			$pagelist = $this->pagelist;
			if (empty($pagelist[1]))
			{
				$pagelist[1] = $this->title;
			}
			$this->pagelist = $pagelist;

			$pages = array();
			foreach ($this->pagelist AS $pagenum => $title)
			{
				$route->setParameter(0, $pagenum);

				if (!is_array($title))
				{
					$title = $title ? $title : new vB_Phrase('vbcms', 'page_x', $pagenum);

					// undo the 'stop_parse' from the [page] bbcode and strip bbcode and html
					$title = vbchop(strip_tags(strip_bbcode(str_replace(array('&#91;', '&#93;'), array('[', ']'), $title))), 75);

					$pages[$pagenum] = array(
						'url'      => $route->getCurrentURL(null, array($pagenum)),
						'title'    => ($pagenum > 1 AND $this->htmlstate != 'off') ? htmlspecialchars_uni($title) : $title,
						'selected' => ($pagenum == $this->current_page) ? 1 : 0
					);
				}
			}

			if ($this->current_page > 1)
			{
				$this->prev_page_url = $pages[$this->current_page - 1]['url'];
				$this->prev_page_phrase = new vB_Phrase('vbcms', 'previous');
			}

			if ($this->current_page < sizeof($pages))
			{
				$this->next_page_url = $pages[$this->current_page + 1]['url'];
				$this->next_page_phrase = new vB_Phrase('vbcms', 'next');
			}

			$this->pagelist = $pages;
		}
		else
		{
			$this->pagelist = false;
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28709 $
|| ####################################################################
\*======================================================================*/
