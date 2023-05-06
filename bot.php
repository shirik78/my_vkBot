<?php
require_once 'constants.php';
require_once 'oFile.php';
include 'vk_methods.php';
require_once 'districts.php';
require_once 'carousel.php';
require_once 'district_names.php';
require_once 'vendor/autoload.php';
date_default_timezone_set("Europe/Moscow");

$districts = "https://vk.cc/cbklQu";
$dezhur_tab = "https://vk.cc/c1pCx2";

#@param $vkrequest from vk_methods.php
$messages = new VK\Actions\Messages($vkrequest);

//$callbackApiHandler = new VK\CallbackApi\VKCallbackApiHandler; todo на потом

#reading incoming request 
$data = json_decode(
    file_get_contents('php://input'),
    true
);

$type = $data['type'];
$object = $data["object"];
#todo: use & override methods from SDK
if ($data['group_id'] == VK_BOT_GROUP_ID) {
    switch ($type) {
        case 'confirmation':
            echo CONFIRM_STRING;
            break;

        case 'message_new':
            echo 'ok';
            $incomMessage = $object['message'];
            /**
             * на будущее:
             * $message_object = new Message::fromASSOCArray($object["message"]);
             * $text = $message_object -> getText();
             * $chat_id = $message_object -> getPeerId();
             * $user_id = $message_object -> getFromId();
             * $date = $message_object -> getDate();
             */
            markAsRead($incomMessage["peer_id"], $incomMessage["id"]);
            $text = str_replace("/", "", $incomMessage['text']);
            $text = mb_strtolower($text, "UTF-8");
            $set_of_text = explode(" ", $text);
            $date = $incomMessage['date'];
            $message_id = $incomMessage['id'];
            $chat_id = $incomMessage['peer_id'];
            $user_id = $incomMessage['from_id'];

            $user_info = getUserInfo($user_id);
            $user_name = '@id' . $user_id . '(' . $user_info["first_name"] . ')';
            $message = '';
            $q = '';
            $action = $incomMessage["action"];
            $ac_type = $action["type"];

            $dog = "@";
            $hash = "#";
            $hasDog = strpos($text, $dog, 0);
            $has_hash = strpos($text, $hash, 0);

            #обработка голосовых команд
            if ($text == "" && isset($incomMessage['attachments'])) {
                $attachments = $incomMessage['attachments'];
                #смотрим только первый объект
                $attachment = $attachments[0];
                $attachment_type = $attachment['type'];
                switch ($attachment_type) {
                    case 'audio_message':
                        $aud_message = $attachment['audio_message'];
                        $file_url = $aud_message['link_mp3'];
                        handleVoiceMessage($file_url, $chat_id, $date, $user_name);
                        break;

                    case 'wall_reply':
                        $reply = $attachment['wall_reply'];
                        $text = $reply['text'];
                        $from_user = getUserInfo($reply['from_id']);
                        $date = $reply['date'];
                        $link = 'https://vk.com/wall' . $reply['owner_id'] . '_' . $reply['post_id'];
                        #todo: $message = getMessage();
                        break;
                }
            } else {
                if ($incomMessage["payload"]) {
                    $payload = json_decode($incomMessage["payload"], true);
                    $command = explode(" ", mb_strtolower($payload["command"]));
                    $message = getMessage($command, $chat_id, $date, $user_name);
                } else {
                    if ($hasDog === false) {  //$message_object -> hasAtSign()
                        if ($has_hash === false) {    //$message_object -> hasOctotorp()
                            $message = getMessage($set_of_text, $chat_id, $date, $user_name);
                        } else {
                            $obj =  json_decode(getPost(implode(" ", $set_of_text)), true);
                            $message = $obj["message"];
                            $attach = $obj["attachment"];
                            $source = $obj["content_source"];
                        }
                    } else if (strpos($set_of_text[0], "all", 0) > -1) {
                        #do nothing
                    } else {
                        if (strpos($set_of_text[0], 'club198797031', 0) > -1) {
                            $message = getMessage($set_of_text[1], $chat_id, $date, $user_name);
                        } else {
                            handleMessageWithDog($incomMessage);
                        }
                    }
                }
            }



            if ($message != "" || $attach != "") {
                setActivity($chat_id);
                $messageParams = array(
                    'message' => $message,
                    'random_id' => $incomMessage["date"],   //$message_object ->getDate()
                    'peer_id' => $incomMessage["peer_id"],  //$message_object ->getPeerId()
                    'attachment' => $attach,
                    'content_source' => $source
                );

                $result =  $messages->send(VK_API_ACCESS_TOKEN, $messageParams);
            }

            markAsAnswered($incomMessage["peer_id"]);   //$message_object -> getPeerId()

            break;

        case 'message_edit':
            echo 'ok';
            markAsRead($object["peer_id"], $object["id"]);
            $text = str_replace("/", "", mb_strtolower($object["text"], "UTF-8"));
            $isDog = strpos($text, "@", 0);
            $isHash = strpos($text, "#", 0);
            $user = getUserInfo($object["from_id"]);
            $message_id = $object['conversation_message_id'];
            if ($isDog === false && $isHash === false) {
                $message = getMessage($text, $object["peer_id"], $object["date"], $user["first_name"]);
            } else if ($isHash > -1) {
                $obj =  json_decode(getPost($object["text"]), true);
                $message = $obj["message"];
                $attach = $obj["attachment"];
            } else {
                if (strpos($text, "all", 0) > -1) {
                }
                handleMessageWithDog($object);
            }

            if ($message != '') {
                setActivity($object["peer_id"]);

                $message_params = array(
                    'random_id' => $object["update_time"],
                    'peer_id' => $object["peer_id"],
                    'message' => $message,
                    'attachment' => $attach
                );
                $messages->send(VK_API_ACCESS_TOKEN, $message_params);
            }
            markAsAnswered($object["peer_id"]);

            break;

        case 'group_join':
            echo 'ok';
            $user_id = $object["user_id"];
            $fields = 'sex';
            $user = getUserInfo($user_id, 'nom', $fields);
            switch ($user['sex']) {
                case '1':
                    $join = 'пришла ко мне))';
                    break;
                case '2':
                    $join = 'пришел к нам!';
                    break;
                case "0":
                    $join = "пришло в группу!";
                    break;
            }
            $message = "@id{$user['id']}({$user['first_name']} {$user['last_name']}) {$join}";
            $peer_ids = BOT_AUTHOR . "," . LENA_DRU;
            $params = array(
                'random_id' => time(),
                'message' => $message,
                'peer_ids' => $peer_ids
            );
            $result = $messages->send(VK_API_ACCESS_TOKEN, $params);
            break;

        case 'group_leave':
            echo 'ok';
            $user_id = $object["user_id"];
            $user = getUserInfo($user_id, 'nom', "sex");
            switch ($user["sex"]) {
                case '1':
                    $leaved = "сбежала из группы";
                    break;
                case '2':
                    $leaved = "свалил из группы";
                    break;
                default:
                    $leaved = "ушло из группы";
            }
            $message = "@id{$user['id']}({$user['first_name']} {$user['last_name']}) {$leaved}";
            $peer_ids = BOT_AUTHOR . "," . LENA_DRU;
            $params = array(
                'random_id' => $object["date"],
                'message'   => $message,
                'peer_ids'   => $peer_ids
            );
            $result = $messages->send(VK_API_ACCESS_TOKEN, $params);
            break;

        case 'board_post_new':
            echo "ok";
            $topic = $object["topic_id"];
            if ($topic == DOLG_TOPIC_ID) {
                $dezh = readTable();
                $text = $object["text"];
                $message = $dezh . "\n\n" . $text;
                $messageParams = array(
                    "peer_id" => WORK_CHAT,
                    "random_id" => $object["date"]
                );
                #$result = $messages -> send(VK_API_ACCESS_TOKEN, $messageParams);
                $answer = sendMessage($message, $messageParams);
            }

            break;

        case 'board_post_edit':
            echo "ok";
            $groups = new VK\Actions\Groups($vkrequest);
            $topic = $object["topic_id"];
            if ($topic == DOLG_TOPIC_ID) {
                $text = $object["text"];
                $comment_author_id = $object["from_id"];
                if ($comment_author_id > 0) {
                    $user = getUserInfo($comment_author_id, 'ins');
                    $fullName = $user["first_name"] . " " . $user["last_name"];
                    $name_string = "@id{$comment_author_id}({$fullName})";
                } else {
                    $request = array(
                        'group_id' => -$comment_author_id
                    );
                    $response = $groups->getById(VK_API_ACCESS_TOKEN, $request);
                    $user = $response[0];
                    $fullName = $user['name'];
                    $name_string = "[club{-$comment_author_id}|{$fullName}]";
                }


                $messageParams = array(
                    'peer_id' => BOT_AUTHOR,
                    'random_id' => $object['date']
                );
                $message = "Список долгов обновлен {$name_string}, " . date("H:i", time());
                $answer = sendMessage($message, $messageParams);
            }

            break;

        case 'message_reply':
            echo 'ok';
            break;

        case 'message_event':
            echo 'ok';
            $payload = $object["payload"];
            $user = getUserInfo($object['user_id']);
            $user_name = $user['first_name'];
            $event_data = array(
                'type' => 'show_snackbar',
                'text' => "Спасибо, {$user_name}, ответ отправлен &#128077;"
            );
            $js_data = json_encode($event_data);
            $params = array(
                'event_id' => $object["event_id"],
                'user_id' => $object["user_id"],
                'peer_id' => $object["peer_id"],
                'event_data' => $js_data
            );

            $response = $vkrequest->post('messages.sendMessageEventAnswer', VK_API_ACCESS_TOKEN, $params);
            $date = time();
            getMessage($payload["command"], $object["user_id"], $date, $user_name);

            break;
    }
} else {
    $gr = $data["group_id"];
    $messageParams = array(
        'peer_id' => BOT_AUTHOR,
        'random_id' => $object['date']
    );
    $message = "message for $gr see Callback API in group";
    sendMessage($message, $messageParams);
}

