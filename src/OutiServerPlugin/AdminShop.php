<?php

declare(strict_types=1);

namespace OutiServerPlugin;

use ArgumentCountError;
use jojoe77777\FormAPI\{CustomForm, ModalForm, SimpleForm};
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use pocketmine\item\Item;
use pocketmine\Player;
use TypeError;

class AdminShop
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function AdminShop(Player $player)
    {
        try {
            $form = new SimpleForm(function (Player $player, $data) {
                if ($data === null) return true;

                switch ($data) {
                    case 0:
                        $this->AdminShopMenuCategory($player);
                        break;
                    case 1:
                        $this->AdminShopSetprice($player);
                        break;
                    case 2:
                        $this->DeleteItemCategory($player);
                        break;
                }

                return true;
            });

            $form->setTitle("iPhone-AdminShop");
            $form->addButton("メニュー");
            if ($player->isOp()) {
                $form->addButton("アイテム設定");
                $form->addButton("アイテム削除");
            }
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function AdminShopSetprice(Player $player)
    {
        try {
            $ItemCategorys = $this->plugin->db->GetAllItemCategoryAll();
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
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function AdminShopMenuCategory(Player $player)
    {
        try {
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
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function AdminShopMenu(Player $player, int $CategoryId)
    {
        try {
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
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function SelectAdminShop(Player $player, $itemdata)
    {
        try {
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
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function AdminShopBuyCheck(Player $player, $item, $itemdata)
    {
        try {
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
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function AdminShopSellCheck(Player $player, $item, $itemdata)
    {
        try {
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
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function DeleteItemCategory(Player $player)
    {
        try {
            $alldata = $this->plugin->db->GetAllItemCategory();
            if (!$alldata) {
                $player->sendMessage("現在AdminShopでは何も売られていないようです");
                return;
            }

            $form = new SimpleForm(function (Player $player, $data) use ($alldata) {
                if ($data === null) return true;
                $Categoryid = $alldata[(int)$data]["id"];
                $this->DeleteItemSelect($player, $Categoryid);
                return true;
            });

            $form->setTitle("iPhone");
            $form->setContent("AdminShop-カテゴリー");

            for ($i = 0; $i < count($alldata); $i++) {
                $form->addButton($alldata[$i]["name"]);
            }

            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function DeleteItemSelect(Player $player, int $categoryid)
    {
        try {
            $allitem = $this->plugin->db->AllAdminShop($categoryid);
            if (!$allitem) {
                $player->sendMessage("現在そのカテゴリーでは何も売られていないようです");
                return;
            }

            $allitems = [];
            foreach ($allitem as $key) {
                $item = Item::get($key["itemid"], $key["itemmeta"]);
                if(!$item) continue;
                $itemname = $this->plugin->allItem->GetItemJaNameById($item->getId());
                if(!$itemname) continue;
                $allitems[] = $itemname;
            }

            $form = new CustomForm(function (Player $player, $data) use ($allitem) {
                if($data === null) return true;
                else if(!is_numeric($data[0])) return true;

                $item = Item::get($allitem[$data[0]]["itemid"], $allitem[$data[0]]["itemmeta"]);
                if(!$item) return true;
                $itemname = $this->plugin->allItem->GetItemJaNameById($item->getId());
                if(!$itemname) return true;

                $this->plugin->db->DeleteAdminShopItem($item);
                $player->sendMessage($itemname . "をAdminShopから削除しました");

                return true;
            });

            $form->setTitle("Admin-アイテム削除");
            $form->addDropdown("アイテム", $allitems);
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}