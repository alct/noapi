<?php namespace alct\noapi;

class Twitter
{
    /**
     * Add HTML markup around @mentions, #hashtags and URLs ; convert \n to <br/>.
     *
     * @param  string $string
     * @return string
     */
    protected function html($string)
    {
        $pattern = [
            '~(https?://(?:w{3}\d*\.)?([^\s]+))~i' => '<a href="$1" class="url">$2</a>',
            '~(?<=[^\w]|^)@(\w+)(?=[^\w]|$)~i' => '<a href="https://twitter.com/$1" class="mention">@<span>$1</span></a>',
            '~(?<=[^\w]|^)#(\w+)(?=[^\w]|$)~iu' => '<a href="https://twitter.com/hashtag/$1" class="hashtag">#<span>$1</span></a>',
            '~\n~' => '<br/>',
        ];

        return preg_replace(array_keys($pattern), array_values($pattern), $string);
    }

    /**
     * Extract a series of information from a twitter page.
     *
     * See doc/Twitter.md for the detailed structure of the returned array.
     *
     * @param  string     $page content of a twitter page
     * @param  array      $meta see query_to_meta()
     * @return array|bool       false on error
     */
    protected function parse($page, $meta)
    {
        $twitter['meta'] = $meta;

        $tweet = '//ol[@id="stream-items-id"]/li[@data-item-type="tweet"]';

        // foo_bar will be converted to foo['bar']
        $tweet_details = [
            'user_avatar'   => './/img[contains(@class, "avatar")]/@src',
            'user_fullname' => './/@data-name',
            'user_name'     => './/@data-screen-name',
            'stats_fav'     => './/span[contains(@class, "ProfileTweet-action--favorite")]//@data-tweet-stat-count',
            'stats_rt'      => './/span[contains(@class, "ProfileTweet-action--retweet")]//@data-tweet-stat-count',
            'id'            => './/@data-tweet-id',
            'retweetid'     => './/@data-retweet-id',
            'text'          => './/p[contains(@class, "tweet-text")]',
            'datetime'      => './/@data-time',
        ];

        $user_stats = '//ul[contains(@class, "ProfileNav-list")]';

        $user_stats_details = [
            'followers' => './/li[contains(@class, "ProfileNav-item--followers")]/a/@title',
            'following' => './/li[contains(@class, "ProfileNav-item--following")]/a/@title',
            'likes'     => './/li[contains(@class, "ProfileNav-item--favorites")]/a/@title',
            'lists'     => './/li[contains(@class, "ProfileNav-item--lists")]/a/@title',
            'tweets'    => './/li[contains(@class, "ProfileNav-item--tweets")]/a/@title',
        ];

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadHTML($page);

        $xpath = new \DomXpath($dom);

        foreach ($xpath->query($tweet) as $item) {

            foreach ($tweet_details as $key => $query) {

                $value = @$xpath->query($query, $item)->item(0)->textContent;

                if ($key == 'retweetid') {

                    $details['retweet'] = empty($value) ? 0 : 1;

                    continue;
                }

                if ($key == 'text') {

                    $cleanup = [
                        '~\xc2\xa0~'               => '',   // remove non breaking spaces
                        '~\s{2,}~U'                => ' ',  // replace consecutive spaces by a single one
                        '~(https?://[^\s]+) ?…~iU' => '$1', // remove elipsis after URLs
                        '~pic\.twitter\.com~iU'    => ' https://pic.twitter.com',
                    ];

                    $value = preg_replace(array_keys($cleanup), array_values($cleanup), $value);

                    $details['text']['raw'] = $value;
                    $details['text']['html'] = $this->html($value);

                    $details['reply'] = substr($value, 0, 1) === '@' ? 1 : 0;

                    continue;
                }

                if ($key == 'datetime') $value = date('c', $value);

                if (strpos($key, '_') !== false) {

                    $subarray = explode('_', $key);
                    $subname  = $subarray[0];
                    $subkey   = $subarray[1];

                    $details[$subname][$subkey] = $value;

                } else {

                    $details[$key] = $value;
                }
            }

            $details['user']['url'] = 'https://twitter.com/' . $details['user']['name'];
            $details['url'] = $details['user']['url'] . '/status/' . $details['id'];

            // type casting
            $details['id'] = (int) $details['id'];
            $details['stats']['fav'] = (int) $details['stats']['fav'];
            $details['stats']['rt']  = (int) $details['stats']['rt'];

            ksort($details);

            $twitter['tweets'][] = $details;
        }

        $twitter['tweets_count'] = count($twitter['tweets']);

        if ($meta['type'] == 'user') {

            $item = $xpath->query($user_stats)->item(0);

            foreach ($user_stats_details as $key => $query) {

                $value = @$xpath->query($query, $item)->item(0)->textContent;

                $twitter['user_stats'][$key] = preg_match('~([\d,]+)~', $value, $matches) ? (int) strtr($matches[1], [',' => '']) : 0;
            }
        }

        ksort($twitter);

        return $twitter;
    }

    /**
     * Convert a query to metadata.
     *
     * See doc/Twitter.md
     *
     * Return an array with the following structure:
     *
     * (array)
     *     type  (string) hashtag, search or user
     *     url   (string) URL of the target page
     *     query (string)
     *
     * @param  string $query
     * @return array
     */
    protected function query_to_meta($query)
    {
        $query = str_replace(' ', '+', $query);

        if (preg_match('~^(?:hashtag:|#)(\w+)$~i', $query, $matches)) {

            $type = 'hashtag';
            $url  = 'https://twitter.com/hashtag/' . $matches[1] . '?f=tweets';

        } elseif (preg_match('~^(?:user:|@)(\w+)$~i', $query, $matches)) {

            $type = 'user';
            $url  = 'https://twitter.com/' . $matches[1];

        } elseif (preg_match('~^(?:search:)(.+)$~i', $query, $matches)) {

            $type = 'search';
            $url  = 'https://twitter.com/search?f=tweets&q=' . $matches[1];

        } else {

            $type = 'search';
            $url  = 'https://twitter.com/search?f=tweets&q=' . $query;
        }

        return [ 'query' => $query, 'type' => $type, 'url' => $url ];
    }

    /**
     * Download and parse the twitter page corresponding to a query.
     *
     * Return an array, see doc/Twitter.md for details.
     *
     * @param  string     $query see query_to_meta()
     * @return array|bool        false on error
     */
    public function twitter($query)
    {
        $meta = $this->query_to_meta($query);

        if (! $page = NoAPI::curl($meta['url'])) return false;

        return $this->parse($page, $meta);
    }
}
