<?php

declare(strict_types=1);

namespace OutiServerPlugin\plugins;

use ArgumentCountError;
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\SimpleForm;
use OutiServerPlugin\Main;
use OutiServerPlugin\plugins\Casinos\Slot;
use pocketmine\Player;
use TypeError;

class Casino
{
    private Main $plugin;
    public Slot $slot;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        $this->slot = new Slot($this->plugin);
    }

    public function Form(Player $player)
    {
        try {
            $form = new SimpleForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;

                    switch ($data) {
                        case 0:
                            $this->slot->Form($player);
                            break;
                    }
                    return true;
                }
                catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("iPhone-Casino");
            $form->addButton("スロット");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}