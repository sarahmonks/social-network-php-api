<?php
/*
 * A class for working with the One Signal API.
 * Whenever we want to generate a notification to send to the client side app we need to make a POST request to the One Signal
 * API with an object in the following format.
 * { "app_id" : "YOURAPPID", 
 *   "include_player_ids": [""], 
 *   "headings" : {"en" : "My title"}, 
 *   "contents": {"en" : "Some content"}, 
 *   "data" : {"action" : "openPage", "id" : "42"}
 * }
 * One Signal then sends a message to the ios/Android servers and these servers send a push to our device.
 * This class contains the necessary data and methods to make this POST request.
 * In our client side app this data can be retrieved as follows:
 * data.notification.payload.body;
 * data.notification.payload.title;
 * data.notification.payload.additionalData["action"];
 * data.notification.payload.additionalData["id"];
 */

class Notification{
	protected $oneSignalNotificationID;
  protected $url = "https://onesignal.com/api/v1/notifications"; //the url where the POST request will be sent
  protected $appID = ONESIGNALAPPID; //our one signal app ID which we received when we set up our app on onesignal.com
  protected $headings = array(); //the headings of the notification to display to user
  protected $contents = array(); //the contents of the notification to display to user
  protected $additionalData = array(); //an array of additional data to send with the notification to use in our client side code.
  protected $playerIDs = array(); //an array of recipients IDs.
  protected $fields = array(); //the fields to be sent in the POST request
  protected $numberOfRecipients = null;

	public function __construct(){

	}
  public function generateNotification($headings, $contents, $additionalData, $playerIDs){
    $fields = array(
      'app_id' => $this->appID,
      'include_player_ids' => $playerIDs,
      'contents' => $contents,
      'headings' => $headings,
      'data' => $additionalData
    );

    $fields = json_encode($fields);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    //as a response we get the id of the notification and also how many recipients there were. 
    //e.g {"id":"d92b9a83-fe0b-4ad9-9121-159a01fefd32","recipients":1,"external_id":null}
    //we may want to create a log file to log theses responses
    $response = curl_exec($ch);
    //in order to convert the response string to an array we do the following:
    $responseArray = json_decode($response);  
    foreach($responseArray as $obj){ 
     // var_dump($obj);
      $filteredArray[] = $obj;
    }
    //the one signal notificationID will be the first element of the array
    $this->oneSignalNotificationID = $filteredArray[0];
    //the number of recipients will be the second element of the array
    $this->numberOfRecipients = $filteredArray[1];
    
    //$error = json_encode(curl_getinfo($ch)); //json_encode(curl_getinfo($ch)) json_encode(curl_errno($ch)) json_encode(curl_error($ch));
    curl_close($ch);
    return $this->oneSignalNotificationID;
  }

}

?>