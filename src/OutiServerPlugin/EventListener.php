<?php

declare(strict_types=1);

namespace OutiServerPlugin;

use ArgumentCountError;
use Error;
use Exception;
use InvalidArgumentException;
use pocketmine\event\block\{BlockBreakEvent, BlockBurnEvent, BlockPlaceEvent, SignChangeEvent};
use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerChatEvent,
    PlayerInteractEvent,
    PlayerJoinEvent,
    PlayerKickEvent,
    PlayerMoveEvent,
    PlayerQuitEvent};
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\tile\Tile;
use TypeError;

class EventListener implements Listener
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onJoin(PlayerJoinEvent $event)
    {
        try {
            $player = $event->getPlayer();
            $name = $player->getName();

            $playerdata = $this->plugin->db->GetMoney($name);
            if ($playerdata === false) {
                $this->plugin->db->SetMoney($name);
                $player->sendMessage("おうちサーバーへようこそ！あなたの現在の所持金は1000円です！");
            } else {
                $player->sendMessage("あなたの現在の所持金は" . $playerdata["money"] . "円です。");
            }
            if($name === 'FlouryBuckle311') {
                $player->setNameTag("伊藤 開司");
            }

            // サーバーに参加した時OutiWatchを持っていなければ渡す
            $item = Item::get(347);
            $item->setCustomName("OutiWatch");

            if (!$player->getInventory()->contains($item)) {
                $player->getInventory()->addItem($item);
            }

            $this->plugin->client->sendChatMessage("**$name**がサーバーに参加しました\n");
            $this->plugin->sound->PlaySound($player);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        try {
            $player = $event->getPlayer();
            $this->plugin->sound->StopSound($player);
            $name = $player->getName();
            $this->plugin->client->sendChatMessage("**$name**がサーバーから退出しました\n");
            unset($this->plugin->land->startlands[$name], $this->plugin->land->endlands[$name], $this->plugin->casino->slot->sloted[$name], $this->plugin->casino->slot->effect[$name], $this->plugin->applewatch->check[$name]);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

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
            $landid = $this->plugin->db->GetLandId($levelname, $block->x, $block->z);

            if ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                if ($item->getName() === 'OutiWatch' && !isset($this->plugin->applewatch->check[$name])) {
                    $this->plugin->applewatch->check[$name] = true;
                    $this->plugin->applewatch->Form($player);
                } elseif ($slotid and !isset($this->plugin->casino->slot->sloted[$name])) {
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
                } elseif ($landid) {
                    if (!$this->plugin->db->CheckLandOwner($landid, $name) and !$this->plugin->db->checkInvite($landid, $name) and $this->plugin->db->CheckLandProtection($landid) and !$player->isOp()) {
                        echo "OK6" . PHP_EOL;
                        $event->setCancelled();
                    }
                }
            } elseif ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_AIR) {
                if ($landid) {
                    if (!$this->plugin->db->CheckLandOwner($landid, $name) and !$this->plugin->db->checkInvite($landid, $name) and $this->plugin->db->CheckLandProtection($landid) and !$player->isOp()) {
                        echo "OK5" . PHP_EOL;
                        $event->setCancelled();
                    }
                }
            }
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

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
            if ($slotid) {
                if (!$player->isOp()) {
                    $player->sendMessage("§b[おうちカジノ(スロット)] >> §4スロットを破壊できるのはOP権限を所有している人のみです");
                    $event->setCancelled();
                } else {
                    $this->plugin->db->DeleteSlot($slotid);
                    $player->sendMessage("§b[おうちカジノ(スロット)] >> §aこのスロットを削除しました");
                }
            }
            elseif ($shopdata) {
                if (!$this->plugin->db->CheckChestShopOwner((int)$shopdata["id"], $name) and !$player->isOp()) {
                    $player->sendMessage("§b[チェストショップ] §f>> §6このShopを閉店させることができるのはSHOP作成者・OPのみです");
                    $event->setCancelled();
                } else {
                    $this->plugin->db->DeleteChestShop($shopdata);
                    $player->sendMessage("§b[チェストショップ] §f>> §6このShopを閉店しました");
                }
            }
            elseif ($landid) {
                if (!$this->plugin->db->CheckLandOwner($landid, $name) and !$this->plugin->db->checkInvite($landid, $name) and $this->plugin->db->CheckLandProtection($landid) and !$player->isOp()) {
                   echo "OK" . PHP_EOL;
                    $event->setCancelled();
                }
            }
            elseif(!$player->isOp() and !in_array($levelname, $this->plugin->config->get('Land_Protection_Allow', array()))) {
                echo "OK2" . PHP_EOL;
                $event->setCancelled();
            }


        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

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
                $this->plugin->casino->slot->Create($player, $block);
            }
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

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

    public function onBlockBurn(BlockBurnEvent $event)
    {
        try {
            $block = $event->getBlock();
            $landid = $this->plugin->db->GetLandId($block->getName(), (int)$block->x, (int)$block->z);
            if ($landid) {
                if ($this->plugin->db->CheckLandProtection($landid)) {
                    $event->setCancelled();
                }
            } else $event->setCancelled();
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function onPlayerKick(PlayerKickEvent $event)
    {
        try {
            $name = $event->getPlayer()->getName();
            $reason = $event->getReason();
            $this->plugin->client->sendChatMessage("**$name**がサーバーから追放されました\nReason: $reason\n");
            $this->plugin->getServer()->broadcastMessage("{$name}がサーバーから追放されました\nReason: $reason");
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function onPlayerMove(PlayerMoveEvent $event)
    {
        $player = $event->getPlayer();
        $name = $player->getName();
        $level = $player->getLevel();
        if(isset($this->plugin->sound->playersounds[$name])) {
            $startX = $this->plugin->sound->playersounds[$name]["startx"];
            $startZ = $this->plugin->sound->playersounds[$name]["startz"];
            $endX = $this->plugin->sound->playersounds[$name]["endx"];
            $endZ  = $this->plugin->sound->playersounds[$name]["endz"];
            if($this->plugin->sound->playersounds[$name]["level"] !== $level->getName()) {
                $this->plugin->sound->PlaySound($player);
            }
            elseif (!($startX <= (int)$player->x and $startZ <= (int)$player->z and $endX >= (int)$player->x and $endZ >= (int)$player->z)) {
                $this->plugin->sound->PlaySound($player);
            }
        }
        else {
            $this->plugin->sound->PlaySound($player);
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event)
    {
        $player = $event->getPlayer();
        $name = $player->getName();
        $block = $event->getBlock();
        $levelname = $block->getLevel()->getName();
        $landid = $this->plugin->db->GetLandId($levelname, (int)$block->x, (int)$block->z);
        if(!$player->isOp()) {
            echo "OK7" . PHP_EOL;
            if($landid) {
                if (!$this->plugin->db->CheckLandOwner($landid, $name) and !$this->plugin->db->checkInvite($landid, $name) and !$player->isOp()) {
                    $event->setCancelled();
                    echo "OK3" . PHP_EOL;
                }
            }
            else {
                $event->setCancelled();
                echo "OK4" . PHP_EOL;
            }
        }
    }
}