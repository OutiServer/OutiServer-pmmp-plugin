<?php

declare(strict_types=1);

namespace OutiServerPlugin\plugins;

use ArgumentCountError;
use Error;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\{CustomForm, ModalForm, SimpleForm};
use OutiServerPlugin\Main;
use pocketmine\level\Position;
use pocketmine\Player;
use TypeError;

class Land
{
    private Main $plugin;
    public array $startlands = [];
    public array $endlands = [];

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function land(Player $player)
    {
        try {
            $form = new SimpleForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;

                    switch ($data) {
                        case 0:
                            foreach ($this->plugin->config->get('Land_Buy_Bans', array()) as $key) {
                                if ($key === $player->getLevel()->getName()) {
                                    $player->sendMessage("§b[土地保護] >> §rこのワールドの土地は購入できません");
                                    return true;
                                }
                            }

                            $x = (int)floor($player->x);
                            $z = (int)floor($player->z);
                            $levelname = $player->getLevel()->getName();
                            $name = $player->getName();

                            if (isset($this->startlands[$name])) {
                                if ($this->startlands[$name]["level"] !== $levelname) {
                                    $player->sendMessage("§b[土地保護] >> §r土地保護の開始地点とワールドが違います");
                                    unset($this->startlands[$name], $this->endlands[$name]);
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
                            $this->protectionland($player);
                            break;
                        case 2:
                            $this->inviteland($player);
                            break;
                        case 3:
                            $this->allinvitesland($player);
                            break;
                        case 4:
                            $this->MoveLandOwner($player);
                            break;
                        case 5:
                            $this->CheckLandId($player);
                            break;
                        case 6:
                            $this->TeleportLand($player);
                            break;
                    }

                    return true;
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onErrorNotPlayer($e);
                }

                return true;
            });

            $form->setTitle("iPhone-土地");
            $form->addButton("土地購入の開始地点・終了地点の設定");
            $form->addButton("土地を保護・保護解除");
            $form->addButton("土地に招待・招待取り消し");
            $form->addButton("土地に招待されている人一覧");
            $form->addButton("土地の所有権の移行");
            $form->addButton("立っている土地のID確認");
            $form->addButton("土地IDを指定してテレポート");
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
                $player->sendMessage("選択された土地は既に" . $landdata["owner"] . "が所有しています");
                return;
            }

            $blockcount = ((($endX + 1) - ($startX - 1)) - 1) * ((($endZ + 1) - ($startZ - 1)) - 1);
            $price = $blockcount * $this->plugin->config->get("Land_Price", 200);

            $form = new ModalForm(function (Player $player, $data) use ($levelname, $price, $startX, $startZ, $endX, $endZ) {
                try {
                    $name = $player->getName();
                    unset($this->startlands[$name], $this->endlands[$name]);
                    if ($data === true) {
                        $playerdata = $this->plugin->db->GetMoney($name);
                        if ($price > $playerdata["money"]) {
                            $player->sendMessage("§b[土地保護] >> §4お金が" . ($playerdata["money"] - $price) * -1 . "円足りていませんよ？");
                            return true;
                        }

                        $this->plugin->db->UpdateMoney($name, $playerdata["money"] - $price);
                        $id = $this->plugin->db->SetLand($name, $levelname, $player->y, $startX, $startZ, $endX, $endZ);
                        $player->sendMessage("§b[土地保護] >> §6購入しました\n購入した土地番号は #$id です、招待・TPなどに使用しますので控えておいてください。");
                    } elseif ($data === false) {
                        $player->sendMessage("§b[土地保護] >> §6購入しませんでした");
                    }
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onErrorNotPlayer($e);
                }

                return true;
            });

            $form->setTitle("iPhone-土地-購入");
            $form->setContent("土地を" . $blockcount . "ブロック購入しますか？\n" . $price . "円です");
            $form->setButton1("購入する");
            $form->setButton2("やめる");
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
            if(!$alllands) {
                $player->sendMessage("§b[土地保護] >> §4あなたが現在所有している土地は存在しないようです。");
                return;
            }

            $form = new CustomForm(function (Player $player, $data) use ($name, $alllands) {
                try {
                    if ($data === null) return true;
                    else if (!is_numeric($data[0])) return true;

                    $landid = (int)$alllands[(int)$data[0]];
                    $landdata = $this->plugin->db->GetLandData($landid);
                    if(!$landdata) {
                        $player->sendMessage("§b[土地保護] >> §4土地データが見つかりませんでした");
                        return true;
                    }

                    if($this->plugin->db->CheckLandProtection($landid)) {
                        $this->plugin->db->UpdateLandProtection($landid, 0);
                        $player->sendMessage("§b[土地保護] >> §4土地ID #$landid の土地保護を無効にしました");
                    }
                    else {
                        $this->plugin->db->UpdateLandProtection($landid, 1);
                        $player->sendMessage("§b[土地保護] >> §6土地ID #$landid の土地保護を有効にしました");
                    }

                    return true;
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onErrorNotPlayer($e);
                }

                return true;
            });

            $form->setTitle("iPhone-土地-保護");
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
            if(!$alllands) {
                $player->sendMessage("§b[土地保護] >> §4あなたが現在所有している土地は存在しないようです。");
                return;
            }

