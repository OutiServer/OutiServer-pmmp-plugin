<?php

declare(strict_types=1);

namespace OutiServerPlugin\Tasks;

use ArgumentCountError;
use DateTime;
use DateTimeZone;
use Error;
use Exception;
use InvalidArgumentException;
use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\Config;
use TypeError;

class SendLog extends AsyncTask
{
    private string $webhook = "";
    private string $content = "";

    public function __construct(string $webhook, string $content)
    {
        $this->webhook = $webhook;
        $this->content = $content;
    }

    public function onRun()
    {
        try {
            if($this->webhook === "" or $this->content === "") return;
            try {
                $time = new DateTime('NOW', new DateTimeZone("Asia/Tokyo"));
            } catch (Exception $error) {
                echo $error->getFile() . "の" . $error->getLine() . "行目でError\n" . $error->getMessage() . PHP_EOL;
                return;
            }

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $this->webhook);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(
                array(
                    'content' => "```[{$time->format('Y-m-d H:i:sP')}]: $this->content```"
                )
            ));
            curl_exec($curl);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $error) {
            echo $error->getFile() . "の" . $error->getLine() . "行目でError\n" . $error->getMessage() . PHP_EOL;
        }
    }
}