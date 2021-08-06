<?php

declare(strict_types=1);

namespace OutiServerPlugin\plugins\Casinos;

use ArgumentCountError;
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\ModalForm;
use jojoe77777\FormAPI\SimpleForm;
use OutiServerPlugin\Main;
use OutiServerPlugin\Tasks\ReturnForm;
use OutiServerPlugin\Tasks\SlotTask;
use OutiServerPlugin\Utils\OutiServerPluginEnum;
use pocketmine\block\Block;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\Player;
use pocketmine\tile\Tile;
use pocketmine\utils\Config;
use pocketmine\Utils\TextFormat;
use TypeError;

class Slot
{
    private Main $plugin;
    public array $sloted = [];
    public array $effect = [];
    private Config $slotconfig;
    private FloatingTextParticle $ftp;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        try {
            $this->slotconfig = new Config($this->plugin->getDataFolder() . "slotconfig.yml", Config::YAML);
            if ($this->plugin->getServer()->isLevelGenerated($this->slotconfig->get("world", ""))) {
                if (!$this->plugin->getServer()->isLevelLoaded($this->slotconfig->get("world", ""))) {
                    $this->plugin->getServer()->loadLevel($this->slotconfig->get("world", ""));
                }
                $level = $this->plugin->getServer()->getLevelByName($this->slotconfig->get("world", ""));
                $pos = new Vector3($this->slotconfig->get("x", 0), $this->slotconfig->get("y", 0), $this->slotconfig->get("z", 0));
                $this->ftp = new FloatingTextParticle($pos, "取得中...", "§3現在のおうちサーバーカジノ(スロット)の状態");
                $level->addParticle($this->ftp);
            }

            $this->plugin->getScheduler()->scheduleRepeatingTask(new SlotTask([$this, "slotinfo"]), 20);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

    }

