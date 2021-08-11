<?php

declare(strict_types=1);

namespace OutiServerPlugin\plugins;

use ArgumentCountError;
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\{ModalForm, SimpleForm};
use OutiServerPlugin\Main;
use pocketmine\Player;
use TypeError;

class Announce
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function Form(Player $player)
    {
        try {
            $allannounce = $this->plugin->db->GetAllAnnounce();
            if (!$allannounce) {
                $player->sendMessage("§b[運営からのお知らせ] >> §4現在運営からのお知らせはありません");
                return;
            }

            $form = new SimpleForm(function (Player $player, $data) use ($allannounce) {
                try {
                    if ($data === null) return true;
                    elseif ($data === 0) {
                        $this->plugin->applewatch->Form($player);
                        return true;
                    }
                    $this->Check($player, $allannounce[(int)$data - 1]["id"]);
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-運営からのお知らせ");
            $form->addButton("戻る");
            foreach ($allannounce as $key) {
                $form->addButton($key["title"] . " 追加日: " . $key["addtime"]);
            }
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function Check(Player $player, int $id)
    {
        try {
            $announcedata = $this->plugin->db->GetAnnounce($id);

            $form = new ModalForm(function (Player $player, $data) {
                if ($data === null) return true;
                $this->Form($player);
            });

            $form->setTitle("OutiWatch-運営からのお知らせ-" . $announcedata["title"]);
            $form->setContent("タイトル: " . $announcedata["title"] . "\n\n" . $announcedata["content"] . "\n\n追加日: " . $announcedata["addtime"]);
            $form->setButton1("確認");
            $form->setButton2("確認");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}