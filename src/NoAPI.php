<?php namespace alct\noapi;

class NoAPI
{
    public function twitter($query)
    {
        return (new Twitter)->twitter($query);
    }

    /**
     * Download and return a resource.
     *
     * @param string $url URL of the target page
     *
     * @return string|bool false on error
     */
    public static function curl($url)
    {
        $req = curl_init();

        $opt = [
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_COOKIEJAR      => tempnam(sys_get_temp_dir(), 'curl'),
            CURLOPT_FAILONERROR    => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => ['DNT: 1', 'Accept-Language: en-us,en;q=0.5'],
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL            => $url,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:48.0) Gecko/20100101 Firefox/48.0',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 10,
        ];

        curl_setopt_array($req, $opt);

        if (! $res = curl_exec($req)) return false;

        curl_close($req);

        // mb_convert_encoding is used to avoid encoding issues related to DOMDocument::loadHTML
        // see https://secure.php.net/manual/en/domdocument.loadhtml.php#52251
        return mb_convert_encoding($res, 'HTML-ENTITIES', 'UTF-8');
    }
}
