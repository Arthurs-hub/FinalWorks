<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Parser</title>
</head>

<body>
    <h1>Image Parser</h1>
    <form method="POST" action="index.php">
        <label for="url">Введите URL:</label>
        <input type="text" id="url" name="url" required>
        <button type="submit">Обработать</button>
    </form>
   

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $url = $_POST['url'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/php-developer-base-1/Module-18/HTMLProcessor.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        $data = ['url' => $url];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $images = json_decode($response, true);
            echo '<div>';
            foreach ($images as $image) {
                echo "<img src=\"$image\" alt=\"Image\" style=\"max-width: 200px; margin: 10px;\">";
            }
            echo '</div>';
        } elseif ($httpCode === 404) {
            echo '<p>Картинки не найдены</p>';
        } else {
            echo '<p>Произошла ошибка при обработке запроса</p>';
        }
    }
    ?>
</body>

</html>