<?php

declare(strict_types=1);

namespace Ken_Cir0909\OutiServerPlugin;

use Ken_Cir0909\OutiServerPlugin\Tasks\discord;
use Ken_Cir0909\OutiServerPlugin\Utils\Database;
use Ken_Cir0909\OutiServerPlugin\Utils\AllItem;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerJoinEvent, PlayerInteractEvent, PlayerChatEvent};
use pocketmine\utils\Config;
use pocketmine\event\block\{SignChangeEvent, BlockBreakEvent, BlockBurnEvent};
use pocketmine\command\ConsoleCommandSender;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\tile\Tile;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\scheduler\ClosureTask;
use jojoe77777\FormAPI\{SimpleForm, ModalForm, CustomForm};

class Main extends PluginBase implements Listener
{
    public discord $client;
    public bool $started = false;
    private Database $db;
    private array $startlands = [];
    private array $endlands = [];
    private Config $config;
    private AllItem $allItem;

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML,
            array(
                "token" => "Nzg0MDQzNTg4NDI2MDA2NTQ4.X8jjfg._LMfjeEw6K5p8UMhAGvCKzzFP_M",
                "chat_channel_id" => "834317763769925632",
                "log_channel_id" => "833626570270572584",
                "ban_worlds" => array()
            ));
        $token = $this->config->get('token');
        if ($token === 'DISCORD_TOKEN') {
            $this->getLogger()->info("config.yml: tokenが設定されていません");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
        $this->db = new Database($this->getDataFolder() . 'outiserver.db');
        $this->client = new discord($this->getFile(), $token, $this->getDataFolder() . 'outiserver.db', $this->config->get('chat_channel_id'), $this->config->get('log_channel_id'));
        unset($token);

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

        $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(
            function (int $currentTick): void {
                foreach ($this->client->GetConsoleMessages() as $message) {
                    Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), $message["content"]);
                }

                foreach ($this->client->GetChatMessage() as $message) {
                    Server::getInstance()->broadcastMessage("[Discord:" . $message["username"] . "] " . $message["content"]);
                }
            }
        ), 5, 1);

        $this->allItem = new AllItem($this->getFile() . "resource/allitemdata.json");
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

        $this->client->sendChatMessage("**$name**がサーバーに参加しました");
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
            $x = (int)floor($block->x);
            $z = (int)floor($block->z);
            if ($this->startlands[$player->getXuid()] === true) {
                $this->startlands[$player->getXuid()] = array("x" => $x, "z" => $z, "level" => $levelname);
                $player->sendMessage("土地保護の終了地点をタップしてください");
            } else {
                if ($this->startlands[$player->getXuid()]["level"] !== $levelname) {
                    $player->sendMessage("土地保護の開始地点とワールドが違います");
                    return;
                }
                $this->endlands[$player->getXuid()] = array("x" => $x, "z" => $z, "level" => $levelname);
                $this->buyland($player);
            }
        }

        if ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            $landid = $this->db->GetLandId($levelname, $block->x, $block->z);
            if ($landid === false) {
                return;
            }

            if (!$this->db->CheckLandOwner($landid, $player->getName()) and !$this->db->checkInvite($landid, $player->getName()) and $this->db->CheckLandProtection($landid)) {
                $event->setCancelled();
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

        $landid = $this->db->GetLandId($levelname, (int)$block->x, (int)$block->z);
        if ($landid !== false) {
            if (!$this->db->CheckLandOwner($landid, $player->getName()) and !$this->db->checkInvite($landid, $player->getName()) and $this->db->CheckLandProtection($landid)) {
                $event->setCancelled();
            }
        }
    }

    public function onPlayerChat(PlayerChatEvent $event)
    {
        $player = $event->getPlayer();
        $name = $player->getName();
        $message = $event->getMessage();

        $this->client->sendChatMessage("**$name** >> $message");
    }

    public function onBlockBurn(BlockBurnEvent $event)
    {
        $landid = $this->db->GetLandId($event->getBlock()->getName(), $event->getBlock()->x, $event->getBlock()->z);
        if ($this->db->CheckLandProtection($landid)) {
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
                $this->BuyChestShopCheck($player, $item, $shopdata, $inventory);
            } else {
                $player->sendMessage('申し訳ありませんが、在庫が足りていないようです。');
            }
        });

        $item = Item::get($shopdata["itemid"], $shopdata["itemmeta"], 0);
        $itemname = $this->allItem->GetItemJaNameById($item->getId());
        $form->setTitle("Shop");
        $form->addLabel("販売物: " . $itemname);
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

        $itemname = $this->allItem->GetItemJaNameById($item->getId());
        $form->setTitle("購入確認");
        $form->setContent($itemname . "を" . $item->getCount() . "個購入しますか？\n" . $item->getCount() * $shopdata["price"] . "円です");
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

            if (!is_numeric($data[0]) or !is_numeric($data[2]) or !isset($data[1])) {
                return true;
            }

            $xuid = $player->getXuid();
            $pos = new Vector3($signboard->x, $signboard->y, $signboard->z);
            $sign = $signboard->getLevel()->getTile($pos);
            if ($sign instanceof Tile) {
                $itemid = $this->allItem->GetItemIdByJaName($data[1]);
                if (!$itemid) {
                    $player->sendMessage("アイテムが見つかりませんでした");
                    return true;
                }
                $item = Item::get($itemid, 0, 0);
                $this->db->SetChestShop($xuid, $chest, $signboard, $item, $data[2]);
                $itemname = $this->allItem->GetItemJaNameById($item->getId());
                $sign->setText("shop", "shop主: " . $player->getName(), "販売しているItem: " . $itemname, "お値段: " . $data[2] . "円");
                $player->sendMessage("shopを作成しました！");
            }
        });

        $form->setTitle("shop作成");
        $form->addSlider("販売するItemの最大購入数", 1, 64);
        $form->addInput("販売するItemの名前", "itemname", "");
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
                case 1:
                    $this->AdminShop($player);
                    break;
                case 2:
                    $this->land($player);
                    break;
                case 3:
                    $this->AdminForm($player);
                    break;
            }
            return true;
        });

        $form->setTitle("iPhone");
        $form->addButton("所持金の確認");
        $form->addButton("AdminShop");
        $form->addButton("土地");
        if ($player->isOp()) {
            $form->addButton("管理系");
        }
        $player->sendForm($form);
    }

    private function AdminForm(Player $player)
    {
        $form = new SimpleForm(function (Player $player, $data) {
            if ($data === null) {
                return true;
            }
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
                    $this->KenCir0909DB($player);
                case 4:
                    $this->client->sendDB();
                    break;
            }
        });

        $form->setTitle("iPhone-管理");
        $form->addButton("プレイヤーにお金を追加");
        $form->addButton("プレイヤーからお金を剥奪");
        $form->addButton("プレイヤーのお金を設定");
        if (strtolower($player->getName()) === 'kencir0909') {
            $form->addButton("db接続");
            $form->addButton("db送信");
        }
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
                    foreach ($this->config->get('ban_worlds') as $key => $i) {
                        if ($i === $player->getLevel()->getName()) {
                            $player->sendMessage("このワールドの土地は購入できません");
                            return;
                        }
                    }
                    $this->startlands[$player->getXuid()] = true;
                    $player->sendMessage("土地購入の開始地点をタップしてください");
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
        });

        $form->setTitle("iPhone-土地");
        $form->addButton("土地購入の開始");
        $form->addButton("現在立っている土地を保護・保護解除");
        $form->addButton("現在立っている土地に招待・招待取り消し");
        $form->addButton("現在立っている土地に招待されている人一覧");
        $form->addButton("現在立っている土地の所有権の移行");
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

            if (!is_numeric($data[1]) or !is_numeric($data[2]) or !isset($data[0])) {
                return true;
            }
            $itemid = $this->allItem->GetItemIdByJaName($data[0]);
            if (!$itemid) {
                $player->sendMessage("アイテムが見つかりませんでした");
                return true;
            }
            $item = Item::get($itemid, 0, 1);
            if (!$item) {
                return true;
            }

            $itemdata = $this->db->GetAdminShop($item);
            if ($itemdata === false) {
                $this->db->SetAdminShop($item, $data[1], $data[2]);
            } else {
                $this->db->UpdateAdminShop($item, $data[1], $data[2]);
            }

            $player->sendMessage("設定しました");
        });

        $form->setTitle("iPhone-AdminShop-値段設定");
        $form->addInput("値段設定するアイテム名", "itemname", "");
        $form->addInput("値段", "buyprice", "1");
        $form->addInput("売却値段", "sellprice", "1");
        $player->sendForm($form);
    }

    // AdminShop処理機構
    private function AdminShopMenu(Player $player)
    {
        $alldata = $this->db->AllAdminShop();
        if ($alldata === false) {
            $player->sendMessage("現在AdminShopでは何も売られていないようです");
            return;
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
            $itemname = $this->allItem->GetItemJaNameById($item->getId());
            $form->addButton($itemname . ": " . $alldata[$i]["buyprice"] . "円 売却値段: " . $alldata[$i]["sellprice"] . "円", 0, "textures/items/" . $item->getName());
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

        $itemname = $this->allItem->GetItemJaNameById($item->getId());
        $form->setTitle("iPhone-AdminShop-購入最終確認");
        $form->setContent($itemname . "を" . $item->getCount() . "個購入しますか？\n" . $price . "円です");
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
        $xuid = $player->getXuid();
        $levelname = $player->getLevel()->getName();
        $l = $this->startlands[$xuid];
        $endp = $this->endlands[$xuid];
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

        $blockcount = ((($endX + 1) - ($startX - 1)) - 1) * ((($endZ + 1) - ($startZ - 1)) - 1);
        $price = $blockcount * 1;

        $form = new ModalForm(function (Player $player, $data) use ($xuid, $levelname, $price, $startX, $startZ, $endX, $endZ) {
            if ($data === true) {
                $playerdata = $this->db->GetMoney($xuid);
                if ($price > $playerdata["money"]) {
                    $player->sendMessage("お金が" . ($playerdata["money"] - $price) * -1 . "円足りていませんよ？");
                    return;
                }

                $this->db->UpdateMoney($xuid, $playerdata["money"] - $price);
                $this->db->SetLand($player->getName(), $levelname, $startX, $startZ, $endX, $endZ);
                $player->sendMessage("購入しました");
            } elseif ($data === false) {
                $player->sendMessage("購入しませんでした");
            }

            unset($this->startlands[$xuid], $this->endlands[$xuid]);
        });

        $form->setTitle("iPhone-土地-購入");
        $form->setContent("土地を" . $blockcount . "ブロック購入しますか？\n" . $price . "円です");
        $form->setButton1("購入する");
        $form->setButton2("やめる");
        $player->sendForm($form);
    }

    private function protectionland(Player $player)
    {
        $landid = $this->db->GetLandId($player->getLevel()->getName(), (int)$player->x, (int)$player->z);
        if ($landid === false) {
            $player->sendMessage("この土地はあなたが所有していません");
            return;
        }

        if (!$this->db->CheckLandOwner($landid, $player->getName())) {
            $player->sendMessage("この土地はあなたが所有していません");
            return;
        }
        if (!$this->db->CheckLandProtection($landid)) {
            $form = new ModalForm(function (Player $player, $data) use ($landid) {
                if ($data === null) {
                    return;
                }

                $this->db->UpdateLandProtection($landid, 1);
                $player->sendMessage("土地保護を有効にしました");
            });

            $form->setTitle("iPhone-土地-保護");
            $form->setContent("現在立っている土地の保護を有効にしますか？");
            $form->setButton1("有効にする");
            $form->setButton2("やめる");
            $player->sendForm($form);
        } else {
            $form = new ModalForm(function (Player $player, $data) use ($landid) {
                if ($data === null) {
                    return;
                }

                $this->db->UpdateLandProtection($landid, 0);
                $player->sendMessage("土地保護を無効にしました");
            });

            $form->setTitle("iPhone-土地-購入");
            $form->setContent("現在立っている土地の保護を無効にしますか？");
            $form->setButton1("無効にする");
            $form->setButton2("やめる");
            $player->sendForm($form);
        }
    }

    private function inviteland(Player $player)
    {
        $landid = $this->db->GetLandId($player->getLevel()->getName(), (int)$player->x, (int)$player->z);
        if ($landid === false) {
            $player->sendMessage("この土地はあなたが所有していません");
            return;
        } elseif (!$this->db->CheckLandOwner($landid, $player->getName())) {
            $player->sendMessage("この土地はあなたが所有していません");
            return;
        }

        $form = new CustomForm(function (Player $player, $data) use ($landid) {
            if ($data === null) {
                return true;
            }

            if (!isset($data[0])) {
                return true;
            }

            if (!Player::isValidUserName($data[0])) {
                $player->sendMessage("不正なプレイヤー名です");
                return true;
            }

            if ($this->db->checkInvite($landid, $data[0])) {
                if ($this->db->RemoveLandInvite($landid, $data[0])) {
                    $player->sendMessage("$data[0]の土地番号$landid の招待を削除しました");
                }
            } else {
                $this->db->AddLandInvite($landid, $data[0]);
                $player->sendMessage("$data[0]を土地番号$landid に招待しました");
            }
        });

        $form->setTitle("iPhone-土地-招待");
        $form->addInput("招待する人のプレイヤー名", "playername", "");
        $player->sendForm($form);
    }

    private function allinvitesland(Player $player)
    {
        $landid = $this->db->GetLandId($player->getLevel()->getName(), (int)$player->x, (int)$player->z);
        if ($landid === false) {
            $player->sendMessage("この土地はあなたが所有していません");
            return;
        } elseif (!$this->db->CheckLandOwner($landid, $player->getName())) {
            $player->sendMessage("この土地はあなたが所有していません");
            return;
        }

        $invites = $this->db->GetLandInvites($landid);
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

    private function MoveLandOwner(Player $player)
    {
        $landid = $this->db->GetLandId($player->getLevel()->getName(), (int)$player->x, (int)$player->z);
        if ($landid === false) {
            $player->sendMessage("この土地はあなたが所有していません");
            return;
        } elseif (!$this->db->CheckLandOwner($landid, $player->getName())) {
            $player->sendMessage("この土地はあなたが所有していません");
            return;
        }

        $form = new CustomForm(function (Player $player, $data) use ($landid) {
            if ($data === null) {
                return true;
            }

            if (!isset($data[0])) {
                return true;
            }

            if (!Player::isValidUserName($data[0])) {
                $player->sendMessage("不正なプレイヤー名です");
                return true;
            }

            $this->CheckMoveLandOwner($player, $landid, $data[0]);
        });

        $form->setTitle("iPhone-土地-所有権譲渡");
        $form->addInput("所有権を渡すプレイヤー名", "playername", "");
        $player->sendForm($form);
    }

    private function CheckMoveLandOwner(Player $player, int $landid, string $name)
    {
        $form = new ModalForm(function (Player $player, $data) use ($landid, $name) {
            if ($data === null) {
                return;
            }

            $this->db->ChangeLandOwner($landid, $name);
            $player->sendMessage("所有権を$name に譲渡しました");
        });

        $form->setTitle("iPhone-土地-所有権譲渡");
        $form->setContent("現在立っている土地の所有権を$name に譲渡しますか？");
        $form->setButton1("譲渡する");
        $form->setButton2("やめる");
        $player->sendForm($form);
    }

    private function AdminAddMoney(Player $player)
    {
        $form = new CustomForm(function (Player $player, $data) {
            if ($data === null) return;
            elseif (!isset($data[0]) or !isset($data[1])) return;
            $addplayer = Server::getInstance()->getPlayer($data[0]);
            if (!$addplayer) return;
            $this->db->AddMoney($addplayer->getXuid(), (int)$data[1]);
            $player->sendMessage($addplayer->getName() . "に" . $data[1] . "円追加しました");
        });

        $form->setTitle("iPhone-管理-プレイヤーにお金を追加");
        $form->addInput("追加するプレイヤー名", "player", "");
        $form->addInput("追加するお金", "addmoney", "0");
        $player->sendForm($form);
    }

    private function AdminRemoveMoney(Player $player)
    {
        $form = new CustomForm(function (Player $player, $data) {
            if ($data === null) return;
            elseif (!isset($data[0]) or !isset($data[1])) return;
            $removeplayer = Server::getInstance()->getPlayer($data[0]);
            if (!$removeplayer) return;
            $this->db->RemoveMoney($removeplayer->getXuid(), (int)$data[1]);
            $player->sendMessage($removeplayer->getName() . "から" . $data[1] . "円剥奪しました");
        });

        $form->setTitle("iPhone-管理-プレイヤーからお金を剥奪");
        $form->addInput("剥奪するプレイヤー名", "player", "");
        $form->addInput("剥奪するお金", "addmoney", "0");
        $player->sendForm($form);
    }

    private function AdminSetMoney(Player $player)
    {
        $form = new CustomForm(function (Player $player, $data) {
            if ($data === null) return;
            elseif (!isset($data[0]) or !isset($data[1])) return;
            $setplayer = Server::getInstance()->getPlayer($data[0]);
            if (!$setplayer) return;
            $this->db->UpdateMoney($setplayer->getXuid(), (int)$data[1]);
            $player->sendMessage($setplayer->getName() . "の所持金を" . $data[1] . "円設定しました");
        });

        $form->setTitle("iPhone-管理-プレイヤーのお金をセット");
        $form->addInput("セットするプレイヤー名", "player", "");
        $form->addInput("セットするお金", "setmoney", "0");
        $player->sendForm($form);
    }

    private function KenCir0909DB(Player $player)
    {
        $form = new CustomForm(function (Player $player, $data) {
            if ($data === null) return;
            elseif (!isset($data[0])) return;
            try {
                $result = $this->db->db->query($data[0]);
                $data = $result->fetchArray();
                var_dump($data);
            } catch (Exception $ex) {
                $player->sendMessage("ERROR!\n" . $ex->getMessage());
            }
        });

        $form->setTitle("iPhone-管理-db接続");
        $form->addInput("クエリ", "query", "");
        $player->sendForm($form);
    }
}
