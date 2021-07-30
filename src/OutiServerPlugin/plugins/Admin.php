<?php

declare(strict_types=1);

namespace OutiServerPlugin\plugins;

use ArgumentCountError;
use jojoe77777\FormAPI\{CustomForm, SimpleForm};
use DateTime;
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use OutiServerPlugin\Main;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use TypeError;

class Admin
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function AdminForm(Player $player)
    {
        try {
            $form = new SimpleForm(function (Player $player, $data) {
                if ($data === null) return true;

                switch ($data) {
                    case 0:
                        $this->AdminAddMoney($player);
                        break;
                    case 1:
                        $this->AdminRemoveMoney($player);
                        break;
                    case 2:
                        $this->AdminSetMoney($player);
                        break;
                    case 3:
                        $this->AddItemCategory($player);
                        break;
                    case 4:
                        $this->RemoveItemCategory($player);
                        break;
                    case 5:
                        $this->AddWorldTeleport($player);
                        break;
                    case 6:
                        $this->RemoveWorldTeleport($player);
                        break;
                    case 7:
                        $this->AddAnnounce($player);
                        break;
                    case 8:
                        $this->RemoveAnnounce($player);
                        break;
                    case 9:
                        $this->KenCir0909DB($player);
                        break;
                    case 10:
                        $this->plugin->client->sendDB();
                        break;
                }

                return true;
            });

            $form->setTitle("iPhone-管理");
            $form->addButton("プレイヤーにお金を追加");
            $form->addButton("プレイヤーからお金を剥奪");
            $form->addButton("プレイヤーのお金を設定");
            $form->addButton("アイテムカテゴリーの追加");
            $form->addButton("アイテムカテゴリーの削除");
            $form->addButton("テレポートの追加");
            $form->addButton("テレポートの削除");
            $form->addButton("アナウンスの追加");
            $form->addButton("アナウンスの削除");
            if (strtolower($player->getName()) === 'kencir0909') {
                $form->addButton("db接続");
                $form->addButton("db送信");
            }
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function AdminAddMoney(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                if ($data === null) return true;
                elseif (!isset($data[0]) or !is_numeric($data[1])) return true;
                else if (!Player::isValidUserName($data[0])) return true;
                $this->plugin->db->AddMoney($data[0], (int)$data[1]);
                $player->sendMessage($data[0] . "に" . $data[1] . "円追加しました");
            });

            $form->setTitle("iPhone-管理-プレイヤーにお金を追加");
            $form->addInput("追加するプレイヤー名", "player", "");
            $form->addInput("追加するお金", "addmoney", "0");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function AdminRemoveMoney(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                if ($data === null) return true;
                elseif (!isset($data[0]) or !is_numeric($data[1])) return true;
                else if (!Player::isValidUserName($data[0])) return true;
                $this->plugin->db->RemoveMoney($data[0], (int)$data[1]);
                $player->sendMessage($data[0] . "から" . $data[1] . "円剥奪しました");
            });

            $form->setTitle("iPhone-管理-プレイヤーからお金を剥奪");
            $form->addInput("剥奪するプレイヤー名", "player", "");
            $form->addInput("剥奪するお金", "addmoney", "0");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function AdminSetMoney(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                if ($data === null) return true;
                elseif (!isset($data[0]) or !is_numeric($data[1])) return true;
                else if (!Player::isValidUserName($data[0])) return true;
                $this->plugin->db->UpdateMoney($data[0], (int)$data[1]);
                $player->sendMessage($data[0] . "の所持金を" . $data[1] . "円設定しました");
            });

            $form->setTitle("iPhone-管理-プレイヤーのお金をセット");
            $form->addInput("セットするプレイヤー名", "player", "");
            $form->addInput("セットするお金", "setmoney", "0");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function KenCir0909DB(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                if ($data === null) return;
                elseif (!isset($data[0])) return;
                try {
                    $result = $this->plugin->db->db->query($data[0]);
                    $data = $result->fetchArray();
                    var_dump($data);
                } catch (Exception $ex) {
                    $player->sendMessage("ERROR!\n" . $ex->getMessage());
                }
            });

            $form->setTitle("iPhone-管理-db接続");
            $form->addInput("クエリ", "query", "");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function AddItemCategory(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                if($data === null) return true;
                else if(!isset($data[0])) return true;

                $this->plugin->db->AddItemCategory($data[0]);
                $player->sendMessage($data[0] . "をアイテムカテゴリーに追加しました");

                return true;
            });

            $form->setTitle("Admin-アイテムカテゴリー追加");
            $form->addInput("追加するアイテムカテゴリーの名前", "additemcategoryname", "");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function RemoveItemCategory(Player $player)
    {
        try {
            $ItemCategorys = $this->plugin->db->GetAllItemCategory();
            $allCategorys = [];
            foreach ($ItemCategorys as $key) {
                $allCategorys[] = $key["name"];
            }

            $form = new CustomForm(function (Player $player, $data) use ($ItemCategorys) {
                if($data === null) return true;
                else if(!is_numeric($data[0])) return true;

                $this->plugin->db->RemoveItemCategory($ItemCategorys[(int)$data[0]]["id"]);
                $player->sendMessage($ItemCategorys[(int)$data[0]]["name"] . "をアイテムカテゴリーから削除しました");
            });

            $form->setTitle("Admin-アイテムカテゴリー削除");
            $form->addDropdown("アイテムカテゴリー", $allCategorys);
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function AddWorldTeleport(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                if($data === null) return true;
                else if(!isset($data[0])) return true;

                $pos = $player->getPosition();
                $this->plugin->db->SetWorldTeleport($data[0], $pos);
                $player->sendMessage("テレポート地点を追加しました");

                return true;
            });

            $form->setTitle("Admin-ワールドテレポートの追加");
            $form->addInput("テレポート名", "name", "");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function RemoveWorldTeleport(Player $player)
    {
        try {
            $allteleportworlds = $this->plugin->db->GetAllWorldTeleport();
            if(!$allteleportworlds) {
                $player->sendMessage("現在テレポートできるワールドは1つもないようです");
                return;
            }
            $teleportworlds = [];
            foreach ($allteleportworlds as $key) {
                $teleportworlds[] = $key["name"];
            }

            $form = new CustomForm(function (Player $player, $data) use ($allteleportworlds) {
                if($data === null) return true;
                else if(!is_numeric($data[0])) return true;

                $this->plugin->db->DeleteWorldTeleport($allteleportworlds[(int)$data[0]]["id"]);
                $player->sendMessage($allteleportworlds[(int)$data[0]]["name"] . "をテレポートから削除しました");
            });

            $form->setTitle("Admin-テレポート削除");
            $form->addDropdown("テレポート先", $teleportworlds);
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function AddAnnounce(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                if($data === null) return true;
                else if(!isset($data[0]) or !isset($data[1])) return true;

                $time = new DateTime('now');
                $this->plugin->db->AddAnnounce($time->format("Y年m月d日 H時i分"), $data[0], $data[1]);
                $player->sendMessage("運営からのお知らせ " . $data[0] . " を追加しました");
                Server::getInstance()->broadcastMessage(TextFormat::YELLOW . "[運営より] 運営からのお知らせが追加されました、ご確認ください。");
                $this->plugin->client->sendChatMessage("__**[運営より] 運営からのお知らせが追加されました、ご確認ください。**__\n");

                return true;
            });

            $form->setTitle("Admin-アナウンス追加");
            $form->addInput("タイトル", "title", "");
            $form->addInput("説明", "content", "");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function RemoveAnnounce(Player $player)
    {
        try {
            $allannounce = $this->plugin->db->GetAllAnnounce();
            if(!$allannounce) {
                $player->sendMessage("現在運営からのお知らせはありません");
                return;
            }

            $announces = [];
            foreach ($allannounce as $key) {
                $announces[] = $key["title"];
            }

            $form = new CustomForm(function (Player $player, $data) use ($allannounce) {
                if($data === null) return true;
                else if(!is_numeric($data[0])) return true;

                $this->plugin->db->DeleteAnnounce($allannounce[(int)$data[0]]["id"]);
                $player->sendMessage($allannounce[(int)$data[0]]["title"] . "をアナウンスから削除しました");
            });

            $form->setTitle("Admin-アナウンス削除");
            $form->addDropdown("アナウンス", $announces);
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}