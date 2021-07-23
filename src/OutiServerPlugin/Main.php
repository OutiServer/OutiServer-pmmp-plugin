<?php

declare(strict_types=1);

namespace OutiServerPlugin;

use OutiServerPlugin\Tasks\Discord;
use OutiServerPlugin\Utils\Database;
use OutiServerPlugin\Utils\AllItem;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\Server;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase
{
    public Discord $client;
    public bool $started = false;
    public Database $db;
    public Config $config;
    public AllItem $allItem;
    public Land $land;
    public ChestShop $chestshop;
    public AdminShop $adminshop;
    public Admin $admin;

    public function onEnable()
    {
        $this->saveResource("config.yml");
        $this->saveResource("allitemdata.json");

        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $token = $this->config->get('DiscordBot_Token', "DISCORD_TOKEN");
        if ($token === 'DISCORD_TOKEN') {
            $this->getLogger()->error("config.yml: DiscordBot_Tokenが設定されていません");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
        $this->db = new Database($this->getDataFolder() . 'outiserver.db', $this->config->get("Default_Item_Category", array()));
        $this->allItem = new AllItem($this->getDataFolder() . "allitemdata.json");
        $this->land = new Land($this);
        $this->chestshop = new ChestShop($this);
        $this->adminshop = new AdminShop($this);
        $this->admin = new Admin($this);
        $this->client = new Discord($this->getFile(), $this->getDataFolder(), $token, $this->config->get('Discord_Guild_Id', '706452606918066237'), $this->config->get('DiscordChat_Channel_Id', '834317763769925632'), $this->config->get('DiscordLog_Channel_Id', '833626570270572584'));
        unset($token);

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function (int $currentTick): void {
                $this->started = true;
                $this->getLogger()->info("ログ出力を開始します");
                ob_start();
            }
        ), 10);

        $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(
            function (int $currentTick): void {
                if (!$this->started) return;
                $string = ob_get_contents();
                if ($string === "") return;
                $this->client->sendLogMessage($string);
                ob_flush();
            }
        ), 10, 1);

        $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(
            function (int $currentTick): void {
                foreach ($this->client->GetConsoleMessages() as $message) {
                    Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), $message["content"]);
                }

                foreach ($this->client->GetChatMessage() as $message) {
                    Server::getInstance()->broadcastMessage("[Discord:" . $message["username"] . "] " . $message["content"]);
                }
            }
        ), 5, 1);

        $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(
            function (int $currentTick): void {
                foreach ($this->client->GetCommand() as $command) {
                    switch ($command["name"]) {
                        case "server":
                            $server = Server::getInstance();
                            $this->client->sendCommand($command["channelid"], "```diff\n🏠おうちサーバー(PMMP)の現在の状態🏠\n+ IP: " .$server->getIp() . "\n+ PORT: " . $server->getPort() . "\n+ サーバーのバージョン: " . $server->getVersion() . "\n+ デフォルトゲームモード: " . $server->getDefaultGamemode() . "\n+ デフォルトワールド: " . $server->getDefaultLevel()->getName() . "\n+ 現在参加中のメンバー: " . count($server->getOnlinePlayers()) . "/" . $server->getMaxPlayers() . "人\n```");
                            break;
                    }
                }
            }
        ), 5, 1);

        $this->client->sendChatMessage('サーバーが起動しました！\n');
    }

    public function onDisable()
    {
        if (!$this->started) return;
        $this->db->close();
        $this->client->sendChatMessage('サーバーが停止しました\n');
        $this->getLogger()->info("出力バッファリングを終了しています...");
        $this->client->shutdown();
        ob_flush();
        ob_end_clean();
        $this->getLogger()->info("discordBotの終了を待機しております...");
        $this->client->join();
    }
}
