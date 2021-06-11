<?php

declare(strict_types=1);

namespace Ken_Cir0909\OutiMoney\Utils;

class Debug
{
    public function __construct()
    {
    }

    public function log(string $message)
    {
        echo $message . PHP_EOL;
    }
}