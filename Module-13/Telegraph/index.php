<?php

require_once __DIR__ . '/autoload.php';

use Telegraph\Entities\TelegraphText;
use Telegraph\Core\Templates\Spl;
use Telegraph\Core\Templates\Swig;
use Telegraph\Interfaces\IRender;
use Telegraph\Entities\User;
use Telegraph\Entities\View;


$telegraphText = new TelegraphText('Заголовок статьи', 'Текст статьи');
$telegraphText->setSlug('article-slug');

$spl = new Spl('telegraph_text.spl.txt');
$spl->addVariablesToTemplate(['slug', 'title', 'text']);

echo $spl->render($telegraphText) . "\n";

$swig = new Swig('telegraph_text.swig.txt');
$swig->addVariablesToTemplate(['slug', 'text']);

echo $swig->render($telegraphText) . "\n";
