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

require_once(DIR . '/includes/functions_file.php');
require_once(DIR . '/includes/class_upload.php');
require_once(DIR . '/includes/class_image.php');
require_once(DIR . '/packages/vbattach/attach.php');

/*
	Hack the upload attachment class to avoid a couple of validation checks that
	are unneeded and otherwise difficult to work around
*/
class vB_Upload_Attachment_Backend extends vB_Upload_Attachment
{
	function accept_upload(&$upload)
	{
		$this->upload['filename'] = trim($upload['name']);
		$this->upload['filesize'] = intval($upload['size']);
		$this->upload['location'] = trim($upload['tmp_name']);
		$this->upload['extension'] = strtolower(file_extension($this->upload['filename']));
		$this->upload['thumbnail'] = '';
		$this->upload['filestuff'] = '';
		return true;
	}

	//don't check user permissions for default data images
	function fetch_max_uploadsize($extension)
	{
		return 100000;
	}

	function check_attachment_overage($filehash, $filesize)
	{
		$return_value = parent::check_attachment_overage($filehash, $filesize);

		if (!$return_value)
		{
			// over the limit; make sure the source file doesn't get deleted.
			$this->upload['location'] = '';
		}

		return $return_value;
	}

	function set_error()
	{
		// don't delete source file on error
		$this->upload['location'] = '';

		$args = func_get_args();
		parent::set_error($args);
	}

	function save_upload()
	{
		// if filestuff is not empty, assume it was resized, otherwise put the image in there
		if (empty($this->upload['filestuff']))
		{
			$this->upload['filestuff'] = file_get_contents($this->upload['location']);
		}
		// keep the source file from being deleted
		$this->upload['location'] = '';

		return parent::save_upload();
	}
}


function add_default_data()
{
	global $vbulletin;
	require_once(DIR . "/install/cmsdefaultdata/default_data_queries.php");
	foreach($cms_data_queries AS $query)
	{
		$vbulletin->db->query_write($query);
	}

	//we need to truncate the grids table in order to make all of the ids line up
	require_once(DIR . "/includes/adminfunctions_cms.php");
	$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "cms_grid");
	xml_import_grid(file_get_contents(DIR . "/install/vbulletin-grid.xml"), true);

	// Make the hard-coded contenttypeids in the default data queries match the contenttypeids in the contenttype table.
	// The default data currently only contains Sections and Articles. If that changes, add the other contenttypes here.
	fix_contenttypeid('Section', 17);
	fix_contenttypeid('Article', 18);
}

function add_default_attachments($userid)
{
	@set_time_limit(0);
	global $vbulletin, $startimage, $endimage;

	$imagedir = DIR . '/install/cmsdefaultdata/attachments/';

	//fake the user login if we don't have a user
	if (!$vbulletin->userinfo)
	{
		$vbulletin->userinfo = fetch_userinfo($userid);
		cache_permissions($vbulletin->userinfo, true);
	}

	$errors = fix_images($imagedir);
	$vbulletin->db->query_write(
		"UPDATE " . TABLE_PREFIX . "cms_node
		SET userid = " . $vbulletin->userinfo['userid'] . " WHERE userid = 1"
	);
	//if we can, automatically blow out the cache.
	if (VB_AREA != 'Upgrade' AND VB_AREA != 'Install')
	{
		print_cp_header($vbphrase['category_manager']);
	}
	if (method_exists(vB_Cache::instance(), 'clean'))
	{
		vB_Cache::instance()->clean(false);
	}

	return $errors;
}


