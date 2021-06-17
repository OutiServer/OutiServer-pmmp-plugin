<?php

declare(strict_types=1);

namespace Ken_Cir0909\OutiServerPlugin;

// Task
use Ken_Cir0909\OutiServerPlugin\Tasks\discord;

// Util
use Ken_Cir0909\OutiServerPlugin\Utils\Database;

// pmmp
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\item\Item;
use pocketmine\tile\Tile;
use pocketmine\math\Vector3;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\level\Position;
use pocketmine\scheduler\ClosureTask;

//Form
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\ModalForm;
use jojoe77777\FormAPI\CustomForm;

class Main extends PluginBase implements Listener
{
    public $client;
    public $started = false;
    private $db;
    private $startlands = [];
    private $endlands = [];
    private $levelnamelands = [];
    private $config;

    public function onEnable()
    {
        $this->db = new Database($this->getDataFolder() . 'outiserver.db');
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->client = new discord($this->getFile(), 'Nzc3MTk4MjM2NDcyODM2MTQ3.X6_8Qw.OF6nEfLocrA9obNtrG65yPqfCA4');

        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function (int $currentTick): void {
                $this->started = true;
                $this->getLogger()->info("ログ出力を開始します");
                ob_start();
            }
        ), 10);

        $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(
            function (int $currentTick): void {
                if (!$this->started) {
                    return;
                }
                $string = ob_get_contents();
                if ($string === "") {
                    return;
                }
                $this->client->sendLogMessage($string);
                ob_flush();
            }
        ), 10, 1);

        $this->client->sendChatMessage('サーバーが起動しました！');
    }

    public function onDisable()
    {
        $this->client->sendChatMessage('サーバーが停止しました');

        $this->db->close();
        if (!$this->started) {
            return;
        }
        $this->getLogger()->info("出力バッファリングを終了しています...");
        $this->client->shutdown();
        ob_flush();
        ob_end_clean();
        $this->getLogger()->info("discordBotの終了を待機しております...");
        $this->client->join();
    }

    // サーバー参加関数
    public function onJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        $xuid = $player->getXuid();
        $name = $player->getName();

        // サーバーに参加した時playerデータがなければ作成する
        $playerdata = $this->db->GetMoney($xuid);
        if ($playerdata === false) {
            $this->db->SetMoney($xuid);
            $player->sendMessage("おうちサーバーへようこそ！あなたの現在の所持金は1000円です！");
        } else {
            $player->sendMessage("あなたの現在の所持金は" . $playerdata["money"] . "円です。");
        }

        // サーバーに参加した時iPhoneを持っていなければ渡す
        $item = Item::get(347, 0, 1);
        if (!$player->getInventory()->contains($item)) {
            $player->getInventory()->addItem($item);
        }

        $this->client->sendChatMessage("$name がサーバーに参加しました");
    }
 
    public function onInteract(PlayerInteractEvent $event)
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $block = $event->getBlock();
        $xuid = $player->getXuid();
        $levelname = $block->level->getName();
        $shopdata = $this->db->GetChestShop($block, $levelname);

        if ($item->getName() === 'Clock') {
            $player = $event->getPlayer();
            $this->iPhone($player);
        }
       
        if ($shopdata) {
            if ($this->db->isChestShopExits($block, $levelname) and $shopdata["ownerxuid"] !== $xuid and $event->getAction() === 1 and !$player->isOp()) {
                $event->setCancelled();
                $player->sendMessage("このチェストをオープンできるのはSHOP作成者か、オペレーター権限を所持している人のみです");
            } elseif ($shopdata["ownerxuid"] === $xuid and $event->getAction() === 1) {
                $player->sendMessage("自分のSHOPで購入することはできません");
            } else {
                $this->BuyChestShop($player, $shopdata);
            }
        }
    
        if (isset($this->startlands[$player->getXuid()])) {
            if ($this->startlands[$player->getXuid()] === true) {
                $this->startlands[$player->getXuid()] = $block;
                $player->sendMessage("土地保護の終了地点をタップしてください");
            } else {
                $this->endlands[$player->getXuid()] = $block;
                $this->buyland($player);
            }
        }
    }

    public function SignChange(SignChangeEvent $event)
    {
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
            $this->CreateChestShop($player, $chestdata, $block);
        }
    }

    public function onBreak(BlockBreakEvent $event)
    {
        $block = $event->getBlock();
        $levelname = $block->level->getName();
        $player = $event->getPlayer();
        $xuid = $player->getXuid();
        $shopdata = $this->db->GetChestShop($block, $levelname);
        if ($shopdata) {
            if ($shopdata["ownerxuid"] !== $xuid and !$player->isOp()) {
                $player->sendMessage("このShopを閉店させることができるのはSHOP作成者か、オペレーター権限を所持している人のみです");
                $event->setCancelled();
            } else {
                $this->db->DeleteChestShop($shopdata);
                $player->sendMessage("このShopを閉店しました");
            }
        }

        if ($this->db->isLandExits($levelname, $block)) {
            $player->sendMessage("他人の所有している土地を破壊することはできません");
            $event->setCancelled();
        }
    }

    private function BuyChestShop($player, $shopdata)
    {
        $form = new CustomForm(function (Player $player, $data) use ($shopdata) {
            if ($data === null) {
                return true;
            }

            $inventory = $player->getInventory();
            $pos = new Position($shopdata["chestx"], $shopdata["chesty"], $shopdata["chestz"], $this->getServer()->getLevelByName($shopdata["levelname"]));
            $item = Item::get($shopdata["itemid"], $shopdata["itemmeta"], (int)$data[1]);

            if (!$inventory->canAddItem($item)) {
                $player->sendMessage('インベントリの空きが足りません');
                return true;
            }

            $chest = $pos->level->getTile($pos)->getInventory()->contains($item);

            if ($chest) {
                $this->BuyChestShopCheck($player, $item, $shopdata, $playerinventory);
            } else {
                $player->sendMessage('申し訳ありませんが、在庫が足りていないようです。');
            }
        });
        
        $item = Item::get($shopdata["itemid"], $shopdata["itemmeta"], 0);
        $form->setTitle("Shop");
        $form->addLabel("販売物: " . $item->getName());
        $form->addSlider("\n買う個数", 1, $shopdata["maxcount"]);
        $player->sendForm($form);
    }

    private function BuyChestShopCheck($player, $item, $shopdata, $playerinventory)
    {
        $form = new SimpleForm(function (Player $player, $data) use ($item, $shopdata, $playerinventory) {
            if ($data === null) {
                return true;
            }
            switch ($data) {
            case 0:
                $playerxuid = $player->getXuid();
                $playermoney = $this->db->GetMoney($playerxuid);
                $price = $item->getCount() * $shopdata["price"];
                if ($price > $playermoney["money"]) {
                    $player->sendMessage("お金が" . ($playermoney["money"] - $price) * -1 . "円足りていませんよ？");
                    return true;
                }
                $pos = new Position($shopdata["chestx"], $shopdata["chesty"], $shopdata["chestz"], $this->getServer()->getLevelByName($shopdata["levelname"]));
                $pos->level->getTile($pos)->getInventory()->removeItem($item);
                $playerinventory->addItem($item);
                $this->db->UpdateMoney($playerxuid, $playermoney["money"] - $price);
                $shopownermoney = $this->db->GetMoney($shopdata["ownerxuid"]);
                $this->db->UpdateMoney($shopdata["ownerxuid"], $shopownermoney["money"] += $price);
                $player->sendMessage("購入しました");
            break;
            case 1:
                $player->sendMessage("購入しませんでした");
            break;
        }
        });
        
        $form->setTitle("購入確認");
        $form->setContent($item->getName() . "を" . $item->getCount() . "個購入しますか？\n" . $item->getCount() * $shopdata["price"] . "円です");
        $form->addButton("購入する");
        $form->addButton("キャンセル");
        $player->sendForm($form);
    }

    private function CreateChestShop($player, $chest, $signboard)
    {
        $form = new CustomForm(function (Player $player, $data) use ($chest, $signboard) {
            if ($data === null) {
                return true;
            }

            if (!is_numeric($data[0]) or !is_numeric($data[1]) or !is_numeric($data[2]) or !is_numeric($data[3])) {
                return;
            }

            $xuid = $player->getXuid();
            $pos = new Vector3($signboard->x, $signboard->y, $signboard->z);
            $sign = $signboard->getLevel()->getTile($pos);

            if ($sign instanceof Tile) {
                $item = Item::get((int)$data[1], (int)$data[2], 0);
                $this->db->SetChestShop($xuid, $chest, $signboard, $item, $data[3]);
                $sign->setText("shop", "shop主: " . $player->getName(), "販売しているItem: " . $item->getName(), "お値段: " . $data[3] . "円");
                $player->sendMessage("shopを作成しました！");
            }
        });
        
        $form->setTitle("shop作成");
        $form->addSlider("販売するItemの最大購入数", 1, 64);
        $form->addInput("販売するItemのID", "itemid", "");
        $form->addInput("販売するItemのMETA", "itemmeta", "0");
        $form->addInput("販売するItemの値段", "price", "1");
        $player->sendForm($form);
    }

    private function iPhone(Player $player)
    {
        $form = new SimpleForm(function (Player $player, $data) {
            if ($data === null) {
                return true;
            }
            switch ($data) {
            case 0:
                $xuid = $player->getXuid();
                $playerdata = $this->db->GetMoney($xuid);
                if ($playerdata === false) {
                    break;
                }
                $player->sendMessage("あなたの現在の所持金: " . $playerdata["money"] . "円");
                break;
            break;
            case 1:
                $this->AdminShop($player);
                break;
            case 2:
                $this->land($player);
                break;
        }
        });
        
        $form->setTitle("iPhone");
        $form->addButton("所持金の確認");
        $form->addButton("AdminShop");
        $form->addButton("土地");
        $player->sendForm($form);
    }

    private function land(Player $player)
    {
        $form = new SimpleForm(function (Player $player, $data) {
            if ($data === null) {
                return true;
            }

            switch ($data) {
                case 0:
                    $this->startlands[$player->getXuid()] = true;
                    $player->sendMessage("土地保護の開始地点をタップしてください");
                    break;
                case 1:
                break;
            }
        });

        $form->setTitle("iPhone-土地");
        $form->addButton("土地購入の開始");
        $player->sendForm($form);
    }

    private function AdminShop(Player $player)
    {
        $form = new SimpleForm(function (Player $player, $data) {
            if ($data === null) {
                return true;
            }
            switch ($data) {
            case 0:
                $this->AdminShopMenu($player);
                break;
            case 1:
                $this->AdminShopSetprice($player);
                break;
        }
        });
        
        $form->setTitle("iPhone-AdminShop");
        $form->addButton("メニュー");
        if ($player->isOp()) {
            $form->addButton("値段の設定");
        }
        $player->sendForm($form);
    }

    // AdminShop値段設定機構

    private function AdminShopSetprice(Player $player)
    {
        $form = new CustomForm(function (Player $player, $data) {
            if ($data === null) {
                return true;
            }

            if (!is_numeric($data[0]) or !is_numeric($data[1])) {
                return true;
            }
            $item = Item::get((int)$data[0], (int)$data[1], 1);
            if (!$item) {
                return true;
            }

            $itemdata = $this->db->GetAdminShop($item);
            if ($itemdata === false) {
                $this->db->SetAdminShop($item, $data[2], $data[3]);
            } else {
                $this->db->UpdateAdminShop($item, $data[2], $data[3]);
            }

            $player->sendMessage("設定しました");
        });

        $form->setTitle("iPhone-AdminShop-値段設定");
        $form->addInput("値段設定するItemID", "itemid", "");
        $form->addInput("値段設定するItemのMETA", "itemmeta", "0");
        $form->addInput("値段", "buyprice", "1");
        $form->addInput("売却値段", "sellprice", "1");
        $player->sendForm($form);
    }

    // AdminShop処理機構
    private function AdminShopMenu(Player $player)
    {
        $alldata = $this->db->AllAdminShop();
        if ($alldata === false) {
            return $player->sendMessage("現在AdminShopでは何も売られていないようです");
        }

        $form = new SimpleForm(function (Player $player, $data) use ($alldata) {
            if ($data === null) {
                return;
            }

            $itemdata = $alldata[$data];

            $this->SelectAdminShop($player, $itemdata);
        });

        $form->setTitle("iPhone");
        $form->setContent("メニュー");
        
        for ($i = 0; $i < count($alldata); $i++) {
            $item = Item::get($alldata[$i]["itemid"], $alldata[$i]["itemmeta"], 1);
            $form->addButton($item->getName() . ": " . $alldata[$i]["buyprice"] . "円 売却値段: " . $alldata[$i]["sellprice"] . "円");
        }

        $player->sendForm($form);
    }

    private function SelectAdminShop(Player $player, $itemdata)
    {
        $form = new CustomForm(function (Player $player, $data) use ($itemdata) {
            if ($data === null) {
                return;
            }

            $item = Item::get($itemdata["itemid"], $itemdata["itemmeta"], (int)$data[1]);
            if ($data[0] === 0) {
                if ($player->getInventory()->canAddItem($item)) {
                    $this->AdminShopBuyCheck($player, $item, $itemdata);
                } else {
                    $player->sendMessage("インベントリの空き容量が足りません");
                }
            } elseif ($data[0] === 1) {
                if ($player->getInventory()->contains($item)) {
                    $this->AdminShopSellCheck($player, $item, $itemdata);
                } else {
                    $player->sendMessage("自分の所持しているアイテム以上のアイテムを売却することはできません");
                }
            }
        });

        $form->setTitle("iPhone-AdminShop-購入・売却");
        $form->addDropdown("購入・売却", ["購入", "売却"]);
        $form->addSlider("購入・売却する個数", 1, 64);
        $player->sendForm($form);
    }

    private function AdminShopBuyCheck(Player $player, $item, $itemdata)
    {
        $price = $item->getCount() * $itemdata["buyprice"];

        $form = new ModalForm(function (Player $player, $data) use ($item, $price) {
            if ($data === true) {
                $xuid = $player->getXuid();
                $playerdata = $this->db->GetMoney($xuid);
                if ($price > $playerdata["money"]) {
                    $player->sendMessage("お金が" . ($playerdata["money"] - $price) * -1 . "円足りていませんよ？");
                    return;
                }

                $this->db->UpdateMoney($xuid, $playerdata["money"] - $price);
                $player->getInventory()->addItem($item);
                $player->sendMessage("購入しました");
            } elseif ($data === false) {
                $player->sendMessage("購入しませんでした");
            }
        });

        $form->setTitle("iPhone-AdminShop-購入最終確認");
        $form->setContent($item->getName() . "を" . $item->getCount() . "個購入しますか？\n" . $price . "円です");
        $form->setButton1("購入する");
        $form->setButton2("やめる");
        $player->sendForm($form);
    }

    private function AdminShopSellCheck(Player $player, $item, $itemdata)
    {
        $price = $item->getCount() * $itemdata["sellprice"];

        $form = new ModalForm(function (Player $player, $data) use ($item, $price) {
            if ($data === true) {
                $xuid = $player->getXuid();
                $playerdata = $this->db->GetMoney($xuid);
                $this->db->UpdateMoney($xuid, $playerdata["money"] + $price);
                $player->getInventory()->removeItem($item);
                $player->sendMessage("売却しました");
            } elseif ($data === false) {
                $player->sendMessage("売却しませんでした");
            }
        });

        $form->setTitle("iPhone-AdminShop-売却最終確認");
        $form->setContent($item->getName() . "を" . $item->getCount() . "個売却しますか？");
        $form->setButton1("売却する");
        $form->setButton2("やめる");
        $player->sendForm($form);
    }

    private function buyland(Player $player)
    {
        $pos1 = new Vector3($this->startlands[$player->getXuid()]->x, $this->startlands[$player->getXuid()]->y, $this->startlands[$player->getXuid()]->z);
        $pos2 = new Vector3($this->endlands[$player->getXuid()]->x, $this->endlands[$player->getXuid()]->y, $this->endlands[$player->getXuid()]->z);
        $xuid = $player->getXuid();
        $levelname = $player->getLevel()->getName();
        $this->db->SetLand($xuid, $levelname, $pos1, $pos2);
        unset($this->startlands[$player->getXuid()]);
        unset($this->endlands[$player->getXuid()]);
    }
}