            $form = new CustomForm(function (Player $player, $data) use ($alllands) {
                try {
                    if ($data === null) return true;
                    else if (!is_numeric($data[0]) or !isset($data[1])) return true;
                    else if (!Player::isValidUserName($data[1])) {
                        $player->sendMessage("§b[土地保護] >> §4不正なプレイヤー名です");
                        return true;
                    }

                    $landid = (int)$alllands[(int)$data[0]];

                    if ($this->plugin->db->checkInvite($landid, $data[1])) {
                        if ($this->plugin->db->RemoveLandInvite($landid, $data[1])) {
                            $player->sendMessage("§b[土地保護] >> §6$data[1]の土地ID #$landid の招待を削除しました");
                        }
                    } else {
                        $this->plugin->db->AddLandInvite($landid, $data[1]);
                        $player->sendMessage("§b[土地保護] >> §6$data[1]を土地ID #$landid に招待しました");
                    }

                    return true;
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onErrorNotPlayer($e);
                }

                return true;
            });

            $form->setTitle("iPhone-土地-招待");
            $form->addDropdown("土地保護・保護解除する土地ID", $alllands);
            $form->addInput("招待する・招待を取り消すプレイヤー名", "playername", "");
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
            if(!$alllands) {
                $player->sendMessage("§b[土地保護] >> §4あなたが現在所有している土地は存在しないようです。");
                return;
            }

            $form = new CustomForm(function (Player $player, $data) use ($alllands) {
                try {
                    if ($data === null) return true;
                    elseif (!is_numeric($data[0])) return true;

                    $landid = (int)$alllands[(int)$data[0]];
                    $invites = $this->plugin->db->GetLandInvites($landid);
                    if ($invites === null) {
                        $player->sendMessage("§b[土地保護] >> §6土地ID #$landid には誰も招待されていません");
                        return true;
                    }
                    $invitestext = "土地ID #$landid に招待されている人数: " . count($invites);
                    for ($i = 0; $i < count($invites); $i++) {
                        $invitestext .= "\n$invites[$i]";
                    }
                    $player->sendMessage("§b[土地保護] >> ");
                    $player->sendMessage("§6" . $invitestext);

                    return true;
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onErrorNotPlayer($e);
                }

                return true;
            });

            $form->setTitle("iPhone-土地-招待されている人一覧");
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
            if(!$alllands) {
                $player->sendMessage("§b[土地保護] >> §4あなたが現在所有している土地は存在しないようです。");
                return;
            }

            $form = new CustomForm(function (Player $player, $data) use ($alllands) {
                try {
                    if ($data === null) return true;
                    else if (!is_numeric($data[0]) or !isset($data[1])) return true;
                    else if (!Player::isValidUserName($data[1])) {
                        $player->sendMessage("§b[土地保護] >> §4不正なプレイヤー名です");
                        return true;
                    }

                    $landid = (int)$alllands[(int)$data[0]];
                    $this->plugin->db->ChangeLandOwner($landid, $data[1]);
                    $player->sendMessage("§b[土地保護] >> §6土地ID #$landid の所有権を$data[1]に譲渡しました");

                    return true;
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onErrorNotPlayer($e);
                }

                return true;
            });

            $form->setTitle("iPhone-土地-所有権譲渡");
            $form->addDropdown("所有権を渡す土地ID", $alllands);
            $form->addInput("所有権を渡すプレイヤー名", "playername", "");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function CheckLandId(Player $player)
    {
        try {
            $landid = $this->plugin->db->GetLandId($player->getLevel()->getName(), (int)$player->x, (int)$player->z);
            if(!$landid) {
                $player->sendMessage("§b[土地保護] >> §4この土地は誰も所有してないようです");
                return;
            }

            $landdata = $this->plugin->db->GetLandData($landid);
            if($this->plugin->db->checkInvite($landid, $player->getName())) {
                $invite = "あなたはこの土地に招待されています";
            }
            else {
                $invite = "あなたはこの土地に招待されていません";
            }
            $player->sendMessage("§b[土地保護] >> §6土地ID #$landid\n所有者 {$landdata["owner"]}\n$invite");
        }
        catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function TeleportLand(Player $player)
    {
        try {
            $name = $player->getName();
            $alllands = $this->plugin->db->GetAllLandOwnerInviteData($name);
            if(!$alllands) {
                $player->sendMessage("§b[土地保護] >> §4あなたが現在所有している・招待されている土地は存在しないようです。");
                return;
            }

            $form = new CustomForm(function (Player $player, $data) use ($alllands) {
                try {
                    if ($data === null) return true;
                    else if (!is_numeric($data[0])) return true;

                    $landid = (int)$alllands[(int)$data[0]];
                    $landdata = $this->plugin->db->GetLandData($landid);
                    if (!$this->plugin->getServer()->isLevelGenerated($landdata["levelname"])) {
                        $player->sendMessage("§b[土地保護] >> §4テレポート先のワールドが存在しないようです");
                        return true;
                    }
                    if (!$this->plugin->getServer()->isLevelLoaded($landdata["levelname"])) {
                        $this->plugin->getServer()->loadLevel($landdata["levelname"]);
                    }
                    $level = $this->plugin->getServer()->getLevelByName($landdata["levelname"]);
                    $pos = new Position($landdata["startx"], $landdata["y"], $landdata["startz"], $level);
                    $player->teleport($pos);
                    $player->sendMessage("§b[土地保護] >> §6土地ID #$landid にテレポートしました");

                    return true;
                } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("iPhone-土地-テレポート");
            $form->addDropdown("テレポートする土地ID", $alllands);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}