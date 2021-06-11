<?php

declare(strict_types=1);

namespace Ken_Cir0909\OutiMoney\Tasks;

use pocketmine\scheduler\AsyncTask;

class ShopMessageTask extends AsyncTask
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

        $url = 'https://discord.com/api/webhooks/838773781124087848/_5Xn_H44znaCn2AWbUKoxTxXIQY6w1kow68diUduw6HlQq7aCbGZ6GUMv7I7x8970xf-';

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}
