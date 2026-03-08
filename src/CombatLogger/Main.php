<?php

namespace CombatLogger;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\player\Player;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;

use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener{

    private array $combatTagged = [];

    public function onEnable() : void{
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    private function tagPlayer(Player $player) : void{
        $time = $this->getConfig()->get("time");
        $msg = $this->getConfig()->getNested("messages.player-tagged");

        $this->combatTagged[$player->getName()] = time() + $time;

        $player->sendMessage(TextFormat::colorize($msg));
    }

    public function onDamage(EntityDamageByEntityEvent $event) : void{

        $damager = $event->getDamager();
        $victim = $event->getEntity();

        if($damager instanceof Player && $victim instanceof Player){
            $this->tagPlayer($damager);
            $this->tagPlayer($victim);
        }
    }

    public function onCommandPreprocess(PlayerCommandPreprocessEvent $event) : void{

        $player = $event->getPlayer();
        $cmd = strtolower(explode(" ", $event->getMessage())[0]);
        $cmd = str_replace("/", "", $cmd);

        if(isset($this->combatTagged[$player->getName()])){

            if(time() > $this->combatTagged[$player->getName()]){
                unset($this->combatTagged[$player->getName()]);
                $player->sendMessage(TextFormat::colorize($this->getConfig()->getNested("messages.player-tagged-timeout")));
                return;
            }

            $banned = $this->getConfig()->get("banned-commands");

            if(in_array($cmd, $banned)){
                $event->cancel();
                $player->sendMessage(TextFormat::colorize($this->getConfig()->getNested("messages.player-run-banned-command")));
            }
        }
    }

    public function onQuit(PlayerQuitEvent $event) : void{

        $player = $event->getPlayer();

        if(isset($this->combatTagged[$player->getName()])){

            if(time() < $this->combatTagged[$player->getName()]){

                if($this->getConfig()->get("kill-on-log")){
                    $player->setHealth(0);
                }

                unset($this->combatTagged[$player->getName()]);
            }
        }
    }
}
