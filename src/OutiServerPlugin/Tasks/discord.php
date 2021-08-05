<?php

namespace OutiServerPlugin\Tasks;

use Discord\Exceptions\IntentException;
use Discord\Parts\Channel\{Channel, Message};
use Discord\Parts\User\Member;
use pocketmine\utils\TextFormat;
use React\EventLoop\Factory;
use Thread;
use Threaded;

class discord extends Thread
{
    public string $file;
    public bool $stopped = false;
    public bool $started = false;
    private string $guild_id;
    private string $chat_id;
    private string $log_id;
    private string $db_id;
    private string $errorlog_id;
    private string $dir;
    private string $token;
    private bool $db_send;
    private string $prefix;
    protected Threaded $console_Queue;
    protected Threaded $serverchat_Queue;
    protected Threaded $log_Queue;
    protected Threaded $chat_Queue;
    protected Threaded $command_Queue;
    protected Threaded $command_response_Queue;
    protected Threaded $errorlog_Queue;

    public function __construct(string $file, string $dir, string $token, string $prefix, string $guild_id, string $chat_channel_id, string $log_channel_id, string $db_channel_id, string $errorlog_channel_id)
    {
        $this->file = $file;
        $this->dir = $dir;
        $this->token = $token;
        $this->prefix = $prefix;
        $this->guild_id = $guild_id;
        $this->chat_id = $chat_channel_id;
        $this->log_id = $log_channel_id;
        $this->db_id = $db_channel_id;
        $this->errorlog_id = $errorlog_channel_id;
        $this->console_Queue = new Threaded;
        $this->serverchat_Queue = new Threaded;
        $this->log_Queue = new Threaded;
        $this->chat_Queue = new Threaded;
        $this->command_Queue = new Threaded;
        $this->command_response_Queue = new Threaded;
        $this->errorlog_Queue = new Threaded;

        $this->start();
    }

    public function run()
    {
        include $this->file . "vendor/autoload.php";
        $loop = Factory::create();

        try {
            $discord = new \Discord\Discord([
                'token' => $this->token,
                "loop" => $loop
            ]);
        } catch (IntentException $error) {
            echo $error->getFile() . "の" . $error->getLine() . "行目でError\n" . $error->getMessage() . PHP_EOL;
            echo "DiscordPHP ログインできません" . PHP_EOL;
            return;
        }

        unset($this->token);

        $loop->addPeriodicTimer(1, function () use ($discord) {
            if ($this->stopped) {
                $guild = $discord->guilds->get('id', $this->guild_id);
                $chatchannel = $guild->channels->get('id', $this->chat_id);
                $chatchannel->sendMessage("サーバーが停止しました");
                $discord->close();
                $discord->loop->stop();
                $this->started = false;
            }
        });

        $loop->addPeriodicTimer(1, function () use ($discord) {
            $this->task($discord);
        });

        $discord->on('ready', function () use ($discord) {
            $this->started = true;
            echo "Bot is ready.", PHP_EOL;
            $discord->on('message', function (Message $message) use ($discord) {
                if ($message->author instanceof Member ? $message->author->user->bot : $message->author->bot or $message->type !== Message::TYPE_NORMAL or $message->channel->type !== Channel::TYPE_TEXT or $message->content === "" or !$message->author instanceof Member) return;
                if ($message->channel_id === $this->log_id) {
                    $this->console_Queue[] = serialize([
                        'username' => $message->author->username,
                        'content' => $message->content
                    ]);
                } elseif ($message->channel_id === $this->chat_id) {
                    $this->serverchat_Queue[] = serialize([
                        'username' => $message->author->username,
                        'content' => $message->content
                    ]);
                }

                if (!str_starts_with(strtolower($message->content), $this->prefix)) return;
                $args = preg_split("/ +/", trim(mb_substr($message->content, strlen($this->prefix))));
                $command = strtolower(array_shift($args));
                if ($command === "help") {
                    $message->channel->sendMessage("```\n" . "Command Prefix: " . $this->prefix . "\n\nhelp: このコマンド\nserver: サーバーの状態を表示\nannounce [title] [content]: (管理者専用)運営からのお知らせを追加する\n```");
                } else if ($command === "server") {
                    $this->command_Queue[] = serialize([
                        "name" => $command,
                        "channelid" => $message->channel_id
                    ]);
                } else if ($command === "announce") {
                    if (count($args) < 2 or (!$message->author->roles->has("771015602180587571") and !$message->author->roles->has("822852335322923060") and !$message->author->roles->has("852190591830982677"))) return;
                    $this->command_Queue[] = serialize([
                        "name" => $command,
                        "channelid" => $message->channel_id,
                        "args" => $args
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

        $guild = $discord->guilds->get('id', $this->guild_id);
        $chatchannel = $guild->channels->get('id', $this->chat_id);
        $logchannel = $guild->channels->get('id', $this->log_id);
        $db_channel = $guild->channels->get('id', $this->db_id);
        $errorlogchannel = $guild->channels->get('id', $this->errorlog_id);

        while (count($this->log_Queue) > 0) {
            $message = unserialize($this->log_Queue->shift());
            $message = preg_replace(['/\]0;.*\%/', '/[\x07]/', "/Server thread\//"], '', TextFormat::clean(substr($message, 0, 1900)));
            if ($message === "") continue;
            if (strlen($message) <= 1800) {
                $logchannel->sendMessage("```" . $message . "```");
            }
        }

        while (count($this->chat_Queue) > 0) {
            $message = unserialize($this->chat_Queue->shift());
            $message = str_replace("@", "", $message);
            if ($message === "") continue;
            if (strlen($message) <= 1800) {
                $chatchannel->sendMessage($message);
            }
        }

        while (count($this->command_response_Queue) > 0) {
            $cmd_response = unserialize($this->command_response_Queue->shift());
            $guild = $discord->guilds->get('id', $this->guild_id);
            $channel = $guild->channels->get('id', $cmd_response["channelid"]);
            $channel->sendMessage($cmd_response["response"]);
        }

        while (count($this->errorlog_Queue) > 0) {
            $message = unserialize($this->errorlog_Queue->shift());
            if ($message === "") continue;
            if (strlen($message) <= 1800) {
                $errorlogchannel->sendMessage($message);
            }
        }

        if ($this->db_send) {
            $db_channel->sendFile($this->dir . "outiserver.db");
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

    public function GetConsoleMessages(): array
    {
        $messages = [];
        while (count($this->console_Queue) > 0) {
            $messages[] = unserialize($this->console_Queue->shift());
        }
        return $messages;
    }

    public function GetChatMessage(): array
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

    public function sendCommand(string $channelid, string $response)
    {
        $this->command_response_Queue[] = serialize([
            "channelid" => $channelid,
            "response" => $response
        ]);
    }

    public function GetCommand(): array
    {
        $commands = [];
        while (count($this->command_Queue) > 0) {
            $commands[] = unserialize($this->command_Queue->shift());
        }
        return $commands;
    }

    public function sendErrorLogMessage(string $message)
    {
        $this->errorlog_Queue[] = serialize($message);
    }
}
