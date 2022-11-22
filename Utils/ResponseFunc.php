<?php

require_once('../model/Response.php');

function sendResponse($statusCode, $success, $message = null, $toCache = false, $data = null)
{
    $response = new Response();
    $response->setHttpStatusCode($statusCode);
    $response->setSuccess($success);
    if ($message !== null) {
        $response->addMessage($message);
    }
    $response->toCache($toCache);
    if ($data !== null) {
        $response->setData($data);
    }
    $response->send();
    exit();
}