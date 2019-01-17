<?php
/*
 * A class for working with Comment data (related to Posts).
 */
class Comment{
	
  private $DatabaseConnection;//stores the database handler
  private $ErrorHandler;
  private $Validator;
  //we use protected so the properties can be accessed and overidden if necessary
  protected $commentID; 
  protected $commentTime;    
  protected $commentText; 
  protected $commentUserID; 
  protected $postID; //each comment will have a postID which will be the post the comment belongs to.
  protected $commentUserData; //each comment will have data related to the user who generated the comment
  protected $commentsData = array();

  public function __construct(){
    //Get an instance of the DatabaseConnection class.
    $this->DatabaseConnection = DatabaseConnection::getInstance();
    //Create an instance of the ErrorHandler class so we can log errors.
    $this->ErrorHandler = new ErrorHandler("Comment");
    //Create an instance of the Validator class so we can validate the commentText so it is more secure.
    $this->Validator = new Validator();
  }


  public function getComments($postID){ 
    /*
     * The purpose of this method is to get all comments (from the database) of a particular post with an ID of postID.
     * We will not need the full user profile data for comments so we leave out certain properties like bio and coverImgUrl etc.
     */
    //Define the current timezone.
    //Note: we will have to change this timezone if there are international users of the app.
    date_default_timezone_set('Europe/London'); 
    $this->postID = $postID;
    $commentsData = array();
    foreach($this->DatabaseConnection->getComments($postID) as $key => $value){  
      array_push(
        $commentsData, array(
          'commentID' => $value['commentID'],
          'commentText' => $this->Validator->cleanUserInput($value['commentText']),
          'commentTime' => date("jS F Y H:i", $value['commentTime']), 
          'commentUserData' => $this->getCommentUserData($value['commentUserID']),
          'postID' => $value['postID']
        )
      ); 
    }
    return $commentsData;
  }


  public function getCommentUserData($commentUserID){ 
    /*
     * The purpose of this method is to prepare the user data associated with a comment on a post.
     * We will not need the full user profile data for comments so we leave out certain properties like bio and coverImgUrl etc.
     */
    //get all the user profile data for a particular comment.
    $userProfileData = $this->DatabaseConnection->getUserProfile($commentUserID);
    //only store the data that we need for the comments.
    $this->commentUserData['userID'] = $userProfileData['userID'];
    $this->commentUserData['firstName'] = $userProfileData['firstName'];
    $this->commentUserData['lastName'] = $userProfileData['lastName'];
    $this->commentUserData['username'] = $userProfileData['username'];
    $this->commentUserData['profileImgUrl'] = "http://localhost/my_app_images/" . $userProfileData['profileImgUrl'];
    return $this->commentUserData;
  }
}

?>