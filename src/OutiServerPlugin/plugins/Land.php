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
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\utils\Config;
use TypeError;

class Land
{
    private Main $plugin;
    public array $startlands = [];
    public array $endlands = [];
    private Config $landprice;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        $this->landprice = new Config($this->plugin->getDataFolder() . "land.yml", Config::YAML);
    }

    public function land(Player $player)
    {
        try {
            $form = new SimpleForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;

                    switch ($data) {
                        case 0:
                            if (in_array($player->getLevel()->getName(), $this->plugin->config->get('Land_Buy_Bans', array()))) {
                                $player->sendMessage("§b[土地保護] >> §rこのワールドの土地は購入できません");
                                return true;
                            }

                            $x = (int)floor($player->x);
                            $z = (int)floor($player->z);
                            $levelname = $player->getLevel()->getName();
                            $name = $player->getName();

                            if (isset($this->startlands[$name])) {
                                if ($this->startlands[$name]["level"] !== $levelname) {
                                    $player->sendMessage("§b[土地保護] >> §r土地保護の開始地点とワールドが違います");
                                    return true;
                                }

                                $this->endlands[$name] = array("x" => $x, "z" => $z, "level" => $levelname);
                                $this->buyland($player);

                            } else {
                                $this->startlands[$name] = array("x" => $x, "z" => $z, "level" => $levelname);
                                $player->sendMessage("§b[土地保護] >> §r土地購入の開始地点を設定しました");
                            }


                            break;
                        case 1:
                            $name = $player->getName();
                            unset($this->endlands[$name], $this->startlands[$name]);
                            $player->sendMessage("§b[土地保護] >> §e土地購入の地点をリセットしました");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                            break;
                        case 2:
                            $this->protectionland($player);
                            break;
                        case 3:
                            $this->inviteland($player);
                            break;
                        case 4:
                            $this->allinvitesland($player);
                            break;
                        case 5:
                            $this->MoveLandOwner($player);
                            break;
                        case 6:
                            $this->SetTereport($player);
                            break;
                        case 7:
                            $this->AbandonedLand($player);
                            break;
                        case 8:
                            $this->CheckLandId($player);
                            break;
                        case 9:
                            $this->TeleportLand($player);
                            break;
                        case 10:
                            $this->ChangePerms($player);
                            break;
                        case 11:
                            $this->plugin->applewatch->Form($player);
                            break;
                    }
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-土地");
            $form->addButton("土地購入の開始地点・終了地点の設定");
            $form->addButton("土地購入の地点をリセット");
            $form->addButton("購入した土地を保護・保護解除");
            $form->addButton("購入した土地に招待・招待取り消し");
            $form->addButton("購入した土地に招待されている人一覧");
            $form->addButton("購入した土地の所有権の移行");
            $form->addButton("購入した土地のtp地点を設定");
            $form->addButton("購入した土地放棄");
            $form->addButton("立っている土地のID確認");
            $form->addButton("土地IDを指定してテレポート");
            $form->addButton("土地の権限変更");
            $form->addButton("戻る");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function buyland(Player $player)
    {
        try {
            $name = $player->getName();
            $levelname = $this->startlands[$name]["level"];
            $l = $this->startlands[$name];
            $endp = $this->endlands[$name];
            $startX = (int)floor($l["x"]);
            $endX = (int)floor($endp["x"]);
            $startZ = (int)floor($l["z"]);
            $endZ = (int)floor($endp["z"]);
            if ($startX > $endX) {
                $backup = $startX;
                $startX = $endX;
                $endX = $backup;
            }
            if ($startZ > $endZ) {
                $backup = $startZ;
                $startZ = $endZ;
                $endZ = $backup;
            }

            if ($landid = $this->plugin->db->GetLandId($levelname, $startX, $startZ) or $landid = $this->plugin->db->GetLandId($levelname, $endX, $endZ)) {
                $landdata = $this->plugin->db->GetLandData($landid);
                unset($this->startlands[$name], $this->endlands[$name]);
                $player->sendMessage("選択された土地は既に" . $landdata["owner"] . "が所有しています");
                return;
            }

            $blockcount = ((($endX + 1) - ($startX - 1)) - 1) * ((($endZ + 1) - ($startZ - 1)) - 1);
            $price = $blockcount * $this->plugin->landconfig->get($levelname, array('price' => 200))["price"];

            $form = new CustomForm(function (Player $player, $data) use ($levelname, $price, $startX, $startZ, $endX, $endZ) {
                try {
                    if ($data === null) return true;
                    $name = $player->getName();
                    unset($this->startlands[$name], $this->endlands[$name]);
                    if ($data[1] === false) {
                        $playerdata = $this->plugin->db->GetMoney($name);
                        if ($price > $playerdata["money"]) {
                            $player->sendMessage("§b[土地保護] >> §4お金が" . ($playerdata["money"] - $price) * -1 . "円足りていませんよ？");

                        } else {
                            $this->plugin->db->UpdateMoney($name, $playerdata["money"] - $price);
                            $perms = array(
                                $data[5],
                                $data[6],
                                $data[7]
                            );
                            $id = $this->plugin->db->SetLand($name, $levelname, $startX, $startZ, $endX, $endZ, $perms, (int)$data[2], $data[3] ? $player->asVector3() : null);
                            $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()}が土地購入を使用し ワールド $levelname 開始X座標 $startX 開始Z座標 $startZ 終了X座標 $endX 終了Z座標 $endZ の土地を $price 円で購入しました\n土地ID $id\n" . $data[3] ? "TP地点 X座標 {$player->asVector3()->getX()} Y座標 {$player->asVector3()->getY()} Z座標 {$player->asVector3()->getZ()}" : "\n権限: $data[5] $data[6] $data[7]"));
                            $player->sendMessage("§b[土地保護] >> §6購入しました\n購入した土地番号は #$id です、招待・TPなどに使用しますので控えておいてください。");
                        }

                    } elseif ($data[1] === true) {
                        $player->sendMessage("§b[土地保護] >> §6購入しませんでした");
                    }

                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-土地-購入");
            $form->addLabel("土地を{$blockcount}ブロック購入しますか？\n{$price}円です");
            $form->addToggle("キャンセルして戻る", false);
            $form->addToggle("購入時土地保護を有効にする", true);
            $form->addToggle("現在立っている場所をtp地点に設定する", false);
            $form->addLabel("ーーー招待されてないプレイヤーができる操作設定ーーー");
            $form->addToggle("ブロックタップ・ブロック設置", false);
            $form->addToggle("ブロック破壊", false);
            $form->addToggle("侵入した時に警報装置を作動させる", true);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function protectionland(Player $player)
    {
        try {
            $name = $player->getName();
            $alllands = $this->plugin->db->GetAllLandOwnerData($name);
            if (!$alllands) {
                $player->sendMessage("§b[土地保護] >> §4あなたが現在所有している土地は存在しないようです。");
                return;
            }

            $form = new CustomForm(function (Player $player, $data) use ($name, $alllands) {
                try {
                    if ($data === null) return true;
                    else if (!is_numeric($data[0])) return true;

                    $landid = (int)$alllands[(int)$data[0]];
                    $landdata = $this->plugin->db->GetLandData($landid);
                    if (!$landdata) {
                        $player->sendMessage("§b[土地保護] >> §4土地データが見つかりませんでした");
                        return true;
                    }

                    if ($this->plugin->db->CheckLandProtection($landid)) {
                        $this->plugin->db->UpdateLandProtection($landid, 0);
                        $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()}が土地保護設定を使用し Id $landid の土地保護を無効化しました"));
                        $player->sendMessage("§b[土地保護] >> §4土地ID #$landid の土地保護を無効にしました");
                    } else {
                        $this->plugin->db->UpdateLandProtection($landid, 1);
                        $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()}が土地保護設定を使用し Id $landid の土地保護を有効化しました"));
                        $player->sendMessage("§b[土地保護] >> §6土地ID #$landid の土地保護を有効にしました");
                    }

                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-土地-保護");
            $form->addDropdown("土地保護・保護解除する土地ID", $alllands);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function inviteland(Player $player)
    {
        try {
            $name = $player->getName();
            $alllands = $this->plugin->db->GetAllLandOwnerData($name);
            if (!$alllands) {
                $player->sendMessage("§b[土地保護] >> §4あなたが現在所有している土地は存在しないようです。");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                return;
            }

            $form = new CustomForm(function (Player $player, $data) use ($alllands) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0]) {
                        $player->sendMessage("§b[土地保護] >> §eキャンセルしました");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                        return true;
                    } else if (!is_numeric($data[1]) or !isset($data[2])) return true;
                    else if (!Player::isValidUserName($data[2])) {
                        $player->sendMessage("§b[土地保護] >> §4不正なプレイヤー名です");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "inviteland"], [$player]), 20);
                        return true;
                    }

                    $landid = (int)$alllands[(int)$data[1]];

                    if ($this->plugin->db->checkInvite($landid, $data[2])) {
                            $this->plugin->db->RemoveLandInvite($landid, $data[2]);
                            $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()}が土地招待を使用し Id $landid の $data[2] の招待を取り消しました"));
                            $player->sendMessage("§b[土地保護] >> §6$data[2]の土地ID #$landid の招待を削除しました");
                    } else {
                        $perms = array(
                            $data[4],
                            $data[5]
                        );
                        $this->plugin->db->AddLandInvite($landid, $data[2], $perms);
                        $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()}が土地招待を使用し Id $landid に $data[2] を招待しました\n権限 $data[4] $data[5]"));
                        $player->sendMessage("§b[土地保護] >> §6$data[2]を土地ID #$landid に招待しました");
                    }

                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-土地-招待");
            $form->addToggle("キャンセルして戻る", false);
            $form->addDropdown("土地に招待・招待を取り消す土地ID", $alllands);
            $form->addInput("招待する・招待を取り消すプレイヤー名", "playername", "");
            $form->addLabel("ーーー招待するプレイヤーができる操作設定ーーー");
            $form->addToggle("ブロックタップ・ブロック設置", true);
            $form->addToggle("ブロック破壊", true);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function allinvitesland(Player $player)
    {
        try {
            $name = $player->getName();
            $alllands = $this->plugin->db->GetAllLandOwnerData($name);
            if (!$alllands) {
                $player->sendMessage("§b[土地保護] >> §4あなたが現在所有している土地は存在しないようです。");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                return;
            }

            $form = new CustomForm(function (Player $player, $data) use ($alllands) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0] === true) {
                        $player->sendMessage("§b[土地保護] >> §eキャンセルしました");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                        return true;
                    } elseif (!is_numeric($data[1])) return true;

                    $landid = (int)$alllands[(int)$data[1]];
                    $invites = $this->plugin->db->GetLandInvites($landid);
                    $invitestext = "土地ID #$landid に招待されている人数: " . count($invites);
                    foreach ($invites as $key) {
                        $invitestext .= "\n{$key["name"]}";
                    }
                    $player->sendMessage("§b[土地保護] >> ");
                    $player->sendMessage("§6" . $invitestext);
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-土地-招待されている人一覧");
            $form->addToggle("キャンセルして戻る", false);
            $form->addDropdown("招待されている人一覧を確認する土地ID", $alllands);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function MoveLandOwner(Player $player)
    {
        try {
            $name = $player->getName();
            $alllands = $this->plugin->db->GetAllLandOwnerData($name);
            if (!$alllands) {
                $player->sendMessage("§b[土地保護] >> §4あなたが現在所有している土地は存在しないようです。");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                return;
            }

            $form = new CustomForm(function (Player $player, $data) use ($alllands) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0] === true) {
                        $player->sendMessage("§b[土地保護] >> §eキャンセルしました");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                        return true;
                    } else if (!is_numeric($data[1]) or !isset($data[2])) return true;
                    else if (!Player::isValidUserName($data[2])) {
                        $player->sendMessage("§b[土地保護] >> §4不正なプレイヤー名です");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "MoveLandOwner"], [$player]), 20);
                        return true;
                    }

                    $landid = (int)$alllands[(int)$data[1]];
                    $this->plugin->db->ChangeLandOwner($landid, $data[2]);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()}が土地所有権譲渡を使用し 土地ID $landid の所有権を $data[2] に譲渡しました"));
                    $player->sendMessage("§b[土地保護] >> §6土地ID #$landid の所有権を$data[2]に譲渡しました");

                    return true;
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-土地-所有権譲渡");
            $form->addToggle("キャンセルして戻る", false);
            $form->addDropdown("所有権を渡す土地ID", $alllands);
            $form->addInput("所有権を渡すプレイヤー名", "playername", "");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function SetTereport(Player $player)
    {
        try {
            $landid = $this->plugin->db->GetLandId($player->getLevel()->getName(), (int)$player->x, (int)$player->z);
            if (!$landid) {
                $player->sendMessage("§b[土地保護] >> §4現在立っている場所はあなたが所有していません");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                return;
            } elseif (!$this->plugin->db->CheckLandOwner($landid, $player->getName())) {
                $player->sendMessage("§b[土地保護] >> §4現在立っている場所はあなたが所有していません");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                return;
            }

            $this->plugin->db->UpdateLandTereport($landid, $player->asVector3());
            $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()}が土地TP地点設定を使用し 土地ID $landid のTP地点を X座標 {$player->asVector3()->getX()} Y座標 {$player->asVector3()->getY()} Z座標 {$player->asVector3()->getZ()} に変更しました"));
            $player->sendMessage("§b[土地保護] >> §d土地ID #$landid のテレポート場所を変更しました");
            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function AbandonedLand(Player $player)
    {
        try {
            $name = $player->getName();
            $alllands = $this->plugin->db->GetAllLandOwnerData($name);
            if (!$alllands) {
                $player->sendMessage("§b[土地保護] >> §4あなたが現在所有している土地は存在しないようです。");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                return;
            }

            $form = new CustomForm(function (Player $player, $data) use ($alllands) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0] === true) {
                        $player->sendMessage("§b[土地保護] >> §eキャンセルしました");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                        return true;
                    } else if (!is_numeric($data[1])) return true;

                    $landid = (int)$alllands[(int)$data[1]];
                    $this->plugin->db->DeleteLand($landid);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()}が土地放棄を使用し 土地ID $landid の土地を放棄しました"));
                    $player->sendMessage("§b[土地保護] >> §6土地ID #$landid を私有地から削除しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-土地-放棄");
            $form->addToggle("キャンセルして戻る");
            $form->addDropdown("土地放棄する土地ID", $alllands);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function CheckLandId(Player $player)
    {
        try {
            $landid = $this->plugin->db->GetLandId($player->getLevel()->getName(), (int)$player->x, (int)$player->z);
            if (!$landid) {
                $player->sendMessage("§b[土地保護] >> §4この土地は誰も所有してないようです");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                return;
            }

            $landdata = $this->plugin->db->GetLandData($landid);
            if ($this->plugin->db->checkInvite($landid, $player->getName())) {
                $invite = "あなたはこの土地に招待されています";
            } else {
                $invite = "あなたはこの土地に招待されていません";
            }
            $player->sendMessage("§b[土地保護] >> §6土地ID #$landid\n所有者 {$landdata["owner"]}\n$invite");
            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function TeleportLand(Player $player)
    {
        try {
            $name = $player->getName();
            $alllands = $this->plugin->db->GetAllLandOwnerInviteData($name);
            if (!$alllands) {
                $player->sendMessage("§b[土地保護] >> §4あなたが現在所有してTP地点が設定されている土地・招待されていてTP地点が設定されている土地は存在しないようです。");
                $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                return;
            }

            $form = new CustomForm(function (Player $player, $data) use ($alllands) {
                try {
                    if ($data === null) return true;
                    elseif ($data[0] === true) {
                        $player->sendMessage("§b[土地保護] >> §eキャンセルしました");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                        return true;
                    } elseif (!is_numeric($data[1])) return true;

                    $landid = (int)$alllands[(int)$data[1]];
                    $landdata = $this->plugin->db->GetLandData($landid);
                    if (!$this->plugin->getServer()->isLevelGenerated($landdata["levelname"])) {
                        $player->sendMessage("§b[土地保護] >> §4テレポート先のワールドが存在しないようです");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "TeleportLand"], [$player]), 20);
                        return true;
                    }
                    if (!$this->plugin->getServer()->isLevelLoaded($landdata["levelname"])) {
                        $this->plugin->getServer()->loadLevel($landdata["levelname"]);
                    }
                    $level = $this->plugin->getServer()->getLevelByName($landdata["levelname"]);
                    $pos = new Position($landdata["tpx"], $landdata["tpy"], $landdata["tpz"], $level);
                    $oldtp = $player;
                    $player->teleport($pos);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()}が土地TPを使用し、 ワールド {$oldtp->getLevel()->getName()} X座標 {$oldtp->getX()} Y座標 {$oldtp->getY()} Z座標 {$oldtp->getZ()} から ワールド {$level->getName()} X座標 {$player->getX()} Y座標 {$player->getY()} Z座標 {$player->getZ()} にTPしました"));
                    $player->sendMessage("§b[土地保護] >> §6土地ID #$landid にテレポートしました");

                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-土地-テレポート");
            $form->addToggle("キャンセルして戻る", false);
            $form->addDropdown("テレポートする土地ID", $alllands);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function ChangePerms(Player $player)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) {
                try {
                    $name = $player->getName();
                    if ($data === null) return true;
                    elseif ($data[0] === true) {
                        $player->sendMessage("§b[土地保護] >> §eキャンセルしました");
                        $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                        return true;
                    }
                    elseif (!is_numeric($data[1])) return true;
                    elseif (!$this->plugin->db->CheckLandOwner((int)$data[1], $name)) {
                        $player->sendMessage("§b[土地保護] >> §4土地ID $data[1] はあなたが所有していません");
                        return true;
                    }

                    $perms = array(
                        $data[2],
                        $data[3],
                        $data[4]
                    );
                    $this->plugin->db->UpdateLandPemrs((int)$data[1], $perms);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "$name が 土地ID $data[1] の権限を $data[2] $data[3] $data[4] に変更しました"));
                    $player->sendMessage("§b[土地保護] >> §6土地ID #$data[1] の権限を変更しました");
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "land"], [$player]), 20);
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-土地-権限変更");
            $form->addToggle("キャンセルして戻る");
            $form->addInput("土地ID", "id", "");
            $form->addToggle("ブロックタップ・ブロック設置", false);
            $form->addToggle("ブロック破壊", false);
            $form->addToggle("侵入した時に警報装置を作動させる", true);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}