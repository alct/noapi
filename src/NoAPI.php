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

    /**
     * Create local copy of remote images.
     *
     * Hotlinking third party content raises privacy and security concerns. This
     * method allows for easy copying of remote images.
     *
     * Copying remote content raises other kind of security concerns, we
     * mitigate the risks by : checking the mime type against a predefined set ;
     * ignoring the original file name, extension and metadata ; enforcing part
     * of the path (prepending "noapi_" to the file name).
     *
     * @param string $url       URL of the remote image
     * @param string $filename  name of the target image
     * @param string $directory target directory without a ending slash
     * @param bool   $overwrite true to overwrite existing image
     *
     * @return string|bool false on error
     */
    public static function image_proxy($url, $filename, $directory, $overwrite = null)
    {
        $allowedmimetype = [ 'image/gif', 'image/jpeg', 'image/png', 'image/x-icon' ];

        $localpath = $directory . '/noapi_' . $filename;

        if (! $overwrite && file_exists($localpath)) return $localpath;

        $image = file_get_contents($url);
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimetype = $finfo->buffer($image);

        if (! in_array($mimetype, $allowedmimetype)) return false;

        if (file_put_contents($localpath, $image) === false) return false;

        return $localpath;
    }
}
