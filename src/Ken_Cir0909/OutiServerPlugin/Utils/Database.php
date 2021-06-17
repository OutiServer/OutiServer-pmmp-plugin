<?php

declare(strict_types=1);

namespace Ken_Cir0909\OutiServerPlugin\Utils;

class Database
{
    public $db;

    public function __construct(string $dir)
    {
        $this->db = new \SQLite3($dir);
        $this->db->exec("DROP TABLE moneys");
        $this->db->exec("DROP TABLE shops");
        $this->db->exec("DROP TABLE adminshops");
        $this->db->exec("DROP TABLE lands");
        $this->db->exec("CREATE TABLE IF NOT EXISTS moneys (xuid TEXT PRIMARY KEY, money INTEGER)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS shops (id TEXT PRIMARY KEY, ownerxuid TEXT, chestx INTEGER, chesty INTEGER, chestz INTEGER, signboardx INTEGER, signboardy INTEGER, signboardz INTEGER, itemid INTEGER, itemmeta INTEGER, price INTEGER, maxcount INTEGER, levelname TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS adminshops (id TEXT PRIMARY KEY, itemid INTEGER, itemmeta INTEGER, buyprice INTEGER, sellprice INTEGER)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS lands (id TEXT PRIMARY KEY, ownerxuid INTEGER, levelname TEXT, startx INTEGER, startz INTEGER, endx INTEGER, endz INTEGER)");
    }

    public function close()
    {
        $this->db->close();
    }

    // プレイヤー所持金設定
    public function SetMoney(string $xuid)
    {
        $sql = $this->db->prepare("INSERT INTO moneys VALUES (:xuid, 1000)");
        $sql->bindValue(':xuid', $xuid, SQLITE3_TEXT);
        $sql->execute();
        return;
    }

    // プレイヤー所持金取得
    public function GetMoney(string $xuid)
    {
        $sql = $this->db->prepare("SELECT * FROM moneys WHERE xuid = :xuid");
        $sql->bindValue(':xuid', $xuid, SQLITE3_TEXT);
        $result = $sql->execute();
        $data = $result->fetchArray();
        if (!$data) {
            return false;
        }
        return $data;
    }

    // プレイヤー所持金更新
    public function UpdateMoney(string $xuid, int $money)
    {
        $sql = $this->db->prepare("UPDATE moneys SET money = :money WHERE xuid = :xuid");
        $sql->bindValue(':money', $money, SQLITE3_INTEGER);
        $sql->bindValue(':xuid', $xuid, SQLITE3_TEXT);
        $sql->execute();
    }

    // チェストショップ設定
    public function SetChestShop($xuid, $chest, $signboard, $item, $price)
    {
        $sql = $this->db->prepare('INSERT INTO shops VALUES (:id, :ownerxuid, :chestx, :chesty, :chestz, :signboardx, :signboardy, :signboardz, :itemid, :itemmeta, :price, :maxcount, :levelname)');
        $sql->bindValue(':id', "$xuid-$chest->x-$chest->y-$chest->z-$signboard->x-$signboard->y-$signboard->z", SQLITE3_TEXT);
        $sql->bindValue(':ownerxuid', $xuid, SQLITE3_TEXT);
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
    }

    // チェストショップのチェスト存在確認
    public function isChestShopExits($block, $levelname)
    {
        $sql = $this->db->prepare("SELECT * FROM shops WHERE chestx = :x AND chesty = :y AND chestz = :z AND levelname = :levelname");
        $sql->bindValue(':x', $block->x, SQLITE3_INTEGER);
        $sql->bindValue(':y', $block->y, SQLITE3_INTEGER);
        $sql->bindValue(':z', $block->z, SQLITE3_INTEGER);
        $sql->bindValue(':levelname', $levelname, SQLITE3_TEXT);
        $result = $sql->execute();
        $data = $result->fetchArray();
        if (!$data) {
            return false;
        }
        return true;
    }

    // チェストショップ取得
    public function GetChestShop($block, $levelname)
    {
        $sql = $this->db->prepare("SELECT * FROM shops WHERE ((signboardx = :x AND signboardy = :y AND signboardz = :z) OR (chestx = :x AND chesty = :y AND chestz = :z)) AND levelname = :levelname");
        $sql->bindValue(':x', $block->x, SQLITE3_INTEGER);
        $sql->bindValue(':y', $block->y, SQLITE3_INTEGER);
        $sql->bindValue(':z', $block->z, SQLITE3_INTEGER);
        $sql->bindValue(':levelname', $levelname, SQLITE3_TEXT);
        $result = $sql->execute();
        $data = $result->fetchArray();
        if (!$data) {
            return false;
        }
        return $data;
    }