function fix_images($filedirectory)
{
	global $vbulletin;
	$contenttypeid = $vbulletin->db->query_first("SELECT contenttypeid FROM " . TABLE_PREFIX . "contenttype AS c
		INNER JOIN " . TABLE_PREFIX . "package AS p ON p.packageid = c.packageid
		WHERE p.productid = 'vbcms' AND c.class = 'Article'");
	$contenttypeid = $contenttypeid['contenttypeid'];
	$set = $vbulletin->db->query_read("
		SELECT cms_node.nodeid, cms_article.*
		FROM " . TABLE_PREFIX . "cms_article AS cms_article JOIN " .
			TABLE_PREFIX . "cms_node AS cms_node ON
				(cms_article.contentid = cms_node.contentid AND cms_node.contenttypeid = $contenttypeid)
	");

	$errors = array();

	while ($row = $vbulletin->db->fetch_array($set))
	{
		$attachment_map = array();
		$pagetext = $row['pagetext'];

		//get attachments and replace with new ids
		$matches = array();
		if (preg_match_all("#\\[ATTACH=CONFIG\\](\\d+)\\[/ATTACH\\]#i", $row['pagetext'], $matches))
		{
			foreach($matches[1] AS $attachmentid)
			{
				if (!array_key_exists($attachmentid, $attachment_map))
				{
					$file_name = get_image_filename($filedirectory, $attachmentid, $errors);
					$attachment_map[$attachmentid] = attach_image($file_name, $filedirectory, $row['nodeid'], $errors);
				}
			}

			if (count($attachment_map))
			{
				$orig = array();
				$replacement = array();
				foreach($attachment_map AS $oldid => $newid)
				{
					$orig[] = "[ATTACH=CONFIG]" . $oldid . "[/ATTACH]";
					$replacement[] = "[ATTACH=CONFIG]" . $newid . "[/ATTACH]";
				}

				if (count($orig))
				{
					$pagetext = str_replace($orig, $replacement, $pagetext);
				}

				$vbulletin->db->query_write("
					UPDATE " . TABLE_PREFIX . "cms_article
					SET pagetext = '" . $vbulletin->db->escape_string($pagetext) . "'
					WHERE contentid = $row[contentid]
				");

			}
		}

		//find and replace attachments added as IMG tags.  Otherwise they'll look for the live site, which is bad.

		$matches = array();
		if (preg_match_all("#\\[IMG\\][^]]*attachmentid=(\\d+)[^]]*\\[/IMG\\]#i", $row['pagetext'], $matches))
		{
			$orig = array();
			$replacement = array();
			foreach($matches[1] AS $key => $attachmentid)
			{
				if (!array_key_exists($attachmentid, $attachment_map))
				{
					$file_name = get_image_filename($filedirectory, $attachmentid, $errors);
					$attachment_map[$attachmentid] = attach_image($file_name, $filedirectory, $row['nodeid'], $errors);
				}

				if ($attachment_map[$attachmentid])
				{
					$orig[] = $matches[0][$key];
					$replacement[] = "[ATTACH]" . $attachment_map[$attachmentid] . "[/ATTACH]";
				}
			}
			if (count($orig))
			{
				$pagetext = str_replace($orig, $replacement, $pagetext);
				$vbulletin->db->query_write("
					UPDATE " . TABLE_PREFIX . "cms_article
					SET pagetext = '" . $vbulletin->db->escape_string($pagetext) . "'
					WHERE contentid = $row[contentid]
				");
			}
		}

		//handle preview images
		$matches = array();
		if (preg_match("#attachmentid=(\\d+)&#", $row['previewimage'], $matches))
		{
			$attachmentid = $matches[1];
			if ($attachmentid)
			{
				if (!array_key_exists($attachmentid, $attachment_map))
				{
					$file_name = get_image_filename($filedirectory, $attachmentid, $errors);
					$attachment_map[$attachmentid] = attach_image($file_name, $filedirectory, $row['nodeid'], $errors);
				}

				$newid = $attachment_map[$attachmentid];
				if ($newid)
				{
					$record = $vbulletin->db->query_first($q = "
						SELECT thumbnail_width, thumbnail_height
						FROM " .
							TABLE_PREFIX . "attachment AS attach INNER JOIN "  .
							TABLE_PREFIX . "filedata AS data ON data.filedataid = attach.filedataid WHERE attachmentid = $newid"
					);

					$vbulletin->db->query_write($q = "
						UPDATE " . TABLE_PREFIX . "cms_article
						SET previewimage = 'attachment.php?attachmentid=$newid&amp;cid=$contenttypeid',
							imagewidth = $record[thumbnail_width], imageheight = $record[thumbnail_height]
						WHERE contentid = $row[contentid]
					");
				}
				else
				{
					$errors[] = "<p>Could not find attachmentid $attachmentid</p>";
					if (VB_AREA != 'Upgrade' AND VB_AREA != 'Install')
					{
						echo end($errors);
					}
				}
			}
		}
	}

	return $errors;
}


function attach_image($file_name, $filedirectory, $nodeid, &$errors)
{
	global $vbulletin;
	//make a copy of the file, the attachment code assumes its a temp file and deletes it.

	$file_location = "$filedirectory/$file_name";
	if (!$file_name OR !file_exists($file_location))
	{
		$errors[] = "<p>Could not find file $file_location\n";
		if (VB_AREA != 'Upgrade' AND VB_AREA != 'Install')
		{
			echo end($errors);
			exit;
		}
		else
		{
			return;
		}
	}

	//need to clear the cache so that the filesize operation works below.
	clearstatcache();

	$attachment = array(
		'name'     => $file_name,
		'tmp_name' => $file_location,
		'error'    => array(),
		'size'     => @filesize($file_location)
	);

	$poststarttime = time();
	$posthash = md5($vbulletin->GPC['poststarttime'] . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']);

	$contenttypeid = vB_Types::instance()->getContentTypeID("vBCms_Article");

	// here we call the attach/file data combined dm
	$attachdata =& datamanager_init('AttachmentFiledata', $vbulletin, ERRTYPE_SILENT, 'attachment');
	$attachdata->set('contenttypeid', $contenttypeid);
	$attachdata->set('posthash', $posthash);
	$attachdata->set('contentid', $nodeid);
	$attachdata->set_info('categoryid', 0);
	$attachdata->set('state', 'visible');

	$upload = new vB_Upload_Attachment_Backend($vbulletin);
	$upload->contenttypeid = $contenttypeid;
	$upload->userinfo = $vbulletin->userinfo;
	$upload->data =& $attachdata;
	$upload->image =& vB_Image::fetch_library($vbulletin);

	$attachmentid = $upload->process_upload($attachment);
	if (!$attachmentid)
	{
		$errors[] = "<p>Error loading image '$file_name':" . $upload->error . "</p>";
		if (VB_AREA != 'Upgrade' AND VB_AREA != 'Install')
		{
			echo end($errors);
		}
	}
	return $attachmentid;
}

function get_image_filename($filedirectory, $id, &$errors)
{
	$filepath = "$filedirectory/$id.jpg";
	if (file_exists($filepath))
	{
		return "$id.jpg";
	}

	$filepath = "$filedirectory/$id.png";
	if (file_exists($filepath))
	{
		return "$id.png";
	}

	$error = "<p>Could not find file for for id $id</p>";
	if (VB_AREA != 'Upgrade' AND VB_AREA != 'Install')
	{
		echo $error;
		exit;
	}
}

/**
 * Changes the hard-coded contenttypeids in the default CMS data so they use the correct contenttypeids
 *
 * @param	string	The content type class name
 * @param	int	The old (hard-coded) content type id
 */
function fix_contenttypeid($class, $oldcontenttypeid)
{
	global $vbulletin;

	static $packageid = null;
	if ($packageid === null)
	{
		$package = $vbulletin->db->query_first("
			SELECT packageid
			FROM " . TABLE_PREFIX . "package
			WHERE productid = 'vbcms'
		");
		$packageid = intval($package['packageid']);
	}

	$contenttypeid = $vbulletin->db->query_first("
		SELECT contenttypeid
		FROM " . TABLE_PREFIX . "contenttype
		WHERE
			class = '" . $vbulletin->db->escape_string($class) . "'
				AND
			packageid = $packageid
	");
	$contenttypeid = intval($contenttypeid['contenttypeid']);
	$oldcontenttypeid = intval($oldcontenttypeid);

	if ($contenttypeid > 0 AND $contenttypeid != $oldcontenttypeid)
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "cms_node
			SET contenttypeid = $contenttypeid
			WHERE contenttypeid = $oldcontenttypeid
		");
	}
}
