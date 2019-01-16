<?php
/*
 * A class for working with an API Key. Any requests to our API will need an APIkey for the endpoints to be processed successfully
 */
class APIKey{
    private $companyAPIKey; //a company's API key which will be stored privately on the server
    private $ErrorHandler;
    private $requestAPIKey; //the aPI key that is passed to the endpoint with the API request
    private $keyIsValid = false; //Initialize keyIsValid to false.
 
    public function __construct($companyAPIKey){
        /*
         * This constructor takes in a company API key. 
         * This company API key will be defined in our config file which will be stored privately on the server.
         */
        $this->companyAPIKey = $companyAPIKey;
        //Create an instance of the ErrorHandler class in order to log errors
        $this->ErrorHandler = new ErrorHandler("APIKey");
    }

    public function verifyKey($requestAPIKey){
        /*
         * This method checks an API Key that was sent in an API request to see if it matches our company's API key.
         */
        try{
            $this->requestAPIKey = $requestAPIKey;
            if($this->requestAPIKey === $this->companyAPIKey){
                $this->keyIsValid = true;
                return $this->keyIsValid;
            }else{
                return false;
            }
        }catch(Exception $e){
            //create a log entry to record the error message
            $this->ErrorHandler->createLogEntry("verifyKey", $e->getMessage());
            return false;
        }
    }

}

?>