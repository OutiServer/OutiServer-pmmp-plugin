<?php

declare(strict_types=1);

namespace OutiServerPlugin;

use ArgumentCountError;
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerJoinEvent, PlayerInteractEvent, PlayerChatEvent, PlayerKickEvent, PlayerQuitEvent};
use pocketmine\Player;
use pocketmine\event\block\{SignChangeEvent, BlockBreakEvent, BlockBurnEvent};
use pocketmine\item\Item;
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

            // サーバーに参加した時iPhoneを持っていなければ渡す
            $item = Item::get(347);
            if (!$player->getInventory()->contains($item)) {
                $player->getInventory()->addItem($item);
            }

            $this->plugin->client->sendChatMessage("**$name**がサーバーに参加しました\n");
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        try {
            $name = $event->getPlayer()->getName();
            $this->plugin->client->sendChatMessage("**$name**がサーバーから退出しました\n");
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
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

            if ($item->getName() === 'Clock') {
                $this->iPhone($player);
            }

            if ($shopdata) {
                if ($this->plugin->db->isChestShopExits($block, $levelname) and $shopdata["owner"] !== $name and $event->getAction() === 1) {
                    $event->setCancelled();
                    $player->sendMessage("このチェストをオープンできるのはSHOP作成者のみです。");
                } elseif ($shopdata["owner"] === $name and $event->getAction() === 1) {
                    $player->sendMessage("自分のSHOPで購入することはできません");
                } else {
                    $this->plugin->chestshop->BuyChestShop($player, $shopdata);
                }
            }

            if ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                $landid = $this->plugin->db->GetLandId($levelname, $block->x, $block->z);
                if (!$landid) return;
                else if (!$this->plugin->db->CheckLandOwner($landid, $name) and !$this->plugin->db->checkInvite($landid, $name) and $this->plugin->db->CheckLandProtection($landid)) {
                    $event->setCancelled();
                }
            }
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
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
            }
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
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
            if ($shopdata) {
                if ($shopdata["owner"] !== $name) {
                    $player->sendMessage("このShopを閉店させることができるのはSHOP作成者のみです");
                    $event->setCancelled();
                } else {
                    $this->plugin->db->DeleteChestShop($shopdata);
                    $player->sendMessage("このShopを閉店しました");
                }
            }

            $landid = $this->plugin->db->GetLandId($levelname, (int)$block->x, (int)$block->z);
            if ($landid !== false) {
                if (!$this->plugin->db->CheckLandOwner($landid, $player->getName()) and !$this->plugin->db->checkInvite($landid, $player->getName()) and $this->plugin->db->CheckLandProtection($landid)) {
                    $event->setCancelled();
                }
            }
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
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
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function onBlockBurn(BlockBurnEvent $event)
    {
        try {
            $landid = $this->plugin->db->GetLandId($event->getBlock()->getName(), $event->getBlock()->x, $event->getBlock()->z);
            if ($this->plugin->db->CheckLandProtection($landid)) {
                $event->setCancelled();
            }
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function onPlayerKick(PlayerKickEvent $event)
    {
        try {
            $name = $event->getPlayer()->getName();
            $reason = $event->getReason();
            $this->plugin->client->sendChatMessage("**$name**がサーバーから追放されました\nReason: $reason\n");
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    private function iPhone(Player $player)
    {
        try {
            $form = new SimpleForm(function (Player $player, $data) {
                if ($data === null) return true;

                switch ($data) {
                    case 0:
                        $this->plugin->money->Form($player);
                        break;
                    case 1:
                        $this->plugin->adminshop->AdminShop($player);
                        break;
                    case 2:
                        $this->plugin->land->land($player);
                        break;
                    case 3:
                        $this->plugin->teleport->Form($player);
                        break;
                    case 4:
                        $this->plugin->announce->Form($player);
                        break;
                    case 5:
                        $this->plugin->admin->AdminForm($player);
                        break;
                }
                return true;
            });

            $form->setTitle("iPhone");
            $form->addButton("お金関連");
            $form->addButton("AdminShop");
            $form->addButton("土地");
            $form->addButton("テレポート");
            $form->addButton("運営からのお知らせ");
            if ($player->isOp()) {
                $form->addButton("管理系");
            }
            $player->sendForm($form);
        }
        catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
}