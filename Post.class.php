<?php

class Post{
	
	private $DatabaseConnection;// stores the database handler
  private $ErrorHandler;
  private $Validator;
  protected $postID; 
  protected $postTime;    // using protected so they can be accessed
  protected $postText; // and overidden if necessary
  protected $postType;
  protected $photoLink;
  protected $postUserID;
  protected $postLikesData = array();


	public function __construct(){
		$this->DatabaseConnection = DatabaseConnection::getInstance();
    $this->ErrorHandler = new ErrorHandler("Post");
    $this->Validator = new Validator();
    $this->Comment = new Comment();
    $this->User = new User();
	}
	public function getUserPosts($userID){ 
    date_default_timezone_set('Europe/London');
    $postsData = array();
	  $this->postUserID = $userID;
    foreach($this->DatabaseConnection->getUserPosts($userID) as $key => $value){  
      //The post user data only needs to contain the userID, firstName and lastName so 
      //we call the getUserProfile method and use it below
      $postUserData = $this->User->getUserProfile($value['postUserID']);
        
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
    $userFriendsPosts = array();
    foreach($this->User->getUserFriends($userID) as $key => $value){  
      foreach($this->DatabaseConnection->getUserPosts($value['userID']) as $key => $value){  
        //The post user data only needs to contain the userID, firstName and lastName so 
        //we call the getUserProfile method and use it below
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
    $this->postID = $postID;
    $this->postLikesData = $this->DatabaseConnection->getPostLikes($postID);
    return $this->postLikesData;
  }

  public function likePost($userID, $postID){
    /*
     */
    $postLikesArray = array();
    $this->postID = $postID;

    $userLikesPostAlready = $this->DatabaseConnection->checkIfUserLikesPost($userID, $this->postID);
    if(!$userLikesPostAlready){
      $this->DatabaseConnection->likePost($userID, $this->postID);
    }
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
    $this->DatabaseConnection->unlikePost($userID, $this->postID);
    $postLikesData = $this->DatabaseConnection->getPostLikes($this->postID);
    return $postLikesData;
  }


}

?>