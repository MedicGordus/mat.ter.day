<?php

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

// PDO connection
try {
    $db = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create buffer_table
    $createBufferTable = "
    CREATE TABLE buffer_table (
        tweet_id VARCHAR(255) NOT NULL PRIMARY KEY,
        request_time TIMESTAMP NOT NULL
    )";
    $db->exec($createBufferTable);
    echo "buffer_table created successfully.<br>";

    // Create tweets_cache_table
    $createTweetsCacheTable = "
    CREATE TABLE tweets_cache_table (
        tweet_id VARCHAR(255) NOT NULL PRIMARY KEY,
        tweet_content JSON NOT NULL,
        timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($createTweetsCacheTable);
    echo "tweets_cache_table created successfully.<br>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Close connection
$db = null;

?>