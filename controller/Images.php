<?php

require_once('DB.php');
require_once('../model/Response.php');
require_once('../model/Image.php');
require_once('../utils/ResponseFunc.php');

function uploadImageRoute($readDB, $writeDB, $taskID, $returned_userid)
{
    try {
        if (!isset($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'], "multipart/form-data; boundary=") === false) {
            sendResponse(400, false, "Content type header not set to multipart/form-data with a boundary");
        }
        $query = $readDB->prepare('select id from tbltasks where id = :taskid and userid= :userid');
        $query->bindParam(':taskid', $taskID, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();
        if ($rowCount === 0) {
            sendResponse(404, false, "Task Not Found");
        }
        if (!isset($_POST['attributes'])) {
            sendResponse(404, false, "Attributes missing from body of request");
        }
        if (!$jsonImageAttributes = json_decode($_POST['attributes'])) {
            sendResponse(400, false, "Attributes field is not valid JSON");
        }
        if (!isset($jsonImageAttributes->title) || !isset($jsonImageAttributes->filename) || $jsonImageAttributes->title == '' || $jsonImageAttributes->filename == '') {
            sendResponse(400, false, "Title and Filename fields are mandatory");
        }
        if (strpos($jsonImageAttributes->filename, ".") > 0) {
            sendResponse(400, false, "Filename must not contain a file extension");
        }
        if (!isset($_FILES['imagefile']) || $_FILES['imagefile']['error'] !== 0) {
            sendResponse(500, false, "Image file upload unsuccessful - make sure you selected a file");
        }

        $imageFileDetails = getimagesize($_FILES['imagefile']['tmp_name']);

        if (isset($_FILES['imagefile']['size']) && $_FILES['imagefile']['size'] > 5242880) {
            sendResponse(400, false, "File must be under 5MB");
        }

        $allowedImageFileTypes = array('image/jpeg', 'image/gif', 'image/png');

        if (!in_array($imageFileDetails['mime'], $allowedImageFileTypes)) {
            sendResponse(400, false, "File type not supported");
        }
        $fileExtension = "";
        switch ($imageFileDetails['mime']) {
            case "image/jpeg":
                $fileExtension = ".jpg";
                break;
            case "image/gif":
                $fileExtension = ".gif";
                break;
            case "image/png":
                $fileExtension = ".png";
                break;
            default:
                break;
        }
        if ($fileExtension == "") {
            sendResponse(400, false, "No valid file extension found for mimetype");
        }
        $image = new Image(null, $jsonImageAttributes->title, $jsonImageAttributes->filename . $fileExtension, $imageFileDetails['mime'], $taskID);
        $title = $image->getTitle();
        $newFileName = $image->getFilename();
        $mimetype = $image->getMimetype();

        $query = $readDB->prepare('select tblimages.id from tblimages, tbltasks where tblimages.taskid = tbltasks.id and tbltasks.id = :taskid and tbltasks.userid = :userid and tblimages.filename = :filename');
        $query->bindParam(':taskid', $taskID, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->bindParam(':filename', $newFileName, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount !== 0) {
            sendResponse(409, false, "A file with that filename already exists for this task - try a different filename");
        }

        $writeDB->beginTransaction();

        $query = $writeDB->prepare('insert into tblimages (title, filename, mimetype, taskid) values (:title,:filename,:mimetype,:taskid)');
        $query->bindParam(':title', $title, PDO::PARAM_STR);
        $query->bindParam(':filename', $newFileName, PDO::PARAM_STR);
        $query->bindParam(':mimetype', $mimetype, PDO::PARAM_STR);
        $query->bindParam(':taskid', $taskID, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }
            sendResponse(500, false, "Failed to upload image");
        }
        $lastImageID = $writeDB->lastInsertId();

        $query = $writeDB->prepare('select tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid from tblimages, tbltasks where tblimages.id = :imageid and tbltasks.id = :taskid and tbltasks.userid = :userid and tblimages.taskid = tbltasks.id');
        $query->bindParam(':imageid', $lastImageID, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskID, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();
        if ($rowCount === 0) {
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }
            sendResponse(500, false, "Failed to retrieve image attributes after upload - try uploading image again");
        }
        $imageArray = array();
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']);
            $imageArray[] = $image->returnImageAsArray();
        }
        $image->saveImageFile($_FILES['imagefile']['tmp_name']);
        $writeDB->commit();
        sendResponse(201, true, "Image uploaded successfully", false, $imageArray);
    } catch (PDOException $ex) {
        error_log("Database Query Error: " . $ex, 0);
        if ($writeDB->inTransaction()) {
            $writeDB->rollBack();
        }
        sendResponse(500, false, "Failed tot upload the image");
    } catch (ImageException $ex) {
        sendResponse(500, false, $ex->getMessage());
    }
}

function getImageAttributesRoute($readDB, $taskid, $imageid, $returned_userid)
{
    try {
        $query = $readDB->prepare('SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid from tblimages, tbltasks where tblimages.id = :imageid and tbltasks.id = :taskid and tbltasks.userid = :userid and tblimages.taskid = tbltasks.id');
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();
        if ($rowCount === 0) {
            sendResponse(404, false, "Image Not Found");
        }
        $imageArray = array();
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']);
            $imageArray[] = $image->returnImageAsArray();
        }
        sendResponse(200, true, "Image attributes", true, $imageArray);
    } catch (ImageException $ex) {
        sendResponse(500, false, $ex->getMessage());
    } catch (PDOException $ex) {
        error_log("Database Query Error : " . $ex, 0);
        sendResponse(500, false, "Failed to get image attributes");
    }
}

