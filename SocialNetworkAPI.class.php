<?php
require_once 'config.php';
require_once 'API.class.php';
require_once 'ErrorHandler.class.php';
require_once 'Validator.class.php';
require_once 'DatabaseConnection.class.php';

require_once 'APIKey.class.php';
require_once 'JWT.class.php';
require_once 'UserToken.class.php';
require_once 'User.class.php';
require_once 'Comment.class.php';
require_once 'Post.class.php';
require_once 'Recipe.class.php';
require_once 'Notification.class.php';

class SocialNetworkAPI extends API{

    protected $Validator;
    protected $requestOrigin;
    protected $ErrorHandler;
    protected $UserToken;
    protected $User; 
    protected $Post;
    protected $Comment;


    public function __construct($request, $origin){
        //This contructor takes in the super global $_REQUEST array as a parameter which should contain a request and apiKey index
        //and also the $_SERVER['HTTP_ORIGIN']
        parent::__construct($request['request']);
        //Note: It looks like the $origin value will be file:// when a request is made from a phonegap application on android. 
        //Whereas It will be a domain if the request is coming from a server.
        $this->requestOrigin = $origin; 

        //create an instance of the APIKey class, passing in the company APIkey defined in our config file to the constructor
        $APIKey = new APIKey(COMPANYAPIKEY);

      
        if (!array_key_exists('apiKey', $request)) {
          //  throw new Exception('No API Key provided');
        }elseif(!$APIKey->verifyKey($request['apiKey'])){

         //   throw new Exception('Invalid API Key');
        }
        $this->ErrorHandler = new ErrorHandler("MyAPI");
    }

   /**
     * userRegistration Endpoint
     * Format: domain.com/userRegistration?apiKey=12345678...
     * A HTTP POST request is performed on the client side to this endpoint with post variables: username, emailAddress, password
     */
    protected function userRegistration(){
        if ($this->method == 'POST'){

            //get the post data sent in the request.
            $emailAddress = $this->request->emailAddress;
            $password = $this->request->password;
            $username = $this->request->username;


            $filteredInputs = array(); //an array to hold the filtered inputs
            //create an instance of the Validator class
            $this->Validator = new Validator();
            //Check if the inputs are a valid length and also valid utf8 encoding
            $inputsAreValid = $this->Validator->checkInputsAreValid(
                array(array("input" => $emailAddress, "minLength" => 1, "maxLength" => 255), 
                      array("input" => $password, "minLength" => 6, "maxLength" => 20),
                      array("input" => $username, "minLength" => 6, "maxLength" => 12))); 

            if(!$inputsAreValid){
              //Inputs are not a valid length or not valid encoding.
              //Log this error for our own records as it shouldnt really occur as checks will have 
              //already been made on the client side.
              $this->ErrorHandler->createLogEntry("userRegistration", "User input values are not valid");
              //Throw an exception
              throw new Exception("User input values are not valid");
            }

            //If the inputs are a valid length and also valid utf8 then filter them.
            $filteredInputs = $this->Validator->filterInputs(
                array(array("input" => $emailAddress, "filterMethod" => FILTER_SANITIZE_STRING),
                      array("input" => $password, "filterMethod" => FILTER_SANITIZE_STRING),
                      array("input" => $username, "filterMethod" => FILTER_SANITIZE_STRING)));
            //store the filtered values into a local variables for more legibility
            $filteredEmailAddress = $filteredInputs[0];
            $filteredPassword = $filteredInputs[1];
            $filteredUsername = $filteredInputs[2];


            //Create an instance of the User class.
            $this->User = new User();
            $data = array(); 
            //call the registerUser method. This will return an emailAlreadyExists boolean
            //If the emailAlreadyExists boolean is true then we will also have a userID variable. userID will be null if 
            //registration was unsuccessful.
            $data = $this->User->registerUser($filteredEmailAddress, $filteredPassword, $filteredUsername);
            //responseStatus will be success unless there is an exception thrown in any of the methods down further
            $data['responseStatus'] = "success"; 
            if($data['emailAlreadyExists']){
              //the email address already exists so just return the data as is.
              return $data;   
            }
            if($data['userID'] != null){
              //the email address doesnt already exist so the userID variable will be populated with a value or null.
              //if the userID is not be null then registration was successful.
              //we get the users profile data
              $userProfileData = $this->User->getUserProfile($data['userID']);
              $data['username'] = $userProfileData['username'];
              $data['emailAddress'] = $userProfileData['emailAddress'];
              $data['firstName'] = $userProfileData['firstName'];
              $data['lastName'] = $userProfileData['lastName'];
              $data['profileImgUrl'] = $userProfileData['profileImgUrl'];
              $data['coverImgUrl'] = $userProfileData['coverImgUrl'];
              $data['bio'] = $userProfileData['bio'];
              
              //generate a userToken
              $this->UserToken = new UserToken();
              $data['userToken'] = $this->UserToken->createUserToken($data['userID'], $userProfileData['emailAddress']);  
            } 
            return $data;  
        }else{
            throw new Exception("Only accepts POST requests");
        }
    }


