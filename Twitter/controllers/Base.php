<?php
use Firebase\JWT\JWT;
abstract class BaseController
{
    public $db_tweets;
    public $db_auth;
    public $user;
    public $id;
    public $auth = false;
    protected $refresh = "iW0TdKav8l8GSEVg5FrL47A22qDqtUQy";
    protected $key = "D91303F61B40A52C1E8E060A93E59944CC6E3D4F8D50C6795F45DB209736E03E";
    public function __construct($db_tweets, $db_auth)
    {
        $this->db_tweets = $db_tweets;
        $this->db_auth = $db_auth;
    }
    public function setStatus($status, $message){
        http_response_code($status);
        echo json_encode($message);
    }
    public function validateUsername($data){
        $username = trim($data);
        if(strlen($username)>5&&strlen($username)<25&&preg_match('/[0-9]/', $username)){
            return true;
        } else{
            return false;
        }
    }
    public function validateEmail($data){
        $email = trim($data);
        if(filter_var($email, FILTER_VALIDATE_EMAIL)){
            return true;
        } else {
            return false;
        }
    }
    public function validatePass($data){
        $pass = trim($data);
        if(preg_match('/^(?=.*\d)(?=.*[A-Za-z])[0-9A-Za-z!@#$%]{8,20}$/', $pass)){
            return true;
        } else {
            return false;
        }
    }
    public function setAccessJwt(array $user){
        $issuer_claim = "http://localhost"; // this can be the servername
        $audience_claim = "http://localhost";
        $issuedat_claim = time(); // issued at
        //$notbefore_claim = $issuedat_claim + 2; //not before in seconds
        $expire_claim = $issuedat_claim + 8; // expire time in seconds
        $access_token = array(
            "iss" => $issuer_claim,
            "aud" => $audience_claim,
            "iat" => $issuedat_claim,
            //"nbf" => $notbefore_claim,
            "exp" => $expire_claim,
            "data" => array(
                "uniqid" => $user['uniqid'],
                "username" => $user['username']
            )
        );
        $jwt = JWT::encode($access_token, $this->key);
        
        echo json_encode(
            array(
                "message" => "Successful login.",
                "jwt" => $jwt,
                "expire_at" => $expire_claim,
            ));
    }
    public function setRefreshJwt(array $user){
        $issuer_claim = "http://localhost"; // this can be the servername
        $audience_claim = "http://localhost";
        $issuedat_claim = time(); // issued at
        $notbefore_claim = $issuedat_claim + 0; //not before in seconds
        $refresh_token = array(
            "iss" => $issuer_claim,
            "aud" => $audience_claim,
            "iat" => $issuedat_claim,
            "nbf" => $notbefore_claim,
            "data" => [
                "username" => $user['username'],
                "uniqid"=>$user['uniqid']
            ]
        );
        try{
            //Creating a refresh token
            $jwtRefresh = JWT::encode($refresh_token, $this->refresh);
            //Storing it to the httpOnly
            setcookie("refresh", $jwtRefresh, time()+3600*24, '/', '' ,false, true);
        } catch (Exception $e){
            print_r("Error:".$e->getMessage());
            exit();
        }
        //

    }
    public function getNewAccess(){
        if(isset($_COOKIE['refresh'])){
            $refresh_token = $_COOKIE['refresh'];

        } else{
            //If there is no refresh token then the page can not be accessed
            $this->setStatus(403, 'No refresh');
            exit();
        }
        
            try{
                $decoded = JWT::decode($refresh_token, $this->refresh, array('HS256'));
                $user = (array) $decoded->data;
                //Saving new token
                $this->setAccessJwt($user);
                
                // echo json_encode($response);
                // exit();               
                // //If adding tweet we dont need any response
                
            } catch( Exception $e){
                //Invalid refresh token
                $this->setStatus(404, "Please log in again");
                print_r(json_encode($e->getMessage()));
            }
    }
    public function decode($token){
        try{
            $decoded = JWT::decode($token, $this->refresh, array('HS256'));
            return $decoded;
        } catch(Exception $e){
            return $e->getMessage();
            exit();
        }
    }
    public function checkToken(){
        $headers = getallheaders();

        if(!isset($headers['Authorization'])){
            //Not authorized users can not access 
            //var_dump($_SERVER['REQUEST_METHOD']);
            $this->setStatus(403, "No authorization token");
            exit();
        }
        elseif(!isset($_COOKIE['refresh'])){
            $this->setStatus(403, "No refresh token");
            var_dump($_COOKIE);
            //var_dump($_SERVER);
            exit();
        } else{
            try{
                //Verify token
                $token = $headers['Authorization'];
                $jwt = str_replace('Bearer ', '', $token);
                $decoded = JWT::decode($jwt, $this->key, array('HS256'));
                $this->user = $decoded->data->username;
                $this->id = $decoded->data->uniqid;
                //Check if user exists
                if(!$this->db_auth->getUser($this->id)){
                    var_dump($this->db_auth->getUser($this->id));
                    var_dump($decoded);
                    $this->setStatus(403, "User doesn't exist");
                    exit();
                }
                //If it is valid then return false(valid token)
                return false;
                exit();

            } catch( Exception $e){
                //Checking if the token is expired
                if($e->getMessage() === "Expired token"){
                    //If the token is expired then it gets refreshed, we need to post the tweet anyway
                
                //If refreshing the token
                if(isset($_GET['action'])&& (strcmp($_GET['action'], 'refresh')===0 || strcmp($_GET['action'], 'refresh')===0)){
                    $this->getNewAccess();
                    return false;
                } else{
                    //print_r(strcmp($_GET['action'], 'refresh'));
                    return false;
                }
                   
                    
                } else{
                    //If there is another error then log out and print it
                    var_dump($e->getMessage());
                    $this->setStatus(404, $token);
                }
            } finally{
                $this->setUniqId();
            }
        }
    }

    public function getPostData(){
        $rawPostData = file_get_contents('php://input');
        $postData = json_decode($rawPostData);
        return $postData;
    }

    public function setUniqId(){
        if(!empty($_COOKIE['refresh'])){
            $token = $this->decode($_COOKIE['refresh']);
            $this->id = $token->data->uniqid;
            $this->user = $token->data->username;
        } else{
            http_response_code(403, "No refresh token");
        }
    }
}
