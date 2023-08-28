<?php

try
{
    //// Read secrets from keyfile.json
    //
    $keyfileContent = file_get_contents("./secrets.json");
    $secrets = json_decode($keyfileContent, true);
    //
    $bearer_token =  $secrets['bearer-token'];
    //
    $dbhost = $secrets['db-host'];
    $dbname = $secrets['db-name'];
    $dbuser = $secrets['db-user'];
    $dbpass = $secrets['db-password'];
    //
    ////

    // Database connection
    $db = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);

    processBuffer($db, $bearer_token);

    exit;
}
catch (Exception $e) {
    handleError($e, "main()");
}

function processBuffer($db, $token) {
    try
    {
        // Fetch buffered tweets where request time has passed, up to 100 tweet IDs
        $query = $db->prepare("SELECT DISTINCT tweet_id FROM buffer_table WHERE expire_time <= NOW() LIMIT 100");
        $query->execute();
        $bufferedTweets = $query->fetchAll(PDO::FETCH_ASSOC);

        // If there are no tweets in the buffer, just return
        if(count($bufferedTweets) === 0)
        {
            echo json_encode(['status' => "spinning"]);
            exit;
        }

        // Generate comma-separated list of IDs
        $filteredTweetIds = array_filter(array_column($bufferedTweets, 'tweet_id'));
        $tweetIds = implode(",", $filteredTweetIds);

        if($tweetIds == "")
        {
            echo json_encode(['status' => "spinning"]);
            exit;
        }

        // Fetch from Twitter API
        $tweetsContent = fetchFromTwitterAPI($tweetIds, $token);
        $tweetsData = json_decode($tweetsContent, true)['data'];
        $tweetsUsers = json_decode($tweetsContent, true)['includes']['users'];

        // Create a mapping from author_id to user object
        $authorIdToUser = [];
        foreach ($tweetsUsers as $user) {
            $authorIdToUser[$user['id']] = $user;
        }


        # TEST to make sure the api is working
        #echo "TWITTER CONTENT = ". $tweetsContent;

        foreach ($tweetsData as $tweet) {
            // Fetch the corresponding author info using the tweet's author_id
            $author_info = $authorIdToUser[$tweet['author_id']] ?? null;
            if (!$author_info) {
                // If for some reason there's no corresponding user info, skip processing this tweet.
                continue;
            }
    
            // Store in cache
            $tweetId = $tweet['id'];
            $store = $db->prepare("INSERT INTO tweets_cache_table (tweet_id, tweet_content, author_info, timestamp) VALUES (:tweet_id, :tweet_content, :author_info, NOW())");
            $store->bindParam(":tweet_id", $tweetId);
            $store->bindParam(":tweet_content", json_encode($tweet));
            $store->bindParam(":author_info", json_encode($author_info));
            $store->execute();

            // Remove from buffer
            $delete = $db->prepare("DELETE FROM buffer_table WHERE tweet_id = :tweet_id OR tweet_id is NULL or tweet_id = ''");
            $delete->bindParam(":tweet_id", $tweetId);
            $delete->execute();
        }

        // return response
        echo json_encode(['status' => "request fulfilled"]);
    }
    catch (Exception $e) {
        handleError($e, "processBuffer");
    }
}

function fetchFromTwitterAPI($tweetIds, $token) {
    try
    {
        $apiUrl = "https://api.twitter.com/2/tweets?ids={$tweetIds}&expansions=author_id";

        $header = [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        
        $response = curl_exec($ch);
        curl_close($ch);

        return $response; 
    }
    catch (Exception $e) {
        handleError($e, "fetchFromTwitterAPI");
    }
}

function handleError($err, $method) {
    # test purposes only
    # echo $method . ": " . $err->getMessage();
    throw new Exception($method . ": " . $err->getMessage());

}
?>