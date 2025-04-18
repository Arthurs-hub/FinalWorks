<?php

namespace Telegraph\Interfaces;

use Telegraph\Entities\TelegraphText;
interface IRender
    {
        public function render(TelegraphText $telegraphText): string;
    }
