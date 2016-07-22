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

define('MATHTEX_URL', '');

require_once(MCWD . '/support/stringparser_bbcode.class.php');
require_once(MCWD . '/support/colors.php');

define('STRING',	'STRING');
define('INTEGER',	'INTEGER');
define('TOGGLE',	'TOGGLE');
define('BOOLEAN',	'BOOLEAN');
define('SPECIAL',	'SPECIAL');
define('DATETIME',	'DATETIME');
define('FLOAT',		'FLOAT');

define('FR_PREVIEW_LEN',    300);
define('FR_MIN_PERPAGE',    10);
define('FR_MAX_PERPAGE',    40);

define('FR_LAST_POST',      7571719);

define('MYSQL_TIME_FORMAT',	'Y-m-d H:i');
define('TWELVEHDATE',		'Y-m-d h:i A');
define('PRETTY_TIME',		'M d, Y h:i A');

define('RV_BAD_PASSWORD',		50);
define('RV_NOT_LOGGED_IN',		51);
define('RV_POST_ERROR',			52);
define('RV_UPLOAD_ERROR',		53);
define('RV_NEED_FORUM_PASSWORD',	54);
define('RV_NO_PM_ACCESS',		55);

define('ERR_NO_PERMISSION',		'Permission denied.');
define('ERR_INVALID_THREAD',		'Invalid thread.');
define('ERR_INVALID_ANNOUNCEMENT',	'Invalid announcement.');
define('ERR_INVALID_FORUM',		'Invalid forum.');
define('ERR_INVALID_LOGGEDIN',		'You must be logged in.');
define('ERR_ATTACH_NO_DELETE',		'Could not delete attachment.');
define('ERR_NEED_PASSWORD',		'Need forum password.');
define('ERR_INVALID_PM',		'Invalid PM.');
define('ERR_DUPE_THREAD',		'This is a duplicate thread.');
define('ERR_DUPE_POST',			'This is a duplicate post.');
define('ERR_LOGGED_OUT',		'You were logged out.');
define('ERR_INVALID_TOP',		'Invalid post or thread.');
define('ERR_INVALID_SUB',		'Invalid subscription.');
define('ERR_CANNOT_SUB_FORUM_CLOSED',	'Cannot subscribe - forum closed.');
define('ERR_CANNOT_SUB_PASSWORD',	'You must visit this forum in the forum browser and enter the password first.');
define('ERR_INVALID_USER',		'Invalid user.');
define('ERR_ERROR_SEARCH',		'There was an error retrieving this search.');
define('ERR_NONEW',			'There were no new threads or posts found.');

define('FR_ENCODING',			'FR_ENCODING');

define('FR_AD_TOPTHREAD',		1 << 0);
define('FR_AD_THREADLIST',		1 << 1);
define('FR_AD_BOTTOMTHREAD',		1 << 2);

define('MOD_DELETEPOST',		1 << 0);
define('MOD_STICK',			1 << 1);
define('MOD_UNSTICK',			1 << 2);
define('MOD_DELETETHREAD',		1 << 3);
define('MOD_OPEN',			1 << 4);
define('MOD_CLOSE',			1 << 5);
define('MOD_MOVETHREAD',		1 << 6);
define('MOD_SPAM_CONTROLS',		1 << 7);

if (!function_exists('stripos')) {
    function stripos($haystack, $needle, $offset = 0) {
	$foundstring = stristr(substr($haystack, $offset), $needle);
	return $foundstring === false ? false : strlen($haystack) - strlen($foundstring);
    }
}

function
is_vb ()
{
    global $fr_platform;

    return ($fr_platform == 'vb36' || $fr_platform == 'vb37' || $fr_platform
	== 'vb38' || $fr_platform == 'vb40');
}

function
is_phpbb ()
{
    global $fr_platform;

    return ($fr_platform == 'phpbb30');
}

function
is_xen ()
{
    global $fr_platform;

    return ($fr_platform == 'xen10');
}

function
is_mybb ()
{
    global $fr_platform;

    return ($fr_platform == 'mybb16');
}

function
is_ipb ()
{
    global $fr_platform;

    return ($fr_platform == 'ipb3');
}

function
is_vanilla ()
{
    return ($GLOBALS['fr_platform'] == 'van2');
}

function
is_android ()
{
    global $frcl_platform;

    return ($frcl_platform == 'a');
}

function
is_iphone ()
{
    global $frcl_platform;

    return ($frcl_platform == 'ip');
}

function
fr_get_client_version ()
{
    if (preg_match('/ForumRunner (.*?) /', $_SERVER['HTTP_USER_AGENT'], $matches)) {
	return $matches[1];
    }
    return '1.0.0';
}

function
get_val_or_null($array, $key)
{
    if (isset($array[$key])) {
	return stripslashes(trim($array[$key]));
    } else {
	return null;
    }
}

function
fr_debug ($out)
{
    if ($_REQUEST['d']) {
        print "<pre>$out\n</pre>";
    }
}

function
process_input ($args)
{
    $out = array();

    foreach ($args as $key => $val) {
	$getval = get_val_or_null($_REQUEST, $key);
	if ($getval !== null) {
	    switch ($val) {
	    case STRING:
		$out[$key] = $getval;
		break;
	    case INTEGER: $out[$key] = (int)$getval; break;
	    case DATETIME: $out[$key] = strtotime($getval); break;
	    case FLOAT: $out[$key] = (float)$getval; break;
	    case TOGGLE:
		if ($getval == 'on') {
		    $out[$key] = 1;
		} else {
		    $out[$key] = 0;
		}
		break;
	    case BOOLEAN:
		if ($getval == 'true' || $getval == '1') {
		    $out[$key] = true;
		} else {
		    $out[$key] = false;
		}
		break;
	    }
	}
    }

    return $out;
}

