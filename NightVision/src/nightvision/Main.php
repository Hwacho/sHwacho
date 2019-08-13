<?php

namespace nightvision;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {
    public $prefix = "§l§f[ §6야간투시 §f] §r§7";

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onHeld(PlayerItemHeldEvent $event) {
        $player = $event->getPlayer();
        if ($event->getItem()->getId() == 50) {
            $player->getEffects()->add(new EffectInstance(VanillaEffects::NIGHT_VISION(), 99999, 1));
            $player->sendMessage($this->prefix . "야간투시가 지급 되었습니다.");
        }
    }
}
	
