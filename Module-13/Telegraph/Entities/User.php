<?php

namespace Telegraph\Entities;
use Telegraph\Interfaces\IRender;
use Telegraph\Entities\FileStorage;
use Telegraph\Entities\Storage;
use Telegraph\Entities\TelegraphText;
use Telegraph\Entities\View;
abstract class User
{
    protected $name;
    protected $email;
    protected $password;
}
