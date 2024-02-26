<?php
setlocale(LC_ALL, 'ru_RU.UTF-8');
require_once __DIR__ . '/Loaders/DriveApiLoader.php';
use Loaders\DriveAPILoader;


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
$board = new VK\Actions\Board($vkrequest);

//$callbackApiHandler = new VK\CallbackApi\VKCallbackApiHandler; todo на потом

#reading incoming request 
$data = json_decode
(
    file_get_contents('php://input'),true);

$type = $data['type'];
$object = $data["object"];
#todo: use & override methods from SDK
if ($data['group_id'] == VK_BOT_GROUP_ID){
    switch ($type){
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
            markAsRead($incomMessage["peer_id"],$incomMessage["id"]);
            $text = str_replace("/","",$incomMessage['text']);
            $text=mb_strtolower($text,"UTF-8");
                $set_of_text = explode(" ", $text);
                $date = $incomMessage['date'];
                $message_id = $incomMessage['id'];
                $conversationMessageId = $incomMessage["conversation_message_id"];
                $chat_id = $incomMessage['peer_id'];
                $user_id = $incomMessage['from_id'];
                            
                $user_info = getUserInfo($user_id);
                $user_name = '@id'.$user_id.'('.$user_info["first_name"].')';
                $message = '';
                $q = '';
                $action = $incomMessage["action"];
                $ac_type = $action["type"];
                
                $dog = "@";
                $hash = "#";
                $hasDog = strpos($text,$dog,0);
                $has_hash = strpos($text,$hash,0);
    
                #обработка вложений
                if($incomMessage['attachments'] != null){
                    $attachments = $incomMessage['attachments'];
                    #смотрим только первый объект
                    $attachment = $attachments[0];
                    $attachment_type = $attachment['type'];
                    switch($attachment_type){
                        case 'audio_message':
                            $aud_message = $attachment['audio_message'];
                            $file_url = $aud_message['link_mp3'];
                            handleVoiceMessage($file_url,$chat_id, $date, $user_info);
                        break;
    
                        case 'wall_reply':
                            $reply = $attachment['wall_reply'];
                            $reply_text = $reply['text'];
                            $from_user = getUserInfo($reply['from_id']);
                            $date = $reply['date'];
                            $link = 'https://vk.com/wall' . $reply['owner_id'] . '_' . $reply['post_id'];
                            #todo: $message = getMessage();
                            $message = $incomMessage['text'].PHP_EOL.$link.PHP_EOL."@id".$reply['from_id']."(".$from_user["first_name"]." ".$from_user["last_name"].")";
                            $attach = "wall".$reply['owner_id']."_".$reply['id'];
                            $textArray = explode(" ", $text);
                            $firstWord = array_shift($textArray);
                            if(mb_strtolower($firstWord) == 'инфоргу'){
                                handleWallReply($textArray, $attach, $chat_id);
                            }
                        break;
                    }
                } else {
                    if($incomMessage["payload"]){
                        $payload = json_decode($incomMessage["payload"],true);
                        $command = explode(" ", mb_strtolower($payload["command"]));
                        $message = getMessage($command,$chat_id,$date,$user_info);
                    } else {
                        if($hasDog === false){  //$message_object -> hasAtSign()
                            if($has_hash === false){    
                                //$message_object -> hasOctotorp()
                                $message = getMessage($set_of_text,$chat_id,$date,$user_info, $conversationMessageId);
                            } else {
                                if($chat_id != WORK_CHAT){
                                    $obj =  json_decode(getPost(implode(" ",$set_of_text)),true);
                                    $message = $obj["message"];
                                    $attach = $obj["attachment"];
                                    $source = $obj["content_source"];
                                }
                            }
                        } else if (strpos($set_of_text[0],"all",0)>-1){
                         #do nothing

                        } else {    
                            if(strpos($set_of_text[0],'club198797031',0)>-1){
                                $message = getMessage($set_of_text[1],$chat_id,$date,$user_info,$conversationMessageId);
                            } else {
                                handleMessageWithDog($incomMessage);
                            }       
                        }   
                    }
                } 
            
            
            if($message != "" || $attach != ""){
                setActivity($chat_id);   
                $messageParams = array(
                    'message' => $message,
                    'random_id' => $incomMessage["date"],   //$message_object ->getDate()
                    'peer_id' => $incomMessage["peer_id"],  //$message_object ->getPeerId()
                    'attachment' => $attach,
                    'content_source' => $source
                );
               $result =  $messages -> send(VK_API_ACCESS_TOKEN, $messageParams); 
            }   

            markAsAnswered($incomMessage["peer_id"]);   //$message_object -> getPeerId()

            break;

        case 'message_edit':
            echo 'ok';
            markAsRead($object["peer_id"], $object["id"]);
            $text = str_replace("/", "", mb_strtolower($object["text"], "UTF-8"));
            $isDog = strpos($text,"@",0);
            $isHash = strpos($text, "#",0);
            $user = getUserInfo($object["from_id"]);
            $message_id = $object['conversation_message_id'];
            if($isDog === false && $isHash === false){
                $message = getMessage($text, $object["peer_id"], $object["date"], $user, $object["conversation_message_id"]);
            } else if($isHash > -1){
                $obj =  json_decode(getPost($object["text"]),true);
                    $message = $obj["message"];
                    $attach = $obj["attachment"];
            } else {
                    if(strpos($text,"all",0)> -1){}
                    handleMessageWithDog($object);
            }
            
            if($message != ''){
                setActivity($object["peer_id"]);
                $message_params = array(
                    'random_id' => $object["update_time"],
                    'peer_id' => $object["peer_id"],
                    'message' => $message,
                    'attachment' => $attach
                );
                $messages -> send(VK_API_ACCESS_TOKEN, $message_params);
            }
            markAsAnswered($object["peer_id"]);
            
            break;

        case 'group_join':
            echo 'ok';
            $user_id=$object["user_id"];
            $fields = 'sex';
            $user = getUserInfo($user_id, 'nom', $fields);
            switch($user['sex']){
                case '1':
                $join ='пришла ко мне))';
                break;
                case '2':
                $join ='пришел к нам!';
                break;
                case "0":
                $join = "пришло в группу!";
                break;
            }
            
            $message="@id{$user['id']}({$user['first_name']} {$user['last_name']}) {$join}";
            $peer_ids = BOT_AUTHOR . "," . LENA_DRU;
            $params = array(
                'random_id'=>time(),
                'message'=>$message,
                'peer_ids'=>$peer_ids
            );
            
            $result = $messages -> send(VK_API_ACCESS_TOKEN,$params);
            break;

        case 'group_leave':
            echo 'ok';
            $user_id = $object["user_id"];
            $user = getUserInfo($user_id,'nom',"sex");
            switch ($user["sex"]){
                case '1':
                    $leaved = "сбежала из группы";
                    break;
                case '2':
                    $leaved = "свалил из группы))";
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
            
            $result = $messages -> send(VK_API_ACCESS_TOKEN, $params);
            break;
            
        case 'board_post_new':
            echo "ok";
            global $messages;
            $topic = $object["topic_id"];
            if ($topic == DOLG_TOPIC_ID){
                $dezh = readTable();
                #читаем обсуждения
                $params = array(
                    'group_id' => VK_BOT_GROUP_ID,
                    'topic_id'=> DOLG_TOPIC_ID
                );
                $dolgResponse = $board -> getComments(VK_API_SERVICE_TOKEN, $params);
                $comments = $dolgResponse['items'];
                $first_comment = array_shift($comments);
                #отправка Дежурного
                $text = $dezh;
                $messageParams = array(
                    "peer_id"=> WORK_CHAT,
                    "random_id" => 0,
                    'message'=>$text
                );
                $messages->send(VK_API_ACCESS_TOKEN, $messageParams);
                
                #все посты с долгами
                foreach($comments as $comment ){
                    $text = $comment["text"];
                    searchDistrict("гатчинский (гатчина + г. п.)", mb_strtolower($text));
                    searchDistrict("вписки",mb_strtolower($text));
                    $messageParams = array(
                        "peer_id"=> WORK_CHAT,
                        "random_id" => 0,
                        'message'=>$text
                    );
                    $messages->send(VK_API_ACCESS_TOKEN, $messageParams);
                }   

                #$answer = sendMessage($message, $messageParams);
            }
            
            break;

        case 'board_post_edit':
            echo "ok";
            global $messages;
            $groups = new VK\Actions\Groups($vkrequest);
            $topic = $object["topic_id"];
            $comment_author_id = $object["from_id"];
            if ($topic == DOLG_TOPIC_ID){
                if($comment_author_id > 0){
                    $user = getUserInfo($comment_author_id, 'ins');
                    $fullName = $user["first_name"] . " " . $user["last_name"];
                    $name_string = "@id{$comment_author_id}({$fullName})";
                } else {
                    $name_string = "от имени Бота";
                }
                
                $message = "Список долгов обновлен {$name_string}, " . date("H:i", time());
                $messageParams = array(
                    'peer_id' => BOT_AUTHOR,
                    'random_id' => '0',
                    'message'=> $message
                );
                
                $messages->send(VK_API_ACCESS_TOKEN, $messageParams);
                #$answer = sendMessage($message, $messageParams);
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

            $response = $vkrequest -> post('messages.sendMessageEventAnswer', VK_API_ACCESS_TOKEN, $params);
            $date = time();
            getMessage($payload["command"], $object["user_id"], $date, $user);

        break;
        }
    } else {
        $gr = $data["group_id"];
        $messageParams = array(
            'peer_id' => BOT_AUTHOR,
            'random_id' => $object['date']
        );
        $message = "message for $gr see Callback API in group";
        sendMessage($message,$messageParams);
    }

    function clearCommand($text){
        #нихуя не делать.))
    }


   /**
    * @param string text 
    * @return string JSON
    **/ 
 function getPost($text){
        $text = str_replace("ё","е", $text);
        global $vkrequest;
        global $messages;
        $wall = new VK\Actions\Wall($vkrequest);
        $inforgs = getInforgs();#json_decode(sortJSONArrayByName(file_get_contents('https://docs.google.com/uc?export=download&id=1WbOhdU4DfovV2g5g_UNHl7rc-RHmJznB')),true); 
        $la_dog_inforgs = json_decode(sortJSONArrayByName(file_get_contents("la_dog_inforgs.json")), true); #'https://docs.google.com/uc?export=download&id=1XOcUyY2Wihcu9k5i70j179J2oS0sK39h'

        $in_la_dog = is_in_la_dog($la_dog_inforgs, $text);
        
        $request = array(
            'domain'=>'lizaalert_piter',
            'query'=>substr($text, strpos($text, "#")),
            'owners_only' => '1'
        );           
        $response = $wall -> search(VK_API_SERVICE_TOKEN, $request);
        
        if($response['count'] == 0){       
            $ar = array(
                'message'=>"Я такой фамилии не нашел &#128530;" . PHP_EOL . "Возможно, опечатка?",
            );
            $result=json_encode($ar);
        } else {
            $post=array_shift($response['items']);
            if(!$post){
                $ar = array(
                'message'=>"Я такой фамилии не нашел &#128530;" . PHP_EOL . "Возможно, опечатка?",
                'attachment'=>null
                );
                $result=json_encode($ar);
            }
            $inf="Инфорг";
            $infs = "Инфорги";
            if(str_contains($post['text'], $inf) || str_contains($post['text'], $infs)){
                $post_text = $post['text'];
                $owner_id = $post['owner_id'];
                $post_id = $post['id'];
                $attachments = $post['attachments'][0];
                $photo = $attachments['photo'];
                $id = $photo['id'];
                $key = $photo['access_key']; 
                $photo_url = "https://vk.com/photo{$owner_id}_{$id}";
                $post_strings = explode("\n",$post_text);
                $first_string = $post_strings[0];
                $inforg = strpos($post_text,$inf,0);
                $inf_str = substr($post_text,$inforg);
                $inforges = strpos($post_text,$infs,0);
                $infs_str = substr($post_text,$inforges);
                $contains = false;
                $symbolsToReplace = ["(",")","ё"];
                $symbolsForReplace = ["","","е"];
                $replaced_inf = str_replace($symbolsToReplace, $symbolsForReplace, $inf_str);
                $replaced_infs = str_replace($symbolsToReplace, $symbolsForReplace, $infs_str);
                foreach($inforgs as $inforg){
                    $phones = $inforg["phones"];
                        if(str_contains($replaced_inf, $inforg['name']) || str_contains($replaced_infs, $inforg['name'])){    
                            foreach($phones as $phone){         
                                if(str_contains($replaced_inf ,$phone["phone"]) || str_contains($replaced_infs, $phone["phone"])){
                                    $contains = true;
                                    break 1;
                                }
                            }
                        }
                }
                if(!$contains){
                    $repl_inf = ($replaced_inf == "") ? $replaced_infs:$replaced_inf;
                    $mess_param = array(
                        'message' => "Номер инфорга не в списке: ".PHP_EOL.$repl_inf.PHP_EOL.'https://vk.com/wall'.$owner_id.'_'.$post_id,
                        'random_id'=>'0',
                        'peer_ids'=>BOT_AUTHOR . "," . LENA_DRU
                    );
                    $messages -> send(VK_API_ACCESS_TOKEN, $mess_param);
                }
                $message="";
                if (strpos($post_text, "ВЫЕЗД", 0) > 0){
                    for ($i=0; $i < count($post_strings)-2; $i++){
                        if($i==1){
                            continue;
                        }
                        $message = $message.$post_strings[$i]."\n";
                    }
                        $message = $message."\n"."https://vk.com/photo".$owner_id."_".$id;
                } else {
                    $message = $post_strings[0] . "\n" . "Максимальный репост, пожалуйста! \n" . $inf_str . PHP_EOL . $photo_url . PHP_EOL . "Источник:" . PHP_EOL . "vk.com/wall" . $owner_id . "_" . $post_id;
                }
                $attach = "photo".$post['owner_id'] . "_" . $photo['id'] . "_" . $key;
            
                $source = array(
                    'type' => 'url',
                    'url' => "https://vk.com/wall" . $owner_id . "_" . $post_id
                );
                $ar = array(
                    'message'=>$message,
                    'attachment'=>$attach,
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
                $post_strings = explode("\n",$post_text);
                $match_string = "@[a-zА-Я]+\s\W[a-zА-Я]+@ui";   #текст_пробел_#текст ui - utf-8 И игнор регистра
                if(preg_match($match_string, $post_text) == 1){
                    $result = json_encode(
                        array(
                            'message' => $post_strings[0] . PHP_EOL . $post_strings[1] . PHP_EOL . "Максимальный репост, пожалуйста!" . PHP_EOL . $photo_url . PHP_EOL . "Источник:" . PHP_EOL . "vk.com/wall" . $owner_id . "_" . $post_id,
                            'attachment' => "photo" . $post["owner_id"] . "_" . $photo_id . "_" . $key,
                            'content_source' => json_encode(
                                array(
                                    'type' => 'url',
                                    'url' => "https://vk.com/wall{$owner_id}_{$post_id}"
                                )
                            )
                        )
                    );
                    if(!$in_la_dog){
                        $mess_param = array(
                            'message' => "Пост без инфорга не занесен в программу LA_DOG" . PHP_EOL . $text . PHP_EOL . 'https://vk.com/wall' . $owner_id . '_' . $post_id,
                            'random_id'=>'0',
                            'peer_id'=> BOT_AUTHOR
                        );
                        $messages -> send(VK_API_ACCESS_TOKEN, $mess_param);
                    }
                } else {
                    $result = json_encode(
                        array(
                            'message'        => $post_text. PHP_EOL . "Максимальный репост, пожалуйста!" . PHP_EOL . $photo_url . PHP_EOL . "Ссылка на пост:" . PHP_EOL . "https://vk.com/wall" . $owner_id . "_" . $post_id,
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

    function is_in_la_dog($json_inforgs, $text){
        foreach($json_inforgs as $json_inforg){
            foreach($json_inforg["phones"] as $phone){
                if(mb_strtolower($phone["phone"],"UTF-8") == mb_strtolower($text,"UTF-8")){
                    return true;
                }
            }
        }
        return false;
    }		
    function readDutyTable(int $month){    
        $loader = new DriveAPILoader('1rrouNM3lH4n4nG7tJi8Lr0IBahrp4R0Bv26JgL2c-FI');    
        $duty_sheet = $loader->loadTable('График');    
        $year = date('Y', time());    
        $row = (($year - 2020) * 24) + $month*2; #table begin from 2020 & 2 strings per month   
        if($duty_sheet !== null){
            return  $duty_sheet[$row];    
        } else {
            $params = array(
                'format' =>'csv',
                'gid' =>'0'
            );
            $file_id = '1rrouNM3lH4n4nG7tJi8Lr0IBahrp4R0Bv26JgL2c-FI';
            $query = http_build_query($params);
            $serv ='https://docs.google.com/spreadsheets/d/'.$file_id.'/export?' ;
            $csv = file_get_contents($serv.$query);
            $csv = explode("\r\n", $csv);
            $table = array_map('str_getcsv', $csv);
            $str_month = $table[$month*2+96];    //начало с 2020, по 2 строки месяц поэтому +96 [24*4]
            return $str_month;
        }
    }
    function check_whitespaces($month): int{    
        $whites = [];
        for($i=0; $i<2; $i++){  #delete first 2 elements
            $del = array_shift($month);
        }
        foreach($month as $day=>$dezh){
            if(!$dezh || $dezh === "."){
                $whites[] = $day;
            }
        }
        return count($whites);
    }
    
    function check_nextMonth(int $month): int{    
        $nextMonth =  $month + 2;    
        $next_duty_month = readDutyTable($nextMonth);    
        return  check_whitespaces($next_duty_month);
    }
    
    function alert($month, $whites){    
        global $messages;	
        global $dezhur_tab;    
        $text = "@all Всем привет, сегодня 20-е {$month} и пришло время напомнить про дежурства в следующем месяце. Свободных дней аж целых {$whites}!     
        Записываемся, пожалуйста, не стесняемся))	
        {$dezhur_tab}";    
        $params = array(        
            'message' => $text, 
            'peer_id' => CHAT_BOLTALKA,        
            'group_id' => VK_BOT_GROUP_ID,        
            'random_id' => '0'    
        );    
        $messages->send(VK_API_ACCESS_TOKEN, $params);
    }
    
    function getUserId($name){
        global $vkrequest;
        $name = mb_strtolower($name);
        $json_names = json_decode(file_get_contents('vkmethods/user_names.json'), true);
        $id = $json_names[$name];
        if(ctype_digit($id)){
            return "id".$id;
        } else {
           $utils = new VK\Actions\Utils($vkrequest);
           $params = array(
               'screen_name'=> $id
           );
            $response = $utils->resolveScreenName(VK_API_ACCESS_TOKEN, $params);
            return "id".$response["object_id"];
        }
    }

  /**
   * read Google Table for value
   **/
  function readTable(){
    define("HOME","http://f0793013.xsph.ru");
    global $vkrequest;
    $users = new VK\Actions\Users($vkrequest);
    $month = (int)date('m', time());
    $day_num = (int)date('d', time());
    $duty_month = readDutyTable($month);		
    $whites = check_whitespaces($duty_month);	
    $whites_next_month = check_nextMonth($month);
    $text2 = "";
    $dezh = trim($duty_month[$day_num + 1]);
    if($dezh){
        $user_id = trim(getUserId($dezh));
    } else {
    #    $user_ids = [];
    #    $json_users = json_decode(file_get_contents('/vkmethods/user_names.json'), true);
    #    foreach($json_users as $name => $id){
    #        array_push($user_ids, $id);
    #    }
        $user_id = null;#get_random_user_id($user_ids);
        $text2 = "дежурить желающих не нашлось... &#128533;";
    }
    if($user_id){
        $user_params = array(
            'user_ids' => $user_id
        );
        $response = $users -> get(VK_API_SERVICE_TOKEN, $user_params);
        $user = $response[0];
        send_accept_decline_buttons($user);
        $text2 = "дежурит &#129418; @{$user_id}({$dezh})";
    } 
   /** $monthes = [
    *    'января',
    *    'февраля',
    *    'марта',
    *    'апреля',
    *    'мая',
    *    'июня',
    *    'июля',
    *    'августа',
    *    'сентября',
    *    'октября',
    *    'ноября',
    *    'декабря'
    *];

    *$this_month = date('n')-1;
    **/
    $thisMonth = mktime(0,0,0,(int)date('m'));  #взяли номер месяца
    $renamedMonth = mb_strtolower(monthRename(strftime('%B', $thisMonth))); #и переделали в название на русском в род. падеже
    $today = date('j') . " " . $renamedMonth;
    if($day_num == 20 && $whites_next_month > 0){		
        alert($renamedMonth, $whites_next_month);	
    }
    $text = "Привет, сегодня $today, " . $text2;
    return $text;
  }

/**
 * @param jsonArray JSONArray
 * sort JSONArray with Inforgs alphabetical
 * @return string json
 **/
    function sortJSONArrayByName($jsonArray){
        $inforgs = json_decode($jsonArray,true)['inforgs'];
        usort($inforgs, function($a, $b){
          return $a['name'] <=> $b['name'];
        });
        return json_encode($inforgs);
    }

  function send_accept_decline_buttons($user){
    global $messages;
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
    $user_ids = ['0' => $user_id];

    safe_send_message($user_ids, 0, "Привет, {$username}, ты дежуришь?", $js_key );
  }


  /**
  * create message to return in chat or null
  * @param array set_of text
  * @param integer chat_id
  * @param UnixDate date
  * @param String user_name
  * @return String message
  **/

  function getMessage($set_of_text,$chat_id,$date,$user, $messageId=0){
    include 'districts.php';
    global $vkrequest;
    global $messages;
    $board = new VK\Actions\Board($vkrequest);
    $wall = new VK\Actions\Wall($vkrequest);
    global $districts;
    global $dezhur_tab;
    
    $user_name = '@id'.$user['id'].'('.$user["first_name"].')';
    if(!is_array($set_of_text)){
        $command = $set_of_text;
    } else {
        if(mb_strpos($set_of_text[0],'club198797031',0)>-1 || mb_strpos($set_of_text[0],'[club198797031|Бот ГОО]',0)>-1){
            $ar_command = explode("]",$set_of_text[0]);
            $command = trim($ar_command[1]);
        } else {
            $command = $set_of_text[0];
        }
    }
    switch($command){
        case 'dezh_list':
            $result = '';
            $list = json_decode(file_get_contents('vkmethods/user_names.json'), true);
            ksort($list, SORT_LOCALE_STRING);
            foreach($list as $name=>$id){
                $result .= $name.": ".$id."\n";
            }
            $params = array(
                'message'=> $result,
                'random_id'=>'0',
                'peer_id'=>BOT_AUTHOR
            );
            $messages->send(VK_API_ACCESS_TOKEN, $params);
            break;
        case 'начать':   
            $button_names = array(
                '0'=>'Инфорги',
                '1'=>'Дежурство',
                '2'=>'Ещё'
            );
            $commands = array(
                '0' =>'Инфорги',
                '1' =>'Дежурство',
                '2' =>'Ещё'
            );
           $template = createCarousel("Вот что я умею","Основные команды для работы",$commands, $button_names);

            $message = 'Привет!';
            
            $params = array(
                'message' => $message,
                'template' => $template,
                'random_id' =>$date,
                'peer_id'=> $chat_id
            );
            $act_params= array(
                'peer_id'=>$chat_id,
                'group_id'=>VK_BOT_GROUP_ID,
                'type'=>'typing'
            );
            $act = $messages ->setActivity(VK_API_ACCESS_TOKEN,$act_params);
            time_sleep_until(time()+3);
            $response = $messages -> send(VK_API_ACCESS_TOKEN,$params);
            $act_param2 = array(
                'peer_id'                   => $chat_id,
                'start_message_id'          => $message_id,
                'group_id'                  => $group_id,
                'mark_conversation_as_read' => '1'
            );
            $response2 = $messages -> markAsRead(VK_API_ACCESS_TOKEN,$act_param2);
            unset($message);    //return null
            break;

        case 'ещё':
            $button_names = array(
                '0'=>'Районы',
                '1'=>'Долги',
                '2'=>'Команды'
            );
            $commands = array(
                '0' => 'Районы',
                '1' => "Долги",
                '2' => "Команды"
            );
            $template = createCarousel("Вот что я умею","Продолжение",$commands,$button_names);
            $message = "Продолжаем";
            $params = array(
                'message' => $message,
                'template' => $template,
                'random_id' =>$date,
                'peer_id'=> $chat_id
            );
            $messages ->send(VK_API_ACCESS_TOKEN, $params);
            unset($message);
            break;

        case 'duty_accepted':
        $user = getUserInfo($chat_id, "ins");
        $user_name = $user['first_name'] . " " . $user['last_name'];
        $params = array(
            'message' => "Дежурство подтверждено @id{$chat_id}({$user_name}), в " . date("H:i",time()),
            'random_id' => 0,
            'peer_id' => CHAT_TEST
        );
            $messages -> send(VK_API_ACCESS_TOKEN, $params);
            break;

        case 'duty_declined':
        $user = getUserInfo($chat_id, "ins");
        $user_name = $user['first_name'] . " " . $user['last_name'];
            $params = array(
                'message' => "Дежурство отклонено @id{$chat_id}({$user_name}) в " . date("H:i", time()),
                'random_id' => 0,
                'peer_id' => CHAT_TEST
            );

            $messages -> send(VK_API_ACCESS_TOKEN, $params);
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
            $response = $vkrequest -> post('messages.send', VK_API_ACCESS_TOKEN, $params);
            $message="";
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
            foreach($actions as $action){
                array_push($colors, "secondary");
            }
            $js_key = createKeyboard($actions, $colors, false);
            $params = array(
                'message' => 'Нажми меня',
                'keyboard' => $js_key,
                'random_id' => $date,
                'peer_id' => $chat_id
            );
            $response = $vkrequest -> post('messages.send', VK_API_ACCESS_TOKEN, $params);
            $message = "";
        
            break;

        case 'инфорги':
            $inforgs = getInforgs();#json_decode(sortJSONArrayByName(file_get_contents('https://docs.google.com/uc?export=download&id=1WbOhdU4DfovV2g5g_UNHl7rc-RHmJznB')),true); 
            foreach ($inforgs as $inforg){
                $arrayPhones = [];
                foreach($inforg["phones"] as $phone){
                    array_push($arrayPhones, $phone["phone"]);
                }
                $stringPhone = implode(", ", $arrayPhones);
                
                $message .= $inforg["name"] . " - " . $stringPhone. PHP_EOL;
            }
            break;

        case 'районы':
            $message = "ссылка на файл с районами ГОО";
            $actions = array(
                '0' => array(
                    'type'=>'open_link',
                    'label'=>'&#128073; Таблица районов',
                    'link'=>$districts
                )
            );
            $colors = ["secondary"];
            $inline = true;
            $keyboard = createKeyboard($actions, $colors, $inline);
            $params = array(
                'message'=>$message,
                'keyboard'=>$keyboard,
                'peer_id'=>$chat_id,
                'random_id'=>time()
            );
            $response = $messages -> send(VK_API_ACCESS_TOKEN,$params);
            unset($message);
            break;

        case 'дежурство':
            $message = "{$user_name}, держи ссылку на таблицу дежурств";
            $actions = array(
                '0' => array(
                    'type'=>'open_link',
                    'label'=>'Таблица дежурств',
                    'link'=>$dezhur_tab
                )
            );
            $inline = true;
            $color = ['secondary'];
            $keyboard = createKeyboard($actions, $color, $inline);
            $params = array(
                'message'=>$message,
                'keyboard'=>$keyboard,
                'peer_id'=>$chat_id,
                'random_id'=>time()
            );
            $response = $messages->send(VK_API_ACCESS_TOKEN,$params);
            unset($message);
            break;

        case 'showbutton':
            $user = getUserInfo($chat_id);
            send_accept_decline_buttons($user);
            break;
            
        case 'whois':
            $user = getUserInfo($set_of_text[1]);
            $message = "@id" . $user["id"] . "(" .$user["first_name"] . " " . $user["last_name"] . ")";
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
            
        case "вводная":
            $params = array(
                'domain' => 'lizaalert_piter',
                'query' => 'Регистрация по ссылке',
                'owners_only' => '1',
                'count' => '1'
            );
            $response = $wall ->search(VK_API_SERVICE_TOKEN, $params);
            $post = array_shift($response['items']);
            $attachment = $post['attachments'][0];
            $photo = $attachment['photo'];
            $mes_params = array(
                'message' => $post['text'] .PHP_EOL . "\nhttps://vk.com/wall" . $post['owner_id'] . "_" . $post['id'],
                'attachment' => 'photo' . $post['owner_id'] ."_" . $photo['id'] . "_" .$photo['access_key'],
                'peer_id' => $chat_id,
                'random_id' => time()
            );
            $messages -> send(VK_API_ACCESS_TOKEN, $mes_params);
            break;

        case 'адмиралтейский':
            $message = $admiral;
            break;

        case 'василеостровский':
            $message = $vaska;
            break;

        case 'выборгский':
            if($set_of_text[1]){
                $message = $vyborgLO;
            } else {
                $message= $vyborgsk;
            }
            break;

        case 'калининский':
            $message = $kalin;
            break;

        case 'кировский':
            if($set_of_text[1]){
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
            $message=$kurort;
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
            $message=$primor;
            break;

        case 'пушкинский':
            $message = $pushkin;
            break;

        case 'фрунзенский':
            $message=$frunze;
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
            $message= $tichwin;
            break;

        case 'сланцевский':
            $message= $slanez;
            break;

        case 'приозерский':
            $message= $priozer;
            break;

        case 'подпорожский':
            $message= $podporozh;
            break;

        case 'лужский':
            $message= $luga;
            break;

        case 'ломоносовский':
            $message= $lomonos;
            break;

        case 'лодейнопольский':
            $message= $lodeyka;
            break;

        case 'киришский':
            $message= $kirishi;
            break;

        case 'кингисеппский':
            $message= $kingisepp;
            break;

        case 'гатчинский':
            $message= $gatchina;
            break;

        case 'всеволожский':
            $message= $vsevolozh;
            break;

        case 'волховский':
            $message= $volchv;
            break;

        case 'бокситогорский':
            $message= $boxit;
            break;

        case 'волосовский':
            $message= $volosovo;
            break;
        
        case 'долги':
            $params = array(
                'group_id' => VK_BOT_GROUP_ID,
                'topic_id'=> DOLG_TOPIC_ID
            );

            $dolgResponse = $board -> getComments(VK_API_SERVICE_TOKEN, $params);

            $board -> getComments(VK_API_SERVICE_TOKEN, $params);
            $comments = $dolgResponse['items'];
            $first_comment = array_shift($comments);
            $text = "";
            foreach($comments as $comment){
                $text .= $comment["text"]."\n";
            }
            if ($chat_id != CHAT_BOLTALKA && $chat_id != EDUCATION_CHAT && $chat_id != WORK_CHAT && $chat_id != CHAT_TEST){
                $message = $text;
            } else if ($chat_id == CHAT_BOLTALKA){
                $message ="Долги смотрим в [club198797031|личке]";
            } else if ($chat_id == WORK_CHAT || $chat_id == CHAT_TEST){
                $params = array(
                    'random_id'=>0,
                    'peer_id'=>$user['id'],
                    'message'=>"Из рабочего чата удали команду, пожалуйста.\nИ напиши ее мне))"
                    );
                    $messages -> send(VK_API_ACCESS_TOKEN, $params);
            } else {
                $message = "в чате обучения эта команда не работает &#128566;";
            }
            break;
        
        case "добавь":
            $name = $set_of_text[1];
            $id = $set_of_text[2];
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
            if(!$name){
                $message = "имя, сестра, ИМЯ?!©  давай заново!)))";
                break;
            } else {
                if(!$duty_message){
                    $message = "что передавать? заново!)))";
                    break;
                } else {
                    $inforgs = getInforgs();
                    foreach($inforgs as $inforg){
                        $i_name = explode(" ", $inforg['name']);
                        if($name == mb_strtolower($i_name[0]) || $name == mb_strtolower($i_name[1])){
                            if($inforg['vk_id'] != 100){
                                $params = array(
                                    'random_id'=>'0',
                                    'peer_id'=>$inforg['vk_id'],
                                    'message'=>$duty_message
                                );  
                            } else{
                                $params = array(
                                    'random_id'=>'0',
                                    'peer_id'=>'181655184',
                                    'message'=>$duty_message
                                );
                            }
                            if($result = $messages->send(VK_API_ACCESS_TOKEN, $params)){
                                $mess_is_sended = true;
                                break 1;
                            }
                        } else {
                            continue;
                        }
                    }
                }
            }
            if($mess_is_sended){
                $message = "переслано удачно";
            } else {
                $message = "Инфорг с таким именем не найден";
            }
            break;
        case "whites":
            $month = readDutyTable((int)date('m')+$set_of_text[1]);
            $whites = [];
            $del = [];
            for($i=0; $i<2; $i++){  #delete first 2 elements
                $del[] = array_shift($month);
            }
            $monthNum = (int)date('m')+$set_of_text[1];
            $monthN = mktime(0,0,0,$monthNum);
            $monthName = mb_strtolower(strftime('%B', $monthN));
            $year = date('Y');
        
            $message="Дежурства на {$monthName} {$year}: " . PHP_EOL;
            foreach($month as $day=>$dezh){
               $day +=1;
               $message .= "{$day} -> {$dezh}".PHP_EOL ;
                if(!$dezh || $dezh === "."){
                    $whites[] = $day;
                }
            }
            $message  .= "Всего: " . count($month) . PHP_EOL . "Пусто: " . count($whites);
            break;

        default:
            $message="";
    }
      return $message;
  }

    function getJSONUsers(){
        $users = json_decode(file_get_contents("vkmethods/user_names.json"),true);
        $result = "";
        foreach ($users as $name => $id){
            if(ctype_digit($id)){
                $id = "@id" . $id;
            } else {
                $id = "@" . $id;
            }
            $result .= $name . " - " . $id . "\n";
        }
        return $result;
    }

  function addUserToJson($name, $id){
    $file_name = "vkmethods/user_names.json";
    $users_json = json_decode(file_get_contents($file_name),true);
    $users_json[$name] = $id;
    if(file_put_contents($file_name, json_encode($users_json)) !== FALSE){
        return "Имя {$name} добавлено в список дежурящих";
    }
    return "Не удалось добавить {$name} в лист(";
  }

  function deleteUserFromJSON($name){
    $file_name = "vkmethods/user_names.json";
    $users_json = json_decode(file_get_contents($file_name),true);
    unset($users_json[$name]);
    if(file_put_contents($file_name, json_encode($users_json)) !== FALSE){
        return "Юзер $name успешно удален из списка";
    }
    return "Не удалось удалить юзера из списка";
  }

/**
 * обработка сообщения с "@"
 * @param Object $messageObject: объект сообщения ВК
 */

  function handleMessageWithDog($messageObject){
    global $messages;
    
    $inline = true; // button style
    $text = mb_strtolower(str_replace("/", "", $messageObject["text"]));
    $text = str_replace("@","", $text);
    $chat_id = $messageObject["peer_id"];
    $group_id = $messageObject["group_id"];
    $message_id = $messageObject["id"];

    if(DistrictNames::isDistrict($text)){   
        $act_param2 = array(
            'peer_id'                   => $chat_id,
            'start_message_id'          => $message_id,
            'group_id'                  => VK_BOT_GROUP_ID,
            'mark_conversation_as_read' => '1'
        );
        #пометить прочитанным
        $messages -> markAsRead(VK_API_ACCESS_TOKEN,$act_param2);
        $districtes = json_decode(file_get_contents('district_urls.json'),true);
        //форматируем текст
        $message = $districtes[$text];  
        $label = mb_convert_case($text,MB_CASE_TITLE,"UTF-8");
        $actions = [];
        if(!is_array($message)){
            $a1 = array(
                'type'=>'open_link',
                'label'=>$label,
                'link'=>$message
            );
            array_push($actions,$a1);
            $color = ["secondary"];
            $keyboard = createKeyboard($actions, $color, $inline);
        } else {
            $colors = [];
            foreach($message as $mArray){
                foreach ($mArray as $key => $value){
                    $a1 = array(
                        'type' => 'open_link',
                        'label' => mb_convert_case($key,MB_CASE_TITLE,"UTF-8"),
                        'link' => $value
                    );
                    array_push($actions,$a1);
                    array_push($colors, "secondary");
                }
            }
            if(count($actions) > 6){    #больше 6 инлайн кнопок нельзя
                $keyboard = createKeyboard(array_slice($actions,0,6), $colors, $inline);
                $params = array(
                    'message'=>'Список групп района/категории',
                    'keyboard'=>$keyboard,
                    'peer_id'=>$chat_id,
                    'random_id'=>time()
                );
                $act_params= array(
                    'peer_id'=>$chat_id,
                    'group_id'=>VK_BOT_GROUP_ID,
                    'type'=>'typing'
                );
                $response1 = $messages -> setActivity(VK_API_ACCESS_TOKEN,$act_params);
                time_sleep_until(time()+2);
                $response = $messages->send(VK_API_ACCESS_TOKEN,$params);
                $keyboard = createKeyboard(array_slice($actions,6), $colors,$inline);    //max value 6
            } else {
                $keyboard = createKeyboard($actions, $colors, $inline);
            }
        }
                        
        $params = array(
            'message'=>'Список групп района/категории',
            'keyboard'=>$keyboard,
            'peer_id'=>$chat_id,
            'random_id'=>time()
        );
    
        #отправка статуса "печатает..."
        $act_params= array(
            'peer_id'=>$chat_id,
            'group_id'=>VK_BOT_GROUP_ID,
            'type'=>'typing'
        );
        
        $messages -> setActivity(VK_API_ACCESS_TOKEN,$act_params);
        time_sleep_until(time()+2);
    
        $messages->send(VK_API_ACCESS_TOKEN,$params);
    
        $act_param = array(
            'peer_id'                   => $chat_id,
            'answered'                  => 1,
            'group_id'                  => $group_id,
        );
        $messages -> markAsAnsweredConversation(VK_API_ACCESS_TOKEN,$act_param);
    } else if(str_contains($text, "all")){
    #do nothing
    } else {
        $act_params= array(
            'peer_id'=>$chat_id,
            'group_id'=>VK_BOT_GROUP_ID,
            'type'=>'typing'
        );
        
        $messages -> setActivity(VK_API_ACCESS_TOKEN,$act_params);
        time_sleep_until(time()+2);
        $prams = array(
            'peer_id' => $chat_id,
            'random_id' => time(),
            'message' => 'Такого района пока у меня нет, &#128513;'
        );
        $messages -> send(VK_API_ACCESS_TOKEN, $prams);
        $act_param = array(
            'peer_id'                   => $chat_id,
            'answered'                  => 1,
            'group_id'                  => $group_id,
        );
        $messages -> markAsAnsweredConversation(VK_API_ACCESS_TOKEN,$act_param);
    }
}

/**
 * обработка голосового сообщения
 * @param String $file_url: URL with voice message
 * @param Int $chat_id
 * @param String $date: format "DDMMYYYY"
 * @param String $user_name
 */

function handleVoiceMessage($file_url, $chat_id, $date, $user_name){
    global $vkrequest;
    global $messages;
    $path = "temp/{$chat_id}_{$date}.mp3";
    $tmp_file = fopen($path, 'w');
    $content = file_get_contents($file_url);
    fwrite($tmp_file, $content);
    fclose($tmp_file);
    $msg = "";
    $url_response = $vkrequest -> post('asr.getUploadUrl', VK_API_SERVICE_TOKEN);
    $uploadUrl = $url_response['upload_url'];
    $uploaded = uploadFile($uploadUrl, $path);
    if($uploaded !== false){
        $answer = json_decode($uploaded, true);
        if($answer['error_code']){
            $params = array(
                'peer_id' => $chat_id,
                'random_id' => time()
            );
            sendMessage($answer['error_msg'],$params);
        } else {
            $request_params = array(
                'audio' => $uploaded,
                'model' => 'neutral'
            );
            $response = $vkrequest -> post('asr.process',VK_API_SERVICE_TOKEN, $request_params);
            $task_id = $response['task_id'];
            $process_request = array(
                'task_id' => $task_id
            );
            $proc_not_finished = true;

            while($proc_not_finished){
                $response = $vkrequest -> post('asr.checkStatus', VK_API_SERVICE_TOKEN, $process_request);
                switch ($response['status']){
                    case 'processing':
                        time_sleep_until(time()+3);
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
                        $messages -> send(VK_API_ACCESS_TOKEN, $messages_param);
                        $proc_not_finished = false;
                        break;
                    case 'finished': 
                        $text = mb_strtolower(str_replace(array("?","!",",",";"," ","."), "", $response['text']), "UTF-8");
                        $message = getMessage($text, $chat_id, $date, $user_name);                    
                        if($message != ""){
                            $activity_params = array(
                                'peer_id' => $chat_id,
                                'group_id' => VK_BOT_GROUP_ID,
                                'type' => 'typing'
                            );
                            $messages -> setActivity(VK_API_ACCESS_TOKEN, $activity_params);
                            time_sleep_until(time() + 2);
                            $message_params = array(
                                'peer_id' => $chat_id,
                                'random_id' => $date,
                                'message' => $message
                            );
                            $messages -> send(VK_API_ACCESS_TOKEN, $message_params);
                        }
                        $proc_not_finished = false;
                        break;
                }
            }
        }
    }
    unlink($path);
}

/**
 *upload file with POST method to URL
 * @param String $url
 * @param String $filename
 */

function uploadFile($url, $filename){

  $file = curl_file_create($filename,'audio/mp3',$filename);
  $ch = curl_init($url);
  $meta = array(
    'file' => $file
  );
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER,array('User-Agent: Opera/9.80 (Windows NT 6.2; Win64; x64) Presto/2.12.388 Version/12.15','Referer: http://shirik78.hostingem.ru','Content-Type: multipart/form-data'));
  curl_setopt($ch, CURLOPT_POSTFIELDS, $meta);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

   $result = curl_exec ($ch);
  if (curl_errno($ch)) {
      $result = false;
  }
    curl_close ($ch);
  return $result;
}

/**
 * безопасная отправка сообщения ВК одному или нескольким юзерам
 * один из параметров должен быть не null: или $message или $attachment
 * @param Array $user_ids: id юзера/ов
 * @param Int $random_id
 * @param String $message
 * @param Object $keyboard : объект клавиатуры ВК
 * @param Object $attachment: объект вложения к сообщению
 */
function safe_send_message(array $user_ids, $random_id, $message, $keyboard = null, $attachment = null){
    global $messages;
    $allowed_users = [];
    $disallowed_users = [];
    foreach($user_ids as $user_id){
        $params_for_check = array(
            'user_id' => $user_id,
            'group_id' => VK_BOT_GROUP_ID
        );
        $response = $messages -> isMessagesFromGroupAllowed(VK_API_ACCESS_TOKEN, $params_for_check);
      
        if($response['is_allowed'] == 1){
            array_push($allowed_users, $user_id);
        } else {
            array_push($disallowed_users, "@id" . $user_id);
        }
        time_sleep_until(time() + 2);
    }
  
    if(count($allowed_users) > 0){
        $request_params = array(
            'peer_ids' => implode(",", $allowed_users),
            'keyboard' => $keyboard,
            'random_id' => $random_id,
            'message' => $message,
            'attachment' => $attachment
        );
        $messages -> send(VK_API_ACCESS_TOKEN, $request_params);
    }
    if(count($disallowed_users) > 0){
        $alert_params = array
        (
            'peer_ids' => array
            (
                '0' => BOT_AUTHOR,
                '1' => LENA_DRU
            ),
            'random_id' => time(),
            'message' => "Следующие юзеры все еще мне не писали: " . implode(",", $disallowed_users)
        );
        $messages -> send(VK_API_ACCESS_TOKEN, $alert_params);
    }
}

/**
 * обработка аттача в сообщении
 * шлет инфоргу аттач
 * или шлет отправителя))
 */

function handleWallReply(array $text, $attachment, $peer_id){
    global $messages;
    $mess_is_sended = false;
    $name = array_shift($text);
    $inforgs = getInforgs();
    foreach($inforgs as $inforg){
        $i_name = explode(" ", $inforg['name']);
        if($name == mb_strtolower($i_name[0]) || $name == mb_strtolower($i_name[1])){
            if($inforg['vk_id'] != 100){
                $params = array(
                    'random_id'=>'0',
                    'peer_id'=>$inforg['vk_id'],
                    'message'=>implode(" ", $text),
                    'attachment'=>$attachment
                );  
            } 
            else{
                $params = array(
                    'random_id'=>'0',
                    'peer_id'=>'181655184',
                    'message'=>implode(" ", $text),
                    'attachment'=>$attachment
                );
            }
            if($result = $messages->send(VK_API_ACCESS_TOKEN, $params)){
                $mess_is_sended = true;
                break 1;
            }
            
        } 
        else {
            continue;
        }
    }

    if($mess_is_sended){
        $message = "переслано удачно";
    } else {
        $message = "Инфорг с таким именем не найден";
    }
    $parameters = array(
        'message'=>$message,
        'peer_id'=>$peer_id,
        'random_id'=>'0'
    );
    $messages->send(VK_API_ACCESS_TOKEN, $parameters);
}

/**
 * возвращает список инфоргов
 * @return Array[Object]
 */

function getInforgs(){
   // $string_url = "https://docs.google.com/uc?export=download&id=1WbOhdU4DfovV2g5g_UNHl7rc-RHmJznB";
    $inforgs = json_decode(sortJSONArrayByName(file_get_contents("api/inforgs.json")),true);
    for ($i=0; $i<count($inforgs); $i++){
        $inforg = $inforgs[$i];
        if(str_contains($inforg['name'], "ё")){
            $new_name = str_replace("ё", "е", $inforg['name']);
            $new_phones = $inforg['phones'];
            $new_id  = $inforg['vk_id'];
            $new_inforg = array(
                'name'=>$new_name,
                'phones'=>$new_phones,
                'vk_id'=>$new_id
            );
            $inforgs[$i] = $new_inforg;
        }
    }

    return $inforgs;
}

/**
 * из списка выбирает рандомно 1 вариант и возвращает его
 * @param $user_ids: Array[Int]
 * @return Int|false
 */

function get_random_user_id(array $user_ids)
{
    if(count($user_ids)>0){
        $random_num = rand(0, count($user_ids)-1);
        return $user_ids[$random_num];
    }
    return false;
}

/**
 * ищет название района в тексте долгов
 * отправляет результат в лс мне)
 * @param $disrict: String
 * @param $text: String
 */

function searchDistrict($district, $text){

    if(str_contains($text, $district)){
        sendToAuthor("Ваш район '{$district}' есть в долгах!");
    } else {
        sendToAuthor("Вашего района '{$district}' в долгах нет");
    }
}

/**
 * отправляет сообщение мне в ВК
 * @param $text: String
 */

function sendToAuthor($text){
    global $messages;
    
    $params = array(
        'peer_id'=>BOT_AUTHOR,
        'random_id'=>0,
        'message'=> $text
        );
    $messages -> send(VK_API_ACCESS_TOKEN, $params);
}

function monthRename($month){
    $letters = ["ь","й"];
    $lastChar = mb_substr($month, -1);
    if($lastChar == "ь" || $lastChar == "й"){
        return str_replace($letters,"я", $month);
    }
    return $month . 'а';
}
