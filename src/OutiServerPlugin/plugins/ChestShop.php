<?php

declare(strict_types=1);

namespace OutiServerPlugin\plugins;

use ArgumentCountError;
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\{CustomForm};
use OutiServerPlugin\Main;
use OutiServerPlugin\Tasks\ReturnForm;
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

    public function CreateChestShop(Player $player, $chest, $signboard)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) use ($chest, $signboard) {
                try {
                    if ($data === null) return true;
                    else if (!is_numeric($data[1]) or !is_numeric($data[2]) or !is_numeric($data[3])) return true;

                    $name = $player->getName();
                    $pos = new Vector3($signboard->x, $signboard->y, $signboard->z);
                    $sign = $signboard->getLevel()->getTile($pos);
                    if ($sign instanceof Tile) {
                        $item = Item::get((int)$data[1], (int)$data[2], (int)$data[0]);
                        $this->plugin->db->SetChestShop($name, $chest, $signboard, $item, (int)$data[3]);
                        $itemname = $this->plugin->db->GetItemDataItem($item);
                        if (!$itemname) $itemname = array("janame" => $item->getName());
                        $sign->setText("§bshop", "§a" . $name, "§d" . $itemname["janame"], "§e" . $data[3] . "円");
                        $player->sendMessage("§b[チェストショップ] §f>> §6チェストショップを作成しました！");
                    }
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("shop作成");
            $form->addSlider("販売するItemの最大購入数", 1, 64);
            $form->addInput("販売するItemのId", "itemid", "");
            $form->addInput("販売するItemのMeta", "itemmeta", "0");
            $form->addInput("販売するItemの値段", "price", "1");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function BuyChestShop(Player $player, $shopdata)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) use ($shopdata) {
                try {
                    if ($data === null) return true;

                    $name = $player->getName();
                    $inventory = $player->getInventory();
                    $vector3 = new Vector3($shopdata["chestx"], $shopdata["chesty"], $shopdata["chestz"]);
                    $level = $this->plugin->getServer()->getLevelByName($shopdata["levelname"]);
                    $item = Item::get($shopdata["itemid"], $shopdata["itemmeta"], (int)$data[1]);

                    if (!$inventory->canAddItem($item)) {
                        $player->sendMessage('§b[チェストショップ] §f>> §6インベントリの空きが足りません');
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "CreateChestShop"], [$player, $shopdata]), 20);
                        return true;
                    }

                    $tile = $level->getTile($vector3);
                    $chest = $tile->getInventory()->contains($item);

                    if ($chest) {
                        $playermoney = $this->plugin->db->GetMoney($name);
                        $price = $item->getCount() * $shopdata["price"];
                        if ($price > $playermoney["money"]) {
                            $player->sendMessage("§b[チェストショップ] >> §4お金が" . ($playermoney["money"] - $price) * -1 . "円足りていませんよ？");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "BuyChestShop"], [$player]), 20);
                            return true;
                        } elseif (!$player->getInventory()->canAddItem($item)) {
                            $player->sendMessage("§b[チェストショップ] >> §4インベントリの空き容量が足りません");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "BuyChestShop"], [$player]), 20);
                            return true;
                        }

                        $pos = new Position($shopdata["chestx"], $shopdata["chesty"], $shopdata["chestz"], $this->plugin->getServer()->getLevelByName($shopdata["levelname"]));
                        $pos->level->getTile($pos)->getInventory()->removeItem($item);
                        $inventory->addItem($item);
                        $this->plugin->db->RemoveMoney($name, $price);
                        $this->plugin->db->AddMoney($shopdata["owner"], $price);
                        $player->sendMessage("§b[チェストショップ] >> §6購入しました");
                    } else {
                        $player->sendMessage('§b[チェストショップ] >> §6申し訳ありませんが、在庫が足りていないようです。');
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "CreateChestShop"], [$player, $shopdata]), 20);
                    }
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $item = Item::get($shopdata["itemid"], $shopdata["itemmeta"]);
            $itemname = $this->plugin->db->GetItemDataItem($item);
            if (!$itemname) {
                $player->sendMessage("§b[チェストショップ] >> §4アイテムが見つかりませんでした");
                return true;
            }
            $form->setTitle("Shop");
            $form->addLabel("販売物: " . $itemname["janame"]);
            $form->addSlider("\n買う個数", 1, $shopdata["maxcount"]);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}