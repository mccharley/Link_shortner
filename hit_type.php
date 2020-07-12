<?php
if($_SERVER['QUERY_STRING'] != "" && count(str_split($_SERVER['QUERY_STRING'])) == 6 && !(isset($_GET['submit']))) {
  include_once("./includes/connect.php");
  include_once("redirect.php");
  //$query_string = addslashes($_SERVER['QUERY_STRING']);
  $query_string = $_SERVER['QUERY_STRING'];

  if (mysqli_connect_errno()) {
      printf("Connect failed: %s\n", mysqli_connect_error());
      exit();
  }
  else{
    //link sanitization is not done here however it is advised the script user do this to avoid SQL injection on their databases.
  $query_mapping = "SELECT * FROM link_mapping WHERE short_link_ID = '$query_string'";
  $sql = $mysqli -> query($query_mapping);
  if(!$sql){
    trigger_error('Invalid query: ' . $mysqli->error);
    exit();
    $message .= 'The shortlink <b>'."$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]".'</b> does not exist.';
    $message .= ' Please verify the link and try again';
      }
      else{
        $num = $sql->num_rows;
        if($num == 1) {
            $row = $sql -> fetch_array(MYSQLI_NUM);
            $longlink = stripcslashes($row[1]);
            $linkme = new URI_redirector;
            $linkme->redirector($longlink);
            exit;
      }
    }
  }
}
?>
