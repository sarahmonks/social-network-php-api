<?php

class User{
	
	
	private $DatabaseConnection;// stores the database handler
  //using protected so they can be accessed and overriden if necessary
  protected $userID; 
  protected $emailAddress;    
  protected $password; 
  protected $hashedPassword;
  protected $username;
  protected $userDetails = array(); //an array to store user profile details.
  protected $userFriends = array(); //an array to store details about the friends of the user

	public function __construct(){
		$this->DatabaseConnection = DatabaseConnection::getInstance();

	}
	public function registerUser($emailAddress, $password, $username){ 
	      $this->emailAddress = $emailAddress;
       	  $this->password = $password;
          $this->username = $username;
          $data = array(); //create an array as we want to return multiple variables such as the emailAlreadyExists boolean and the userID
          $data['emailAlreadyExists'] = $this->DatabaseConnection->checkEmailExists($this->emailAddress);
          if(!$data['emailAlreadyExists']){
            //email does not exist so the user is not already registered 
		    //call the insertUser method from DatabaseConnection class.
            $this->userID = $this->DatabaseConnection->insertUser($this->emailAddress, $this->password, $this->username);
            $data['userID'] = $this->userID;
          }
		  return $data;

	}


  public function getUserProfile($userID){
    /* 
     * This method takes in a userID as a parameter and gets a users profile data from our database
     */
   
    $this->userID = $userID;
    $this->userDetails = $this->DatabaseConnection->getUserProfile($this->userID);
    $this->userDetails['profileImgUrl'] = "http://localhost/my_app_images/" . $this->userDetails['profileImgUrl'];
    $this->userDetails['coverImgUrl'] = "http://localhost/my_app_images/cover_images/" . $this->userDetails['coverImgUrl'];
    return $this->userDetails;
  }

  public function getUserFriends($userID){
    /* 
     * This method takes in a userID as a parameter and gets the friends of that user.
     */
   
    $this->userID = $userID;
    $this->userFriends = $this->DatabaseConnection->getFriends($this->userID);

    foreach($this->userFriends as $key => $value){  
      //change all of the profileImgUrl's (of the userFriends array of user objects) to the full URL.
      $this->userFriends[$key]['profileImgUrl'] = "http://localhost/my_app_images/" . $value['profileImgUrl'];
    }
    return $this->userFriends;
  }

	public function authenticateUser($emailAddress, $password){
	    $this->emailAddress = $emailAddress;
       	$this->password = $password;


        $data = array();
        //Call the checkEmailForLogin($emailAddress) method which will return an array with a key which is a boolean called emailExists.

        $checkEmailResults = $this->DatabaseConnection->checkEmailForLogin($emailAddress);
        //Store the emailExists boolean into the $data that we will return from this method as we would like to output a different error to the user
        //if the email doesnt exist to the error that we will output if the password doesnt match.
        $data['emailExists'] = $checkEmailResults['emailExists'];
        $data['loginWasSuccessful'] = false; //default to false until set to true
        //create a tooManyFailedLogins variable to return with our data. The default will be false unless set to true after the check.
        $data['tooManyFailedLogins'] = false;

        if($checkEmailResults['emailExists']){
        	//If $checkEmailResults['emailExists'] is true then the $checkEmailResults array will also have userID and hashedPassword keys so we can get their values now.
        	$this->userID = $checkEmailResults['userID'];
          $this->hashedPassword = $checkEmailResults['hashedPassword'];
        	//We now know that the user is indeed registered so we do other checks now.
        	//Check to make sure there are less than 8 failed login attempts (using our checkBrute method).


        	if ($this->DatabaseConnection->checkBrute($this->userID) == true) {
               /*We will lock their account now as they have more than 8 failed login attempts in the last 20 minutes.
                *Do not let them pass the login stage (as this could potentially be a brute force attack).
                *In further developments we should send an email to them to tell them their account is locked.
                */

                $data['tooManyFailedLogins'] = true;
                //We still want to record subsequent failed attempts (in the login_attempts table) even in the 20 minute wait.
                //So we must verify the password again here
                if(!password_verify($this->password, $this->hashedPassword)) {
                    //Password is not correct
                    //We record this attempt in the login_attempts table.
                    $this->DatabaseConnection->recordFailedLoginAttempt($this->userID);
                }
            }else{
               /* Good news-The account is not locked.
                * Check if the password in the database matches the password the user submitted. 
                * We are using the password_verify function to avoid timing attacks.
                */
                if(password_verify($this->password, $this->hashedPassword)) {
                    //Password is correct! Therefore Login is successful.
                    $data['loginWasSuccessful'] = true;
                    $data['userID'] = $this->userID;
                }else{
                    //Password is not correct
                    //We record this attempt in the login_attempts table.
                    $this->DatabaseConnection->recordFailedLoginAttempt($this->userID);

                }
            }
        }

		return $data;
	}

	public function getUserEmail($userID){

		$this->userID = $userID;
		$this->emailAddress = $this->DatabaseConnection->getUserEmail($this->userID);
		return $this->emailAddress;
	}


}

?>