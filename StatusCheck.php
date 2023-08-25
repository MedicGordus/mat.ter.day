<?php

// Process buffer every 5 minutes (this logic can be moved to a separate cron job)
$currentTime = time();
if($currentTime % 300 == 0) { // Very naive way of determining every 5 minutes

    //// Read API info from keyfile.json
    //
    $keyfileContent = file_get_contents("./keyfile.json");
    $apiCredentials = json_decode($keyfileContent, true);
    //
    $key = $apiCredentials['key'];
    $secret = $apiCredentials['secret'];
    //
    // Base64 encode the combination of the key and secret for Basic Authentication
    $basicAuth = base64_encode($key . ":" . $secret);
    //
    ////

    processBuffer($db, $basicAuth);
    
    // return response
    echo json_encode(['status' => "request fulfilled"]);
    exit;
}

// return response
echo json_encode(['status' => "spinning"]);
exit;

function processBuffer($db, $token) {
    $query = $db->prepare("SELECT DISTINCT tweet_id FROM buffer_table LIMIT 15");
    $query->execute();
    $bufferedTweets = $query->fetchAll(PDO::FETCH_ASSOC);

    foreach($bufferedTweets as $entry) {
        $tweetId = $entry['tweet_id'];
        
        // Fetch from Twitter API
        $tweetContent = fetchFromTwitterAPI($tweetId, $token);
        
        // Store in cache
        $store = $db->prepare("INSERT INTO tweets_cache_table (tweet_id, tweet_content, timestamp) VALUES (:tweet_id, :tweet_content, NOW())");
        $store->bindParam(":tweet_id", $tweetId);
        $store->bindParam(":tweet_content", $tweetContent);
        $store->execute();
        
        // Remove from buffer
        $delete = $db->prepare("DELETE FROM buffer_table WHERE tweet_id = :tweet_id");
        $delete->bindParam(":tweet_id", $tweetId);
        $delete->execute();
    }
}

function fetchFromTwitterAPI($tweetId, $token) {
    $apiUrl = "https://api.twitter.com/2/tweets/{$tweetId}";

    /* bearer token code
    $header = [
        "Authorization: Bearer {$token}",
        "Content-Type: application/json"
    ];
    */

    $header = [
        "Authorization: Basic {$token}",
        "Content-Type: application/json"
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    
    $response = curl_exec($ch);
    curl_close($ch);

    return $response; 
}

?>