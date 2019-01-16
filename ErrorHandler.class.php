<?php
/*
 * A class for handling errors and exceptions in our php application.
 */
class ErrorHandler{
    private $className;
    private $methodName;
    private $errorMessage;
    private $logEntry;

    public function __construct($className){
        /*
         * Constructor takes in the name of the class where the error/exception has occurred.
         */
        $this->className = $className;
    }

    public function createLogEntry($methodName, $errorMessage){
        /*
         * This method takes in the name of a method and also an error message or exception message
         * It creates and error entry with this information and stores it in a file called errorlog_.txt
         */
        $this->methodName = $methodName;
        $this->errorMessage = $errorMessage;
        //Something to write to txt log
        $this->logEntry  = "Error! Class name: " . $this->className . " - Method name: " . $this->methodName . " - Error message: " . $this->errorMessage . ' - ' . date("F j, Y, g:i a") . PHP_EOL;
        //Save string to log, use FILE_APPEND to append.
        file_put_contents('./errorlog_.txt', $this->logEntry, FILE_APPEND);
    }
}

?>