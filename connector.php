<?php
// pull in values from environment variables

require_once 'spyc/Spyc.php';
$db_data = Spyc::YAMLLoad('db.yaml');

echo var_dump($db_data);

$db_name = $db_data['db_name'];
$db_username = $db_data['db_username'];
$db_password = $db_data['db_password'];

//instantiate the Connector
$connector = new Connector($db_username, $db_password, $db_name );

class Connector {
  public $users = [];

  function __construct($db_username, $db_password, $db_name) {
//Note currently configured to work with local DB's only
    $conn = new mysqli('localhost', $db_username, $db_password, $db_name );
    if ($conn->connect_error) {
          die('Connection failed: ' . $conn->connect_error);
    }
    $this->getUsers($conn);
    foreach($this->users as $user){
      $user->updateSubscriptionData($conn);
    }
    $conn->close();
  }

  function getUsers($conn){
    //clear any previous users
    unset($this->users);
    $this->users = [];

    $sql = 'SELECT * from cs3wv_usermeta WHERE meta_key = "ms_username"';
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
      // output data of each row
      while($row = $result->fetch_assoc()) {
        //TODO for each row create a new user and push it to the array
        echo 'CREATING USER INSTANCE: name: ' . $row['meta_value'];
        $this->createNewUser($row);
      }
    } else {
      echo '0 results';
    }
  }

  function createNewUser($row){
    $users = new VestaUser($row);
  }
}

class VestaUser {
  public $userId;
  public $subscriptions = "";
  public $userName;

  function __construct($row){
    $this->userId = $row('user_id');
    $this->userName = $row('meta_value');
  }

  public function updateSubscriptionData($conn){
    $sql = 'SELECT * from cs3wv_usermeta WHERE meta_key = "ms_subscriptions" AND WHERE user_id = ' . $this->userId ;
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
      // output data of each row
      while($row = $result->fetch_assoc()) {
        echo 'UPDATING SUBS DATA: name: ' . $this->userName;
        $this->subscriptions = ($row['meta_value']);
      }
    } else {
      echo '0 results';
    }
  }
}

