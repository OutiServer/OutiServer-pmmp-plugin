<?php

declare(strict_types=1);

namespace OutiServerPlugin;

use ArgumentCountError;
use DateTime;
use Error;
use Exception;
use InvalidArgumentException;
use OutiServerPlugin\plugins\{Admin,
    AdminShop,
    Announce,
    AutoItemClear,
    Casino,
    ChestShop,
    Land,
    Money,
    OutiWatch,
    Sound,
    Teleport};
use OutiServerPlugin\Tasks\discord;
use OutiServerPlugin\Tasks\PlayerStatus;
use OutiServerPlugin\Utils\{Database, ErrorHandler};
use OutiServerPlugin\Tasks\SendLog;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\{Config, TextFormat};
use SQLiteException;
use TypeError;


class Main extends PluginBase
{
    public discord $client;
    public bool $started = false;
    public Database $db;
    public Config $config;
    public Config $music;
    public Config $landconfig;
    public Land $land;
    public ChestShop $chestshop;
    public AdminShop $adminshop;
    public Admin $admin;
    public Teleport $teleport;
    public Announce $announce;
    public Money $money;
    public Casino $casino;
    public OutiWatch $applewatch;
    public Sound $sound;
    public AutoItemClear $autoClearLagg;
    public ErrorHandler $errorHandler;

