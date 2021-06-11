<?php

declare(strict_types=1);

namespace Ken_Cir0909\OutiMoney\Tasks;

use pocketmine\scheduler\Task;

class AuctionTask extends Task
{
    private $plugin;
    private $sellerxuid;

    public function __construct($plugin, $sellerxuid)
    {
        $this->plugin = $plugin;
        $this->sellerxuid = $sellerxuid;
    }

    public function onRun(int $tick)
    {
        $data = array(
            'content' => "オークションが終了しました。"
        );

        $url = 'https://discord.com/api/webhooks/838771358275731546/8RChaifOtXE51ro8aMhBW_IBb5uAmimE5dwk1nri0R3VxYZPZS8eujYl8dAG0ZuvApPh';


        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);

        $result = $this->plugin->db->query("SELECT * FROM auctions WHERE id = $this->sellerxuid");
        if ($result !== false) {
            return;
        }
        $arr = $playerdata->fetchArray();
        if (!$arr) {
            return;
        }
        $sellerplayer = Server::getInstance()->getPlayer($arr["sellername"]);
        if (!$sellerplayer) {
            Server::getInstance()->getPlayer($arr["sellername"]);
        }



        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}
