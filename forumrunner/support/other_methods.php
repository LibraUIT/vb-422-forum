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

$methods = array_merge($methods, array(
    /*
     * get_cms_section
     *
     * input:
     *
     * sectionid
     *
     * output:
     *
     * success
     */
    'get_cms_section' => array(
	'include' => 'cms.php',
	'function' => 'do_get_cms_section',
    ),
    /*
     * get_cms_sections
     *
     * input:
     *
     * sectionid
     *
     * output:
     *
     * success
     */
    'get_cms_sections' => array(
	'include' => 'cms.php',
	'function' => 'do_get_cms_sections',
    ),
    /*
     * get_cms_article
     *
     * input:
     *
     * articleid
     *
     * output:
     *
     * success
     */
    'get_cms_article' => array(
	'include' => 'cms.php',
	'function' => 'do_get_cms_article',
    ),
));

?>