function getImageRoute($readDB, $taskid, $imageid, $returned_userid)
{
    try {
        $query = $readDB->prepare('SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid from tblimages, tbltasks where tblimages.id = :imageid and tbltasks.id = :taskid and tbltasks.userid = :userid and tblimages.taskid = tbltasks.id');
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();
        if ($rowCount === 0) {
            sendResponse(404, false, "Image Not Found");
        }
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']);
            $image->returnImageFile();
        }
        if ($image = null) {
            sendResponse(500, false, "Image Not Found");
        }

    } catch (ImageException $ex) {
        sendResponse(400, false, $ex->getMessage());
    } catch (PDOException $ex) {
        error_log("Database Query Error : " . $ex, 0);
        sendResponse(500, false, "Error getting Image");
    }
}

function updateImageAttributesRoute($writeDB, $taskid, $imageid, $returned_userid)
{
    try {
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            sendResponse(400, false, "Content type header not set to JSON");
        }
        $rawPatchData = file_get_contents('php://input');
        if (!$jsonData = json_decode($rawPatchData)) {
            sendResponse(400, false, "Request body is not valid JSON");
        }

        $title_updated = false;
        $filename_updated = false;

        $queryFields = "";

        if (isset($jsonData->title)) {
            $title_updated = true;
            $queryFields .= "tblimages.title = :title, ";
        }

        if (isset($jsonData->filename)) {
            if (strpos($jsonData->filename, ".") !== false) {
                sendResponse(400, false, "Filename cannot contain any dots or file extensions");
            }
            $filename_updated = true;
            $queryFields .= "tblimages.filename = :filename, ";
        }

        $queryFields = rtrim($queryFields, ", ");

        if ($title_updated === false && $filename_updated === false) {
            sendResponse(400, false, "No image fields provided");
        }

        $writeDB->beginTransaction();
        $query = $writeDB->prepare('SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid from tblimages, tbltasks where tblimages.id = :imageid and tblimages.taskid = :taskid and tblimages.taskid = tbltasks.id and tbltasks.userid = :userid');
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();
        if ($rowCount === 0) {
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }
            sendResponse(404, false, "No image found to update");
        }
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']);
        }
        $queryString = "update tblimages inner join tbltasks on tblimages.taskid = tbltasks.id set " . $queryFields . " where tblimages.id = :imageid and tblimages.taskid = tbltasks.id and tblimages.taskid = :taskid and tbltasks.userid = :userid";
        $query = $writeDB->prepare($queryString);
        if ($title_updated === true) {
            $image->setTitle($jsonData->title);
            $up_title = $image->getTitle();
            $query->bindParam(':title', $up_title, PDO::PARAM_STR);
        }
        if ($filename_updated === true) {
            $originalFilename = $image->getFilename();
            $image->setFilename($jsonData->filename . "." . $image->getFileExtension());
            $up_filename = $image->getFilename();
            $query->bindParam(':filename', $up_filename, PDO::PARAM_STR);
        }

        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }
            sendResponse(400, false, "Image attributes not updated - given values may be the same as the stored values");
        }

        $query = $writeDB->prepare('SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid from tblimages, tbltasks where tblimages.id = :imageid and tbltasks.id = :taskid and tbltasks.userid = :userid and tblimages.taskid = tbltasks.id');
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }
            sendResponse(404, false, "No image found");
        }

        $imageArray = array();

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']);
            $imageArray[] = $image->returnImageAsArray();
        }
        if ($filename_updated === true) {
            $image->renameImageFile($originalFilename, $up_filename);
        }
        $writeDB->commit();
        sendResponse(200, true, "Image attributes updated", false, $imageArray);
    } catch (ImageException $ex) {
        if ($writeDB->inTransaction()) {
            $writeDB->rollBack();
        }
        sendResponse(400, false, $ex->getMessage());
    } catch (PDOException $ex) {
        error_log("Database Query Error : " . $ex, 0);
        if ($writeDB->inTransaction()) {
            $writeDB->rollBack();
        }
        sendResponse(500, false, "Failed to update Image - check your data for errors");
    }
}

