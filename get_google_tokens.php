<?php

/* 
Script: get_google_tokens.php
Author: James Attard (james@ttard.info) - jamesattard.com
*/

$url = "https://accounts.google.com/o/oauth2/auth";
$client_id = "xxx.apps.googleusercontent.com";
$client_secret = "yyy";
$redirect_uri = "http://localhost/get_google_tokens.php";
$access_type = "offline";
$approval_prompt = "force";
$grant_type = "authorization_code";
$scope = "https://www.googleapis.com/auth/drive";


$params_request = array(
    "response_type" => "code",
    "client_id" => "$client_id",
    "redirect_uri" => "$redirect_uri",
    "access_type" => "$access_type",
    "approval_prompt" => "$approval_prompt",
    "scope" => "$scope"
    );

$request_to = $url . '?' . http_build_query($params_request);

if(isset($_GET['code'])) {
    // try to get an access token
    $code = $_GET['code'];
    $url = 'https://accounts.google.com/o/oauth2/token';
    $params = array(
        "code" => $code,
        "client_id" => "$client_id",
        "client_secret" => "$client_secret",
        "redirect_uri" => "$redirect_uri",
        "grant_type" => "$grant_type"
    );

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);	

    $json_response = curl_exec($curl);
    curl_close($curl);

    $authObj = json_decode($json_response);

    echo "Refresh token: " . $authObj->refresh_token;
    echo "Access token: " . $authObj->access_token;

    exit("Done.");
}

header("Location: " . $request_to);

?>
