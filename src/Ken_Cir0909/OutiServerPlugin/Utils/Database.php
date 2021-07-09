<?php

declare(strict_types=1);

namespace Ken_Cir0909\OutiServerPlugin\Utils;

class Database
{
    public \SQLite3 $db;

    public function __construct(string $dir)
    {
        $this->db = new \SQLite3($dir);
        $this->db->exec("CREATE TABLE IF NOT EXISTS moneys (xuid TEXT PRIMARY KEY, money INTEGER)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS shops (id INTEGER PRIMARY KEY AUTOINCREMENT, ownerxuid TEXT, chestx INTEGER, chesty INTEGER, chestz INTEGER, signboardx INTEGER, signboardy INTEGER, signboardz INTEGER, itemid INTEGER, itemmeta INTEGER, price INTEGER, maxcount INTEGER, levelname TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS adminshops (id TEXT PRIMARY KEY, itemid INTEGER, itemmeta INTEGER, buyprice INTEGER, sellprice INTEGER)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS lands (id INTEGER PRIMARY KEY AUTOINCREMENT, owner TEXT, levelname TEXT, startx INTEGER, startz INTEGER, endx INTEGER, endz INTEGER, invites TEXT, protection INTEGER)");
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

    public function AddMoney(string $xuid, int $addmoney)
    {
        $oldmoney = $this->GetMoney($xuid);
        if(!$oldmoney) {
            $this->SetMoney($xuid);
            $oldmoney = $this->GetMoney($xuid);
        }
        $sql = $this->db->prepare("UPDATE moneys SET money = :money WHERE xuid = :xuid");
        $sql->bindValue(':money', $oldmoney["money"] + $addmoney, SQLITE3_INTEGER);
        $sql->bindValue(':xuid', $xuid, SQLITE3_TEXT);
        $sql->execute();
    }

    public function RemoveMoney(string $xuid, int $removemoney)
    {
        $oldmoney = $this->GetMoney($xuid);
        if(!$oldmoney) {
            $this->SetMoney($xuid);
            $oldmoney = $this->GetMoney($xuid);
        }
        $sql = $this->db->prepare("UPDATE moneys SET money = :money WHERE xuid = :xuid");
        $sql->bindValue(':money', $oldmoney["money"] - $removemoney, SQLITE3_INTEGER);
        $sql->bindValue(':xuid', $xuid, SQLITE3_TEXT);
        $sql->execute();
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
        $sql = $this->db->prepare('INSERT INTO (ownerxuid, chestx, chesty, chestz, signboardx, signboardy, signboardz, itemid, itemmeta, price, maxcount, levelname) shops VALUES (:ownerxuid, :chestx, :chesty, :chestz, :signboardx, :signboardy, :signboardz, :itemid, :itemmeta, :price, :maxcount, :levelname)');
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
    public function SetLand($owner, $levelname, $startx, $startz, $endx, $endz)
    {
        $sql = $this->db->prepare("INSERT INTO lands (owner, levelname, startx, startz, endx, endz, invites, protection) VALUES (:owner, :levelname, :startx, :startz, :endx, :endz, :invites, :protection)");
        $sql->bindValue(':owner', strtolower($owner), SQLITE3_TEXT);
        $sql->bindValue(':levelname', $levelname, SQLITE3_TEXT);
        $sql->bindValue(':startx', $startx, SQLITE3_INTEGER);
        $sql->bindValue(':startz', $startz, SQLITE3_INTEGER);
        $sql->bindValue(':endx', $endx, SQLITE3_INTEGER);
        $sql->bindValue(':endz', $endz, SQLITE3_INTEGER);
        $sql->bindValue(':invites', serialize(array()), SQLITE3_TEXT);
        $sql->bindValue(':protection', 0, SQLITE3_INTEGER);
        $sql->execute();
    }

    // ID取得
    public function GetLandId(string $levelname, int $x, int $z)
    {
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
    }

    public function GetLandData(int $id)
    {
        $sql = $this->db->prepare("SELECT * FROM lands WHERE id = :id");
        $sql->bindValue(":id", $id, SQLITE3_INTEGER);
        $result = $sql->execute();
        $data = $result->fetchArray();
        if(!$data) {
            return false;
        }
        return $data;
    }

    public function UpdateLandProtection(int $id, int $protection)
    {
        $sql = $this->db->prepare("UPDATE lands SET protection = :protection WHERE id = :id");
        $sql->bindValue(':protection', $protection, SQLITE3_INTEGER);
        $sql->bindValue(':id', $id, SQLITE3_INTEGER);
        $sql->execute();
    }

    public function AddLandInvite(int $id, string $invitename)
    {
        $invites = $this->GetLandInvites($id);
        if(!in_array($invitename, $invites)) {
            $invites[] = strtolower(str_replace("'", "", $invitename));
            $sql = $this->db->prepare("UPDATE lands SET invites = :invites WHERE id = :id");
            $sql->bindValue(":invites", serialize($invites), SQLITE3_TEXT);
            $sql->bindValue(":id", $id, SQLITE3_INTEGER);
            $sql->execute();
        }
    }

    public function GetLandInvites(int $id)
    {
        $sql = $this->db->prepare("SELECT * FROM lands WHERE id = :id");
        $sql->bindValue(":id", $id, SQLITE3_INTEGER);
        $result = $sql->execute();
        $data = $result->fetchArray(SQLITE3_ASSOC);
        if (!$data) {
            return false;
        }

        return unserialize($data["invites"]);
    }

    public function checkInvite(int $id, string $name): bool
    {
        $invites = $this->GetLandInvites($id);
        $invitename = strtolower($name);
        if(!$invites) return false;
        elseif(!in_array($invitename, $invites)) return false;

        return true;
    }

    public function RemoveLandInvite(int $id, string $name): bool
    {
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
    }

    public function ChangeLandOwner(int $id, string $name)
    {
        $sql = $this->db->prepare("UPDATE lands SET owner = :owner WHERE id = :id");
        $sql->bindValue(":owner", $name, SQLITE3_TEXT);
        $sql->bindValue(":id", $id, SQLITE3_INTEGER);
        $sql->execute();
    }

    public function CheckLandOwner(int $id, string $name) : bool
    {
        $ownername = strtolower($name);
        $landdata = $this->GetLandData($id);
        if(!$landdata) return false;
        return $landdata["owner"] === $ownername;
    }

    public function CheckLandProtection(int $id): ?bool
    {
        $landdata = $this->GetLandData($id);
        if(!$landdata) return null;
        elseif ($landdata["protection"] === 1) return true;
        else return false;
    }
}
