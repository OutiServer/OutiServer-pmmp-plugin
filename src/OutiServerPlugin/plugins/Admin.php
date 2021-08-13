<?php

declare(strict_types=1);

namespace OutiServerPlugin\plugins;

use ArgumentCountError;
use DateTime;
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\{CustomForm, SimpleForm};
use OutiServerPlugin\Main;
use OutiServerPlugin\Tasks\ReturnForm;
use OutiServerPlugin\Tasks\SendLog;
use pocketmine\item\Item;
use pocketmine\Player;
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
                            $this->AddItemChildCategory($player);
                            break;
                        case 6:
                            $this->DeleteItemChildCategorySelect($player);
                            break;
                        case 7:
                            $this->AddWorldTeleport($player);
                            break;
                        case 8:
                            $this->RemoveWorldTeleport($player);
                            break;
                        case 9:
                            $this->AddAnnounce($player);
                            break;
                        case 10:
                            $this->RemoveAnnounce($player);
                            break;
                        case 11:
                            $this->AdminShopSet($player);
                            break;
                        case 12:
                            $this->DeleteItemCategory($player);
                            break;
                        case 13:
                            $this->ForcedLandAbandonment($player);
                            break;
                        case 14:
                            $this->SetItem($player);
                            break;
                        case 15:
                            $this->SetRegularMessage($player);
                            break;
                        case 16:
                            $this->DeleteRegularMessage($player);
                            break;
                        case 17:
                            $this->plugin->applewatch->Form($player);
                            break;
                    }
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
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
            $form->addButton("子アイテムカテゴリーの追加");
            $form->addButton("子アイテムカテゴリーの削除");
            $form->addButton("テレポートの追加");
            $form->addButton("テレポートの削除");
            $form->addButton("アナウンスの追加");
            $form->addButton("アナウンスの削除");
            $form->addButton("AdminShopのアイテム設定");
            $form->addButton("AdminShopのアイテム削除");
            $form->addButton("土地強制放棄");
            $form->addButton("アイテム設定");
            $form->addButton("定期メッセージの追加");
            $form->addButton("定期メッセージの削除");
            $form->addButton("戻る");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
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
                    } elseif (!isset($data[1]) or !is_numeric($data[2])) return true;
                    else if (!Player::isValidUserName($data[1])) return true;
                    $this->plugin->db->AddMoney($data[1], (int)$data[2]);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 $data[1] に $data[2] 円追加しました"));
                    $player->sendMessage("§b[経済] >> §a$data[1]に$data[2]円追加しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminAddMoney"], [$player]), 20);
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-管理-プレイヤーにお金を追加");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("追加するプレイヤー名", "player", "");
            $form->addInput("追加するお金", "addmoney", "0");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
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
                    } elseif (!isset($data[1]) or !is_numeric($data[2])) return true;
                    else if (!Player::isValidUserName($data[1])) return true;
                    $this->plugin->db->RemoveMoney($data[1], (int)$data[2]);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 $data[1] に $data[2] 円剥奪しました"));
                    $player->sendMessage("§b[経済] >> §a$data[1]から$data[2]円剥奪しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminRemoveMoney"], [$player]), 20);
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-管理-プレイヤーからお金を剥奪");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("剥奪するプレイヤー名", "player", "");
            $form->addInput("剥奪するお金", "addmoney", "0");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
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
                    } elseif (!isset($data[1]) or !is_numeric($data[2])) return true;
                    else if (!Player::isValidUserName($data[1])) return true;
                    $this->plugin->db->UpdateMoney($data[1], (int)$data[2]);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 $data[1] の所持金を $data[2] 円に設定しました"));
                    $player->sendMessage("§b[経済] >> §a$data[1]の所持金を$data[2]円設定しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminSetMoney"], [$player]), 20);
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-管理-プレイヤーのお金をセット");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("セットするプレイヤー名", "player", "");
            $form->addInput("セットするお金", "setmoney", "0");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function AddItemCategory(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    } else if (!isset($data[1])) return true;

                    $this->plugin->db->AddItemCategory($data[1]);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 $data[1] をアイテムカテゴリーに追加しました"));
                    $player->sendMessage("§b[AdminShop] >> §a$data[1]をアイテムカテゴリーに追加しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AddItemCategory"], [$player]), 20);
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-アイテムカテゴリー追加");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("追加するアイテムカテゴリーの名前", "additemcategoryname", "");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function RemoveItemCategory(Player $player)
    {
        try {
            $ItemCategorys = $this->plugin->db->GetAllItemCategory();
            if (!$ItemCategorys) {
                $player->sendMessage("§b[AdminShop] >> §4カテゴリーが1つも見つかりませんでした");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
                return;
            }
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
                    } else if (!is_numeric($data[1])) return true;

                    $this->plugin->db->RemoveItemCategory($ItemCategorys[(int)$data[1]]["id"]);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 {$ItemCategorys[(int)$data[1]]["name"]} をアイテムカテゴリーから削除しました"));
                    $player->sendMessage("{$ItemCategorys[(int)$data[1]]["name"]}をアイテムカテゴリーから削除しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "RemoveItemCategory"], [$player]), 20);
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-アイテムカテゴリー削除");
            $form->addToggle("キャンセルして戻る");
            $form->addDropdown("アイテムカテゴリー", $allCategorys);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function AddItemChildCategory(Player $player)
    {
        try {
            $ItemCategorys = $this->plugin->db->GetAllItemCategory();
            if (!$ItemCategorys) {
                $player->sendMessage("§b[AdminShop] >> §4カテゴリーが1つも見つかりませんでした");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
                return;
            }
            $allCategorys = [];
            foreach ($ItemCategorys as $key) {
                $allCategorys[] = $key["name"];
            }

            $form = new CustomForm(function (Player $player, $data) use ($ItemCategorys, $allCategorys) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    } elseif (!isset($data[1])) return true;

                    $this->plugin->db->AddItemChildCategory($data[2], $ItemCategorys[$data[1]]["id"]);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 {$allCategorys[$data[1]]} に アイテムカテゴリー $data[2] を追加しました"));
                    $player->sendMessage("§b[AdminShop] >> §a{$allCategorys[$data[1]]}に$data[2]をカテゴリーとして追加しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AddItemChildCategory"], [$player]), 20);
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-子アイテムカテゴリー追加");
            $form->addToggle("キャンセルして戻る");
            $form->addDropdown("親カテゴリー", $allCategorys);
            $form->addInput("追加する子アイテムカテゴリーの名前", "name", "");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function DeleteItemChildCategorySelect(Player $player)
    {
        try {
            $ItemCategorys = $this->plugin->db->GetAllItemCategory();
            if (!$ItemCategorys) {
                $player->sendMessage("§b[AdminShop] >> §4カテゴリーが1つも見つかりませんでした");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
                return;
            }

            $form = new SimpleForm(function (Player $player, $data) use ($ItemCategorys) {
                try {
                    if ($data === null) return true;
                    elseif ($data === 0) {
                        $this->AdminForm($player);
                        return true;
                    }
                    $Categoryid = $ItemCategorys[(int)$data - 1]["id"];
                    $this->DeleteItemChildCategory($player, $Categoryid);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-Admin-子アイテムカテゴリー削除");
            $form->setContent("AdminShop-カテゴリー");
            $form->addButton("戻る");
            foreach ($ItemCategorys as $key) {
                $form->addButton($key["name"]);
            }
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function DeleteItemChildCategory(Player $player, int $id)
    {
        try {
            $ItemCategorys = $this->plugin->db->GetItemChildCategory($id);
            if (!$ItemCategorys) {
                $player->sendMessage("§b[AdminShop] >> §4カテゴリーが1つも見つかりませんでした");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "DeleteItemChildCategorySelect"], [$player]), 20);
                return;
            }
            $allCategorys = [];
            foreach ($ItemCategorys as $key) {
                $allCategorys[] = $key["name"];
            }

            $form = new CustomForm(function (Player $player, $data) use ($ItemCategorys, $id) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    } else if (!is_numeric($data[1])) return true;

                    $this->plugin->db->RemoveItemCategory($ItemCategorys[(int)$data[1]]["id"]);
                    $parentcategory = $this->plugin->db->GetItemCaegory($id);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 {$parentcategory["name"]} のアイテムカテゴリーから {$ItemCategorys[(int)$data[1]]["name"]} をから削除しました"));
                    $player->sendMessage("{$parentcategory["name"]}のアイテムカテゴリーから{$ItemCategorys[(int)$data[1]]["name"]}をから削除しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "DeleteItemChildCategory"], [$player, $id]), 20);
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-アイテムカテゴリー削除");
            $form->addToggle("キャンセルして戻る");
            $form->addDropdown("アイテムカテゴリー", $allCategorys);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function AddWorldTeleport(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    } else if (!isset($data[2])) return true;

                    $pos = $player->getPosition();
                    $this->plugin->db->SetWorldTeleport($data[2], $pos, (int)$data[1]);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 ワールド {$pos->getLevel()->getName()} X座標 {$pos->getX()} Y座標 {$pos->getY()} Z座標 {$pos->getZ()} に テレポート名 $data[2] としてTP地点を追加しました"));
                    $player->sendMessage("§b[ワールドテレポート] >> §aテレポート地点を追加しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-ワールドテレポートの追加");
            $form->addToggle("キャンセルして戻る");
            $form->addToggle("OPのみ");
            $form->addInput("テレポート名", "name", "");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function RemoveWorldTeleport(Player $player)
    {
        try {
            $allteleportworlds = $this->plugin->db->GetAllWorldTeleport();
            if (!$allteleportworlds) {
                $player->sendMessage("§b[ワールドテレポート] >> §4現在テレポートできるワールドは1つもないようです");
                return;
            }
            $teleportworlds = [];
            foreach ($allteleportworlds as $key) {
                $teleportworlds[] = $key["name"];
            }

            $form = new CustomForm(function (Player $player, $data) use ($allteleportworlds) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    } else if (!is_numeric($data[1])) return true;

                    $this->plugin->db->DeleteWorldTeleport($allteleportworlds[(int)$data[1]]["id"]);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 ワールド {$allteleportworlds[(int)$data[1]]["levelname"]} X座標 {$allteleportworlds[(int)$data[1]]["x"]} Y座標 {$allteleportworlds[(int)$data[1]]["y"]} Z座標 {$allteleportworlds[(int)$data[1]]["z"]} TP名 X座標 {$allteleportworlds[(int)$data[1]]["name"]} を削除しました"));
                    $player->sendMessage("§b[ワールドテレポート] >> §a{$allteleportworlds[(int)$data[1]]["name"]}をテレポートから削除しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "RemoveWorldTeleport"], [$player]), 20);
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-テレポート削除");
            $form->addToggle("キャンセルして戻る");
            $form->addDropdown("削除するテレポート先", $teleportworlds);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function AddAnnounce(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    } else if (!isset($data[1]) or !isset($data[2])) return true;

                    $time = new DateTime('now');
                    $this->plugin->db->AddAnnounce($time->format("Y年m月d日 H時i分"), $data[1], $data[2]);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 アナウンス $data[1] を追加しました"));
                    $player->sendMessage("§b[運営からのお知らせ] >> §a運営からのお知らせ$data[1]を追加しました");
                    $this->plugin->getServer()->broadcastMessage("§b[おうちサーバー] >> §e運営からのお知らせが追加されました、ご確認ください。");
                    $this->plugin->client->sendChatMessage("__**[おうちサーバー] >> 運営からのお知らせが追加されました、ご確認ください。**__\n");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-アナウンス追加");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("タイトル", "title", "");
            $form->addInput("説明", "content", "");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function RemoveAnnounce(Player $player)
    {
        try {
            $allannounce = $this->plugin->db->GetAllAnnounce();
            if (!$allannounce) {
                $player->sendMessage("§b[運営からのお知らせ] >> §4現在運営からのお知らせはありません");
                return;
            }

            $announces = [];
            foreach ($allannounce as $key) {
                $announces[] = $key["title"];
            }

            $form = new CustomForm(function (Player $player, $data) use ($allannounce) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $this->AdminForm($player);
                        return true;
                    } else if (!is_numeric($data[1])) return true;

                    $this->plugin->db->DeleteAnnounce($allannounce[(int)$data[1]]["id"]);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 アナウンス {$allannounce[(int)$data[1]]["title"]} を削除しました"));
                    $player->sendMessage("§b[運営からのお知らせ] >> §a{$allannounce[(int)$data[1]]["title"]}をアナウンスから削除しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "RemoveAnnounce"], [$player]), 20);
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-アナウンス削除");
            $form->addToggle("キャンセルして戻る");
            $form->addDropdown("アナウンス", $announces);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
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
                    if (!isset($data[1])) return true;
                    $item = $this->plugin->db->GetItem($data[1]);
                    if (!$item) {
                        $player->sendMessage("§b[AdminShop] >> §4アイテムデータが見つかりませんでした");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminShopSet"], [$player]), 20);
                        return true;
                    }

                    $itemdata = $this->plugin->db->GetAdminShop($item);
                    if (!$itemdata) {
                        $this->plugin->db->SetAdminShop($item, (int)$data[2], (int)$data[3], $ItemCategorys[(int)$data[4]]["id"], (int)$data[5]);
                    } else {
                        $this->plugin->db->UpdateAdminShop($item, (int)$data[2], (int)$data[3], $ItemCategorys[(int)$data[4]]["id"], (int)$data[5]);
                    }

                    $itemdata = $this->plugin->db->GetItemDataItem($item);
                    if(!$itemdata) $itemdata = array("janame" => $item->getName());
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 AdminShopにItemName {$itemdata["janame"]} 購入金額 $data[2] 売却金額 $data[3] Mode $data[5] に設定しました"));
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
            $ItemCategorys = $this->plugin->db->GetItemChildCategory($categoryid);
            $allitem = $this->plugin->db->AllAdminShop($categoryid);
            if (!$allitem and !$ItemCategorys) {
                $player->sendMessage("§b[AdminShop] >> §4現在そのカテゴリーでは何も売られていないようです");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "DeleteItemCategory"], [$player]), 20);
                return;
            }

            $allitems = [];
            $category = [];
            if ($ItemCategorys !== false) {
                foreach ($ItemCategorys as $data) {
                    $category[] = $data["name"];
                }
            }

            if ($allitem !== false) {
                foreach ($allitem as $key) {
                    $item = Item::get($key["itemid"], $key["itemmeta"]);
                    if (!$item) continue;
                    $itemdata = $this->plugin->db->GetItemDataItem($item);
                    if (!$itemdata) {
                        $itemdata = array(
                            "janame" => $item->getName()
                        );
                    }
                    $allitems[] = $itemdata["janame"];
                }
            }

            $form = new CustomForm(function (Player $player, $data) use ($allitem, $ItemCategorys) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0] === true) {
                        $this->AdminForm($player);
                        return true;
                    } elseif (($data[1] === true and $ItemCategorys !== false) or $allitem === false) {

                        $this->DeleteItemSelect($player, $ItemCategorys[$data[3]]["id"]);
                        return true;
                    }

                    $item = Item::get($allitem[$data[2]]["itemid"], $allitem[$data[2]]["itemmeta"]);
                    if (!$item) return true;

                    $itemdata = $this->plugin->db->GetItemDataItem($item);
                    if(!$itemdata) $itemdata = array("janame" => $item->getName());


                    $this->plugin->db->DeleteAdminShopItem($item);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 AdminShopから ItemName {$itemdata["janame"]} を削除しました"));
                    $player->sendMessage("§b[AdminShop] >> §a{$itemdata["janame"]}をAdminShopから削除しました");
                    if (count($allitem) > 1) {
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "DeleteItemSelect"], [$player, $allitem[$data[2]]["categoryid"]]), 20);
                    } else {
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "DeleteItemCategory"], [$player]), 20);
                    }
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("Admin-アイテム削除");
            $form->addToggle("キャンセルして戻る");
            $form->addToggle("カテゴリーに移動する");
            $form->addDropdown("アイテム", $allitems);
            $form->addDropdown("カテゴリー", $category);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function ForcedLandAbandonment(Player $player)
    {
        try {
            $alllands = $this->plugin->db->GetAllLandId();
            if (!$alllands) {
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
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 土地ID $landid を強制放棄しました"));
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

    // <editor-fold desc="アイテム名設定">
    public function SetItem(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0] === true) {
                        $player->sendMessage("§b[Item設定] >> §eキャンセルしました");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
                        return true;
                    } elseif (!is_numeric($data[1]) or !is_numeric($data[2]) or !isset($data[3]) or !isset($data[4])) return true;

                    $item = Item::get((int)$data[1], (int)$data[2]);
                    if (!$item) return true;

                    if ($this->plugin->db->GetItemDataItem($item)) {
                        $this->plugin->db->UpdateItemData($item, $data[3], $data[4]);
                    } else {
                        $this->plugin->db->SetItemData($item, $data[3], $data[4]);
                    }

                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、 ItemId {$item->getId()} ItemMeta {$item->getDamage()} Item名 $data[3] テクスチャパス $data[4] に設定しました"));
                    $player->sendMessage("§b[Item設定] >> §a設定しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "SetItem"], [$player]), 20);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-Admin-アイテム設定");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("ItemID", "", "");
            $form->addInput("ItemMETA", "", "0");
            $form->addInput("Itemの日本語名", "", "");
            $form->addInput("Itemのテクスチャへのパス", "", "");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
    // </editor-fold>

    // <editor-fold desc="定期メッセージ設定">
    public function SetRegularMessage(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0] === true) {
                        $player->sendMessage("§b[定期メッセージ設定] >> §eキャンセルしました");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
                        return true;
                    } elseif (!isset($data[1])) return true;
                    $this->plugin->db->SetRegularMessage($data[1]);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、定期メッセージを追加しました\ncontent: $data[1]"));
                    $player->sendMessage("§b[定期メッセージ設定] >> §a設定しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "SetRegularMessage"], [$player]), 20);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-Admin-定期メッセージ追加");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("メッセージ", "content", "");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
    // </editor-fold>

    // <editor-fold desc="定期メッセージ削除">
    public function DeleteRegularMessage(Player $player)
    {
        $allmessages = $this->plugin->db->GetRegularMessageAll();
        if (!$allmessages) {
            $player->sendMessage("§b[定期メッセージ削除] >> §4現在定期メッセージは1つもないようです");
            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
            return;
        }

        $messages = [];
        foreach ($allmessages as $message) {
            $messages[] = $messag["content"];
        }


        $form = new CustomForm(function (Player $player, $data) use ($allmessages) {
            try {
                if ($data === null) return true;
                elseif ($data[0] === true) {
                    $player->sendMessage("§b[土地保護] >> §eキャンセルしました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "AdminForm"], [$player]), 20);
                    return true;
                }

                $message = $allmessages[$data[1]];
                $this->plugin->db->DeleteLand($message["id"]);
                $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()} がAdminを使用し、定期メッセージを削除しました\nid {$message["id"]} content {$message["content"]}"));
                $player->sendMessage("§b[土地保護] >> §6定期メッセージID #{$message["id"]} を削除しました");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "ForcedLandAbandonment"], [$player]), 20);
            } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                $this->plugin->errorHandler->onError($e, $player);
            }

            return true;
        });

        $form->setTitle("OutiWatch-Admin-定期メッセージ削除");
        $form->addToggle("キャンセルして戻る");
        $form->addDropdown("削除するメッセージ", $messages);
        $player->sendForm($form);
    }
    // </editor-fold>
}