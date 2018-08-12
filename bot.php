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

    private $amountOfWinners;

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
        $this->amountOfWinners = "0";

    }

    public function init()
    {
        $client = $this->client;
        $slackApiClient = $this->slackApiClient;
        $csv = $this->csv;


        $client->on('reaction_added', function ($data) use ($client, $slackApiClient) {
            echo "data: " . $data['reaction'] . $data['item_user'] . "\n";
            if ($this->amountOfWinners > 0) {
                echo "got >0";
                if ($data['reaction'] == 'muscle') {
                    echo "got reaction";
                    echo $this->players[0];
                    if (in_array($data['item_user'], $this->players)) {
                        echo "got players";
                        echo $this->amountOfWinners;
                        echo $this->players[0];
                        $this->addWin($this->csv, $data['item_user']);
                        unset($this->players[array_search($data['item_user'], $this->players)]);
                        $this->amountOfWinners--;
                        echo $this->amountOfWinners;
                        echo '<pre>', var_dump($this->players), '</pre>';

                    }
                }
            }
            if ($this->amountOfWinners == 0) {
                foreach ($this->players as $key => $player) {
                    $this->addLose($this->csv, $player);
                }
                $this->players = [];
                $this->status = "false";
                $this->amountOfPlayers = "0";
                $this->amountOfWinners = "0";

                $client->getChannelGroupOrDMByID($data['item']['channel'])->then(function ($channel) use ($client, $data,$slackApiClient) {
                    $message = $client->getMessageBuilder()
                        ->addAttachment(new Attachment('', "Ух, ну и побоище, поздравляем победителей, насмехаемся над проигравшими, БОЛЬШЕ ФУТБОЛА БОГУ ФУТБОЛА!", "", "#be00db", null, ["thumb_url" => "https://st2.depositphotos.com/1001951/6825/i/450/depositphotos_68253005-stock-photo-man-with-conceptual-spiritual-body.jpg"]))
                        ->setChannel($channel)
                        ->create();
                    $client->postMessage($message);
                });
            }
        });


        $client->on('message', function ($data) use ($client, $slackApiClient,$csv) {
            /*$slackApiClient->chat->postMessage(array(
                    "channel" => "GC66DNNKS",
                    "parse" => "full",
                    "link_names" => 1,
                    "mrkdwn" => true,
                    "text" => '*bold* `code` _italic_ ~strike~ <@UBJ6FD69X> <http://foo.com/|foo> :smile: <!here>',
                    "username" => "Wrapi Bot",
                    "as_user" => false,
                    "icon_url" => "http://icons.iconarchive.com/icons/ampeross/qetto/48/icon-developer-icon.png",
                    "unfurl_links" => true,
                    "unfurl_media" => false

                )
            );*/

            /*$flag = $this->inUserExist($csv,$data);

            if (!isset($flag)) {
                $this->addNewUser($data, $slackApiClient);
            }*/

            if ($data['text'] == "!rating") {
                $this->showRating($data, $this->csv);
            }
            if (stripos($data['text'], '!rating <@') === 0) {
                $this->showRatingByUser($data, $this->csv, $slackApiClient);
                $this->addWin($this->csv, $data['user']);
            }
            if (stripos($data['text'], '+') === 0) {
                $this->addPlayersToGame($data);
            }
            if (stripos($data['text'], '!match') === 0) {
                $this->startGame($data);
            }
        });
        $this->loop->run();
    }

    public function inUserExist($csv,$data){
        foreach ($csv as $key => $value) {
            $playerData = explode(';', $value[0]);
            if (in_array($data['user'], $playerData)) {
                $flag = false;
            }
        }
        return $flag;
    }

    public function addNewUser($data, $slackApiClient)
    {
        $user = $slackApiClient->users->info(array("user" => $data['user'])); // имя юзера
        $dataString = $data['user'] . ';' . $user["user"]["real_name"] . ';' . '0' . ';' . '0';
        $fp = fopen("table.csv", "a");
        fwrite($fp, "\r\n" . $dataString);
        fclose($fp);
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
        foreach ($csv as $key => $value) {
            $playerData = explode(';', $value[0]);
            if ($playerData[0] == $id) {
                $playerData[2]++;
                $savedItem = implode(';', $playerData);
                $csv[$key] = (array)$savedItem;
            }
        }
        $this->saveForDB($csv);
    }

    public function addLose($csv, $id)
    {
        foreach ($csv as $key => $value) {
            $playerData = explode(';', $value[0]);
            if ($playerData[0] == $id) {
                $playerData[3]++;
                $savedItem = implode(';', $playerData);
                $csv[$key] = (array)$savedItem;
            }
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
                    $winCoef = round($playerData[2]/($playerData[2]+$playerData[3])*100,2);
                    $messageInfo = "Имя " . $playerData[1] . "\n Количество побед " . $playerData[2];
                    $messageInfo .= "\n Общее кол-во игр " .($playerData[2]+$playerData[3])."\n % побед " .$winCoef;
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
                        $winCoef = round($playerData[2]/($playerData[2]+$playerData[3])*100,2);
                        $messageInfo = "Имя " . $playerData[1] . "\n количество побед " . $playerData[2];
                        $messageInfo .= "\n Общее кол-во игр " .($playerData[2]+$playerData[3])."\n % побед " .$winCoef;
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


        switch ($this->status) {
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
                if (!in_array($data['user'], $this->players)) {
                    $this->players[] = $data['user'];
                    $this->amountOfPlayers--;
                    if ($this->amountOfPlayers > 0) {
                        $this->status = "waiting";
                        $user = $slackApiClient->users->info(array("user" => $data['user']));
                        $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user) {
                            $message = $client->getMessageBuilder()
                                ->addAttachment(new Attachment('', $user["user"]["real_name"] . " вступает в игру!", null, "#36a64f"))
                                ->setChannel($channel)
                                ->create();
                            $client->postMessage($message);
                        });
                        $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user) {
                            $message = $client->getMessageBuilder()
                                ->addAttachment(new Attachment('', "нужно еще $this->amountOfPlayers", null, "#c4c4c4"))
                                ->setChannel($channel)
                                ->create();
                            $client->postMessage($message);
                        });
                    } else {
                        $this->status = "onair";
                        $user = $slackApiClient->users->info(array("user" => $data['user']));
                        $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user) {
                            $message = $client->getMessageBuilder()
                                ->addAttachment(new Attachment('', $user["user"]["real_name"] . " вступает в игру!", null, "#36a64f"))
                                ->setChannel($channel)
                                ->create();
                            $client->postMessage($message);
                        });
                        $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data) {
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

        if ($this->status != "waiting" && $this->status != "onair") {
            $msg = explode('h', $data['text']);
            switch ($msg[1]) {
                case 2:
                    $this->amountOfPlayers = "2";
                    $this->status = "waiting";
                    break;
                case 4:
                    $this->amountOfPlayers = "4";
                    $this->status = "waiting";
                    break;
                default;
                    $this->amountOfPlayers = " укажите кол-во игроков (2 или 4), например !match4";
                    $this->status = "false";
                    break;
            }
            switch ($this->status) {
                case "false":
                    $user = $slackApiClient->users->info(array("user" => $data['user']));
                    $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user) {
                        $message = $client->getMessageBuilder()
//                ->addAttachment(new Attachment('My Attachment', 'attachment text', null, "#36a64f"))
                            ->addAttachment(new Attachment('', $user["user"]["real_name"] . "," . $this->amountOfPlayers, null, "#a63639"))
                            ->setChannel($channel)
                            ->create();
                        $client->postMessage($message);
                    });
                    break;
                case "waiting":
                    echo $data['user'];
                    $this->players[] = $data['user'];
                    $this->amountOfWinners = $this->amountOfPlayers / 2;
                    $user = $slackApiClient->users->info(array("user" => $data['user']));
                    $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user) {
                        $message = $client->getMessageBuilder()
                            ->addAttachment(new Attachment('', $user["user"]["real_name"] . " собирает игру на $this->amountOfPlayers игроков!", null, "#36a64f"))
//                ->addAttachment(new Attachment('', 'attachment text', null, "#a63639"))
                            ->setChannel($channel)
                            ->create();
                        $client->postMessage($message);
                    });
                    $this->amountOfPlayers--;
                    $client->getChannelGroupOrDMByID($data['channel'])->then(function ($channel) use ($client, $data, $user) {
                        $message = $client->getMessageBuilder()
                            ->addAttachment(new Attachment('', "нужно еще $this->amountOfPlayers", null, "#c4c4c4"))
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