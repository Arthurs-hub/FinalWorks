<?php
session_start();

require_once __DIR__ . '/autoload.php';

use Telegraph\Entities\TelegraphText;
use Telegraph\Entities\FileStorage;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function exceptionHandler($exception)
{
    $_SESSION['errorMessage'] = $exception->getMessage();

    header('Location: input_text.php');
    exit;
}
set_exception_handler('exceptionHandler');

$successMessage = $_SESSION['successMessage'] ?? '';
$errorMessage = $_SESSION['errorMessage'] ?? '';
unset($_SESSION['successMessage'], $_SESSION['errorMessage']);

$author = filter_input(INPUT_POST, 'author', FILTER_DEFAULT) ?? '';
$email = filter_input(INPUT_POST, 'email', FILTER_DEFAULT) ?? '';
$text = filter_input(INPUT_POST, 'text', FILTER_DEFAULT) ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $directory = __DIR__ . '/storage';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $storage = new FileStorage($directory);


        $text = filter_input(INPUT_POST, 'text', FILTER_DEFAULT) ?? '';


        if (trim($text) === '') {
            throw new \Exception('Текст не может быть пустым. Пожалуйста, введите текст.');
        }


        $telegraphText = new TelegraphText($author, $text);
        $storage->save($telegraphText);

        if (!empty($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Некорректный email. Пожалуйста, введите правильный адрес.');
            }

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'localhost';
            $mail->SMTPAuth = false;
            $mail->Port = 25;
            $mail->setFrom('no-reply@telegraph.com', 'Telegraph');
            $mail->addAddress($email);
            $mail->Subject = 'Ваш текст в Telegraph';
            $mail->Body = $text;

            $mail->send();
            $_SESSION['successMessage'] = 'Текст успешно создан и отправлен.';
        } else {
            $_SESSION['successMessage'] = 'Текст успешно создан.';
        }

        header('Location: input_text.php');
        exit;
    } catch (Exception $e) {

        $_SESSION['errorMessage'] = $e->getMessage();

        header('Location: input_text.php');
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить текст в Telegraph</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
    <script>
        window.onload = function () {

            setTimeout(() => {
                const errorBox = document.querySelector('.error-message');
                if (errorBox) {
                    errorBox.style.display = 'none';
                }

                const successBox = document.querySelector('.success-message');
                if (successBox) {
                    successBox.style.display = 'none';
                }
            }, 5000);
        };
    </script>
    <style>
        body {
            display: grid;
            place-items: center;
            height: 100vh;
            margin: 0;
        }

        form {
            margin-top: -250px;
            background-color: rgba(255, 255, 255, 0.82);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            width: 450px;
        }

        .success-message,
        .error-message {
            position: absolute;
            top: 10px;
            left: 30%;
            right: 30%;
            transform: translate(-50%, -50%);
            width: auto;
            text-align: center;
            padding: 0px;
            border-radius: 5px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body>
    <div class="message-container">
        <?php if ($successMessage): ?>
            <div class="success-message" style="background-color: lightgreen; padding: 10px; border: 1px solid green;">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="error-message"
                style="background-color: pink; color: black; font-weight: bold; padding: 10px; border: 1px solid darkred;">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>
    </div>
    <form method="POST" action="input_text.php">
        <h2>Добавить текст в Telegraph</h2>
        <label for="author">Автор:</label><br>
        <input type="text" class="form-control" id="author" name="author" value="<?= htmlspecialchars($author ?? '') ?>"
            required><br><br>

        <label for="text">Текст:</label><br>
        <textarea id="text" class="form-control" name="text"
            rows="5"><?= htmlspecialchars($text ?? '') ?></textarea><br><br>

        <label for="email">Email (необязательно):</label><br>
        <input type="text" class="form-control" id="email" name="email"
            value="<?= htmlspecialchars($email ?? '') ?>"><br><br>

        <input type="submit" class="btn btn-success" value="Отправить">
    </form>
</body>
<script>
    window.onload = function () {

        document.querySelectorAll('.success-message, .error-message').forEach(msg => {
            msg.style.opacity = '1';
            msg.style.transform = 'translateY(0)';
            msg.style.transition = 'opacity 0.5s ease';
        });


        setTimeout(() => {
            document.querySelectorAll('.success-message, .error-message').forEach(msg => {
                msg.style.opacity = '0';
                msg.style.transform = 'translateY(-1px)';
            });
        }, 5000);
    };
</script>

</html>