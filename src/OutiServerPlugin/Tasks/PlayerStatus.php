<?php
declare(strict_types=1);

namespace OutiServerPlugin\Tasks;

use ArgumentCountError;
use Error;
use Exception;
use InvalidArgumentException;
use OutiServerPlugin\Main;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use TypeError;

class PlayerStatus extends Task
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onRun(int $currentTick)
    {
        try {
            foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
                if($player->isOp() and !in_array(strtolower($player->getName()), $this->plugin->config->get('OPList', array()))) {
                    $player->kick("§cプラグインにより不正検知されました\nOPを所持しているがOPホワイトリストに存在しない\nこれが誤検知である場合は、運営に解除申請をしてください");
                    $player->setBanned(true);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPunishmentLog_Webhook', ''), "{$player->getName()} がプラグインにより不正検知されました\n不正検知詳細: OPを所持しているがOPホワイトリストに存在しない"));
                    return;
                }
                elseif (($player->getGamemode() === Player::CREATIVE or $player->getGamemode() === Player::SPECTATOR) and !in_array(strtolower($player->getName()), $this->plugin->config->get('OPList', array()))) {
                    $player->kick("§cプラグインにより不正検知されました\nゲームモードがクリエイティブ・スペクテイターモードだがOPホワイトリストに存在しない\nこれが誤検知である場合は、運営に解除申請をしてください");
                    $player->setBanned(true);
                    $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPunishmentLog_Webhook', ''), "{$player->getName()} がプラグインにより不正検知されました\nゲームモードがクリエイティブ・スペクテイターモードだがOPホワイトリストに存在しない"));
                    return;
                }

                $levelname = $player->getLevel()->getName();
                $landid = $this->plugin->db->GetLandId($levelname, (int)$player->x, (int)$player->z);
                $owner = "誰も所有していません";
                if($landid) {
                    $land = $this->plugin->db->GetLandData($landid);
                    $owner = "{$land["owner"]}が所有しています";
                }
                $item = Item::get(347);
                $item->setCustomName("OutiWatch");
                if (!$player->getInventory()->contains($item)) {
                    $player->getInventory()->addItem($item);
                }
                $name = $player->getName();
                if (!$this->plugin->db->GetMoney($name)) return;
                $this->RemoveData($player);
                $this->setupData($player);
                $this->sendData($player, "§e所持金: " . $this->plugin->db->GetMoney($name)["money"] . "円", 1);
                $this->sendData($player, "§b座標: " . $player->getfloorX() . "," . $player->getfloorY() . "," . $player->getfloorZ(), 2);
                $this->sendData($player, "§bワールド: " . $player->getLevel()->getFolderName(), 3);
                $this->sendData($player, "§c現在時刻: " . date("G時i分s秒"), 4);
                $this->sendData($player, "§6持ってるアイテムid: " . $player->getInventory()->getItemInHand()->getId() . ":" . $player->getInventory()->getItemInHand()->getDamage(), 5);
                $this->sendData($player, "§6オンライン人数: " . count($this->plugin->getServer()->getOnlinePlayers()) . "/" . $this->plugin->getServer()->getMaxPlayers(), 6);
                $this->sendData($player, "§dPing: " . $player->getPing() . "ms", 7);
                $this->sendData($player, "§c" . gmdate("アイテムクリアまであとi分s秒", $this->plugin->autoClearLagg->seconds), 8);
                $this->sendData($player, "§a現在立っている土地は$owner", 9);
            }
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }

    }

    private function setupData(Player $player)
    {
        try {
            $pk = new SetDisplayObjectivePacket();
            $pk->displaySlot = "sidebar";
            $pk->objectiveName = "sidebar";
            $pk->displayName = "§a" . $player->getName();
            $pk->criteriaName = "dummy";
            $pk->sortOrder = 0;
            $player->sendDataPacket($pk);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function sendData(Player $player, string $data, int $id)
    {
        try {
            $entry = new ScorePacketEntry();
            $entry->objectiveName = "sidebar";
            $entry->type = $entry::TYPE_FAKE_PLAYER;
            $entry->customName = $data;
            $entry->score = $id;
            $entry->scoreboardId = $id + 11;
            $pk = new SetScorePacket();
            $pk->type = $pk::TYPE_CHANGE;
            $pk->entries[] = $entry;
            $player->sendDataPacket($pk);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }

    private function RemoveData(Player $player)
    {
        try {
            $pk = new RemoveObjectivePacket();
            $pk->objectiveName = "sidebar";
            $player->sendDataPacket($pk);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}