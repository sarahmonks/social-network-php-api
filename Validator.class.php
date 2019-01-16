<?php
/*
 * A class for validating user input or input coming from the client side
 */
class Validator{

    protected $ErrorHandler;

    public function __construct(){
        $this->ErrorHandler = new ErrorHandler("Validator");
    }

    public function checkForValidEncoding($input){
        /*
         * This method takes in an input value and checks if the encoding is utf-8. 
         * We will use this in the checkInputIsValid method of this class.
         */
        $isValidEncoding = mb_check_encoding($input, 'UTF-8');
        return $isValidEncoding;
    }	
	
    public function checkLengthIsValid($inputArray){
        /* 
         * This method takes in an array which consists of an input value and a minimum and maximum length allowed for that value.
         * It returns whether the length of the input is valid or not.
         * We will use this in the checkInputIsValid method of this class.
         */
        $inputLength = mb_strlen($inputArray["input"], 'utf8');
        if($inputLength >= $inputArray["minLength"] && $inputLength <= $inputArray["maxLength"]){ 
       
            //The length of the input is valid.
            return true;		
        }else{
         
            return false;	
        }
    }

    public function checkInputIsValid($inputArray){
        /*
         * This method takes in an array which consists of an input value and a minimum and maximum length allowed for that value.
         * It checks if the encoding and the length of the input are valid 
         * using the checkForValidEncoding() and checkLengthIsValid() methods from this class
         */
        $isValidEncoding = $this->checkForValidEncoding($inputArray["input"]);
        if($isValidEncoding){
            //input is valid utf-8 encoding.
            //Now check the length of the input.
            $isValidLength = $this->checkLengthIsValid($inputArray);
            if($isValidLength){
                return true;
            }else{
              
                return false;
            }
        }else{
         
            //input is not valid utf-8 encoding.
            return false;
        }
    }

    public function checkInputsAreValid($inputsArray){
        /*
         * This method takes in a multidimensional array. 
         * Each array within the outer array consists of an input value and a minimum and maximum length allowed for that value.
         * It calls the checkInputIsValid() method on each array to check if the encoding and the length of the input are valid.
         * It returns a boolean value indicating whether all inputs are valid or not.
         */
        $inputsAreValid = true;
        foreach($inputsArray as $key => $inputArray){	
            $inputIsValid = $this->checkInputIsValid($inputArray);
            if($inputIsValid){ 
            }else{
                //This input is invalid therefore indicate that all inputs of this array are invalid.
                $inputsAreValid = false;		
            }
        }
        return $inputsAreValid;	
    }

    public function filterInputField($inputArray){
        /* 
         * This method takes in an array which consists of an input value (from a form) and a filter method (depending on the type of input)
         * and returns the filtered input.
         */
        $input = filter_var($inputArray["input"], $inputArray["filterMethod"]); 
        $input = trim(htmlspecialchars(strip_tags(stripslashes($input))));
        return $input;
    }


    public function filterInputs($inputsArray){
        /* 
         * This method takes in a multidimensional array. 
         * Each array within the outer array consists of an input value (from a form) and a filter method (depending on the type of input)
         * and returns an array of filtered inputs.
         */
        $filteredInputs = array();
        foreach($inputsArray as $key => $inputArray){	
            $filteredInputs[$key] = $this->filterInputField($inputArray);
        }
        return $filteredInputs;
    }

    public function cleanUserInput($data){
        /* 
         * This method takes in user input and filters it for display within the application
         * It is currently used for filtering a user review in the Review class
         */
        $data = htmlentities(strip_tags(stripslashes($data)));
        return $data;
    }

}

?>