function clearCommand($text)
{
}


/**
 * @param string text 
 * @return string JSONArray
 **/
function getPost($text)
{
    global $vkrequest;
    global $messages;
    $wall = new VK\Actions\Wall($vkrequest);
    $inforgs = getInforgs();
    $la_dog_inforgs = json_decode(sortJSONArrayByName(file_get_contents('https://docs.google.com/uc?export=download&id=1XOcUyY2Wihcu9k5i70j179J2oS0sK39h')), true);

    $in_la_dog = is_in_la_dog($la_dog_inforgs, $text);

    $request = array(
        'domain' => 'lizaalert_piter',
        'query' => substr($text, strpos($text, "#")),
        'owners_only' => '1'
    );
    $response = $wall->search(VK_API_SERVICE_TOKEN, $request);

    if ($response['count'] == 0) {
        $ar = array(
            'message' => "Я такой фамилии не нашел &#128530;" . PHP_EOL . "Возможно, опечатка?",
        );
        $result = json_encode($ar);
    } else {
        $post = array_shift($response['items']);
        if (!$post) {
            $ar = array(
                'message' => "Я такой фамилии не нашел &#128530;" . PHP_EOL . "Возможно, опечатка?",
                'attachment' => null
            );
            $result = json_encode($ar);
        }
        $inf = "Инфорг";
        $infs = "Инфорги";
        if (str_contains($post['text'], $inf) || str_contains($post['text'], $infs)) {
            $post_text = $post['text'];
            $owner_id = $post['owner_id'];
            $post_id = $post['id'];
            $attachments = $post['attachments'][0];
            $photo = $attachments['photo'];
            $id = $photo['id'];
            $key = $photo['access_key'];
            $photo_url = "https://vk.com/photo{$owner_id}_{$id}";
            $post_strings = explode("\n", $post_text);
            $first_string = $post_strings[0];
            $inforg = strpos($post_text, $inf, 0);
            $inf_str = substr($post_text, $inforg);
            $inforges = strpos($post_text, $infs, 0);
            $infs_str = substr($post_text, $inforges);
            $contains = false;
            $symbolsToReplace = ["(", ")", "ё"];
            $symbolsForReplace = ["", "", "е"];
            $replaced_inf = str_replace($symbolsToReplace, $symbolsForReplace, $inf_str);
            $replaced_infs = str_replace($symbolsToReplace, $symbolsForReplace, $infs_str);
            foreach ($inforgs as $inforg) {
                $phones = $inforg["phones"][0];
                if (str_contains($replaced_inf, $inforg['name']) || str_contains($replaced_infs, $inforg['name'])) {
                    foreach ($phones as $phone) {
                        if (str_contains($replaced_inf, $phone) || str_contains($replaced_infs, $phone)) {
                            $contains = true;
                            break 1;
                        }
                    }
                }
            }
            if (!$contains) {
                $repl_inf = ($replaced_inf == "") ? $replaced_infs : $replaced_inf;
                $mess_param = array(
                    'message' => "Номер инфорга не в списке: " . PHP_EOL . $repl_inf . PHP_EOL . 'https://vk.com/wall' . $owner_id . '_' . $post_id,
                    'random_id' => '0',
                    'peer_ids' => BOT_AUTHOR . "," . LENA_DRU
                );
                $messages->send(VK_API_ACCESS_TOKEN, $mess_param);
            }
            $message = "";
            if (strpos($post_text, "ВЫЕЗД", 0) > 0) {
                for ($i = 0; $i < count($post_strings) - 2; $i++) {
                    if ($i == 1) {
                        continue;
                    }
                    $message = $message . $post_strings[$i] . "\n";
                }
                $message = $message . "\n" . "https://vk.com/photo" . $owner_id . "_" . $id;
            } else {
                $message = $post_strings[0] . "\n" . "Максимальный репост, пожалуйста! \n" . $inf_str . PHP_EOL . $photo_url . PHP_EOL . "Ссылка на пост:" . PHP_EOL . "https://vk.com/wall" . $owner_id . "_" . $post_id;
            }
            $attach = "photo" . $post['owner_id'] . "_" . $photo['id'] . "_" . $key;

            $source = array(
                'type' => 'url',
                'url' => "https://vk.com/wall" . $owner_id . "_" . $post_id
            );
            $ar = array(
                'message' => $message,
                'attachment' => $attach,
                'content_source' => json_encode($source)
            );
            $result = json_encode($ar);
        } else {
            $post_text = $post['text'];
            $owner_id = $post['owner_id'];
            $post_id = $post['id'];
            $attachments = $post['attachments'][0];
            $photo = $attachments['photo'];
            $photo_id = $photo['id'];
            $key = $photo['access_key'];
            $photo_url = "https://vk.com/photo{$owner_id}_{$photo_id}";
            $post_strings = explode("\n", $post_text);
            $match_string = "@[a-zА-Я]+\s\W[a-zА-Я]+@ui";   #текст_пробел_#текст ui - utf-8 И игнор регистра
            if (preg_match($match_string, $post_text) == 1) {
                $result = json_encode(
                    array(
                        'message' => $post_strings[0] . PHP_EOL . $post_strings[1] . PHP_EOL . "Максимальный репост, пожалуйста!" . PHP_EOL . $photo_url . PHP_EOL . "Ссылка на пост:" . PHP_EOL . "https://vk.com/wall" . $owner_id . "_" . $post_id,
                        'attachment' => "photo" . $post["owner_id"] . "_" . $photo_id . "_" . $key,
                        'content_source' => json_encode(
                            array(
                                'type' => 'url',
                                'url' => "https://vk.com/wall{$owner_id}_{$post_id}"
                            )
                        )
                    )
                );
                if (!$in_la_dog) {
                    $mess_param = array(
                        'message' => "Пост без инфорга не занесен в программу LA_DOG" . PHP_EOL . $text . PHP_EOL . 'https://vk.com/wall' . $owner_id . '_' . $post_id,
                        'random_id' => '0',
                        'peer_id' => BOT_AUTHOR
                    );
                    $messages->send(VK_API_ACCESS_TOKEN, $mess_param);
                }
            } else {
                $result = json_encode(
                    array(
                        'message'        => $post_text . PHP_EOL . "Максимальный репост, пожалуйста!" . PHP_EOL . $photo_url . PHP_EOL . "Ссылка на пост:" . PHP_EOL . "https://vk.com/wall" . $owner_id . "_" . $post_id,
                        'attachment'     => "photo" . $post["owner_id"] . "_" . $photo_id . "_" . $key,
                        'content_source' => json_encode(
                            array(
                                'type' => 'url',
                                'url'  => "https://vk.com/wall{$owner_id}_{$post_id}"
                            )
                        )
                    )
                );
            }
        }
    }
    return $result;
}

