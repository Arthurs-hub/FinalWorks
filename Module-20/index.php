<?php

require_once 'User.php';

$user = new User();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $user->create($_POST);
            header("Location: index.php");
            exit;
        } elseif ($_POST['action'] === 'update' && isset($_POST['id'])) {
            $user->update($_POST['id'], $_POST);
            header("Location: index.php");
            exit;
        }
    }
}
if (isset($_GET['delete'])) {
    $user->delete($_GET['delete']);
    header("Location: index.php");
    exit;
}

$users = $user->list();
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Users CRUD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f9f9f9;
        }

        .center-container {
            max-width: 1200px;
            margin: 40px auto 0 auto;
            background: #fff;
            padding: 30px 40px 40px 40px;
            border-radius: 10px;
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.08);
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
        }

        form.inline {
            display: inline;
        }

        th.actions-col,
        td.actions-col {
            min-width: 160px;
        }
    </style>
</head>

<body>
    <div class="center-container">
        <h2>Список пользователей</h2>
        <table class="table table-bordered table-striped align-middle text-center">
            <thead class="table-light">
                <tr>
                    <th>Email</th>
                    <th>Имя</th>
                    <th>Фамилия</th>
                    <th>Возраст</th>
                    <th>Дата и время добавления</th>
                    <th class="actions-col">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <form class="inline" method="post" action="index.php">
                            <td><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($u['email']) ?>"></td>
                            <td><input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($u['first_name']) ?>"></td>
                            <td><input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($u['last_name']) ?>"></td>
                            <td><input type="number" class="form-control" name="age" value="<?= htmlspecialchars($u['age']) ?>"></td>
                            <td><?= htmlspecialchars($u['date_created']) ?></td>
                            <td class="actions-col">
                                <div class="d-flex gap-2 justify-content-center">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" name="action" value="update" class="btn btn-primary btn-sm">Edit</button>
                                    <a href="index.php?delete=<?= $u['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить пользователя?')">Delete</a>
                                </div>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Добавить пользователя</h2>
        <form method="post" action="index.php" class="row g-3 justify-content-center">
            <div class="col-md-3">
                <input type="email" class="form-control" name="email" placeholder="Email" required>
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control" name="first_name" placeholder="Имя" required>
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control" name="last_name" placeholder="Фамилия" required>
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control" name="age" placeholder="Возраст" required>
            </div>
            <div class="col-md-2">
                <button type="submit" name="action" value="create" class="btn btn-success w-100">Добавить</button>
            </div>
        </form>
    </div>
</body>

</html>