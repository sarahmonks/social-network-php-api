<?php
/*
 * A class which contains all methods for retrieving and inserting data to our application database.
 * We will use prepared statements for more security.
 * 
 */
class DatabaseConnection{
    private $query;
    private $statement;
    private static $instance = null;	
    private $pdoConnection;
    private $ErrorHandler;
    private $Validator;

    public function __construct(){
        $this->ErrorHandler = new ErrorHandler("DatabaseConnection");
        try{
            //construct a new PDO object.
            $this->pdoConnection = new PDO(HOSTDBNAME, USER, PASSWORD);
            $this->pdoConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->pdoConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            //set the character set for more security.
            $this->pdoConnection->exec("SET CHARACTER SET utf8mb4");
        }catch(PDOException $e){ 
        	$this->ErrorHandler->createLogEntry("construct", $e->getMessage());
            throw new Exception("Error connecting to database ");
        }
        $this->Validator = new Validator();
    }

    public static function getInstance(){
        if(!isset(self::$instance)){ // Check if the instance is not set.
            //create a new DatabaseConnection instance which will execute the code in the above constructor.			
            self::$instance = new DatabaseConnection();	
        }
        return self::$instance;	
	}

   
    public function checkEmailForLogin($emailAddress){
        /* 
         * This method takes in an emailAddress as a parameter and checks if it exists in the users table of the database.
         * It will return an array containing a key called: emailExists (boolean)
         * If the 'emailExists' boolean is true then we know that the method will have also returned keys: userID and hashPassword.
         * We will use this method during user authentication process (i.e the authenticateUser method of the User class).
         * Note: SQL syntax is case insensitive so it should match the email regardless of the case.
         */
        $data = array();

        try{
            $this->query ="SELECT * FROM users WHERE emailAddress = :emailAddress LIMIT 1";
            $this->statement = $this->pdoConnection->prepare($this->query);
            $this->statement->bindValue(':emailAddress', $emailAddress, PDO::PARAM_STR); 
            $this->statement->execute();
            $this->statement->setFetchMode(PDO::FETCH_ASSOC);
            $count = 0;
            while($row = $this->statement->fetch()){
                $data['userID'] = $row['userID'];
                $data['hashedPassword'] = $row["password"];
                $count++;
            }
            if($count > 0){
                //We have found the email address in the users table so set $data['emailExists'] to true.
                //Note that if $data['emailExists'] is true then we will also be returning $data['userID'] and $data['hashedPassword'] variables
                //from this method as the while loop above would have executed.
                $data['emailExists'] = true;
            }else{
                $data['emailExists'] = false;
            }
            return $data;
        }catch(PDOException $e){
            //log the error for our own records and throw an exception
            $this->ErrorHandler->createLogEntry("checkEmailForLogin", $e->getMessage());
            throw new Exception("Error checking email address!"); 
        }   
    }

    public function checkBrute($userID) {
       /* 
        * This method takes in a userID as a parameter.
        * The purpose of this method is to check if a user has made more than 8 failed login attempts in the last 20 minutes.
        * This is important to check in order to prevent Brute force attacks.
        * We will use this method during user authentication process (i.e the authenticateUser method of the User class).
        */ 
        $now = time(); //Get timestamp of current time 
    
        //All login attempts are counted from the past 20 minutes. 
        $validAttempts = $now - (20 * 60);
        try{
            //count the number of previous failed login attempts for that user
            $this->query = "SELECT count(loginAttemptID) FROM login_attempts WHERE userID = :userID AND loginTime > :validAttempts";
            $this->statement = $this->pdoConnection->prepare($this->query);
            $this->statement->bindValue(':userID', $userID, PDO::PARAM_INT); 
            $this->statement->bindValue(':validAttempts', $validAttempts, PDO::PARAM_INT); 
            $this->statement->execute();
            $count = $this->statement->fetchColumn(0); //store the count into local variable

            //If there have been more than 8 failed logins return true
            if($count > 8){
                return true;
            }else{
                return false;
            }
            
        }catch(PDOException $e){
            //log the error for our own records and throw an exception
            $this->ErrorHandler->createLogEntry("checkBrute", $e->getMessage());
            throw new Exception("Error checking user credentials!"); 
        }
    }
    public function recordFailedLoginAttempt($userID) {
       /* 
        * This method takes in a userID as a parameter.
        * The purpose of this method is to keep a record of failed login attempts (in the login_attempts table).
        * We will use this method during user authentication process (i.e the authenticateUser method of the User class)
        * if the credentials entered by the user in the login process are incorrect.
        */ 
        try{
            $now = time();
            $this->query ="INSERT INTO login_attempts (userID, loginTime) VALUES (:userID, :now)";
            $this->statement = $this->pdoConnection->prepare($this->query);
            $this->statement->bindValue(':userID', $userID, PDO::PARAM_INT); 
            $this->statement->bindValue(':now', $now, PDO::PARAM_STR); 
            $this->statement->execute();

        }catch(PDOException $e){
            $this->ErrorHandler->createLogEntry("recordFailedLoginAttempt", $e->getMessage());
            throw new Exception("Error checking user credentials!"); 
        }
    }