    /**
     * userAuth Endpoint
     * Format: domain.com/userAuth?apiKey=12345678...
     */
    protected function userAuth(){
          if ($this->method == 'POST'){
            //get the post data sent in the request.
            $emailAddress = $this->request->emailAddress;
            $password =  $this->request->password;

            $filteredInputs = array(); //an array to hold the filtered inputs
            //create an instance of the Validator class
            $this->Validator = new Validator();
            //Check if the inputs are a valid length and also valid utf8 encoding
            $inputsAreValid = $this->Validator->checkInputsAreValid(
                array(
                      array("input" => $emailAddress, "minLength" => 1, "maxLength" => 255), 
                      array("input" => $password, "minLength" => 6, "maxLength" => 20))); 

            if(!$inputsAreValid){
              //Inputs are not a valid length or not valid encoding.
              //Log this error for our own records as it shouldnt really occur as checks will have 
              //already been made on the client side.
              $this->ErrorHandler->createLogEntry("userAuth", "User input values are not valid.");
              //Throw an exception
              throw new Exception("User input values are not valid");
            }

            //If the inputs are a valid length and also valid utf8 then filter them.
            $filteredInputs = $this->Validator->filterInputs(
                array(array("input" => $emailAddress, "filterMethod" => FILTER_SANITIZE_STRING),
                      array("input" => $password, "filterMethod" => FILTER_SANITIZE_STRING)));
            //store the filtered values into a local variables for more legibility
            $filteredEmailAddress = $filteredInputs[0];
            $filteredPassword = $filteredInputs[1];


            //Create an instance of the User class.
            $this->User = new User();
            //Create an array to store the data that will be sent back to the client side
            $data = array(); 

            //Call the authenticateUser method which will return an array containing a key: emailExists (boolean) and tooManyFailedLogins (boolean).
            //If emailExists is true then we will also have keys: loginWasSuccessful (boolean) and also userID (int)
            $data = $this->User->authenticateUser($filteredEmailAddress, $filteredPassword);
            $data['responseStatus'] = "success"; //add the responseStatus key to the array that we send back.

            if($data['emailExists']){
              //if $data['emailExists'] is true that means we will have done further checks to see if the password is correct
              //so we know the data array will also have the following keys: loginWasSuccessful (boolean) and also userID (int)
              //We check if the credentials are valid i.e the login was successful
              if($data['loginWasSuccessful']){
                //successful authentication 
                //Get the user profile data and add it to the data array
                $userProfileData = $this->User->getUserProfile($data['userID']);
                $data['username'] = $userProfileData['username'];
                $data['emailAddress'] = $userProfileData['emailAddress'];
                $data['firstName'] = $userProfileData['firstName'];
                $data['lastName'] = $userProfileData['lastName'];
                $data['profileImgUrl'] = $userProfileData['profileImgUrl'];
                $data['coverImgUrl'] = $userProfileData['coverImgUrl'];
                $data['bio'] = $userProfileData['bio'];
              
                //we need to generate a JSON web token here and send it back to the client side (i.e store it in the data array).
                $this->UserToken = new UserToken();
                $data['userToken'] = $this->UserToken->createUserToken($data['userID'], $data['emailAddress']);  
               
              }
            }
            return $data;        
        }elseif($this->method == 'OPTIONS'){

        }else{
            throw new Exception("Only accepts POST requests. userAuth");
        }
    }

