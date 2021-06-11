<?php

declare(strict_types=1);

namespace Ken_Cir0909\OutiMoney\Tasks;

use pocketmine\scheduler\AsyncTask;

class AuctionMessageTask extends AsyncTask
{
    private $message;

    public function __construct($message)
    {
        $this->message = $message;
    }
    
    public function onRun()
    {
        $data = array(
            'content' => $this->message
        );

        $url = 'https://discord.com/api/webhooks/838948013643923489/tNswHWtBDQUc7Ib6z6gYgfFmYfK-GdqAJoPaSuUK7CZP-4w9SppmJCM5EF4dtEJ-IX0W';


        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}
