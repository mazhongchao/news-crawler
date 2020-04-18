<?php

class Util
{
    public static function absolute_url($url_path, $refer_url)
    {
        if (filter_var($refer_url, FILTER_VALIDATE_URL) == false){
            return false;
        }
        if (filter_var($url_path, FILTER_VALIDATE_URL)){
            return $url_path;
        }
        else {
            if (substr($url_path, 0, 2) == '//') {
                $path_arr = explode('/', $url_path);
                if (isset($path_arr[2])) {
                    if (preg_match ("/^([A-Za-z0-9-]+\.)+([A-Za-z]{2,4})$/i", $path_arr[2])) {
                        return "http:{$url_path}";
                    }
                }
                $url_path = substr($url_path, 1);
            }
            extract(parse_url($refer_url));
            $path = trim($path, "/");
            $path_arr = explode('/', $path);
            $last = end($path_arr);
            if (strpos($last, '.')!==false || strpos($last, '#')!==false || strpos($last, '?')) {
                $path = preg_replace('#/[^/]*$#', '', $path);
                array_pop($path_arr);
            }
            $rpc = count($path_arr);
            if (strpos($url_path, '../') !== false) {
                $upc = substr_count($url_path, '../');
                $pc = $rpc - $upc;
                $n_path_arr = array_slice($path_arr, 0, $pc);
                $n_path = join('/', $n_path_arr);
                if (!empty($n_path)) {
                    $n_path = "/$n_path";
                }
                $pos = strrpos($url_path, '../');
                $u_path = substr($url_path, $pos+2);
                $abs_url = "$scheme://$host$n_path$u_path";
            }
            else if (substr($url_path, 0, 2) == './') {
                $abs_url = "$scheme://$host/$path".substr($url_path, 1);
            }
            else if (substr($url_path, 0, 1) == '/'){
                $abs_url = "$scheme://$host$url_path";
            }
            else {
                $abs_url = "$scheme://$host/$url_path";
            }
            return $abs_url;
        }
    }
    public static function is_gb_html($html_source)
    {
        $matches = [];
        preg_match('/<meta([^>]*)charset\s*=\s*("|\')*\s*([\w-]+)/i', $html_source, $matches);
        if ($matches && isset($matches[3])) {
            return strtoupper(substr($matches[3], 0, 2)) == 'GB';
        }
        $encode = mb_detect_encoding($html_source, ['ASCII', 'EUC-CN', 'UTF-8']);
        if ('EUC-CN' == $encode) {
            return true;
        }
        return false;
    }
    public static function html_gb2utf8($html_source)
    {
        if (empty($html_source)) return $html_source;
        $html = mb_convert_encoding($html_source, "UTF-8", "GBK");
        $html = preg_replace('/<meta([^>]*)charset\s*=\s*("|\')*\s*([\w-]+)/i', '<meta$1charset=$2utf-8', $html);
        return $html;
    }

    public static function rel2abs($rel, $base) {
        // parse base URL  and convert to local variables: $scheme, $host,  $path
        extract(parse_url($base));

        if (strpos($rel,"//") === 0) {
            return $scheme . ':' . $rel;
        }

        // return if already absolute URL
        if (parse_url($rel, PHP_URL_SCHEME) != '') {
            return $rel;
        }

        // queries and anchors
        if ($rel[0] == '#' || $rel[0] == '?') {
            return $base . $rel;
        }

        // remove non-directory element from path
        $path = preg_replace('#/[^/]*$#', '', $path);

        // destroy path if relative url points to root
        if ($rel[0] ==  '/') {
            $path = '';
        }

        // dirty absolute URL
        $abs = $host . $path . "/" . $rel;

        // replace '//' or  '/./' or '/foo/../' with '/'
        $abs = preg_replace("/(\/\.?\/)/", "/", $abs);
        $abs = preg_replace("/\/(?!\.\.)[^\/]+\/\.\.\//", "/", $abs);

        // absolute URL is ready!
        return $scheme . '://' . $abs;
    }
}
