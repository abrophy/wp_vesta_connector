<?php
// pull in values from environment variables

$db_data = parse_ini_file("db.ini");
$vst_data = parse_ini_file("vst.ini");

$vst_hostname = $vst_data['vst_hostname'];
$vst_username = $vst_data['vst_username'];
$vst_password = $vst_data['vst_password'];

$db_name = $db_data['db_name'];
$db_username = $db_data['db_username'];
$db_password = $db_data['db_password'];

//instantiate the Connector
$api = new VestaApi($vst_hostname, $vst_username, $vst_password);
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
        echo 'CREATING USER INSTANCE: name: ' . $row['meta_value'] . "\n";
	$this->users[] = new VestaUser($row);
      }
    } else {
      echo "0 results\n";
    }
  }

}

class VestaUser {
  public $userId;
  public $subscriptions = "";
  public $userName;

  function __construct($row){
    $this->userId = $row['user_id'];
    $this->userName = $row['meta_value'];
  }

  public function updateSubscriptionData($conn){
    $sql = 'SELECT * from cs3wv_usermeta WHERE meta_key = "ms_subscriptions" AND user_id = ' . $this->userId ;
echo "USER ID SQL STRING\n";
echo $sql . "\n";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
      // output data of each row
      while($row = $result->fetch_assoc()) {
        echo 'UPDATING SUBS DATA: name: ' . $this->userName . "\n";
        $this->subscriptions = ($row['meta_value']);
      }
    } else {
      echo "0 results\n";
    }
  }
}



class VestaApi {

  // Server credentials
  private $vst_hostname;
  private $vst_username;
  private $vst_password;
  private $vst_returncode;

  function __construct($hostname, $username, $password){
    $this->vst_hostname = $hostname;
    $this->vst_username = $username;
    $this->vst_password = $password;
    $this->vst_returncode = 'yes';
  }

  public function createNewUser($username, $password, $email, $package, $first_name){

    // Prepare POST query
    $postvars = array(
      'user' => $vst_username,
      'password' => $vst_password,
      'returncode' => $vst_returncode,
      'cmd' => 'v-add-user',
      'arg1' => $username,
      'arg2' => $password,
      'arg3' => $email,
      'arg4' => $package,
      'arg5' => $first_name,
      'arg6' => $last_name
    );
    $postdata = http_build_query($postvars);

    // Send POST query via cURL
    $postdata = http_build_query($postvars);
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, 'https://' . $vst_hostname . ':8083/api/');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
    $answer = curl_exec($curl);

    // Check result
    if($answer == 0) {
      echo "User account has been successfuly created\n";
      return true;
    } else {
      echo "Query returned error code: " .$answer. "\n";
      return false;
    }
  }
}