    public function checkEmailExists($emailAddress){
        /* 
         * This method takes in an emailAddress as a parameter and checks if it exists in the users table of the database.
         * It will return true or false if the email exists or not respectively.
         * We will use this during user registration before inserting the user's details to the users table.
         */
        $emailExists = false; 
        try{      
            $this->query ="SELECT COUNT(userID) FROM users WHERE emailAddress=:emailAddress";
            $this->statement = $this->pdoConnection->prepare($this->query);
            $this->statement->bindValue(':emailAddress', $emailAddress, PDO::PARAM_STR);
            $this->statement->execute();  
            $count = $this->statement->fetchColumn(0);
            if($count > 0){
                //email exists. 
                $emailExists = true; 
            }
            return $emailExists;

        }catch(PDOException $e){
            $this->ErrorHandler->createLogEntry("checkEmailExists", $e->getMessage());
            throw new Exception("Error checking email!"); 
        }  
    }

    public function insertUser($emailAddress, $password, $username){
        /* 
         * This method takes in an emailAddress, password, username as parameters and inserts a row into the users table of the database.
         * It will return a userID if successfully inserted. userID value will be null if unsuccessful.
         */
        //initialize userID to null
        $userID = null;
        try{
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
            $this->query = "INSERT INTO users (emailAddress, password, username) VALUES (:emailAddress, :password, :username)";
            $this->statement = $this->pdoConnection->prepare($this->query);
            $this->statement->bindValue(':emailAddress', $emailAddress, PDO::PARAM_STR);
            $this->statement->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
            $this->statement->bindValue(':username', $username, PDO::PARAM_STR);

            $this->statement->execute();
            $userID = $this->pdoConnection->lastInsertId();
            return $userID;
        }catch(PDOException $e){
            $this->ErrorHandler->createLogEntry("insertUser", $e->getMessage());
            throw new Exception("Error registering user in database"); 
        }
    }
    
     public function getUserProfile($userID){
        /* 
         * This method takes in a userID as a parameter and gets that user's profile data from the users table in the database
         */
        //declare an array called userDetails.
        $userDetails = array();
        try{
            $this->query ="SELECT userID, emailAddress, firstName, lastName, username, profileImgUrl, coverImgUrl, bio FROM users WHERE userID = :userID LIMIT 1";
            $this->statement = $this->pdoConnection->prepare($this->query);
            $this->statement->bindValue(':userID', $userID, PDO::PARAM_INT); 
            $this->statement->execute();
            $this->statement->setFetchMode(PDO::FETCH_ASSOC);
            $count = 0;
            while($row = $this->statement->fetch()){
                //store the data from the result of the query into the userDetails array. (Note: It will be an associative array.) 
                $userDetails = $row;
                $count++;
            }
            if($count > 0){
                return $userDetails;
            }else{
                //The userID was not found in the users table.
                //create a log entry to record this for our own records and throw an exception
                $this->ErrorHandler->createLogEntry("getUserProfile", "This userID did not return any rows");
                throw new Exception("User profile not found.");
            }   
        }catch(PDOException $e){
            //create a log entry to record the error message for our own records
            $this->ErrorHandler->createLogEntry("getUserProfile", $e->getMessage());
            throw new Exception("Error getting user profile");
        }   
    }


