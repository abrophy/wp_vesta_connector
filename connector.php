<?php

// pull in values from environment variables

//TODO refactor these into arrays so as to make it easier to pass them into constructors
$db_data = parse_ini_file("db.ini");
$vst_data = parse_ini_file("vst.ini");
$whitelisted_users = parse_ini_file("user-whitelist.ini")["users"];

$vst_hostname = $vst_data['vst_hostname'];
$vst_username = $vst_data['vst_username'];
$vst_password = $vst_data['vst_password'];

$db_name = $db_data['db_name'];
$db_username = $db_data['db_username'];
$db_password = $db_data['db_password'];

//instantiate the Connector
$api = new VestaApi($vst_hostname, $vst_username, $vst_password);
$connector = new Connector($db_username, $db_password, $db_name, $whitelisted_users, $api );

class Connector {
  public $users = [];
  public $whitelisted_users = [];
  public $api;

  function __construct($db_username, $db_password, $db_name, $whitelisted_users, $api) {
//Note currently configured to work with local DB's only
	  $this->whitelisted_users = $whitelisted_users;
	  $this->api = $api;
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
	      if(!in_array($row['meta_value'], $this->whitelisted_users)){
		      echo 'CREATING USER INSTANCE: name: ' . $row['meta_value'] . "\n";
		      $this->users[] = new VestaUser($row, $this->api, $conn);
	      }
      }
    } else {
      //TODO better handling of missing data here
      echo "0 results\n";
    }
  }

  function synchUserStatuses(){
	  foreach($this->users as $user){
		  if($user->existsOnVesta()){
			  $user->compareVestaStatus();
		  } else {
			  echo "user $user->userName needs to be created\n";
			  $user->createOnVesta();
		  }
	  }
  }

}

class VestaUser {

  public $userId;
  public $hasSubscriptions;
  public $subscriptions;
  public $userName;
  public $email;
  public $fullName;

/*TODO:
 move the api back out of the class,
as there being so many copies of the user class with so many copies of the api within them would be inefficient, rather just pass the api in as a parameter when calling the methods that need the api
*/
  public $api;

  function __construct($row, $api, $conn){
    $this->userId = $row['user_id'];
    $this->userName = $row['meta_value'];
    $this->api = $api;
    $this->email = $this->getEmailAddress($conn);
    $this->fullName = $this->getFullName($conn);
  }

  public function updateSubscriptionData($conn){
	  $sql = 'SELECT * from cs3wv_usermeta WHERE meta_key = "ms_subscriptions" AND user_id = ' . $this->userId ;
	  $result = $conn->query($sql);
	  if ($result->num_rows > 0) {
		  // output data of each row
		  while($row = $result->fetch_assoc()) {
			  $subscriptions = unserialize($row['meta_value']);

			  /*These nested loops are required to pull the subs data out of a nested incomplete php object

			    the reason it's so convoluted is due to php having trouble pulling out the membership object as it's a nested incomplete, can't be called by key as though it's part of an array, or as though the parent is an object
			   */

			  if( count($subscriptions) > 0 ){
				  $subs_array = array();
				  foreach($subscriptions as $subs){
					  $subs_inner_array = get_object_vars($subs);

					  $next_one_is_membership = false;
					  foreach($subs_inner_array as $key => $value){
						  if($next_one_is_membership){
							  $next_one_is_membership = false;
							  $subs_array['membership'] = get_object_vars($value);
						  }
						  if($key == "\0*\0payment_type"){
							  $subs_array[$key] = $value;
							  $next_one_is_membership = true;
						  } else {
							  $subs_array[$key] = $value;
						  }
					  }
				  }
				  $this->hasSubscriptions = true;
				  $this->subscriptions = $subs_array;
			  } else {
				  $this->hasSubscriptions = false;
				  $this->subscriptions = array();
			  }
		  }
	  } else {
		  //TODO better handling of missing data here
		  echo "0 results\n";
	  }
  }

  public function getSubscriptionStatus(){
	  return $this->subscriptions["\0*\0status"];
  }

  public function getSubscriptionName(){
	  $subsArray = parse_ini_file("packages.ini");
	  $subsName = $this->subscriptions["membership"]["\0*\0name"];
	  return $subsArray[$subsName];
  }

  private function getVestaStatus(){
	  return $this->api->fetchVestaData($this->userName)[$this->userName]["SUSPENDED"];
  }

  private function generateRandomPassword(){
	  $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
	  $pass = array();
	  $alphaLength = strlen($alphabet) - 1;
	  for ($i = 0; $i < 8; $i++) {
		  $n = rand(0, $alphaLength);
		  $pass[] = $alphabet[$n];
	  }
	  return implode($pass);
  }