function
json_error ($err, $code=null)
{
    global $test_mode;

    $out = array(
	'success' => false,
	'message' => $err,
    );
    if ($code != null) {
	$out['code'] = $code;
    }
    $json = new Services_JSON();
    print $json->encode($out);
    exit;
}

function
get_local_charset ()
{
    global $fr_platform;

    $charset = '';

    if ($fr_platform == 'vb40') {
	require_once('./includes/class_bootstrap.php');
	$charset = vB_Template_Runtime::fetchStyleVar('charset');
    } else if ($fr_platform == 'vb38' || $fr_platform == 'vb37' || $fr_platform == 'vb36') {
	global $stylevar;
	$charset = $stylevar['charset'];
    } else if (is_mybb()) {
        global $lang;
        $charset = $lang->settings['charset'];
    } else if (is_ipb()) {
        $charset = IPS_DOC_CHAR_SET;
    } else if (is_vanilla()) {
        $charset = 'utf-8';
    }

    if ($charset == '') {
	$charset = 'ISO-8859-1';
    }

    return $charset;
}

function
frchop ($string, $length)
{
    $length = intval($length);
    if ($length <= 0)
    {
	return $string;
    }

    $pretruncate = 13 * $length;
    $string = substr($string, 0, $pretruncate);

    if (preg_match_all('/&(#[0-9]+|lt|gt|quot|amp);/', $string, $matches, PREG_OFFSET_CAPTURE)) {
	foreach ($matches[0] AS $match) {
	    $offset = $match[1];
	    if ($offset < $length) {
		$length += strlen($match[0])  - 1;
	    } else {
		break;
	    }
	}
    }

    if (function_exists('mb_substr') && ($substr = @mb_substr($string, 0, $length, get_local_charset())) != '') {
	return $substr;
    } else if (function_exists('iconv') && ($substr = @iconv($string, 0, $length, get_local_charset())) != '') {
	return $substr;
    } else {
	return substr($string, 0, $length);
    }
}

function
date_trunc ($date)
{
    // Although we want to use the configured date function for the
    // board, we lack the screen real-estate to display full months.
    return str_replace(array('January', 'February', 'March', 'April', 'May', 'June', 'July',
			      'August', 'September', 'October', 'November', 'December'),
			array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug',
			      'Sep', 'Oct', 'Nov', 'Dec'), $date);
}

function
escape_non_numerical_entities_helper ($matches)
{
    global $charset;

    return html_entity_decode($matches[0], ENT_QUOTES, $charset);
}

function
escape_non_numerical_entities ($str, $encoding)
{
    global $charset;

    $charset = $encoding;

    return preg_replace_callback('/(&\w+;)/U', 'escape_non_numerical_entities_helper', $str);
}

function
fr_strip_quotes ($text)
{
    $lowertext = strtolower($text);

    $start_pos = array();
    $curpos = 0;
    do {
        $pos = strpos($lowertext, '[quote', $curpos);
        if ($pos !== false) {
            $start_pos["$pos"] = 'start';
        }

        $curpos = $pos + 6;
    } while ($pos !== false);

    if (sizeof($start_pos) == 0) {
        return $text;
    }

    $end_pos = array();
    $curpos = 0;
    do {
        $pos = strpos($lowertext, '[/quote]', $curpos);
        if ($pos !== false) {
            $end_pos["$pos"] = 'end';
            $curpos = $pos + 8;
        }
    } while ($pos !== false);

    if (sizeof($end_pos) == 0) {
        return $text;
    }

    $pos_list = $start_pos + $end_pos;
    ksort($pos_list);

    do {
        $stack = array();
        $newtext = '';
        $substr_pos = 0;
        foreach ($pos_list AS $pos => $type) {
            $stacksize = sizeof($stack);
            if ($type == 'start') {
                if ($stacksize == 0) {
                    $newtext .= substr($text, $substr_pos, $pos - $substr_pos);
                }
                array_push($stack, $pos);
            } else {
                if ($stacksize) {
                    array_pop($stack);
                    $substr_pos = $pos + 8;
                }
            }
        }

        $newtext .= substr($text, $substr_pos);

        if ($stack) {
            foreach ($stack AS $pos) {
                unset($pos_list["$pos"]);
            }
        }
    } while ($stack);

    return $newtext;
}

function
fr_to_utf8 ($in, $charset = false)
{
    if ('' === $in OR false === $in OR is_null($in)) {
	return $in;
    }

    // Fallback to UTF-8
    if (!$charset) {
	$charset = 'UTF-8';
    }

    if (is_ipb()) {
        return IPSText::convertCharsets($in, $charset, 'utf-8');
    }

    // Try iconv
    if (function_exists('iconv')) {
	$out = @iconv($charset, 'UTF-8//TRANSLIT', $in);
	if ($out !== false) {
	    return $out;
	}
    }

    // Try mbstring
    if (function_exists('mb_convert_encoding')) {
	return @mb_convert_encoding($in, 'UTF-8', $charset);
    }

    // Strip non valid UTF-8
    // TODO: Do we really want to do this?
    $utf8 = '#([\x09\x0A\x0D\x20-\x7E]' .			# ASCII
	'|[\xC2-\xDF][\x80-\xBF]' .				# non-overlong 2-byte
	'|\xE0[\xA0-\xBF][\x80-\xBF]' .			# excluding overlongs
	'|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}' .	# straight 3-byte
	'|\xED[\x80-\x9F][\x80-\xBF]' .			# excluding surrogates
	'|\xF0[\x90-\xBF][\x80-\xBF]{2}' .		# planes 1-3
	'|[\xF1-\xF3][\x80-\xBF]{3}' .			# planes 4-15
	'|\xF4[\x80-\x8F][\x80-\xBF]{2})#S';	# plane 16

    $out = '';
    $matches = array();
    while (preg_match($utf8, $in, $matches)) {
	$out .= $matches[0];
	$in = substr($in, strlen($matches[0]));
    }

    return $out;
}