    public function getUserEmail($userID){
        /* 
         * This method takes in a userID as a parameter and gets that user's emailAddress from the users table in the database
         */

        $userEmail = '';
        try{
            $this->query ="SELECT emailAddress FROM users WHERE userID = :userID LIMIT 1";
            $this->statement = $this->pdoConnection->prepare($this->query);
            $this->statement ->bindValue(':userID', $userID, PDO::PARAM_INT); 

            $this->statement ->execute();
            $this->statement ->setFetchMode(PDO::FETCH_ASSOC);
            $count = 0;
            while($row = $this->statement ->fetch()){
                $userEmail = $row['emailAddress'];
                $count++;
            } 
            if($count > 0){
                return $userEmail;
            }else{
                //The userID was not found in the users table.
                //create a log entry to record this for our own records and throw an exception
                $this->ErrorHandler->createLogEntry("getUserEmail", "This userID did not return any rows");
                throw new Exception("User was not found.");
            }           
        }catch(PDOException $e){ 
            $this->ErrorHandler->createLogEntry("getUserEmail", $e->getMessage());
            throw new Exception("Error getting user email");
        }   
    }
    public function getUserPosts($userID){
        /* 
         * This method gets all posts from the posts table for a particular user.
         * We user ORDER BY postID descending so that the most recent post created will be at the start of the array that is output.
         */
        $postsData = array();
        try{
            $this->query ="SELECT * FROM posts WHERE postUserID = :userID ORDER BY postID DESC";
            $this->statement = $this->pdoConnection->prepare($this->query);
            $this->statement ->bindValue(':userID', $userID, PDO::PARAM_INT); 
            $this->statement->execute();
            $this->statement->setFetchMode(PDO::FETCH_ASSOC);
           
            while($row = $this->statement->fetch()){
                //push the data from the result of the query into the postsData array (This will be an associative array). 
                array_push($postsData, $row);
            }
            return $postsData;
               
        }catch(PDOException $e){
            //create a log entry to record the error message
            $this->ErrorHandler->createLogEntry("getUserPosts", $e->getMessage());
            throw new Exception("Error getting posts");
        }   
    }
    public function getPostLikes($postID){
        /* 
         * This method gets all likes from the post_likes table for a particular post
         */
        $postLikesData = array();
        try{
            $this->query ="SELECT DISTINCT users.userID, users.username, users.firstName, users.lastName, users.profileImgUrl FROM post_likes, users WHERE post_likes.postID = :postID AND users.userID=post_likes.userID";
            $this->statement = $this->pdoConnection->prepare($this->query);
            $this->statement ->bindValue(':postID', $postID, PDO::PARAM_INT); 
            $this->statement->execute();
            $this->statement->setFetchMode(PDO::FETCH_ASSOC);
           
            while($row = $this->statement->fetch()){
                //store the data from the result of the query into an associative array. 
                array_push($postLikesData, $row);
            }
            return $postLikesData;
               
        }catch(PDOException $e){
            //create a log entry to record the error message
            $this->ErrorHandler->createLogEntry("getPostLikes", $e->getMessage());
            throw new Exception("Error getting post likes");
        }   
    }


    public function getComments($postID){
        /* 
         * This method gets the comments of a particular post from the comments table 
         */
        $commentsData = array();
        try{
            $this->query ="SELECT * FROM comments WHERE postID = :postID ORDER BY commentID ASC";
            $this->statement = $this->pdoConnection->prepare($this->query);
            $this->statement ->bindValue(':postID', $postID, PDO::PARAM_INT); 
            $this->statement->execute();
            $this->statement->setFetchMode(PDO::FETCH_ASSOC);
           
            while($row = $this->statement->fetch()){
                //store the data from the result of the query into an associative array. 
                array_push($commentsData, $row);
            }
            return $commentsData;
               
        }catch(PDOException $e){
            //create a log entry to record the error message
            $this->ErrorHandler->createLogEntry("getComments", $e->getMessage());
            throw new Exception("Error getting comments");
        }   
    }

