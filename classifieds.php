<?php

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'panjo');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('panjo');

// ########################## REQUIRE BACK-END ############################
require_once 'global.php';

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

Panjo::run();

?>