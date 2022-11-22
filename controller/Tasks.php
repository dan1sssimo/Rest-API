<?php

require_once('DB.php');
require_once('../model/Task.php');
require_once('../model/Response.php');
require_once('../model/Image.php');
require_once('../Utils/ResponseFunc.php');

/**
 * @throws ImageException
 */
function retrieveTaskImages($dbConnection, $taskid, $returned_userid): array
{
    $imageQuery = $dbConnection->prepare('SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid from tblimages, tbltasks where tbltasks.id = :taskid and tbltasks.userid = :userid and tblimages.taskid = tbltasks.id');
    $imageQuery->bindParam(':taskid', $taskid, PDO::PARAM_INT);
    $imageQuery->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
    $imageQuery->execute();
    $imageArray = array();

    while ($imageRow = $imageQuery->fetch(PDO::FETCH_ASSOC)) {
        $image = new Image($imageRow['id'], $imageRow['title'], $imageRow['filename'], $imageRow['mimetype'], $imageRow['taskid']);
        $imageArray[] = $image->returnImageAsArray();
    }

    return $imageArray;
}

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error - " . $ex, 0);
    sendResponse(500, false, "Database connection error");
}

// begin auth script
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
        sendResponse(401, false, 'User account is not active');
    }

    if ($returned_loginattempts >= 3) {
        sendResponse(401, false, 'User account is currently locked out');
    }

    if (strtotime($returned_accesstokenexpiry) < time()) {
        sendResponse(401, false, 'Access token has expired - please log in again');
    }
} catch (PDOException $ex) {
    sendResponse(500, false, 'There was an issue authenticating - please try again');
}
// end auth script
if (array_key_exists("taskid", $_GET)) {
    $taskid = $_GET['taskid'];

    if ($taskid == '' || !is_numeric($taskid)) {
        sendResponse(400, false, 'Task ID cannot be blank or must be numeric');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid and userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
            $rowCount = $query->rowCount();
            $taskArray = array();

            if ($rowCount === 0) {
                sendResponse(404, false, 'Task not found');
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $imageArray = retrieveTaskImages($readDB, $taskid, $returned_userid);
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            sendResponse(200, true, "Task loaded", true, $returnData);
        } catch (ImageException|TaskException $ex) {
            sendResponse(400, false, $ex->getMessage());
        } catch (PDOException $ex) {
            sendResponse(500, false, 'Failed to get task');
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        try {
            $imageSelectQuery = $readDB->prepare('SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid from tblimages, tbltasks where tbltasks.id = :taskid and tbltasks.userid = :userid and tblimages.taskid = tbltasks.id');
            $imageSelectQuery->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $imageSelectQuery->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $imageSelectQuery->execute();

            while ($imageRow = $imageSelectQuery->fetch(PDO::FETCH_ASSOC)) {
                $writeDB->beginTransaction();
                $image = new Image($imageRow['id'], $imageRow['title'], $imageRow['filename'], $imageRow['mimetype'], $imageRow['taskid']);
                $imageID = $image->getID();
                $query = $writeDB->prepare('delete tblimages from tblimages, tbltasks where tblimages.id = :imageid and tblimages.taskid = :taskid and tblimages.taskid = tbltasks.id and tbltasks.userid = :userid');
                $query->bindParam(':imageid', $imageID, PDO::PARAM_INT);
                $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                $query->execute();
                $image->deleteImageFile();
                $writeDB->commit();
            }

            $query = $writeDB->prepare('delete from tbltasks where id = :taskid and userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(404, false, 'Task not found');
            }

            $taskImageFolder = "../../../taskimages/" . $taskid;

            if (is_dir($taskImageFolder)) {
                rmdir($taskImageFolder);
            }

            sendResponse(200, true, 'Task deleted');
        } catch (ImageException $ex) {
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }

            sendResponse(400, false, $ex->getMessage());
        } catch (PDOException $ex) {
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }

            sendResponse(500, false, "Failed to delete task");
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        try {
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                sendResponse(400, false, "Content Type header not set to JSON");
            }

            $rawPatchData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPatchData)) {
                sendResponse(400, false, "Request body is not valid JSON");
            }

            $title_updated = false;
            $description_updated = false;
            $deadline_updated = false;
            $completed_updated = false;
            $queryFields = "";

            if (isset($jsonData->title)) {
                $title_updated = true;
                $queryFields .= "title = :title, ";
            }

            if (isset($jsonData->description)) {
                $description_updated = true;
                $queryFields .= "description = :description, ";
            }

            if (isset($jsonData->deadline)) {
                $deadline_updated = true;
                $queryFields .= "deadline = STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
            }

            if (isset($jsonData->completed)) {
                $completed_updated = true;
                $queryFields .= "completed = :completed, ";
            }

            $queryFields = rtrim($queryFields, ", ");

            if ($title_updated === false && $description_updated === false && $deadline_updated === false && $completed_updated === false) {
                sendResponse(400, false, "No task fields provided");
            }

            $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid and userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(404, false, "No task found to update");
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
            }

            $queryString = "update tbltasks set " . $queryFields . " where id = :taskid and userid = :userid";
            $query = $writeDB->prepare($queryString);

            if ($title_updated === true) {
                $task->setTitle($jsonData->title);
                $up_title = $task->getTitle();
                $query->bindParam(':title', $up_title, PDO::PARAM_STR);
            }

            if ($description_updated === true) {
                $task->setDescription($jsonData->description);
                $up_description = $task->getDescription();
                $query->bindParam(':description', $up_description, PDO::PARAM_STR);
            }

            if ($deadline_updated === true) {
                $task->setDeadline($jsonData->deadline);
                $up_deadline = $task->getDeadline();
                $query->bindParam(':deadline', $up_deadline, PDO::PARAM_STR);
            }

            if ($completed_updated === true) {
                $task->setCompleted($jsonData->completed);
                $up_completed = $task->getCompleted();
                $query->bindParam(':completed', $up_completed, PDO::PARAM_STR);
            }

            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(400, false, "Task not updated - given values may be the same as the stored values");
            }

            $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid and userid=:userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(404, false, "No task found");
            }

            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $imageArray = retrieveTaskImages($writeDB, $taskid, $returned_userid);
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            sendResponse(200, true, "Task updated", false, $returnData);
        } catch (ImageException|TaskException $ex) {
            sendResponse(400, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database Query Error: " . $ex, 0);
            sendResponse(500, false, "Failed to update task - check your data for errors");
        }
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} elseif (array_key_exists("completed", $_GET)) {
    $completed = $_GET['completed'];

    if ($completed !== 'Y' && $completed !== 'N') {
        sendResponse(400, false, "Completed filter must be Y or N");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $query = $readDB->prepare('select id, title , description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where completed = :completed and userid=:userid');
            $query->bindParam(':completed', $completed, PDO::PARAM_STR);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
            $rowCount = $query->rowCount();
            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $imageArray = retrieveTaskImages($readDB, $row['id'], $returned_userid);
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            sendResponse(200, true, "Tasks loaded", true, $returnData);
        } catch (ImageException|TaskException $ex) {
            sendResponse(400, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error -" . $ex, 0);
            sendResponse(500, false, "Failed to get tasks");
        }
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} elseif
(array_key_exists("page", $_GET)) {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $page = $_GET['page'];

        if ($page == '' || !is_numeric($page)) {
            sendResponse(400, false, "Page number cannot be blanc and must be numeric");
        }

        $limitPerPage = 5;

        try {
            $query = $readDB->prepare('select count(id) as totalNoOfTasks from tbltasks where userid=:userid');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
            $row = $query->fetch(PDO::FETCH_ASSOC);
            $tasksCount = intval($row['totalNoOfTasks']);
            $numOfPages = ceil($tasksCount / $limitPerPage);

            if ($numOfPages == 0) {
                $numOfPages = 1;
            }

            if ($page > $numOfPages || $page == 0) {
                sendResponse(404, false, "Page not found");
            }

            $offset = ($page == 1 ? 0 : ($limitPerPage * ($page - 1)));
            $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline,"%d/%m/%Y %H:%i") as deadline, completed from tbltasks where userid=:userid limit :pglimit offset :offset');
            $query->bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT);
            $query->bindParam(':offset', $offset, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
            $rowCount = $query->rowCount();
            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $imageArray = retrieveTaskImages($readDB, $row['id'], $returned_userid);
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['total_rows'] = $tasksCount;
            $returnData['total_page'] = $numOfPages;

            ($page < $numOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page'] = false);
            ($page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] = false);

            $returnData['tasks'] = $taskArray;

            sendResponse(200, true, "Tasks loaded", true, $returnData);
        } catch (ImageException|TaskException $ex) {
            sendResponse(400, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to get tasks");
        }
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} elseif (empty($_GET)) {
    if ($_SERVER["REQUEST_METHOD"] === 'GET') {
        try {
            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where userid=:userid');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
            $rowCount = $query->rowCount();
            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $imageArray = retrieveTaskImages($readDB, $row['id'], $returned_userid);
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            sendResponse(200, true, "Tasks loaded", true, $returnData);
        } catch (ImageException|TaskException $ex) {
            sendResponse(400, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database Query Error: " . $ex, 0);
            sendResponse(500, false, "Failed to get tasks");
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                sendResponse(400, false, "Content Type header not set to JSON");
            }

            $rawPostData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPostData)) {
                sendResponse(400, false, "Request body is not valid JSON");
            }

            if (!isset($jsonData->title) || !isset($jsonData->completed)) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                if (!isset($jsonData->title)) $response->addMessage("Title field is mandatory and must be provided");
                if (!isset($jsonData->completed)) $response->addMessage("Completed field is mandatory and must be provided");
                $response->send();
                exit;
            }

            $newTask = new Task(null, $jsonData->title, (isset($jsonData->description) ? $jsonData->description : null), (isset($jsonData->deadline) ? $jsonData->deadline : null), $jsonData->completed);
            $title = $newTask->getTitle();
            $description = $newTask->getDescription();
            $deadline = $newTask->getDeadline();
            $completed = $newTask->getCompleted();
            $query = $writeDB->prepare('insert into tbltasks (title, description, deadline, completed, userid) values (:title, :description, STR_TO_DATE(:deadline, \'%d/%m/%Y %H:%i\'), :completed, :userid)');
            $query->bindParam(':title', $title, PDO::PARAM_STR);
            $query->bindParam(':description', $description, PDO::PARAM_STR);
            $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
            $query->bindParam(':completed', $completed, PDO::PARAM_STR);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(500, false, "Failed to create task");
            }

            $lastTaskID = $writeDB->lastInsertId();
            $query = $writeDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid and userid=:userid');
            $query->bindParam(':taskid', $lastTaskID, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(500, false, "Failed to retrieve task after creation");
            }

            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            sendResponse(201, true, "Task created", false, $returnData);
        } catch (TaskException $ex) {
            sendResponse(400, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database Query Error: " . $ex, 0);
            sendResponse(500, false, "Failed to insert task into database - check submitted data for errors");
        }
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} else {
    sendResponse(404, false, "Endpoint not found");
}