function deleteImageRoute($writeDB, $taskid, $imageid, $returned_userid)
{
    try {
        $writeDB->beginTransaction();
        $query = $writeDB->prepare('SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid from tblimages, tbltasks where tblimages.id = :imageid and tbltasks.id = :taskid and tbltasks.userid = :userid and tblimages.taskid = tbltasks.id');
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();
        if ($rowCount === 0) {
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }
            sendResponse(404, false, "Image not found");
        }
        $image = null;
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']);
        }

        if ($image == null) {
            $writeDB->rollBack();
            sendResponse(500, false, "Failed to get Image");
        }

        $query = $writeDB->prepare('delete tblimages from tblimages, tbltasks where tblimages.id = :imageid and tbltasks.id = :taskid and tblimages.taskid = tbltasks.id and tbltasks.userid = :userid');
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();
        if ($rowCount === 0) {
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }
            sendResponse(404, false, "Failed to delete Image");
        }

        $image->deleteImageFile();
        $writeDB->commit();

        sendResponse(200, true, "Image Deleted");
    } catch (ImageException $ex) {
        $writeDB->rollBack();
        sendResponse(400, false, $ex->getMessage());
    } catch (PDOException $ex) {
        error_log("Database Query Error : " . $ex, 0);
        $writeDB->rollBack();
        sendResponse(500, false, "Failed to delete image");
    }
}

function checkAuthStatusAndReturnUserID($writeDB)
{
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

    $accessToken = $_SERVER['HTTP_AUTHORIZATION'];

    try {
        $query = $writeDB->prepare('select userid, accesstokenexpiry, useractive, loginattempts from tblsessions, tblusers where tblsessions.userid = tblusers.id and accesstoken = :accesstoken');
        $query->bindParam(':accesstoken', $accessToken, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            sendResponse(401, false, 'Invalid Access Token');
        }

        $row = $query->fetch(PDO::FETCH_ASSOC);

        $returned_userid = $row['userid'];
        $returned_access_token_expiry = $row['accesstokenexpiry'];
        $returned_user_active = $row['useractive'];
        $returned_login_attempts = $row['loginattempts'];

        if ($returned_user_active !== 'Y') {
            sendResponse(401, false, "User account is not active");
        }
        if ($returned_login_attempts >= 3) {
            sendResponse(401, false, "User account is currently locked out");
        }
        if (strtotime($returned_access_token_expiry) < time()) {
            sendResponse(401, false, "Access token has expired - please log in again");
        }
        return $returned_userid;
    } catch (PDOException $ex) {
        sendResponse(500, false, "There was an issue authenticating - please try again");
    }
    return null;
}

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error - " . $ex, 0);
    sendResponse(500, false, "Database connection error");
}

$returned_userid = checkAuthStatusAndReturnUserID($writeDB);

if (array_key_exists("taskid", $_GET) && array_key_exists("imageid", $_GET) && array_key_exists("attributes", $_GET)) {
    $taskid = $_GET['taskid'];
    $imageid = $_GET['imageid'];
    $attributes = $_GET['attributes'];

    if ($imageid == '' || !is_numeric($imageid) || $taskid == '' || !is_numeric($taskid)) {
        sendResponse(400, false, "Image ID or Task ID cannot be blank and must be numeric");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        getImageAttributesRoute($readDB, $taskid, $imageid, $returned_userid);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        updateImageAttributesRoute($writeDB, $taskid, $imageid, $returned_userid);
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} elseif (array_key_exists("taskid", $_GET) && array_key_exists("imageid", $_GET)) {
    $taskid = $_GET['taskid'];
    $imageid = $_GET['imageid'];
    if ($imageid == '' || !is_numeric($imageid) || $taskid == '' || !is_numeric($taskid)) {
        sendResponse(400, false, "Image ID or Task ID cannot be blank and must be numeric");
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        getImageRoute($readDB, $taskid, $imageid, $returned_userid);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        deleteImageRoute($writeDB, $taskid, $imageid, $returned_userid);
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} elseif (array_key_exists("taskid", $_GET) && !array_key_exists("imageid", $_GET)) {
    $taskid = $_GET['taskid'];
    if ($taskid == '' || !is_numeric($taskid)) {
        sendResponse(400, false, "Task ID cannot be blank and must be numeric");
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        uploadImageRoute($readDB, $writeDB, $taskid, $returned_userid);
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} else {
    sendResponse(404, false, "Endpoint not found");
}