function is_in_la_dog($json_inforgs, $text)
{
    foreach ($json_inforgs as $json_inforg) {
        if (mb_strtolower($json_inforg["phone"], "UTF-8") == mb_strtolower($text, "UTF-8")) {
            return true;
        }
    }
    return false;
}

/**
 * read Google Table for value
 **/
function readTable()
{
    #todo: include google API for spreadsheets
    define("HOME", "http://shirik78.hostingem.ru");
    global $vkrequest;
    $users = new VK\Actions\Users($vkrequest);
    $month = date('m', time());
    $day_num = date('d', time());
    $params = array(
        'format' => 'csv',
        'gid' => '0'
    );
    $file_id = '1rrouNM3lH4n4nG7tJi8Lr0IBahrp4R0Bv26JgL2c-FI';

    $query = http_build_query($params);
    $serv = 'https://docs.google.com/spreadsheets/d/' . $file_id . '/export?';
    $csv = file_get_contents($serv . $query);
    $csv = explode("\r\n", $csv);
    $table = array_map('str_getcsv', $csv);
    $str_month = $table[$month + 36];    //начало с 2020, поэтому +36
    $dezh = trim($str_month[$day_num + 1]);
    if ($dezh) {
        $param = array(
            'name' => $dezh
        );
        $query = http_build_query($param);
        $url = "/vkmethods/user_names.php?";
        $user_id = file_get_contents(HOME . $url . $query);
    } else {
        $user_ids = [];
        $json_users = json_decode(file_get_contents('/vkmethods/user_names.json'), true);
        foreach ($json_users as $name => $id) {
            array_push($user_ids, $id);
        }
        $user_id = get_random_user_id($user_ids);
    }
    $user_params = array(
        'user_ids' => $user_id
    );
    $response = $users->get(VK_API_SERVICE_TOKEN, $user_params);
    $user = $response[0];
    send_accept_decline_buttons($user);
    $today = date('d.m.Y', time());
    $text = "Привет, сегодня $today, дежурит &#129418; @{$user_id}({$dezh})";
    return $text;
}

