<?php

use Whisnet\IrcBotBundle\EventListener\Plugins\Commands\CommandListener;
use Whisnet\IrcBotBundle\Event\BotCommandFoundEvent;
use Whisnet\IrcBotBundle\Event\IrcCommandFoundEvent;


class KarmaCommandListener extends CommandListener
{
    /**
     * @var string
     */
    private $cacheFile;

    /**
     * {@inheritdoc}
     */
    public function onCommand(BotCommandFoundEvent $event)
    {
        $arguments = $event->getArguments();

        if (!isset($arguments[0])) {
        } else {
            $karma = $this->readFromKarma($arguments[0]);
            if ($karma) {
                $this->sendMessage(array($event->getChannel()), 'The Karma of '.$arguments[0].' is '.$karma.' ');
            } else {
                $this->sendMessage( array($event->getChannel()), 'Sorry '.$arguments[0].' has neutral Karma');
            }
        }
    }


    /**
     * @param IrcCommandFoundEvent $event
     * @throws CommandException
     * @return boolean
     */
    public function onUpdateInformation(IrcCommandFoundEvent $event)
    {
        
        $data = $event->getData();
        $msg = $data[4];
        
        $operation = substr($msg, -2);
        if( $operation === '++' || $operation === '--' ){
            $topic = substr($msg, 0, -2);
            $this->updateKarma( $topic, $operation === '++');
        }

        unset($dateTime);
        unset($data);
    }

    private function updateKarma( $topic, $increment ){

        $seenFile = file_exists($this->cacheFile) ? file_get_contents($this->cacheFile) : false;

        if (false !== $seenFile) {
            $seenArray = json_decode($seenFile, true);

            unset($seenFile);
        } else {
            $seenArray = array();
        }
        

        $topic = strtolower($topic);
        if( isset($seenArray[$topic]) ){
            $seenArray[$topic] += $increment ? 1 : -1;
        }else{
            $seenArray[$topic] = $increment ? 1 : -1;
        }

        file_put_contents($this->cacheFile, json_encode($seenArray));
        unset($seenArray);

        return $this;
    }
    /**
     * @param string $topic
     * @return false if no record is available, string if we found one
     */
    private function readFromKarma($topic)
    {
        $result = false;

        $seenFile = file_get_contents($this->cacheFile);

        if (false !== $seenFile) {
            $seenArray = json_decode($seenFile, true);

            unset($seenFile);
        } else {
            $seenArray = array();
        }

        $topic = strtolower($topic);
        if (isset($seenArray[$topic])) {
            $result = $seenArray[$topic];
        }

        unset($seenArray);

        return $result;
    }
    
    /**
     * @param string $cacheDir
     */
    public function setCacheDir($cacheDir)
    {
        $this->cacheFile = $cacheDir.DIRECTORY_SEPARATOR.'irc-bot-karma.json';
    }
}