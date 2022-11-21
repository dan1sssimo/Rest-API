<?php

require_once('DB.php');
require_once('../model/Response.php');
require_once('../model/Image.php');

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

function checkAuthStatusAndReturnUserID($writeDB)
{
// begin auth script
    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
        $message = null;
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $message = "Access token is missing from the header";
        } else {
            if (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
                $message = "Access token cannot be blank";
            }
        }
        sendResponse(401, false, $message);
    }

    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

    try {
        $query = $writeDB->prepare('select userid, accesstokenexpiry, useractive, loginattempts from tblsessions, tblusers where tblsessions.userid = tblusers.id and accesstoken = :accesstoken');
        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            sendResponse(401, false, 'Invalid Access Token');
        }

        $row = $query->fetch(PDO::FETCH_ASSOC);

        $returned_userid = $row['userid'];
        $returned_accesstokenexpiry = $row['accesstokenexpiry'];
        $returned_useractive = $row['useractive'];
        $returned_loginattempts = $row['loginattempts'];

        if ($returned_useractive !== 'Y') {
            sendResponse(401, false, "User account is not active");
        }
        if ($returned_loginattempts >= 3) {
            sendResponse(401, false, "User account is currently locked out");
        }
        if (strtotime($returned_accesstokenexpiry) < time()) {
            sendResponse(401, false, "Access token has expired - please log in again");
        }
        return $returned_userid;
    } catch (PDOException $ex) {
        sendResponse(500, false, "There was an issue authenticating - please try again");
    }
// end auth script
}

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error - " . $ex, 0);
    sendResponse(500, false, "Database connection error");
}

if (array_key_exists("taskid", $_GET) && array_key_exists("imageid", $_GET) && array_key_exists("attributes", $_GET)) {
    $taskid = $_GET['taskid'];
    $imageid = $_GET['imageid'];
    $attributes = $_GET['attributes'];

    if ($imageid == '' || !is_numeric($imageid) || $taskid == '' || !is_numeric($taskid)) {
        sendResponse(400, false, "Image ID or Task ID cannot be blank and must be numeric");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {

    } else {
        sendResponse(405, false, "Request method not allowed");
    }
// /tasks/1/images/5
} elseif (array_key_exists("taskid", $_GET) && array_key_exists("imageid", $_GET)) {
    $taskid = $_GET['taskid'];
    $imageid = $_GET['imageid'];
    if ($imageid == '' || !is_numeric($imageid) || $taskid == '' || !is_numeric($taskid)) {
        sendResponse(400, false, "Image ID or Task ID cannot be blank and must be numeric");
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} // /tasks/1/images
elseif (array_key_exists("taskid", $_GET) && !array_key_exists("imageid", $_GET)) {
    $taskid = $_GET['taskid'];
    if ($taskid == '' || !is_numeric($taskid)) {
        sendResponse(400, false, "Task ID cannot be blank and must be numeric");
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} else {
    sendResponse(404, false, "Endpoint not found");
}