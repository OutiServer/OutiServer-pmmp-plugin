<?php

declare(strict_types=1);

namespace OutiServerPlugin;


use ArgumentCountError;
use jojoe77777\FormAPI\{CustomForm, SimpleForm};
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\tile\Tile;
use TypeError;

class ChestShop
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function BuyChestShop(Player $player, $shopdata)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) use ($shopdata) {
                if ($data === null) return true;

                $inventory = $player->getInventory();
                $pos = new Position($shopdata["chestx"], $shopdata["chesty"], $shopdata["chestz"], $this->plugin->getServer()->getLevelByName($shopdata["levelname"]));
                $item = Item::get($shopdata["itemid"], $shopdata["itemmeta"], (int)$data[1]);

                if (!$inventory->canAddItem($item)) {
                    $player->sendMessage('インベントリの空きが足りません');
                    return true;
                }

                $tile = $pos->level->getTile($pos);
                $chest = $tile->getInventory()->contains($item);

                if ($chest) {
                    $this->BuyChestShopCheck($player, $item, $shopdata, $inventory);
                } else {
                    $player->sendMessage('申し訳ありませんが、在庫が足りていないようです。');
                }

                return true;
            });

            $item = Item::get($shopdata["itemid"], $shopdata["itemmeta"]);
            $itemname = $this->plugin->allItem->GetItemJaNameById($item->getId());
            $form->setTitle("Shop");
            $form->addLabel("販売物: " . $itemname);
            $form->addSlider("\n買う個数", 1, $shopdata["maxcount"]);
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function CreateChestShop(Player $player, $chest, $signboard)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) use ($chest, $signboard) {
                if ($data === null) return true;
                else if (!is_numeric($data[0]) or !is_numeric($data[2]) or !isset($data[1])) return true;

                $name = $player->getName();
                $pos = new Vector3($signboard->x, $signboard->y, $signboard->z);
                $sign = $signboard->getLevel()->getTile($pos);
                if ($sign instanceof Tile) {
                    $itemid = $this->plugin->allItem->GetItemIdByJaName($data[1]);
                    if (!$itemid) {
                        $player->sendMessage("アイテムが見つかりませんでした");
                        return true;
                    }
                    $item = Item::get($itemid);
                    $this->plugin->db->SetChestShop($name, $chest, $signboard, $item, $data[2]);
                    $itemname = $this->plugin->allItem->GetItemJaNameById($item->getId());
                    $sign->setText("shop", "shop主: " . $name, "販売しているItem: " . $itemname, "お値段: " . $data[2] . "円");
                    $player->sendMessage("shopを作成しました！");
                }

                return true;
            });

            $form->setTitle("shop作成");
            $form->addSlider("販売するItemの最大購入数", 1, 64);
            $form->addInput("販売するItemの名前", "itemname", "");
            $form->addInput("販売するItemの値段", "price", "1");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function BuyChestShopCheck($player, $item, $shopdata, $playerinventory)
    {
        try {
            $form = new SimpleForm(function (Player $player, $data) use ($item, $shopdata, $playerinventory) {
                if ($data === null) return true;

                switch ($data) {
                    case 0:
                        $playername = $player->getName();
                        $playermoney = $this->plugin->db->GetMoney($playername);
                        $price = $item->getCount() * $shopdata["price"];
                        if ($price > $playermoney["money"]) {
                            $player->sendMessage("お金が" . ($playermoney["money"] - $price) * -1 . "円足りていませんよ？");
                            return true;
                        }

                        $pos = new Position($shopdata["chestx"], $shopdata["chesty"], $shopdata["chestz"], $this->plugin->getServer()->getLevelByName($shopdata["levelname"]));
                        $pos->level->getTile($pos)->getInventory()->removeItem($item);
                        $playerinventory->addItem($item);
                        $this->plugin->db->RemoveMoney($playername, $price);
                        $this->plugin->db->AddMoney($shopdata["owner"], $price);
                        $player->sendMessage("購入しました");
                        break;
                    case 1:
                        $player->sendMessage("購入しませんでした");
                        break;
                }

                return true;
            });

            $itemname = $this->plugin->allItem->GetItemJaNameById($item->getId());
            $form->setTitle("購入確認");
            $form->setContent($itemname . "を" . $item->getCount() . "個購入しますか？\n" . $item->getCount() * $shopdata["price"] . "円です");
            $form->addButton("購入する");
            $form->addButton("キャンセル");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}