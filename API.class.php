<?php
/**
 * This class will act as a wrapper class for all of the custom endpoints that our API will be using.
 * It takes in our request, grabs the endpoint from the URI string, detects the HTTP method and
 * assembles any additional data provided by the header or in the URI.
 * This class is also responsible for handling forming a HTTP response to return back to the client.
 */
abstract class API{
    /**
     * Property: method
     * The HTTP method this request was made in, either GET, POST, PUT or DELETE
     */
    protected $method = '';
    /**
     * Property: endpoint
     * The Model requested in the URI. eg: /files
     */
    protected $endpoint = '';
    /**
     * Property: verb
     * An optional additional descriptor about the endpoint, used for things that can
     * not be handled by the basic methods. eg: /files/process
     */
    protected $verb = '';
    /**
     * Property: args
     * Any additional URI components after the endpoint and verb have been removed, in our
     * case, an integer ID for the resource. eg: /<endpoint>/<verb>/<arg0>/<arg1>
     * or /<endpoint>/<arg0>
     */
    protected $args = Array();
    /**
     * Property: file
     * Stores the input of the PUT request
     */
    protected $file = Null;

    /**
     * Constructor: __construct
     * Allow for CORS, assemble and pre-process the data
     */
    public function __construct($request){
        /* 
         * This constructor takes in the $request variable which will be sent from the htaccess file.
         * It contains the original URI that the client requested.
         */

        header("Access-Control-Allow-Origin: *"); 
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
        header("Access-Control-Allow-Headers: content-type, if-none-match"); //for preflight (not sure if I need the if-none-match)

        header("Content-Type:application/json;charset=utf-8");
       // header("Access-Control-Max-Age: 3600");
        /* 
         * By exploding the request string at the slash we can grab the endpoint.
         * If applicable the next slot in the array will be the verb.
         * Any remaining items are the arguments.
         */
        $this->args = explode('/', rtrim($request, '/'));
        $this->endpoint = array_shift($this->args);
        if (array_key_exists(0, $this->args) && !is_numeric($this->args[0])){
            $this->verb = array_shift($this->args);
        }
        
        $this->method = $_SERVER['REQUEST_METHOD']; 
        if ($this->method == 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)){
            //DELETE and PUT requests are hidden inside a POST request through the use of the HTTP_X_HTTP_METHOD header.
            //So we check here if the method was DELETE or PUT
            //however after doing some tests on this with my angular application when doing a DELETE request, it is not disguised as a POST request
            //and was stored in the REQUEST_METHOD as DELETE so there was no need for the following, however it may be different in different browsers
            if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'DELETE'){
                $this->method = 'DELETE';
            } else if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'PUT'){
                $this->method = 'PUT';
            } else {
                throw new Exception("Unexpected Header");
            }
        }
        
        /*
         * Clean the inputs
         */
        switch($this->method){
        case 'DELETE':
        case 'POST':
            //we need to get the data the following way because we have the Content-Type header set to application/json, so $_POST will no longer be populated
            $rawPostData = file_get_contents('php://input');
            $json = json_decode($rawPostData);
            $this->request = $json;

            break;
        case 'GET':
            $this->request = $this->_cleanInputs($_GET);
            break;
        case 'PUT':
            $rawPostData = file_get_contents('php://input');
            $json = json_decode($rawPostData);
            $this->request = $json;
            break;
        case 'OPTIONS':
            break;
        default:
            $this->_response('Invalid Method', 405);
            break;
        }
    }


    public function processAPI(){
        /*
         * The purpose of this method is to determine if the concrete class implements a method for the endpoint that the client requested
         * If it does not, then we return a 404 response.
         */
        if (method_exists($this, $this->endpoint)){
            return $this->_response($this->{$this->endpoint}($this->args));
        }
        return $this->_response(array("error" => "No Endpoint : $this->endpoint"), 404);
    }

    private function _response($data, $status = 200){
        /*
         * This method handles returning the response
         */
        header("HTTP/1.1 " . $status . " " . $this->_requestStatus($status));
        return json_encode($data);
    }

    private function _cleanInputs($data){
         /*
          * This method is for cleaning our inputs.
          */
        $clean_input = Array();
        if (is_array($data)){
            foreach($data as $k => $v){
                $clean_input[$k] = $this->_cleanInputs($v);
            }
        } else {
            $clean_input = trim(strip_tags($data));
        }
        return $clean_input;
    }

    private function _requestStatus($code){
        $status = array(  
            200 => 'OK',
            404 => 'Not Found',   
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
        ); 
        return ($status[$code])?$status[$code]:$status[500]; 
    }
}
?>