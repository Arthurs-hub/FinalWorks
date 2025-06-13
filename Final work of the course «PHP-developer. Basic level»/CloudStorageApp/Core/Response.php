<?php

namespace App\Core;

class Response
{
    private $data;
    private $status;

    public function __construct($data, $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
    }

    public function send()
    {
        http_response_code($this->status);
        header('Content-Type: application/json');
        error_log(json_encode($this->data));
        echo json_encode($this->data);
    }
}
