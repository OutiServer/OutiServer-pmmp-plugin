<?php

declare(strict_types=1);

namespace OutiServerPlugin;

use ArgumentCountError;
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\ModalForm;
use jojoe77777\FormAPI\SimpleForm;
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
            $allteleportworlds = $this->plugin->db->GetAllWorldTeleport();
            if(!$allteleportworlds) {
                $player->sendMessage("現在テレポートできるワールドは1つもないようです");
                return;
            }

            $form = new SimpleForm(function (Player $player, $data) use ($allteleportworlds) {
                if ($data === null) return true;

                $this->TeleportCheck($player, $allteleportworlds[(int)$data]["id"]);

                return true;
            });

            $form->setTitle("iPhone-Teleport");
            foreach ($allteleportworlds as $key) {
                $form->addButton($key["name"]);
            }
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    private function TeleportCheck(Player $player, int $id)
    {
        try {
            $worlddata = $this->plugin->db->GetWorldTeleport($id);
            $level = $this->plugin->getServer()->getLevelByName($worlddata["levelname"]);
            if(!$level) {
                $player->sendMessage("指定されたワールドが見つかりませんでした\nデータが破壊されているか、存在しません");
                return;
            }

            $pos = new Position($worlddata["x"], $worlddata["y"], $worlddata["z"], $level);

            $form = new ModalForm(function (Player $player, $data) use ($pos) {
                if ($data === true) {
                    $player->teleport($pos);
                    $player->sendMessage("テレポートしました");
                } elseif ($data === false) {
                    $player->sendMessage("テレポートしました");
                }
            });



            $form->setTitle("iPhone-Teleport-最終確認");
            $form->setContent("ワールド名: " .  $pos->getLevel()->getName() . "\nX座標: " . $pos->x . "\nY座標" . $pos->y . "\nZ座標" . $pos->z . "\nにある" . $worlddata["name"] . "にテレポートします、よろしいですか？");
            $form->setButton1("テレポートする");
            $form->setButton2("やめる");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}