<?php

declare(strict_types=1);

namespace OutiServerPlugin\Utils;

use ArgumentCountError;
use Error;
use Exception;
use InvalidArgumentException;
use OutiServerPlugin\Main;
use TypeError;

class AllItem
{
    private array $items;
    private Main $plugin;

    public function __construct(Main $plugin, string $path)
    {
        $this->plugin = $plugin;

        try {
            $json = file_get_contents($path);
            $this->items = json_decode($json, true);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function GetItemIdByJaName(string $ja_name)
    {
        try {
            if (!isset($this->items["ja_name"][$ja_name])) return false;
            $data = $this->items["ja_name"][$ja_name];
            return $data["id"];
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function GetItemJaNameById(int $id)
    {
        try {
            if (!isset($this->items["id"][$id])) return false;
            $data = $this->items["id"][$id];
            if (!$data) return false;
            return $data["ja_name"];
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }
}