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
 * CMS Article Content Item
 * The model item for CMS articles.
 *
 * @author vBulletin Development Team
 * @version $Revision: 61698 $
 * @since $Date: 2012-04-19 14:46:06 -0700 (Thu, 19 Apr 2012) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Item_Content_PhpEval extends vBCms_Item_Content_StaticPage
{
	/*Properties====================================================================*/

	/**
	 * A class identifier.
	 *
	 * @var string
	 */
	protected $class = 'PhpEval';

	/**
	 * A package identifier.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	/**
	 * The DM for handling CMS Article data.
	 *
	 * @var string
	 */
	protected $dm_class = 'vBCms_DM_Node';

	protected $rendered = false;

	/**
	 * This function verifies that the rendered text is up to date.
	 *
	 * @return mixed
	 */
	protected function renderText($preview_only = true)
	{
		$hash = strtolower($this->package) . '_rendered_' . $this->getNodeId() .
			vB::$vbulletin->session->vars['sessionurl'];
		if ($this->config['cache_ttl'] AND $this->rendered = vB_Cache::instance()->read($hash, true, true))
		{
				if ($preview_only OR $this->rendered['rendered_text'])
			{
					return;
			}
		}

		$this->rendered = array();
		$valid = true;
		$this->Load(self::INFO_CONFIG);
			//Only render the pagetext if we have to
		if ($preview_only AND !empty($this->config['previewtext']))
		{
			try
			{
				$content = eval($this->config['previewtext']);

				if ((!isset($content) OR empty($content)) AND isset($output) AND !empty($output))
				{
					$content = $output;
				}

				$this->rendered['rendered_preview'] = $content;
				$this->rendered['rendered_text'] = false;
				if ($this->config['cache_ttl'])
				{
					vB_Cache::instance()->write($hash , $this->rendered, $this->config['cache_ttl'], $this->getContentCacheEvent());
				}
				return;
			}
			catch(exception $e)
			{
				$this->rendered['rendered_preview'] = $e->getMessage();
				$valid = false;
			}
		}

		//We have to render both
		try
		{
			$content = eval($this->config['pagetext']);

			if ((!isset($content) OR empty($content)) AND isset($output) AND !empty($output))
			{
				$content = $output;
			}
			$this->rendered['rendered_text'] = $content;
		}
		catch(exception $e)
		{
			$this->rendered['rendered_text'] = $e->getMessage();
			$valid = false;
		}

		try
		{
			if (empty($this->config['previewtext']))
			{
				$this->rendered['rendered_preview'] = substr(strip_tags($output, '<br />'), 0,
					vB::$vbulletin->options['default_cms_previewlength']);
			}
			else
			{
				$content = eval($this->config['previewtext']);

				if ((!isset($content) OR empty($content)) AND isset($output) AND !empty($output))
				{
					$content = $output;
				}
				$this->rendered['rendered_preview'] = $content;
			}

		}
		catch(exception $e)
		{
			$this->rendered['rendered_preview'] = $e->getMessage();
			$valid = false;
		}

		if ($valid AND $this->config['cache_ttl'])
		{
			vB_Cache::instance()->write($hash , $this->rendered, $this->config['cache_ttl'],
				$this->getContentCacheEvent());
		}
	}
	/**
	 * Fetches the contentid, which is the nodeid.
	 *
	 * 	 * @return int
	 */
	public function getContentId($contentonly = false)
	{
		//For sections, and other types in the future, we have no separate contentid, just a nodeid
		$this->Load();

		return $this->nodeid;
	}

	/**
	 * We override the two default functions, because we don't want to show raw php.
	 *
	 * @return string
	 */
	public function getPageText()
	{
		//We may need to render
		return '';
	}
	public function getPreviewText()
	{
		//We may need to render
		return '';
	}

	public function getRenderedText()
	{
		//We may need to render
		if (!$this->rendered OR !$this->rendered['rendered_text'])
		{
			$this->renderText(false);
		}
		return $this->rendered['rendered_text'];
	}

	/**** returns the item previewtext
	 *
	 * @return string
	 ****/
	public function getRenderedPreviewText()
	{
		//We may need to render
		if (!$this->rendered)
		{
			$this->renderText();
		}
		return $this->rendered['rendered_preview'];
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 61698 $
|| ####################################################################
\*======================================================================*/