function
prepare_utf8_string ($str, $entities=true)
{
    if (!is_phpbb() && !is_xen()) {
	$str = fr_to_utf8($str, get_local_charset());
    }
    $str = escape_non_numerical_entities($str, 'UTF-8');
    $str = html_entity_decode_utf8($str, $entities);
    return $str;
}

function
prepare_remote_utf8_string ($str)
{
    $charset = get_local_charset();

    if (strtolower($charset) == 'utf-8') {
	return $str;
    }

    if (is_ipb()) {
        return IPSText::convertCharsets($str, 'utf-8', $charset);
    }

    $str = html_entity_decode_utf8($str, true);
    $converted = false;

    if (function_exists('mb_convert_encoding') && !$converted) {
	$out = @mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8');
	$out = @mb_convert_encoding($out, $charset, 'UTF-8');
	if (mb_strlen(trim($out)) > 0) {
	    $converted = true;
	}
    }

    if (function_exists('iconv') && !$converted) {
	$out = @iconv('UTF-8', $charset . '//TRANSLIT', $str);
	if ($out !== false) {
	    $converted = true;
	}
    }

    if (!$converted) {
	$out = $str;
    }

    $out = escape_non_numerical_entities($out, $charset);

    return $out;
}

function
preview_chop ($str, $len = FR_PREVIEW_LEN)
{
    if (strlen($str) > $len) {
	$preview = frchop($str, $len) . '...';
    } else {
	$preview = $str;
    }
    return str_replace(array("\r", "\n"), array('', ' '), $preview);
}

function
make_preview ($str, $strip_html = false)
{
    $str = preg_replace('/\s+/', ' ', $str);
    $str = fr_strip_quotes($str);
    $str = remove_bbcode($str, false, true, true);
    $str = preg_replace('#<br\s?/>#is', ' ', $str);

    if ($strip_html) {
        $str = strip_tags($str);
        $str = str_replace('&nbsp;', ' ', $str);
    }

    $msg = preg_replace('/[\n\r\t]+/', ' ', $msg);

    return preview_chop(trim($str));
}

function
remove_bbcode ($str, $quote = false, $image = false, $url = false)
{
    // List of valid BBCODE in vBulletin/phpBB

    $bbcode = array('B', 'I', 'U', 'COLOR', 'SIZE', 'FONT', 'HIGHLIGHT',
	'LEFT', 'RIGHT', 'CENTER', 'INDENT', 'EMAIL', 'THREAD',
	'POS', 'LIST', 'VIDEO', 'CODE', 'PHP', 'HTML',
	'NOPARSE', 'ATTACH', 'BUG', 'NOTE', 'SCREENCAST', 'VAR', 'WARNING',
	'STRIKE', 'PRBREAK', 'SIGPIC', 'BOX');
    if ($quote) {
	$bbcode[] = 'QUOTE';
    }
    if ($image) {
	$str = preg_replace('/\[img\](.*?)\[\/img\]/si', '', $str);
    }
    if ($url) {
        $bbcode[] = 'URL';
    }

    foreach ($bbcode as $code) {
	$str = preg_replace("#\[/?$code" . ($code != 'B' &&
	    $code != 'I' && $code != 'U' ? '.*?' : '') . "\]#is", '', $str);
    }

    $str = preg_replace('/\[spoiler\](.*?)\[\/spoiler\]/si', '* SPOILER *', $str);

    return $str;
}

function reverse_htmlentities ($mixed)
{
    $htmltable = get_html_translation_table(HTML_ENTITIES);
    foreach ($htmltable as $key => $value)
    {
	$mixed = preg_replace('/' . addslashes($value) . '/', $key, $mixed);
    }
    return $mixed;
}

function
fr_get_host ($full_bb_url=false)
{
    $host = '';
    if (is_phpbb()) {
	$host = fr_get_phpbb_bburl(!$full_bb_url);
    } else if (is_vb()) {
        global $vbulletin;
	if ($full_bb_url) {
	    $host = $vbulletin->options['bburl'];
	} else {
	    preg_match('@^(?:(https?)://)?([^/]+)@i', $vbulletin->options['bburl'], $matches);
	    $host = $matches[1] . '://' . $matches[2];
	}
    } else if (is_xen()) {
	$host = fr_get_xenforo_bburl();
    } else if (is_mybb()) {
        global $mybb;
        $host = $mybb->settings['bburl'];
    } else if (is_ipb()) {
        global $settings;
        $host = $settings['_original_base_url'];
    } else if (is_vanilla()) {
        // No good way to do this.  Ugh.
        $host = trim(Url('/', true), '/');
        $path = trim($_SERVER['SCRIPT_NAME'], '/forumrunner/request.php');
        $host .= "/$path";
    }

    return $host;
}

function
fr_fix_url ($url)
{
    if ($url == '') {
	return;
    }

    if (preg_match('/https?:/', $url)) {
	if (preg_match("#forumdisplay.php.*?(?:f=|forumid=)(\d+)#", $url, $matches)) {
	    return (int)$matches[1];
	}
	return $url;
    }

    $host = fr_get_host($url[0] != '/');

    if ($host[strlen($host)-1] != '/' && $url[0] != '/') {
	$host .= '/';
    }

    return $host . $url;
}

function
normalize_url ($url)
{
    if (strpos($url, '../') === false && strpos($url, './') === false) {
        return $url;
    }

    if (!preg_match('#(https?)://(.*?)/(.*)#', $url, $matches)) {
        return $url;
    }

    $type = $matches[1];
    $host = $matches[2];
    $parts = preg_split(',/,', $matches[3]);

    $out = array();
    for ($i = 0; $i < count($parts); $i++) {
        if ($parts[$i] != '..') {
            if ($parts[$i] != '.') {
                $out[] = $parts[$i];
            }
        } else {
            array_pop($out);
        }
    }

    return $type . '://' . $host . '/' . join('/', $out);
}

