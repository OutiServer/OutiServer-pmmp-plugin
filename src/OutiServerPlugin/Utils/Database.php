<?php

declare(strict_types=1);

namespace OutiServerPlugin\Utils;

use ArgumentCountError;
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use OutiServerPlugin\Main;
use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use SQLite3;
use SQLiteException;
use TypeError;

class Database
{
    public SQLite3 $db;
    private Main $plugin;

    public function __construct(Main $plugin, string $dir, array $DefaultItemCategory)
    {
        $this->plugin = $plugin;

        try {
            $this->db = new SQLite3($dir);
            $this->db->exec("CREATE TABLE IF NOT EXISTS moneys (name TEXT PRIMARY KEY, money INTEGER)");
            $this->db->exec("CREATE TABLE IF NOT EXISTS chestshops (id INTEGER PRIMARY KEY AUTOINCREMENT, owner TEXT, chestx INTEGER, chesty INTEGER, chestz INTEGER, signboardx INTEGER, signboardy INTEGER, signboardz INTEGER, itemid INTEGER, itemmeta INTEGER, price INTEGER, maxcount INTEGER, levelname TEXT)");
            $this->db->exec("CREATE TABLE IF NOT EXISTS adminshops (id TEXT PRIMARY KEY, itemid INTEGER, itemmeta INTEGER, buyprice INTEGER, sellprice INTEGER, categoryid INTEGER, mode INTEGER)");
            $this->db->exec("CREATE TABLE IF NOT EXISTS lands (id INTEGER PRIMARY KEY AUTOINCREMENT, owner TEXT, levelname TEXT, startx INTEGER, startz INTEGER, endx INTEGER, endz INTEGER, invites TEXT, protection INTEGER, tpx INTEGER, tpy INTEGER, tpz INTEGER)");
            $this->db->exec("CREATE TABLE IF NOT EXISTS itemcategorys (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)");
            $this->db->exec("CREATE TABLE IF NOT EXISTS worldteleports (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, levelname TEXT, x INTEGER, y INTEGER, z INTEGER, oponly INTEGER)");
            $this->db->exec("CREATE TABLE IF NOT EXISTS adminannounces (id INTEGER PRIMARY KEY AUTOINCREMENT, addtime TEXT, title TEXT, content TEXT)");
            $this->db->exec("CREATE TABLE IF NOT EXISTS casinoslots (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, bet INTEGER , rate INTEGER, line INTEGER, levelname TEXT, x INTEGER, y INTEGER, z INTEGER)");
            $this->db->exec("CREATE TABLE IF NOT EXISTS casinoslotsettings (levelname TEXT PRIMARY KEY, jp INTEGER, highjp INTEGER, highplayer TEXT, lastjp INTEGER, lastplayer TEXT, x INTEGER, y INTEGER, z INTEGER)");
            if (!strpos($this->db->prepare("SELECT * FROM sqlite_master WHERE type = 'table' AND name = 'lands'")->execute()->fetchArray()["sql"], "tpx")) {
                $this->db->exec("ALTER TABLE lands ADD COLUMN tpx INTEGER");
            }
            if (!strpos($this->db->prepare("SELECT * FROM sqlite_master WHERE type = 'table' AND name = 'lands'")->execute()->fetchArray()["sql"], "tpy")) {
                $this->db->exec("ALTER TABLE lands ADD COLUMN tpy INTEGER");
            }
            if (!strpos($this->db->prepare("SELECT * FROM sqlite_master WHERE type = 'table' AND name = 'lands'")->execute()->fetchArray()["sql"], "tpz")) {
                $this->db->exec("ALTER TABLE lands ADD COLUMN tpz INTEGER");
            }

            foreach ($DefaultItemCategory as $key) {
                $sql = $this->db->prepare("SELECT * FROM itemcategorys WHERE name = :name");
                $sql->bindValue(':name', $key, SQLITE3_TEXT);
                $result = $sql->execute();
                if (!$result->fetchArray()) {
                    $sql = $this->db->prepare("INSERT INTO itemcategorys (name) VALUES (:name)");
                    $sql->bindValue(':name', $key, SQLITE3_TEXT);
                    $sql->execute();
                }
            }
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    // db接続を閉じる
    public function close()
    {
        try {
            $this->db->close();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    // プレイヤー所持金設定
    public function SetMoney(string $name)
    {
        try {
            $sql = $this->db->prepare("INSERT INTO moneys VALUES (:name, :money)");
            $sql->bindValue(':name', strtolower($name), SQLITE3_TEXT);
            $sql->bindValue(':money', $this->plugin->config->get('Default_Money', 1000), SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    // プレイヤー所持金取得
    public function GetMoney(string $name)
    {
        try {
            $sql = $this->db->prepare("SELECT * FROM moneys WHERE name = :name");
            $sql->bindValue(':name', strtolower($name), SQLITE3_TEXT);
            $result = $sql->execute();
            $data = $result->fetchArray();
            if (!$data) {
                $this->SetMoney($name);
                $sql = $this->db->prepare("SELECT * FROM moneys WHERE name = :name");
                $sql->bindValue(':name', strtolower($name), SQLITE3_TEXT);
                $result = $sql->execute();
                $data = $result->fetchArray();
            }
            return $data;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    // プレイヤー所持金追加
    public function AddMoney(string $name, int $addmoney)
    {
        try {
            $oldmoney = $this->GetMoney($name);
            if (!$oldmoney) {
                $this->SetMoney($name);
                $oldmoney = $this->GetMoney($name);
            }
            $sql = $this->db->prepare("UPDATE moneys SET money = :money WHERE name = :name");
            $sql->bindValue(':money', ($oldmoney["money"] + $addmoney), SQLITE3_INTEGER);
            $sql->bindValue(':name', strtolower($name), SQLITE3_TEXT);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    // プレイヤー所持金剥奪
    public function RemoveMoney(string $name, int $removemoney)
    {
        try {
            $oldmoney = $this->GetMoney($name);
            if (!$oldmoney) {
                $this->SetMoney($name);
                $oldmoney = $this->GetMoney($name);
            }
            $sql = $this->db->prepare("UPDATE moneys SET money = :money WHERE name = :name");
            $sql->bindValue(':money', ($oldmoney["money"] - $removemoney), SQLITE3_INTEGER);
            $sql->bindValue(':name', strtolower($name), SQLITE3_TEXT);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    // プレイヤー所持金更新
    public function UpdateMoney(string $name, int $money)
    {
        try {
            $data = $this->GetMoney($name);
            if (!$data) {
                $sql = $this->db->prepare("INSERT INTO moneys VALUES (:name, :money)");
            } else {
                $sql = $this->db->prepare("UPDATE moneys SET money = :money WHERE name = :name");
            }

            $sql->bindValue(':name', strtolower($name), SQLITE3_TEXT);
            $sql->bindValue(':money', $money, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    // チェストショップ設定
    public function SetChestShop(string $name, $chest, $signboard, $item, $price)
    {
        try {
            $sql = $this->db->prepare('INSERT INTO chestshops (owner, chestx, chesty, chestz, signboardx, signboardy, signboardz, itemid, itemmeta, price, maxcount, levelname) VALUES (:owner, :chestx, :chesty, :chestz, :signboardx, :signboardy, :signboardz, :itemid, :itemmeta, :price, :maxcount, :levelname)');
            $sql->bindValue(':owner', strtolower($name), SQLITE3_TEXT);
            $sql->bindValue(':chestx', $chest->x, SQLITE3_INTEGER);
            $sql->bindValue(':chesty', $chest->y, SQLITE3_INTEGER);
            $sql->bindValue(':chestz', $chest->z, SQLITE3_INTEGER);
            $sql->bindValue(':signboardx', $signboard->x, SQLITE3_INTEGER);
            $sql->bindValue(':signboardy', $signboard->y, SQLITE3_INTEGER);
            $sql->bindValue(':signboardz', $signboard->z, SQLITE3_INTEGER);
            $sql->bindValue(':itemid', $item->getId(), SQLITE3_INTEGER);
            $sql->bindValue(':itemmeta', $item->getDamage(), SQLITE3_INTEGER);
            $sql->bindValue(':price', $price, SQLITE3_INTEGER);
            $sql->bindValue(':maxcount', $item->getCount(), SQLITE3_INTEGER);
            $sql->bindValue(':levelname', $signboard->getLevel()->getName(), SQLITE3_TEXT);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    // チェストショップのチェスト存在確認
    public function isChestShopChestExits(Block $block, $levelname): bool
    {
        try {
            $data = $this->GetChestShop($block, $levelname);
            if ($data["chestx"] === $block->x and $data["chesty"] === $block->y and $data["chestz"] === $block->z) return true;
            else return false;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    // チェストショップのオーナーか確認
    public function CheckChestShopOwner(int $id, string $name): bool
    {
        $data = $this->GetChestShopId($id);
        return $data["owner"] === strtolower($name);
    }

    // チェストショップ取得
    public function GetChestShop($block, $levelname)
    {
        try {
            $sql = $this->db->prepare("SELECT * FROM chestshops WHERE ((signboardx = :x AND signboardy = :y AND signboardz = :z) OR (chestx = :x AND chesty = :y AND chestz = :z)) AND levelname = :levelname");
            $sql->bindValue(':x', $block->x, SQLITE3_INTEGER);
            $sql->bindValue(':y', $block->y, SQLITE3_INTEGER);
            $sql->bindValue(':z', $block->z, SQLITE3_INTEGER);
            $sql->bindValue(':levelname', $levelname, SQLITE3_TEXT);
            $result = $sql->execute();
            $data = $result->fetchArray();
            if (!$data) return false;
            return $data;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    // チェストショップID取得
    public function GetChestShopId(int $id)
    {
        try {
            $sql = $this->db->prepare("SELECT * FROM chestshops WHERE id = :id");
            $sql->bindValue(':id', $id, SQLITE3_INTEGER);
            $result = $sql->execute();
            $data = $result->fetchArray();
            if (!$data) return false;
            return $data;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    // チェストショップ削除
    public function DeleteChestShop($shopdata)
    {
        try {
            $sql = $this->db->prepare("DELETE FROM chestshops WHERE signboardx = :x AND signboardy = :y AND signboardz = :z AND levelname = :levelname");
            $sql->bindValue(':x', $shopdata["signboardx"], SQLITE3_INTEGER);
            $sql->bindValue(':y', $shopdata["signboardy"], SQLITE3_INTEGER);
            $sql->bindValue(':z', $shopdata["signboardz"], SQLITE3_INTEGER);
            $sql->bindValue(':levelname', $shopdata["levelname"], SQLITE3_TEXT);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    // AdminShop設定
    public function SetAdminShop(Item $item, int $buy, int $sell, int $categoryid, int $mode)
    {
        try {
            $sql = $this->db->prepare("INSERT INTO adminshops VALUES (:id, :itemid, :itemmeta, :buyprice, :sellprice, :categoryid, :mode)");
            $sql->bindValue(':id', $item->getId() . "-" . $item->getDamage(), SQLITE3_TEXT);
            $sql->bindValue(':itemid', $item->getId(), SQLITE3_INTEGER);
            $sql->bindValue(':itemmeta', $item->getDamage(), SQLITE3_INTEGER);
            $sql->bindValue(':buyprice', $buy, SQLITE3_INTEGER);
            $sql->bindValue(':sellprice', $sell, SQLITE3_INTEGER);
            $sql->bindValue(":categoryid", $categoryid, SQLITE3_INTEGER);
            $sql->bindValue(":mode", $mode, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    // AdminShop設定更新
    public function UpdateAdminShop(Item $item, int $buy, int $sell, int $categoryid, int $mode)
    {
        try {
            $sql = $this->db->prepare("UPDATE adminshops SET buyprice = :buyprice, sellprice = :sellprice, categoryid = :categoryid, mode = :mode WHERE itemid = :itemid AND itemmeta = :itemmeta");
            $sql->bindValue(':itemid', $item->getId(), SQLITE3_INTEGER);
            $sql->bindValue(':itemmeta', $item->getDamage(), SQLITE3_INTEGER);
            $sql->bindValue(':buyprice', $buy, SQLITE3_INTEGER);
            $sql->bindValue(':sellprice', $sell, SQLITE3_INTEGER);
            $sql->bindValue(":categoryid", $categoryid, SQLITE3_INTEGER);
            $sql->bindValue(":mode", $mode, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    // AdminShop取得
    public function GetAdminShop($item)
    {
        try {
            $sql = $this->db->prepare("SELECT * FROM adminshops WHERE itemid = :itemid AND itemmeta = :itemmeta");
            $sql->bindValue(':itemid', $item->getId(), SQLITE3_INTEGER);
            $sql->bindValue(':itemmeta', $item->getDamage(), SQLITE3_INTEGER);
            $result = $sql->execute();
            $data = $result->fetchArray();
            if (!$data) {
                return false;
            }
            return $data;
        } catch (SQLiteException | Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    // AdminShopに登録されているItem全取得
    public function AllAdminShop(int $CategoryId)
    {
        try {
            $alldata = [];
            $sql = $this->db->prepare("SELECT * FROM adminshops WHERE categoryid = :id");
            $sql->bindValue(":id", $CategoryId, SQLITE3_INTEGER);
            $result = $sql->execute();
            while ($d = $result->fetchArray(SQLITE3_ASSOC)) {
                $alldata[] = $d;
            }

            if (count($alldata) < 1) return false;

            return $alldata;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    // 土地保護設定
    public function SetLand(string $owner, string $levelname, int $startx, int $startz, int $endx, int $endz, int $protection, ?Vector3 $vector3)
    {
        try {
            $sql = $this->db->prepare("INSERT INTO lands (owner, levelname, startx, startz, endx, endz, invites, protection, tpx, tpy, tpz) VALUES (:owner, :levelname, :startx, :startz, :endx, :endz, :invites, :protection, :tpx, :tpy, :tpz)");
            $sql->bindValue(':owner', strtolower($owner), SQLITE3_TEXT);
            $sql->bindValue(':levelname', $levelname, SQLITE3_TEXT);
            $sql->bindValue(':startx', $startx, SQLITE3_INTEGER);
            $sql->bindValue(':startz', $startz, SQLITE3_INTEGER);
            $sql->bindValue(':endx', $endx, SQLITE3_INTEGER);
            $sql->bindValue(':endz', $endz, SQLITE3_INTEGER);
            $sql->bindValue(':invites', serialize(array()), SQLITE3_TEXT);
            $sql->bindValue(':protection', $protection, SQLITE3_INTEGER);
            if (!$vector3) {
                $sql->bindValue(':tpx', null, SQLITE3_NULL);
                $sql->bindValue(':tpy', null, SQLITE3_NULL);
                $sql->bindValue(':tpz', null, SQLITE3_NULL);
            } else {
                $sql->bindValue(':tpx', (int)$vector3->x, SQLITE3_INTEGER);
                $sql->bindValue(':tpy', (int)$vector3->y, SQLITE3_INTEGER);
                $sql->bindValue(':tpz', (int)$vector3->z, SQLITE3_INTEGER);
            }

            $sql->execute();
            return $this->db->query("SELECT seq FROM sqlite_sequence WHERE name = 'lands'")->fetchArray()["seq"];
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    // ID取得
    public function GetLandId(string $levelname, int $x, int $z)
    {
        try {
            $sql = $this->db->prepare("SELECT * FROM lands WHERE levelname = :levelname AND endx >= :x AND endz >= :z AND startx <= :x AND startz <= :z");
            $sql->bindValue(':levelname', $levelname, SQLITE3_TEXT);
            $sql->bindValue(':x', $x, SQLITE3_INTEGER);
            $sql->bindValue(':z', $z, SQLITE3_INTEGER);
            $result = $sql->execute();
            $data = $result->fetchArray(SQLITE3_ASSOC);
            if (!$data) {
                return false;
            }
            return (int)$data["id"];
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function GetLandData(int $id)
    {
        try {
            $sql = $this->db->prepare("SELECT * FROM lands WHERE id = :id");
            $sql->bindValue(":id", $id, SQLITE3_INTEGER);
            $result = $sql->execute();
            $data = $result->fetchArray();
            if (!$data) {
                return false;
            }
            return $data;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    // 土地データ削除
    public function DeleteLand(int $id)
    {
        try {
            $sql = $this->db->prepare("DELETE FROM lands WHERE id = :id");
            $sql->bindValue(':id', $id, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    // nameが所有している土地ID全取得
    public function GetAllLandOwnerData(string $name)
    {
        try {
            $alldata = [];
            $sql = $this->db->prepare("SELECT * FROM lands WHERE owner = :owner");
            $sql->bindValue(":owner", strtolower($name), SQLITE3_TEXT);
            $result = $sql->execute();
            while ($d = $result->fetchArray(SQLITE3_ASSOC)) {
                $alldata[] = "{$d["id"]}";
            }

            if (count($alldata) < 1) return false;

            return $alldata;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function GetAllLandOwnerInviteData(string $name)
    {
        try {
            $alldata = [];
            $sql = $this->db->prepare("SELECT * FROM lands WHERE owner = :owner");
            $sql->bindValue(":owner", strtolower($name), SQLITE3_TEXT);
            $result = $sql->execute();
            while ($d = $result->fetchArray(SQLITE3_ASSOC)) {
                if (!$d["tpx"]) continue;
                $alldata[] = "{$d["id"]}";
            }

            $lands = $this->GetAllLand();
            if(!$lands) return false;
            foreach ($lands as $data) {
                if (!$this->checkInvite($data["id"], $name) or !$data["tpx"]) continue;

                $alldata[] = "{$data["id"]}";
            }

            if (count($alldata) < 1) return false;

            return $alldata;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    // 全ての土地取得
    public function GetAllLand()
    {
        try {
            $alldata = [];
            $sql = $this->db->prepare("SELECT * FROM lands");
            $result = $sql->execute();
            while ($d = $result->fetchArray(SQLITE3_ASSOC)) {
                $alldata[] = $d;
            }

            if (count($alldata) < 1) return false;

            return $alldata;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    // 全ての土地ID取得
    public function GetAllLandId()
    {
        try {
            $alldata = [];
            $lands = $this->GetAllLand();
            if(!$lands) return false;
            foreach ($lands as $data) {
                $alldata[] = "{$data["id"]}";
            }

            if (count($alldata) < 1) return false;

            return $alldata;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function UpdateLandProtection(int $id, int $protection)
    {
        try {
            $sql = $this->db->prepare("UPDATE lands SET protection = :protection WHERE id = :id");
            $sql->bindValue(':protection', $protection, SQLITE3_INTEGER);
            $sql->bindValue(':id', $id, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function UpdateLandTereport(int $id, Vector3 $vector3)
    {
        try {
            $sql = $this->db->prepare("UPDATE lands SET tpx = :x, tpy = :y, tpz = :z WHERE id = :id");
            $sql->bindValue(':x', (int)$vector3->x, SQLITE3_INTEGER);
            $sql->bindValue(':y', (int)$vector3->y, SQLITE3_INTEGER);
            $sql->bindValue(':z', (int)$vector3->z, SQLITE3_INTEGER);
            $sql->bindValue(':id', $id, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function AddLandInvite(int $id, string $invitename)
    {
        try {
            $invites = $this->GetLandInvites($id);
            if (!in_array($invitename, $invites)) {
                $invites[] = strtolower(str_replace("'", "", $invitename));
                $sql = $this->db->prepare("UPDATE lands SET invites = :invites WHERE id = :id");
                $sql->bindValue(":invites", serialize($invites), SQLITE3_TEXT);
                $sql->bindValue(":id", $id, SQLITE3_INTEGER);
                $sql->execute();
            }
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function GetLandInvites(int $id)
    {
        try {
            $sql = $this->db->prepare("SELECT * FROM lands WHERE id = :id");
            $sql->bindValue(":id", $id, SQLITE3_INTEGER);
            $result = $sql->execute();
            $data = $result->fetchArray(SQLITE3_ASSOC);
            if (!$data) {
                return false;
            }

            return unserialize($data["invites"]);
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function checkInvite(int $id, string $name): bool
    {
        try {
            $invites = $this->GetLandInvites($id);
            $invitename = strtolower($name);
            if (!$invites) return false;
            elseif (!in_array($invitename, $invites)) return false;
            return true;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function RemoveLandInvite(int $id, string $name): bool
    {
        try {
            $invites = $this->GetLandInvites($id);
            $invitename = strtolower($name);
            if (!in_array($invitename, $invites)) return false;

            foreach ($invites as $key => $i) {
                if ($i === $invitename) {
                    unset($invites[$key]);
                    $sql = $this->db->prepare("UPDATE lands SET invites = :invites WHERE id = :id");
                    $sql->bindValue(":invites", serialize($invites), SQLITE3_TEXT);
                    $sql->bindValue(":id", $id, SQLITE3_INTEGER);
                    $sql->execute();
                    return true;
                }
            }

            return false;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function ChangeLandOwner(int $id, string $name)
    {
        try {
            $sql = $this->db->prepare("UPDATE lands SET owner = :owner WHERE id = :id");
            $sql->bindValue(":owner", strtolower($name), SQLITE3_TEXT);
            $sql->bindValue(":id", $id, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function CheckLandOwner(int $id, string $name): bool
    {
        try {
            $ownername = strtolower($name);
            $landdata = $this->GetLandData($id);
            if (!$landdata) return false;
            return $landdata["owner"] === $ownername;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function CheckLandProtection(int $id): ?bool
    {
        try {
            $landdata = $this->GetLandData($id);
            if (!$landdata) return null;
            elseif ($landdata["protection"] === 1) return true;
            else return false;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function GetAllItemCategoryAll()
    {
        try {
            $alldata = [];
            $sql = $this->db->prepare("SELECT * FROM itemcategorys");
            $result = $sql->execute();
            while ($d = $result->fetchArray(SQLITE3_ASSOC)) {
                $alldata[] = $d;
            }

            if (count($alldata) < 1) return false;

            return $alldata;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function GetAllItemCategory()
    {
        try {
            $alldata = [];
            $sql = $this->db->prepare("SELECT * FROM itemcategorys");
            $result = $sql->execute();
            while ($d = $result->fetchArray(SQLITE3_ASSOC)) {
                $alldata[] = $d;
            }

            if (count($alldata) < 1) return false;

            return $alldata;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function GetAllItemCategoryShop()
    {
        try {
            $alldata = [];
            $sql = $this->db->prepare("SELECT * FROM itemcategorys");
            $result = $sql->execute();
            while ($d = $result->fetchArray(SQLITE3_ASSOC)) {
                if (!$this->AllAdminShop($d["id"])) continue;
                $alldata[] = $d;
            }

            if (count($alldata) < 1) return false;

            return $alldata;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function AddItemCategory(string $name)
    {
        try {
            $sql = $this->db->prepare("INSERT INTO itemcategorys (name) VALUES (:name)");
            $sql->bindValue(':name', $name, SQLITE3_TEXT);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function RemoveItemCategory(int $id)
    {
        try {
            $sql = $this->db->prepare("DELETE FROM itemcategorys WHERE id = :id");
            $sql->bindValue(':id', $id, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function DeleteAdminShopItem(Item $item)
    {
        try {
            $sql = $this->db->prepare("DELETE FROM adminshops WHERE itemid = :itemid AND itemmeta = :itemmeta");
            $sql->bindValue(':itemid', $item->getId(), SQLITE3_INTEGER);
            $sql->bindValue(':itemmeta', $item->getDamage(), SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function SetWorldTeleport(string $name, Position $position, int $oponly)
    {
        try {
            $sql = $this->db->prepare("INSERT INTO worldteleports (name, levelname, x, y, z, oponly) VALUES (:name, :levelname, :x, :y, :z, :oponly)");
            $sql->bindValue(':name', $name, SQLITE3_TEXT);
            $sql->bindValue(':levelname', $position->getLevel()->getName(), SQLITE3_TEXT);
            $sql->bindValue(':x', (int)$position->x, SQLITE3_INTEGER);
            $sql->bindValue(':y', (int)$position->y, SQLITE3_INTEGER);
            $sql->bindValue(':z', (int)$position->z, SQLITE3_INTEGER);
            $sql->bindValue(":oponly", $oponly, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function DeleteWorldTeleport(int $id)
    {
        try {
            $sql = $this->db->prepare("DELETE FROM worldteleports WHERE id = :id");
            $sql->bindValue(':id', $id, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function GetAllWorldTeleport()
    {
        try {
            $alldata = [];
            $sql = $this->db->prepare("SELECT * FROM worldteleports");
            $result = $sql->execute();
            while ($d = $result->fetchArray(SQLITE3_ASSOC)) {
                $alldata[] = $d;
            }

            if (count($alldata) < 1) return false;

            return $alldata;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function GetWorldTeleport(int $id)
    {
        try {
            $sql = $this->db->prepare("SELECT * FROM worldteleports WHERE id = :id");
            $sql->bindValue(":id", $id, SQLITE3_INTEGER);
            $result = $sql->execute();
            $data = $result->fetchArray();
            if (!$data) return false;
            return $data;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function AddAnnounce(string $addtime, string $title, string $content)
    {
        try {
            $sql = $this->db->prepare("INSERT INTO adminannounces (addtime, title, content) VALUES (:addtime, :title, :content)");
            $sql->bindValue(':addtime', $addtime, SQLITE3_TEXT);
            $sql->bindValue(':title', $title, SQLITE3_TEXT);
            $sql->bindValue(':content', $content, SQLITE3_TEXT);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function DeleteAnnounce(int $id)
    {
        try {
            $sql = $this->db->prepare("DELETE FROM adminannounces WHERE id = :id");
            $sql->bindValue(':id', $id, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function GetAnnounce(int $id)
    {
        try {
            $sql = $this->db->prepare("SELECT * FROM adminannounces WHERE id = :id");
            $sql->bindValue(":id", $id, SQLITE3_INTEGER);
            $result = $sql->execute();
            $data = $result->fetchArray();
            if (!$data) return false;
            return $data;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function GetAllAnnounce()
    {
        try {
            $alldata = [];
            $sql = $this->db->prepare("SELECT * FROM adminannounces");
            $result = $sql->execute();
            while ($d = $result->fetchArray(SQLITE3_ASSOC)) {
                $alldata[] = $d;
            }

            if (count($alldata) < 1) return false;

            return $alldata;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function GetSlotId(Block $pos)
    {
        try {
            $sql = $this->db->prepare("SELECT * FROM casinoslots WHERE levelname = :levelname AND y = :y AND x = :x AND z = :z");
            $sql->bindValue(":levelname", $pos->getLevel()->getName(), SQLITE3_TEXT);
            $sql->bindValue(":x", (int)$pos->x, SQLITE3_INTEGER);
            $sql->bindValue(":y", (int)$pos->y, SQLITE3_INTEGER);
            $sql->bindValue(":z", (int)$pos->z, SQLITE3_INTEGER);
            $result = $sql->execute();
            $data = $result->fetchArray();
            if (!$data) return false;
            return (int)$data["id"];
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function GetSlot(int $id)
    {
        try {
            $sql = $this->db->prepare("SELECT * FROM casinoslots WHERE id = :id");
            $sql->bindValue(":id", $id, SQLITE3_INTEGER);
            $result = $sql->execute();
            $data = $result->fetchArray();
            if (!$data) return false;
            return $data;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function SetSlot(string $name, int $bet, int $rate, int $line, Block $pos)
    {
        try {
            // id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, bet INTEGER , rate INTEGER, line INTEGER, levelname TEXT, x INTEGER, y INTEGER, z INTEGER
            $sql = $this->db->prepare("INSERT INTO casinoslots (name, bet, rate, line, levelname, x, y, z) VALUES (:name, :bet, :rate, :line, :levelname, :x, :y, :z)");
            $sql->bindValue(':name', $name, SQLITE3_TEXT);
            $sql->bindValue('bet', $bet, SQLITE3_INTEGER);
            $sql->bindValue('rate', $rate, SQLITE3_INTEGER);
            $sql->bindValue('line', $line, SQLITE3_INTEGER);
            $sql->bindValue(':levelname', $pos->getLevel()->getName(), SQLITE3_TEXT);
            $sql->bindValue(':x', (int)$pos->x, SQLITE3_INTEGER);
            $sql->bindValue(':y', (int)$pos->y, SQLITE3_INTEGER);
            $sql->bindValue(':z', (int)$pos->z, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function GetAllSlot()
    {
        try {
            $alldata = [];
            $sql = $this->db->prepare("SELECT * FROM casinoslots");
            $result = $sql->execute();
            while ($d = $result->fetchArray(SQLITE3_ASSOC)) {
                $alldata[] = $d;
            }

            if (count($alldata) < 1) return false;

            return $alldata;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function DeleteSlot(int $id)
    {
        try {
            $sql = $this->db->prepare("DELETE FROM casinoslots WHERE id = :id");
            $sql->bindValue(':id', $id, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function AddSlotJP(string $levelname, int $added)
    {
        try {
            $oldsettings = $this->GetSlotSettings($levelname);
            if (!$oldsettings) {
                $this->SetSlotSettings($levelname);
                $oldsettings = $this->GetSlotSettings($levelname);
            }

            $sql = $this->db->prepare("UPDATE casinoslotsettings SET jp = :jp WHERE levelname = :levelname");
            $sql->bindValue(':jp', ($oldsettings["jp"] + $added), SQLITE3_INTEGER);
            $sql->bindValue(':levelname', $levelname, SQLITE3_TEXT);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function GetSlotSettings(string $levelname)
    {
        try {
            $sql = $this->db->prepare("SELECT * FROM casinoslotsettings WHERE levelname = :name");
            $sql->bindValue(":name", $levelname, SQLITE3_TEXT);
            $result = $sql->execute();
            $data = $result->fetchArray();
            if (!$data) return false;
            return $data;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function SetSlotSettings(string $levelname)
    {
        try {
            $sql = $this->db->prepare("INSERT INTO casinoslotsettings (levelname, jp, highjp, highplayer, lastjp, lastplayer, x, y, z) VALUES (:levelname, :jp, :highjp, :highplayer, :lastjp, :lastplayer, :x, :y, :z)");
            $sql->bindValue(':levelname', $levelname, SQLITE3_TEXT);
            $sql->bindValue(':jp', (int)$this->plugin->config->get("Default_Slot_JP", 10000), SQLITE3_INTEGER);
            $sql->bindValue(':highjp', 0, SQLITE3_INTEGER);
            $sql->bindValue(':highplayer', "なし", SQLITE3_TEXT);
            $sql->bindValue(':lastjp', 0, SQLITE3_INTEGER);
            $sql->bindValue(':lastplayer', "なし", SQLITE3_TEXT);
            $sql->bindValue(':x', 0, SQLITE3_INTEGER);
            $sql->bindValue(':y', 0, SQLITE3_INTEGER);
            $sql->bindValue(':z', 0, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function AllGetSlotSettings()
    {
        try {
            $alldata = [];
            $sql = $this->db->prepare("SELECT * FROM casinoslotsettings");
            $result = $sql->execute();
            while ($d = $result->fetchArray(SQLITE3_ASSOC)) {
                $alldata[] = $d;
            }

            if (count($alldata) < 1) return false;

            return $alldata;
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

        return false;
    }

    public function UpdateSlotSettingxyz(string $levelname, int $x, int $y, int $z)
    {
        try {
            $sql = $this->db->prepare("UPDATE casinoslotsettings SET levelname = :levelname, x = :x, y = :y, z = :z WHERE levelname = :levelname");
            $sql->bindValue(":levelname", $levelname, SQLITE3_TEXT);
            $sql->bindValue(":x", $x, SQLITE3_INTEGER);
            $sql->bindValue(":y", $y, SQLITE3_INTEGER);
            $sql->bindValue(":z", $z, SQLITE3_INTEGER);
            $sql->execute();
        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }

    public function ResetSlotSettingJP(string $levelname, string $name)
    {
        try {
            $data = $this->GetSlotSettings($levelname);
            if ($data["highjp"] < $data["jp"]) {
                $sql = $this->db->prepare("UPDATE casinoslotsettings SET jp = :setjp, highjp = :jp, highplayer = :player, lastjp = :jp, lastplayer = :player  WHERE levelname = :levelname");
            } else {
                $sql = $this->db->prepare("UPDATE casinoslotsettings SET jp = :setjp, lastjp = :jp, lastplayer = :player  WHERE levelname = :levelname");
            }
            $sql->bindValue(":setjp", (int)$this->plugin->config->get("Default_Slot_JP", 10000), SQLITE3_INTEGER);
            $sql->bindValue(":jp", $data["jp"], SQLITE3_INTEGER);
            $sql->bindValue(":player", $name, SQLITE3_TEXT);
            $sql->bindValue(":levelname", $levelname, SQLITE3_TEXT);
            $sql->execute();

        } catch (SQLiteException | Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
}
