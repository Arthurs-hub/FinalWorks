<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Загрузка фотографии</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
    <style>
        body {
            margin: 0;
            background-color: #f8f9fa;
            height: 20vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .container {
            position: fixed;
            top: 5%;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            background-color: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            width: 450px;
        }

        h1 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 30px;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .form-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        input[type="file"] {
            flex: 1;
        }

        button {
            white-space: nowrap;
            padding: 3px 10px !important;
            font-size: 16px !important;
            width: 105px !important;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Загрузка фотографии</h1>
        <?php if (!empty($error)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="post" action="send_photo.php" enctype="multipart/form-data">
            <label for="photo">Выберите файл (JPG, PNG, не более 2 МБ):</label>
            <div class="form-row">
                <input type="file" name="photo" id="photo" required>
                <button type="submit" class="btn btn-success">Загрузить</button>
            </div>
        </form>
    </div>
</body>

</html>
