<?php
/*contains object definitions to be used in checking that a provided link is valid*/
class New_link{
  var $URL; //$URL to be validated
//properties of object

public function is_valid_link($aURL){
  $this->URL = $aURL;
  if($this->URL != ''){
    $URL_handler = new URL_handler();
    $URL_handler->f_URL = $aURL;
    $patched_URL = $URL_handler->url_patchr($aURL);
    $link_host = parse_url($patched_URL, PHP_URL_HOST);
    if($link_host != ''){
        return $result = 'OK 1';
    }
    else{return $result = 'You did not enter any URL';}
  }
  else{return $result = 'Please enter a valid URL';}
}




public function is_a_link($aURL){
  $this->URL = $aURL;
//checks if a link is an actual URL or not.
  if(count(explode(" ", $this->URL)) == 1){
    return $result = 'OK 2';
  }
  else {return $result = 'bad URL';}
}



public function check_exists($aURL, $mysqli){
  if (mysqli_connect_errno()) {
      printf("Connect failed: %s\n", mysqli_connect_error());
      exit();
  }
  else{
    $this->URL = $aURL;
    $URL_handler = new URL_handler();
    $URL_handler->f_URL = $aURL;
    $stripped_URL = $URL_handler->http_strippr($aURL);
    $sanitized_long_link = addslashes($stripped_URL);
    $myquery = "SELECT * FROM link_mapping where long_link = '$sanitized_long_link'";
    $sql = $mysqli -> query($myquery);
    $num = $sql -> num_rows;
    if($num == 0){
      return $result = 'Does not exist 0';//implies record does not already exist
    }
    else if($num > 0){
      $row = $sql -> fetch_array(MYSQLI_NUM);
      $result = 'Copy and paste <b>localhost/link_shortner/index.php?'.$row[2].'</b> in a new tab within your broswer to the visit site using short link.';
      return $result;
    }
  }
}



public function is_a_live_link($aURL){
  $this->URL = $aURL;
  // checks to see if the link is reachable on the net to ensure fake links are not inserted\
  $result = 'link unreachable';
  if(gethostbyname(parse_url($this->URL, PHP_URL_HOST)) != '' && count(explode(".", gethostbyname(parse_url($this->URL, PHP_URL_HOST)))) == 4){
    $result = '';
    $result = 'OK 3';
    return $result;
  }
  else{
    return $result;
  }
}
}

?>
