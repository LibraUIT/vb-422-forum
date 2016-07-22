<?php
/**
 * PostRelease vBulletin Plugin
 *
 * @author Postrelease
 * @version 4.2.0
 * @copyright © PostRelease, Inc.
 */

if ($vbulletin->options['postrelease_enable'])
{
    $cache[] = 'postrelease_vb4';
    $cache[] = 'postrelease_vb4_postbits';
    $cache[] = 'postrelease_vb4_postbits_legacy';
    $cache[] = 'postrelease_vb4_postbits_mobile';
    $cache[] = 'postrelease_vb4_mobile';
}
?>