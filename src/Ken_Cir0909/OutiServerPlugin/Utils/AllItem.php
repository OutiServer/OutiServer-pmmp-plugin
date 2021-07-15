<?php

declare(strict_types=1);

namespace Ken_Cir0909\OutiServerPlugin\Utils;

class AllItem
{
    private array $items = array();

    public function __construct(string $path)
    {
        $json = file_get_contents($path);
        $this->items = json_decode($json, true);
    }

    public function GetItemIdByJaName(string $ja_name)
    {
        if(!isset($this->items["ja_name"][$ja_name])) return false;
        $data = $this->items["ja_name"][$ja_name];
        return $data["id"];
    }

    public function GetItemJaNameById(int $id)
    {
        if(!isset($this->items["id"][$id])) return false;
        $data = $this->items["id"][$id];
        if (!$data) return false;
        return $data["ja_name"];
    }
}