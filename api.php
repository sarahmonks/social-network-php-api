<?php

require_once 'SocialNetworkAPI.class.php';
// Requests from the same server don't have a HTTP_ORIGIN header
if (!array_key_exists('HTTP_ORIGIN', $_SERVER)) {
    $_SERVER['HTTP_ORIGIN'] = $_SERVER['SERVER_NAME'];
}
$ErrorHandler = new ErrorHandler("api");
try {
    $API = new SocialNetworkAPI($_REQUEST, $_SERVER['HTTP_ORIGIN']);
    echo $API->processAPI();
} catch (Exception $e) {
    //create a log entry to record the error message
    $ErrorHandler->createLogEntry("api", $e->getMessage());
    echo json_encode(array('responseStatus' => 'error', 'errorMessage' => $e->getMessage()));
}
?>