/**
 * @param jsonArray JSONObject
 * sort JSONArray with Inforgs alphabetical
 * @return string JSON
 **/
function sortJSONArrayByName($jsonArray)
{

    $inforgs = json_decode($jsonArray, true)['inforgs'];
    usort($inforgs, function ($a, $b) {
        return $a['name'] <=> $b['name'];
    });
    return json_encode($inforgs);
}

function send_accept_decline_buttons($user)
{
    global $date;

    $username = $user['first_name'];
    $user_id = $user["id"];
    $actions = array(
        '0' => array(
            'type' => 'callback',
            'payload' => array(
                'command' => 'duty_accepted'
            ),
            'label' => 'Да'
        ),
        '1' => array(
            'type' => 'callback',
            'payload' => array(
                'command' => 'duty_declined'
            ),
            'label' => 'Нет'
        )
    );

    $colors = array(
        '0' => "positive",
        '1' => "negative"
    );

    $js_key = createKeyboard($actions, $colors, true);
    $user_ids = array(
        '0' => $user_id
    );

    safe_send_message($user_ids, $date, "Привет, {$username}, ты дежуришь?", $js_key);
}


/**
 * create message to return in chat or null
 * @param Array set_of text
 * @param int chat_id
 * @param UnixDate date
 * @param String user_name
 * return String message
 **/

