<?php

declare(strict_types=1);

namespace OutiServerPlugin;

use jojoe77777\FormAPI\{CustomForm, ModalForm, SimpleForm};
use pocketmine\item\Item;
use pocketmine\Player;

class AdminShop
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function AdminShop(Player $player)
    {
        $form = new SimpleForm(function (Player $player, $data) {
            if ($data === null) return true;

            switch ($data) {
                case 0:
                    $this->AdminShopMenuCategory($player);
                    break;
                case 1:
                    $this->AdminShopSetprice($player);
                    break;
            }

            return true;
        });

        $form->setTitle("iPhone-AdminShop");
        $form->addButton("メニュー");
        if ($player->isOp()) {
            $form->addButton("設定");
        }
        $player->sendForm($form);
    }

    private function AdminShopSetprice(Player $player)
    {
        $ItemCategorys = $this->plugin->db->GetAllItemCategory();
        $allCategorys = [];
        foreach ($ItemCategorys as $key) {
            $allCategorys[] = $key["name"];
        }

        $form = new CustomForm(function (Player $player, $data) use ($ItemCategorys) {
            if ($data === null) return true;
            else if (!is_numeric($data[1]) or !is_numeric($data[2]) or !isset($data[0]) or !is_numeric($data[3])) return true;
            $itemid = $this->plugin->allItem->GetItemIdByJaName($data[0]);
            if (!$itemid) {
                $player->sendMessage("アイテムが見つかりませんでした");
                return true;
            }
            $item = Item::get($itemid);
            if (!$item) return true;

            $itemdata = $this->plugin->db->GetAdminShop($item);
            if (!$itemdata) {
                $this->plugin->db->SetAdminShop($item, $data[1], $data[2], (int)$data[3] + 1);
            } else {
                $this->plugin->db->UpdateAdminShop($item, $data[1], $data[2], (int)$data[3] + 1);
            }

            $player->sendMessage("設定しました");

            return true;
        });

        $form->setTitle("iPhone-AdminShop-値段設定");
        $form->addInput("値段設定するアイテム名", "itemname", "");
        $form->addInput("値段", "buyprice", "1");
        $form->addInput("売却値段", "sellprice", "1");
        $form->addDropdown("アイテムカテゴリー", $allCategorys);
        $player->sendForm($form);
    }

    private function AdminShopMenuCategory(Player $player)
    {
        $alldata = $this->plugin->db->GetAllItemCategory();
        if (!$alldata) {
            $player->sendMessage("現在AdminShopでは何も売られていないようです");
            return;
        }

        $form = new SimpleForm(function (Player $player, $data) use ($alldata) {
            if ($data === null) return true;
            $Categoryid = $alldata[(int)$data]["id"];
            $this->AdminShopMenu($player, $Categoryid);
            return true;
        });

        $form->setTitle("iPhone");
        $form->setContent("AdminShop-カテゴリー");

        for ($i = 0; $i < count($alldata); $i++) {
            $form->addButton($alldata[$i]["name"]);
        }

        $player->sendForm($form);
    }

    private function AdminShopMenu(Player $player, int $CategoryId)
    {
        $alldata = $this->plugin->db->AllAdminShop($CategoryId);
        if (!$alldata) {
            $player->sendMessage("現在そのカテゴリーでは何も売られていないようです");
            return;
        }

        $form = new SimpleForm(function (Player $player, $data) use ($alldata) {
            if ($data === null) return true;

            $itemdata = $alldata[$data];

            $this->SelectAdminShop($player, $itemdata);

            return true;
        });

        $form->setTitle("iPhone");
        $form->setContent("AdminShop-メニュー");

        for ($i = 0; $i < count($alldata); $i++) {
            $item = Item::get($alldata[$i]["itemid"], $alldata[$i]["itemmeta"]);
            $itemname = $this->plugin->allItem->GetItemJaNameById($item->getId());
            $form->addButton($itemname . ": " . $alldata[$i]["buyprice"] . "円 売却値段: " . $alldata[$i]["sellprice"] . "円");
        }

        $player->sendForm($form);
    }

    private function SelectAdminShop(Player $player, $itemdata)
    {
        $form = new CustomForm(function (Player $player, $data) use ($itemdata) {
            if ($data === null) return true;

            $item = Item::get($itemdata["itemid"], $itemdata["itemmeta"], (int)$data[1]);
            if ($data[0] === 0) {
                if ($player->getInventory()->canAddItem($item)) {
                    $this->AdminShopBuyCheck($player, $item, $itemdata);
                } else {
                    $player->sendMessage("インベントリの空き容量が足りません");
                }
            } elseif ($data[0] === 1) {
                if ($player->getInventory()->contains($item)) {
                    $this->AdminShopSellCheck($player, $item, $itemdata);
                } else {
                    $player->sendMessage("自分の所持しているアイテム以上のアイテムを売却することはできません");
                }
            }

            return true;
        });

        $form->setTitle("iPhone-AdminShop-購入・売却");
        $form->addDropdown("購入・売却", ["購入", "売却"]);
        $form->addSlider("購入・売却する個数", 1, 64);
        $player->sendForm($form);
    }

    private function AdminShopBuyCheck(Player $player, $item, $itemdata)
    {
        $price = $item->getCount() * $itemdata["buyprice"];

        $form = new ModalForm(function (Player $player, $data) use ($item, $price) {
            if ($data === true) {
                $name = $player->getName();
                $playerdata = $this->plugin->db->GetMoney($name);
                if ($price > $playerdata["money"]) {
                    $player->sendMessage("お金が" . ($playerdata["money"] - $price) * -1 . "円足りていませんよ？");
                    return;
                }

                $this->plugin->db->RemoveMoney($name, $price);
                $player->getInventory()->addItem($item);
                $player->sendMessage("購入しました");
            } elseif ($data === false) {
                $player->sendMessage("購入しませんでした");
            }
        });

        $itemname = $this->plugin->allItem->GetItemJaNameById($item->getId());
        $form->setTitle("iPhone-AdminShop-購入最終確認");
        $form->setContent($itemname . "を" . $item->getCount() . "個購入しますか？\n" . $price . "円です");
        $form->setButton1("購入する");
        $form->setButton2("やめる");
        $player->sendForm($form);
    }

    private function AdminShopSellCheck(Player $player, $item, $itemdata)
    {
        $price = $item->getCount() * $itemdata["sellprice"];

        $form = new ModalForm(function (Player $player, $data) use ($item, $price) {
            if ($data === true) {
                $name = $player->getName();
                $this->plugin->db->AddMoney($name, $price);
                $player->getInventory()->removeItem($item);
                $player->sendMessage("売却しました");
            } elseif ($data === false) {
                $player->sendMessage("売却しませんでした");
            }
        });

        $form->setTitle("iPhone-AdminShop-売却最終確認");
        $form->setContent($item->getName() . "を" . $item->getCount() . "個売却しますか？");
        $form->setButton1("売却する");
        $form->setButton2("やめる");
        $player->sendForm($form);
    }
}