# mat.ter.day
This tool helps people generate digital memories from 𝕏 content and interactions.

## rate limiting
10 000 get requests for posts, per 30d:

30 d  x  24 hr  x 60 min  = 43200 min
          1 d      1 hr

10000 get  =    1 get
43200 min    4.32 min

## secrets
secrets.json should be like so:
{
    "bearer-token":"get your bearer token from https://developer.twitter.com/en/portal/dashboard",
    "db-host":"",
    "db-name":"",
    "db-user":"",
    "db-password":""
}

Make sure to block access to this file, or move the secrets into your PHP files.