<?php


use Slack\Message\Attachment;

require_once 'vendor/autoload.php';

class Slack
{

    private $client;

    private $loop;

    private $slackApiClient;

    private $csv;

    private $status;

    private $amountOfPlayers;

    private $players;

    public function __construct()
    {
        $this->loop = React\EventLoop\Factory::create();
        $this->slackApiClient = new wrapi\slack\slack('xoxb-4160842300-414695393236-P1EF0TEMNZGFWGyUihVGOFfF');
        $this->client = new Slack\RealTimeClient($this->loop);
        $this->client->setToken('xoxb-4160842300-414695393236-P1EF0TEMNZGFWGyUihVGOFfF');
        $this->csv = array_map('str_getcsv', file('table.csv'));
        $this->client->connect();
        $this->players = [];
        $this->status = "false";
        $this->amountOfPlayers = "0";
    }

    public function init()
    {
        $client = $this->client;
        $slackApiClient = $this->slackApiClient;
        $csv = $this->csv;


        $client->on('message', function ($data) use ($client, $slackApiClient, $csv) {

            if ($data['text'] == "rating") {
                $this->showRating($data, $csv);
            }
            if (stristr($data['text'], 'rating <@')) {
                $this->showRatingByUser($data, $this->csv, $slackApiClient);
                $this->addWin($this->csv, $data['user']);
            }
            if (stripos($data['text'], '+') === 0) {
                $this->addPlayersToGame($data);
            }
            if (stripos($data['text'], '!match') === 0){
                $this->startGame($data);
            }
        });
        $this->loop->run();
    }

    public function saveForDB($csv)
    {
        $fp = fopen('table.csv', 'w');
        foreach ($csv as $fields) {
            fputcsv($fp, $fields, ';', '"');
        }
        fclose($fp);

        $this->csv = array_map('str_getcsv', file('table.csv'));
    }

    public function addWin($csv, $id)
    {
        foreach ($csv as $item) {
                $playerData = explode(';', $item[0]);
                if ($playerData[0] == $id) {
                    $playerData[2]++;
                    $savedItem = implode(';', $playerData);
                }
                $csv[key($csv)] = (array)$savedItem;
            }
        $this->saveForDB($csv);
    }

    public function addLose($csv, $id)
    {
        foreach ($csv as $item) {
            $playerData = explode(';', $item[0]);
            if ($playerData[0] == $id) {
                $playerData[2]--;
                $savedItem = implode(';', $playerData);
            }
            $csv[key($csv)] = (array)$savedItem;
        }
        $this->saveForDB($csv);

    }

    public function showRating($data, $csv)
    {
        $client = $this->client;

        $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $csv) {
            foreach ($csv as $string) {
                foreach ($string as $player) {
                    $playerData = explode(';', $player);
                    $messageInfo = "Имя " . $playerData[1] . "\n количество побед " . $playerData[2];
                    $color = rand(0, 999999);
                    $message = $client->getMessageBuilder()
                        ->addAttachment(new Attachment('', $messageInfo, null, "#" . $color))
                        ->setChannel($channel)
                        ->create();
                    $client->postMessage($message);
                }
            };

        });
    }

    public function showRatingByUser($data, $csv, $slackApiClient)
    {
        $client = $this->client;

        $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $slackApiClient, $csv) {
            $user = $slackApiClient->users->info(array("user" => $data['user'])); // имя юзера
            foreach ($csv as $string) {
                foreach ($string as $player) {
                    $playerData = explode(';', $player);
                    if ($playerData[0] == $user["user"]["id"]) {
                        $messageInfo = "Имя " . $playerData[1] . "\n количество побед " . $playerData[2];
                        $color = rand(0, 999999);
                        $message = $client->getMessageBuilder()
                            ->addAttachment(new Attachment('', $messageInfo, null, "#" . $color))
                            ->setChannel($channel)
                            ->create();
                        $client->postMessage($message);
                    }
                }
            };
        });
    }

    public function addPlayersToGame($data)
    {

        $client = $this->client;
        $slackApiClient = $this->slackApiClient;
        $players = $this->players;
        $amountOfPlayers = $this->amountOfPlayers;
        $status=$this->status;

        switch ($status) {
            case "onair":
                $user = $slackApiClient->users->info(array("user" => $data['user']));
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
                        $this->status = "waiting";
                        $user = $slackApiClient->users->info(array("user" => $data['user']));
                        $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user, $amountOfPlayers) {
                            $message = $client->getMessageBuilder()
                                ->addAttachment(new Attachment('', $user["user"]["real_name"] . " вступает в игру!", null, "#36a64f"))
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
                        $this->status = "onair";
                        $user = $slackApiClient->users->info(array("user" => $data['user']));
                        $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user, $amountOfPlayers) {
                            $message = $client->getMessageBuilder()
                                ->addAttachment(new Attachment('', $user["user"]["real_name"] . " вступает в игру!", null, "#36a64f"))
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

    public function startGame($data)
    {

        $client = $this->client;
        $slackApiClient = $this->slackApiClient;
        $players = $this->players;
        $status = $this->status;


        if ($status != "waiting" && $status != "onair") {
            $msg = explode('h', $data['text']);
            switch ($msg[1]) {
                case 2:
                    $amountOfPlayers = "2";
                    $this->status = "waiting";
                    break;
                case 4:
                    $amountOfPlayers = "4";
                    $this->status = "waiting";
                    break;
                default;
                    $amountOfPlayers = " укажите кол-во игроков (2 или 4), например !match4";
                    $this->status = "false";
                    break;
            }
            switch ($this->status) {
                case "false":
                    $user = $slackApiClient->users->info(array("user" => $data['user']));
                    $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user, $amountOfPlayers) {
                        $message = $client->getMessageBuilder()
//                ->addAttachment(new Attachment('My Attachment', 'attachment text', null, "#36a64f"))
                            ->addAttachment(new Attachment('', $user["user"]["real_name"] . "," . $this->amountOfPlayers, null, "#a63639"))
                            ->setChannel($channel)
                            ->create();
                        $client->postMessage($message);
                    });
                    break;
                case "waiting":
                    $players[] = $data['user'];
                    $user = $slackApiClient->users->info(array("user" => $data['user']));
                    $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user, $amountOfPlayers) {
                        $message = $client->getMessageBuilder()
                            ->addAttachment(new Attachment('', $user["user"]["real_name"] . " собирает игру на $amountOfPlayers игроков!", null, "#36a64f"))
//                ->addAttachment(new Attachment('', 'attachment text', null, "#a63639"))
                            ->setChannel($channel)
                            ->create();
                        $client->postMessage($message);
                    });
                    $amountOfPlayers--;
                    $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user, $amountOfPlayers) {
                        $message = $client->getMessageBuilder()
                            ->addAttachment(new Attachment('', "нужно еще $amountOfPlayers", null, "#c4c4c4"))
//                ->addAttachment(new Attachment('', 'attachment text', null, "#a63639"))
                            ->setChannel($channel)
                            ->create();
                        $client->postMessage($message);
                    });
                    break;
            }
        } else {
            $user = $slackApiClient->users->info(array("user" => $data['user']));
            $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user) {
                $message = $client->getMessageBuilder()
                    ->addAttachment(new Attachment('', $user["user"]["real_name"] . ", извини, игра уже идет/собирается", null, "#a63639"))
                    ->setChannel($channel)
                    ->create();
                $client->postMessage($message);
            });
        }
    }


}

$slackClient = new Slack();
$slackClient->init();