function getMessage($set_of_text, $chat_id, $date, $user_name)
{
    include 'districts.php';
    require_once 'vendor/autoload.php';
    global $vkrequest;
    global $messages;
    $board = new VK\Actions\Board($vkrequest);
    $wall = new VK\Actions\Wall($vkrequest);
    global $districts;
    global $dezhur_tab;
    if (!is_array($set_of_text)) {
        $command = $set_of_text;
    } else {
        if (mb_strpos($set_of_text[0], 'club198797031', 0) > -1 || mb_strpos($set_of_text[0], '[club198797031|Бот ГОО]', 0) > -1) {
            $ar_command = explode("]", $set_of_text[0]);
            $command = trim($ar_command[1]);
        } else {
            $command = array_shift($set_of_text);
        }
    }
    switch ($command) {
        case 'начать':
            $button_names = array(
                '0' => 'Инфорги',
                '1' => 'Дежурство',
                '2' => 'Ещё'
            );
            $commands = array(
                '0' => '',
                '1' => '',
                '2' => ''
            );
            $template = createCarousel("Вот что я умею", "Основные команды для работы", $commands, $button_names);

            $message = 'Привет!';

            $params = array(
                'message' => $message,
                'template' => $template,
                'random_id' => $date,
                'peer_id' => $chat_id
            );
            $act_params = array(
                'peer_id' => $chat_id,
                'group_id' => VK_BOT_GROUP_ID,
                'type' => 'typing'
            );
            $act = $messages->setActivity(VK_API_ACCESS_TOKEN, $act_params);
            time_sleep_until(time() + 3);
            $response = $messages->send(VK_API_ACCESS_TOKEN, $params);
            $act_param2 = array(
                'peer_id'                   => $chat_id,
                'start_message_id'          => $message_id,
                'group_id'                  => $group_id,
                'mark_conversation_as_read' => '1'
            );
            $response2 = $messages->markAsRead(VK_API_ACCESS_TOKEN, $act_param2);
            unset($message);    //return null
            break;

        case 'ещё':
            $button_names = array(
                '0' => 'Районы',
                '1' => 'Долги',
                '2' => 'Команды'
            );
            $commands = array(
                '0' => '',
                '1' => "",
                '2' => ""
            );
            $template = createCarousel("Вот что я умею", "Продолжение", $commands, $button_names);
            $message = "продолжаем";
            $params = array(
                'message' => $message,
                'template' => $template,
                'random_id' => $date,
                'peer_id' => $chat_id
            );
            $response = $vkrequest->post("messages.send", VK_API_ACCESS_TOKEN, $params);
            unset($message);
            break;

        case 'duty_accepted':
            $user = getUserInfo($chat_id, "ins");
            $user_name = $user['first_name'] . " " . $user['last_name'];
            $params = array(
                'message' => "Дежурство подтверждено @id{$chat_id}({$user_name}), в " . date("H:i", time()),
                'random_id' => time(),
                'peer_id' => CHAT_TEST
            );
            $messages->send(VK_API_ACCESS_TOKEN, $params);
            break;

        case 'duty_declined':
            $user = getUserInfo($chat_id, "ins");
            $user_name = $user['first_name'] . " " . $user['last_name'];
            $params = array(
                'message' => "Дежурство отклонено @id{$chat_id}({$user_name}) в " . date("H:i", time()),
                'random_id' => time(),
                'peer_id' => CHAT_TEST
            );

            $messages->send(VK_API_ACCESS_TOKEN, $params);
            break;

            #отключает инлайн клавиатуру
        case 'кнопки':
            $array =  array(
                'buttons' => [],
                'inline' => false
            );
            $js_key = json_encode($array);
            $params = array(
                'message' => 'выключаю',
                'keyboard' => $js_key,
                'random_id' => $date,
                'peer_id' => $chat_id
            );
            $response = $vkrequest->post('messages.send', VK_API_ACCESS_TOKEN, $params);
            $message = "";
            break;

            #инлайн кнопка
        case 'справка':
            $actions = array(
                '0' => array(
                    'type' => 'callback',
                    'payload' => array(
                        'command' => 'команды'
                    ),
                    'label' => 'Команды'
                )
            );
            $colors = [];
            foreach ($actions as $action) {
                array_push($colors, "secondary");
            }
            $js_key = createKeyboard($actions, $colors, false);
            $params = array(
                'message' => 'Нажми меня',
                'keyboard' => $js_key,
                'random_id' => $date,
                'peer_id' => $chat_id
            );
            $response = $vkrequest->post('messages.send', VK_API_ACCESS_TOKEN, $params);
            $message = "";

            break;

        case 'инфорги':
            $inforgs = getInforgs();
            foreach ($inforgs as $inforg) {
                $phones =  $inforg['phones'][0];
                $message .= $inforg["name"] . " - " . implode(", ", $phones) . PHP_EOL;
            }
            break;

        case 'районы':
            $message = "ссылка на файл с районами ГОО";
            $actions = array(
                '0' => array(
                    'type' => 'open_link',
                    'label' => '&#128073; Таблица районов',
                    'link' => $districts
                )
            );
            $colors = ["secondary"];
            $inline = true;
            $keyboard = createKeyboard($actions, $colors, $inline);
            $params = array(
                'message' => $message,
                'keyboard' => $keyboard,
                'peer_id' => $chat_id,
                'random_id' => time()
            );
            $response = $messages->send(VK_API_ACCESS_TOKEN, $params);
            unset($message);
            break;

        case 'дежурство':
            $message = "{$user_name}, держи ссылку на таблицу дежурств";
            $actions = array(
                '0' => array(
                    'type' => 'open_link',
                    'label' => 'Таблица дежурств',
                    'link' => $dezhur_tab
                )
            );
            $inline = true;
            $color = ['secondary'];
            $keyboard = createKeyboard($actions, $color, $inline);
            $params = array(
                'message' => $message,
                'keyboard' => $keyboard,
                'peer_id' => $chat_id,
                'random_id' => time()
            );
            $response = $messages->send(VK_API_ACCESS_TOKEN, $params);
            unset($message);
            break;

        case 'showbutton':
            $user = getUserInfo($chat_id);
            send_accept_decline_buttons($user);
            break;

        case 'команды':
            $message = '"инфорги" - выдам список инфоргов;
            "районы" - ссылка на таблицу с районами;
            "дежурство" - ссылка на таблицу дежурных;
            "#фамилия_бвп" - готовый текст поста с оркой и ссылкой на оригинал в ЛАП;
            "название_района" - список прилегающих к указанному;
            "@название_района" - ссылка на список групп в сообществе Бота;
            "долги" - список невыполненных задач.(работает только в личке у Бота).';
            break;

        case 'выезд':
            if ($set_of_text[0]) {
                $post_id = $set_of_text[0];
                $params = array(
                    'posts' => "-41515336_{$post_id}"
                );
                $response = $wall->getById(VK_API_SERVICE_TOKEN, $params);
                #$list = $response["items"];
                $post = $response[0];
                $answer = createTextIfOutdoorSearch($post, true);
                $params1 = array(
                    'message' => $answer["message"],
                    'attachment' => $answer["attachment"],
                    'peer_id' => $chat_id,
                    'random_id' => $date
                );
                $messages->send(VK_API_ACCESS_TOKEN, $params1);
                unset($message);
                break;
            } else {
                $params1 = array(
                    'message' => 'Не указан ИД поста',
                    'peer_id' => $chat_id,
                    'random_id' => $date
                );
                $messages->send(VK_API_ACCESS_TOKEN, $params1);
            }

        case "обычный":
            $post_id = $set_of_text[0];
            $params = array(
                'posts' => "-41515336_{$post_id}"
            );
            $response = $wall->getById(VK_API_SERVICE_TOKEN, $params);
            $post = $response[0];
            $answer = createTextIfOutdoorSearch($post, false);
            $params1 = array(
                'message' => $answer["message"],
                'attachment' => $answer['attachment'],
                'peer_id' => $chat_id,
                'random_id' => time()
            );
            $messages->send(VK_API_ACCESS_TOKEN, $params1);
            unset($message);
            break;

        case "вводная":
            $params = array(
                'domain' => 'lizaalert_piter',
                'query' => 'Регистрация по ссылке',
                'owners_only' => '1',
                'count' => '1'
            );
            $response = $wall->search(VK_API_SERVICE_TOKEN, $params);
            $post = array_shift($response['items']);
            $attachment = $post['attachments'][0];
            $photo = $attachment['photo'];
            $mes_params = array(
                'message' => $post['text'] . PHP_EOL . "\nhttps://vk.com/wall" . $post['owner_id'] . "_" . $post['id'],
                'attachment' => 'photo' . $post['owner_id'] . "_" . $photo['id'] . "_" . $photo['access_key'],
                'peer_id' => $chat_id,
                'random_id' => time()
            );
            $messages->send(VK_API_ACCESS_TOKEN, $mes_params);
            break;

        case 'адмиралтейский':
            $message = $admiral;
            break;

        case 'василеостровский':
            $message = $vaska;
            break;

        case 'выборгский':
            if ($set_of_text[0]) {
                $message = $vyborgLO;
            } else {
                $message = $vyborgsk;
            }
            break;

        case 'калининский':
            $message = $kalin;
            break;

        case 'кировский':
            if ($set_of_text[0]) {
                $message = $kirovLo;
            } else {
                $message = $kirovSpb;
            }
            break;
        case 'колпинский':
            $message = $kolpin;
            break;

        case 'красногвардейский':
            $message = $krasnogv;
            break;

        case 'красносельский':
            $message = $krasnosel;
            break;

        case 'кронштадтский':
            $message = $kronstadt;
            break;

        case 'курортный':
            $message = $kurort;
            break;

        case 'московский':
            $message = $moskov;
            break;

        case 'невский':
            $message = $nevsk;
            break;

        case 'петроградский':
            $message = $petrogr;
            break;

        case 'петродворцовый':
            $message = $petrodvor;
            break;

        case 'приморский':
            $message = $primor;
            break;

        case 'пушкинский':
            $message = $pushkin;
            break;

        case 'фрунзенский':
            $message = $frunze;
            break;

        case 'центральный':
            $message = $centr;
            break;

        case 'мурино':
            $message = $murino;
            break;

        case 'кудрово':
            $message = $kudrovo;
            break;

        case 'сосновоборский':
            $message = $sosnovobor;
            break;

        case 'тосненский':
            $message = $tosno;
            break;

        case 'тихвинский':
            $message = $tichwin;
            break;

        case 'сланцевский':
            $message = $slanez;
            break;

        case 'приозерский':
            $message = $priozer;
            break;

        case 'подпорожский':
            $message = $podporozh;
            break;

        case 'лужский':
            $message = $luga;
            break;

        case 'ломоносовский':
            $message = $lomonos;
            break;

        case 'лодейнопольский':
            $message = $lodeyka;
            break;

        case 'киришский':
            $message = $kirishi;
            break;

        case 'кингисеппский':
            $message = $kingisepp;
            break;

        case 'гатчинский':
            $message = $gatchina;
            break;

        case 'всеволожский':
            $message = $vsevolozh;
            break;

        case 'волховский':
            $message = $volchv;
            break;

        case 'бокситогорский':
            $message = $boxit;
            break;

        case 'волосовский':
            $message = $volosovo;
            break;

        case 'долги':
            $params = array(
                'group_id' => VK_BOT_GROUP_ID,
                'topic_id' => DOLG_TOPIC_ID
            );

            $dolgResponse = $board->getComments(VK_API_SERVICE_TOKEN, $params);

            $comments = $dolgResponse['items'];
            $lastComment = array_pop($comments);
            $text = $lastComment['text'];
            if ($chat_id != CHAT_BOLTALKA && $chat_id != EDUCATION_CHAT) {
                $message = $text;
            } else if ($chat_id == CHAT_BOLTALKA) {
                $message = "Долги смотрим в [club198797031|личке]";
            } else {
                $message = "в чате обучения эта команда не работает &#128566;";
            }
            break;

        case "добавь":
            $name = $set_of_text[0];
            $id = $set_of_text[1];
            $message = addUserToJson($name, $id);
            break;

        case "getuserslist":
            $message = getJSONUsers();
            break;

        case "удали":
            $message = deleteUserFromJSON($set_of_text[0]);
            break;

        case "инфоргу":
            $mess_is_sended = false;
            $name = array_shift($set_of_text);
            $duty_message = implode(" ", $set_of_text);
            if (!$name) {
                $message = "имя, сестра, ИМЯ?!©  давай заново!)))";
                break;
            } else {
                if (!$duty_message) {
                    $message = "что передавать? заново!)))";
                    break;
                } else {
                    $inforgs = getInforgs();
                    foreach ($inforgs as $inforg) {
                        $i_name = explode(" ", $inforg['name']);
                        if ($name == mb_strtolower($i_name[0]) || $name == mb_strtolower($i_name[1])) {
                            if ($inforg['vk_id'] != 100) {
                                $params = array(
                                    'random_id' => '0',
                                    'peer_id' => $inforg['vk_id'],
                                    'message' => $duty_message
                                );
                            } else {
                                $params = array(
                                    'random_id' => '0',
                                    'peer_id' => '181655184',
                                    'message' => $duty_message
                                );
                            }
                            if ($result = $messages->send(VK_API_ACCESS_TOKEN, $params)) {
                                $mess_is_sended = true;
                                break 1;
                            }
                        } else {
                            continue;
                        }
                    }
                }
            }
            if ($mess_is_sended) {
                $message = "переслано удачно";
            } else {
                $message = "Инфорг с таким именем не найден";
            }
            break;

        default:
            $message = "";
    }
    return $message;
}