function
process_avatarurl ($url)
{
    global $vbulletin;

    if ($url == '') {
	return;
    }

    if (stripos($url, 'http:') !== false) {
	return reverse_htmlentities(normalize_url($url));
    } else {
	if (is_vb()) {
	    if (strpos($url, '/') === 0) {
		$host = parse_url($vbulletin->options['bburl']);
		return normalize_url($host['scheme'] . '://' . $host['host'] . reverse_htmlentities($url));
	    } else {
		return normalize_url($vbulletin->options['bburl'] . '/' . reverse_htmlentities($url));
	    }
	} else if (is_phpbb()) {
	    return normalize_url(fr_get_phpbb_bburl() . reverse_htmlentities($url));
	} else if (is_xen()) {
	    return normalize_url(fr_get_xenforo_bburl() . '/' . reverse_htmlentities($url));
	}
    }
}

function
fr_get_forum_icon ($forumid, $shownew, $showlink=false)
{
    $basedir = MCWD . '/icons/';

    $new = $old = $link = null;

    if (@file_exists($basedir . 'forum-default-new.png')) {
	$new = 'forum-default-new.png';
	if (@file_exists($basedir . 'forum-default-old.png')) {
	    $old = 'forum-default-old.png';
	} else {
	    $old = $new;
	}
    }
    if (@file_exists($basedir . 'forum-default-link.png')) {
	$link = 'forum-default-link.png';
    }

    if (@file_exists($basedir . 'forum-' . $forumid . '-new.png')) {
	$new = 'forum-' . $forumid . '-new.png';
	if (@file_exists($basedir . 'forum-' . $forumid . '-old.png')) {
	    $old = 'forum-' . $forumid . '-old.png';
	} else {
	    $old = $new;
	}
    }
    if (@file_exists($basedir . 'forum-' . $forumid . '-link.png')) {
	$link = 'forum-' . $forumid . '-link.png';
    }

    return ($showlink ? $link : ($shownew ? $new : $old));
}

function
parse_post_callback ($matches)
{
    global $vbulletin;

    if (stripos($matches[1], 'http:') !== false) {
	return $matches[0];
    } else {
	return 'img src="' . $vbulletin->options['bburl'] . '/' . $matches[1] . '"';
    }
}

// Decode &xffff;  &12345;  %uffff  as well as normal HTML entities into UTF-8 characters
function
html_entity_decode_utf8 ($string, $htmltrans = false)
{
    static $trans_tbl;

    if (!isset($trans_tbl)) {
        $trans_tbl = array();
	foreach (get_html_translation_table(HTML_ENTITIES) as $val => $key) {
	    $trans_tbl[$key] = utf8_encode($val);
	}
    }

    $string = preg_replace(
	array(
	    '~&#x([0-9a-f]+);~ei',
	    '~&#([0-9]+);~e',
	    '~%u([0-9a-f]{4})~ei',
	),
	array(
	    'code2utf(hexdec("\\1"))',
	    'code2utf(\\1)',
	    'code2utf(hexdec("\\1"))',
	), $string
    );

    if ($htmltrans) {
	$string = strtr($string, $trans_tbl);
    }

    return $string;
}

function
code2utf ($num)
{
    if ($num < 128) return chr($num);
    if ($num < 2048) return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
    if ($num < 65536) return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
    if ($num < 2097152) return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
    return '';
}

function
handle_quotes ($action, $attributes, $content, $params, $node_object)
{
    global $nuke_quotes;

    if ($action == 'validate') {
        return true;
    } else {
	if (trim($content) == '') {
	    return '';
	}
	$who = '';
	$depth = 0;
	$node =& $node_object;
	while ($node->type() != STRINGPARSER_NODE_ROOT) {
	    $depth++;
	    $node =& $node->_parent;
	}
	if ($depth == 1) {
	    $class = 'quotedRoot';
	} else {
	    $class = 'quoted' . ($depth - 1) % 3;
	}
	if (isset($attributes['default'])) {
	    $attributes['default'] = str_replace(
		array('"', '&quot;'),
		array('', ''),
		html_entity_decode($attributes['default'])
	    );
            $tmp = preg_split('/;/', $attributes['default']);
	    if ($tmp[0]) {
		$who = str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $tmp[0]);
	    }
	}
	if ($nuke_quotes) {
	    return '';
	}
	$content = preg_replace("#\[IMG\](.*?)\[/IMG\]#is", '&lt;image quoted&gt;', $content);
	if ($who) {
	    return "<div class=\"$class\">$who said:<br/><br/>" . trim($content) . "</div>";
	} else {
	    return "<div class=\"$class\">" . trim($content) . "<br/></div>";
	}
    }
}

