<?php
/*
This page is the landing page:
It collects the input string and matches it with a database long link which it redirects to
It also accepts a long link and generates a unique short link which it can be matched to
*/
error_reporting(E_ALL);
$message = '';
if($_SERVER['QUERY_STRING'] != "" && count(str_split($_SERVER['QUERY_STRING'])) == 6 && !(isset($_GET['submit']))) {
include_once("./hit_type.php"); //check if a shortlink was used
}
else{
  if(count(str_split($_SERVER['QUERY_STRING'])) > 6 && !(isset($_GET['submit']))) {
    $message .= count(str_split($_SERVER['QUERY_STRING'])).' is greater than the expected string length of 6 <br />';
    $message .= $_SERVER['SERVER_NAME'];
  }
else if(count(str_split($_SERVER['QUERY_STRING'])) < 6 && !(isset($_GET['submit'])) && $_SERVER['QUERY_STRING'] != "") {
    $message .= count(str_split($_SERVER['QUERY_STRING'])).' is less than the expected string length of 6 <br />';
    $message .= $_SERVER['SERVER_NAME'];
  }
}



if (isset($_GET['submit'])) {
  include_once("./includes/connect.php");
  include_once("./includes/validator.php");
  include_once("./includes/atomizer.php");
  include_once("./includes/url_patch.php");
  $Link = new New_link();
  $Link->URL = $_GET['URL']; //sets the variable inside the class with the inputted URL string
  if($Link->is_valid_link($_GET['URL']) == 'OK 1'){
    if($Link->is_a_link($_GET['URL']) == 'OK 2'){
        if($Link->is_a_live_link($_GET['URL']) == 'OK 3'){
          if($Link->check_exists($_GET['URL'], $mysqli) == 'Does not exist 0'){
            //var_dump($Link->check_exists($_GET['URL'], $mysqli));
          $dataset = array();
          $modify_URL = new atomize();
          // $modify_URL->XXI_free_URL($_GET['URL']);
          $dataset = array();
          $dataset[0] = $modify_URL -> XXI_free_URL($_GET['URL']); //long link filtered for cross-scripting and stripped of security type
          $dataset[1] = $modify_URL -> shortfier_ID(6,$mysqli); //short link
          $dataset[2] = gethostbyname(parse_url($_GET['URL'], PHP_URL_HOST)); //ip
          if($modify_URL->mapper($dataset,$mysqli) == 1){
            $message .= 'Short link was successfully generated. ';
            $message .= 'Copy and paste <b>localhost/link_shortner/index.php?'.$dataset[1].'</b> in a new tab within your broswer to the visit site using short link.';
          }
        }
        else {$message = $Link->check_exists($_GET['URL'], $mysqli);}
      }
      else{$message = $Link->is_a_live_link($_GET['URL']);}
    }
    else{$message = $Link->is_a_link($_GET['URL']);}
  }
  else{$message = $Link->is_valid_link($_GET['URL']);}
}
?>



<html>

<head>
  <meta charset="UTF-8">
  <link href="https://fonts.googleapis.com/css?family=Fira+Sans:400,400i,500,700" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="css/prism.css">
  <link rel="stylesheet" type="text/css" href="css/style.css">
  <link rel="stylesheet" type="text/css" href="css/loading.css">
  <title>Link Shortner Demo</title>
</head>

<body>

<div align="center">
  <form align='center' action="index.php" method="get">
    <input type="textbox" name= "URL"  value = ""/>
    <input type="submit" name="submit" value = "Shortify My Link" />
  </form>
</div>
<p><div align="center"> <?PHP print $message; ?> </div></p>

</body>

</html>
