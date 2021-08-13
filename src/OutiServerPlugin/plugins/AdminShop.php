<?php

declare(strict_types=1);

namespace OutiServerPlugin\plugins;

use ArgumentCountError;
use Error;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\{CustomForm, SimpleForm};
use OutiServerPlugin\Main;
use OutiServerPlugin\Tasks\ReturnForm;
use OutiServerPlugin\Tasks\SendLog;
use OutiServerPlugin\Utils\Enum;
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
                try {
                    if ($data === null) return true;

                    switch ($data) {
                        case 0:
                            $this->AdminShopMenuCategory($player);
                            break;
                        case 1:
                            $this->plugin->applewatch->Form($player);
                            break;
                    }
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-AdminShop");
            $form->addButton("メニュー");
            $form->addButton("戻る");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function AdminShopMenuCategory(Player $player)
    {
        try {
            $alldata = $this->plugin->db->GetAllItemCategoryShop();
            if (!$alldata) {
                $player->sendMessage("§b[AdminShop] >> §4現在AdminShopでは何も売られていないようです");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this->plugin->applewatch, "Form"], [$player]), 20);
                return;
            }

            $form = new SimpleForm(function (Player $player, $data) use ($alldata) {
                try {
                    if ($data === null) return true;
                    elseif ($data === 0) {
                        $this->plugin->applewatch->Form($player);
                        return true;
                    }
                    $Categoryid = $alldata[(int)$data - 1]["id"];
                    $this->AdminShopMenu($player, $Categoryid);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-AdminShop");
            $form->setContent("AdminShop-カテゴリー");
            $form->addButton("戻る");
            for ($i = 0; $i < count($alldata); $i++) {
                $form->addButton($alldata[$i]["name"]);
            }

            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function AdminShopMenu(Player $player, int $CategoryId)
    {
        try {
            $alldata = $this->plugin->db->AllAdminShop($CategoryId);
            $ItemCategorys = $this->plugin->db->GetItemChildCategory($CategoryId);
            if (!$alldata and !$ItemCategorys) {
                $player->sendMessage("§b[AdminShop] >> §4現在そのカテゴリーでは何も売られていないようです");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminShopMenuCategory"], [$player]), 20);
                return;
            }

            $form = new SimpleForm(function (Player $player, $data) use ($alldata, $ItemCategorys) {
                try {
                    $categoryadded = 0;
                    if ($data === null) return true;
                    elseif ($data === 0) {
                        $this->AdminShopMenuCategory($player);
                        return true;
                    } elseif ($ItemCategorys !== false) {
                        $categoryadded = count($ItemCategorys);
                        if ($categoryadded >= $data) {
                            $this->AdminShopMenu($player, $ItemCategorys[$data - 1]["id"]);
                            return true;
                        }
                    }

                    $itemdata = $alldata[$data - ($categoryadded + 1)];
                    $this->SelectAdminShop($player, $itemdata);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch");
            $form->setContent("AdminShop-メニュー");
            $form->addButton("戻る");

            if ($ItemCategorys !== false) {
                foreach ($ItemCategorys as $data) {
                    $form->addButton("カテゴリー: {$data["name"]}");
                }
            }

            if ($alldata !== false) {
                foreach ($alldata as $data) {
                    $item = Item::get($data["itemid"], $data["itemmeta"]);
                    $itemdata = $this->plugin->db->GetItemDataItem($item);
                    if (!$itemdata) {
                        $itemdata = array(
                            "janame" => $item->getName(),
                            "imagepath" => ""
                        );
                    }
                    if ($data["mode"] === Enum::ADMINSHOP_ALL) {
                        $form->addButton("{$itemdata["janame"]} 購入値段: {$data["buyprice"]}円 売却値段: {$data["sellprice"]}円", 0, $itemdata["imagepath"]);
                    } elseif ($data["mode"] === Enum::ADMINSHOP_BUY_ONLY) {
                        $form->addButton("{$itemdata["janame"]} 購入値段: {$data["buyprice"]}円", 0, $itemdata["imagepath"]);
                    } elseif ($data["mode"] === Enum::ADMINSHOP_SELL_ONLY) {
                        $form->addButton("{$itemdata["janame"]} 売却値段: {$data["sellprice"]}円", 0, $itemdata["imagepath"]);
                    }
                }
            }

            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function SelectAdminShop(Player $player, $itemdata)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) use ($itemdata) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminShopMenu($player, $itemdata["categoryid"]);
                        return true;
                    }

                    $item = Item::get($itemdata["itemid"], $itemdata["itemmeta"], (int)$data[2]);
                    if ($data[1] === 0) {
                        if ($itemdata["mode"] === Enum::ADMINSHOP_SELL_ONLY) {
                            $player->sendMessage("§b[AdminShop] >> §4そのアイテムは売却のみ使用できます");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "SelectAdminShop"], [$player, $itemdata]), 20);
                        } elseif ($player->getInventory()->canAddItem($item)) {
                            $price = $item->getCount() * $itemdata["buyprice"];
                            $name = $player->getName();
                            $playerdata = $this->plugin->db->GetMoney($name);
                            if ($price > $playerdata["money"]) {
                                $player->sendMessage("§b[AdminShop] >> §4お金が" . ($playerdata["money"] - $price) * -1 . "円足りていませんよ？");
                                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "SelectAdminShop"], [$player, $itemdata]), 20);
                                return true;
                            }
                            $this->plugin->db->RemoveMoney($name, $price);
                            $player->getInventory()->addItem($item);
                            $itemname = $this->plugin->db->GetItemDataItem($item);
                            if (!$itemname) $itemname = array("janame" => $item->getName());
                            $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminShopを使用し、 {$itemname["janame"]} を {$item->getCount()} 個 {$price} 円で購入しました"));
                            $player->sendMessage("§b[AdminShop] >> §a購入しました");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminShopMenu"], [$player, $itemdata["categoryid"]]), 20);
                        } else {
                            $player->sendMessage("§b[AdminShop] >> §4インベントリの空き容量が足りません");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "SelectAdminShop"], [$player, $itemdata]), 20);
                        }
                    } elseif ($data[1] === 1) {
                        if ($itemdata["mode"] === Enum::ADMINSHOP_BUY_ONLY) {
                            $player->sendMessage("§b[AdminShop] >> §4そのアイテムは購入のみ使用できます");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "SelectAdminShop"], [$player, $itemdata]), 20);
                        } elseif ($player->getInventory()->contains($item)) {
                            $price = $item->getCount() * $itemdata["sellprice"];
                            $name = $player->getName();
                            $this->plugin->db->AddMoney($name, $price);
                            $player->getInventory()->removeItem($item);
                            $itemname = $this->plugin->db->GetItemDataItem($item);
                            if (!$itemname) $itemname = array("janame" => $item->getName());
                            $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminShopを使用し、 {$itemname["janame"]} を {$item->getCount()} 個 {$price} 円で売却しました"));
                            $player->sendMessage("§b[AdminShop] >> §a売却しました");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminShopMenu"], [$player, $itemdata["categoryid"]]), 20);
                        } else {
                            $player->sendMessage("§b[AdminShop] >> §4自分の所持しているアイテム以上のアイテムを売却することはできません");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "SelectAdminShop"], [$player, $itemdata]), 20);
                        }
                    }
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-AdminShop-購入・売却");
            $form->addToggle("キャンセルして戻る");
            $form->addDropdown("購入・売却", ["購入", "売却"]);
            $form->addInput("購入・売却する個数", "count", "1");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}