<?php

declare(strict_types=1);

namespace CombatLogger;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\CommandEvent;
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
         Combat timer task
        */

        $this->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function() : void{

                foreach($this->combat as $player => $time){

                    if(time() >= $time){

                        $online = $this->getServer()->getPlayerExact($player);

                        if($online instanceof Player){

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
     PvP Event
    */

    public function onDamage(EntityDamageByEntityEvent $event) : void{

        $damager = $event->getDamager();
        $entity = $event->getEntity();

        if(!$damager instanceof Player || !$entity instanceof Player){
            return;
        }

        /*
         Teaming support
         Prevent teammate combat tagging
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
         Only send enter message once
        */

        if(!isset($this->combat[$player->getName()])){

            $player->sendMessage(
                $this->getConfig()->get("combat-enter-message")
            );
        }

        /*
         Refresh timer
        */

        $this->combat[$player->getName()] = time() + $this->combatTime;
    }

    /*
     Combat check
    */

    public function isInCombat(Player $player) : bool{
        return isset($this->combat[$player->getName()]);
    }

    /*
     Command blocker
    */

    public function onCommandEvent(CommandEvent $event) : void{

        $sender = $event->getSender();

        if(!$sender instanceof Player){
            return;
        }

        if(!$this->isInCombat($sender)){
            return;
        }

        /*
         Block ALL commands
        */

        if($this->getConfig()->get("block-all-commands")){

            $event->cancel();

            $sender->sendMessage(
                $this->getConfig()->get("command-block-message")
            );

            return;
        }

        /*
         Optional blocked commands
        */

        $commandLine = strtolower($event->getCommand());

        $args = explode(" ", $commandLine);

        $command = strtolower($args[0]);

        foreach($this->getConfig()->get("blocked-commands") as $blocked){

            if($command === strtolower($blocked)){

                $event->cancel();

                $sender->sendMessage(
                    $this->getConfig()->get("command-block-message")
                );

                return;
            }
        }
    }

    /*
     Combat logging punishment
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