    public function Form(Player $player)
    {
        try {
            $form = new SimpleForm(function (Player $player, $data) {
                try {
                    if ($data === null) return true;

                    switch ($data) {
                        case 0:
                            $this->plugin->db->UpdateSlotSettingxyz($player->getLevel()->getName(), (int)$player->x, (int)$player->y, (int)$player->z);
                            if (!isset($this->ftp)) {
                                $pos = new Vector3((int)$player->x, (int)$player->y, (int)$player->z);
                                $this->ftp = new FloatingTextParticle($pos, "取得中...", "§3現在のおうちサーバーカジノ(スロット)の状態");
                                $player->getLevel()->addParticle($this->ftp);
                            }
                            $this->slotinfo();
                            $player->sendMessage("§b[おうちカジノ(スロット)] >> 設定しました");
                            break;
                    }

                    return true;
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-カジノ");
            $form->addButton("スロットの状態表示座標変更");
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function Create(Player $player, Block $block)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) use ($block) {
                try {
                    if ($data === null) return true;
                    else if (!isset($data[0]) or !is_numeric($data[1]) or !is_numeric($data[2])) return true;

                    $pos = new Vector3($block->x, $block->y, $block->z);
                    $sign = $block->getLevel()->getTile($pos);
                    if ($sign instanceof Tile) {
                        $id = $this->plugin->db->SetSlot($data[0], (int)$data[1], (int)$data[2], $data[3], $block);
                        if($data[3] === OutiServerPluginEnum::SLOT_NORMAL) {
                            $sign->setText("§bSLOT: " . $data[0], "§f[§6§k?§r§f]-[§a§k?§r§f]-[§c§k?§r§f]", "§f[§6§k?§r§f]-[§a§k?§r§f]-[§c§k?§r§f]", "§f[§6§k?§r§f]-[§a§k?§r§f]-[§c§k?§r§f]");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "SetSlotMode"], [$player, $id]), 20);
                        }
                        elseif($data[3] === OutiServerPluginEnum::SLOT_VIP) {
                            $sign->setText("§6VIP SLOT: " . $data[0], "§f[§6§k?§r§f]-[§a§k?§r§f]-[§c§k?§r§f]", "§f[§6§k?§r§f]-[§a§k?§r§f]-[§c§k?§r§f]", "§f[§6§k?§r§f]-[§a§k?§r§f]-[§c§k?§r§f]");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "SetSlotMode"], [$player, $id]), 20);
                        }
                        elseif ($data[3] === OutiServerPluginEnum::SLOT_KAIJI) {
                            $sign->setText("§8沼", "§6一回4000円");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new ReturnForm([$this, "SetSlotMode"], [$player, $id]), 20);
                        }
                        $player->sendMessage("Slot: " . $data[0] . "を作成しました");
                    }

                    return true;
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-Casino-スロット");
            $form->addInput("スロット名", "slotname");
            $form->addInput('ベット', 'bet', '1');
            $form->addInput("レート数", "rate", "1");
            $form->addDropdown("モード", ["通常", "VIP"]);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function SetSlotMode(Player $player, int $id)
    {
        try {
            $form = new CustomForm(function (Player $player, $data) use ($id) {
                try {
                    if ($data === null) return true;
                    $this->plugin->db->UpdateSlotMode($id, $data[0]);
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch-Casino-スロット");
            $form->addDropdown("カジノモード", [
                'Mode 0 -> 完全乱数任せ',
                'Mode 1 -> 一定額越えるまでJPが絶対に当たらない',
                'Mode2 -> 絶対にJPが当たらない',
                'Mode3 -> 絶対にゾロ目にならない',
                'Mode4 -> 一定回数回されるまで絶対にJP当たらない',
                'Mode5 -> 確率機',
                'Mode6 -> デバッグモード(基本禁止)',
                'Mode7 -> 停止',
                'Mode8 -> 1/2 でゾロ目',
                'Mode9 -> 確定ゾロ目'
            ]);
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function Start(Player $player, int $id, Tile $tile)
    {
        try {
            $slotdata = $this->plugin->db->GetSlot($id);
            if (!$slotdata) {
                $player->sendMessage("§b[おうちカジノ(スロット)] >> Error " . TextFormat::RED . "スロットデータが見つかりませんでした。");
                return;
            }
            if($slotdata["slottype"] === OutiServerPluginEnum::SLOT_NORMAL and $slotdata["slottype"] === OutiServerPluginEnum::SLOT_VIP) {
                $form = new CustomForm(function (Player $player, $data) use ($slotdata, $tile) {
                    try {
                        $name = $player->getName();
                        if ($data === null) {
                            unset($this->sloted[$name]);
                            return true;
                        } else if (!is_numeric($data[1])) {
                            unset($this->sloted[$name]);
                            return true;
                        }
                        $money = $this->plugin->db->GetMoney($name);
                        if($money["money"] < ($slotdata["bet"] * (int)$data[1])) {
                            unset($this->sloted[$name]);
                            $player->sendMessage("§b[おうちカジノ(スロット)] >> §rお金があと" . (($slotdata["bet"] * (int)$data[1]) - $money["money"]) . "円足りていませんよ？");
                            return true;
                        }
                        $this->plugin->getScheduler()->scheduleDelayedTask(new SlotTask([$this, "slot_1"], [$player, $tile, $slotdata, (int)$data[1]]), 30);
                        $player->sendTitle("§f[§e?§f]-[§e?§f]-[§e?§f]\n§f[§e?§f]-[§e?§f]-[§e?§f]\n§f[§e?§f]-[§e?§f]-[§e?§f]", "§6スロットを開始します");
                    } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                        $this->plugin->errorHandler->onError($e, $player);
                    }

                    return true;
                });

                $form->setTitle("OutiWatch-Casino-スロット");
                $form->addLabel("bet: " . $slotdata["bet"]);
                $form->addSlider("レート数", 1, $slotdata["rate"]);
                $player->sendForm($form);
            }

        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function slot_1(Player $player, Tile $tile, array $slotdata, int $rate)
    {
        try {

            $s1 = array(
                rand(0, 9),
                rand(0, 9),
                rand(0, 9)
            );

            $this->sendslot($player, $tile, $slotdata, $s1);
            $this->oto($player, "pop");
            $this->plugin->getScheduler()->scheduleDelayedTask(new SlotTask([$this, "slot_2"], [$player, $tile, $slotdata, $rate, $s1]), 30);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function slot_2(Player $player, Tile $tile, array $slotdata, int $rate, array $s1)
    {
        try {
            $s2 = array(
                rand(0, 9),
                rand(0, 9),
                rand(0, 9)
            );
            $this->sendslot($player, $tile, $slotdata, $s1, $s2);
            $this->oto($player, "pop");
            $this->plugin->getScheduler()->scheduleDelayedTask(new SlotTask([$this, "slot_3"], [$player, $tile, $slotdata, $rate, $s1, $s2]), 30);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function slot_3(Player $player, Tile $tile, array $slotdata, int $rate, array $s1, array $s2)
    {
        try {
            $s3 = array(
                rand(0, 9),
                rand(0, 9),
                rand(0, 9)
            );
            if (isset($this->effect[$player->getName()])) {
                if ($this->effect[$player->getName()]["type"] === 0) {
                    $this->plugin->getScheduler()->scheduleDelayedTask(new SlotTask([$this, "slot_4"], [$player, $tile, $slotdata, $rate, $s1, $s2, $s3]), 54);
                }
            } else {
                $this->sendslot($player, $tile, $slotdata, $s1, $s2, $s3);
                $this->oto($player, "pop");
                $this->plugin->getScheduler()->scheduleDelayedTask(new SlotTask([$this, "slot_end"], [$player, $slotdata, $rate, $s1, $s2, $s3]), 20);
            }
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    // 演出用？
    public function slot_4(Player $player, Tile $tile, array $slotdata, int $rate, array $s1, array $s2, array $s3)
    {
        try {
            $this->sendslot($player, $tile, $slotdata, $s1, $s2, $s3);
            $this->oto($player, "pop");
            $this->plugin->getScheduler()->scheduleDelayedTask(new SlotTask([$this, "slot_end"], [$player, $slotdata, $rate, $s1, $s2, $s3]), 20);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function slot_end(Player $player, array $slotdata, int $rate, array $s1, array $s2, array $s3)
    {
        try {
            $name = $player->getName();
            unset($this->sloted[$name]);
            unset($this->effect[$name]);

            // 横枠
            if (/*横1*/ ($s1[0] === $s2[0] and $s1[0] === $s3[0]) or /*横2*/ ($s1[1] === $s2[1] and $s1[1] === $s3[1]) or /*横3*/ ($s1[2] === $s2[2] and $s1[2] === $s3[2])) {
                if()
                if ($s1[0] === 7 or $s1[1] === 7 or $s1[2] === 7) {
                    $slotsettings = $this->plugin->db->GetSlotSettings($player->getLevel()->getName());
                    if (!$slotsettings) return;
                    $this->plugin->db->AddMoney($name, $slotsettings["jp"]);
                    $this->plugin->db->ResetSlotSettingJP($player->getLevel()->getName(), $name);
                    $pk = new LevelEventPacket();
                    $pk->evid = LevelEventPacket::EVENT_SOUND_TOTEM;
                    $pk->data = 0;
                    $pk->position = $player->asVector3();
                    $player->dataPacket($pk);
                    $player->sendMessage("§b[おうちカジノ(スロット)] >> §6ジャックポット§bおめでとうございます！");
                    $player->sendMessage("§b[おうちカジノ(スロット)] >> §6{$slotsettings["jp"]}円手に入れた！");
                    $this->plugin->getServer()->broadcastMessage("§b[おうちカジノ(スロット)] >> " . $name . "さんがJPを当て、" . $slotsettings["jp"] . "カジノコイン入手しました！\nおめでとうございます！");
                    $this->plugin->getServer()->broadcastMessage("§b[おうちカジノ(スロット)] >> ジャックポットが" . $this->plugin->config->get('Default_Slot_JP', 10000) . "に戻りました");
                    $this->plugin->client->sendChatMessage("§b[おうちカジノ(スロット)] >> " . $name . "さんがJPを当て、" . $slotsettings["jp"] . "カジノコイン入手しました！\nおめでとうございます！\n");
                    $this->plugin->client->sendChatMessage("§b[おうちカジノ(スロット)] >> ジャックポットが" . $this->plugin->config->get('Default_Slot_JP', 10000) . "に戻りました\n");
                } else {
                    $this->oto($player, "good");
                    $addedmoney = $this->plugin->config->get("Default_Slot_Doublet", 1000) * $rate;
                    $this->plugin->db->AddMoney($name, $addedmoney);
                    $player->sendMessage("§b[おうちカジノ(スロット)] >> §6ゾロ目！");
                    $player->sendMessage("§6{$addedmoney}円§a手に入れた！");
                }
            } // 縦枠
            elseif (/*縦1*/ ($s1[0] === $s1[1] and $s1[0] === $s1[2]) or /*縦2*/ ($s2[0] === $s2[1] and $s2[0] === $s2[2]) or /*縦3*/ ($s3[0] === $s3[1] and $s3[0] === $s3[2])) {
                if ($s1[0] === 7 or $s2[0] === 7 or $s3[0] === 7) {
                    $slotsettings = $this->plugin->db->GetSlotSettings($player->getLevel()->getName());
                    if (!$slotsettings) return;
                    $this->plugin->db->AddMoney($name, $slotsettings["jp"]);
                    $this->plugin->db->ResetSlotSettingJP($player->getLevel()->getName(), $name);
                    $pk = new LevelEventPacket();
                    $pk->evid = LevelEventPacket::EVENT_SOUND_TOTEM;
                    $pk->data = 0;
                    $pk->position = $player->asVector3();
                    $player->dataPacket($pk);
                    $player->sendMessage("§b[おうちカジノ(スロット)] >> §6ジャックポット§bおめでとうございます！");
                    $player->sendMessage("§b[おうちカジノ(スロット)] >> §6{$slotsettings["jp"]}円手に入れた！");
                    $this->plugin->getServer()->broadcastMessage("§b[おうちカジノ(スロット)] >> " . $name . "さんがJPを当て、" . $slotsettings["jp"] . "カジノコイン入手しました！\nおめでとうございます！");
                    $this->plugin->getServer()->broadcastMessage("§b[おうちカジノ(スロット)] >> ジャックポットが" . $this->plugin->config->get('Default_Slot_JP', 10000) . "に戻りました");
                    $this->plugin->client->sendChatMessage("§b[おうちカジノ(スロット)] >> " . $name . "さんがJPを当て、" . $slotsettings["jp"] . "カジノコイン入手しました！\nおめでとうございます！\n");
                    $this->plugin->client->sendChatMessage("§b[おうちカジノ(スロット)] >> ジャックポットが" . $this->plugin->config->get('Default_Slot_JP', 10000) . "に戻りました\n");
                } else {
                    $this->oto($player, "good");
                    $addedmoney = $this->plugin->config->get("Default_Slot_Doublet", 1000) * $rate;
                    $this->plugin->db->AddMoney($name, $addedmoney);
                    $player->sendMessage("§b[おうちカジノ(スロット)] >> §6ゾロ目！");
                    $player->sendMessage("§6{$addedmoney}円§a手に入れた！");
                }
            } // 斜め枠
            elseif (/*斜め右上から右下*/ ($s1[0] === $s2[1] and $s1[0] === $s3[2]) or /*斜め左上から左下*/ ($s1[2] === $s2[1] and $s1[2] === $s3[0])) {
                if ($s1[0] === 7 or $s3[0] === 7) {
                    $slotsettings = $this->plugin->db->GetSlotSettings($player->getLevel()->getName());
                    if (!$slotsettings) return;
                    $this->plugin->db->AddMoney($name, $slotsettings["jp"]);
                    $this->plugin->db->ResetSlotSettingJP($player->getLevel()->getName(), $name);
                    $pk = new LevelEventPacket();
                    $pk->evid = LevelEventPacket::EVENT_SOUND_TOTEM;
                    $pk->data = 0;
                    $pk->position = $player->asVector3();
                    $player->dataPacket($pk);
                    $player->sendMessage("§b[おうちカジノ(スロット)] >> §6ジャックポット§bおめでとうございます！");
                    $player->sendMessage("§b[おうちカジノ(スロット)] >> §6{$slotsettings["jp"]}円手に入れた！");
                    $this->plugin->getServer()->broadcastMessage("§b[おうちカジノ(スロット)] >> " . $name . "さんがJPを当て、" . $slotsettings["jp"] . "カジノコイン入手しました！\nおめでとうございます！");
                    $this->plugin->getServer()->broadcastMessage("§b[おうちカジノ(スロット)] >> ジャックポットが" . $this->plugin->config->get('Default_Slot_JP', 10000) . "に戻りました");
                    $this->plugin->client->sendChatMessage("§b[おうちカジノ(スロット)] >> " . $name . "さんがJPを当て、" . $slotsettings["jp"] . "カジノコイン入手しました！\nおめでとうございます！\n");
                    $this->plugin->client->sendChatMessage("§b[おうちカジノ(スロット)] >> ジャックポットが" . $this->plugin->config->get('Default_Slot_JP', 10000) . "に戻りました\n");
                } else {
                    $this->oto($player, "good");
                    $addedmoney = $this->plugin->config->get("Default_Slot_Doublet", 1000) * $rate;
                    $this->plugin->db->AddMoney($name, $addedmoney);
                    $player->sendMessage("§b[おうちカジノ(スロット)] >> §6ゾロ目！");
                    $player->sendMessage("§6{$addedmoney}円§a手に入れた！");
                }
            } else {
                $this->oto($player, "bad");
                $player->sendMessage("§b[おうちカジノ(スロット)] >> §cハズレ");
                $this->plugin->db->RemoveMoney($name, (int)$slotdata["bet"] * $rate);
                $this->plugin->db->AddSlotJP($slotdata["levelname"], (int)$slotdata["bet"] * $rate);
            }
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function EffectLost()
    {

    }

    public function NormalAtari()
    {

    }

    public function JP(Player $player)
    {
    }

    public function slotinfo()
    {
        try {
            $level = $this->plugin->getServer()->getLevelByName($this->slotconfig->get("world", ""));
            if (!$level) return;
            $this->ftp->setInvisible();
            $level->addParticle($this->ftp);
            $pos = new Vector3($this->slotconfig->get("x", 0), $this->slotconfig->get("y", 0), $this->slotconfig->get("z", 0));
            $slots = $this->plugin->db->GetAllSlot();
            if ($slots) {
                $count = count($slots);
            } else {
                $count = 0;
            }
            $this->ftp = new FloatingTextParticle($pos, "§b現在のジャックポット: §6" . $this->slotconfig->get("jp", $this->plugin->config->get("Default_Slot_JP", 10000)) . "§fカジノコイン\n§b過去最高ジャックポット当選者: §d" . $this->slotconfig->get("highplayer", "NO NAME") . " §f" . $this->slotconfig->get("highjp", 0) . "§aカジノコイン\n" . "§b最後のジャックポット当選者: " . $this->slotconfig->get("lastplayer", "NO NAME") . " §d" . $this->slotconfig->get("lastjp", 0) . "カジノコイン\n§e稼働しているスロット台数: " . $count . "台", "現在のおうちサーバーカジノ(スロット)の状態");
            $level->addParticle($this->ftp);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function sendslot(Player $player, Tile $tile, array $slotdata, array $s1, $s2 = array("§k?§r", "§k?§r", "§k?§r"), $s3 = array("§k?§r", "§k?§r", "§k?§r"))
    {
        try {
            $tile->setText("§bSLOT: " . $slotdata["name"], "§f[§6$s1[0]§f]-[§a$s2[0]§f]-[§c$s3[0]§f]", "§f[§6$s1[1]§f]-[§a$s2[1]§f]-[§c$s3[1]§f]", "§f[§6$s1[2]§f]-[§a$s2[2]§f]-[§c$s3[2]§f]");
            if (is_numeric($s2[0])) {
                if (($s1[0] === $s2[0] or $s1[1] === $s2[1] or $s1[2] === $s2[2] or $s1[0] === $s2[1] or $s3[0] === $s2[1]) and !isset($this->effect[$player->getName()])) {
                    $this->effect[$player->getName()] = array(
                        "type" => 0
                    );

                    $player->sendTitle("§f[§6$s1[0]§f]-[§a$s2[0]§f]-[§c$s3[0]§f]\n§f[§6$s1[1]§f]-[§a$s2[1]§f]-[§c$s3[1]§f]\n§f[§6$s1[2]§f]-[§a$s2[2]§f]-[§c$s3[2]§f]", "ざわ…ざわ…");
                    return;
                }

            }

            $player->sendTitle("§f[§6$s1[0]§f]-[§a$s2[0]§f]-[§c$s3[0]§f]\n§f[§6$s1[1]§f]-[§a$s2[1]§f]-[§c$s3[1]§f]\n§f[§6$s1[2]§f]-[§a$s2[2]§f]-[§c$s3[2]§f]");
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function oto(Player $player, string $id)
    {
        try {
            switch ($id) {
                case "pop": //抽選中
                    $pk = new PlaySoundPacket;
                    $pk->soundName = "random.pop";
                    $pk->x = $player->x;
                    $pk->y = $player->y;
                    $pk->z = $player->z;
                    $pk->volume = 0.5;
                    $pk->pitch = 1;
                    $player->sendDataPacket($pk);
                    break;

                case "bad": //ハズレ
                    $pk = new PlaySoundPacket;
                    $pk->soundName = "random.anvil_land";
                    $pk->x = $player->x;
                    $pk->y = $player->y;
                    $pk->z = $player->z;
                    $pk->volume = 0.5;
                    $pk->pitch = 1;
                    $player->sendDataPacket($pk);
                    break;
                case "good": //あたり...
                    $pk = new PlaySoundPacket;
                    $pk->soundName = "random.levelup";
                    $pk->x = $player->x;
                    $pk->y = $player->y;
                    $pk->z = $player->z;
                    $pk->volume = 0.5;
                    $pk->pitch = 1;
                    $player->sendDataPacket($pk);
                    break;
            }
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    public function Invisibleftp()
    {
        $level = $this->plugin->getServer()->getLevelByName($this->slotconfig->get("world", ""));
        if (!$level) return;
        $this->ftp->setInvisible();
        $level->addParticle($this->ftp);
    }

    public function GetRand(int $type, ?array $s1, ?array $s2): array
    {
        $random = array();
        switch ($type) {
            case 0:
                $random = array(
                    rand(0, 9),
                    rand(0, 9),
                    rand(0, 9)
                );
                break;
            case 1:
                if($this->slotconfig->get('jp', 10000) >= $this->plugin->config->get('NormalSlot_Mode1', 100000)) {
                    $random = array(
                        rand(0, 9),
                        rand(0, 9),
                        rand(0, 9)
                    );
                }
                elseif (isset($s2)) {
                    if($s1[0] === $s2[0] or $s1[1] === $s2[1] or $s1[2] === $s2[2] or $s1[0] === $s2[1])
                    $random = array(
                        rand(0, 9),
                        rand(0, 9),
                        rand(0, 9)
                    );
                }
        }

        return $random;
    }
}