<?php

Class atomize{
  var $URL;
  var $str_entropy;
  var $entropy;

public function XXI_free_URL($URL){
  $URL_handler = new URL_handler();
  $URL_handler->URL = $_GET['URL'];
  $stripped_URL = $URL_handler->http_strippr($URL);
  $XXI_URL = addslashes($stripped_URL);
  return $XXI_URL;
  }



  
//string uniqueness checker
public function shortfier_ID($entropy,$mysqli) {
  $this -> entropy = $entropy;
  //6 character alphanumeric string generator
    function id_generator($entropy){
      $id = '';
      $alph = array_merge(range('a', 'z'), range('A', 'Z')); //generates 2 arrays of alphabets in lower case and upper case and merges then for form one array.
      $alph_len = count($alph);
      $i = 0;
        while($i < $entropy){
          $position = mt_rand(0,1);
          if($position == 0){
            $id .= $alph[mt_rand(0, ($alph_len - 1))];
          }
          else if($position == 1){
            $id .= mt_rand(0, 9);
          }
          $i++;
        }
    return $id;
    }

    if ($mysqli -> connect_errno) {
        printf("Connect failed: %s\n", mysqli_connect_error());
        exit();
    }
    else{
      //initial query
      $link_ID = id_generator($entropy);
      $myquery = 'SELECT * FROM link_mapping where short_link_ID = "$link_ID"';
      $sql = $mysqli -> query($myquery);
      if(!$sql){
        trigger_error('Invalid query: ' . $mysqli->error);
        exit();
      }
      else{
        //repeated queries till unique
        while($sql -> num_rows > 0) {
          $link_ID = id_generator($entropy);
          $mysqli -> query($myquery);
        }
      return $link_ID;
      }
    }
  }


    public function mapper($dataload,$mysqli){
      $myaction = "INSERT INTO link_mapping (long_link, short_link_ID, op_time, entry_source_ip) VALUES ('$dataload[0]', '$dataload[1]', current_timestamp(), '$dataload[2]')";
      $sql_2 = $mysqli->query($myaction);
      if(!$sql_2){
        trigger_error('Invalid query: ' . $mysqli->error);
        exit();
        }
        else{
          $result = 1; //successful completion flag
          return $result;
          //No Need to return any message.
        }
      }
    }
?>
