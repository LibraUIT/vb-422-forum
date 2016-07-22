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

$methods += array(
    /*
     * get_albums
     *
     * input:
     *
     * userid
     */
    'get_albums' => array(
        'include' => 'album.php',
        'function' => 'do_get_albums',
    ),
    
    /*
     * get_photos
     *
     * input:
     *
     * userid
     * albumid
     */
    'get_photos' => array(
        'include' => 'album.php',
        'function' => 'do_get_photos',
    ),

    /*
     * create_album
     *
     * input:
     *
     * title
     */
    'create_album' => array(
        'include' => 'album.php',
        'function' => 'do_create_album',
    ),
    
    /*
     * upload_photo
     *
     * input:
     *
     * albumid
     * upload
     */
    'upload_photo' => array(
        'include' => 'album.php',
        'function' => 'do_upload_photo',
    ),

    /*
     * like
     *
     * input:
     *
     * postid
     *
     * output:
     *
     * success
     */
    'like' => array(
	'include' => 'misc.php',
	'function' => 'do_like',
    ),
);

?>
