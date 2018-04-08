<?php

require_once 'vendor/autoload.php';
require_once 'config.php';

use Abraham\TwitterOAuth\TwitterOAuth;

session_start();

// create TwitterOAuth object
$twitter_oauth = new TwitterOAuth($twitter_settings['consumer_key'], $twitter_settings['consumer_secret']);

// request token of application
$request_token = $twitter_oauth->oauth(
    'oauth/request_token', [
        'oauth_callback' => $twitter_settings['url_callback']
    ]
);

// throw exception if something gone wrong
if($twitter_oauth->getLastHttpCode() != 200) {
    throw new \Exception('There was a problem performing this request');
}

// save token of application to session
$_SESSION['oauth_token'] = $request_token['oauth_token'];
$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];

// generate the URL to make request to authorize our application
$url = $twitter_oauth->url(
    'oauth/authorize', [
        'oauth_token' => $request_token['oauth_token']
    ]
);

// and redirect
header('Location: '. $url);

