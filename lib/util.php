<?php

class Util
{
    public static function absolute_url($url, $refer_url)
    {
        if (filter_var($refer_url, FILTER_VALIDATE_URL) == false){
            return false;
        }

        if (filter_var($url_path, FILTER_VALIDATE_URL)){
            return $url_path;
        }
        else {
            $refer_arr = parse_url($refer_url);
            $scheme = $refer_arr['scheme'];
            $host = $refer_arr['host'];
            $path = trim($refer_arr['path'], '/');
            $path_arr = explode('/', $path);
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
                $refer_url = rtrim($refer_url, '/');
                $abs_url = $refer_url.substr($url_path, 1);
            }
            else if (substr($url_path, 0, 1) == '/'){
                $abs_url = "$scheme://$host$url_path";
            }
            else {
                return false;
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
}
