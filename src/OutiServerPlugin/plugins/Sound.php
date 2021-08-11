<?php

namespace OutiServerPlugin\plugins;

use ArgumentCountError;
use Error;
use Exception;
use InvalidArgumentException;
use OutiServerPlugin\Main;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\StopSoundPacket;
use pocketmine\Player;
use TypeError;

class Sound
{
    private Main $plugin;
    public array $playersounds = [];

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function PlaySound(Player $player)
    {
        try {
            $name = $player->getName();
            $level = $player->getLevel();
            $sound = $this->plugin->music->get($level->getName());
            if (!$sound) return;
            foreach ($sound as $data) {
                $startX = (int)$data["startx"];
                $endX = (int)$data["endx"];
                $startZ = (int)$data["startz"];
                $endZ = (int)$data["endz"];
                if ($startX > $endX) {
                    $backup = $data["startx"];
                    $startX = $endX;
                    $endX = $backup;
                }
                if ($startZ > $endZ) {
                    $backup = $startZ;
                    $startZ = $endZ;
                    $endZ = $backup;
                }
                $this->StopSound($player);
                if ($startX <= (int)$player->x and $startZ <= (int)$player->z and $endX >= (int)$player->x and $endZ >= (int)$player->z) {
                    $pk = new PlaySoundPacket;
                    $pk->soundName = $data["sound"];
                    $pk->x = (int)$player->x;
                    $pk->y = (int)$player->y;
                    $pk->z = (int)$player->z;
                    $pk->volume = 1;
                    $pk->pitch = 1;
                    $player->senddataPacket($pk);
                    $this->playersounds[$name] = array(
                        "sound" => $data["sound"],
                        "level" => $level->getName(),
                        "startx" => $startX,
                        "startz" => $startZ,
                        "endx" => $endX,
                        "endz" => $endZ
                    );
                    break;
                }
            }
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }

    }

    public function StopSound(Player $player)
    {
        try {
            $name = $player->getName();
            if (!isset($this->playersounds[$name])) return;
            $pk = new StopSoundPacket;
            $pk->soundName = $this->playersounds[$name]["sound"];
            $player->sendDataPacket($pk);
            unset($this->playersounds[$name]);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onError($e, $player);
        }
    }
}