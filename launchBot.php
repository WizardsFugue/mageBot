<?php


require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/KarmaCommandListener.php';

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler("exception_error_handler");

$app = new Silex\Application();
$app->register(new Silex\Provider\ValidatorServiceProvider());


$eventDispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();

$ircConnection   = new \Whisnet\IrcBotBundle\Connection\Socket(
    'irc.freenode.net',
    6667,
    $app['validator'],
    $eventDispatcher
);

$commandPrefix = '!bot';
$user          = array(
    'username' => 'thebot|'.rand(55,950),
    'mode'     => '8',
    'realname' => 'mana-bot',
);
$channels      = array(
    '#magento-de',
    '#magento-dev',
);


$listener['whisnet_irc_bot.command_PING'] 
    = array(new \Whisnet\IrcBotBundle\EventListener\Irc\Messages\PingListener($ircConnection, $eventDispatcher),'onData');
$listener['whisnet_irc_bot.irc_command_PRIVMSG'] 
    = array(new \Whisnet\IrcBotBundle\EventListener\Irc\Messages\PrivMsgListener($ircConnection, $eventDispatcher, $commandPrefix),'onData');
$listener['whisnet_irc_bot.data_from_server'] 
    = array(new \Whisnet\IrcBotBundle\EventListener\Irc\ServerRequestListener($ircConnection, $eventDispatcher),'onData');
    #= '\Whisnet\IrcBotBundle\EventListener\Irc\ServerRequestListener';

foreach($listener as $eventName => $eventHandler ){
    $eventDispatcher->addListener($eventName,$eventHandler,10);
}
unset($listener);

$loadUserCoreListener = new Whisnet\IrcBotBundle\EventListener\Plugins\Core\LoadUserCoreListener($ircConnection);
$loadUserCoreListener->setConfig($user, $channels);
$listener['whisnet_irc_bot.post_connection'] = array($loadUserCoreListener,'onCore');


$listener['whisnet_irc_bot.bot_command_join'] 
    = array(new \Whisnet\IrcBotBundle\EventListener\Plugins\Commands\JoinCommandListener($ircConnection),'onCommand');

$listener['whisnet_irc_bot.bot_command_part'] 
    = array(new \Whisnet\IrcBotBundle\EventListener\Plugins\Commands\PartCommandListener($ircConnection),'onCommand');
/*
$listener['whisnet_irc_bot.bot_command_quit']
    = array(new \Whisnet\IrcBotBundle\EventListener\Plugins\Commands\QuitCommandListener($ircConnection),'onCommand');
*/
$listener['whisnet_irc_bot.bot_command_info'] 
    = array(new \Whisnet\IrcBotBundle\EventListener\Plugins\Commands\InfoCommandListener($ircConnection),'onCommand');
$listener['whisnet_irc_bot.bot_command_datetime']
    = array(new \Whisnet\IrcBotBundle\EventListener\Plugins\Commands\DateTimeCommandListener($ircConnection),'onCommand');

$seenCommandListener = new \Whisnet\IrcBotBundle\EventListener\Plugins\Commands\SeenCommandListener($ircConnection);
$seenCommandListener->setCacheDir(__DIR__.'/cache');
$listener['whisnet_irc_bot.bot_command_seen'] = array($seenCommandListener,'onCommand');
$listener['whisnet_irc_bot.irc_command_PRIVMSG'] = array($seenCommandListener,'onUpdateInformation');




$helpCommandListener = new \Whisnet\IrcBotBundle\EventListener\Plugins\Commands\HelpCommandListener($ircConnection);
$helpList = new SplDoublyLinkedList();
$helpList[] = array(
    'command'   => 'seen',
    'arguments' => '$nick',
);
$helpList[] = array(
    'command'   => 'karma',
    'arguments' => '$topic',
);
$helpList[] = array(
    'command'   => 'info',
);
$helpCommandListener->setConfig( $commandPrefix, $helpList) ;
$listener['whisnet_irc_bot.bot_command_help'] = array($helpCommandListener,'onCommand');

foreach($listener as $eventName => $eventHandler ){
    $eventDispatcher->addListener($eventName,$eventHandler,20);
}


$karmaCommandListener = new \KarmaCommandListener($ircConnection);
$karmaCommandListener->setCacheDir(__DIR__.'/cache');
$eventDispatcher->addListener(
    'whisnet_irc_bot.bot_command_karma',
    array($karmaCommandListener,'onCommand')
    ,30);
$eventDispatcher->addListener(
    'whisnet_irc_bot.irc_command_PRIVMSG',
    array($karmaCommandListener,'onUpdateInformation')
    ,30);


$ircConnection->connect();

do {
    try{
    $eventDispatcher->dispatch(
        'whisnet_irc_bot.data_from_server',
        new \Whisnet\IrcBotBundle\Event\DataFromServerEvent(
            \Whisnet\IrcBotBundle\Utils\Utils::cleanUpServerRequest($ircConnection->getData())
        )
    );
    }catch( ErrorException $e ){
        echo $e->getSeverity().': '.$e->getMessage();
        sleep(5);
        $ircConnection->connect();
    }/**/
} while (true);



