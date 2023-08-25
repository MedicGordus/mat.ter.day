<?php

// Database connection
$host = 'localhost';
$dbname = 'your_database_name';
$user = 'your_username';
$pass = 'your_password';
$db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);

// Retrieve tweet ID from POST request (you should validate and sanitize this!)
$requestedTweetId = $_POST['tweet_id'];
$userIdentifier = $_SERVER['REMOTE_ADDR']; // Using IP as identifier; consider alternatives for better accuracy.

// Check user's buffer limit
$query = $db->prepare("SELECT COUNT(*) FROM buffer_table WHERE user_identifier = :user_identifier AND timestamp > DATE_SUB(NOW(), INTERVAL 20 MINUTE)");
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
$query = $db->prepare("SELECT COUNT(*) FROM buffer_table WHERE timestamp > DATE_SUB(NOW(), INTERVAL 20 MINUTE)");
$query->execute();
$totalRequestCount = $query->fetchColumn();

if($totalRequestCount >= 15) {
    die(json_encode(['error' => 'Total request limit reached']));
}

// Not in cache, add to buffer
$query = $db->prepare("INSERT INTO buffer_table (tweet_id, user_identifier, timestamp) VALUES (:tweet_id, :user_identifier, NOW())");
$query->bindParam(":tweet_id", $requestedTweetId);
$query->bindParam(":user_identifier", $userIdentifier);
$query->execute();


// return buffer response
echo json_encode(['status' => "request buffered"]);
exit;

?>