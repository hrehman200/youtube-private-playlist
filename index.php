<?php

require_once __DIR__ . '/vendor/autoload.php';
session_start();

define('CREDENTIALS_PATH', './data/oauth2.json');
define('SECRET_PATH', './data/google_client_secret.json');
define('REDIRECT_URL', 'http://localhost/youtube-private-playlist/');

function getClient() {
    $client = new Google_Client();
    // Set to name/location of your client_secrets.json file.
    $client->setAuthConfig(SECRET_PATH);
    // Set to valid redirect URI for your project.
    $client->setRedirectUri(REDIRECT_URL);
    $client->addScope(Google_Service_YouTube::YOUTUBE_READONLY);
    $client->setAccessType('offline');

    // Load previously authorized credentials from a file.
    $credentialsPath = CREDENTIALS_PATH;
    if (file_exists($credentialsPath)) {
        $accessToken = file_get_contents($credentialsPath);
    } else {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = urldecode(trim(fgets(STDIN)));

        // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        // Store the credentials to disk.
        if (!file_exists(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }
        file_put_contents($credentialsPath, $accessToken);
        printf("Credentials saved to %s\n", $credentialsPath);
    }
    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $jsonCreds = file_get_contents(CREDENTIALS_PATH);
        $jsonArray = json_decode($jsonCreds, true);
        $client->fetchAccessTokenWithRefreshToken($jsonArray["refresh_token"]);

        $newAccessToken = $client->getAccessToken();
        $accessToken = array_merge($jsonArray, $newAccessToken);

        file_put_contents($credentialsPath, json_encode($accessToken));
    }
    return $client;
}

$client = getClient();
$youtube = new Google_Service_YouTube($client);

if (isset($_GET['code'])) {
    if (strval($_SESSION['state']) !== strval($_GET['state'])) {
        die('The session state did not match.');
    }

    $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $_SESSION['token'] = $client->getAccessToken();
    header('Location: ' . REDIRECT_URL);
}

if (isset($_SESSION['token'])) {
    $client->setAccessToken($_SESSION['token']);
}

if (!$client->getAccessToken()) {
    print("no access token, whaawhaaa");
    exit;
}

$playlistItems = $youtube->playlistItems->listPlaylistItems("snippet,contentDetails", array(
    'playlistId' => 'PLjncHZSg0GNF2dTrf0PknwTYrZWy-USTX',
    'maxResults' => 50,
));

while ($playlistItems->nextPageToken) {
    $playlistItems = $youtube->playlistItems->listPlaylistItems(
        "snippet",
        array(
            "playlistId" => 'PLjncHZSg0GNF2dTrf0PknwTYrZWy-USTX',
            "maxResults" => 50,
            "pageToken" => $playlistItems->nextPageToken
        )
    );
}

if ($playlistItems) {
    $arr = $playlistItems->getItems();
    $item = end($arr);
}

echo $item['snippet']['title'] . " " . $item['snippet']['resourceId']['videoId'] . "\n";

?>