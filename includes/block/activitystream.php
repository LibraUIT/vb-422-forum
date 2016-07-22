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

class vB_BlockType_Activitystream extends vB_BlockType
{
	/**
	 * The Productid that this block type belongs to
	 * Set to '' means that it belongs to vBulletin forum
	 *
	 * @var string
	 */
	protected $productid = '';

	/**
	 * The title of the block type
	 * We use it only when reload block types in admincp.
	 * Automatically set in the vB_BlockType constructor.
	 *
	 * @var string
	 */
	protected $title = '';

	/**
	 * The description of the block type
	 * We use it only when reload block types in admincp. So it's static.
	 *
	 * @var string
	 */
	protected $description = '';

	/**
	 * The block settings
	 * It uses the same data structure as forum settings table
	 * e.g.:
	 * <code>
	 * $settings = array(
	 *     'varname' => array(
	 *         'defaultvalue' => 0,
	 *         'optioncode'   => 'yesno'
	 *         'displayorder' => 1,
	 *         'datatype'     => 'boolean'
	 *     ),
	 * );
	 * </code>
	 * @see print_setting_row()
	 *
	 * @var string
	 */
	protected $settings = array(
		'activitystream_limit' => array(
			'defaultvalue' => 5,
			'displayorder' => 1,
			'datatype'     => 'integer'
		),
		'activitystream_date' => array(
			'defaultvalue' => 0,
			'displayorder' => 1,
			'optioncode'   => 'radio:piped
0|last24hours
1|last7days
2|last30days
3|alltime'),
		'activitystream_sort' => array(
			'defaultvalue' => 0,
			'displayorder' => 3,
			'optioncode'   => 'radio:piped
0|stream_new
1|stream_popular'),
		'activitystream_filter' => array(
			'defaultvalue' => 0,
			'displayorder' => 4,
			'optioncode'   => 'radio:piped
0|stream_all_items
1|stream_photos
2|stream_forum
3|stream_cms
4|stream_blog
5|stream_socialgroup'),
	);

	public function getData()
	{
		global $vbphrase;

		$activity = new vB_ActivityStream_View_Block($vbphrase);
		return $activity->process($this->config);
	}

	public function getHTML($streamdata = false)
	{
		if (!$streamdata)
		{
			$streamdata = $this->getData();
		}

		if ($streamdata)
		{
			$templater = vB_Template::create('block_activitystream');
				$templater->register('blockinfo', $this->blockinfo);
				$templater->register('stream', $streamdata);
			return $templater->render();
		}
	}

	/*
	 * Cache is just per user. It is nigh impossible to build a list of probable permissions for all of the
	 * possible activitystream items without processing the stream. Some items are removed during the fetch query.
	 *
	 */
	public function getHash()
	{
		$context = new vB_Context('forumblock' ,
			array (
				'blockid' => $this->blockinfo['blockid'],
				'userid'  => $this->userinfo['userid'],
				THIS_SCRIPT
			)
		);

		return strval($context);
	}
}
