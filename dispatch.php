<?php
/**
* @version $Id$
* @author octeth.com/oempro <support@octeth.com>
* @see http://octeth.com/oempro
* @package Oempro Subscribe WP Plugin
*/

if (!function_exists('add_action')) {
    require_once("../../../wp-config.php");
}

// checking if plugin enabled and POST params are sent
if (
    !defined('OEMPRO_SUBSCRIBE') or
    !is_array($_POST) or 
    !isset($_POST['email']) or
    !isset($_POST['target_list'])
) {
    die('Hack attempt');
} else {
    $OemproSubscribeDispatcher = new OemproSubscribeDispatcher();
    $OemproSubscribeDispatcher->setEmail($_POST['email']);
    $OemproSubscribeDispatcher->setTargetList($_POST['target_list']);
    if (isset($_POST['custom_fields']))
        $OemproSubscribeDispatcher->setCustomFields($_POST['custom_fields']);
    if ('unsubscribe' == $_POST['action'])
        $OemproSubscribeDispatcher->unsubscribe();
    else
        $OemproSubscribeDispatcher->subscribe();
}





