<?php


namespace OutiServerPlugin\plugins;

use ArgumentCountError;
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\SimpleForm;
use OutiServerPlugin\Main;
use pocketmine\Player;
use TypeError;

class OutiWatch
{
    private Main $plugin;
    public array $check;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function Form(Player $player)
    {
        try {
            $form = new SimpleForm(function (Player $player, $data) {
                try {
                    unset($this->check[$player->getName()]);
                    if ($data === null) return true;

                    switch ($data) {
                        case 0:
                            break;
                        case 1:
                            $this->plugin->money->Form($player);
                            break;
                        case 2:
                            $this->plugin->adminshop->AdminShopMenuCategory($player);
                            break;
                        case 3:
                            $this->plugin->land->land($player);
                            break;
                        case 4:
                            $this->plugin->teleport->Form($player);
                            break;
                        case 5:
                            $this->plugin->announce->Form($player);
                            break;
                        case 6:
                            $this->plugin->casino->Form($player);
                            break;
                        case 7:
                            $this->plugin->admin->AdminForm($player);
                            break;
                    }
                    return true;
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch");
            $form->addButton("?????????");
            $form->addButton("????????????");
            $form->addButton("AdminShop");
            $form->addButton("??????");
            $form->addButton("???????????????");
            $form->addButton("???????????????????????????");
            if ($player->isOp()) {
                $form->addButton("?????????");
                $form->addButton("?????????");
            }
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
}