function
handle_url ($action, $attributes, $content, $params, $node_object)
{
    global $nuke_quotes;

    if (!isset($attributes['default'])) {
	$url = $content;
	$text = $content;
    } else {
	$url = $attributes['default'];
	$text = $content;
    }
    if ($action == 'validate') {
	if (substr ($url, 0, 5) == 'data:' || substr ($url, 0, 5) == 'file:'
	    || substr ($url, 0, 11) == 'javascript:' || substr ($url, 0, 4) == 'jar:') {
		return false;
	    }
	return true;
    }
    $url = trim(str_replace("&quot;", "", $url));

    if ($nuke_quotes) {
	if ($text != '') {
	    return '[URL="' . $url . '"]' . $text . '[/URL]';
	} else {
	    return '[URL]' . $url . '[/URL]';
	}
    }

    if (is_vb()) {
	global $vbulletin;

	$bburl = trim($vbulletin->options['bburl'], '/');

        $url_parts = @parse_url($url);
        $bburl_parts = @parse_url($bburl);

        $bburl_noscheme = $bburl_parts['host'] . $url_parts['path'];
        $url_noscheme = $url_parts['host'] . $url_parts['path'];

	if ($url_parts !== false) {
	    $ours = false;
	    if (!$url_parts['host']) {
		$ours = true;
            } else if (strpos($url_noscheme, $bburl_noscheme) !== false) {
                $ours = true;
            }
            $new_url = '';
            $threadid = $postid = $forumid = -1;
            if ($ours) {
                // First, check for override
                if (function_exists('fr_vbseo_parseurl')) {
                    list($new_url, $forumid, $threadid, $postid) = fr_vbseo_parseurl($url);
                } else if (strpos($url, 'showthread.php') !== false || strpos($url, 'forumdisplay.php') !== false) {
                    // Now, check for standard vBulletin
                    $args = preg_split('/&/', $url_parts['query']);
                    if (is_array($args)) {
                        foreach ($args as $arg) {
                            if (preg_match('/(t|threadid|p|postid|f|forumid)=(\d+)/', $arg, $matches)) {
                                if ($matches[1] == 't' || $matches[1] == 'threadid') {
                                    $new_url = 'tt://threadTableView/';
                                    $threadid = $matches[2];
                                    break;
                                } else if ($matches[1] == 'p' || $matches[1] == 'postid') {
                                    $new_url = 'tt://threadTableView/';
                                    $postid = $matches[2];
                                } else if ($matches[1] == 'f' || $matches[1] == 'forumid') {
                                    $new_url = 'tt://forumTableView/';
                                    $forumid = $matches[2];
                                }
                            }
                        }
                    }
                }

                if ($threadid > -1 || $postid > -1) {
                    $postjoin = $where = '';
                    if ($postid != -1) {
                        $postjoin = "
                            LEFT JOIN " . TABLE_PREFIX . "post AS post ON post.threadid = thread.threadid
                        ";
                        $where = "
                            WHERE post.postid = $postid
                        ";
                    } else {
                        $where = "
                            WHERE thread.threadid = $threadid
                        ";
                    }

                    $thread = $vbulletin->db->query_first_slave("
                        SELECT thread.title, thread.threadid
                        FROM " . TABLE_PREFIX . "thread AS thread
                        $postjoin
                        $where
                    ");

                    if ($text == $url) {
                        if ($thread['title']) {
                            $text = prepare_utf8_string(htmlentities($thread['title']));
                        } else {
                            $text = 'Thread Link';
                        }
                    }

                    $new_url .= $thread['threadid'];

                    if ($postid > -1) {
                        $new_url .= '?gotopost=' . $postid;
                    }
                } else if ($forumid > -1) {
                    $forum = fetch_foruminfo($forumid);
                    if ($text == $url) {
                        if ($forum['title']) {
                            $text = prepare_utf8_string(htmlentities($forum['title']));
                        } else {
                            $text = 'Forum Link';
                        }
                    }
                    $new_url .= $forum['forumid'];
                }

                if ($new_url != '') {
                    $url = $new_url;
                }
	    }
	}
    } else if (is_xen()) {
        // Parse XenForo SEO URLs
        $bburl = trim(fr_get_xenforo_bburl(), '/');

        $url_parts = @parse_url($url);
        $bburl_parts = @parse_url($bburl);

        $bburl_noscheme = $bburl_parts['host'] . $url_parts['path'];
        $url_noscheme = $url_parts['host'] . $url_parts['path'];

	if ($url_parts !== false) {
	    $ours = false;
	    if (!$url_parts['host']) {
		$ours = true;
            } else if (strpos($url_noscheme, $bburl_noscheme) !== false) {
		$ours = true;
            }
            $new_url = '';
            $threadid = $forumid = -1;
	    if ($ours) {
		// First, check for standard XenForo
		if (strpos($url, 'threads/') !== false ||
		    strpos($url, 'forums/') !== false)
                {
                    if (preg_match('#threads/.*?\.(\d+)/#i', $url, $matches)) {
                        $threadid = $matches[1];
                        $new_url = 'tt://threadTableView/' . $threadid;
                    } else if (preg_match('#forums/.*?\.(\d+)/#i', $url, $matches)) {
                        $forumid = $matches[1];
                        $new_url = 'tt://forumTableView/' . $forumid;
                    }
                }

                $db = XenForo_Application::get('db');
                if ($threadid > -1) {
                    $thread = $db->fetchRow("
                        SELECT thread.title
                        FROM xf_thread AS thread
                        WHERE thread.thread_id = ?
                    ", $threadid);
                    if ($url == $text) {
                        if ($thread['title']) {
                            $text = prepare_utf8_string($thread['title']);
                        } else {
                            $text = 'Thread Link';
                        }
                    }
                } else if ($forumid > -1) {
                    $forum = $db->fetchRow("
                        SELECT node.title
                        FROM xf_node AS node
                        WHERE node.node_id = ?
                    ", $forumid);
                    if ($url == $text) {
                        if ($forum['title']) {
                            $text = prepare_utf8_string($forum['title']);
                        } else {
                            $text = 'Forum Link';
                        }
                    }
                }

                if ($new_url != '') {
                    $url = $new_url;
                }
            }
        }
    }

    $text = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $text);

    return '<a href="' . $url . '">' . $text . '</a>';
}

function
handle_image ($action, $attributes, $content, $params, $node_object)
{
    global $nuke_quotes, $images;

    if ($action == 'validate') {
	return true;
    }
    if (!isset($attributes['default'])) {
	$image = trim($content);
	$text = '';
    } else {
	$image = trim($attributes['default']);
	$text = $content;
    }

    if ($nuke_quotes) {
	if ($text != '') {
	    return '[IMG="' . $image . '"]' . $text . '[/IMG]';
	} else {
	    return '[IMG]' . $image . '[/IMG]';
	}
    }

    $images[] = $image;

    return '<img src="' . $image . '"/>';
}

function
handle_spoiler ($action, $attributes, $content, $params, $node_object)
{
    global $nuke_quotes;

    if ($action == 'validate') {
	return true;
    }

    if ($nuke_quotes) {
	if ($content != '') {
	    return '[SPOILER]' . $content . '[/SPOILER]';
	}
    }

    return '<spoiler> ' . $content . '</spoiler>';
}

function
handle_attach ($action, $attributes, $content, $params, $node_object)
{
    global $vbulletin, $fr_platform, $db, $contenttype, $images, $nuke_quotes;

    if ($action == 'validate') {
	return true;
    }

    if (!is_numeric($content)) {
	return $content;
    }
    $attachmentid = intval($content);

    if ($fr_platform == 'vb40') {
	$_REQUEST['attachmentid'] = $attachmentid;
        if (!($attach =& vB_Attachment_Display_Single_Library::fetch_library($vbulletin, $contenttype, true, $attachmentid))) {
            return '';
	}

	$result = $attach->verify_attachment();
        if ($result !== true) {
            return '';
	}

	$url = $vbulletin->options['bburl'] . '/attachment.php?attachmentid=' . $attachmentid;

	if (!$nuke_quotes) {
	    $images[] = $url;
	}

	return "<img src=\"$url\"/>";
    } else if ($fr_platform == 'vb38' || $fr_platform == 'vb37' || $fr_platform == 'vb36') {
	if (!$attachmentinfo = $db->query_first_slave("
	    SELECT filename, attachment.postid, attachment.userid, attachmentid, attachment.extension,
	    " . ((!empty($vbulletin->GPC['thumb'])
	    ? 'thumbnail_dateline AS dateline, thumbnail_filesize AS filesize,'
	    : 'attachment.dateline, filesize,')) . "
	    attachment.visible, attachmenttype.newwindow, mimetype, thread.forumid, thread.threadid, thread.postuserid,
	    post.visible AS post_visible, thread.visible AS thread_visible
	    $hook_query_fields
	    FROM " . TABLE_PREFIX . "attachment AS attachment
	    LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype ON (attachmenttype.extension = attachment.extension)
	    LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = attachment.postid)
	    LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
	    $hook_query_joins
	    WHERE " . ($vbulletin->GPC['postid'] ? "attachment.postid = " . $vbulletin->GPC['postid'] : "attachmentid = " . $attachmentid) . "
	    $hook_query_where
	    "))
        {
            return '';
	}

	if ($attachmentinfo['postid'] == 0)
	{	// Attachment that is in progress but hasn't been finalized
	    if ($vbulletin->userinfo['userid'] != $attachmentinfo['userid'] AND !can_moderate($attachmentinfo['forumid'], 'caneditposts'))
	    {	// Person viewing did not upload it
                return '';
	    }
	    // else allow user to view the attachment (from the attachment manager for example)
	}
	else
	{
	    $forumperms = fetch_permissions($attachmentinfo['forumid']);

	    $threadinfo = array('threadid' => $attachmentinfo['threadid']); // used for session.inthread
	    $foruminfo = array('forumid' => $attachmentinfo['forumid']); // used for session.inforum

	    # Block attachments belonging to soft deleted posts and threads
	    if (!can_moderate($attachmentinfo['forumid']) AND ($attachmentinfo['post_visible'] == 2 OR $attachmentinfo['thread_visible'] == 2))
	    {
                return '';
	    }

	    # Block attachments belonging to moderated posts and threads
	    if (!can_moderate($attachmentinfo['forumid'], 'canmoderateposts') AND ($attachmentinfo['post_visible'] == 0 OR $attachmentinfo['thread_visible'] == 0))
	    {
                return '';
	    }

	    $viewpermission = (($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment']));
	    $viewthumbpermission = (($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment']) OR ($forumperms & $vbulletin->bf_ugp_forumpermissions['canseethumbnails']));

	    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($attachmentinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0)))
            {
                return '';
	    }
	    else if (($vbulletin->GPC['thumb'] AND !$viewthumbpermission) OR (!$vbulletin->GPC['thumb'] AND !$viewpermission))
            {
                return '';
	    }

	    // check if there is a forum password and if so, ensure the user has it set
	    verify_forum_password($attachmentinfo['forumid'], $vbulletin->forumcache["$attachmentinfo[forumid]"]['password']);

	    if (!$attachmentinfo['visible'] AND !can_moderate($attachmentinfo['forumid'], 'canmoderateattachments') AND $attachmentinfo['userid'] != $vbulletin->userinfo['userid'])
	    {
		print_no_permission();
	    }

	}
	$url = $vbulletin->options['bburl'] . '/attachment.php?attachmentid=' . $attachmentid;

	if (!$nuke_quotes) {
	    $images[] = $url;
            return "<img src=\"$url\"/>";
        } else {
            return '';
        }
    } else {
        return '';
    }
}

function
handle_bbcode_bold ($action, $attributes, $content, $params, $node_object)
{
    global $nuke_quotes;

    if ($action == 'validate') {
	return true;
    }

    if ($nuke_quotes) {
	return '[B]' . $content . '[/B]';
    } else {
	return '<b>' . $content . '</b>';
    }
}

function
handle_bbcode_italic ($action, $attributes, $content, $params, $node_object)
{
    global $nuke_quotes;

    if ($action == 'validate') {
	return true;
    }

    if ($nuke_quotes) {
	return '[I]' . $content . '[/I]';
    } else {
	return '<i>' . $content . '</i>';
    }
}

function
zeropad ($num, $limit)
{
    return (strlen($num) >= $limit) ? $num : zeropad('0' . $num, $limit);
}

function
convert_color ($color)
{
    $out = '';

    // We only accept color in #rgb, #rrggbb, and rgb(r,g,b) formats
    if (preg_match('/#(\[0-9a-f])([0-9a-f])([0-9a-f])/i', $color, $matches)) {
	$out = '#' . $matches[1] . $matches[1] . $matches[2] . $matches[2] . $matches[3] . $matches[3];
    } else if (preg_match('/#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i', $color, $matches)) {
	$out = '#' . $matches[1] . $matches[2] . $matches[3];
    } else if (preg_match('/rgb\((\d+),\s?(\d+),\s?(\d+)\)/', $color, $matches)) {
	$out = '#' . zeropad(dechex($matches[1]), 2) . zeropad(dechex($matches[2]), 2) . zeropad(dechex($matches[3]), 2);
    }
    return $out;
}

function
handle_bbcode_color ($action, $attributes, $content, $params, $node_object)
{
    global $web_colors, $nuke_quotes;

    if ($action == 'validate') {
	return true;
    }

    $attributes['default'] = str_replace("&quot;", "", $attributes['default']);

    if ($nuke_quotes) {
	return '[COLOR="' . $attributes['default'] . '"]' . $content . '[/COLOR]';
    }

    // Figure out if this is a default web color
    $pre = strtolower($attributes['default']);
    $color = '';
    if (isset($web_colors[$pre])) {
	$color = $web_colors[$pre];
    } else {
	$color = convert_color($pre);
    }

    if ($color != '') {
	return "<color color=\"$color\">$content</color>";
    } else {
	return $content;
    }
}

function
fr_handle_youtube ($action, $attributes, $content, $params, $node_object)
{
    global $vbulletin, $fr_platform, $db;

    if ($action == 'validate') {
	return true;
    }

    if (!isset($attributes['default'])) {
	$comment = 'YouTube Link';
	$yt = $content;
    } else {
	$comment = 'YouTube Link: ' . $attributes['default'];
	$yt = $content;
    }

    $comment = str_replace("&quot;", "", $comment);

    if (preg_match('/v=([^<]+)/', $yt, $matches)) {
	$id = $matches[1];
    } else {
	$id = $yt;
    }

    return '<a href="http://www.youtube.com/watch?v=' . $id . '">' . $comment . '</a>';
}

function
fr_handle_tex ($action, $attributes, $content, $params, $node_object)
{
    global $vbulletin, $fr_platform, $db;

    if ($action == 'validate') {
	return true;
    }

    if (MATHTEX_URL == '') {
	return 'MATHTEX_URL not configured.';
    }

    $content = urlencode($content);

    $url = MATHTEX_URL . '?formdata=' . $content;

    $img = @file_get_contents($url);
    $rawimg = imagecreatefromstring($img);

    return '<frimg width="' . imagesx($rawimg) . '" height="' . imagesy($rawimg) . '" src="' . $url . '"/>';
}

function
handle_video ($action, $attributes, $content, $params, $node_object)
{
    global $vbulletin, $fr_platform, $db, $nuke_quotes;

    if ($action == 'validate') {
	return true;
    }

    if (!isset($attributes['default'])) {
	$comment = 'Video Link: ' . $content;
	$url = $content;
    } else {
        if (substr($attributes['default'], 0, 6) == '&quot;') {
            $attributes['default'] = substr($attributes['default'], 6);
        }
        if (substr($attributes['default'], -6) == '&quot;') {
            $attributes['default'] = substr($attributes['default'], 0, -6);
        }

	// Format is type;code
	$split = preg_split('/;/', html_entity_decode_utf8($attributes['default'], true));
	if (count($split) == 2) {
	    switch (strtolower($split[0])) {
	    case 'youtube':
		$type = 'YouTube';
		$url = 'http://www.youtube.com/watch?v=' . $split[1];
		break;
	    case 'hulu':
		$type = 'Hulu';
		$url = $content;
		break;
	    case 'facebook':
		$type = 'Facebook';
		$url = 'http://www.facebook.com/v/' . $split[1];
		break;
	    default:
		$type = 'Video';
		$url = $content;
		break;
	    }
	    $comment = $type . ' Link: ' . $content;
	} else {
	    // Unknown?  Just treat as a full URL.
	    $comment = 'Video Link: ' . $attributes['default'];
	    $url = $attributes['default'];
	}
    }

    if ($nuke_quotes) {
	if ($comment) {
	    return '[URL="' . $url . '"]' . $comment . '[/URL]';
	} else {
	    return '[URL]' . $url . '[/URL]';
	}
    }

    return '<a href="' . $url . '">' . $comment . '</a>';
}

function
handle_xen_media ($action, $attributes, $content, $params, $node_object)
{
    global $vbulletin, $fr_platform, $db, $nuke_quotes;

    if ($action == 'validate') {
	return true;
    }

    if (!isset($attributes['default'])) {
	return $content;
    }

    $media_type = strtolower($attributes['default']);
    $media_type = str_replace('&quot;', '', $media_type);

    switch ($media_type) {
    case 'youtube':
	$type = 'YouTube';
	$url = 'http://www.youtube.com/watch?v=' . $content;
	break;
    case 'facebook':
	$type = 'Facebook';
	$url = 'http://www.facebook.com/v/' . $content;
	break;
    case 'vimeo':
	$type = 'Vimeo';
	$url = 'http://player.vimeo.com/video/' . $content;
	break;
    default:
	return $content;
    }

    $comment = "$type Video";

    if ($nuke_quotes) {
	return '[URL="' . $url . '"]' . $comment . '[/URL]';
    }

    return '<a href="' . $url . '">' . $comment . '</a>';
}

function
parse_post ($text, $allowsmilie=false)
{
    global $nuke_quotes, $fr_platform, $images;

    $images = array();

    if (is_ipb()) {
        // Replace <br.*/> with \n
        $text = preg_replace('#<br.*?/>#is', "\n", $text);
    }
    
    $smilies = false;
    $v = process_input(array('smilies' => BOOLEAN));
    if (isset($v['smilies'])) {
	$smilies = ($v['smilies'] === true);
    }

    // Trim each line
    $lines = preg_split("/\n/", $text);
    for ($i = 0; $i < count($lines); $i++) {
	$lines[$i] = trim($lines[$i]);
    }
    $text = join("\n", $lines);

    $text = prepare_utf8_string($text, false);

    $bbcode = new StringParser_BBCode();
    $bbcode->setGlobalCaseSensitive(false);

    // Handle default BBCode
    $bbcode->addCode('quote', 'callback_replace', 'handle_quotes', array(), 'inline',
	array('listitem', 'block', 'inline', 'link'), array(''));
    $bbcode->addCode('url', 'usecontent?', 'handle_url', array('usecontent_param' => 'default'),
	'link', array('listitem', 'block', 'inline'), array('link'));
    $bbcode->addCode('source', 'usecontent?', 'handle_url', array('usecontent_param' => 'default'),
        'link', array('listitem', 'block', 'inline'), array('link'));
    if (!is_mybb()) {
        // myBB wonky attachment codes are already handled
        $bbcode->addCode('attach', 'callback_replace', 'handle_attach', array(), 'inline',
            array('listitem', 'block', 'inline', 'link'), array(''));
    }
    $bbcode->addCode('attach', 'callback_replace', 'handle_attach', array(), 'inline',
	array('listitem', 'block', 'inline', 'link'), array(''));
    $bbcode->addCode('img', 'callback_replace', 'handle_image', array(), 'inline',
	array('listitem', 'block', 'inline', 'link'), array(''));
    $bbcode->addCode('imgl', 'callback_replace', 'handle_image', array(), 'inline',
	array('listitem', 'block', 'inline', 'link'), array(''));
    $bbcode->addCode('imgr', 'callback_replace', 'handle_image', array(), 'inline',
	array('listitem', 'block', 'inline', 'link'), array(''));
    //$bbcode->addCode('spoiler', 'callback_replace', 'handle_spoiler', array(), 'inline',
    //array('listitem', 'block', 'inline', 'link'), array(''));
    $bbcode->addCode('b', 'callback_replace', 'handle_bbcode_bold', array('usecontent_param' => array('default')),
	'inline', array('listitem', 'block', 'inline', 'link'), array());
    $bbcode->addCode('i', 'callback_replace', 'handle_bbcode_italic', array('usecontent_param' => array('default')),
    	'inline', array('listitem', 'block', 'inline', 'link'), array());
    $bbcode->addCode('color', 'callback_replace', 'handle_bbcode_color', array('usecontent_param' => array('default')),
	'inline', array('listitem', 'block', 'inline', 'link'), array());
    $bbcode->setCodeFlag('color', 'closetag', BBCODE_CLOSETAG_MUSTEXIST);

    // Video Link BBCode
    $bbcode->addCode('yt', 'callback_replace', 'fr_handle_youtube', array(), 'inline',
	array('listitem', 'block', 'inline', 'link'), array(''));
    $bbcode->addCode('youtube', 'callback_replace', 'fr_handle_youtube', array(), 'inline',
	array('listitem', 'block', 'inline', 'link'), array(''));
    $bbcode->addCode('video', 'callback_replace', 'handle_video', array(), 'inline',
	array('listitem', 'block', 'inline', 'link'), array(''));
    $bbcode->addCode('ame', 'callback_replace', 'handle_video', array(), 'inline',
	array('listitem', 'block', 'inline', 'link'), array(''));
    $bbcode->addCode('media', 'callback_replace', is_xen() ? 'handle_xen_media' : 'handle_video', array(), 'inline',
        array('listitem', 'block', 'inline', 'link'), array(''));
    $bbcode->addCode('tex', 'callback_replace', 'fr_handle_tex', array(), 'inline',
	array('listitem', 'block', 'inline', 'link'), array(''));
    if (function_exists('fr_branded_bbcode_handler')) {
        @fr_branded_bbcode_handler($bbcode);
    }

    if (is_mybb()) {
        $bbcode->setMixedAttributeTypes(true);
    }

    $nuked_quotes = $text;
    $text = htmlspecialchars_uni($text);

    $nuke_quotes = true;
    $nuked_quotes = $bbcode->parse($nuked_quotes);
    if (is_ipb()) {
        $nuked_quotes = ipb_handle_attachments($nuked_quotes);
    }
    $nuke_quotes = false;

    $text = $bbcode->parse($text);
    if (is_ipb()) {
        $text = ipb_handle_attachments($text);
    }

    // Snag out images
    preg_match_all('#\[IMG\](.*?)\[/IMG\]#is', $text, $matches);
    $text = preg_replace("#\[IMG\](.*?)\[/IMG\]#is", '', $text);
    $nuked_quotes = preg_replace("#\[IMG\](.*?)\[/IMG\]#is", '', $nuked_quotes);

    if ($smilies) {
	if (is_vb()) {
	    global $vbulletin;

	    $parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());
	    $text = $parser->parse_smilies($text, false);
	    $text = preg_replace_callback('#img src="(.*?)"#is', parse_post_callback, $text);
	}
    }

    $text = preg_replace("#\n\n\n+#", "\n\n", $text);
    $text = preg_replace("#\n#", "<br/>", $text);

    $text = remove_bbcode($text);

    $nuked_quotes = preg_replace("#\n\n\n+#", "\n\n", $nuked_quotes);
    $nuked_quotes = remove_bbcode($nuked_quotes);

    return array($text, $nuked_quotes, $images);
}

define('FR_UTILS_INCLUDED', true);

?>
