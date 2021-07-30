<?php

declare(strict_types=1);

namespace OutiServerPlugin\Tasks;

use ArgumentCountError;
use Error;
use Exception;
use InvalidArgumentException;
use pocketmine\scheduler\Task;
use TypeError;

// スロット用タスク
class SlotTask extends Task
{
    private $callable;
    private array $args = [];

    public function __construct(callable $callable, array $args = []){
        $this->callable = $callable;
        $this->args = $args;
    }

    public function onRun(int $tick){
        try {
            call_user_func_array($this->callable, $this->args);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e)  {
            echo "Error: " . $e->getMessage();
        }
    }
}