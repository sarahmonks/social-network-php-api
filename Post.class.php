<?php
/*
 * A class for working with post data (i.e posts created by users within the application).
 */
class Post{
	
  private $DatabaseConnection;// stores the database handler
  private $ErrorHandler;
  private $Validator;
  //using protected so they can be accessed and overidden if necessary
  protected $postID; 
  protected $postTime; //each post will have a time when the post was created. This is stored as a unix timestamp in the database
  protected $postText; //each post will have text
  protected $postType; //each post will have a type which will either be "status", "photo" or "video"
  protected $photoLink; 
  protected $postUserID;
  protected $postLikesData = array();


  public function __construct(){
    $this->DatabaseConnection = DatabaseConnection::getInstance();
    $this->ErrorHandler = new ErrorHandler("Post");
    //Create an instance of the Validator class so we can validate the postText so it is more secure.
    $this->Validator = new Validator();
    //create an instance of the Comment class so we can get the comments for each post
    $this->Comment = new Comment(); 
    //create an instance of the User class so we can get user data for each post
    $this->User = new User();
  }


  public function getUserPosts($userID){ 
    /*
     * This method takes in a $userID and gets all the "posts" created by that user.
     * The first element of the posts array will contain the most recent post that the user created.
     *
     */
    //set the timezone to be Europe/London.
    date_default_timezone_set('Europe/London'); //will have to change this timezone if there are international users of the app.
    $postsData = array();
    //set the postUserID property to be the userID we took in as a parameter.
    $this->postUserID = $userID;
    //Pass in the userID to the getUserPosts method of the DatabasConnection class 
    foreach($this->DatabaseConnection->getUserPosts($userID) as $key => $value){  
      //The postUserData only needs to contain the userID, firstName and lastName so 
      //we call the getUserProfile method now storing the result in $postUserData and then filter it below
      $postUserData = $this->User->getUserProfile($value['postUserID']);
      //push the filtered data into the postData array which is the array we will return from this method.  
      array_push(
        $postsData, array(
          'postID' => $value['postID'],
          'postUserData' => 
                array('userID' => $postUserData['userID'], 
                    'firstName' => $postUserData['firstName'], 
                    'lastName' => $postUserData['lastName'],
                    'profileImgUrl' => $postUserData['profileImgUrl']),
          'postText' => $this->Validator->cleanUserInput($value['postText']),
          'postTime' => date("jS F Y H:i", $value['postTime']), 
          'postType' => $value['postType'],
          'photoLink' => "http://localhost/my_app_images/" . $value['photoLink'],
          'postLikes' => $this->DatabaseConnection->getPostLikes($value['postID']),
          'comments' => $this->Comment->getComments($value['postID'])
        )
      ); 
    }
    return $postsData;
  }

  public function getUserFriendsPosts($userID){ 
    /*
     * This method takes in a $userID and gets all the most recent "posts" of the friends of that user.
     *
     */
    date_default_timezone_set('Europe/London');
    $userFriendsPosts = array();
    //firstly get the list of friends of this user.
    foreach($this->User->getUserFriends($userID) as $key => $value){  
      //Now pass in the userID of each friend into the following getUserPosts method 
      foreach($this->DatabaseConnection->getUserPosts($value['userID']) as $key => $value){  
        //The postUserData only needs to contain the userID, firstName and lastName so 
        //we call the getUserProfile method now storing the result in $postUserData and then filter it below
        $postUserData = $this->User->getUserProfile($value['postUserID']);
        array_push(
          $userFriendsPosts, array(
            'postID' => $value['postID'],
            'postUserData' => 
                array('userID' => $postUserData['userID'], 
                    'firstName' => $postUserData['firstName'], 
                    'lastName' => $postUserData['lastName'],
                    'profileImgUrl' => $postUserData['profileImgUrl']),
            'postText' => $this->Validator->cleanUserInput($value['postText']),
            'postTime' => date("jS F Y H:i", $value['postTime']), 
            'postType' => $value['postType'],
            'photoLink' => "http://localhost/my_app_images/" . $value['photoLink'],
            'postLikes' => $this->DatabaseConnection->getPostLikes($value['postID']),
            'comments' => $this->Comment->getComments($value['postID'])
          )
        ); 
      }
    }
    return $userFriendsPosts;
  }

  public function getPostLikes($postID){
    /*
     * This method takes in a $postID and gets the postLikes data for that post.
     * postLikes data will be an array of data related to the users who have "liked" this post.
     */
    $this->postID = $postID;
    $this->postLikesData = $this->DatabaseConnection->getPostLikes($postID);
    return $this->postLikesData;
  }

  public function likePost($userID, $postID){
    /*
     * This method takes in a $userID, $postID and calls the likePost method from the DatabaseConnection class.
     * We return the postLikes array for this post.
     */
    $postLikesArray = array();
    $this->postID = $postID;
    //Firstly check it the user currently likes this post.
    $userLikesPostAlready = $this->DatabaseConnection->checkIfUserLikesPost($userID, $this->postID);
    if(!$userLikesPostAlready){
      //if this entry does not already exist in the database then we create it here by calling the likePost method of the DatabaseConnection class.
      $this->DatabaseConnection->likePost($userID, $this->postID);
    }
    //get the new postLikes data for this post. 
    $postLikesArray = $this->DatabaseConnection->getPostLikes($this->postID);
    return $postLikesArray;
  }
  
  public function unlikePost($userID, $postID){
    /*
     * This method takes in a $userID, $postID and calls the unlikePost method from the DatabaseConnection class.
     * We return the postLikes array for this post.
     */
    $postLikesData = array();
    $this->postID = $postID;
    //call the unlikePost method in order to delete the entry of the post_likes table which has userID=$userID and postID=$postID
    $this->DatabaseConnection->unlikePost($userID, $this->postID);
    //get the new postLikes data for this post. (As it will be different now that we have deleted one entry)
    $postLikesData = $this->DatabaseConnection->getPostLikes($this->postID);
    return $postLikesData;
  }


}

?>