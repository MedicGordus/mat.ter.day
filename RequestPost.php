<?php
try
{
    // Retrieve tweet ID from POST request (you should validate and sanitize this!)
    $requestedTweetId = $_POST['tweet_id']  . $_GET['tweet_id'];

    if(!isValidTweetId($requestedTweetId)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid tweet ID']);
        exit;
    }

    //// Read secrets from keyfile.json
    //
    $keyfileContent = file_get_contents("./secrets.json");
    $secrets = json_decode($keyfileContent, true);
    //
    $dbhost = $secrets['db-host'];
    $dbname = $secrets['db-name'];
    $dbuser = $secrets['db-user'];
    $dbpass = $secrets['db-password'];
    //
    ////

    // Database connection
    $db = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);

    $userIdentifier = $_SERVER['REMOTE_ADDR']; // Using IP as identifier; consider alternatives for better accuracy.

    // Check user's buffer limit
    $query = $db->prepare("SELECT COUNT(*) FROM buffer_table WHERE user_identifier = :user_identifier AND request_time > DATE_SUB(NOW(), INTERVAL 20 MINUTE)");
    $query->bindParam(":user_identifier", $userIdentifier);
    $query->execute();
    $userRequestCount = $query->fetchColumn();

    if($userRequestCount >= 15) {
        die(json_encode(['error' => 'User request limit reached']));
    }

    // Check cache first
    $query = $db->prepare("SELECT tweet_content FROM tweets_cache_table WHERE tweet_id = :tweet_id");
    $query->bindParam(":tweet_id", $requestedTweetId);
    $query->execute();
    $result = $query->fetch();

    if($result) {
        // Serve from cache
        echo json_encode(['tweet' => $result['tweet_content']]);
        exit;
    }

    // Check app's buffer limit
    $query = $db->prepare("SELECT COUNT(*) FROM buffer_table WHERE request_time > DATE_SUB(NOW(), INTERVAL 20 MINUTE)");
    $query->execute();
    $totalRequestCount = $query->fetchColumn();

    if($totalRequestCount >= 15) {
        die(json_encode(['error' => 'Total request limit reached']));
    }

    //// Not in cache, add to buffer
    //
    // Calculate the next cache time
    $nextCacheTime = (ceil(time()/300) * 300); // Rounds up to the nearest 5 minutes
    $responseTime = date('Y-m-d H:i:s', $nextCacheTime);
    //
    $query = $db->prepare("INSERT INTO buffer_table (tweet_id, user_identifier, expire_time, request_time) VALUES (:tweet_id, :user_identifier, :expire_time, NOW())");
    $query->bindParam(":tweet_id", $requestedTweetId);
    $query->bindParam(":user_identifier", $userIdentifier);
    $query->bindParam(":expire_time", $nextCacheTime);
    $query->execute();
    //
    ////

    // return buffer response
    echo json_encode(
        [
            'status' => 'request buffered',
            'nextCacheTime' => $responseTime
        ]
    );
    exit;
}
catch (Exception $e) {
    handleError($e, "main()");
}

function isValidTweetId($tweetId) {
    try
    {
        // Check for the length
        if (strlen($tweetId) > 256) {
            return false;
        }
        
        // This regular expression checks if the string contains only numbers, letters, hyphens, or underscores
        return preg_match('/^[a-zA-Z0-9-_]+$/', $tweetId);
    }
    catch (Exception $e) {
        handleError($e, "isValidTweetId");
    }
}

function handleError($err, $method) {
    echo $method . ": " . $err->getMessage();
}
?>