    public function onEnable()
    {
        try {
            $this->saveResource("config.yml");
            $this->saveResource("sound.yml");
            $this->saveResource("land.yml");

            $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
            $this->music = new Config($this->getDataFolder() . "sound.yml", Config::YAML);
            $this->landconfig = new Config($this->getDataFolder() . "land.yml", Config::YAML);
            $token = $this->config->get('DiscordBot_Token', "DISCORD_TOKEN");
            if ($token === 'DISCORD_TOKEN') {
                $this->getLogger()->error("config.yml: DiscordBot_Tokenが設定されていません");
                $this->getServer()->getPluginManager()->disablePlugin($this);
                return;
            }

            $this->errorHandler = new ErrorHandler($this);
            $this->db = new Database($this, $this->getDataFolder() . 'outiserver.db', $this->config->get("Default_Item_Category", array()));
            $this->land = new Land($this);
            $this->chestshop = new ChestShop($this);
            $this->adminshop = new AdminShop($this);
            $this->admin = new Admin($this);
            $this->teleport = new Teleport($this);
            $this->announce = new Announce($this);
            $this->money = new Money($this);
            $this->casino = new Casino($this);
            $this->applewatch = new OutiWatch($this);
            $this->sound = new Sound($this);
            $this->autoClearLagg = new AutoItemClear($this);

            $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
            $this->client = new discord($this->getFile(), $this->getDataFolder(), $token, $this->config->get("Discord_Command_Prefix", "?unko"), $this->config->get('Discord_Guild_Id', '706452606918066237'), $this->config->get('DiscordChat_Channel_Id', '834317763769925632'), $this->config->get('DiscordLog_Channel_Id', '833626570270572584'), $this->config->get('DiscordDB_Channel_Id', '863124612429381699'), $this->config->get('DiscordErrorLog_Channel_id', '868787060394307604'));
            unset($token);


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
                        $this->getServer()->dispatchCommand(new ConsoleCommandSender(), $message["content"]);
                    }

                    foreach ($this->client->GetChatMessage() as $message) {
                        $this->getServer()->broadcastMessage("[Discord:{$message["role"]}:" . $message["username"] . "] " . $message["content"]);
                    }
                }
            ), 5, 1);
            $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(
                function (int $currentTick): void {
                    try {
                        foreach ($this->client->GetCommand() as $command) {
                            switch ($command["name"]) {
                                case "server":
                                    $server = $this->getServer();
                                    $this->client->sendCommand($command["channelid"], "```diff\n🏠おうちサーバー(PMMP)の現在の状態🏠\n+ IP: " . $server->getIp() . "\n+ PORT: " . $server->getPort() . "\n+ サーバーのバージョン: " . $server->getVersion() . "\n+ デフォルトゲームモード: " . $server->getDefaultGamemode() . "\n+ デフォルトワールド: " . $server->getDefaultLevel()->getName() . "\n+ 現在参加中のメンバー: " . count($server->getOnlinePlayers()) . "/" . $server->getMaxPlayers() . "人\n```\n");
                                    break;
                                case "announce":
                                    $time = new DateTime('now');
                                    $title = array_shift($command["args"]);
                                    $content = join("\n", $command["args"]);
                                    $this->db->AddAnnounce($time->format("Y年m月d日 H時i分"), $title, $content);
                                    $this->client->sendCommand($command["channelid"], "アナウンスに" . $title . "を追加しました\n");
                                    $this->getServer()->broadcastMessage(TextFormat::YELLOW . "[運営より] 運営からのお知らせが追加されました、ご確認ください。");
                                    $this->client->sendChatMessage("__**[運営より] 運営からのお知らせが追加されました、ご確認ください。**__\n");
                                    break;
                                case 'db':
                                    $query = join(" ", $command["args"]);
                                    var_dump($this->db->db->query($query)->fetchArray());
                                    break;
                            }
                        }
                    } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError | SQLiteException $e) {
                        $this->errorHandler->onErrorNotPlayer($e);
                    }
                }
            ), 5, 1);
            $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
                function (int $currentTick): void {
                    try {
                        $messages = $this->db->GetRegularMessageAll();
                        if(!$messages) return;
                        $message = $messages[array_rand($messages)];
                        $this->getServer()->broadcastMessage("[定期] {$message["content"]}");
                    } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                        $this->errorHandler->onErrorNotPlayer($e);
                    }
                }
            ), $this->config->get('RegularMessageTick', 60) * 20);

            $this->getScheduler()->scheduleRepeatingTask(new PlayerStatus($this), 5);

            $this->client->sendChatMessage("サーバーが起動しました！\n");
            $this->getServer()->getAsyncPool()->submitTask(new SendLog($this->config->get('DiscordPluginLog_Webhook', ''), "プラグインが正常に有効化されました"));
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $error) {
            $this->getLogger()->info(TextFormat::RED . "プラグイン読み込み中にエラーが発生しました\nプラグインを無効化します");
            $this->getLogger()->error($error->getFile() . "の" . $error->getLine() . "行目でError\n" . $error->getMessage());
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
    }

    public function onDisable()
    {
        try {
            if (!$this->started) return;
            $this->db->close();
            $this->client->sendChatMessage("サーバーが停止しました\n");
            $this->getLogger()->info("出力バッファリングを終了しています...");
            $this->client->shutdown();
            ob_flush();
            ob_end_clean();
            $this->getLogger()->info("discordBotの終了を待機しております...");
            $this->client->join();
            $this->getServer()->getAsyncPool()->submitTask(new SendLog($this->config->get('DiscordPluginLog_Webhook', ''), "プラグインが正常に無効化されました"));
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->getLogger()->info(TextFormat::RED . "プラグイン無効化中にエラーが発生しました\nプラグインが正常に無効化できていない可能性があります");
            $this->getLogger()->error($e->getMessage());
        }

    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args): bool
    {
        try {
            $name = $sender->getName();
            switch (strtolower($command->getName())) {
                case "money":
                    if (isset($args[0])) {
                        $data = $this->db->GetMoney($args[0]);
                        if (!$data) return false;
                        $sender->sendMessage("§a[経済] >> §6$args[0]の現在の所持金: §d{$data["money"]}円");
                    } elseif ($sender instanceof Player) {
                        $data = $this->db->GetMoney($name);
                        $sender->sendMessage("§a[経済] >> §6あなたの現在の所持金: §d{$data["money"]}円");
                    } else return false;
                    break;
                case "outiwatch":
                    if (!$sender instanceof Player) {
                        $sender->sendMessage("§a[おうちサーバー] >> §4このコマンドはコンソールから実行できません");
                    } else {
                        $item = Item::get(347);
                        $item->setCustomName("OutiWatch");
                        if (!$sender->getInventory()->contains($item)) {
                            $sender->getInventory()->addItem($item);
                            $sender->sendMessage("§a[おうちサーバー] >> §bOutiWatchを付与しました");
                        } else {
                            $sender->sendMessage("§a[おうちサーバー] >> §4あなたは既に時計を所持しています");
                        }
                    }
                    break;
                case 'reloadouticonfig':
                    $this->config->reload();
                    $sender->sendMessage("§a[おうちサーバー] >> §aconfigをリロードしました");
                    break;
                case 'reloadoutisoundconfig':
                    $this->music->reload();
                    $sender->sendMessage("§a[おうちサーバー] >> §asoundをリロードしました");
                    break;
                case 'db':
                    $query = join(" ", $args);
                    var_dump($this->db->db->query($query)->fetchArray());
                    break;
                case 'senddb':
                    $this->client->sendDB();
                    break;
                case 'setitem':
                    var_dump($args);
                    if (!is_numeric($args[0]) or !is_numeric($args[1]) or !isset($args[2])) break;
                    $item = Item::get((int)$args[0], (int)$args[1]);
                    if (!$item) return true;

                    $path = "";
                    if (isset($args[3])) {
                        $path = $args[3];
                    }

                    if ($this->db->GetItemDataItem($item)) {
                        $this->db->UpdateItemData($item, $args[2], $path);
                    } else {
                        $this->db->SetItemData($item, $args[2], $path);
                    }

                    $sender->sendMessage("§b[Item設定] >> §a設定しました");
                    break;
                case 'reloadoutilandconfig':
                    $this->landconfig->reload();
                    $sender->sendMessage("§b[土地設定] >> §a土地設定をリロードしました");
                    break;
            }

            return true;
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError | SQLiteException $e) {
            $this->errorHandler->onErrorNotPlayer($e);
        }

        return true;
    }
}
