<?php

'filepath: c:\xampp\htdocs\welcome\HTMLProcessor.php'
?>

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
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

function parseImagesFromHTML($html)
{
    $doc = new DOMDocument();
    @$doc->loadHTML($html); 
    $tags = $doc->getElementsByTagName('img');
    $images = [];
    foreach ($tags as $tag) {
        $images[] = $tag->getAttribute('src');
    }
    return $images;
}

$html = getHTMLFromURL($url);
$images = parseImagesFromHTML($html);

if (!empty($images)) {
    http_response_code(200);
    echo json_encode($images);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'No images found']);
}
