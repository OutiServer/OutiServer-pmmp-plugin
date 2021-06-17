<?php

namespace Ken_Cir0909\OutiServerPlugin\Tasks;

use Discord\Parts\Channel\Message;
use Monolog\Logger;
use pocketmine\utils\TextFormat;

class discord extends \Thread
{
    public $file;
    public $stopped = false;
    public $started = false;
    public $content;
    private $token;
    protected $D2P_Queue;
    protected $log_Queue;
    protected $chat_Queue;

    public function __construct($file, string $token)
    {
        $this->file = $file;
        $this->token = $token;

        $this->D2P_Queue = new \Threaded;
        $this->log_Queue = new \Threaded;
        $this->chat_Queue = new \Threaded;

        $this->start();
    }

    public function run()
    {
        include $this->file . "vendor/autoload.php";
        $loop = \React\EventLoop\Factory::create();
        $debug = $this->debug;

        $discord = new \Discord\Discord([
            'token' => $this->token,
            "loop" => $loop
        ]);

        $timer = $loop->addPeriodicTimer(1, function () use ($discord) {
            if ($this->stopped) {
                $guild = $discord->guilds->get('id', '706452606918066237');
                $chatchannel = $guild->channels->get('id', '834317763769925632');
                $chatchannel->sendMessage("サーバーが停止しました");
                $discord->close();
                $discord->loop->stop();
                $this->started = false;
                return;
            }
        });

        $timer1 = $loop->addPeriodicTimer(1, function () use ($discord) {
            $this->task($discord);
        });

        unset($this->token);

        $discord->on('ready', function ($discord) {
            $this->started = true;
            echo "Bot is ready.", PHP_EOL;
            $discord->on('message', function (Message $message) {
                if ($message->author->bot or $message->channel_id !== '' or $message->type !== Message::TYPE_NORMAL) {
                    return;
                }
                $this->D2P_Queue[] = serialize([
                        'username' => $message->author->username,
                        'content' => $message->content
                    ]);
            });
        });
        $discord->run();
    }

    public function task($discord)
    {
        if (!$this->started) {
            return;
        }

        $guild = $discord->guilds->get('id', '706452606918066237');
        $chatchannel = $guild->channels->get('id', '834317763769925632');
        $logchannel = $guild->channels->get('id', '854354514320293928');


        $logsend = "";
        $chatsend = "";

        while (count($this->log_Queue) > 0) {
            $message = unserialize($this->log_Queue->shift());
            $message = preg_replace(['/\]0;.*\%/', '/[\x07]/', "/Server thread\//"], '', TextFormat::clean(substr($message, 0, 1900)));
            if ($message === "") {
                continue;
            }
            $logsend  .= $message;
            if (strlen($logsend) >= 1800) {
                break;
            }
        }
        if ($logsend !== "") {
            $logchannel->sendMessage("```".$logsend."```");
        }

        while (count($this->chat_Queue) > 0) {
            $message = unserialize($this->chat_Queue->shift());
            if ($message === "") {
                continue;
            }
            $chatsend .= $message;
            if (strlen($chatsend) >= 1800) {
                break;
            }
        }
        if ($chatsend !== "") {
            $chatchannel->sendMessage($chatsend);
        }
    }

    public function shutdown()
    {
        $this->stopped = true;
    }

    public function sendChatMessage(string $message)
    {
        //var_dump("send".$message);
        $this->chat_Queue[] = serialize($message);
    }

    public function sendLogMessage(string $message)
    {
        $this->log_Queue[] = serialize($message);
    }

    public function GetMessages()
    {
        //var_dump("?!?!");
        $messages = [];
        while (count($this->D2P_Queue) > 0) {
            $messages[] = unserialize($this->D2P_Queue->shift());
        }
        //var_dump($messages);
        return $messages;
    }
}
