<?php
// pull in values from environment variables

require_once "spyc/Spyc.php";
$db_data = Spyc::YAMLLoad('db.yaml');

echo var_dump($db_data);

$db_name = $db_data['db_name'];
$db_username = $db_data['db_username'];
$db_password = $db_data['db_password'];

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
