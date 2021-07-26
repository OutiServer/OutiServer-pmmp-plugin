<?php

declare(strict_types=1);

namespace OutiServerPlugin\Utils;

use ArgumentCountError;
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use OutiServerPlugin\Main;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use SQLiteException;
use TypeError;

class ErrorHandler
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onError($error, Player $player)
    {
        try {
            $player->sendMessage(TextFormat::RED . "プラグイン内で不明なエラーが発生しました。");
            $this->plugin->client->sendErrorLogMessage("```\n" . $error->getFile() . "の" . $error->getLine() . "行目でError\n" . $error->getMessage()  . "\n```");
            $this->plugin->getLogger()->error($error->getMessage());
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e){
            echo $error->getFile() . "の" . $error->getLine() . "行目でError\n" . $error->getMessage() . PHP_EOL;
        }
    }

    public function onErrorNotPlayer($error)
    {
        try {
            $this->plugin->client->sendErrorLogMessage("```\n" . $error->getFile() . "の" . $error->getLine() . "行目でError\n" . $error->getMessage()  . "\n```");
            $this->plugin->getLogger()->error($error->getMessage());
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            echo $error->getFile() . "の" . $error->getLine() . "行目でError\n" . $error->getMessage() . PHP_EOL;
        }
    }
}