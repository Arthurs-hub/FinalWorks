<?php

namespace Telegraph\Entities;

abstract class Storage
{
    abstract public function create($object);
    abstract public function read($slug);
    abstract public function update($slug, $updatedObject);
    abstract public function delete($slug);
    abstract public function list();
}