   /**
     * userProfile Endpoint
     * Format: domain.com/userProfile/userID?apiKey=12345678...
     */
    protected function userProfile(){
        if ($this->method == 'GET'){
            $userID = $this->args[0];

            $data = array(); 

            //Create instances of our classes.
            $this->User = new User();
            $data = $this->User->getUserProfile($userID);
            return $data;
        }else{
            throw new Exception("Only accepts GET requests");
        }
    }

   /**
     * userEmail Endpoint
     * Format: domain.com/userEmail/userID?apiKey=12345678...
     */
    protected function userEmail(){
        if ($this->method == 'GET'){
            $userID = $this->args[0];


            //Create instances of our classes.
            $this->User = new User();
            $email = $this->User->getUserEmail($userID);
            return $email;
        }else{
            throw new Exception("Only accepts GET requests");
        }
    }


    /**
     * posts Endpoint
     * Format: domain.com/posts/userID/userToken?apiKey=12345678...
     * We send the userToken as an argument of the url instead of in the body because thats not possible with get requests.
     */
    protected function posts(){
        if ($this->method == 'GET'){
            //userID will be the first argument of the endpoint URI.
            $userID = $this->args[0];
            $userToken = $this->args[1];
            //Create an instance of the UserToken class.
            $this->UserToken = new UserToken();

            $userIDFromJWT = $this->UserToken->verifyUserToken($userToken);

            $data = array(); //an array to hold the data returned to the client

            if($userIDFromJWT !== NULL){
              //the $userIDFromJWT is not null, therefore the userToken is valid.
              //Create an instance of the Post class and get all posts data.
              $this->Post = new Post();
              $data['posts'] = $this->Post->getUserPosts($userID);
              $data['responseStatus'] = "success"; //add the responseStatus key to the array that we send back.
              return $data;
            }else{
              //Log this error
              $this->ErrorHandler->createLogEntry("posts", "User Token is not valid.");
              throw new Exception("User token is not valid.");
            }
        }else{
            throw new Exception("Only accepts GET requests");
        }
    }

    /**
     * likePost Endpoint
     * Format: domain.com/likePost/userID/postID?apiKey=12345678...
     * We send the userToken in the body of the request.
     */
    protected function likePost(){
        if ($this->method == 'POST'){
            //userID will be the first argument of the endpoint URI.
            $userID = $this->args[0];
            //postID will be the second argument of the endpoint URI.
            $postID = $this->args[1];

            //get the post data sent in the request.
            $userToken = $this->request->userToken;
            //Create an instance of the UserToken class.
            $this->UserToken = new UserToken();

            $userIDFromJWT = $this->UserToken->verifyUserToken($userToken);

            $data = array(); //an array to hold the data returned to the client

            if($userIDFromJWT !== NULL){
              //the userID is not null, therefore the userToken is valid.
              //Create an instance of the Post class and call the likePost method.
              //store the result, which will be an array of PostLikes in "data"
              $this->Post = new Post();
              $data['postLikes'] = $this->Post->likePost($userID, $postID);
              $data['responseStatus'] = "success"; //add the responseStatus key to the array that we send back.
              return $data;
            }else{
              //Log this error
              $this->ErrorHandler->createLogEntry("likePost", "User Token is not valid.");
              throw new Exception("User token is not valid.");
            }
        }else{
            throw new Exception("Only accepts POST requests");
        }
    }
    /**
     * unlikePost Endpoint
     * Format: domain.com/unlikePost/userID/postID?apiKey=12345678...
     * We send the userToken in the body of the request.
     */
    protected function unlikePost(){
        if ($this->method == 'DELETE') {
            //userID will be the first argument of the endpoint URI.
            $userID = $this->args[0];
            //postID will be the second argument of the endpoint URI.
            $postID = $this->args[1];

            //get the data sent in the body of the request.
            $userToken = $this->request->userToken;
            //Create an instance of the UserToken class.
            $this->UserToken = new UserToken();

            $userIDFromJWT = $this->UserToken->verifyUserToken($userToken);

            $data = array();
            if($userIDFromJWT !== NULL){

              //the userID is not null, therefore the userToken is valid.
              //Create an instance of the Post class and call the unlikePost method from the Post class 
              //passing in the userID the postID we received in the parameters.
              //store the result, which will be an array of user data related to the users who have liked this particular post. 
              $this->Post = new Post();
              $data['postLikes'] = $this->Post->unlikePost($userID, $postID);
              $data['responseStatus'] = "success"; //add the responseStatus key to the array that we send back.
             
              return $data;
            }else{
              //Log this error
              $this->ErrorHandler->createLogEntry("unlikePost", "User Token is not valid.");
              throw new Exception("User token is not valid.");
            }

        }elseif($this->method == 'OPTIONS'){

        }else{
           throw new Exception("Only accepts DELETE requests");
        }
    }

