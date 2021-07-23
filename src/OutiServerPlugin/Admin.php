<?php

declare(strict_types=1);

namespace OutiServerPlugin;

use jojoe77777\FormAPI\{CustomForm, SimpleForm};
use Exception;
use pocketmine\Player;

class Admin
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function AdminForm(Player $player)
    {
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
                    $this->KenCir0909DB($player);
                    break;
                case 6:
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
        if (strtolower($player->getName()) === 'kencir0909') {
            $form->addButton("db接続");
            $form->addButton("db送信");
        }
        $player->sendForm($form);
    }

    private function AdminAddMoney(Player $player)
    {
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

    private function AdminRemoveMoney(Player $player)
    {
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

    private function AdminSetMoney(Player $player)
    {
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

    private function KenCir0909DB(Player $player)
    {
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

    private function AddItemCategory(Player $player)
    {
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

    private function RemoveItemCategory(Player $player)
    {
        $ItemCategorys = $this->plugin->db->GetAllItemCategory();
        $allCategorys = [];
        foreach ($ItemCategorys as $key) {
            $allCategorys[] = $key["name"];
        }

        $form = new CustomForm(function (Player $player, $data) use ($allCategorys) {
            if($data === null) return true;
            else if(!is_numeric($data[0])) return true;

            $this->plugin->db->RemoveItemCategory((int)$data[0] + 1);
            $player->sendMessage($allCategorys[(int)$data[0]] . "をアイテムカテゴリーから削除しました");
        });

        $form->setTitle("Admin-アイテムカテゴリー追加");
        $form->addDropdown("アイテムカテゴリー", $allCategorys);
        $player->sendForm($form);
    }
}