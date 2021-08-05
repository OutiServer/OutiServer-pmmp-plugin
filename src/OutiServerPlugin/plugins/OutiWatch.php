<?php



namespace OutiServerPlugin\plugins;

use ArgumentCountError;
use Error;
use ErrorException;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\SimpleForm;
use OutiServerPlugin\Main;
use pocketmine\Player;
use TypeError;

class OutiWatch
{
    private Main $plugin;
    public array $check;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function Form(Player $player)
    {
        try {
            $form = new SimpleForm(function (Player $player, $data) {
                try {
                    unset($this->check[$player->getName()]);
                    if ($data === null) return true;

                    switch ($data) {
                        case 0:
                            break;
                        case 1:
                            $this->plugin->money->Form($player);
                            break;
                        case 2:
                            $this->plugin->adminshop->AdminShop($player);
                            break;
                        case 3:
                            $this->plugin->land->land($player);
                            break;
                        case 4:
                            $this->plugin->teleport->Form($player);
                            break;
                        case 5:
                            $this->plugin->announce->Form($player);
                            break;
                        case 6:
                            $this->plugin->casino->Form($player);
                            break;
                        case 7:
                            $this->plugin->admin->AdminForm($player);
                            break;
                    }
                    return true;
                } catch (Error | TypeError | Exception | ErrorException | InvalidArgumentException | ArgumentCountError $e) {
                    $this->plugin->errorHandler->onError($e, $player);
                }

                return true;
            });

            $form->setTitle("OutiWatch");
            $form->addButton("閉じる");
            $form->addButton("お金関連");
            $form->addButton("AdminShop");
            $form->addButton("土地");
            $form->addButton("テレポート");
            $form->addButton("運営からのお知らせ");
            if ($player->isOp()) {
                $form->addButton("カジノ");
                $form->addButton("管理系");
            }
            $player->sendForm($form);
        } catch (Error | TypeError | Exception | InvalidArgumentException | ArgumentCountError $e) {
            $this->plugin->errorHandler->onErrorNotPlayer($e);
        }
    }
}