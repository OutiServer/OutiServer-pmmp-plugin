<?php

namespace Ken_Cir0909\OutiServerPlugin\Tasks;

use Discord\Parts\Channel\Message;
use Monolog\Logger;
use pocketmine\utils\TextFormat;

class discord extends \Thread
{
    public string $file;
    public bool $stopped = false;
    public bool $started = false;
    public $content;
    private string $db_file;
    private string $token;
    private bool $db_send;
    protected \Threaded $console_Queue;
    protected \Threaded $serverchat_Queue;
    protected \Threaded $log_Queue;
    protected \Threaded $chat_Queue;

    public function __construct(string $file, string $token, string $db_file)
    {
        $this->file = $file;
        $this->token = $token;
        $this->db_file = $db_file;

        $this->console_Queue = new \Threaded;
        $this->serverchat_Queue = new \Threaded;
        $this->log_Queue = new \Threaded;
        $this->chat_Queue = new \Threaded;

        $this->start();
    }

    public function run()
    {
        include $this->file . "vendor/autoload.php";
        $loop = \React\EventLoop\Factory::create();

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
                if ($message->author->user->bot === true or $message->type !== Message::TYPE_NORMAL or $message->content === '') {
                    return;
                }
                if ($message->channel_id === '854354514320293928') {
                    $this->console_Queue[] = serialize([
                        'username' => $message->author->username,
                        'content' => $message->content
                    ]);
                } elseif ($message->channel_id === '834317763769925632') {
                    $this->serverchat_Queue[] = serialize([
                        'username' => $message->author->username,
                        'content' => $message->content
                    ]);
                }
            });
        });
        $discord->run();
    }

    public function task(\Discord\Discord $discord)
    {
        if (!$this->started) {
            return;
        }

        $guild = $discord->guilds->get('id', '706452606918066237');
        $db_guild = $discord->guilds->get('id', '794380572323086358');
        $chatchannel = $guild->channels->get('id', '834317763769925632');
        $logchannel = $guild->channels->get('id', '854354514320293928');
        $db_channel = $db_guild->channels->get('id', '852840652706283534');


        $logsend = "";
        $chatsend = "";

        while (count($this->log_Queue) > 0) {
            $message = unserialize($this->log_Queue->shift());
            $message = preg_replace(['/\]0;.*\%/', '/[\x07]/', "/Server thread\//"], '', TextFormat::clean(substr($message, 0, 1900)));
            if ($message === "") {
                continue;
            }
            $logsend .= $message;
            if (strlen($logsend) >= 1800) {
                break;
            }
        }
        if ($logsend !== "") {
            $logchannel->sendMessage("```" . $logsend . "```");
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

        if($this->db_send)  {
            $db_channel->sendFile($this->db_file);
            $this->db_send = false;
        }
    }

    public function shutdown()
    {
        $this->stopped = true;
    }

    public function sendChatMessage(string $message)
    {
        $this->chat_Queue[] = serialize($message);
    }

    public function sendLogMessage(string $message)
    {
        $this->log_Queue[] = serialize($message);
    }

    public function GetConsoleMessages()
    {
        $messages = [];
        while (count($this->console_Queue) > 0) {
            $messages[] = unserialize($this->console_Queue->shift());
        }
        return $messages;
    }

    public function GetChatMessage()
    {
        $messages = [];
        while (count($this->serverchat_Queue) > 0) {
            $messages[] = unserialize($this->serverchat_Queue->shift());
        }
        return $messages;
    }

    public function sendDB()
    {
        $this->db_send = true;
    }
}
