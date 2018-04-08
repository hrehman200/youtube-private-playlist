<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
session_start();

use Abraham\TwitterOAuth\TwitterOAuth;

function getClient() {
    $client = new Google_Client();
    // Set to name/location of your client_secrets.json file.
    $client->setAuthConfig(SECRET_PATH);
    // Set to valid redirect URI for your project.
    $client->setRedirectUri(REDIRECT_URL);
    $client->addScope(Google_Service_YouTube::YOUTUBE_READONLY);
    $client->setAccessType('offline');
    $client->setApprovalPrompt('force');

    if (isset($_GET['code'])) {
        if (strval($_SESSION['state']) !== strval($_GET['state'])) {
            die('The session state did not match.');
        }

        $accessToken = $client->fetchAccessTokenWithAuthCode($_GET['code']);

        // Store the credentials to disk.
        if (!file_exists(dirname(CREDENTIALS_PATH))) {
            mkdir(dirname(CREDENTIALS_PATH), 0700, true);
        }
        file_put_contents(CREDENTIALS_PATH, json_encode($accessToken));
        printf("Credentials saved to %s\n", CREDENTIALS_PATH);

        $_SESSION['token'] = $client->getAccessToken();
        header('Location: ' . REDIRECT_URL);
    }

    if (file_exists(CREDENTIALS_PATH)) {
        $accessToken = json_decode(file_get_contents(CREDENTIALS_PATH), true);
        $client->setAccessToken($accessToken);
    } else {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        header('Location:' . $authUrl);
    }

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $jsonCreds = file_get_contents(CREDENTIALS_PATH);
        $jsonArray = json_decode($jsonCreds, true);
        $client->fetchAccessTokenWithRefreshToken($jsonArray["refresh_token"]);

        $newAccessToken = $client->getAccessToken();
        $accessToken = array_merge($jsonArray, $newAccessToken);

        file_put_contents(CREDENTIALS_PATH, json_encode($accessToken));
    }
    return $client;
}

$client = getClient();
$youtube = new Google_Service_YouTube($client);

if (isset($_SESSION['token'])) {
    $client->setAccessToken($_SESSION['token']);
}

if (!$client->getAccessToken()) {
    print("no access token, whaawhaaa");
    exit;
}

$playlistItems = $youtube->playlistItems->listPlaylistItems("snippet,contentDetails", array(
    'playlistId' => PLAYLIST_ID,
    'maxResults' => 50,
));

while ($playlistItems->nextPageToken) {
    $playlistItems = $youtube->playlistItems->listPlaylistItems(
        "snippet",
        array(
            "playlistId" => PLAYLIST_ID,
            "maxResults" => 50,
            "pageToken" => $playlistItems->nextPageToken
        )
    );
}

if ($playlistItems) {
    $arr = $playlistItems->getItems();
    $item = end($arr);
    $latest_video_id = $item['snippet']['resourceId']['videoId'];
    $saved_video_id = file_get_contents(LATEST_VIDEO_PATH);

    if($latest_video_id != $saved_video_id) {
        echo $item['snippet']['title'] . " " . $latest_video_id . "<br>";
        file_put_contents(LATEST_VIDEO_PATH, $latest_video_id);

        $twitter_token = json_decode(file_get_contents(TWITTER_OAUTH_PATH), true);
        $twitter = new TwitterOAuth(
            $twitter_settings['consumer_key'],
            $twitter_settings['consumer_secret'],
            $twitter_token['oauth_token'],
            $twitter_token['oauth_token_secret']
        );

        $status = $twitter->post(
            "statuses/update", [
                "status" => "New video uploaded https://www.youtube.com/watch?v=".$latest_video_id
            ]
        );

        echo ('Created new twitter status with #' . $status->id . PHP_EOL);

    } else {
        echo 'No new video found in playlist to be tweeted';
    }
}

?>