  private function getFullName($conn){
	  $firstSql = 'SELECT * from cs3wv_usermeta WHERE meta_key = "first_name" AND user_id = ' . $this->userId ;
	  $secondSql = 'SELECT * from cs3wv_usermeta WHERE meta_key = "last_name" AND user_id = ' . $this->userId ;
	  $firstResult = $conn->query($firstSql);
	  $secondResult = $conn->query($secondSql);

	  $firstName = "";
	  $lastName = "";
	  if ($firstResult->num_rows > 0) {
		  // output data of each row
		  while($row = $firstResult->fetch_assoc()) {
			  $firstName = $row["meta_value"];
		  }
	  }

	  if ($secondResult->num_rows > 0) {
		  // output data of each row
		  while($row = $secondResult->fetch_assoc()) {
			  $lastName = $row["meta_value"];
		  }
	  }

	  return $firstName . " " . $lastName;
  }

  private function getEmailAddress($conn){
	  $sql = 'SELECT * from cs3wv_usermeta WHERE meta_key = "ms_email" AND user_id = ' . $this->userId ;
	  $result = $conn->query($sql);
	  if ($result->num_rows > 0) {
		  // output data of each row
		  while($row = $result->fetch_assoc()) {
			  return $row["meta_value"];
		  }
	  } else {
		  return false;
	  }
  }

	  public function compareVestaStatus(){
		  $vestaStatus = $this->getVestaStatus();
		  $wpStatus = $this->getSubscriptionStatus();

		  if($vestaStatus == "yes" && $wpStatus == "active"){
			  //TODO create appropriate functions to handle suspension and unsuspension of accounts below
			  echo "User $this->userName needs to be unsuspended\n";
		  } elseif ($vestaStatus == "no" && ($wpStatus == "cancelled" || $wpStatus == "expired")) {
			  echo "User $this->userName needs to be suspended\n";
		  } else {
			  echo "User status appropriately synched between vesta and wp for $this->userName\n";
		  }
	  }


	  public function existsOnVesta(){
		  //returns null if the user doesn't exist on the vesta system
		  return $this->api->fetchVestaData($this->userName);
	  }

	  public function createOnVesta(){
		  $username = $this->userName;
		  $password =  $this->generateRandomPassword();
		  $email = $this->email;
		  $package = $this->getSubscriptionName();
		  $fullName = $this->fullName;

		  $this->api->createNewUser($username, $password, $email, $package, $fullName);
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
	}

	public function createNewUser($username, $password, $email, $package, $fullName){

		// Prepare POST query
		$postvars = array(
				'user' => $this->vst_username,
				'password' => $this->vst_password,
				'returncode' => 'yes',
				'cmd' => 'v-add-user',
				'arg1' => $username,
				'arg2' => $password,
				'arg3' => $email,
				'arg4' => $package,
//TODO need to get correct last name
				'arg5' => $fullName,
//				'arg6' => $last_name
				'arg6' => "testing last name"
				);
		$postdata = http_build_query($postvars);

		// Send POST query via cURL
		$postdata = http_build_query($postvars);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, 'https://' . $this->vst_hostname . ':8083/api/');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
		$answer = curl_exec($curl);

		// Check result
//TODO the below test is not working to determine if the user was created successfully
		echo "RETURNING ANSWER: $answer";
		if($answer == 0) {
			echo "User account has been successfuly created\n";
			//TODO send out a notification e-mail to the relevant address(es)
			echo "USER $username SUCCESSFULLY CREATED\n";
			return true;
		} else {
			echo "Query returned error code: " .$answer. "\n";
			return false;
		}
	}

	public function fetchVestaData($username){

		// Prepare POST query
		$postvars = array(
				'user' => $this->vst_username,
				'password' => $this->vst_password,
				'returncode' => 'no',
				'cmd' => 'v-list-user',
				'arg1' => $username,
				'arg2' => 'json',
				);
		$postdata = http_build_query($postvars);

		// Send POST query via cURL
		$postdata = http_build_query($postvars);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, 'https://' . $this->vst_hostname . ':8083/api/');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
		$answer = curl_exec($curl);

		// Check result
		$decodedJson = json_decode($answer, true);
		if ($decodedJson == null){
			//user does not exist
			return false;
		} else {
			return $decodedJson;
		}
	}

//TODO create method for suspending user

//TODO create method for unsuspending user
}

//TODO create a mailer class for sending out mail notifications using SMTP
