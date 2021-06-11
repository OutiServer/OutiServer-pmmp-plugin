<?php

declare(strict_types=1);

namespace Ken_Cir0909\OutiMoney\Utils;

class Database
{
    private $db;

    public function __construct(string $dir)
    {
        $this->db = new \SQLite3($dir);
        $this->db->exec("CREATE TABLE IF NOT EXISTS moneys (id TEXT PRIMARY KEY, user TEXT, money INTEGER)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS shops (id TEXT PRIMARY KEY, ownerxuid TEXT, chestx INTEGER, chesty INTEGER, chestz INTEGER, signboardx INTEGER, signboardy INTEGER, signboardz INTEGER, itemid INTEGER, itemmeta INTEGER, price INTEGER, maxcount INTEGER, levelname TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS adminshops (id TEXT PRIMARY KEY, itemid INTEGER, itemmeta INTEGER, buyprice INTEGER, sellprice INTEGER)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS auctions (id TEXT PRIMARY KEY, sellerxuid INTEGER, sellername TEXT, itemid INTEGER, itemmeta INTEGER, itemcount INTEGER, buyerxuid INTEGER, buyername TEXT, price INTEGER)");
    }

    public function close()
    {
        $this->db->close();
    }

    public function getplayermoney(string $xuid)
    {
        $result = $this->db->query("SELECT * FROM moneys WHERE id = $xuid");
        $data = $result->fetchArray();
        if(!$data) {
            return false;
        }

        return $data;
    }

    public function setplayermoney(string $xuid)
    {
        $this->db->exec("INSET INTO moneys VALUES ($xuid, $xuid, 1000)");
    }
}
