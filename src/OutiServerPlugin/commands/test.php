<?php

declare(strict_types=1);

namespace OutiServerPlugin\commands;

use OutiServerPlugin\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class money extends Command
{
    private Main $plugin;

    public function __construct(string $name, Main $plugin, string $description = "", ?string $usageMessage = null, array $aliases = []){
        parent::__construct($name, $description, $usageMessage, $aliases);
        $this->plugin = $plugin;
        $this->setDescription("");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        $sender->sendMessage("Tada");
    }
}