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
if (!VB_API) die;

define('VB_API_LOADLANG', true);

loadCommonWhiteList();

$VB_API_WHITELIST = array(
	'response' => array(
		'blocks' => array(
			'visitors' => array(
				'html' => array(
					'block_data' => array(
						'visitorbits' => array(
							'*' => array(
								'user' => array(
									'userid', 'username', 'invisiblemark',
									'buddymark'
								)
							)
						)
					)
				)
			),
			'groups' => array(
				'html' => array(
					'block_data' => array(
						'socialgroupbits' => array(
							'*' => array(
								'showgrouplink',
								'socialgroup' => array(
									'groupid', 'shortdescription', 'iconurl', 'name',
									'name_html'
								)
							)
						),
						'membergroupbits' => array(
							'*' => array(
								'usergroup' => array(
									'usergroupid', 'opentag', 'title', 'closetag'
								)
							)
						)
					)
				)
			),
			'visitor_messaging' => array(
				'block_data' => array(
					'messagebits' => array(
						'*' => array(
							'message' => array(
								'vmid', 'avatarurl', 'postuserid', 'userid', 'username',
								'postuserid', 'profileusername',
								'time', 'message', 'hostuserid', 'guestuserid',
								'converse_description_phrase'
							),
							'show' => array(
								'profile', 'detailedtime', 'moderation', 'edit',
								'converse', 'reportlink'
							)
						)
					),
					'lastcomment',
					'pagenav', 'pagenumber', 'messagetotal'
				),
			),
			'stats' => array(
				'html' => array(
					'block_data'
				)
			),
			'contactinfo' => array(
				'html' => array(
					'block_data' => array(
						'imbits' => array(
							'*' => array(
								'imserviceid', 'imtitle', 'imusername',
							)
						)
					)
				)
			),
			'albums' => array(
				'html' => array(
					'block_data' => array(
						'albumbits' => array(
							'*' => array(
								'album' => array(
									'albumid', 'title_html', 'picturetime', 'lastpicturedate',
								)
							)
						)
					)
				)
			),
			'aboutme' => array(
				'block_data' => array(
					'fields' => array(
						'*' => array(
							'category' => array(
								'title', 'description',
								'fields' => array(
									'*' => array(
										'profilefield' => array(
											'profilefieldid', 'title', 'value'
										)
									)
								)
							)
						)
					)
				)
			),
			'friends' => array(
				'block_data' => array(
					'start_friends', 'end_friends', 'showtotal', 'pagenav',
					'friendbits' => array(
						'*' => array(
							'remove' => array(
								'userid', 'return'
							),
							'user' => array(
								'userid', 'username',
								'onlinestatus' => array('onlinestatus'),
								'usertitle', 'showicq', 'showmsn', 'showaim', 'showyahoo',
								'showskype', 'icq', 'msn', 'aim', 'yahoo',
								'skype', 'avatarurl'
							),
							'show' => array(
								'breakfriendship'
							)
						)
					)
				)
			),
		),
		'prepared' => array(
			'birthday', 'age', 'signature', 'userid', 'username', 'displayemail',
			'homepage', 'profileurl', 'hasimdetails', 'usertitle', 'profilepicurl',
			'onlinestatus' => array('onlinestatus'),
			'canbefriend', 'homepage', 'usernotecount', 'joindate', 'action',
			'where', 'lastactivitytime', 'posts', 'avatarurl'
		), 'selected_tab'
	),
	'show' => array(
		'vcard', 'edit_profile', 'hasimicons', 'usernotes', 'usernotepost',
		'usernoteview', 'email', 'pm', 'addbuddylist', 'removebuddylist',
		'addignorelist', 'removeignorelist', 'userlists', 'messagelinks',
		'contactlinks', 'can_customize_profile', 'view_conversation',
		'post_visitor_message',	'vm_block', 'usernote_block', 'usernote_post',
		'usernote_data', 'album_block', 'simple_link', 'edit_link', 'profile_category_title',
		'profilefield_edit', 'extrainfo', 'lastentry', 'infractions', 'giveinfraction',
		'private', 'lastcomment', 'latestentry', 'avatar', 'profilepic',
		'subscribelink', 'emaillink', 'homepage', 'pmlink', 'gotonewcomment'
	),
	'vboptions' => array(
		'postminchars'
	)
);

function api_result_prerender_1($t, &$r)
{
	global $vbulletin, $vbphrase;

	switch ($t)
	{
		case 'memberinfo_visitormessage_deleted':
		case 'memberinfo_visitormessage':
		case 'memberinfo_visitormessage_ignored':
		case 'memberinfo_visitormessage_global_ignored':
		case 'visitormessage_simpleview_deleted':
		case 'visitormessage_simpleview':
			$r['message']['time'] = $r['message']['dateline'];
			break;
		case 'MEMBERINFO':
			// Resolve the birthday/age field
			$profileField = array();
			if ($r['userinfo']['showbirthday'] > 0)
			{
				if ($r['userinfo']['showbirthday'] == 1 AND !empty($r['prepared']['age']))
				{
					$profileField = array(
						'profilefield' => array(
							'title' => $vbphrase['age'],
							'value' => "$r[prepared][age]",
						),
					);
				}
				else if ($r['userinfo']['showbirthday'] == 2 AND !empty($r['prepared']['birthday']) AND !empty($r['prepared']['age']))
				{
					$profileField = array(
						'profilefield' => array(
							'title' => $vbphrase['birthday'],
							'value' => $r['prepared']['birthday'] . " (" . $r['prepared']['age'] . ")",
						),
					);
				}
				else if ($r['userinfo']['showbirthday'] == 3 AND !empty($r['prepared']['birthday'])) 
				{
					$profileField = array(
						'profilefield' => array(
							'title' => $vbphrase['birthday'],
							'value' => $r['prepared']['birthday'],
						),
					);
				}
			}
			// Add the profile field to the response block
			if (!empty($profileField))
			{
				$r['blocks']['aboutme']['block_data']['fields']['category']['fields'][] = $profileField;
			}
			$r['prepared']['joindate'] = $r['userinfo']['joindate'];
			$r['prepared']['lastactivitytime'] = $r['userinfo']['lastactivity'];
			$r['prepared']['usecoppa'] = $vbulletin->options['usecoppa'];
			break;
	}
}

vB_APICallback::instance()->add('result_prerender', 'api_result_prerender_1', 1);

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/