function getJSONUsers()
{
    $users = json_decode(file_get_contents("vkmethods/user_names.json"), true);
    $result = "";
    foreach ($users as $name => $id) {
        if (ctype_digit($id)) {
            $id = "@id" . $id;
        } else {
            $id = "@" . $id;
        }
        $result .= $name . " - " . $id . "\n";
    }
    return $result;
}

function addUserToJson($name, $id)
{
    $file_name = "vkmethods/user_names.json";
    $users_json = json_decode(file_get_contents($file_name), true);
    $users_json[$name] = $id;
    if (file_put_contents($file_name, json_encode($users_json)) !== FALSE) {
        return "Имя {$name} добавлено в список дежурящих";
    }
    return "Не удалось добавить {$name} в лист(";
}

function deleteUserFromJSON($name)
{
    $file_name = "vkmethods/user_names.json";
    $users_json = json_decode(file_get_contents($file_name), true);
    unset($users_json[$name]);
    if (file_put_contents($file_name, json_encode($users_json)) !== FALSE) {
        return "Юзер $name успешно удален из списка";
    }
    return "Не удалось удалить юзера из списка";
}


function handleMessageWithDog($messageObject)
{
    global $messages;
    $inline = true;
    $text = mb_strtolower(str_replace("/", "", $messageObject["text"]));
    $text = str_replace("@", "", $text);
    $chat_id = $messageObject["peer_id"];
    $group_id = $messageObject["group_id"];
    $message_id = $messageObject["id"];
    if ($checkedText = DistrictNames::from($text)) {
        $act_param2 = array(
            'peer_id'                   => $chat_id,
            'start_message_id'          => $message_id,
            'group_id'                  => VK_BOT_GROUP_ID,
            'mark_conversation_as_read' => '1'
        );
        $messages->markAsRead(VK_API_ACCESS_TOKEN, $act_param2);
        $districtes = json_decode(file_get_contents('district_urls.json'), true);
        $message = $districtes[$text];
        $label = mb_convert_case($text, MB_CASE_TITLE, "UTF-8");
        $actions = [];
        if (!is_array($message)) {
            $a1 = array(
                'type' => 'open_link',
                'label' => $label,
                'link' => $message
            );
            array_push($actions, $a1);
            $color = ["secondary"];
            $keyboard = createKeyboard($actions, $color, $inline);
        } else {
            $colors = [];
            foreach ($message as $mArray) {
                foreach ($mArray as $key => $value) {
                    $a1 = array(
                        'type' => 'open_link',
                        'label' => mb_convert_case($key, MB_CASE_TITLE, "UTF-8"),
                        'link' => $value
                    );
                    array_push($actions, $a1);
                    array_push($colors, "secondary");
                }
            }
            if (count($actions) > 6) {
                $keyboard = createKeyboard(array_slice($actions, 0, 6), $colors, $inline);
                $params = array(
                    'message' => 'Список групп района',
                    'keyboard' => $keyboard,
                    'peer_id' => $chat_id,
                    'random_id' => time()
                );
                $act_params = array(
                    'peer_id' => $chat_id,
                    'group_id' => VK_BOT_GROUP_ID,
                    'type' => 'typing'
                );
                $response1 = $messages->setActivity(VK_API_ACCESS_TOKEN, $act_params);
                time_sleep_until(time() + 2);
                $response = $messages->send(VK_API_ACCESS_TOKEN, $params);
                $keyboard = createKeyboard(array_slice($actions, 6), $colors, $inline);    //max value 6
            } else {
                $keyboard = createKeyboard($actions, $colors, $inline);
            }
        }

        $params = array(
            'message' => 'Список групп района',
            'keyboard' => $keyboard,
            'peer_id' => $chat_id,
            'random_id' => time()
        );

        #отправка статуса "печатает..."
        $act_params = array(
            'peer_id' => $chat_id,
            'group_id' => VK_BOT_GROUP_ID,
            'type' => 'typing'
        );

        $messages->setActivity(VK_API_ACCESS_TOKEN, $act_params);
        time_sleep_until(time() + 2);

        $messages->send(VK_API_ACCESS_TOKEN, $params);

        $act_param = array(
            'peer_id'                   => $chat_id,
            'answered'                  => 1,
            'group_id'                  => $group_id,
        );
        $messages->markAsAnsweredConversation(VK_API_ACCESS_TOKEN, $act_param);
    } else {
        $act_params = array(
            'peer_id' => $chat_id,
            'group_id' => VK_BOT_GROUP_ID,
            'type' => 'typing'
        );

        $messages->setActivity(VK_API_ACCESS_TOKEN, $act_params);
        time_sleep_until(time() + 2);
        $prams = array(
            'peer_id' => $chat_id,
            'random_id' => time(),
            'message' => 'Такого района пока у меня нет, &#128513;'
        );
        $messages->send(VK_API_ACCESS_TOKEN, $prams);
        $act_param = array(
            'peer_id'                   => $chat_id,
            'answered'                  => 1,
            'group_id'                  => $group_id,
        );
        $messages->markAsAnsweredConversation(VK_API_ACCESS_TOKEN, $act_param);
    }
}

