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
use OutiServerPlugin\Tasks\ReturnForm;
use pocketmine\item\Item;
use pocketmine\Player;
use SQLiteException;
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
                try {
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
                            $this->AdminShopSet($player);
                            break;
                        case 10:
                            $this->DeleteItemCategory($player);
                            break;
                        case 11:
                            $this->ForcedLandAbandonment($player);
                            break;
                        case 12:
                            $this->plugin->config->reload();
                            $player->sendMessage("§a[おうちサーバー] >> §aconfigをリロードしました");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
                            break;
                        case 13:
                            $this->db($player);
                            break;
                        case 14:
                            $this->plugin->client->sendDB();
                            break;
                        case 15:
                            $this->plugin->applewatch->Form($player);
                            break;
                    }
                }
                catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-管理");
            $form->addButton("プレイヤーにお金を追加");
            $form->addButton("プレイヤーからお金を剥奪");
            $form->addButton("プレイヤーのお金を設定");
            $form->addButton("アイテムカテゴリーの追加");
            $form->addButton("アイテムカテゴリーの削除");
            $form->addButton("テレポートの追加");
            $form->addButton("テレポートの削除");
            $form->addButton("アナウンスの追加");
            $form->addButton("アナウンスの削除");
            $form->addButton("AdminShopのアイテム設定");
            $form->addButton("AdminShopのアイテム削除");
            $form->addButton("土地強制放棄");
            $form->addButton("Configリロード");
            $form->addButton("db操作(§4SQLite技術者以外使用禁止)");
            $form->addButton("db送信");
            $form->addButton("戻る");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function AdminAddMoney(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    }
                    elseif (!isset($data[1]) or !is_numeric($data[2])) return true;
                    else if (!Player::isValidUserName($data[1])) return true;
                    $this->plugin->db->AddMoney($data[1], (int)$data[2]);
                    $player->sendMessage("§b[経済] >> §a$data[1]に$data[2]円追加しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminAddMoney"], [$player]), 20);
                }
                catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-管理-プレイヤーにお金を追加");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("追加するプレイヤー名", "player", "");
            $form->addInput("追加するお金", "addmoney", "0");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function AdminRemoveMoney(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    }
                    elseif (!isset($data[1]) or !is_numeric($data[2])) return true;
                    else if (!Player::isValidUserName($data[1])) return true;
                    $this->plugin->db->RemoveMoney($data[1], (int)$data[2]);
                    $player->sendMessage("§b[経済] >> §a$data[1]から$data[2]円剥奪しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminRemoveMoney"], [$player]), 20);
                }
                catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-管理-プレイヤーからお金を剥奪");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("剥奪するプレイヤー名", "player", "");
            $form->addInput("剥奪するお金", "addmoney", "0");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function AdminSetMoney(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    }
                    elseif (!isset($data[1]) or !is_numeric($data[2])) return true;
                    else if (!Player::isValidUserName($data[1])) return true;
                    $this->plugin->db->UpdateMoney($data[1], (int)$data[2]);
                    $player->sendMessage("§b[経済] >> §a$data[1]の所持金を$data[2]円設定しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminSetMoney"], [$player]), 20);
                }
                catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-管理-プレイヤーのお金をセット");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("セットするプレイヤー名", "player", "");
            $form->addInput("セットするお金", "setmoney", "0");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function db(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    }
                    elseif (!isset($data[1])) return true;
                    try {
                        $result = $this->plugin->db->db->query($data[1]);
                        $data = $result->fetchArray();
                        var_dump($data);
                    } catch (SQLiteException $ex) {
                        $player->sendMessage("§4ERROR!\n" . $ex->getMessage());
                    }
                }
                catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-管理-db");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("クエリ", "query", "");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function AddItemCategory(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    if($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    }
                    else if(!isset($data[1])) return true;

                    $this->plugin->db->AddItemCategory($data[1]);
                    $player->sendMessage("§b[AdminShop] >> §a$data[1]をアイテムカテゴリーに追加しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AddItemCategory"], [$player]), 20);
                }
                catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-アイテムカテゴリー追加");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("追加するアイテムカテゴリーの名前", "additemcategoryname", "");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function RemoveItemCategory(Player $player)
    {
        try {
            $ItemCategorys = $this->plugin->db->GetAllItemCategory();
            $allCategorys = [];
            foreach ($ItemCategorys as $key) {
                $allCategorys[] = $key["name"];
            }

            $form = new CustomForm(function (Player $player, $data) use ($ItemCategorys) {
                try {
                    if($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    }
                    else if(!is_numeric($data[1])) return true;

                    $this->plugin->db->RemoveItemCategory($ItemCategorys[(int)$data[1]]["id"]);
                    $player->sendMessage("{$ItemCategorys[(int)$data[1]]["name"]}をアイテムカテゴリーから削除しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "RemoveItemCategory"], [$player]), 20);
                }
                catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-アイテムカテゴリー削除");
            $form->addToggle("キャンセルして戻る");
            $form->addDropdown("アイテムカテゴリー", $allCategorys);
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function AddWorldTeleport(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    if($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    }
                    else if(!isset($data[2])) return true;

                    $pos = $player->getPosition();
                    $this->plugin->db->SetWorldTeleport($data[2], $pos, (int)$data[1]);
                    $player->sendMessage("§b[ワールドテレポート] >> §aテレポート地点を追加しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
                }
                catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-ワールドテレポートの追加");
            $form->addToggle("キャンセルして戻る");
            $form->addToggle("OPのみ");
            $form->addInput("テレポート名", "name", "");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function RemoveWorldTeleport(Player $player)
    {
        try {
            $allteleportworlds = $this->plugin->db->GetAllWorldTeleport();
            if(!$allteleportworlds) {
                $player->sendMessage("§b[ワールドテレポート] >> §4現在テレポートできるワールドは1つもないようです");
                return;
            }
            $teleportworlds = [];
            foreach ($allteleportworlds as $key) {
                $teleportworlds[] = $key["name"];
            }

            $form = new CustomForm(function (Player $player, $data) use ($allteleportworlds) {
                try {
                    if($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    }
                    else if(!is_numeric($data[1])) return true;

                    $this->plugin->db->DeleteWorldTeleport($allteleportworlds[(int)$data[1]]["id"]);
                    $player->sendMessage("§b[ワールドテレポート] >> §a{$allteleportworlds[(int)$data[1]]["name"]}をテレポートから削除しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "RemoveWorldTeleport"], [$player]), 20);
                }
                catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-テレポート削除");
            $form->addToggle("キャンセルして戻る");
            $form->addDropdown("削除するテレポート先", $teleportworlds);
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function AddAnnounce(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    if($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    }
                    else if(!isset($data[1]) or !isset($data[2])) return true;

                    $time = new DateTime('now');
                    $this->plugin->db->AddAnnounce($time->format("Y年m月d日 H時i分"), $data[1], $data[2]);
                    $player->sendMessage("§b[運営からのお知らせ] >> §a運営からのお知らせ$data[1]を追加しました");
                    $this->plugin->getServer()->broadcastMessage("§b[おうちサーバー] >> §e運営からのお知らせが追加されました、ご確認ください。");
                    $this->plugin->client->sendChatMessage("__**[おうちサーバー] >> 運営からのお知らせが追加されました、ご確認ください。**__\n");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
                }
                catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-アナウンス追加");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("タイトル", "title", "");
            $form->addInput("説明", "content", "");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function RemoveAnnounce(Player $player)
    {
        try {
            $allannounce = $this->plugin->db->GetAllAnnounce();
            if(!$allannounce) {
                $player->sendMessage("§b[運営からのお知らせ] >> §4現在運営からのお知らせはありません");
                return;
            }

            $announces = [];
            foreach ($allannounce as $key) {
                $announces[] = $key["title"];
            }

            $form = new CustomForm(function (Player $player, $data) use ($allannounce) {
                try {
                    if($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    }
                    else if(!is_numeric($data[1])) return true;

                    $this->plugin->db->DeleteAnnounce($allannounce[(int)$data[1]]["id"]);
                    $player->sendMessage("§b[運営からのお知らせ] >> §a{$allannounce[(int)$data[1]]["title"]}をアナウンスから削除しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "RemoveAnnounce"], [$player]), 20);
                }
                catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-アナウンス削除");
            $form->addToggle("キャンセルして戻る");
            $form->addDropdown("アナウンス", $announces);
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function AdminShopSet(Player $player)
    {
        try {
            $ItemCategorys = $this->plugin->db->GetAllItemCategoryAll();
            $allCategorys = [];
            foreach ($ItemCategorys as $key) {
                $allCategorys[] = $key["name"];
            }

            $form = new CustomForm(function (Player $player, $data) use ($ItemCategorys) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    }
                    else if (!is_numeric($data[2]) or !is_numeric($data[3]) or !isset($data[1]) or !is_numeric($data[4])) return true;
                    $itemid = $this->plugin->allItem->GetItemIdByJaName($data[1]);
                    if (!$itemid) {
                        $player->sendMessage("§b[AdminShop] >> §4アイテムが見つかりませんでした");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminShopSet"], [$player]), 20);
                        return true;
                    }
                    $item = Item::get($itemid);
                    if (!$item) return true;

                    $itemdata = $this->plugin->db->GetAdminShop($item);
                    if (!$itemdata) {
                        $this->plugin->db->SetAdminShop($item, (int)$data[2], (int)$data[3], $ItemCategorys[(int)$data[4]]["id"], (int)$data[5]);
                    } else {
                        $this->plugin->db->UpdateAdminShop($item, (int)$data[2], (int)$data[3], $ItemCategorys[(int)$data[4]]["id"], (int)$data[5]);
                    }

                    $player->sendMessage("§b[AdminShop] >> §a設定しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminShopSet"], [$player]), 20);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-AdminShop-値段設定");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("値段設定するアイテム名", "itemname", "");
            $form->addInput("値段", "buyprice", "1");
            $form->addInput("売却値段", "sellprice", "1");
            $form->addDropdown("アイテムカテゴリー", $allCategorys);
            $form->addDropdown("モード", ["通常", "購入のみ", "売却のみ"]);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function DeleteItemCategory(Player $player)
    {
        try {
            $alldata = $this->plugin->db->GetAllItemCategoryShop();
            if (!$alldata) {
                $player->sendMessage("§b[AdminShop] >> §4現在AdminShopでは何も売られていないようです");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
                return;
            }

            $form = new SimpleForm(function (Player $player, $data) use ($alldata) {
                try {
                    if ($data === null) return true;
                    elseif ($data === 0) {
                        $this->AdminForm($player);
                        return true;
                    }
                    $Categoryid = $alldata[(int)$data - 1]["id"];
                    $this->DeleteItemSelect($player, $Categoryid);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch");
            $form->setContent("Admin-アイテム削除-カテゴリー");
            $form->addButton("戻る");

            for ($i = 0; $i < count($alldata); $i++) {
                $form->addButton($alldata[$i]["name"]);
            }

            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function DeleteItemSelect(Player $player, int $categoryid)
    {
        try {
            $allitem = $this->plugin->db->AllAdminShop($categoryid);
            if (!$allitem) {
                $player->sendMessage("§b[AdminShop] >> §4現在そのカテゴリーでは何も売られていないようです");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "DeleteItemCategory"], [$player]), 20);
                return;
            }

            $allitems = [];
            foreach ($allitem as $key) {
                $item = Item::get($key["itemid"], $key["itemmeta"]);
                if (!$item) continue;
                $itemname = $this->plugin->allItem->GetItemJaNameById($item->getId());
                if (!$itemname) continue;
                $allitems[] = $itemname;
            }

            $form = new CustomForm(function (Player $player, $data) use ($allitem) {
                try {
                    if ($data === null) return true;
                    elseif ($data === 0) {
                        $this->AdminForm($player);
                        return true;
                    }
                    else if (!is_numeric($data[1])) return true;

                    $item = Item::get($allitem[$data[1]]["itemid"], $allitem[$data[1]]["itemmeta"]);
                    if (!$item) return true;
                    $itemname = $this->plugin->allItem->GetItemJaNameById($item->getId());
                    if (!$itemname) return true;

                    $this->plugin->db->DeleteAdminShopItem($item);
                    $player->sendMessage("§b[AdminShop] >> §a{$itemname}をAdminShopから削除しました");
                    if(count($allitem) > 1) {
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "DeleteItemSelect"], [$player, $allitem[$data[1]]["categoryid"]]), 20);
                    }
                    else {
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "DeleteItemCategory"], [$player]), 20);
                    }
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-アイテム削除");
            $form->addToggle("キャンセルして戻る");
            $form->addDropdown("アイテム", $allitems);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function ForcedLandAbandonment(Player $player)
    {
        try {
            $alllands = $this->plugin->db->GetAllLandId();
            if(!$alllands) {
                $player->sendMessage("§b[土地保護] >> §4現在土地は1つもないようです");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
                return;
            }

            $form = new CustomForm(function (Player $player, $data) use ($alllands) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0] === true) {
                        $player->sendMessage("§b[土地保護] >> §eキャンセルしました");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
                        return true;
                    }

                    $landid = (int)$alllands[(int)$data[1]];
                    $this->plugin->db->DeleteLand($landid);
                    $player->sendMessage("§b[土地保護] >> §6土地ID #$landid を強制放棄しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "ForcedLandAbandonment"], [$player]), 20);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-Admin-土地強制放棄");
            $form->addToggle("キャンセルして戻る");
            $form->addDropdown("土地放棄する土地ID", $alllands);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}