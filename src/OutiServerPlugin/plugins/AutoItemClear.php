<?php

declare(strict_types=1);

namespace OutiServerPlugin\plugins;

// 落ちているアイテム自動消去
use ArgumentCountError;
use Error;
use Exception;
use InvalidArgumentException;
use OutiServerPlugin\Main;
use OutiServerPlugin\Tasks\SendLog;
use pocketmine\entity\Creature;
use pocketmine\entity\Human;
use pocketmine\entity\object\ExperienceOrb;
use pocketmine\entity\object\ItemEntity;
use pocketmine\scheduler\ClosureTask;
use TypeError;

class AutoItemClear
{
    private Main $plugin;
    public int $seconds;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        try {
            $this->seconds = $this->plugin->config->get('ClearItemTick', 3600);

            $this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(
                function (int $currentTick): void {
                    try {
                        $this->seconds--;
                        // 実行時間になったら
                        if ($this->seconds === 0) {
                            $entitiesCleared = 0;
                            foreach ($this->plugin->getServer()->getLevels() as $level) {
                                foreach ($level->getEntities() as $entity) {
                                    if ($entity instanceof ItemEntity) {
                                        $entity->flagForDespawn();
                                        $entitiesCleared++;
                                    } else if ($entity instanceof Creature && !$entity instanceof Human) {
                                        $entity->flagForDespawn();
                                        $entitiesCleared++;
                                    } else if ($entity instanceof ExperienceOrb) {
                                        $entity->flagForDespawn();
                                        $entitiesCleared++;
                                    }
                                }
                            }

                            $this->plugin->getServer()->getAsyncPool()->submitTask(new SendLog($this->plugin->config->get('DiscordPluginLog_Webhook', ''), "合計{$entitiesCleared}個の落ちていたアイテムを削除しました"));
                            $this->seconds = $this->plugin->config->get('ClearItemTick', 3600);
                            $this->plugin->getLogger()->info("合計{$entitiesCleared}個のアイテムを削除しました");
                        } elseif (in_array($this->seconds, $this->plugin->config->get("WarningClearItemTick", array(60, 5)))) {
                            $this->plugin->getServer()->broadcastMessage("§b[おうちサーバー] >> §cあと{$this->seconds}秒で落ちているアイテムが削除されます、ご注意ください。");
                        }
                    } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
                        $this->plugin->errorHandler->onErrorNotPlayer($e);
                    }
                }
            ), 20);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
}