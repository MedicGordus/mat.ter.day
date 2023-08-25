# mat.ter.day
This tool helps people generate digital memories from 𝕏 content and interactions.

## rate limiting
10 000 get requests for posts, per 30d:

30 d  x  24 hr  x 60 min  = 43200 min
          1 d      1 hr

10000 get  =    1 get
43200 min    4.32 min

## secrets
keyfile.json should be like so:
{
    "key":"get your api key from https://developer.twitter.com/en/portal/dashboard",
    "secret":"get your api secret from https://developer.twitter.com/en/portal/dashboard"
}

## todo
Next step is to pull profile id of the 𝕏 poster.