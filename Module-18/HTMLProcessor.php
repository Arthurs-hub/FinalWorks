<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (empty($_POST['url'])) {
    http_response_code(400);
    echo json_encode(['error' => 'URL is required']);
    exit;
}

$url = $_POST['url'];

function getHTMLFromURL($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $response = curl_exec($ch);

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);


    if (preg_match('/charset=([\w-]+)/i', $headers, $matches)) {
        $charset = $matches[1];
        if (strtolower($charset) !== 'utf-8') {
            $body = iconv($charset, 'UTF-8//IGNORE', $body);
        }
    }

    curl_close($ch);
    return $body;
}

function parseImagesFromHTML($html, $baseUrl)
{
    $doc = new DOMDocument();

    $html = trim($html);

    libxml_use_internal_errors(true);
    @$doc->loadHTML($html);
    libxml_clear_errors();

    $tags = $doc->getElementsByTagName('img');
    $images = [];
    foreach ($tags as $tag) {
        $src = $tag->getAttribute('src');
        if (filter_var($src, FILTER_VALIDATE_URL)) {
            $images[] = $src;
        } else {
            $images[] = rtrim($baseUrl, '/') . '/' . ltrim($src, '/');
        }
    }
    return $images;
}

$html = getHTMLFromURL($url);
$html = trim($html);

if (empty($html)) {
    http_response_code(400);
    echo json_encode(['error' => 'Failed to fetch HTML from the URL']);
    exit;
}

$images = parseImagesFromHTML($html, $url);

if (!empty($images)) {
    http_response_code(200);
    echo json_encode($images);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'No images found']);
}
