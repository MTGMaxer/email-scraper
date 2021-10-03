<?php
require_once('config.php');
include_once('polyfill.php');
define("EMAIL_REGEX", "/[\w][\w._]*@[\w][\w-]*(\.[\w-]+)*\.[a-zA-Z-]{2,}/");
define("URL_REGEX", '/(https?:\/\/[\w-]+(?:\.[\w-]*[\w])*\.[a-zA-Z]{2,}[^\s"\'<]*)|((?<=href=").*?(?="))/');
define("IGNORED_EXTENSIONS", [".dtd", ".js", ".css", ".ico", ".png", ".jpg", ".pdf"]);

$all_urls = [];
$all_emails = [];
if (isset($_GET["url"])) {
    $url = $_GET["url"];
    if (!get_protocol($url)) {
        $url = 'http://' . $url;
    }
    $result_arr = ["url" => $url];
    scrap_url($result_arr);

    $result_arr["allEmails"] = array_values(array_unique($all_emails));
    print(json_encode($result_arr));
}

$depth = 0;

function scrap_url(&$url_obj)
{
    global $depth, $all_emails;
    $options = stream_context_create(["http" => [
        "timeout" => 5,
        'ignore_errors' => true,
        "method" => "GET",
        "header" => "Accept: */*\r\n" .
            "User-Agent: Chrome/93\r\n"
    ]]);
    $url_to_scan = $url_obj["url"];

    $content = @file_get_contents($url_to_scan, false, $options);
    if ($content === FALSE) return;
    $emails = [];
    $urls = [];
    find_emails($content, $emails, EMAIL_LIMIT);
    $url_obj["emails"] = $emails;
    array_push($all_emails, ...$emails);
    find_urls($content, $urls, URL_LIMIT, $url_to_scan, 'check_url');

    if ($depth < SCRAP_DEPTH) {
        $depth++;
        url_wrap($urls);
        $url_obj["urls"] = $urls;
        foreach ($url_obj["urls"] as &$url) {
            scrap_url($url);
        }
        $depth--;
    }
}

function url_wrap(&$url_arr)
{
    foreach ($url_arr as &$url) {
        $url = ["url" => $url];
    }
}

function check_url($url)
{
    $param_sep_pos = strpos($url, "?");
    $clear_url = $param_sep_pos ? substr($url, 0, $param_sep_pos) : $url;
    foreach (IGNORED_EXTENSIONS as $excl_ext) {
        if (str_ends_with($clear_url, $excl_ext)) {
            return false;
        }
    }
    return true;
}

function find_emails($document, &$result_arr, $limit)
{
    $offset = 0;
    for ($i = 0; $i < $limit; $i++) {
        $matches = [];
        if (preg_match(EMAIL_REGEX, $document, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $email = $matches[0][0];
            $result_arr[$i] = $email;
            $offset = $matches[0][1] + strlen($email) + 1;
        } else {
            return;
        }
    }
}

function find_urls($document, &$result_arr, $limit, $parent_url, $check_func)
{
    global $all_urls;
    $offset = 0;
    $i = 0;
    while ($i < $limit) {
        $matches = [];
        if (preg_match(URL_REGEX, $document, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $url = $matches[0][0];
            $offset = $matches[0][1] + strlen($url) + 1;
            $url = full_url($url, $parent_url);

            if ($check_func($url)) {
                if (!in_array($url, $all_urls)) {
                    array_push($all_urls, $url);
                    $result_arr[$i] = $url;
                    $i++;
                }
            }
        } else {
            return;
        }
    }
}

function full_url($url, $parent_url)
{
    if (str_starts_with($url, "//")) {
        return "http:" . $url;
    }
    if (str_starts_with($url, "/")) {
        return remove_path($parent_url) . $url;
    }

    if (!get_protocol($url)) {
        return url_dir($parent_url) . $url;
    }

    return $url;
}

function get_protocol($url)
{
    $colon_pos = strpos($url, ":");
    return substr($url, 0, $colon_pos);
}

function remove_path($url)
{
    $colon_pos = strpos($url, ":");
    $domain_start = $colon_pos + 3;
    $slash_pos = strpos($url, "/", $domain_start);
    if (!$slash_pos) {
        return substr($url, 0);
    }
    return substr($url, 0, $slash_pos);
}

function url_dir($url)
{
    $first_slash_pos = strpos($url, "/");
    $last_slash_pos = strrpos($url, "/");
    if ($last_slash_pos === $first_slash_pos + 1) {
        return $url . "/";
    }
    return substr($url, 0, $last_slash_pos + 1);
}
