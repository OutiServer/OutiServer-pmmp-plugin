<?php

declare(strict_types=1);

namespace Ken_Cir0909\OutiMoney\Tasks;

use pocketmine\scheduler\AsyncTask;

class MessageTask extends AsyncTask
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

        $url = 'https://discord.com/api/webhooks/838771358275731546/8RChaifOtXE51ro8aMhBW_IBb5uAmimE5dwk1nri0R3VxYZPZS8eujYl8dAG0ZuvApPh';


        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}
