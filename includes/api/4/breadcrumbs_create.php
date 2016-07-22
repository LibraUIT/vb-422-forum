<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of breadcrumbs_create
 *
 * @author Jorge Tiznado
 */
class vB_APIMethod_breadcrumbs_create extends vBI_APIMethod {
    //put your code here

    public function output(){

       /* global $vbulletin;
        $vbulletin->input->clean_array_gpc('r', array(
			'facebookidList'      => TYPE_STR,
		));*/

        $data = array('response' => array('breadcrumbits' => $this->getBreadCrumbsBits()));
        //error_log("facebookidList ------ first1 \n", 3, "/var/www/html/facebook/error/error1.txt");
        return $data;

    }

    private function getBreadCrumbsBits(){

       global $vbulletin, $db;
       $arrayResponse = array();

       $vbulletin->input->clean_array_gpc('p', array('type' => TYPE_STR, 'conceptid' => TYPE_STR));

       $vbulletin->GPC['type'] = convert_urlencoded_unicode($vbulletin->GPC['type']);
       $vbulletin->GPC['conceptid'] = convert_urlencoded_unicode($vbulletin->GPC['conceptid']);
        //error_log("facebookidList = " . $vbulletin->GPC['facebookidList'] . "\n", 3, "/var/www/html/facebook/error/error1.txt");
       $conceptId = $vbulletin->GPC['conceptid'];
       $type = $vbulletin->GPC['type'];

       if($type == 't'){
           $threadInfo = $db->query_first("SELECT thread.forumid AS forumid FROM " . TABLE_PREFIX . "thread WHERE threadid=$conceptId");
           $conceptId = $threadInfo['forumid'];
           $parents = $db->query_first("SELECT forum.parentlist AS parentlist FROM " . TABLE_PREFIX . "forum WHERE forumid=$conceptId");
           //$parent = $db->fetch_array($parents)
          // error_log("parents = " . print_r($parents,true), 3, "/var/www/html/facebook/error/error2.txt");
           $parentsArray = explode("," , $parents['parentlist']);
           $parentsArray = array_reverse($parentsArray);
           $parents = implode(",", $parentsArray);
       }
       if($type == 'f'){
           $parents = $db->query_first("SELECT forum.parentlist AS parentlist FROM " . TABLE_PREFIX . "forum WHERE forumid=$conceptId");
           //$parent = $db->fetch_array($parents)
          // error_log("parents = " . print_r($parents,true), 3, "/var/www/html/facebook/error/error2.txt");
           $parentsArray = explode("," , $parents['parentlist']);
           array_shift($parentsArray);
           $parentsArray = array_reverse($parentsArray);
           $parents = implode(",", $parentsArray);
       }

       $forumInfo = $db->query_read_slave("SELECT forum.forumid AS forumid, forum.title AS title, forum.threadcount AS threadcount FROM forum WHERE forumid IN (" . $parents . ")");

       $breadCrumbsBits = array();
       while($parentForumInfo = $db->fetch_array($forumInfo)){
            $separator = ",";
           $breadCrumbsBits[$parentForumInfo['forumid']] = array(
               'forumid' => $parentForumInfo['forumid'],
               'title' => $parentForumInfo['title'],
               'threadcount' => $parentForumInfo['threadcount']
           );
       }
      //error_log("parents = " . $parentsArray, 3, "/var/www/html/facebook/error/error2.txt");
       $arrayResponse = array();
       //$parentsArray = explode(",", $parentsArray);
       foreach($parentsArray as $parent){
           if(in_array($breadCrumbsBits[$parent], $breadCrumbsBits))
               $arrayResponse[] = $breadCrumbsBits[$parent];
       }

     // $breadCrumbsBits = array_reverse($breadCrumbsBits);

      return $arrayResponse;
    }
}
?>
