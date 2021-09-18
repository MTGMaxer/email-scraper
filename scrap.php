<?php
define("EMAIL_REGEX", "/[\w][\w._]*@[\w][\w-]*(\.[\w-]+)*\.[a-zA-Z-]{2,}/");
define("URL_REGEX", '/(https?:\/\/[\w-]+(?:\.[\w-]*[\w])*\.[a-zA-Z]{2,}[^\s"\'<]*)|((?<=href=").*?(?="))/');
define("IGNORED_EXTENSIONS", [".dtd", ".js", ".css", ".ico", ".png", ".jpg", ".pdf"]);
define("SCRAP_DEPTH", 3);
define("EMAIL_LIMIT", 1000);
define("URL_LIMIT", 10);

$all_emails = [];
if (isset($_GET["url"])) {
    $url = $_GET["url"];
    if (!has_protocol($url)) {
        $url = 'http://' . $url;
    }
    $result_arr = ["url" => $url];
    scrap_url($result_arr, "");

    $result_arr["allEmails"] = array_values(array_unique($all_emails));
    print(json_encode($result_arr));
}

$depth = 0;

function scrap_url(&$url_obj, $parent_url)
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
    if (!has_protocol($url_to_scan)) {
        $url_to_scan = $parent_url . $url_to_scan;
    }

    $content = @file_get_contents($url_to_scan, false, $options);
    if ($content === FALSE) return;
    $emails = [];
    $urls = [];
    preg_match_all(EMAIL_REGEX, $content, $emails);
    preg_match_all(URL_REGEX, $content, $urls);
    $url_obj["emails"] = array_slice($emails[0], 0, EMAIL_LIMIT);
    array_push($all_emails, ...$url_obj["emails"]);
    if ($depth < SCRAP_DEPTH) {
        $depth++;
        $urls = array_filter($urls[0], "check_url");
        $urls = array_slice($urls, 0, URL_LIMIT);
        url_wrap($urls);
        $url_obj["urls"] = $urls;
        foreach ($url_obj["urls"] as &$url) {
            scrap_url($url, $url_to_scan);
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

function has_protocol($url)
{
    return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
}
