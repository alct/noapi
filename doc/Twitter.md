# Twitter

## Query

* `hashtag:foobar` or `#foobar`
* `search:foo bar` or `foo bar`
* `user:foobar` or `@foobar`

## Output

Note: the `user_stats` subarray is only provided for user pages.

```
(array)
    meta (array)
        query (string) see query_to_meta()
        url   (string) idem
        type  (string) idem
    tweets (array)
        (array)
            datetime (string) ISO 8601 date
            id       (int)    id of the tweet
            reply    (int)    1 if the tweet is a reply, 0 otherwise
            retweet  (int)    1 if the tweet is a retweet, 0 otherwise
            stats (array)
                fav (int) number of fav
                rt  (int) number of rt
            text (array)
                raw  (string) raw cleaned text
                html (string) cleaned and marked up text
            url (string) URL of the tweet
            user (array)
                avatar   (string) URL of the author's profile picture
                fullname (string) author's fullname
                name     (string) author's username
                url      (string) URL of the author's page
        (array)
        …
    tweets_count (int) number of tweets in the `tweets` array
    user_stats (array)
        followers (int) number of followers
        following (int) number of following
        likes     (int) number of likes
        lists     (int) number of lists
        tweets    (int) number of tweets
```

## Example

Save the following snippet in a file named `twitter-to-json.php`:

```php
<?php

require_once 'vendor/autoload.php';

use alct\noapi\NoAPI;

$noapi = new NoAPI;
$query = 'freedom of panorama';

if ($data = $noapi->twitter($query)) {

    // <optional>
    // create a local copy of users' profile pictures to avoid hotlinking (and tracking)
    // this is not mandatory, see Twitter::image_proxy() for more info

    // target directory
    $directory = 'img';

    // create directory if needed, make sure it is writable
    if (! is_dir($directory)) mkdir($directory, 0755, true);
    if (! is_writable($directory)) die('The target directory is not writable.' . PHP_EOL);

    foreach ($data['tweets'] as &$tweet) {

        $url = $tweet['user']['avatar'];
        $filename = $tweet['user']['name'];

        // by default, NoAPI::image_proxy() does not try to overwrite existing images
        // set the fourth argument to true to change this behaviour
        $tweet['user']['avatar'] = NoAPI::image_proxy($url, $filename, $directory);
    }
    // </optional>

    header('Content-Type: application/json; charset: utf-8');
    echo json_encode($data);

} else {

    echo 'An error has occured. Unable to collect data.';
}
```

Then, from the command line:

```bash
php twitter-to-json.php > output.json
```

Or, alternatively, access `twitter-to-json.php` from your web browser.
