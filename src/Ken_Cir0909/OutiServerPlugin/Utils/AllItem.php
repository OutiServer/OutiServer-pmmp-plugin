<?php

declare(strict_types=1);

namespace Ken_Cir0909\OutiServerPlugin;

class AllItem {
     public array $items = array();

    public function __construct(string $path)
    {
        $json = file_get_contents($path);
        $this->items = json_decode($json, true);
    }

    public function GetItemIdByJaName(string $ja_name)
    {
        $data = $this->items->ja_name[$ja_name];
        if(!$data) return false;
        return  $data->id;
    }
}