    /**
     * userFriends Endpoint
     * Format: domain.com/userFriends/userID/userToken?apiKey=12345678...
     * We send the userToken as an argument of the url instead of in the body because thats not possible with get requests.
     */
    protected function userFriends(){
        if ($this->method == 'GET'){
            //userID will be the first argument of the endpoint URI.
            $userID = $this->args[0];
            //userToken will be the second argument of the endpoint URI.
            $userToken = $this->args[1];
            //Create an instance of the UserToken class.
            $this->UserToken = new UserToken();

            $userIDFromJWT = $this->UserToken->verifyUserToken($userToken);

            $data = array(); //an array to hold the data returned to the client

            if($userIDFromJWT !== NULL){
              //the $userIDFromJWT is not null, therefore the userToken is valid.
              //Create an instance of the User class and get all friends data related to the userID we received as a parameter.
              $this->User = new User();
              $data['friends'] = $this->User->getUserFriends($userID);
              $data['responseStatus'] = "success"; //add the responseStatus key to the array that we send back.
              return $data;
            }else{
              //Log this error
              $this->ErrorHandler->createLogEntry("userFriends", "User Token is not valid.");
              throw new Exception("User token is not valid.");
            }
        }else{
            throw new Exception("Only accepts GET requests");
        }
    }

    /**
     * userFriendsPosts Endpoint
     * Format: domain.com/userFriendsPosts/userID/userToken?apiKey=12345678...
     * We send the userToken as an argument of the url instead of in the body because thats not possible with get requests.
     */
    protected function userFriendsPosts(){
        if ($this->method == 'GET'){
            //userID will be the first argument of the endpoint URI.
            $userID = $this->args[0];
            //userToken will be the second argument of the endpoint URI.
            $userToken = $this->args[1];

            //Create an instance of the UserToken class.
            $this->UserToken = new UserToken();

            $userIDFromJWT = $this->UserToken->verifyUserToken($userToken);

            $data = array(); //an array to hold the data returned to the client

            if($userIDFromJWT !== NULL){
              //the $userIDFromJWT is not null, therefore the userToken is valid.
              //Create an instance of the Post class and get the posts of the users friends.
              $this->Post = new Post();
              $data['friendsPosts'] = $this->Post->getUserFriendsPosts($userID);
              $data['responseStatus'] = "success"; //add the responseStatus key to the array that we send back.
              return $data;
            }else{
              //Log this error
              $this->ErrorHandler->createLogEntry("userFriendsPosts", "User Token is not valid.");
              throw new Exception("User token is not valid.");
            }
        }else{
            throw new Exception("Only accepts GET requests");
        }
    }
    /**
     * testNotification Endpoint
     * Format: domain.com/testNotification?apiKey=12345678...
     * The purpose of this endpoint is to test our OneSignal notification system.
     */

    protected function testNotification(){
        $this->Notification = new Notification();
        $contents = array(
            "en" => 'Your message..!!'
        );
        $headings = array(
            "en" => "Your custom title message"
        );
        $data = array(
            "action" => "openPage", "id" => "42"
        );
        $playerIDs= array(ONESIGNALPLAYERID);
        $response = $this->Notification->generateNotification($headings, $contents, $data, $playerIDs);
        return $response;
    }

}
?>
