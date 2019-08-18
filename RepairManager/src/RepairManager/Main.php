<?php

namespace RepairManager;

use pocketmine\block\BlockLegacyIds;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Durable;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;

use onebone\economyapi\EconomyAPI;

class Main extends PluginBase implements Listener {
    private $config, $c;
    private $data, $db;

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        @mkdir($this->getDataFolder());
        $this->config = new Config ($this->getDataFolder() . "Config.yml", Config::YAML, [
                "prefix" => "§f[ §6수리 §f] §r§7",
                "repair-price" => 5000,
                "repair-percent" => 80
        ]);
        $this->data = new Config ($this->getDataFolder() . "Data.yml", Config::YAML, [
                "signs" => []
        ]);
        $this->c = $this->config->getAll();
        $this->db = $this->data->getAll();
    }

    public function onSignPlace(SignChangeEvent $event) {
        $block = $event->getBlock();
        $x = $block->getPos()->x;
        $y = $block->getPos()->y;
        $z = $block->getPos()->z;
        $line = $event->getNewText();
        if ($line->getLine(0) == "수리") {
            if (!$event->getPlayer()->isOp())
                return;
            $line->setLine(0, "§l§f[ §6수리 상점 §f]");
            $line->setLine(1, "§f가격: §a" . $this->c ["repair-price"]);
            $line->setLine(3, "§f터치시 §f수리§f됩니다.");
            $event->getPlayer()->sendMessage($this->c ["prefix"] . "수리 표지판을 설치했습니다.");
            $this->setRepairSign($x, $y, $z);
            $this->save();
        }
    }

    public function setRepairSign($x, $y, $z) {
        $this->db ["signs"] [$x . ":" . $y . ":" . $z] = true;
    }

    public function save() {
        $this->config->setAll($this->c);
        $this->config->save();
        $this->data->setAll($this->db);
        $this->data->save();
    }

    public function unsetRepairSign(BlockBreakEvent $event) {
        $block = $event->getBlock();
        $x = $block->getPos()->x;
        $y = $block->getPos()->y;
        $z = $block->getPos()->z;
        if ($block->getId() == BlockLegacyIds::SIGN_POST || $block->getId() == BlockLegacyIds::WALL_SIGN) {
            if ($event->getPlayer()->isOp()) {
                if (isset ($this->db ["signs"] [$x . ":" . $y . ":" . $z])) {
                    unset ($this->db ["signs"] [$x . ":" . $y . ":" . $z]);
                    $event->getPlayer()->sendMessage($this->c ["prefix"] . "성공적으로 수리 표지판을 제거했습니다.");
                }
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        $command = $command->getName();
        if ($command == "수리") {
            if (!$sender->isOp()) {
                $sender->sendMessage($this->c ["prefix"] . "당신은 이 명령어를 사용 할 권한이 없습니다.");
                return true;
            }
            if (!isset ($args [0]))
                $args [0] = 'x';
            switch ($args [0]) {
                case "가격설정" :
                    if (!isset ($args [1])) {
                        $sender->sendMessage($this->c ["prefix"] . "수리 가격을 입력하세요.");
                        return true;
                    }
                    if (!is_numeric($args [1])) {
                        $sender->sendMessage($this->c ["prefix"] . "가격은 숫자로 입력해야합니다.");
                        return true;
                    }
                    $this->c ["repair-price"] = $args [1];
                    $sender->sendMessage($this->c ["prefix"] . "수리 가격을 §a{$args[1]}원§7으로 설정했습니다.");
                    $this->save();
                    break;
                case "확률설정" :
                    if (!isset ($args [1])) {
                        $sender->sendMessage($this->c ["prefix"] . "확률을 입력하세요.");
                        return true;
                    }
                    if (!is_numeric($args [1])) {
                        $sender->sendMessage($this->c ["prefix"] . "확률은 숫자로 입력해야합니다.");
                        return true;
                    }
                    $this->c ["repair-percent"] = $args [1];
                    $sender->sendMessage($this->c ["prefix"] . "수리 확률을 §a{$args[1]}%§7으로 설정했습니다.");
                    $this->save();
                    break;
                default :
                    $this->help($sender);
                    break;
            }
        }
        return true;
    }

    public function help(CommandSender $sender) {
        $sender->sendMessage($this->c ["prefix"] . "/수리 가격설정 <가격>");
        $sender->sendMessage($this->c ["prefix"] . "/수리 확률설정 <확률(1~100)>");
    }

    public function onInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();
        $id = $item->getId();
        $block = $event->getBlock();
        $x = $block->getPos()->x;
        $y = $block->getPos()->y;
        $z = $block->getPos()->z;
        $damage = $item->getMeta();
        $money = $this->c ["repair-price"];
        if ($item instanceof Durable) {
            if (isset ($this->db ["signs"] [$x . ":" . $y . ":" . $z])) {
                if ($id == 0) {
                    $player->sendMessage($this->c ["prefix"] . "아이템을 들고 터치해주세요.");
                    return true;
                }
                if ($damage < 1) {
                    $player->sendMessage($this->c ["prefix"] . "해당 아이템은 내구도가 닳지 않았습니다.");
                    return true;
                }
                if (EconomyAPI::getInstance()->myMoney($player) < $money) {
                    $player->sendMessage($this->c ["prefix"] . "당신은 수리 할 돈이 부족합니다.");
                    return true;
                }
                $rand = mt_rand(1, ( int ) $this->getPercent());
                if ($rand !== 1) {
                    $player->sendMessage($this->c ["prefix"] . "당신은 수리에 실패했습니다..");
                    EconomyAPI::getInstance()->reduceMoney($player, $money);
                    return true;
                }
                EconomyAPI::getInstance()->reduceMoney($player, $money);
                $item->setDamage(0);
                $player->getInventory()->setItemInHand($item);
                $player->sendMessage($this->c ["prefix"] . "아이템 수리에 성공하였습니다.");
            }
        }
    }

    public function getPercent() {
        $a = $this->c ["repair-percent"];
        if (!is_numeric($a))
            return false;
        if ($a > 100)
            return false;
        $b = ceil(( int ) 100 / $a);
        return $b;
    }

    public function onDisable() {
        $this->save();
    }
}
