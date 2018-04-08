<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 08/04/2018
 * Time: 1:36 PM
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once 'config.php';
session_start();

use Abraham\TwitterOAuth\TwitterOAuth;

$oauth_verifier = filter_input(INPUT_GET, 'oauth_verifier');

if (empty($oauth_verifier) ||
    empty($_SESSION['oauth_token']) ||
    empty($_SESSION['oauth_token_secret'])
) {
    // something's missing, go and login again
    header('Location: ' . $twitter_settings['url_login']);
}

// connect with application token
$connection = new TwitterOAuth(
    $twitter_settings['consumer_key'],
    $twitter_settings['consumer_secret'],
    $_SESSION['oauth_token'],
    $_SESSION['oauth_token_secret']
);

// request user token
$token = $connection->oauth(
    'oauth/access_token', [
        'oauth_verifier' => $oauth_verifier
    ]
);

file_put_contents(TWITTER_OAUTH_PATH, json_encode($token));

echo 'Twitter authorization complete';

