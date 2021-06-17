<?php

declare(strict_types=1);

namespace Ken_Cir0909\OutiServerPlugin\Utils;

class Database
{
    private $db;

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

    public function Setemoney(string $xuid)
    {
        $sql = $this->db->prepare("INSERT INTO moneys VALUES (:xuid, 1000)");
        $sql->bindValue(':xuid', $xuid, SQLITE3_TEXT);
        $sql->execute();
        return;
    }

    public function Getmoney(string $xuid)
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

    public function Updatemoney(string $xuid, int $money)
    {
        $sql = $this->db->prepare("UPDATE moneys SET money = :money WHERE xuid = :xuid");
        $sql->bindValue(':money', $money, SQLITE3_INTEGER);
        $sql->bindValue(':xuid', $xuid, SQLITE3_TEXT);
        $result = $sql->execute();
    }
}
