<?php

declare(strict_types=1);

namespace CombatLogger;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase implements Listener{

    private array $combat = [];

    private int $combatTime;

    public function onEnable() : void{

        $this->saveDefaultConfig();

        $this->combatTime = (int) $this->getConfig()->get("combat-time");

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        /*
         Combat timer checker
        */

        $this->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function() : void{

                foreach($this->combat as $player => $time){

                    if(time() >= $time){

                        $online = $this->getServer()->getPlayerExact($player);

                        if($online !== null){

                            $online->sendMessage(
                                $this->getConfig()->get("combat-leave-message")
                            );
                        }

                        unset($this->combat[$player]);
                    }
                }
            }),
            20
        );
    }

    /*
     Handle PvP Combat
    */

    public function onDamage(EntityDamageByEntityEvent $event) : void{

        $damager = $event->getDamager();
        $entity = $event->getEntity();

        if(!$damager instanceof Player || !$entity instanceof Player){
            return;
        }

        /*
         Teaming Plugin Support
         Prevent teammates from combat tagging
        */

        $teaming = $this->getServer()->getPluginManager()->getPlugin("Teaming");

        if($teaming !== null){

            if(method_exists($teaming, "getTeamManager")){

                if($teaming->getTeamManager()->sameTeam(
                    $damager->getName(),
                    $entity->getName()
                )){

                    return;
                }
            }
        }

        /*
         Tag players
        */

        $this->tagPlayer($damager);

        $this->tagPlayer($entity);
    }

    /*
     Combat tagging
    */

    public function tagPlayer(Player $player) : void{

        if($player->hasPermission("combatlogger.bypass")){
            return;
        }

        /*
         Only send message once
        */

        if(!isset($this->combat[$player->getName()])){

            $player->sendMessage(
                $this->getConfig()->get("combat-enter-message")
            );
        }

        /*
         Refresh timer silently
        */

        $this->combat[$player->getName()] = time() + $this->combatTime;
    }

    /*
     Check combat
    */

    public function isInCombat(Player $player) : bool{
        return isset($this->combat[$player->getName()]);
    }

    /*
     Block commands in combat
    */

    public function onCommandPreprocess(PlayerCommandPreprocessEvent $event) : void{

        $player = $event->getPlayer();

        if(!$this->isInCombat($player)){
            return;
        }

        /*
         Block ALL commands
        */

        if($this->getConfig()->get("block-all-commands")){

            $event->cancel();

            $player->sendMessage(
                $this->getConfig()->get("command-block-message")
            );

            return;
        }

        /*
         Optional specific blocked commands
        */

        $message = strtolower(substr($event->getMessage(), 1));

        $args = explode(" ", $message);

        $command = strtolower($args[0]);

        foreach($this->getConfig()->get("blocked-commands") as $blocked){

            if($command === strtolower($blocked)){

                $event->cancel();

                $player->sendMessage(
                    $this->getConfig()->get("command-block-message")
                );

                return;
            }
        }
    }

    /*
     Kill combat loggers
    */

    public function onQuit(PlayerQuitEvent $event) : void{

        $player = $event->getPlayer();

        if(!$this->isInCombat($player)){
            return;
        }

        if($this->getConfig()->get("kill-on-logout")){

            $player->setHealth(0);
        }

        unset($this->combat[$player->getName()]);
    }
}
