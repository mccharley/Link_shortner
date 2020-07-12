<?php

class URI_redirector{
  public function redirector($reallink){
    if (!empty($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS'])) {
		    $uri = 'https://';
	   }
  else {
		$uri = 'http://';
	   }
    //$reallink = stripcslashes($reallink);
    header('Location: '.$uri.'/'.$reallink);
  }
}
?>
