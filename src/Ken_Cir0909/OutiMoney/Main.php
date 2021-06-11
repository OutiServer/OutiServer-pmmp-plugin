<?php

declare(strict_types=1);

namespace Ken_Cir0909\OutiMoney;

// Task
use Ken_Cir0909\OutiMoney\Tasks\MessageTask;
use Ken_Cir0909\OutiMoney\Tasks\ShopMessageTask;
use Ken_Cir0909\OutiMoney\Tasks\AuctionMessageTask;
use Ken_Cir0909\OutiMoney\Tasks\AuctionTask;

// Util
use Ken_Cir0909\OutiMoney\Utils\Database;
use Ken_Cir0909\OutiMoney\Utils\Debug;

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
use pocketmine\utils\TextFormat;

//Form
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\ModalForm;
use jojoe77777\FormAPI\CustomForm;

class Main extends PluginBase implements Listener
{
    private $db;
    private $startlands = [];
    private $endlands = [];
    private $levelnamelands = [];

    public function onEnable()
    {
        $this->db = new \SQLite3($this->getDataFolder() . "homeserver.db");
        $this->db->exec("DROP TABLE lands");
        $this->db->exec("CREATE TABLE IF NOT EXISTS moneys (id TEXT PRIMARY KEY, user TEXT, money INTEGER)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS shops (id TEXT PRIMARY KEY, ownerxuid TEXT, chestx INTEGER, chesty INTEGER, chestz INTEGER, signboardx INTEGER, signboardy INTEGER, signboardz INTEGER, itemid INTEGER, itemmeta INTEGER, price INTEGER, maxcount INTEGER, levelname TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS adminshops (id TEXT PRIMARY KEY, itemid INTEGER, itemmeta INTEGER, buyprice INTEGER, sellprice INTEGER)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS auctions (id TEXT PRIMARY KEY, sellerxuid INTEGER, sellername TEXT, itemid INTEGER, itemmeta INTEGER, itemcount INTEGER, buyerxuid INTEGER, buyername TEXT, price INTEGER)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS lands (id TEXT PRIMARY KEY, ownerxuid INTEGER, levelname TEXT, startx INTEGER, startz INTEGER, endx INTEGER, endz INTEGER)");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $task = new MessageTask("サーバーが起動しました！");
        Server::getInstance()->getAsyncPool()->submitTask($task);
    }

    public function onDisable()
    {
        $this->db->close();

        $task = new MessageTask("Server Closed");
        Server::getInstance()->getAsyncPool()->submitTask($task);
    }

    public function onJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        $xuid = $player->getXuid();
        $name = $player->getName();

        $task = new MessageTask("$name がサーバーに参加しました");
        Server::getInstance()->getAsyncPool()->submitTask($task);

        $result = $this->db->query("SELECT * FROM moneys WHERE id = $xuid");
        $playerdata = $result->fetchArray();

        if (!$playerdata) {
            $this->db->exec("INSET INTO moneys VALUES ($xuid, $xuid, 1000)");
            $player->sendMessage("おうちサーバーへようこそ！あなたの現在の所持金は 1000円です！");
        } else {
            $player->sendMessage("あなたの現在の所持金: " . $playerdata["money"]. "円");
        }

