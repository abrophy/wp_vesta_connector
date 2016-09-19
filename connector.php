<?php
// pull in values from environment variables
$db_name = getenv('DB_NAME');
$db_username = getenv('DB_USERNAME');
$db_password = getenv('DB_PASSWORD');

//instantiate the connector
$connector = new connector();

class connector {
  private $db;

  function __construct(){
    $db = new db_connection($db_name, $db_username, $db_password);
    //TODO initialize the database connection here
  }
}

//TODO database connector class
class db_connection {

  private $db_name;
  private $db_username;
  private $db_password;

  function __construct($db_name, $db_username, $db_password){
    echo $this->$db_name = $db_name;
    echo $this->$db_username = $db_username;
    echo $this->$db_password = $db_password;
  }
}
