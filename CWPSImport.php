<?php
  /* CWPSImport.php
   * -
   * Written by damian <damian@asit.com.au>
   * -
   * A method of importing clients from ConnectWise into PasswordState automatically
   * Run this script once a day (or even more often..) to automatically generate new Folders in PasswordState for new clients
   * -
   * Note that this initial script doesn't care about what your current setup is, it will create a folder with ASIT default
   */

define('CONFIG_FILE', '/var/www/config/cwpsimport.config.ini');

// require the ini.php
require_once('ini.php');

class CWPSImport {
  private $SET;
  private $CW;
  private $PS;
  
  public function __construct() {
    // import the configuration file
    $this->SET = ini2array(CONFIG_FILE);
    
    // get a list of ConnectWise customers
    $this->CW = $this->collect_connectwise();
    
    // get a list of PasswordState customer folders
    $this->PS = $this->collect_passwordstate();

    // add new clients to PasswordState
    $NEW = $this->check_and_add_ps_clients();
  }
  
  function collect_connectwise() {
    // set up http options
    $HTTP_OPTS = Array(
    'http' => Array(
      'method' => "GET",
      'header' => "Accept: text/html\r\n".
                  "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36\r\n".
                  "Authorization: Basic ". base64_encode($this->SET['ConnectWise']['Company'] ."+". $this->SET['ConnectWise']['PublicKey'] .":". $this->SET['ConnectWise']['PrivateKey']) ."\r\n"
      )
    );
    
    $HTTP_CONTEXT = stream_context_create($HTTP_OPTS);
    
    // set up the URL
    $URL = $this->SET['ConnectWise']['URL'] . $this->SET['ConnectWise']['Branch'] ."/apis/3.0/company/companies?pagesize=1000";

    // grab the JSON output
    $JSON = file_get_contents($URL, false, $HTTP_CONTEXT);

    // return the JSON as an Array
    return json_decode($JSON, true);
  }
  
  function collect_passwordstate() {
    // set up http options
    $HTTP_OPTS = Array(
      'http' => Array(
        'method' => "GET",
        'header' => "Accept: text/html\r\n".
                  "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36\r\n"
      ),
      'ssl' => Array( // maybe shouldn't do this, but it's internal, so who cares!
        'verify_peer' => false,
        'verify_peer_name' => false,
      ),
    );
    
    $HTTP_CONTEXT = stream_context_create($HTTP_OPTS);
    
    // set up the URL
    $URL = $this->SET['PasswordState']['URL'] ."api/folders?apikey=". $this->SET['PasswordState']['API'];

    // grab the JSON output
    $JSON = file_get_contents($URL, false, $HTTP_CONTEXT);

    // convert to an Array
    $CLIENT_LIST = json_decode($JSON, true);
    
    // go through the list and get a list of clients (makes it easier to search later)
    $CLIENTS = Array();
    foreach ($CLIENT_LIST as $CLIENT) {
      $CLIENTS[strtolower($CLIENT['FolderName'])] = $CLIENT['FolderName'];
    }
    
    // return
    return $CLIENTS;
  }
  
  function check_and_add_ps_clients() {
    foreach ($this->CW as $CLIENT) {
      if ($CLIENT['status']['id'] != 1) {
        // if the status isn't Active..
        continue;
      } elseif ($this->find_ps_client($CLIENT['name'])) {
        // if the client already exists
        continue;
      } else {
        // add this guy
        $this->add_ps_client($CLIENT['name']);
      }
    }
  }
  
  function find_ps_client($CLIENT) {
    if (isset($this->PS[strtolower($CLIENT)])) {
      return true;
    } else {
      return false;
    }
  }
  
  function post_content($URL, $DATA) {
    // set up http options
    $HTTP_OPTS = Array(
      'http' => Array(
        'method' => "POST",
        'header' => "Content-Type: application/json\r\n".
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36\r\n",
        'content' => $DATA,
      ),
      'ssl' => Array( // maybe shouldn't do this, but it's internal, so who cares!
        'verify_peer' => false,
        'verify_peer_name' => false,
      ),
    );
    $HTTP_CONTEXT = stream_context_create($HTTP_OPTS);

    // post the data, get the output
    $OUTPUT = file_get_contents($URL, false, $HTTP_CONTEXT);

    // return the output
    return $OUTPUT;
  }
  
  function get_content() {
    
  }

  function add_ps_client($CLIENT) {
    echo '& Adding new client "'. $CLIENT .'" ... ';
    
    // set up the folder configuration
    $FOLDER = Array(
      'FolderName' => $CLIENT,
      'Description' => '',
      'CopyPermissionsFromTemplateID' => '7',
      'NestUnderFolderID' => '0',
      'PropagatePermissions' => 'true',
      'APIKey' => $this->SET['PasswordState']['API'],
    );
    
    // create the folder
    $URL = $this->SET['PasswordState']['URL'] .'api/folders/';
    $FOLDERID = json_decode($this->post_content($URL, json_encode($FOLDER)), true)['FolderID'];
    
    // set up the default PasswordList configuration
    $LIST = Array(
      'Description' => '',
      'LinkToTemplate' => false,
      'NestUnderFolderID' => $FOLDERID,
      'APIKey' => $this->SET['PasswordState']['API'],
    );
    
    // create Network Peripherals
    $LIST['PasswordList'] = 'Network Peripherals';
    $LIST['CopySettingsFromTemplateID'] = '1';
    $URL = $this->SET['PasswordState']['URL'] .'api/passwordLists/';
    $OUTPUT = json_decode($this->post_content($URL, json_encode($LIST)), true);
    
    // create Network Devices
    $LIST['PasswordList'] = 'Network Devices';
    $LIST['CopySettingsFromTemplateID'] = '2';
    $URL = $this->SET['PasswordState']['URL'] .'api/passwordLists/';
    $OUTPUT = json_decode($this->post_content($URL, json_encode($LIST)), true);
    
    // create Servers
    $LIST['PasswordList'] = 'Servers';
    $LIST['CopySettingsFromTemplateID'] = '3';
    $URL = $this->SET['PasswordState']['URL'] .'api/passwordLists/';
    $OUTPUT = json_decode($this->post_content($URL, json_encode($LIST)), true);
    
    // create Software
    $LIST['PasswordList'] = 'Software';
    $LIST['CopySettingsFromTemplateID'] = '4';
    $URL = $this->SET['PasswordState']['URL'] .'api/passwordLists/';
    $OUTPUT = json_decode($this->post_content($URL, json_encode($LIST)), true);
    
    // create Supplier Websites
    $LIST['PasswordList'] = 'Supplier Websites';
    $LIST['CopySettingsFromTemplateID'] = '5';
    $URL = $this->SET['PasswordState']['URL'] .'api/passwordLists/';
    $OUTPUT = json_decode($this->post_content($URL, json_encode($LIST)), true);
    
    // create User Accounts
    $LIST['PasswordList'] = 'User Accounts';
    $LIST['CopySettingsFromTemplateID'] = '6';
    $URL = $this->SET['PasswordState']['URL'] .'api/passwordLists/';
    $OUTPUT = json_decode($this->post_content($URL, json_encode($LIST)), true);
    
    echo 'done'.PHP_EOL;
  }
}

$Import = new CWPSImport();
?>