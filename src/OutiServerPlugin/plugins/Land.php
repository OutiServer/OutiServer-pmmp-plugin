<?php

declare(strict_types=1);

namespace OutiServerPlugin\plugins;

use ArgumentCountError;
use jojoe77777\FormAPI\{CustomForm, ModalForm, SimpleForm};
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use OutiServerPlugin\Main;
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
                if ($data === null) return true;

                switch ($data) {
                    case 0:
                        foreach ($this->plugin->config->get('Land_Buy_Bans', array()) as $key) {
                            if ($key === $player->getLevel()->getName()) {
                                $player->sendMessage("このワールドの土地は購入できません");
                                return true;
                            }
                        }

                        $x = (int)floor($player->x);
                        $z = (int)floor($player->z);
                        $levelname = $player->getLevel()->getName();
                        $name = $player->getName();

                        if(isset($this->startlands[$name])) {
                            if($this->startlands[$name]["level"] !== $levelname) {
                                $player->sendMessage("土地保護の開始地点とワールドが違います");
                                unset($this->startlands[$name], $this->endlands[$name]);
                                return true;
                            }

                            $this->endlands[$name] = array("x" => $x, "z" => $z, "level" => $levelname);
                            $this->buyland($player);

                        }
                        else {
                            $this->startlands[$name] = array("x" => $x, "z" => $z, "level" => $levelname);
                            $player->sendMessage("土地購入の開始地点を設定しました");
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
                }

                return true;
            });

            $form->setTitle("iPhone-土地");
            $form->addButton("土地購入の開始地点・終了地点の設定");
            $form->addButton("現在立っている土地を保護・保護解除");
            $form->addButton("現在立っている土地に招待・招待取り消し");
            $form->addButton("現在立っている土地に招待されている人一覧");
            $form->addButton("現在立っている土地の所有権の移行");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
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

            if($landid = $this->plugin->db->GetLandId($levelname, $startX, $startZ) or $landid = $this->plugin->db->GetLandId($levelname, $endX, $endZ)) {
                $landdata = $this->plugin->db->GetLandData($landid);
                $player->sendMessage("選択された土地は既に" . $landdata["owner"] . "が所有しています");
                return;
            }

            $blockcount = ((($endX + 1) - ($startX - 1)) - 1) * ((($endZ + 1) - ($startZ - 1)) - 1);
            $price = $blockcount * $this->plugin->config->get("Land_Price", 200);

            $form = new ModalForm(function (Player $player, $data) use ($levelname, $price, $startX, $startZ, $endX, $endZ) {
                $name = $player->getName();
                if ($data === true) {
                    $playerdata = $this->plugin->db->GetMoney($name);
                    if ($price > $playerdata["money"]) {
                        $player->sendMessage("お金が" . ($playerdata["money"] - $price) * -1 . "円足りていませんよ？");
                        return;
                    }

                    $this->plugin->db->UpdateMoney($name, $playerdata["money"] - $price);
                    $this->plugin->db->SetLand($name, $levelname, $startX, $startZ, $endX, $endZ);
                    $player->sendMessage("購入しました");
                } elseif ($data === false) {
                    $player->sendMessage("購入しませんでした");
                }

                unset($this->startlands[$name], $this->endlands[$name]);
            });

            $form->setTitle("iPhone-土地-購入");
            $form->setContent("土地を" . $blockcount . "ブロック購入しますか？\n" . $price . "円です");
            $form->setButton1("購入する");
            $form->setButton2("やめる");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function protectionland(Player $player)
    {
        try {
            $name = $player->getName();
            $levelname = $player->getLevel()->getName();
            $landid = $this->plugin->db->GetLandId($levelname, (int)$player->x, (int)$player->z);
            if ($landid === false) {
                $player->sendMessage("この土地はあなたが所有していません");
                return;
            }

            if (!$this->plugin->db->CheckLandOwner($landid, $name)) {
                $player->sendMessage("この土地はあなたが所有していません");
                return;
            }
            if (!$this->plugin->db->CheckLandProtection($landid)) {
                $form = new ModalForm(function (Player $player, $data) use ($landid) {
                    if ($data === null) {
                        return;
                    }

                    $this->plugin->db->UpdateLandProtection($landid, 1);
                    $player->sendMessage("土地保護を有効にしました");
                });

                $form->setTitle("iPhone-土地-保護");
                $form->setContent("現在立っている土地の保護を有効にしますか？");
                $form->setButton1("有効にする");
            } else {
                $form = new ModalForm(function (Player $player, $data) use ($landid) {
                    if ($data === null) return true;

                    $this->plugin->db->UpdateLandProtection($landid, 0);
                    $player->sendMessage("土地保護を無効にしました");
                    return true;
                });

                $form->setTitle("iPhone-土地-購入");
                $form->setContent("現在立っている土地の保護を無効にしますか？");
                $form->setButton1("無効にする");
            }

            $form->setButton2("やめる");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function inviteland(Player $player)
    {
        try {
            $name = $player->getName();
            $levelname = $player->getLevel()->getName();
            $landid = $this->plugin->db->GetLandId($levelname, (int)$player->x, (int)$player->z);
            if ($landid === false) {
                $player->sendMessage("この土地はあなたが所有していません");
                return;
            } elseif (!$this->plugin->db->CheckLandOwner($landid, $name)) {
                $player->sendMessage("この土地はあなたが所有していません");
                return;
            }

            $form = new CustomForm(function (Player $player, $data) use ($landid) {
                if ($data === null) return true;
                else if (!isset($data[0])) return true;
                else if (!Player::isValidUserName($data[0])) {
                    $player->sendMessage("不正なプレイヤー名です");
                    return true;
                }

                if ($this->plugin->db->checkInvite($landid, $data[0])) {
                    if ($this->plugin->db->RemoveLandInvite($landid, $data[0])) {
                        $player->sendMessage("$data[0]の土地番号$landid の招待を削除しました");
                    }
                } else {
                    $this->plugin->db->AddLandInvite($landid, $data[0]);
                    $player->sendMessage("$data[0]を土地番号$landid に招待しました");
                }

                return true;
            });

            $form->setTitle("iPhone-土地-招待");
            $form->addInput("招待する・招待を取り消すプレイヤー名", "playername", "");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function allinvitesland(Player $player)
    {
        try {
            $levelname = $player->getLevel()->getName();
            $name = $player->getName();
            $landid = $this->plugin->db->GetLandId($levelname, (int)$player->x, (int)$player->z);
            if ($landid === false) {
                $player->sendMessage("この土地はあなたが所有していません");
                return;
            } elseif (!$this->plugin->db->CheckLandOwner($landid, $name)) {
                $player->sendMessage("この土地はあなたが所有していません");
                return;
            }

            $invites = $this->plugin->db->GetLandInvites($landid);
            if ($invites === null) {
                $player->sendMessage("この土地には誰も招待されていません");
                return;
            }
            $invitestext = "土地ID$landid に招待されている人数: " . count($invites);
            for ($i = 0; $i < count($invites); $i++) {
                $invitestext .= "\n$invites[$i]";
            }

            $player->sendMessage($invitestext);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function MoveLandOwner(Player $player)
    {
        try {
            $levelname = $player->getLevel()->getName();
            $name = $player->getName();
            $landid = $this->plugin->db->GetLandId($levelname, (int)$player->x, (int)$player->z);
            if ($landid === false) {
                $player->sendMessage("この土地はあなたが所有していません");
                return;
            } elseif (!$this->plugin->db->CheckLandOwner($landid, $name)) {
                $player->sendMessage("この土地はあなたが所有していません");
                return;
            }

            $form = new CustomForm(function (Player $player, $data) use ($landid) {
                if ($data === null) return true;
                else if (!isset($data[0])) return true;
                else if (!Player::isValidUserName($data[0])) {
                    $player->sendMessage("不正なプレイヤー名です");
                    return true;
                }

                $this->CheckMoveLandOwner($player, $landid, $data[0]);
                return true;
            });

            $form->setTitle("iPhone-土地-所有権譲渡");
            $form->addInput("所有権を渡すプレイヤー名", "playername", "");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function CheckMoveLandOwner(Player $player, int $landid, string $name)
    {
        try {
            $form = new ModalForm(function (Player $player, $data) use ($landid, $name) {
                if ($data === null) {
                    return;
                }

                if($data === true) {
                    $this->plugin->db->ChangeLandOwner($landid, $name);
                    $player->sendMessage("所有権を$name に譲渡しました");
                }
                else {
                    $player->sendMessage("キャンセルしました");
                }
            });

            $form->setTitle("iPhone-土地-所有権譲渡");
            $form->setContent("現在立っている土地の所有権を$name に譲渡しますか？");
            $form->setButton1("譲渡する");
            $form->setButton2("やめる");
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}