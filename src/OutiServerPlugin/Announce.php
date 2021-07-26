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
            if(!$allannounce) {
                $player->sendMessage("現在運営からのお知らせはありません");
                return;
            }

            $form = new SimpleForm(function (Player $player, $data) use ($allannounce) {
                if ($data === null) return true;

                $this->Check($player, $allannounce[(int)$data]["id"]);

                return true;
            });

            $form->setTitle("iPhone-運営からのお知らせ");
            foreach ($allannounce as $key) {
                $form->addButton($key["title"]. " 追加日: " . $key["addtime"]);
            }
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    private function Check(Player $player, int $id)
    {
        try {
            $announcedata = $this->plugin->db->GetAnnounce($id);

            $form = new ModalForm(function (Player $player, $data) {
            });

            $form->setTitle("iPhone-運営からのお知らせ-" . $announcedata["title"]);
            $form->setContent("タイトル: " .$announcedata["title"] . "\n\n" . $announcedata["content"] . "\n\n追加日: " . $announcedata["addtime"]);
            $form->setButton1("OK");
            $form->setButton2("確認");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}