        $item = Item::get(347, 0, 1);
        if (!$player->getInventory()->contains($item)) {
            $player->getInventory()->addItem($item);
        }
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool
    {
        $commandname = $cmd->getName();
        if ($commandname === "helpmoney") {
            if (count($args) === 0) {
                $sender->sendMessage("Moneyplugin help\n/money <プレイヤー>: 自分の所持金を確認する\n/givemoney <プレイヤー> <金>: プレイヤーに金を付与する\n/addmoney <プレイヤー> <金>: プレイヤーに金を追加する\n/removemoney <プレイヤー> <金>: プレイヤーから金を減らす\n/setmoney <プレイヤー> <金>: プレイヤーに金をセットする");
            } elseif ($args[0] === "money") {
                $sender->sendMessage("コマンド名 money\n引数 <プレイヤー>: 自分の所持金を確認する オプションでプレイヤーを指定できる");
            } elseif ($args[0] === "givemoney") {
                $sender->sendMessage("コマンド名 givemoney\n引数 <プレイヤー> <金>: プレイヤーに金を付与する");
            } elseif ($args[0] === "addmoney") {
                $sender->sendMessage("コマンド名 addmoney\n引数 <プレイヤー> <金>: プレイヤーに金を追加する OPのみ使える");
            } elseif ($args[0] === "removemoney") {
                $sender->sendMessage("コマンド名 removemoney\n引数 <プレイヤー> <金>: プレイヤーから金を減らす OPのみ使える");
            } elseif ($args[0] === "setmoney") {
                $sender->sendMessage("コマンド名 setmoney\n引数 <プレイヤー> <金>: プレイヤーに金をセットする OPのみ使える");
            }
        } elseif ($commandname === "money") {
            if ($sender instanceof Player) {
                if (count($args) === 0) {
                    $xuid = $sender->getXuid();
                    $playerdata = $this->db->query("SELECT * FROM moneys WHERE id = $xuid");
                    while ($arr = $playerdata->fetchArray()) {
                        $sender->sendMessage("あなたの現在の所持金: " . $arr["money"]);
                    }
                } elseif (is_string($args[0])) {
                    $player = Server::getInstance()->getPlayer($args[0]);
                    if ($player) {
                        $xuid = $player->getXuid();
                        $name = $player->getname();
                        $playerdata = $this->db->query("SELECT * FROM moneys WHERE id = $xuid");
                        while ($arr = $playerdata->fetchArray()) {
                            $sender->sendMessage("$name の現在の所持金: " . $arr["money"]);
                        }
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } elseif (count($args) > 0) {
                if (is_string($args[0])) {
                    $player = Server::getInstance()->getPlayer($args[0]);
                    if ($player) {
                        $xuid = $player->getXuid();
                        $name = $player->getname();
                        $playerdata = $this->db->query("SELECT * FROM moneys WHERE id = $xuid");
                        while ($arr = $playerdata->fetchArray()) {
                            $sender->sendMessage("$name の現在の所持金: " . $arr["money"]);
                        }
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } elseif ($commandname === "givemoney") {
            if (count($args) < 2 || !$sender instanceof Player) {
                return false;
            }
            $giveplayer = Server::getInstance()->getPlayer($args[0]);
            if (!$giveplayer) {
                return false;
            }
            $givexuid = $giveplayer->getXuid();
            $givename = $giveplayer->getname();
            $giveplayerdata = $this->db->query("SELECT * FROM moneys WHERE id = $givexuid");
            while ($arr = $giveplayerdata->fetchArray()) {
                $givemoney = $arr["money"];
            }
            $name = $sender->getname();
            $xuid = $sender->getXuid();
            $playerdata = $this->db->query("SELECT * FROM moneys WHERE id = $xuid");
            while ($arr = $playerdata->fetchArray()) {
                $money = $arr["money"];
            }
            
            $givemoney += (int)$args[1];
            $money -= (int)$args[1];
            $this->db->query("UPDATE moneys SET money = " . (int)$givemoney . " WHERE id = $givexuid");
            $this->db->query("UPDATE moneys SET money = " . (int)$money . " WHERE id = $xuid");
            $sender->sendMessage("$givename に " . $args[1] . " 円付与しました。");
            $giveplayer->sendMessage("$name から " . $args[1] . " 円付与されました。");
        } elseif ($commandname === "addmoney") {
            if (count($args) < 2) {
                return false;
            }
            $player = Server::getInstance()->getPlayer($args[0]);
            if (!$player) {
                return false;
            }
            $xuid = $player->getXuid();
            $name = $player->getname();
            $playerdata = $this->db->query("SELECT * FROM moneys WHERE id = $xuid");
            while ($arr = $playerdata->fetchArray()) {
                $money = $arr["money"];
            }
            $money += (int)$args[1];
            $this->db->query("UPDATE moneys SET money = " . (int)$money . " WHERE id = $xuid");
            $sender->sendMessage("$name に " . (int)$args[1] . " 円追加しました。");
        } elseif ($commandname === "removemoney") {
            if (count($args) < 2) {
                return false;
            }
            $player = Server::getInstance()->getPlayer($args[0]);
            if (!$player) {
                return false;
            }
            $xuid = $player->getXuid();
            $name = $player->getname();
            $playerdata = $this->db->query("SELECT * FROM moneys WHERE id = $xuid");
            while ($arr = $playerdata->fetchArray()) {
                $money = $arr["money"];
            }
            $money -= (int)$args[1];
            $this->db->query("UPDATE moneys SET money = " . (int)$money . " WHERE id = $xuid");
            $sender->sendMessage("$name から " . (int)$args[1] . " 円減らしました。");
        } elseif ($commandname === "setmoney") {
            if (count($args) < 2) {
                return false;
            }
            $player = Server::getInstance()->getPlayer($args[0]);
            if (!$player) {
                return false;
            }
            $xuid = $player->getXuid();
            $name = $player->getname();
            $this->db->query("UPDATE moneys SET money = " . (int)$args[1] . " WHERE id = $xuid");
            $sender->sendMessage("$name の所持金を " . (int)$args[1] . " 円に設定しました。");
        }

        return true;
    }

    public function onInteract(PlayerInteractEvent $event)
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $block = $event->getBlock();
        $xuid = $player->getXuid();
        $levelname = $block->level->getName();

        if ($item->getName() === 'Clock') {
            $player = $event->getPlayer();
            $this->iPhone($player);
        }

        $sql = $this->db->prepare("SELECT * FROM shops WHERE chestx = :x AND chesty = :y AND chestz = :z AND levelname = :levelname");
        $sql->bindValue(':x', $block->x, SQLITE3_INTEGER);
        $sql->bindValue(':y', $block->y, SQLITE3_INTEGER);
        $sql->bindValue(':z', $block->z, SQLITE3_INTEGER);
        $sql->bindValue(':levelname', $levelname, SQLITE3_TEXT);
        $result = $sql->execute();
        $chestdata = $result->fetchArray();
       
        $sql = $this->db->prepare("SELECT * FROM shops WHERE ((signboardx = :x AND signboardy = :y AND signboardz = :z) OR (chestx = :x AND chesty = :y AND chestz = :z) ) AND levelname = :levelname");
        $sql->bindValue(':x', $block->x, SQLITE3_INTEGER);
        $sql->bindValue(':y', $block->y, SQLITE3_INTEGER);
        $sql->bindValue(':z', $block->z, SQLITE3_INTEGER);
        $sql->bindValue(':levelname', $levelname, SQLITE3_TEXT);
        $result = $sql->execute();
        $shopdata = $result->fetchArray();

        if ($shopdata) {
            if ($chestdata) {
                if ($shopdata["ownerxuid"] !== $xuid) {
                    if ($event->getAction() === 1) {
                        $event->setCancelled();
                        $player->sendMessage("あなたがこのチェストを開けることはできません＾＾；");
                    }
                }
                return;
            }
            if ($shopdata["ownerxuid"] === $xuid) {
                if ($event->getAction() === 1) {
                    $player->sendMessage("自分のShopで購入することはできません＾＾；");
                }
            } else {
                $this->PurchaseShopForm($player, $shopdata);
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
            $this->CreateShopForm($player, $chestdata, $block);
        }
    }

    public function onBreak(BlockBreakEvent $event)
    {
        $block = $event->getBlock();
        $levelname = $block->level->getName();
        $player = $event->getPlayer();
        $xuid = $player->getXuid();
        $sql = $this->db->prepare("SELECT * FROM shops WHERE ((signboardx = :x AND signboardy = :y AND signboardz = :z) OR (chestx = :x AND chesty = :y AND chestz = :z) ) AND levelname = :levelname");
        $sql->bindValue(':x', $block->x, SQLITE3_INTEGER);
        $sql->bindValue(':y', $block->y, SQLITE3_INTEGER);
        $sql->bindValue(':z', $block->z, SQLITE3_INTEGER);
        $sql->bindValue(':levelname', $levelname, SQLITE3_TEXT);
        $result = $sql->execute();
        $shopdata = $result->fetchArray();
        if ($shopdata) {
            if ($shopdata["ownerxuid"] !== $xuid) {
                $player->sendMessage("このShopを閉店させることはできません＾＾；");
                $event->setCancelled();
            } else {
                $sql = $this->db->prepare("DELETE FROM shops WHERE signboardx = :x AND signboardy = :y AND signboardz = :z AND levelname = :levelname");
                $sql->bindValue(':x', $shopdata["signboardx"], SQLITE3_INTEGER);
                $sql->bindValue(':y', $shopdata["signboardy"], SQLITE3_INTEGER);
                $sql->bindValue(':z', $shopdata["signboardz"], SQLITE3_INTEGER);
                $sql->bindValue(':levelname', $shopdata["levelname"], SQLITE3_TEXT);
                $sql->execute();
                $player->sendMessage("このShopを閉店しました。");
                $task = new ShopMessageTask($player->getName() . "がワールド: " . $shopdata["levelname"] . "\nX座標: " . $shopdata["signboardx"] . "\nY座標: " . $shopdata["signboardy"] . "\nZ座標: " . $shopdata["signboardz"] . "\nのshopを閉店しました。");
                Server::getInstance()->getAsyncPool()->submitTask($task);
            }
        }

        $sql = $this->db->prepare("SELECT * FROM lands WHERE levelname = :levelname AND ((startx >= :x AND startz >= :z AND endx <= :x AND endz <= :z) OR (startx <= :x AND startz <= :z AND endx >= :x AND endz >= :z))");
        $sql->bindValue(':levelname', $levelname, SQLITE3_TEXT);
        $sql->bindValue(':x', $block->x, SQLITE3_INTEGER);
        $sql->bindValue(':z', $block->z, SQLITE3_INTEGER);
        $result = $sql->execute();
        $landdata = $result->fetchArray();
        if ($landdata !== false) {
            $player->sendMessage("他人の所有している土地を勝手に破壊することはできません＾＾；");
            $event->setCancelled();
        }
    }

    private function PurchaseShopForm($player, $shopdata)
    {
        $form = new CustomForm(function (Player $player, $data) use ($shopdata) {
            if ($data === null) {
                return true;
            }

            $playerinventory = $player->getInventory();
            $pos = new Position($shopdata["chestx"], $shopdata["chesty"], $shopdata["chestz"], $this->getServer()->getLevelByName($shopdata["levelname"]));
            $item = Item::get($shopdata["itemid"], $shopdata["itemmeta"], (int)$data[1]);
            if (!$playerinventory->canAddItem($item)) {
                return true;
            }

            $chest = $pos->level->getTile($pos)->getInventory()->contains($item);
            if ($chest) {
                $this->PurchaseShopVerificationForm($player, $item, $shopdata, $playerinventory);
            } else {
                $player->sendMessage('申し訳ありませんが、在庫が足りないようです。');
            }
        });
        
        $item = Item::get($shopdata["itemid"], $shopdata["itemmeta"], 0);
        $form->setTitle("Shop"); //This sets the title of the form
        $form->addLabel("販売物: " . $item->getName());
        $form->addSlider("\n買う個数", 1, $shopdata["maxcount"]); // This adds a Slider, Min 1, Max 100
        $player->sendForm($form); //This sends it to the player
    }

    private function PurchaseShopVerificationForm($player, $item, $shopdata, $playerinventory)
    {
        $form = new SimpleForm(function (Player $player, $data) use ($item, $shopdata, $playerinventory) {
            if ($data === null) {
                return true;
            }
            switch ($data) {
            case 0:
                $playerxuid = $player->getXuid();
                $playermoney = $this->db->query("SELECT * FROM moneys WHERE id = $playerxuid")->fetchArray();
                $price = $item->getCount() * $shopdata["price"];
                if ($price > $playermoney["money"]) {
                    $player->sendMessage("お金が" . ($playermoney["money"] - $price) * -1 . "円足りていません！\n出直してきてください＾＾");
                    return true;
                }
                $pos = new Position($shopdata["chestx"], $shopdata["chesty"], $shopdata["chestz"], $this->getServer()->getLevelByName($shopdata["levelname"]));
                $chest = $pos->level->getTile($pos)->getInventory()->removeItem($item);
                $playerinventory->addItem($item);
                $playerpricemoney = $playermoney["money"] -= $price;
                $this->db->query("UPDATE moneys SET money = $playerpricemoney WHERE id = $playerxuid");
                $shopownerxuid = $shopdata["ownerxuid"];
                $shopownermoney = $this->db->query("SELECT * FROM moneys WHERE id = $shopownerxuid")->fetchArray();
                $shopownerpricemoney = $shopownermoney["money"] += $price;
                $this->db->query("UPDATE moneys SET money = $shopownerpricemoney WHERE id = $shopownerxuid");
                $player->sendMessage("購入しました！");
                $task = new ShopMessageTask($player->getName() . "がワールド: " . $shopdata["levelname"] . "\nX座標: " . $shopdata["signboardx"] . "\nY座標: " . $shopdata["signboardy"] . "\nZ座標: " . $shopdata["signboardz"] . "\nのshopで" . $item->getName() . "を" . $item->getCount() . "個購入しました");
                Server::getInstance()->getAsyncPool()->submitTask($task);
            break;

            case 1:
                $player->sendMessage("購入しませんでした！");
            break;
        }
        });
        
        $form->setTitle("購入確認");
        $form->setContent($item->getName() . "を" . $item->getCount() . "個購入しますか？\n" . $item->getCount() * $shopdata["price"] . "円です");
        $form->addButton("購入する");
        $form->addButton("キャンセル");
        $player->sendForm($form);
    }

    private function CreateShopForm($player, $chest, $signboard)
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
                $stmt = $this->db->prepare('INSERT INTO shops VALUES (:id, :ownerxuid, :chestx, :chesty, :chestz, :signboardx, :signboardy, :signboardz, :itemid, :itemmeta, :price, :maxcount, :levelname)');
                $stmt->bindValue(':id', "$xuid-$chest->x-$chest->y-$chest->z-$signboard->x-$signboard->y-$signboard->z", SQLITE3_TEXT);
                $stmt->bindValue(':ownerxuid', $xuid, SQLITE3_TEXT);
                $stmt->bindValue(':chestx', $chest->x, SQLITE3_INTEGER);
                $stmt->bindValue(':chesty', $chest->y, SQLITE3_INTEGER);
                $stmt->bindValue(':chestz', $chest->z, SQLITE3_INTEGER);
                $stmt->bindValue(':signboardx', $signboard->x, SQLITE3_INTEGER);
                $stmt->bindValue(':signboardy', $signboard->y, SQLITE3_INTEGER);
                $stmt->bindValue(':signboardz', $signboard->z, SQLITE3_INTEGER);
                $stmt->bindValue(':itemid', $data[1], SQLITE3_INTEGER);
                $stmt->bindValue(':itemmeta', $data[2], SQLITE3_INTEGER);
                $stmt->bindValue(':price', $data[3], SQLITE3_INTEGER);
                $stmt->bindValue(':maxcount', $data[0], SQLITE3_INTEGER);
                $stmt->bindValue(':levelname', $signboard->getLevel()->getName(), SQLITE3_TEXT);
                $stmt->execute();
                $item = Item::get((int)$data[1], (int)$data[2], 0);
                $sign->setText("shop", "shop主: " . $player->getName(), "販売しているItem: " . $item->getName(), "お値段: " . $data[3] . "円");
                $player->sendMessage("shopを作成しました！");
                $task = new ShopMessageTask($player->getName() . "がワールド: " . $signboard->getLevel()->getName() . "\nX座標: " . $chest->x . "\nY座標: " . $chest->y . "\nZ座標: " . $chest->z . "\nにShopを作成しました！\n販売物: " . $item->getName() . "\n値段: " . $data[3] . "円");

                Server::getInstance()->getAsyncPool()->submitTask($task);
            }
        });
        $form->setTitle("shop作成"); //This sets the title of the form
        $form->addSlider("販売するItemの最大購入数", 1, 64); // This adds a Slider, Min 1, Max 100
        $form->addInput("販売するItemのID", "itemid", ""); //This adds a Input, Text already entered
        $form->addInput("販売するItemのMETA", "itemmeta", "0");
        $form->addInput("販売するItemの値段", "price", "1");
        $player->sendForm($form); //This sends it to the player
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
                $name = $player->getName();
                $playerdata = $this->db->query("SELECT * FROM moneys WHERE id = $xuid");
                if ($playerdata !== false) {
                    $arr = $playerdata->fetchArray();
                    $player->sendMessage("あなたの現在の所持金: " . $arr["money"] . "円");
                }
            break;
            case 1:
                $this->AdminShop($player);
                // no break
            case 2:
                # $this->Auction($player);
                $this->land($player);
                break;
        }
        });
        
        $form->setTitle("iPhone");
        $form->addButton("Money");
        $form->addButton("AdminShop");
        $form->addButton("土地");
        # $form->addButton("Auction");
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
                default:
                break;
            }
        });

        $form->setTitle("iPhone-土地");
        $form->addButton("土地購入の開始");
        $form->addButton("土地を購入");
        $form->addButton("土地を売却");
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
        
        $form->setTitle("iPhone");
        $form->setContent("AdminShop");
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

            if (!is_numeric($data[1]) or !is_numeric($data[2])) {
                return true;
            }
            $item = Item::get((int)$data[1], (int)$data[2], (int)$data[3]);
            if (!$item) {
                return true;
            }
            $sql = $this->db->prepare("SELECT * FROM adminshops WHERE itemid = :itemid AND itemmeta = :itemmeya");
            $itemid = $item->getId();
            $itemmeta = $item->getDamage();
            $sql->bindValue(':itemid', $itemid, SQLITE3_INTEGER);
            $sql->bindValue(':itemmeta', $itemmeta, SQLITE3_INTEGER);
            $result = $sql->execute();
            $itemdata = $result->fetchArray();
            if (!$itemdata) {
                $sql = $this->db->prepare("INSERT INTO adminshops VALUES (:id, :itemid, :itemmeta, :buyprice, :sellprice)");
                $sql->bindValue(':id', "$itemid-$itemmeta", SQLITE3_TEXT);
                $sql->bindValue(':itemid', $item->getId(), SQLITE3_INTEGER);
                $sql->bindValue(':itemmeta', $item->getDamage(), SQLITE3_INTEGER);
                $sql->bindValue(':buyprice', $data[3], SQLITE3_INTEGER);
                $sql->bindValue(':sellprice', $data[4], SQLITE3_INTEGER);
                $result = $sql->execute();
            } else {
                $sql = $this->db->prepare("UPDATE adminshops SET buyprice = :buyprice, sellprice = :sellprice WHERE itemid = :itemid AND itemmeta = :itemmeta");
                $sql->bindValue(':itemid', $item->getId(), SQLITE3_INTEGER);
                $sql->bindValue(':itemmeta', $item->getDamage(), SQLITE3_INTEGER);
                $sql->bindValue(':buyprice', $data[3], SQLITE3_INTEGER);
                $sql->bindValue(':sellprice', $data[4], SQLITE3_INTEGER);
                $result = $sql->execute();
            }

            $player->sendMessage("設定しました");
            $task = new ShopMessageTask($player->getName() . "が" . $item->getName() . "の値段を" . $data[3] . "円に 売却値段を" . $data[4] . "円に設定しました");
            Server::getInstance()->getAsyncPool()->submitTask($task);
        });

        $form->setTitle("iPhone");
        $form->addLabel("AdminShop 値段設定");
        $form->addInput("値段設定するItemID", "itemid", "");
        $form->addInput("値段設定するItemのMETA", "itemmeta", "0");
        $form->addInput("値段", "buyprice", "1");
        $form->addInput("売却値段", "sellprice", "1");
        $player->sendForm($form);
    }

    // AdminShop処理機構
    private function AdminShopMenu(Player $player)
    {
        $alldata = [];
        $db = $this->db->prepare("SELECT * FROM adminshops");
        $result = $db->execute();
        while ($d = $result->fetchArray(SQLITE3_ASSOC)) {
            $alldata[] = $d;
        }

        $form = new SimpleForm(function (Player $player, $data) use ($alldata) {
            if ($data === null) {
                return;
            }

            $itemdata = $alldata[$data];

            $this->SelectAdminShopMenu($player, $itemdata);
        });

        $form->setTitle("iPhone");
        $form->setContent("メニュー");
        
        for ($i = 0; $i < count($alldata); $i++) {
            $item = Item::get($alldata[$i]["itemid"], $alldata[$i]["itemmeta"], 1);
            $form->addButton($item->getName() . ": " . $alldata[$i]["buyprice"] . "円 売却値段: " . $alldata[$i]["sellprice"] . "円", 0, "textures/items/gold_ingot");
        }

        $player->sendForm($form);
    }

    private function SelectAdminShopMenu(Player $player, $itemdata)
    {
        $form = new CustomForm(function (Player $player, $data) use ($itemdata) {
            if ($data === null) {
                return;
            }

            if (!is_numeric($data[2])) {
                return;
            }

            $item = Item::get($itemdata["itemid"], $itemdata["itemmeta"], (int)$data[2]);
            if (!$item) {
                return;
            }

            if ($data[1] === 0) {
                if ($player->getInventory()->canAddItem($item)) {
                    $this->AdminShopBuykakunin($player, $item, $itemdata);
                } else {
                    $player->sendMessage("インベントリの空き容量が足りません！空き容量を増やしてきてから出直してきてください＾＾");
                }
            } elseif ($data[1] === 1) {
                if ($player->getInventory()->contains($item)) {
                    $this->AdminShopSellkakunin($player, $item, $itemdata);
                } else {
                    $player->sendMessage("自分の所持しているアイテム以上のアイテムを売却することはできません＾＾；");
                }
            }
        });

        $form->setTitle("iPhone");
        $form->addLabel("AdminShop 購入・売却");
        $form->addDropdown("購入・売却", ["購入", "売却"]);
        $form->addSlider("購入・売却する個数", 1, 64);
        $player->sendForm($form);
    }

    private function AdminShopBuykakunin(Player $player, $item, $itemdata)
    {
        $price = $item->getCount() * $itemdata["buyprice"];

        $form = new ModalForm(function (Player $player, $data) use ($item, $price) {
            if ($data === true) {
                $playerxuid = $player->getXuid();
                $result = $this->db->query("SELECT * FROM moneys WHERE id = $playerxuid");
                $playermoney = $result->fetchArray()["money"];
                if ($price > $playermoney) {
                    $player->sendMessage("お金が" . ($playermoney - $price) * -1 . "円足りていません！\n出直してきてください＾＾");
                    return;
                }

                $playermoney -= $price;

                $this->db->query("UPDATE moneys SET money = $playermoney WHERE id = $playerxuid");
                $player->getInventory()->addItem($item);
                $player->sendMessage("購入しました！");
                $task = new ShopMessageTask($player->getName() . "がAdminShopで" . $item->getName() . "を" . $item->getCount() . "個購入しました");
                Server::getInstance()->getAsyncPool()->submitTask($task);
            } elseif ($data === false) {
                $player->sendMessage("購入しませんでした！");
            }
            return;
        });

        $form->setTitle("iPhone");
        $form->setContent("AdminShop 購入最終確認\n" . $item->getName() . "を" . $item->getCount() . "個購入しますか？\n" . $price . "円です");
        $form->setButton1("購入する");
        $form->setButton2("やめる");
        $player->sendForm($form);
    }

    private function AdminShopSellkakunin(Player $player, $item, $itemdata)
    {
        $price = $item->getCount() * $itemdata["sellprice"];

        $form = new ModalForm(function (Player $player, $data) use ($item, $price) {
            if ($data === true) {
                $playerxuid = $player->getXuid();
                $result = $this->db->query("SELECT * FROM moneys WHERE id = $playerxuid");
                $playermoney = $result->fetchArray()["money"];

                $playermoney += $price;

                $this->db->query("UPDATE moneys SET money = $playermoney WHERE id = $playerxuid");
                $player->getInventory()->removeItem($item);
                $player->sendMessage("売却しました！");
                $task = new ShopMessageTask($player->getName() . "がAdminShopで" . $item->getName() . "を" . $item->getCount() . "個売却ました");
                Server::getInstance()->getAsyncPool()->submitTask($task);
            } elseif ($data === false) {
                $player->sendMessage("売却しませんでした！");
            }
            return;
        });

        $form->setTitle("iPhone");
        $form->setContent("AdminShop 売却最終確認\n" . $item->getName() . "を" . $item->getCount() . "個売却しますか？\n");
        $form->setButton1("売却する");
        $form->setButton2("やめる");
        $player->sendForm($form);
    }

    // オークション処理機構 複雑なので後回し

    private function Auction(Player $player)
    {
        $form = new SimpleForm(function (Player $player, $data) {
            if ($data === null) {
                return true;
            }
            switch ($data) {
            case 1:
                $this->CreateAuction($player);
                break;
            case 2:
                $name = $player->getName();
                $task = new AuctionMessageTask("$name がオークションに入札しました");
                Server::getInstance()->getAsyncPool()->submitTask($task);

            break;
        }
        });
        
        $form->setTitle("iPhone");
        $form->addButton("現在開催中のオークション");
        $form->addButton("出品");
        $form->addButton("入札");
        $player->sendForm($form);
    }

    private function CreateAuction(Player $player)
    {
        $form = new CustomForm(function (Player $player, $data) {
            if ($data === null) {
                return true;
            }

            if (!is_numeric($data[0]) or !is_numeric($data[1]) or !is_numeric($data[2]) or !is_numeric($data[3])) {
                return true;
            }
            $item = Item::get((int)$data[0], (int)$data[1], (int)$data[2]);
            if (!$player->getInventory()->contains($item)) {
                $player->sendMessage("アイテムが足りません！");
            }

            $this->ListingAuction($player, $item, (int)$data[3]);
        });

        $form->setTitle("オークション出品"); //This sets the title of the form
        $form->addInput("出品するItemID", "itemid", ""); //This adds a Input, Text already entered
        $form->addInput("出品するItemのMETA", "itemmeta", "0");
        $form->addInput("出品する個数", "itemcount", "1");
        $form->addInput("終了までの時間 分", "minutes", "1");
        $player->sendForm($form); //This sends it to the player
    }

    private function ListingAuction(Player $player, $item, $minutes)
    {
        $form = new SimpleForm(function (Player $player, $data) use ($item, $minutes) {
            if ($data === null) {
                return true;
            }
            switch ($data) {
            case 0:
                $xuid = $player->getXuid();
                $stmt = $this->db->prepare("INSERT INTO auctions VALUES (:id, :sellerxuid, :itemid, :itemmeta, :itemcount, :buyerxuid, :price");
                $stmt->bindValue(":id", $xuid, SQLITE3_TEXT);
                $stmt->bindValue(":sellerxuid", $xuid, SQLITE3_TEXT);
                $stmt->bindValue(":itemid", $item->getId(), SQLITE3_INTEGER);
                $stmt->bindValue(":itemmeta", $item->getDamage(), SQLITE3_INTEGER);
                $stmt->bindValue(":itemcount", $item->getCount(), SQLITE3_INTEGER);
                $stmt->bindValue(":buyerxuid", "なし", SQLITE3_TEXT);
                $stmt->bindValue(":price", 0, SQLITE3_INTEGER);
                $stmt->execute();
                $this->getScheduler()->scheduleDelayedTask(new AuctionTask($this, $xuid), 20 * ($minutes * 60));

            break;
        }
        });
        
        $form->setTitle("オークション出品最終確認");
        $form->setContent($item->getName() . "を" . $item->getCount() . "出品してもよろしいですか？ 取り消すことはできません");
        $form->addButton("出品する");
        $form->addButton("やめる");

        $player->sendForm($form);
    }

    private function buyland(Player $player)
    {
        $pos1 = new Vector3($this->startlands[$player->getXuid()]->x, $this->startlands[$player->getXuid()]->y, $this->startlands[$player->getXuid()]->z);
        $pos2 = new Vector3($this->endlands[$player->getXuid()]->x, $this->endlands[$player->getXuid()]->y, $this->endlands[$player->getXuid()]->z);
        $sql = $this->db->prepare("INSERT INTO lands VALUES (:id, :ownerxuid, :levelname, :startx, :startz, :endx, :endz)");
        $sql->bindValue(':id', $player->getXuid() . "-" . $this->startlands[$player->getXuid()]->x . "-" . $this->startlands[$player->getXuid()]->z . "-" . $this->endlands[$player->getXuid()]->x . "-" . $this->endlands[$player->getXuid()]->z, SQLITE3_TEXT);
        $sql->bindValue(':ownerxuid', $player->getXuid(), SQLITE3_INTEGER);
        $sql->bindValue(':levelname', $player->getLevel()->getName(), SQLITE3_TEXT);
        $sql->bindValue(':startx', $pos1->x, SQLITE3_INTEGER);
        $sql->bindValue(':startz', $pos1->z, SQLITE3_INTEGER);
        $sql->bindValue(':endx', $pos2->x, SQLITE3_INTEGER);
        $sql->bindValue(':endz', $pos2->z, SQLITE3_INTEGER);
        $result = $sql->execute();
        unset($this->startlands[$player->getXuid()]);
        unset($this->endlands[$player->getXuid()]);
    }
}