function createTextIfOutdoorSearch($post, $isOutdoorNeeded = false)
{
    define("OUTDOOR_IS_CLOSED", "Выезд завершен!");
    global $messages;
    $inforgs = json_decode(sortJSONArrayByName(file_get_contents('https://docs.google.com/uc?export=download&id=1XOcUyY2Wihcu9k5i70j179J2oS0sK39h')), true);
    $inf = "Инфорг";
    $infs = "Инфорги";
    $post_text = $post["text"];
    $owner_id = $post["owner_id"];
    $post_id = $post["id"];
    $attachments = $post["attachments"][0];
    $photo = $attachments["photo"];
    $photo_id = $photo["id"];
    $key = $photo["access_key"];
    $photo_url = "https://vk.com/photo{$owner_id}_{$photo_id}";
    $post_strings = explode("\n", $post_text);
    $post_url = "https://vk.com/wall" . $owner_id . "_" . $post_id;
    foreach ($post_strings as $string) {
        if (str_contains($string, $inf) || str_contains($string, $infs)) {
            $inf_str = $string;
            $inf_str = str_replace("(", "", $inf_str);
            $inf_str = str_replace(")", "", $inf_str);
            $inf_stack = [];
            foreach ($inforgs as $inforg) {
                if (str_contains($inf_str, $inforg["name"]) && str_contains($inf_str, $inforg["phone"])) {
                    array_push($inf_stack, $inforg["name"] . " " . $inforg["phone"]);
                } else {
                    if (str_contains($inf_str, $inforg["name"]) && !str_contains($inf_str, $inforg['phone'])) {
                        $phone = mb_substr($inf_str, mb_strpos($inf_str, "8"));
                        array_push($inf_stack, $inforg["name"] . " " . $phone);
                        $request_array = array(
                            'message' => $inf_str,
                            'random_id' => '0',
                            'peer_ids' => BOT_AUTHOR . "," . LENA_DRU
                        );
                        $messages->send(VK_API_ACCESS_TOKEN, $request_array);
                    }
                }
            }
            if (count($inf_stack) > 1) {
                $inf_str = "Инфорги: ";
                foreach ($inf_stack as $inforg) {
                    $inf_str  =  $inf_str . $inforg . PHP_EOL;
                }
            } else if (count($inf_stack) == 1) {
                $inf_str = "Инфорг: " . $inf_stack[0];
            }
        }
    }
    if ($inf_str == "") {
        $inf_str = $post_strings[1];
    }
    $first_string = $post_strings[0];
    $message = "";
    if ($isOutdoorNeeded) {
        foreach ($post_strings as $string) {
            if (str_contains(mb_strtolower($string), "штаб свернут") || str_contains(mb_strtolower($string), "штаб свёрнут")) {
                $message = OUTDOOR_IS_CLOSED;
                break 1;
            }
            if (str_contains($string, "#") || str_contains($string, "https://")) {
                continue;
            }

            $message = $message . $string . PHP_EOL;
        }
        if ($message != OUTDOOR_IS_CLOSED) {
            $message = $message . PHP_EOL . $photo_url . PHP_EOL . "\nСсылка на пост:" . PHP_EOL . $post_url;
        } else {
            $message = $message . PHP_EOL . "Ссылка на пост:" . PHP_EOL . $post_url;
        }
    } else {
        $message = $post_strings[0] . PHP_EOL . "Максимальный репост, пожалуйста!" . PHP_EOL . $inf_str . PHP_EOL . $photo_url . PHP_EOL . "\nСсылка на пост:" . PHP_EOL . $post_url;
    }

    $attachment = "photo{$owner_id}_{$photo_id}_{$key}";

    $result = array(
        "message" => $message,
        "attachment" => $attachment
    );

    return $result;
}