    public function checkIfUserLikesPost($userID, $postID) {
       /* 
        * This method checks if a user likes a particular post.
        * We take in a userID and a postID and check if this combination of values exist together in the post_likes table.
        */ 
        $userLikesPost = false;
        try{
            //count the number of entries where userID = $userID and postID = $postID
            //There should be no more than 1 but there may be 0.
            $this->query = "SELECT count(postLikeID) FROM post_likes WHERE userID = :userID AND postID = :postID";
            $this->statement = $this->pdoConnection->prepare($this->query);
            $this->statement->bindValue(':postID', $postID, PDO::PARAM_INT);      
            $this->statement->bindValue(':userID', $userID, PDO::PARAM_INT); 
            $this->statement->execute();
            $count = $this->statement->fetchColumn(0); //store the count into local variable
            if($count > 0){
              //There is at least one entry in the table where the userID and the postID (we took in as parameters) exist together.
              //therefore the user with userID = $userID likes post with postID = $postID.
              $userLikesPost = true;
            }
           return $userLikesPost;
            
        }catch(PDOException $e){
            $this->ErrorHandler->createLogEntry("checkIfUserLikesPost", $e->getMessage());
            throw new Exception("Error checking if user likes post!"); 
        }
    }


    public function likePost($userID, $postID){
        /*
         * This method takes in a userID and a postID and inserts a row to the post_likes table.
         * We return the ID of the row it was inserted to.
         */
        try{
            $this->query  = "INSERT INTO post_likes (userID, postID) VALUES (:userID, :postID)";
            $this->statement = $this->pdoConnection->prepare($this->query);
            $this->statement->bindValue(':postID', $postID, PDO::PARAM_INT);      
            $this->statement->bindValue(':userID', $userID, PDO::PARAM_INT); 
            $this->statement->execute();
            $postLikeID = $this->pdoConnection->lastInsertId();    
            return $postLikeID;
        }catch(PDOException $e){
            //create a log entry to record the error message
            $this->ErrorHandler->createLogEntry("likePost", $e->getMessage());
            throw new Exception("Error adding like");
        }
    }
    public function unlikePost($userID, $postID){
        /*
         * This method takes in a userID and a postID and deletes the corresponding row of the post_likes table.
         */
        try{
            $this->query = "DELETE FROM post_likes WHERE postID = :postID AND userID = :userID";
            $this->statement = $this->pdoConnection->prepare($this->query);
            $this->statement->bindValue(':postID', $postID, PDO::PARAM_INT);      
            $this->statement->bindValue(':userID', $userID, PDO::PARAM_INT); 
            $this->statement->execute();
        }catch(PDOException $e){
            //create a log entry to record the error message
            $this->ErrorHandler->createLogEntry("unlikePost", $e->getMessage());
            throw new Exception("Error deleting like");
        }
    }

    public function getFriends($userID){
        /* 
         * This method takes in a userID and gets the "friends" of that user
         */
        $friendsData = array();
        try{
            $this->query ="SELECT DISTINCT users.userID, users.username, users.firstName, users.lastName, users.profileImgUrl FROM friendships, users WHERE friendships.userID = :userID AND users.userID=friendships.friendUserID";
            $this->statement = $this->pdoConnection->prepare($this->query);
            $this->statement ->bindValue(':userID', $userID, PDO::PARAM_INT); 
            $this->statement->execute();
            $this->statement->setFetchMode(PDO::FETCH_ASSOC);
           
            while($row = $this->statement->fetch()){
                //store the data from the result of the query into an associative array. 
                array_push($friendsData, $row);
            }
            return $friendsData;
               
        }catch(PDOException $e){
            //create a log entry to record the error message
            $this->ErrorHandler->createLogEntry("getFriends", $e->getMessage());
            throw new Exception("Error getting friends");
        }   
    }

}
?>