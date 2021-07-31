<?php

declare(strict_types=1);

namespace OutiServerPlugin\plugins;

use ArgumentCountError;
use Error;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\{CustomForm, SimpleForm};
use OutiServerPlugin\Main;
use pocketmine\Player;
use TypeError;

class Money
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function Form(Player $player)
    {
        try {
            $form = new SimpleForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;

                    switch ($data) {
                        case 0:
                            $name = $player->getName();
                            $playerdata = $this->plugin->db->GetMoney($name);
                            if (!$playerdata) break;
                            $player->sendMessage("あなたの現在の所持金: " . $playerdata["money"] . "円");
                            break;
                        case 1:
                            $this->MoveMoney($player);
                            break;
                    }
                    return true;
                }
                catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onErrorNotPlayer($e);
                }

                return true;
            });

            $form->setTitle("iPhone-Money");
            $form->addButton("所持金の確認");
            $form->addButton("他playerにお金を転送");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    private function MoveMoney(Player $player)
    {
        try {
            $name = $player->getName();
            $playermoney = $this->plugin->db->GetMoney($name);
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    if($data === null) return true;
                    else if(!isset($data[0]) or !is_numeric($data[1])) return true;

                    $name = $player->getName();
                    $this->plugin->db->AddMoney($data[0], (int)$data[1]);
                    $this->plugin->db->RemoveMoney($name, (int)$data[1]);
                    $player->sendMessage($data[0] . "に" . $data[1] . "円転送しました");
                    return true;
                }
                catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onErrorNotPlayer($e);
                }

                return true;
            });

            $form->setTitle("Money-他プレイヤーに所持金を転送");
            $form->addInput("転送先のプレイヤー名", "playername", "");
            $form->addSlider("転送するお金", 1, $playermoney["money"]);
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}