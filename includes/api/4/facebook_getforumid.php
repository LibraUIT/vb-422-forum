<?php
/**
 * Description of facebook_getforumid
 *
 * @author Jorge Tiznado
 */
class vB_APIMethod_facebook_getforumid extends vBI_APIMethod {
    //put your code here
    public function output()
    {
            $data = array('response' => array('forumid' => $this->getforumId()));
            return $data;
    }

    private function getforumId()
    {
        global $vbulletin, $db;

        $arrayResponse = array();

        
        
            $vbulletin->input->clean_array_gpc('r', array(
                'threadid' => TYPE_STR,
            ));

            $vbulletin->GPC['threadid'] = convert_urlencoded_unicode($vbulletin->GPC['threadid']);
            $threadid = $vbulletin->GPC['threadid'];

            $forumid = $db->query_first("
				SELECT thread.forumid
				FROM " . TABLE_PREFIX . "thread AS thread
				
				WHERE thread.threadid = $threadid
			");
            return $forumid['forumid'];
        
    }
}
?>
