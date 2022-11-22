<?php


require_once('DB.php');
require_once('../model/Response.php');
require_once('../Utils/ResponseFunc.php');

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error - " . $ex, 0);
    sendResponse(500, false, "Database connection error");
}

// CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Methods: Content-Type');
    header('Access-Control-Max-Age: 86400');

    sendResponse(200, true, false);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, false, "Request method not allowed");
}

if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
    sendResponse(400, false, "Content Type header not set to JSON");
}

$rawPostData = file_get_contents('php://input');

if (!$jsonData = json_decode($rawPostData)) {
    sendResponse(400, false, "Request body is not valid JSON");
}

if (!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)) {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);

    if (!isset($jsonData->fullname)) $response->addMessage("Full name not supplied");

    if (!isset($jsonData->username)) $response->addMessage("Username not supplied");

    if (!isset($jsonData->password)) $response->addMessage("Password not supplied");

    $response->send();
    exit();
}

if (strlen($jsonData->fullname) < 1 || strlen($jsonData->fullname) > 255 || strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255) {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);

    if (strlen($jsonData->fullname) < 1) $response->addMessage("Full name cannot be blank");

    if (strlen($jsonData->fullname) > 255) $response->addMessage("Full name cannot be greater than 255 characters");

    if (strlen($jsonData->username) < 1) $response->addMessage("Username cannot be blank");

    if (strlen($jsonData->username) > 255) $response->addMessage("Username cannot be greater than 255 characters");

    if (strlen($jsonData->password) < 1) $response->addMessage("Password cannot be blank");

    if (strlen($jsonData->password) > 255) $response->addMessage("Password cannot be greater than 255 characters");

    $response->send();
    exit();
}

$fullname = trim($jsonData->fullname);
$username = trim($jsonData->username);
$password = $jsonData->password;

try {
    $query = $writeDB->prepare('select id from tblusers where username = :username');
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();
    $rowCount = $query->rowCount();

    if ($rowCount !== 0) {
        sendResponse(409, false, "Username already exists");
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $query = $writeDB->prepare('insert into tblusers (fullname, username, password) values (:fullname, :username, :password)');
    $query->bindParam(':fullname', $fullname, PDO::PARAM_STR);
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->bindParam(':password', $hashed_password, PDO::PARAM_STR);
    $query->execute();
    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
        sendResponse(500, false, "There was an issue creating a user account - please try again");
    }

    $lastUserID = $writeDB->lastInsertId();
    $returnData = array();
    $returnData['user_id'] = $lastUserID;
    $returnData['fullname'] = $fullname;
    $returnData['username'] = $username;

    sendResponse(201, true, "User created", false, $returnData);
} catch (PDOException $ex) {
    error_log("Database query error: " . $ex, 0);
    sendResponse(500, false, "There was an issue creating a user account - please try again");
}










