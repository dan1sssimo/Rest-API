<?php

require_once('Response.php');

$response = new Response();
$response->setSuccess(false);
$response->setHttpStatusCode(404);
$response->addMessage("T3213213");
$response->send();