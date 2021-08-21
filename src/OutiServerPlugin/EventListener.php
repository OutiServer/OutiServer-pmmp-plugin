<?php

declare(strict_types=1);

namespace OutiServerPlugin;

use ArgumentCountError;
use Error;
use Exception;
use InvalidArgumentException;
use OutiServerPlugin\Tasks\SendLog;
use pocketmine\event\block\{BlockBreakEvent, BlockBurnEvent, BlockPlaceEvent, SignChangeEvent};
use OutiServerPlugin\Utils\Enum;
use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerChatEvent,
    PlayerInteractEvent,
    PlayerJoinEvent,
    PlayerKickEvent,
    PlayerLoginEvent,
    PlayerMoveEvent,
    PlayerQuitEvent,
    PlayerRespawnEvent};
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\tile\Tile;
use TypeError;

class EventListener implements Listener
{
    private Main $plugin;
    private array $playerlevel = [];
    private array $landalarm = [];

    // <editor-fold desc="コンストラクタ">
    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }
    // </editor-fold>

    // <editor-fold desc="プレイヤーログインイベント">
    public function onPlayerLogin(PlayerLoginEvent $event)
    {
        try {
            $player = $event->getPlayer();
            $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPlayerLog_Webhook', ''), "{$player->getName()}が ワールド {$player->getLevel()->getName()} X座標 {$player->getX()} Y座標 {$player->getY()} Z座標 {$player->getZ()} にログインしました"));
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
    // </editor-fold>

    // <editor-fold desc="プレイヤー参加イベント">
    public function onJoin(PlayerJoinEvent $event)
    {
        try {
            $player = $event->getPlayer();
            $name = $player->getName();
            $this->playerlevel[$name] = $player->getLevel()->getName();

            $playerdata = $this->plugin->db->GetMoney($name);
            if ($playerdata === false) {
                $playerdata = array(
                    "money" => 1000
                );
                $this->plugin->db->SetMoney($name);
                $player->sendMessage("おうちサーバーへようこそ！\n初参加の場合は、チュートリアルワールドをプレイすることをお勧めします！\n分からないことがあれば気軽に質問してください！");
                $this->plugin->getServer()->broadcastMessage("{$name}さんがサーバーに初参加です！\nおうちサーバーへようこそ！");
                $this->plugin->client->sendChatMessage("**$name**がサーバーに初参加しました！\n");
            } else {
                $player->sendMessage("あなたの現在の所持金は" . $playerdata["money"] . "円です。");
                $this->plugin->client->sendChatMessage("**$name**がサーバーに参加しました\n");
            }

            $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPlayerLog_Webhook', ''), "{$name}がゲームに参加しました。 所持金: {$playerdata["money"]}円"));

            // サーバーに参加した時OutiWatchを持っていなければ渡す
            $item = Item::get(347);
            $item->setCustomName("OutiWatch");

            if (!$player->getInventory()->contains($item)) {
                $player->getInventory()->addItem($item);
            }

            $this->plugin->sound->PlaySound($player);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
    // </editor-fold>イベント

    // <editor-fold desc="プレイヤー退出イベント">
    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        try {
            $player = $event->getPlayer();
            $this->plugin->sound->StopSound($player);
            $name = $player->getName();
            unset($this->plugin->applewatch->check[$name], $this->plugin->land->startlands[$name], $this->plugin->land->endlands[$name], $this->playerlevel[$name], $this->landalarm[$name]);
            $this->plugin->client->sendChatMessage("**$name**がサーバーから退出しました\n");
            $playerdata = $this->plugin->db->GetMoney($name);
            $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPlayerLog_Webhook', ''), "{$name}がゲームにから退出しました。 ワールド {$player->getLevel()->getName()} X座標 {$player->getX()} Y座標 {$player->getY()} Z座標 {$player->getZ()} 所持金 {$playerdata["money"]}円"));
            unset($this->plugin->land->startlands[$name], $this->plugin->land->endlands[$name], $this->plugin->casino->slot->sloted[$name], $this->plugin->casino->slot->effect[$name], $this->plugin->applewatch->check[$name]);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
    // </editor-fold>

    // <editor-fold desc="ブロックタップイベント">
    public function onInteract(PlayerInteractEvent $event)
    {
        try {
            $player = $event->getPlayer();
            $name = $player->getName();
            $item = $event->getItem();
            $block = $event->getBlock();
            $levelname = $block->level->getName();
            $shopdata = $this->plugin->db->GetChestShop($block, $levelname);
            $slotid = $this->plugin->db->GetSlotId($block);
            $landid = $this->plugin->db->GetLandId($levelname, (int)$block->x, (int)$block->z);
            $blockitem = Item::get($block->getId(), $block->getDamage());
            $itemname = $this->plugin->db->GetItemDataItem($blockitem);
            if (!$itemname) {
                $itemname = array(
                    "janame" => $item->getName()
                );
            }

            if ($event->getAction() === 1) {
                if ($item->getName() === 'OutiWatch' && !isset($this->plugin->applewatch->check[$name])) {
                    $this->plugin->applewatch->check[$name] = true;
                    $this->plugin->applewatch->Form($player);
                }
                if ($slotid and !isset($this->plugin->casino->slot->sloted[$name])) {
                    $pos = new Vector3($block->x, $block->y, $block->z);
                    $sign = $block->getLevel()->getTile($pos);
                    if ($sign instanceof Tile) {
                        $this->plugin->casino->slot->Start($player, $slotid, $sign);
                        $this->plugin->casino->slot->sloted[$name] = true;
                    }
                } elseif ($shopdata) {
                    if ($this->plugin->db->isChestShopChestExits($block, $levelname) and !$this->plugin->db->CheckChestShopOwner((int)$shopdata["id"], $name) and !$player->isOp()) {
                        $event->setCancelled();
                        $player->sendMessage("§b[チェストショップ] §f>> §6このチェストをオープンできるのはSHOP作成者・OPのみです");
                    } elseif ($this->plugin->db->CheckChestShopOwner((int)$shopdata["id"], $name)) {
                        $player->sendMessage("§b[チェストショップ] §f>> §6自分のSHOPで購入することはできません");
                    } else {
                        $this->plugin->chestshop->BuyChestShop($player, $shopdata);
                    }
                }
                elseif ($landid) {
                    if (!$this->plugin->db->CheckLandOwner($landid, $name) and $this->plugin->db->CheckLandProtection($landid)) {
                        if(!$this->plugin->db->checkInvite($landid, $name) and !$this->plugin->db->CheckLandPerms($landid, Enum::LAND_PERMS_TAP_INSTALLATION)) {
                            $event->setCancelled();
                        }
                        elseif ($this->plugin->db->checkInvite($landid, $name) and !$this->plugin->db->CheckLandPerms($landid, Enum::LAND_PERMS_TAP_INSTALLATION, $name)) {
                            $event->setCancelled();
                        }
                    }
                }
                elseif (!$player->isOp() and !in_array($levelname, $this->plugin->config->get('Land_Protection_Allow', array()))) {
                    $event->setCancelled();
                }

                if ($event->isCancelled()) {
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordBlockLog_Webhook', ''), "{$name}がX座標 $block->x Y座標 $block->y Z座標 $block->z ワールド $levelname\nのブロック {$itemname["janame"]} をタップしようとしました"));
                }
                else {
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordBlockLog_Webhook', ''), "{$name}がX座標 $block->x Y座標 $block->y Z座標 $block->z ワールド $levelname\nのブロック {$itemname["janame"]} をタップしました"));
                }
            }
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
    // </editor-fold>

    // <editor-fold desc="ブロック破壊イベント">
    public function onBreak(BlockBreakEvent $event)
    {
        try {
            $block = $event->getBlock();
            $levelname = $block->level->getName();
            $player = $event->getPlayer();
            $name = $player->getName();
            $shopdata = $this->plugin->db->GetChestShop($block, $levelname);
            $slotid = $this->plugin->db->GetSlotId($block);
            $landid = $this->plugin->db->GetLandId($levelname, (int)$block->x, (int)$block->z);
            $item = Item::get($block->getId(), $block->getDamage());
            $itemname = $this->plugin->db->GetItemDataItem($item);
            if (!$itemname) {
                $itemname = array(
                    "janame" => $item->getName()
                );
            }

            if(!in_array(strtolower($player->getName()), $this->plugin->config->get('OPList', array())) and $item->getId() === 7) {
                $player->kick("§cプラグインにより不正検知されました\n岩盤破壊をしているがOPホワイトリストに存在しない\nこれが誤検知である場合は、運営に解除申請をしてください");
                $player->setBanned(true);
                $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPunishmentLog_Webhook', ''), "{$player->getName()} がプラグインにより不正検知されました\n不正検知詳細: 岩盤破壊をしているがOPホワイトリストに存在しない"));
                return;
            }
            if ($slotid) {
                if (!$player->isOp()) {
                    $player->sendMessage("§b[おうちカジノ(スロット)] >> §4スロットを破壊できるのはOP権限を所有している人のみです");
                    $event->setCancelled();
                } else {
                    $this->plugin->db->DeleteSlot($slotid);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()}が ワールド {$block->getLevel()->getName()} X座標 {$block->getX()} Y座標 {$block->getY()} Z座標 {$block->getZ()} のスロットID $slotid を削除(破壊)しました"));
                    $player->sendMessage("§b[おうちカジノ(スロット)] >> §aこのスロットを削除しました");
                }
            } elseif ($shopdata) {
                if (!$this->plugin->db->CheckChestShopOwner((int)$shopdata["id"], $name) and !$player->isOp()) {
                    $player->sendMessage("§b[チェストショップ] §f>> §6このShopを閉店させることができるのはSHOP作成者・OPのみです");
                    $event->setCancelled();
                } else {
                    $this->plugin->db->DeleteChestShop($shopdata);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "{$player->getName()}が ワールド {$block->getLevel()->getName()} X座標 {$block->getX()} Y座標 {$block->getY()} Z座標 {$block->getZ()} Owner {$shopdata["owner"]} のチェストSHOP を閉店(破壊)しました"));
                    $player->sendMessage("§b[チェストショップ] §f>> §6このShopを閉店しました");
                }
            }
            if ($landid) {
                if (!$this->plugin->db->CheckLandOwner($landid, $name) and $this->plugin->db->CheckLandProtection($landid) and !$player->isOp()) {
                    if(!$this->plugin->db->checkInvite($landid, $name) and !$this->plugin->db->CheckLandPerms($landid, Enum::LAND_PERMS_DESTRUCTION)) {
                        $event->setCancelled();
                    }
                    elseif (!$this->plugin->db->CheckLandPerms($landid, Enum::LAND_PERMS_DESTRUCTION, $name)) {
                        $event->setCancelled();
                    }
                }
            } elseif (!$player->isOp() and !in_array($levelname, $this->plugin->config->get('Land_Protection_Allow', array()))) {
                $event->setCancelled();
            }

            if($event->isCancelled()) {
                $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordBlockLog_Webhook', ''), "{$name}がX座標 $block->x Y座標 $block->y Z座標 $block->z ワールド $levelname\nのブロック {$itemname["janame"]} を破壊しようとしました"));
            }
            else {
                $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordBlockLog_Webhook', ''), "{$name}がX座標 $block->x Y座標 $block->y Z座標 $block->z ワールド $levelname\nのブロック {$itemname["janame"]} を破壊しました"));
            }

        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
    // </editor-fold>

    // <editor-fold desc="看板の文字列変更イベント？">
    public function SignChange(SignChangeEvent $event)
    {
        try {
            $lines = $event->getLines();
            $player = $event->getPlayer();
            $block = $event->getBlock();
            $level = $block->level;
            $chestdata = false;
            if ($lines[0] === "shop") {
                $chest = [$block->add(1), $block->add(-1), $block->add(0, 0, 1), $block->add(0, 0, -1)];
                foreach ($chest as $vector) {
                    if ($level->getBlock($vector)->getID() === 54) {
                        $chestdata = $vector;
                    }
                }

                if (!$chestdata) {
                    $player->sendMessage('横にチェストが見つかりません！');
                    return;
                }
                $this->plugin->chestshop->CreateChestShop($player, $chestdata, $block);
            } else if ($lines[0] === "slot") {
                if($player->isOp()) {
                    $this->plugin->casino->slot->Create($player, $block);
                }
            }
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
    // </editor-fold>

    // <editor-fold desc="プレイヤーチャットイベント">
    public function onPlayerChat(PlayerChatEvent $event)
    {
        try {
            $player = $event->getPlayer();
            $name = $player->getName();
            $message = $event->getMessage();

            $this->plugin->client->sendChatMessage("**$name** >> $message \n");
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
    // </editor-fold>

    // <editor-fold desc="ブロック焼失イベント">
    public function onBlockBurn(BlockBurnEvent $event)
    {
        try {
            $block = $event->getBlock();
            $item = Item::get($block->getId(), $block->getDamage());
            $itemname = $this->plugin->db->GetItemDataItem($item);
            if (!$itemname) {
                $itemname = array(
                    "janame" => $item->getName()
                );
            }
            $landid = $this->plugin->db->GetLandId($block->getName(), (int)$block->x, (int)$block->z);
            if ($landid) {
                if ($this->plugin->db->CheckLandProtection($landid)) {
                    $event->setCancelled();
                }
            } else $event->setCancelled();

            if($event->isCancelled()) {
                $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordBlockLog_Webhook', ''), "X座標 $block->x Y座標 $block->y Z座標 $block->z ワールド {$block->getLevel()->getName()}\nのブロック {$itemname["janame"]} が焼失しようとしました"));
            }
            else {
                $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordBlockLog_Webhook', ''), "X座標 $block->x Y座標 $block->y Z座標 $block->z ワールド {$block->getLevel()->getName()}\nのブロック {$itemname["janame"]} が焼失しました"));
            }
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
    // </editor-fold>

    // <editor-fold desc="プレイヤー追放イベント">
    public function onPlayerKick(PlayerKickEvent $event)
    {
        try {
            $name = $event->getPlayer()->getName();
            $reason = $event->getReason();
            $this->plugin->client->sendChatMessage("**$name**がサーバーから追放されました\nReason: $reason\n");
            $this->plugin->getServer()->broadcastMessage("{$name}がサーバーから追放されました\nReason: $reason");
            $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPunishmentLog_Webhook', ''), "$name がサーバーから追放されました\n追放理由: $reason"));
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
    // </editor-fold>

    // <editor-fold desc="プレイヤー移動イベント">
    public function onPlayerMove(PlayerMoveEvent $event)
    {
        try {
            $player = $event->getPlayer();
            $name = $player->getName();
            $level = $player->getLevel();
            $landid = $this->plugin->db->GetLandId($level->getName(), (int)$player->x, (int)$player->z);
            if(isset($this->playerlevel[$name])) {
                if($this->playerlevel[$name] !== $level->getName()) {
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPlayerLog_Webhook', ''), "{$name}が ワールド: {$this->playerlevel[$name]} から {$player->getLevel()->getName()} に移動しました"));
                    $this->playerlevel[$name] = $level->getName();
                }
            }

            if (isset($this->plugin->sound->playersounds[$name])) {
                $startX = $this->plugin->sound->playersounds[$name]["startx"];
                $startZ = $this->plugin->sound->playersounds[$name]["startz"];
                $endX = $this->plugin->sound->playersounds[$name]["endx"];
                $endZ = $this->plugin->sound->playersounds[$name]["endz"];
                if ($this->plugin->sound->playersounds[$name]["level"] !== $level->getName()) {
                    $this->plugin->sound->PlaySound($player);
                } elseif (!($startX <= (int)$player->x and $startZ <= (int)$player->z and $endX >= (int)$player->x and $endZ >= (int)$player->z)) {
                    $this->plugin->sound->PlaySound($player);
                }
            } else {
                $this->plugin->sound->PlaySound($player);
            }

            if($landid) {
                if(!$this->plugin->db->checkInvite($landid, $name) and !$this->plugin->db->CheckLandOwner($landid, $name) and $this->plugin->db->CheckLandPerms($landid, Enum::LAND_PERMS_ALARM) and !isset($this->landalarm[$name])) {
                    $this->landalarm[$name] = true;
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPunishmentLog_Webhook', ''), "⚠️警告⚠️ $name が 土地ID $landid に侵入しました"));
                    $player->sendMessage("§e⚠警告⚠ 土地ID $landid に不法侵入しています");

                }
            }
            else unset($this->landalarm[$name]);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
    // </editor-fold>

    // <editor-fold desc="プレイヤーリスポーンイベント">
    public function onPlayerRespawn(PlayerRespawnEvent $event)
    {
        try {
            $player = $event->getPlayer();
            $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPlayerLog_Webhook', ''), "{$player->getName()}がワールド: {$player->getLevel()->getName()} X座標{$player->getX()} Y座標{$player->getY()} Z座標{$player->getZ()} にリスポーンしました"));
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
    // </editor-fold>

    public function onBlockPlace(BlockPlaceEvent $event)
    {
        try {
            $player = $event->getPlayer();
            $name = $player->getName();
            $block = $event->getBlock();
            $levelname = $block->getLevel()->getName();
            $landid = $this->plugin->db->GetLandId($levelname, (int)$block->x, (int)$block->z);
            $item = Item::get($block->getId(), $block->getDamage());
            $itemname = $this->plugin->db->GetItemDataItem($item);
            if (!$itemname) {
                $itemname = array(
                    "janame" => $item->getName()
                );
            }

            if (!$player->isOp()) {
                if ($landid) {
                    if(!$this->plugin->db->checkInvite($landid, $name) and !$this->plugin->db->CheckLandPerms($landid, Enum::LAND_PERMS_TAP_INSTALLATION)) {
                        $event->setCancelled();
                    }
                    elseif ($this->plugin->db->checkInvite($landid, $name) and !$this->plugin->db->CheckLandPerms($landid, Enum::LAND_PERMS_TAP_INSTALLATION, $name)) {
                        $event->setCancelled();
                    }
                } elseif (!in_array($levelname, $this->plugin->config->get('Land_Protection_Allow', array()))) {
                    $event->setCancelled();
                }
            }

            if($event->isCancelled()) {
                $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordBlockLog_Webhook', ''), "{$name}がX座標 $block->x Y座標 $block->y Z座標 $block->z ワールド $levelname\nにブロック {$itemname["janame"]} を設置しようとしました"));
            }
            else {
                $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordBlockLog_Webhook', ''), "{$name}がX座標 $block->x Y座標 $block->y Z座標 $block->z ワールド $levelname\nにブロック {$itemname["janame"]} を設置しました"));
            }
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
}