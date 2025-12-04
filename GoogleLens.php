<?php 
readonly final class GoogleLens {

    public function get($url, $headers = []) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        if(!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($curl);
        return $response;
    }

    public function post($url, $postData, $headers = []) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);

        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);

        $response = curl_exec($curl);

        return $response;
    }

    public function detect($filepath, $wrapByPTag = false) :array 
    {
        $queryParams = [
            'ep' => 'gsbubb',
            'st' => round(microtime(true) * 1000),
            'authuser' => '0',
            'hl' => 'vi',
        ];
        $url = "https://lens.google.com/v3/upload?" . http_build_query($queryParams);

        $startMb = microtime(true);
        $contentFile = (strpos($filepath, 'http') !== false) ? $this->get($filepath) : file_get_contents($filepath);

        if ($contentFile === false || empty($contentFile)) {
            return [
                'message' => 'Could not read file content',
            ];
        }

        $totalMb = round(microtime(true) - $startMb, 2);

        // $contentType = finfo_buffer(finfo_open(), $contentFile, FILEINFO_MIME_TYPE);
        $contentType = 'image/jpeg';

        $boundary = "----WebKitFormBoundary";
        $postData = "--$boundary\r\n";
        $postData .= "Content-Disposition: form-data; name=\"encoded_image\"; \r\n";
        $postData .= "Content-Type: $contentType\r\n\r\n";
        $postData .= "$contentFile\r\n";
        $postData .= "--$boundary--\r\n";

        $start = microtime(true);
        $response = $this->post($url, $postData, [
            "Content-Type: multipart/form-data; boundary=$boundary",
            'Referer: https://lens.google.com/',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36',
        ]);
        
        preg_match('#vsrid=(.*?)\&#', $response, $vsrid);
        preg_match('#lsessionid=(.*?)\&#', $response, $lsessionid);

        if (!isset($vsrid[1]) || !isset($lsessionid[1])) {
            return [
                'time_read_file' => $totalMb,
                'time_request' => round(microtime(true) - $start, 2),
                'result' => '',
            ];
        }

        $url = "https://lens.google.com/qfmetadata?vsrid={$vsrid[1]}&lsessionid={$lsessionid[1]}";

        $cookie = '';
        preg_match_all('/^set-cookie:\s*([^;]*)/mi', $response, $matches);
        foreach($matches[1] as $item) {
            $cookie .= "$item; ";
        }

        $response = $this->get($url, [
            "Cookie: $cookie",
            'referer: https://www.google.com/',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36',
        ]);
        
        $response = str_replace(")]}'\n\n", '', $response);
        
        $json = json_decode($response, true);

        $list = $json[0][2][0][0] ?? [];

        $text = '';

        foreach ($list as $item) {
            foreach ($item[1] as $values) {
                $p = $values[0] ?? [];
                $newLine = '';
                foreach ($p as $v) {
                    $newLine .= "$v[1]$v[2]";
                }
                $newLine = $wrapByPTag ? "<p>$newLine</p>" : "$newLine ";
                $text .= $newLine;
            }
        }

        $total = round(microtime(true) - $start, 2);

        return [
            'time_read_file' => $totalMb,
            'time_request' => $total,
            'result' => trim($text),
        ];
    }

}