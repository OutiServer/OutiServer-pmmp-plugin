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
    private array $args;

    public function __construct(callable $callable, array $args = [])
    {
        $this->callable = $callable;
        $this->args = $args;
    }

    public function onRun(int $currentTick)
    {
        try {
            call_user_func_array($this->callable, $this->args);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $error) {
            echo $error->getFile() . "の" . $error->getLine() . "行目でError\n" . $error->getMessage() . PHP_EOL;
        }
    }
}