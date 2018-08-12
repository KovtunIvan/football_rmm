<?php
require __DIR__ . '/vendor/autoload.php';

use Slack\Message\Attachment;
use Slack\Message\Attachment\Builder;
use Slack\Message\Attachment\AttachmentField;

$token = 'xoxb-4160842300-414695393236-RUNsqqCQeHM9Lpq0cYd24FKP';
$loop = React\EventLoop\Factory::create();
$slack = new wrapi\slack\slack($token);
$client = new Slack\RealTimeClient($loop);
$client->setToken($token);
$client->connect();

$status = "false";
$amountOfPlayers = "0";
$players = [];

$client->on('reaction_added', function ($data) use ($client) {
    global $status, $amountOfPlayers;
    echo "data: " . $data . "\n";
});

$client->on('message', function ($data) use ($client, $slack) {
    global $status, $amountOfPlayers, $players;
    if (stripos($data['text'], '+') === 0) {
        switch ($status) {
            case "onair":
                $user = $slack->users->info(array("user" => $data['user']));
                $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user) {
                    $message = $client->getMessageBuilder()
                        ->addAttachment(new Attachment('', $user["user"]["real_name"] . ", извини, драка уже началась", null, "#a63639"))
                        ->setChannel($channel)
                        ->create();
                    $client->postMessage($message);
                });
                break;
            case "waiting":
                if (!in_array($data['user'], $players)) {
                    $players[] = $data['user'];
                    $amountOfPlayers--;
                    if ($amountOfPlayers > 0) {
                        $status = "waiting";
                        $user = $slack->users->info(array("user" => $data['user']));
                        $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user, $amountOfPlayers) {
                            $message = $client->getMessageBuilder()
                                ->addAttachment(new Attachment('', $user["user"]["real_name"]." вступает в игру!", null, "#36a64f"))
                                ->setChannel($channel)
                                ->create();
                            $client->postMessage($message);
                        });
                        $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user, $amountOfPlayers) {
                            $message = $client->getMessageBuilder()
                                ->addAttachment(new Attachment('', "нужно еще $amountOfPlayers", null, "#c4c4c4"))
                                ->setChannel($channel)
                                ->create();
                            $client->postMessage($message);
                        });
                    } else {
                        $status = "onair";
                        $user = $slack->users->info(array("user" => $data['user']));
                        $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user, $amountOfPlayers) {
                            $message = $client->getMessageBuilder()
                                ->addAttachment(new Attachment('', $user["user"]["real_name"]." вступает в игру!", null, "#36a64f"))
                                ->setChannel($channel)
                                ->create();
                            $client->postMessage($message);
                        });
                        $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $amountOfPlayers) {
                            $message = $client->getMessageBuilder()
                                ->addAttachment(new Attachment('', "Да начнется битва!", null, "#36a64f"))
                                ->setChannel($channel)
                                ->create();
                            $client->postMessage($message);
                        });

                    }
                }
                break;
        }
    }



});
$loop->run();