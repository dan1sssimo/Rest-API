<?php

class ImageException extends Exception
{
}

class Image
{
    private $_id;
    private $_title;
    private $_filename;
    private $_mimetype;
    private $_taskID;
    private $_uploadFolderLocation;

    /**
     * @throws ImageException
     */
    public function __construct($id, $title, $filename, $mimetype, $taskID)
    {
        $this->setID($id);
        $this->setTitle($title);
        $this->setFilename($filename);
        $this->setMimetype($mimetype);
        $this->setTaskID($taskID);
        $this->_uploadFolderLocation = "../../../taskimages/";
    }

    public function getID()
    {
        return $this->_id;
    }

    public function getTitle()
    {
        return $this->_title;
    }

    public function getFilename()
    {
        return $this->_filename;
    }

    public function getFileExtension()
    {
        $filenameParts = explode(".", $this->_filename);
        $lastArrayElement = count($filenameParts) - 1;
        return $filenameParts[$lastArrayElement];
    }

    public function getMimetype()
    {
        return $this->_mimetype;
    }

    public function getTaskID()
    {
        return $this->_taskID;
    }

    public function getUploadFolderLocation(): string
    {
        return $this->_uploadFolderLocation;
    }

    public function getImageURL(): string
    {
        $httpOrHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $host = $_SERVER['HTTP_HOST'];
        $url = "/v1/tasks/" . $this->getTaskID() . "/images/" . $this->getID();
        return $httpOrHttps . "://" . $host . $url;
    }

    /**
     * @throws ImageException
     */
    public function setID($id)
    {
        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new ImageException("Image ID Error");
        }

        $this->_id = $id;
    }

    /**
     * @throws ImageException
     */
    public function setTitle($title)
    {
        if (strlen($title) < 1 || strlen($title) > 255) {
            throw new ImageException("Image title Error");
        }
        $this->_title = $title;
    }

    /**
     * @throws ImageException
     */
    public function setFilename($filename)
    {
        if (strlen($filename) < 1 || strlen($filename) > 30 || preg_match("/^[a-zA-Z\d_-]+(.jpg|.gif|.png)$/", $filename) != 1) {
            throw new ImageException("Image filename error - must be between 1 and 30 characters and only be .jpg .png .gif");
        }
        $this->_filename = $filename;
    }

    /**
     * @throws ImageException
     */
    public function setMimetype($mimetype)
    {
        if (strlen($mimetype) < 1 || strlen($mimetype) > 255) {
            throw new ImageException("Image mimetype Error");
        }
        $this->_mimetype = $mimetype;
    }

    /**
     * @throws ImageException
     */
    public function setTaskID($taskID)
    {
        if (($taskID !== null) && (!is_numeric($taskID) || $taskID <= 0 || $taskID > 9223372036854775807 || $this->_taskID !== null)) {
            throw new ImageException("Image Task ID Error");
        }

        $this->_taskID = $taskID;
    }

    /**
     * @throws ImageException
     */
    public function saveImageFile($tempFileName)
    {
        $uploadedFilePath = $this->getUploadFolderLocation() . $this->getTaskID() . '/' . $this->getFilename();
        if (!is_dir($this->getUploadFolderLocation() . $this->getTaskID())) {
            if (!mkdir($this->getUploadFolderLocation() . $this->getTaskID())) {
                throw  new ImageException("Failed to create image upload folder for task");
            }
        }
        if (!file_exists($tempFileName)) {
            throw new ImageException("Failed to upload image file");
        }
        if (!move_uploaded_file($tempFileName, $uploadedFilePath)) {
            throw new ImageException("Failed to upload image file");
        }
    }

    /**
     * @throws ImageException
     */
    public function renameImageFile($oldFileName, $newFileName)
    {
        $originalFilePath = $this->getUploadFolderLocation() . $this->getTaskID() . "/" . $oldFileName;
        $renamedFilePath = $this->getUploadFolderLocation() . $this->getTaskID() . "/" . $newFileName;

        if (!file_exists($originalFilePath)) {
            throw new ImageException("Cannot find image file to rename");
        }
        if (!rename($originalFilePath, $renamedFilePath)) {
            throw  new ImageException("Failed to update the filename");
        }
    }

    /**
     * @throws ImageException
     */
    public function deleteImageFile()
    {
        $filepath = $this->getUploadFolderLocation() . $this->getTaskID() . "/" . $this->getFilename();
        if (file_exists($filepath)) {
            if (!unlink($filepath)) {
                throw new ImageException("Failed to delete image file");
            }
        }
    }

    /**
     * @throws ImageException
     */
    public function returnImageFile()
    {
        $filepath = $this->getUploadFolderLocation() . $this->getTaskID() . '/' . $this->getFilename();

        if (!file_exists($filepath)) {
            throw new ImageException("Image file not found");
        }

        header('Content-Type: ' . $this->getMimetype());
        header('Content-Disposition: inline; filename="' . $this->getFilename() . '"');

        if (!readfile($filepath)) {
            http_response_code(404);
            exit();
        }
        exit();
    }

    public function returnImageAsArray(): array
    {
        $image = array();
        $image['id'] = $this->getID();
        $image['title'] = $this->getTitle();
        $image['filename'] = $this->getFilename();
        $image['mimetype'] = $this->getMimetype();
        $image['taskID'] = $this->getTaskID();
        $image['imageURL'] = $this->getImageURL();
        return $image;
    }
}