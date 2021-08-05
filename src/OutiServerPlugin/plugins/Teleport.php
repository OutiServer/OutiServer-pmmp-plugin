<?php

declare(strict_types=1);

namespace OutiServerPlugin\plugins;

use ArgumentCountError;
use Error;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\{ModalForm, SimpleForm};
use OutiServerPlugin\Main;
use OutiServerPlugin\Tasks\ReturnForm;
use pocketmine\level\Position;
use pocketmine\Player;
use TypeError;


class Teleport
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function Form(Player $player)
    {
        try {
            $alldata = $this->plugin->db->GetAllWorldTeleport();
            if(!$alldata) {
                $player->sendMessage("§b[ワールドテレポート] >> §4現在テレポートできるワールドは1つもないようです");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this->plugin->applewatch, "Form"], [$player]), 20);
                return;
            }
            $alltps = [];
            foreach ($alldata as $key) {
                if($key["oponly"] === 1 and $player->isOp()) {
                    $alltps[] = $key;
                }
                elseif ($key["oponly"] === 0) {
                    $alltps[] = $key;
                }
            }

            $form = new SimpleForm(function (Player $player, $data) use ($alltps) {
                try {
                    if ($data === null) return true;
                    elseif($data === 0) {
                        $this->plugin->applewatch->Form($player);
                        return true;
                    }

                    $this->TeleportCheck($player, $alltps[(int)$data - 1]["id"]);
                }
                catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-Teleport");
            $form->addButton("戻る");
            foreach ($alltps as $key) {
                $form->addButton($key["name"]);
            }
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function TeleportCheck(Player $player, int $id)
    {
        try {
            $worlddata = $this->plugin->db->GetWorldTeleport($id);
            if (!$this->plugin->getServer()->isLevelGenerated($worlddata["levelname"])) {
                $player->sendMessage("§b[ワールドテレポート] >> §4指定されたワールドが見つかりませんでした\nデータが破壊されているか、存在しません");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "Form"], [$player]), 20);
                return;
            }
            if (!$this->plugin->getServer()->isLevelLoaded($worlddata["levelname"])) {
                $this->plugin->getServer()->loadLevel($worlddata["levelname"]);
            }
            $level = $this->plugin->getServer()->getLevelByName($worlddata["levelname"]);
            $pos = new Position($worlddata["x"], $worlddata["y"], $worlddata["z"], $level);
            $form = new ModalForm(function (Player $player, $data) use ($pos) {
                try {
                    if ($data === true) {
                        $player->teleport($pos);
                        $player->sendMessage("§b[ワールドテレポート] >> §aテレポートしました");
                    } elseif ($data === false) {
                        $player->sendMessage("§b[ワールドテレポート] >> §cキャンセルしました");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "Form"], [$player]), 20);
                    }
                }
                catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-Teleport-最終確認");
            $form->setContent("ワールド名: " .  $pos->getLevel()->getName() . "\nX座標: " . $pos->x . "\nY座標" . $pos->y . "\nZ座標" . $pos->z . "\nにある" . $worlddata["name"] . "にテレポートします、よろしいですか？");
            $form->setButton1("テレポートする");
            $form->setButton2("やめる");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}