    // チェストショップ削除
    public function DeleteChestShop($shopdata)
    {
        $sql = $this->db->prepare("DELETE FROM shops WHERE signboardx = :x AND signboardy = :y AND signboardz = :z AND levelname = :levelname");
        $sql->bindValue(':x', $shopdata["signboardx"], SQLITE3_INTEGER);
        $sql->bindValue(':y', $shopdata["signboardy"], SQLITE3_INTEGER);
        $sql->bindValue(':z', $shopdata["signboardz"], SQLITE3_INTEGER);
        $sql->bindValue(':levelname', $shopdata["levelname"], SQLITE3_TEXT);
        $sql->execute();
    }

    // AdminShop設定
    public function SetAdminShop($item, $buy, $sell)
    {
        $sql = $this->db->prepare("INSERT INTO adminshops VALUES (:id, :itemid, :itemmeta, :buyprice, :sellprice)");
        $sql->bindValue(':id', $item->getId() . "-" . $item->getDamage(), SQLITE3_TEXT);
        $sql->bindValue(':itemid', $item->getId(), SQLITE3_INTEGER);
        $sql->bindValue(':itemmeta', $item->getDamage(), SQLITE3_INTEGER);
        $sql->bindValue(':buyprice', $buy, SQLITE3_INTEGER);
        $sql->bindValue(':sellprice', $sell, SQLITE3_INTEGER);
        $result = $sql->execute();
    }

    // AdminShop設定更新
    public function UpdateAdminShop($item, $buy, $sell)
    {
        $sql = $this->db->prepare("UPDATE adminshops SET buyprice = :buyprice, sellprice = :sellprice WHERE itemid = :itemid AND itemmeta = :itemmeta");
        $sql->bindValue(':itemid', $item->getId(), SQLITE3_INTEGER);
        $sql->bindValue(':itemmeta', $item->getDamage(), SQLITE3_INTEGER);
        $sql->bindValue(':buyprice', $buy, SQLITE3_INTEGER);
        $sql->bindValue(':sellprice', $sell, SQLITE3_INTEGER);
        $result = $sql->execute();
    }

    // AdminShop取得
    public function GetAdminShop($item)
    {
        $sql = $this->db->prepare("SELECT * FROM adminshops WHERE itemid = :itemid AND itemmeta = :itemmeta");
        $sql->bindValue(':itemid', $item->getId(), SQLITE3_INTEGER);
        $sql->bindValue(':itemmeta', $item->getDamage(), SQLITE3_INTEGER);
        $result = $sql->execute();
        $data = $result->fetchArray();
        if ($data) {
            return false;
        }
        return $data;
    }

    // AdminShopに登録されているItem全取得
    public function AllAdminShop()
    {
        $alldata = [];
        $sql = $this->db->prepare("SELECT * FROM adminshops");
        $result = $sql->execute();
        while ($d = $result->fetchArray(SQLITE3_ASSOC)) {
            $alldata[] = $d;
        }

        if (count($alldata) < 1) {
            return false;
        }

        return $alldata;
    }

    // 土地保護設定
    public function SetLand($xuid, $levelname, $pos1, $pos2)
    {
        $sql = $this->db->prepare("INSERT INTO lands VALUES (:id, :ownerxuid, :levelname, :startx, :startz, :endx, :endz)");
        $sql->bindValue(':id', "$xuid-$levelname-$pos1->x-$pos1->z-$pos2->x-$pos2->z", SQLITE3_TEXT);
        $sql->bindValue(':ownerxuid', $xuid, SQLITE3_INTEGER);
        $sql->bindValue(':levelname', $levelname, SQLITE3_TEXT);
        $sql->bindValue(':startx', $pos1->x, SQLITE3_INTEGER);
        $sql->bindValue(':startz', $pos1->z, SQLITE3_INTEGER);
        $sql->bindValue(':endx', $pos2->x, SQLITE3_INTEGER);
        $sql->bindValue(':endz', $pos2->z, SQLITE3_INTEGER);
        $sql->execute();
    }

    // 土地保護が有効かどうかのチェック
    public function isLandExits($levelname, $block)
    {
        $sql = $this->db->prepare("SELECT * FROM lands WHERE levelname = :levelname AND (startx >= :x AND startz >= :z AND endx <= :x AND endz <= :z) OR (startx <= :x AND startz <= :z AND endx >= :x AND endz >= :z) OR (startx >= :x AND startz <= :z AND endx <= :endx AND endz >= :endz) OR (startx <= :x AND startz >= :z AND endx >= :x AND endz <= :z)");
        $sql->bindValue(':levelname', $levelname, SQLITE3_TEXT);
        $sql->bindValue(':x', $block->x, SQLITE3_INTEGER);
        $sql->bindValue(':z', $block->z, SQLITE3_INTEGER);
        $result = $sql->execute();
        $data = $result->fetchArray();
        var_dump($block);
        if (!$data) {
            return false;
        }
        return true;
    }
}
