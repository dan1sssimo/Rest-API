<?php

require_once('db.php');
require_once('../model/Response.php');
require_once('../utils/ResponseFunc.php');

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error - " . $ex, 0);
    sendResponse(500, false, "Database connection error");
}

if (array_key_exists("sessionid", $_GET)) {
    $sessionid = $_GET['sessionid'];

    if ($sessionid === '' || !is_numeric($sessionid)) {
        $message = false;

        if ($sessionid === '') {
            $message = ("Access token is missing from the header");
        } elseif (!is_numeric($sessionid)) {
            $message = ("Session ID must be numeric");
        }

        sendResponse(400, false, $message);
    }
    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
        $message = null;

        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $message = ("Access token is missing from header");
        }

        if (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
            $message = ("Access token cannot be blank");
        }

        sendResponse(401, false, $message);
    }

    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        try {
            $query = $writeDB->prepare('delete from tblsessions where id = :sessionid and accesstoken = :accesstoken');
            $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->execute();
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(400, false, "Failed to log out of this sessions using access token provided");
            }

            $returnData = array();
            $returnData['session_id'] = intval($sessionid);
            sendResponse(200, true, "Logged out", false, $returnData);
        } catch (PDOException $ex) {
            sendResponse(500, false, "There was an issue logging out - please try again");
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            sendResponse(400, false, "Content Type header not set to JSON");
        }

        $rawPatchData = file_get_contents('php://input');

        if (!$jsonData = json_decode($rawPatchData)) {
            sendResponse(400, false, "Request body is not valid JSON");
        }

        if (!isset($jsonData->refresh_token) || strlen($jsonData->refresh_token) < 1) {
            $message = null;

            if (strlen($jsonData->refresh_token) < 1) {
                $message = ("Refresh token cannot be blank");
            }

            if (!isset($jsonData->refresh_token)) {
                $message = ("Refresh token not supplied");
            }

            sendResponse(400, false, $message);
        }

        try {
            $refresh_token = $jsonData->refresh_token;
            $query = $writeDB->prepare('select tblsessions.id as sessionid, tblsessions.userid as 
                userid, accesstoken, refreshtoken, useractive, loginattempts, accesstokenexpiry, refreshtokenexpiry from tblsessions, tblusers where tblusers.id = tblsessions.userid
                and tblsessions.id = :sessionid and tblsessions.accesstoken = :accesstoken and tblsessions.refreshtoken = :refreshtoken');
            $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->bindParam(':refreshtoken', $refresh_token, PDO::PARAM_STR);
            $query->execute();
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(401, false, "Access token or refresh token is incorrect for session id");
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);
            $returned_sessionid = $row['sessionid'];
            $returned_userid = $row['userid'];
            $returned_accesstoken = $row['accesstoken'];
            $returned_refreshtoken = $row['refreshtoken'];
            $returned_useractive = $row['useractive'];
            $returned_loginattempts = $row['loginattempts'];
            $returned_accesstokenexpiry = $row['accesstokenexpiry'];
            $returned_refresh_token_expiry = $row['refreshtokenexpiry'];

            if ($returned_useractive !== 'Y') {
                sendResponse(401, false, "User account is not active");
            }

            if ($returned_loginattempts >= 3) {
                sendResponse(401, false, "User account is currently locked out");
            }

            if (strtotime($returned_refresh_token_expiry) < time()) {
                sendResponse(401, false, "Refresh token has expired - please log in again");
            }

            if (strtotime($returned_accesstokenexpiry) < time()) {
                sendResponse(401, false, "Access token has expired - please log in again");
            }

            $accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
            $refresh_token = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
            $access_token_expiry_seconds = 1200;
            $refresh_token_expiry_seconds = 1209600;
            $query = $writeDB->prepare('update tblsessions set accesstoken = :accesstoken, accesstokenexpiry = date_add(NOW(), INTERVAL :accesstokenexpiryseconds SECOND),
                         refreshtoken = :refreshtoken, refreshtokenexpiry = date_add(NOW(), INTERVAL :refreshtokenexpiryseconds SECOND) where id= :sessionid and userid = :userid 
                         and accesstoken = :returnedaccesstoken and refreshtoken=:returnedrefreshtoken');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->bindParam(':sessionid', $returned_sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->bindParam(':accesstokenexpiryseconds', $access_token_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(':refreshtoken', $refresh_token, PDO::PARAM_STR);
            $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(':returnedaccesstoken', $returned_accesstoken, PDO::PARAM_STR);
            $query->bindParam(':returnedrefreshtoken', $returned_refreshtoken, PDO::PARAM_STR);
            $query->execute();
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(401, false, "Access token could not be refreshed - please log in again");
            }

            $returnData = array();
            $returnData['session_id'] = $returned_sessionid;
            $returnData['access_token'] = $accesstoken;
            $returnData['access_token_expiry'] = $access_token_expiry_seconds;
            $returnData['refresh_token'] = $refresh_token;
            $returnData['refresh_token_expiry'] = $refresh_token_expiry_seconds;
            sendResponse(200, true, "Token refreshed", false, $returnData);
        } catch (PDOException $ex) {
            sendResponse(500, false, "There was an issue refreshing access token - please try log in again");
        }
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} elseif (empty($_GET)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(405, false, "Request method not allowed");
    }

    sleep(1);

    if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        sendResponse(400, false, "Content Type header not set to JSON");
    }

    $rawPostData = file_get_contents('php://input');

    if (!$jsonData = json_decode($rawPostData)) {
        sendResponse(400, false, "Request body is not valid JSON");
    }

    if (!isset($jsonData->username) || !isset($jsonData->password)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        if (!isset($jsonData->username)) $response->addMessage("Username not supplied");
        if (!isset($jsonData->password)) $response->addMessage("Password not supplied");
        $response->send();
        exit();
    }

    if (strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        if (strlen($jsonData->username) < 1) $response->addMessage("Username cannot be blank");
        if (strlen($jsonData->username) > 255) $response->addMessage("Username cannot be greater than 255 characters");
        if (strlen($jsonData->password) < 1) $response->addMessage("Password cannot be blank");
        if (strlen($jsonData->password) > 255) $response->addMessage("Password cannot be greater than 255 characters");
        $response->send();
        exit();
    }
    try {
        $username = $jsonData->username;
        $password = $jsonData->password;
        $query = $writeDB->prepare('select id, fullname, username, password, useractive, loginattempts from tblusers where username = :username');
        $query->bindParam(':username', $username, PDO::PARAM_STR);
        $query->execute();
        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            sendResponse(401, false, "Username or password is incorrect");
        }

        $row = $query->fetch(PDO::FETCH_ASSOC);
        $returned_id = $row['id'];
        $returned_full_name = $row['fullname'];
        $returned_username = $row['username'];
        $returned_password = $row['password'];
        $returned_useractive = $row['useractive'];
        $returned_loginattempts = $row['loginattempts'];

        if ($returned_useractive !== 'Y') {
            sendResponse(401, false, "User account not active");
        }

        if ($returned_loginattempts >= 3) {
            sendResponse(401, false, "User account is currently locked out");
        }

        if (!password_verify($password, $returned_password)) {
            $query = $writeDB->prepare('update tblusers set loginattempts = loginattempts+1 where id=:id');
            $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
            $query->execute();
            sendResponse(401, false, "Username or password is incorrect");
        }

        $accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
        $refresh_token = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
        $access_token_expiry_seconds = 1200;
        $refresh_token_expiry_seconds = 1209600;
    } catch (PDOException $ex) {
        sendResponse(500, false, "There was an issue logging in");
    }
    try {
        $writeDB->beginTransaction();
        $query = $writeDB->prepare('update tblusers set loginattempts = 0 where id = :id');
        $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
        $query->execute();
        $query = $writeDB->prepare('insert into tblsessions (userid, accesstoken, accesstokenexpiry, refreshtoken, refreshtokenexpiry) values (:userid, :accesstoken, date_add(NOW(),INTERVAL :accesstokenexpiryseconds SECOND ), :refreshtoken,date_add(NOW(),INTERVAL :refreshtokenexpiryseconds SECOND ))');
        $query->bindParam(':userid', $returned_id, PDO::PARAM_INT);
        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        $query->bindParam(':accesstokenexpiryseconds', $access_token_expiry_seconds, PDO::PARAM_INT);
        $query->bindParam(':refreshtoken', $refresh_token, PDO::PARAM_STR);
        $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_INT);
        $query->execute();
        $lastSessionID = $writeDB->lastInsertId();
        $writeDB->commit();
        $returnData = array();
        $returnData['session_id'] = intval($lastSessionID);
        $returnData['access_token'] = $accesstoken;
        $returnData['access_token_expires_in'] = $access_token_expiry_seconds;
        $returnData['refresh_token'] = $refresh_token;
        $returnData['refresh_token_expires_in'] = $refresh_token_expiry_seconds;

        sendResponse(201, true, "Session created", false, $returnData);
    } catch (PDOException $ex) {
        $writeDB->rollBack();
        sendResponse(500, false, "There was an issue logging in - please try again");
    }
} else {
    sendResponse(404, false, "Endpoint not found");
}