function handleVoiceMessage($file_url, $chat_id, $date, $user_name)
{
    global $vkrequest;
    global $messages;
    $path = "temp/{$chat_id}_{$date}.mp3";
    $tmp_file = fopen($path, 'w');
    $content = file_get_contents($file_url);
    fwrite($tmp_file, $content);
    fclose($tmp_file);
    $msg = "";
    $url_response = $vkrequest->post('asr.getUploadUrl', VK_API_SERVICE_TOKEN);
    $uploadUrl = $url_response['upload_url'];
    $uploaded = uploadFile($uploadUrl, $path);
    if ($uploaded !== false) {
        $answer = json_decode($uploaded, true);
        if ($answer['error_code']) {
            $params = array(
                'peer_id' => $chat_id,
                'random_id' => time()
            );
            sendMessage($answer['error_msg'], $params);
        } else {
            $request_params = array(
                'audio' => $uploaded,
                'model' => 'neutral'
            );
            $response = $vkrequest->post('asr.process', VK_API_SERVICE_TOKEN, $request_params);
            $task_id = $response['task_id'];
            $process_request = array(
                'task_id' => $task_id
            );
            $proc_not_finished = true;

            while ($proc_not_finished) {
                $response = $vkrequest->post('asr.checkStatus', VK_API_SERVICE_TOKEN, $process_request);
                switch ($response['status']) {
                    case 'processing':
                        time_sleep_until(time() + 3);
                        continue 2;
                        break;
                    case 'internal_error':
                    case 'transcoding_error':
                    case 'recognition_error':
                        $messages_param = array(
                            'peer_id' => $chat_id,
                            'random_id' => time(),
                            'message' => "Ошибка распознавания, попробуйте позже"
                        );
                        $messages->send(VK_API_ACCESS_TOKEN, $messages_param);
                        $proc_not_finished = false;
                        break;
                    case 'finished':
                        $text = mb_strtolower(str_replace(array("?", "!", ",", ";", " ", "."), "", $response['text']), "UTF-8");
                        $message = getMessage($text, $chat_id, $date, $user_name);
                        if ($message != "") {
                            $activity_params = array(
                                'peer_id' => $chat_id,
                                'group_id' => VK_BOT_GROUP_ID,
                                'type' => 'typing'
                            );
                            $messages->setActivity(VK_API_ACCESS_TOKEN, $activity_params);
                            time_sleep_until(time() + 2);
                            $message_params = array(
                                'peer_id' => $chat_id,
                                'random_id' => $date,
                                'message' => $message
                            );
                            $messages->send(VK_API_ACCESS_TOKEN, $message_params);
                        }
                        $proc_not_finished = false;
                        break;
                }
            }
        }
    }
    unlink($path);
}

function uploadFile($url, $filename)
{

    $file = curl_file_create($filename, 'audio/mp3', $filename);
    $ch = curl_init($url);
    $meta = array(
        'file' => $file
    );
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('User-Agent: Opera/9.80 (Windows NT 6.2; Win64; x64) Presto/2.12.388 Version/12.15', 'Referer: http://shirik78.hostingem.ru', 'Content-Type: multipart/form-data'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $meta);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        $result = false;
    }
    curl_close($ch);
    return $result;
}

function str_contains($haystack, $needle)
{
    return $needle !== '' && mb_strpos($haystack, $needle) !== false;
}

function safe_send_message(array $user_ids, $random_id, $message, $keyboard = null, $attachment = null)
{
    global $messages;
    $allowed_users = [];
    $disallowed_users = [];
    foreach ($user_ids as $user_id) {
        $params_for_check = array(
            'user_id' => $user_id,
            'group_id' => VK_BOT_GROUP_ID
        );
        $response = $messages->isMessagesFromGroupAllowed(VK_API_ACCESS_TOKEN, $params_for_check);

        if ($response['is_allowed'] == 1) {
            array_push($allowed_users, $user_id);
        } else {
            array_push($disallowed_users, "@id" . $user_id);
        }
        time_sleep_until(time() + 2);
    }

    if (count($allowed_users) > 0) {
        $request_params = array(
            'peer_ids' => implode(",", $allowed_users),
            'keyboard' => $keyboard,
            'random_id' => $random_id,
            'message' => $message,
            'attachment' => $attachment
        );
        $messages->send(VK_API_ACCESS_TOKEN, $request_params);
    }
    if (count($disallowed_users) > 0) {
        $alert_params = array(
            'peer_ids' => array(
                '0' => BOT_AUTHOR,
                '1' => LENA_DRU
            ),
            'random_id' => time(),
            'message' => "Следующие юзеры все еще мне не писали: " . implode(",", $disallowed_users)
        );
        $messages->send(VK_API_ACCESS_TOKEN, $alert_params);
    }
}

function handleWallReply($link, $text)
{
}

function getInforgs()
{
    $string_url = "https://docs.google.com/uc?export=download&id=1WbOhdU4DfovV2g5g_UNHl7rc-RHmJznB";
    $inforgs = json_decode(sortJSONArrayByName(file_get_contents($string_url)), true);
    for ($i = 0; $i < count($inforgs); $i++) {
        $inforg = $inforgs[$i];
        if (str_contains($inforg['name'], "ё")) {
            $new_name = str_replace("ё", "е", $inforg['name']);
            $new_phones = $inforg['phones'];
            $new_id  = $inforg['vk_id'];
            $new_inforg = array(
                'name' => $new_name,
                'phones' => $new_phones,
                'vk_id' => $new_id
            );
            $inforgs[$i] = $new_inforg;
        }
    }

    return $inforgs;
}

function get_random_user_id(array $user_ids)
{
    $random_num = rand(0, count($user_ids));
    return $user_ids[$random_num];
}
