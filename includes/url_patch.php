<?php

class URL_handler{
  //removes the http:// and https:// prefix from url string
  var $f_URL; //$URL to be validated
  public $stripped_URL;
  public $http_prifix;

  public function http_strippr($f_URL){
    $len = strlen($f_URL); //lenght of URL
    if(substr("$f_URL", 0, 8) == "https://"){
      $stripped_URL = substr("$f_URL", 8, $len);
      }
    else if(substr("$f_URL", 0, 7) == "http://"){
      $stripped_URL = substr("$f_URL", 7, $len);
      }
    else{
      $stripped_URL = $f_URL; //incase user enters URL without http or https definition
      }
  return $stripped_URL;
  }


public function url_patchr($f_URL){
  //adds the http:// or http://www. prefix to url string
    $len = strlen($f_URL); //lenght of URL
    $http_prifix = 'http://';

    if(substr("$f_URL", 0, 8) != "https://" or substr("$f_URL", 0, 7) != "http://"){ //if either $a or $b is TRUE but not both true at same time.
      $http_prifix = 'http://www.';
      if(substr("$f_URL", 0, 12) != "https://www." or substr("$f_URL", 0, 11) != "http://www."){
      $http_prifix .= $f_URL; //concatinated string
        }
      else{
        $http_prifix .= $f_URL; //concatinated string
        }
      }
    else{
      $http_prifix = $f_URL; //incase user enters URL without http or https definition
      }
  return $http_prifix;
  }
}



?>
