<?php


class User
{
    private $connection;

    public function __construct()
    {
        $this->connection = new PDO(
            "mysql:host=localhost;dbname=test_users_db;charset=utf8",
            "root",
            "mysqlpas123",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    public function create($data)
    {
        $stmt = $this->connection->prepare(
            "INSERT INTO Users (email, first_name, last_name, age, date_created) VALUES (:email, :first_name, :last_name, :age, NOW())"
        );
        $stmt->execute([
            ':email' => $data['email'],
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':age' => $data['age']
        ]);
    }

    public function update($id, $data)
    {
        $stmt = $this->connection->prepare(
            "UPDATE Users SET email=:email, first_name=:first_name, last_name=:last_name, age=:age WHERE id=:id"
        );
        $stmt->execute([
            ':email' => $data['email'],
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':age' => $data['age'],
            ':id' => $id
        ]);
    }

    public function delete($id)
    {
        $stmt = $this->connection->prepare("DELETE FROM Users WHERE id=:id");
        $stmt->execute([':id' => $id]);
    }

    public function list()
    {
        $stmt = $this->connection->query("SELECT * FROM Users ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
