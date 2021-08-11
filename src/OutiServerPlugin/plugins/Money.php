<?php

declare(strict_types=1);

namespace OutiServerPlugin\plugins;

use ArgumentCountError;
use Error;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\{CustomForm, SimpleForm};
use OutiServerPlugin\Main;
use OutiServerPlugin\Tasks\ReturnForm;
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
                            $player->sendMessage("§a[経済] >> §6あなたの現在の所持金: " . $playerdata["money"] . "円");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "Form"], [$player]), 20);
                            break;
                        case 1:
                            $this->MoveMoney($player);
                            break;
                        case 2:
                            $this->plugin->applewatch->Form($player);
                            break;
                    }
                    return true;
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-Money");
            $form->addButton("所持金の確認");
            $form->addButton("他playerにお金を転送");
            $form->addButton("戻る");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function MoveMoney(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $this->Form($player);
                        return true;
                    } else if (!isset($data[1]) or !is_numeric($data[2])) return true;

                    $name = $player->getName();
                    $money = $this->plugin->db->GetMoney($name);
                    if ((int)$data[2] > $money["money"]) {
                        $player->sendMessage("§b[経済] >> §4お金が" . ($money["money"] - (int)$data[2]) * -1 . "円足りていませんよ？");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "MoveMoney"], [$player]), 20);
                        return true;
                    }
                    $this->plugin->db->AddMoney($data[1], (int)$data[2]);
                    $this->plugin->db->RemoveMoney($name, (int)$data[2]);
                    $player->sendMessage("§a[経済] >> §6$data[1]に$data[2]円転送しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "MoveMoney"], [$player]), 20);
                    return true;
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onErrorNotPlayer($e);
                }

                return true;
            });

            $form->setTitle("Money-他プレイヤーに所持金を転送");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("転送先のプレイヤー名", "playername", "");
            $form->addInput("転送するお金", "money", "1");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}
