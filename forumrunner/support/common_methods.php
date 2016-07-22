<?php
/*
 * Forum Runner
 *
 * Copyright (c) 2010-2011 to End of Time Studios, LLC
 *
 * This file may not be redistributed in whole or significant part.
 *
 * http://www.forumrunner.com
 */

$methods = array(
    /*
     * do_version()
     *
     * output:
     *
     * version
     */
    'version' => array(
	'include' => 'misc.php',
	'function' => 'do_version',
    ),

    /*
     * do_login()
     *
     * input:
     *
     * username
     * password
     * fr_username (optional)
     *
     * output:
     *
     * authenticated
     * requires_authentication
     */
    'login' => array(
	'include' => 'login.php',
	'function' => 'do_login',
    ),

    /*
     * do_logout()
     *
     * input:
     *
     * fr_username (optional)
     *
     * output:
     *
     * success
     * requires_authentication
     */
    'logout' => array(
	'include' => 'login.php',
	'function' => 'do_logout',
    ),

    /*
     * get_forum_summary()
     *
     * input:
     *
     * none
     *
     * output:
     *
     * unused.
     */
    'get_forum_summary' => array(
	'include' => 'get_forum.php',
	'function' => 'do_get_forum_summary',
    ),

    /*
     * get_forum()
     *
     * input:
     *
     * forumid
     * password
     * perpage
     * page
     *
     * output:
     *
     * threads => array(
     *   thread_id
     *   new_posts
     *   forum_id
     *   thread_title
     *   thread_preview
     *   post_userid
     *   post_lastposttime
     *   post_username
     * )
     * total_threads
     * threads_sticky => array(
     *   same_as_above
     * )
     * total_sticky_threads
     * forums => array(
     *   id
     *   name
     *   password
     *   desc
     * )
     * prefixes => array(
     *   prefixid
     *   prefixcaption
     * )
     * prefixrequired
     */
    'get_forum' => array(
	'include' => 'get_forum.php',
	'function' => 'do_get_forum',
    ),

    /*
     * search_getnew()
     *
     * input:
     *
     * do (getnew or getdaily)
     * days
     * page
     * perpage
     *
     * output:
     *
     * threads => array(
     *   thread_id
     *   new_posts
     *   forum_id
     *   forum_title
     *   thread_title
     *   thread_preview
     *   post_userid
     *   post_lastposttime
     *   post_username
     * )
     * total_threads
     */
    'search_getnew' => array(
	'include' => 'search_forum.php',
	'function' => 'do_search_getnew',
    ),

    /*
     * get_pm_folders()
     *
     * input:
     *
     * none (requires logged in)
     *
     * output:
     *
     * folders => array(
     *   foldernames
     *  )
     */
    'get_pm_folders' => array(
	'include' => 'pms.php',
	'function' => 'do_get_pm_folders',
    ),

    /*
     * get_pms()
     *
     * input:
     *
     * folderid
     * perpage
     * page
     *
     * output:
     *
     * pms => array(
     *   id
     *   new_pm
     *   username
     *   title
     *   message
     *   pm_timestamp
     * )
     * perpage
     * total_pms
     * unread_pms
     */
    'get_pms' => array(
	'include' => 'pms.php',
	'function' => 'do_get_pms',
    ),

    /*
     * get_pm()
     *
     * input:
     *
     * pmid
     *
     * output:
     *
     * id
     * username
     * userid
     * title
     * message
     * quotable
     * images
     * image_thumbs
     * pm_timestamp
     * avatarurl
     */
    'get_pm' => array(
	'include' => 'pms.php',
	'function' => 'do_get_pm',
    ),

    /*
     * send_pm()
     *
     * input:
     *
     * recipients
     * title
     * message
     *
     * output:
     *
     * success
     */
    'send_pm' => array(
	'include' => 'pms.php',
	'function' => 'do_send_pm',
    ),

    /*
     * delete_pm()
     *
     * input:
     *
     * pm
     *
     * output:
     *
     * success
     */
    'delete_pm' => array(
	'include' => 'pms.php',
	'function' => 'do_delete_pm',
    ),

    /*
     * get_thread()
     *
     * input:
     *
     * threadid
     * page
     * perpage
     * smilies
     *
     * output:
     *
     * posts => array(
     *   post_id
     *   thread_id
     *   username
     *   userid
     *   title
     *   text
     *   post_timestamp
     *   images
     *   image_thumbs
     *   quotable
     *   avatarurl
     * )
     * total_posts
     * page
     * perpage
     * canpost
     */
    'get_thread' => array(
	'include' => 'get_thread.php',
	'function' => 'do_get_thread',
    ),

    /*
     * search()
     *
     * input:
     *
     * text
     * forumid
     * page
     * perpage
     *
     * output:
     *
     * threads => array(
     *   same_as_show_new_posts
     * )
     * total_threads
     */
    'search' => array(
	'include' => 'search_forum.php',
	'function' => 'do_search',
    ),

    /*
     * search_finduser()
     *
     * input:
     *
     * userid
     * starteronly
     *
     * output:
     * thread info
     */
    'search_finduser' => array(
	'include' => 'search_forum.php',
	'function' => 'do_search_finduser',
    ),

    'search_searchid' => array(
	'include' => 'search_forum.php',
	'function' => 'do_search_searchid',
    ),

    /*
     * get_announcement()
     *
     * input:
     *
     * forumid
     * smilies
     *
     * output:
     *
     * posts => array(
     *   username
     *   userid
     *   title
     *   text
     *   post_timestamp
     *   images
     *   image_thumbs
     *   avatarurl
     * )
     * total_posts
     */
    'get_announcement' => array(
	'include' => 'announcement.php',
	'function' => 'do_get_announcement',
    ),

    /*
     * post_message()
     *
     * input:
     *
     * forumid
     * subject
     * message
     * poststarttime
     * sig
     *
     * output:
     *
     * success
     */
    'post_message' => array(
	'include' => 'post.php',
	'function' => 'do_post_message',
    ),

    /*
     * post_reply()
     *
     * input:
     *
     * postid OR threadid
     * message
     * poststarttime
     * sig
     *
     * output:
     *
     * success
     */
    'post_reply' => array(
	'include' => 'post.php',
	'function' => 'do_post_reply',
    ),

    /*
     * post_edit()
     *
     * input:
     *
     * postid
     * message
     * poststarttime
     *
     * output:
     *
     * success
     */
    'post_edit' => array(
	'include' => 'post.php',
	'function' => 'do_post_edit',
    ),

    /*
     * mark_read()
     *
     * input:
     *
     * forumid (or none if marking all read)
     *
     * output:
     *
     * success
     */
    'mark_read' => array(
	'include' => 'misc.php',
	'function' => 'do_mark_read',
    ),

    /*
     * upload_attachment()
     *
     * input:
     *
     * attachment
     * forumid OR threadid
     * poststarttime
     *
     * output:
     *
     * attachmentid
     */
    'upload_attachment' => array(
	'include' => 'attach.php',
	'function' => 'do_upload_attachment',
    ),

    /*
     * delete_attachment()
     *
     * input:
     *
     * attachmentid
     * poststarttime
     *
     * output:
     *
     * success
     */
    'delete_attachment' => array(
	'include' => 'attach.php',
	'function' => 'do_delete_attachment',
    ),

    /*
     * get_profile()
     *
     * input:
     *
     * none
     *
     * output:
     *
     * posts
     * joindate
     * avatarurl
     */
    'get_profile' => array(
	'include' => 'profile.php',
	'function' => 'do_get_profile',
    ),

    /*
     * get_new_updates()
     *
     * input:
     *
     * username
     * password
     * fr_username (optional)
     *
     * output:
     *
     * updates
     */
    'get_new_updates' => array(
	'include' => 'misc.php',
	'function' => 'do_get_new_updates',
    ),

    /*
     * remove_fr_user()
     *
     * input:
     *
     * fr_username
     */
    'remove_fr_user' => array(
	'include' => 'misc.php',
	'function' => 'do_remove_fr_user',
    ),

    /*
     * get_subscriptions()
     *
     * input:
     *
     * perpage
     * pagenumber
     *
     * output:
     */
    'get_subscriptions' => array(
	'include' => 'subscriptions.php',
	'function' => 'do_get_subscriptions',
    ),

    /*
     * unsubscribe_thread()
     *
     * input:
     *
     * threadid
     *
     * output:
     *
     * success
     */
    'unsubscribe_thread' => array(
	'include' => 'subscriptions.php',
	'function' => 'do_unsubscribe_thread',
    ),

    /*
     * subscribe_thread()
     *
     * input:
     *
     * threadid
     *
     * output:
     *
     * success
     */
    'subscribe_thread' => array(
	'include' => 'subscriptions.php',
	'function' => 'do_subscribe_thread',
    ),

    /*
     * stats()
     *
     * output:
     *
     * online_members
     * online_guests
     * members
     * threads
     * posts
     * top_poster
     * newest_member
     *
     * success
     */
    'stats' => array(
	'include' => 'misc.php',
	'function' => 'do_stats',
    ),

    /*
     * online()
     *
     * output:
     *
     * online_users
     *   userid
     *   username
     *   avatarurl
     * num_guests
     *
     * success
     */
    'online' => array(
	'include' => 'online.php',
	'function' => 'do_online',
    ),

    /*
     * moderation()
     *
     * input:
     *
     * do
     * threadid
     *
     * output:
     *
     * success
     */
    'moderation' => array(
	'include' => 'moderation.php',
	'function' => 'do_moderation',
    ),

    /*
     * get_poll
     *
     * input:
     *
     * threadid
     *
     * output:
     *
     * success
     */
    'get_poll' => array(
	'include' => 'get_thread.php',
	'function' => 'do_get_poll',
    ),

    /*
     * vote_poll
     *
     * input:
     *
     * threadid
     * options
     *
     * output:
     *
     * success
     */
    'vote_poll' => array(
	'include' => 'get_thread.php',
	'function' => 'do_vote_poll',
    ),

    /*
     * get_spam_data
     *
     * input:
     *
     * threadid
     * postids
     *
     * output:
     *
     * success
     */
    'get_spam_data' => array(
	'include' => 'moderation.php',
	'function' => 'do_get_spam_data',
    ),

    /*
     * get_ban_data
     *
     * input:
     *
     * output:
     *
     * success
     */
    'get_ban_data' => array(
	'include' => 'moderation.php',
	'function' => 'do_get_ban_data',
    ),

    /*
     * ban_user
     *
     * input:
     *
     * userid
     * reason
     * period
     *
     * output:
     *
     * success
     */
    'ban_user' => array(
	'include' => 'moderation.php',
	'function' => 'do_ban_user',
    ),

    /*
     * get_post
     *
     * input:
     *
     * type (optional)
     * postid
     *
     * output:
     *
     * success
     */
    'get_post' => array(
	'include' => 'get_thread.php',
	'function' => 'do_get_post',
    ),

    /*
     * report
     *
     * input:
     *
     * postid
     * reason
     *
     * output:
     *
     * success
     */
    'report' => array(
	'include' => 'misc.php',
	'function' => 'do_report',
    ),

    /*
     * register
     *
     * input:
     *
     * output:
     *
     */
    'register' => array(
	'include' => 'login.php',
	'function' => 'do_register',
    ),

    /*
     * get_forum_data
     *
     * input:
     *
     * forumids
     *
     * output:
     *
     */
    'get_forum_data' => array(
        'include' => 'get_forum.php',
        'function' => 'do_get_forum_data',
    ),

    /*
     * upload_avatar
     *
     * input:
     *
     * avatar
     */
    'upload_avatar' => array(
        'include' => 'profile.php',
        'function' => 'do_upload_avatar',
    ),

    /*
     * delete_post
     *
     * input:
     *
     * postid
     * threadid
     * reason
     */
    'delete_post' => array(
        'include' => 'moderation.php',
        'function' => 'do_delete_post',
    ),
);

?>
