<?php
setlocale(LC_ALL, 'ru_RU.UTF-8');
require_once __DIR__ . '/Loaders/DriveApiLoader.php';
require_once __DIR__ . '/Classes/DataBaseInforgOperator.php';
require_once 'constants.php';
require_once 'oFile.php';
include 'vk_methods.php';
require_once 'districts.php';
require_once 'carousel.php';
require_once 'district_names.php';
require_once 'vendor/autoload.php';
date_default_timezone_set("Europe/Moscow");

use Loaders\DriveAPILoader;
use Classes\DataBaseInforgOperator;

$districts = "https://vk.cc/cbklQu";
$dezhur_tab = "https://vk.cc/c1pCx2";

#@param $vkrequest from vk_methods.php
$messages = new VK\Actions\Messages($vkrequest);
$board = new VK\Actions\Board($vkrequest);

//$callbackApiHandler = new VK\CallbackApi\VKCallbackApiHandler; todo на потом

#reading incoming request 
$data = json_decode(file_get_contents('php://input'),true);

$requestType = $data['type'];
$object = $data["object"];
#todo: use & override methods from SDK
if ($data['group_id'] == VK_BOT_GROUP_ID)
{
    switch ($requestType)
    {
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
            
            $text = str_replace("/","",$incomMessage['text']);
            $text=mb_strtolower($text,"UTF-8");
            $set_of_text = explode(" ", $text);
            $date = $incomMessage['date'];
            $message_id = $incomMessage['id'];
            $conversationMessageId = $incomMessage["conversation_message_id"];
            $chat_id = $incomMessage['peer_id'];
            $user_id = $incomMessage['from_id'];
            markAsRead($chat_id,$message_id);
            $user_info = getUserInfo($user_id);
            $user_name = '@id'.$user_id.'('.$user_info["first_name"].')';
            $message = '';
            $q = '';
            $action = $incomMessage["action"];
            $ac_type = $action["type"];
            
            $dog = "@";
            $hash = "#";
        
            $hasDog = strpos($text,$dog);
            $has_hash = strpos($text,$hash);
        
    
                #обработка вложений
            if($incomMessage['attachments'] != null)
            {
                $attachments = $incomMessage['attachments'];
                #смотрим только первый объект
                $attachment = $attachments[0];
                $attachment_type = $attachment['type'];
                switch($attachment_type)
                {
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
                    case 'sticker':
                        $sticker = $attachment["sticker"];
                        if($sticker["sticker_id"] == 77663 && $sticker["product_id"] == 1645){
                            $message = readArrears($chat_id, $user_id);
                        }
                        break;
                }
            } 
            else 
            {
                if(isset($incomMessage["payload"]))
                {
                    $payload = json_decode($incomMessage["payload"],true);
                    //$command = explode(" ", mb_strtolower($payload["command"]));
                    $message = getMessage(mb_strtolower($payload["command"]),$chat_id,$date,$user_info);
                } 
                else 
                {
                    if($hasDog === false)
                    {  //$message_object -> hasAtSign()
                        if($has_hash === false)
                        {    
                            //$message_object -> hasOctotorp()
                            $message = getMessage($text,$chat_id,$date,$user_info, $conversationMessageId);
                        } 
                        else 
                        {
                            if($chat_id != WORK_CHAT)
                            {
                                $obj =  json_decode(getPost($text),true);
                                $message = $obj["message"];
                                $attach = $obj["attachment"];
                                $source = $obj["content_source"];
                            }
                        }
                    } 
                    else if (strpos($set_of_text[0],"all",0)>-1)
                    {
                     #do nothing
                    } 
                    else if(strpos($set_of_text[0],'club198797031',0)>-1)
                    {
                        $message = getMessage($set_of_text[1],$chat_id,$date,$user_info,$conversationMessageId);
                    } 
                    else 
                    {
                        handleMessageWithDog($incomMessage);
                    }       
                    
                }
            } 
            
            
            if($message != "" || $attach != "")
            {
                setActivity($chat_id);   
                $messageParams = array(
                    'message' => $message,
                    'random_id' => $date,   //$message_object ->getDate()
                    'peer_id' => $chat_id,  //$message_object ->getPeerId()
                    'attachment' => $attach,
                    'content_source' => $source
                );
               $messages -> send(VK_API_ACCESS_TOKEN, $messageParams); 
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
                    #TODO   $messageParams = handleMessageWithDog($object);
                    #       $messages -> setActivity($activityParams);
                    #       $messages -> edit(..$messageParams);
            }
            
            if($message != ''){
                setActivity($object["peer_id"]);
                #$message_params = array(
                #    'random_id' => $object["update_time"],
                #    'peer_id' => $object["peer_id"],
                #    'message' => $message,
                #    'attachment' => $attach
                #);
                #$messages -> send(VK_API_ACCESS_TOKEN, $message_params);
                editMyMessage($object['peer_id'],$object['id']+1, $message, $attach);
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
            $topic = $object["topic_id"];
            if ($topic == DOLG_TOPIC_ID)
            {
                #запишем в  файл "dolg_...txt"
                $date = date('jS\_F');
                $filePath = "dolg_".$date.".txt";
                $tmp_file = fopen($filePath, 'w');
                fwrite($tmp_file, $object["text"]);
                fclose($tmp_file);
                
                $dezh = readTable();
                #читаем обсуждения
                $params = array
                (
                    'group_id' => VK_BOT_GROUP_ID,
                    'topic_id'=> DOLG_TOPIC_ID
                );
                $dolgResponse = $board -> getComments(VK_API_SERVICE_TOKEN, $params);
                $comments = $dolgResponse['items'];
                #первый  отщепляем
                $first_comment = array_shift($comments);
                #отправка Дежурного
            
                $messageParams = array
                (
                    "peer_id"=> WORK_CHAT,
                    "random_id" => $object["date"],
                    'message'=>$dezh
                );
                $messages->send(VK_API_ACCESS_TOKEN, $messageParams);
                
                #все посты с долгами
                foreach($comments as $key => $comment )
                {
                    $text = $comment["text"];
                    searchDistrict("гатчинский (гатчина + г. п.)", mb_strtolower($text));
                    searchDistrict("вписки",mb_strtolower($text));
                    $messageParams = array
                    (
                        "peer_id"=> WORK_CHAT,
                        "random_id" => $object["date"].$key,
                        'message'=>$text
                    );
                    $messages->send(VK_API_ACCESS_TOKEN, $messageParams);
                }   
            }
            
            break;

        case 'board_post_edit':
            echo "ok";
            $date = date('jS\_F');
            $filePath = "dolg_".$date.".txt";
            $tmp_file = fopen($filePath, 'w');
            fwrite($tmp_file, $object["text"]);
            fclose($tmp_file);
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
                
                $message = "Список долгов обновлен {$name_string}, " . date("H:i:s", time());
                $messageParams = array
                (
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
            $message_id = $object['conversation_message_id'];
            $user = getUserInfo($object['user_id']);
            $user_name = $user['first_name'];
            $event_data = array(
                'type' => 'show_snackbar',
                'text' => "Спасибо, {$user_name}, ответ отправлен &#128077; сообщение с кнопками я удалю)"
            );
            $js_data = json_encode($event_data);
            $params = array(
                'event_id' => $object["event_id"],
                'user_id' => $object["user_id"],
                'peer_id' => $object["peer_id"],
                'event_data' => $js_data
            );

            #$response = $messages -> sendMessageEventAnswer(VK_API_ACCESS_TOKEN, $params);
            $vkrequest -> post('messages.sendMessageEventAnswer', VK_API_ACCESS_TOKEN, $params);
            $date = time();
            # удаляем сообщение с кнопками
            $deleteMessageArray = array
            (
                'cmids' => $message_id,
                'group_id' => VK_BOT_GROUP_ID,
                'delete_for_all' => 1,
                'peer_id' => $object['user_id']
            );
            #$messages -> delete(VK_API_ACCESS_TOKEN, $deleteMessageArray);
            $result=$vkrequest -> post('messages.delete', VK_API_ACCESS_TOKEN, $deleteMessageArray);
            
            getMessage($payload["command"], $object["user_id"], $date, $user);
            break;
            
        case 'message_read':
            echo 'ok';
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
        $inforgs = getInforgs();

        $in_la_dog = is_in_la_dog($text);
        
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
                    if(str_contains($post_strings[0], "UPD"))
                    {
                        $post_strings[0] = $post_strings[2];#"ВНИМАНИЕ! ПОМОГИТЕ НАЙТИ ЧЕЛОВЕКА/ПОДРОСТКА/РЕБЕНКА";
                    }
                    $message = $post_strings[0] . "\n" . "Максимальный репост, пожалуйста! \n" . 
                    $inf_str . PHP_EOL . $photo_url . "\n\nvk.com/wall" . $owner_id . "_" . $post_id ;
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
                            'message' => $post_strings[0] . PHP_EOL . $post_strings[1] . PHP_EOL . 
                            "Максимальный репост, пожалуйста!" . PHP_EOL . $photo_url . 
                            "\n\n vk.com/wall" . $owner_id . "_" . $post_id,
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
                            'message' => "Пост без инфорга не занесен в программу LA_DOG" . PHP_EOL . 
                            $text . PHP_EOL . 'vk.com/wall' . $owner_id . '_' . $post_id,
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

    function is_in_la_dog($text)
    {
        $la_dog_inforgs = json_decode(sortJSONArrayByName(file_get_contents("la_dog_inforgs.json")), true);
        foreach($la_dog_inforgs as $inforg)
        {
            if(mb_strtolower($inforg["phone"]) == mb_strtolower($text))
            {
                    return true;
            }
        }
        return false;
    }		
    
    function readDutyTable(int $month)
    {    
        $loader = new DriveAPILoader('1rrouNM3lH4n4nG7tJi8Lr0IBahrp4R0Bv26JgL2c-FI');    
        $duty_sheet = $loader->loadTable('График');    
        $year = date('Y', time());    
        $row = (($year - 2020) * 24) + $month*2; #table begin from 2020 & 2 strings per month   
        if($duty_sheet !== null)
        {
            return  $duty_sheet[$row];    
        } else 
        {
            $params = array
            (
                'format' =>'csv',
                'gid' =>'0'
            );
            $file_id = '1rrouNM3lH4n4nG7tJi8Lr0IBahrp4R0Bv26JgL2c-FI';
            $query = http_build_query($params);
            $serv ='https://docs.google.com/spreadsheets/d/'.$file_id.'/export?' ;
            $csv = file_get_contents($serv.$query);
            $csv = explode("\r\n", $csv);
            $table = array_map('str_getcsv', $csv);
            $str_month = $table[$row];
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
    
    function getUserId($name)
    {
        global $vkrequest;
        //$name = mb_strtolower($name);
        $json_names = json_decode(file_get_contents('vkmethods/user_names.json'), true);
        $id = $json_names[$name];
        
        if(ctype_digit($id))
        {
            return "id".$id;
        } else {
           $utils = new VK\Actions\Utils($vkrequest);
           $params = array
           (
               'screen_name'=> $id
           );
            $response = $utils->resolveScreenName(VK_API_ACCESS_TOKEN, $params);
            return "id".$response["object_id"];
        }
    }

  /**
   * read Google Table for value
   **/
  function readTable()
  {
    global $vkrequest;
    $users = new VK\Actions\Users($vkrequest);
    $month = (int)date('m', time());
    $day_num = (int)date('d', time());
    $duty_month = readDutyTable($month);		
    #$whites = check_whitespaces($duty_month);	
    #$whites_next_month = check_nextMonth($month);
    $text2 = "";
    $user_id=null;
    $dezh = trim($duty_month[$day_num + 1]);
    if($dezh)
    {
        $user_id = trim(getUserId($dezh));
    } else 
    {
        $text2 = "дежурить желающих не нашлось... &#128533;";
    }
    if($user_id)
    {
        $user_params = array
        (
            'user_ids' => $user_id
        );
        $response = $users -> get(VK_API_SERVICE_TOKEN, $user_params);
        $user = $response[0];
        send_accept_decline_buttons($user);
        $text2 = "дежурит &#129418; @{$user_id}({$dezh})";
    } 

    $thisMonth = mktime(0,0,0,(int)date('m'));  #взяли номер месяца
     #и переделали в название на русском в род. падеже
    $renamedMonth = mb_strtolower(monthRename(strftime('%B', $thisMonth)));
    $today = date('j') . " " . $renamedMonth;
    if($day_num == 20 && $whites_next_month > 0)
    {		
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
            'label' => 'Да, дежурю)'
        ),
        '1' => array(
            'type' => 'callback',
            'payload' => array(
                'command' => 'duty_declined'
            ),
            'label' => 'Нет, не я'
        )
    );

    $colors = array(
        '0' => "positive",
        '1' => "negative"
    );

    $js_key = createKeyboard($actions, $colors, true);
    $user_ids = ['0' => $user_id];

    safe_send_message($user_ids, "Привет, {$username}, ты дежуришь?", $js_key );
  }


  /**
  * create message to return in chat or null
  * @param array set_of text
  * @param integer chat_id
  * @param UnixDate date
  * @param String user_name
  * @return String message
  **/

  function getMessage($text,$chat_id,$date,$user, $messageId=0){
    include 'districts.php';
    global $vkrequest;
    global $messages;
    $board = new VK\Actions\Board($vkrequest);
    $wall = new VK\Actions\Wall($vkrequest);
    global $districts;
    global $dezhur_tab;
    $set_of_text = explode(" ", $text);
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
            foreach($list as $name=>$id)
            {
                $result .= $name.": @".$id."\n";
            }
            $params = array(
                'message'=> $result,
                'random_id'=>'0',
                'peer_id'=>$chat_id
            );
            $messages->send(VK_API_ACCESS_TOKEN, $params);
            break;
        case 'начать':   
            $button_names = ["Инфорги","Дежурство","Ещё"];

           $template = createCarousel("Вот что я умею","Основные команды для работы", $button_names);

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
            $button_names = ["Районы","Долги","Дальше"];

            $template = createCarousel("Вот что я умею","Продолжение",$button_names);
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
        
        case 'дальше':
            $button_names = ["Вводная", "Команды"];
            $template = createCarousel("Вот что я умею", "Продолжение", $button_names);
            $message = "вот еще немного";
            $params = array
            (
                'message' => $message,
                'template' => $template,
                'random_id' => $date,
                'peer_id' => $chat_id
            );
            $messages -> send(VK_API_ACCESS_TOKEN, $params);
            unset($message);
            break;

        case 'duty_accepted':
            $user = getUserInfo($chat_id, "ins");
            $user_name = $user['first_name'] . " " . $user['last_name'];
            $params = array(
                'message' => "Дежурство подтверждено @id{$chat_id}({$user_name}), в " . date("H:i",time()),
                'random_id' => $date,
                'peer_id' => CHAT_TEST
            );
            $messages -> send(VK_API_ACCESS_TOKEN, $params);
            break;

        case 'duty_declined':
        $user = getUserInfo($chat_id, "ins");
        $user_name = $user['first_name'] . " " . $user['last_name'];
            $params = array(
                'message' => "Дежурство отклонено @id{$chat_id}({$user_name}) в " . date("H:i", time()),
                'random_id' => $date,
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
                if($inforg["vk_id"]  !== 100){
                    $message .= "@id" . $inforg["vk_id"]  . "(" . $inforg["name"] .") - " . $stringPhone. PHP_EOL;
                }  else {
                    $message .= $inforg["name"] . " - " . $stringPhone  . PHP_EOL;
                }
            }
            
            break;

        case 'районы':
            $message = "ссылка на файл с районами ГОО";
            $actions = array(
                '0' => array(
                    'type'=>'open_link',
                    'label'=>'Таблица районов',
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
            
        case 'whois':
            $user = getUserInfo($set_of_text[1]);
            $message = "@id" . $user["id"] . "(" .$user["first_name"] . " " . $user["last_name"] . ")";
            break;

        case 'команды':
            $message = '"инфорги" - выдам список инфоргов;
            "районы" - ссылка на таблицу с районами;
            "дежурство" - ссылка на таблицу дежурных;
            "#фамилия_бвп" - готовый текст поста с оркой;
            "название_района" - список прилегающих к указанному;
            "@название_района" - ссылка на список групп в сообществе Бота;
            "долги" - список невыполненных задач.(работает только в личке у Бота).
            "вводная" - текст со стены ЛАП про вводную лекцию';
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
           $message = readArrears($chat_id, $user_id);
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
            
        case "!срочная":
        case "!":
            $loader = new DriveAPILoader('1QVSnLSkGlgmBVwvF-atPFDL5wNUsK_R6hxUmnopRkac');
            $table  = $loader -> loadTable('Срочная');
            #достаемпервое  слово  после !
            $pos  = strpos($text, "!")+1;
            $text = substr($text, $pos+1);
            $another_set  = explode(", ", $text);
            $districtsIsNotInList = [];
            $districtsInList = [];
            if($table !== null)
            {
                $message = "Приоритетные районы:\n";
                foreach($another_set  as $district)
                {
                    if($district == "срочная")
                    {
                        continue;
                    }
                    if(DistrictNames::isDistrict($district))
                    {
                        array_push($districtsInList, $district);
                    }
                    else 
                    {
                        array_push($districtsIsNotInList, $district);
                    }
                }
                foreach($table as $row)
                {
                   foreach($districtsInList as $district)
                    {
                        if(mb_strtolower($row[0]) == mb_strtolower($district))
                        {
                            $message .= "\n" . $row[1] . " " . $row[4] . "(" . $row[3] . "), тел: "  .$row[6];
                        } 
                    }
                }
                
                if(count($districtsIsNotInList) > 0 && count($districtsInList) == 0)
                {
                    $message = "\n\nРайоны, которых не нашел в списке: \n" . implode(", ",$districtsIsNotInList);
                } else if(count($districtsIsNotInList) > 0)
                {
                    $message .= "\n\nРайоны, которых не нашел в списке: \n" . implode(", ",$districtsIsNotInList);
                }
            } else
            {
                $message = "Table wasn't loaded";
            }

            break;
            
        case "тг":
            $familyName  = $set_of_text[1];
            $message = getTelegramPost($familyName,  $chat_id);
            break;
        
        case "updateinforgwithid":
            $operator = new DatabaseInforgOperator();
            $result = $operator->updateInforgById($set_of_text[1],$set_of_text[2],$set_of_text[3]);
            switch($result){
                case null:
                    $message = "неудачная попытка подключения к БД";
                    break;
                case false:
                    $message = "неправильный синтакс команды обновления, зови Антона)";
                    break;
                case true:
                    $message = "Обновление прошло успешно";
                break;
            }
            break;
        case "addinforg":
            $operator = new DatabaseInforgOperator();
            $user = getUserInfo($set_of_text[3],"nom","photo_200"); #screen_name
            $inforg = array
            (
                "name" => $set_of_text[1],
                "phone" => $set_of_text[2],
                "vk_id" => $user["id"],
                "photo_200" => $user["photo_200"],
                "screen_name" => $set_of_text[3]
            );
            $result = $operator->insertInforg($inforg);
            switch($result)
            {
                case null:
                    $message = "Can't connect to DB";
                    break;
                case false:
                    $message = "Wrong statement, see code";
                    break;
                case true:
                    $message = "Инфорг успешно добавлен";
                    break;
            }
            break;
         
        case "deleteinforg" :
            $operator = new DatabaseInforgOperator();
            $result = $operator -> deleteInforg($set_of_text[1]);
            switch($result)
            {
                case null:
                    $message = "Can't connect to DB";
                    break;
                case false:
                    $message = "Wrong statement, see code";
                    break;
                case true:
                    $message = "Инфорг успешно удален";
                    break;
            }
            break;
            
        case "инфорг":
            $operator = new DatabaseInforgOperator();
            $result = $operator->getInforgByName($set_of_text[1]);
            $message = "";
            if($result == null){
                $message = "такого инфорга нет в списке";
            }
            foreach ($result as $inforg){
               ($inforg['id']==100|| $inforg['id']==null) ? $message .=$inforg['name']." - ".$inforg['phone']."\n" : $message .= "@id".$inforg['id']."(".$inforg['name'].") - ".$inforg['phone']."\n";
            }
            break;
        case "updateinforg":
            $operator = new DatabaseInforgOperator();
            if($result=$operator->updateInforgByPhone($set_of_text[1], $set_of_text[2],$set_of_text[3])){
                $message = "обновлено успешно";
            }
            break;
        default:
            $message="";
    }
      return $message;
  }

    function getJSONUsers()
    {
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

  function addUserToJson($name, $id)
  {
    $file_name = "vkmethods/user_names.json";
    $users_json = json_decode(file_get_contents($file_name),true);
    $users_json[$name] = $id;
    if(file_put_contents($file_name, json_encode($users_json)) !== FALSE)
    {
        return "Имя {$name} добавлено в список дежурящих";
    }
    return "Не удалось добавить {$name} в лист(";
  }

  function deleteUserFromJSON($name)
  {
    $file_name = "vkmethods/user_names.json";
    $users_json = json_decode(file_get_contents($file_name),true);
    unset($users_json[$name]);
    if(file_put_contents($file_name, json_encode($users_json)) !== FALSE)
    {
        return "Юзер $name успешно удален из списка";
    }
    return "Не удалось удалить юзера из списка";
  }

/**
 * обработка сообщения с "@"
 * @param Object $messageObject: объект сообщения ВК
 */

  function handleMessageWithDog($messageObject)
  {
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
    $url_response = $vkrequest -> post('asr.getUploadUrl', VK_API_SERVICE_TOKEN);
    $uploadUrl = $url_response['upload_url'];
    $uploaded = uploadFile($uploadUrl, $path);
    if($uploaded !== false)
    {
        $answer = json_decode($uploaded, true);
        if($answer['error_code'])
        {
            $params = array
            (
                'peer_id' => $chat_id,
                'random_id' => time()
            );
            sendMessage($answer['error_msg'],$params);
        } else 
        {
            $request_params = array
            (
                'audio' => $uploaded,
                'model' => 'neutral'
            );
            $response = $vkrequest -> post('asr.process',VK_API_SERVICE_TOKEN, $request_params);
            $task_id = $response['task_id'];
            $process_request = array
            (
                'task_id' => $task_id
            );
            $proc_not_finished = true;

            while($proc_not_finished)
            {
                $response = $vkrequest -> post('asr.checkStatus', VK_API_SERVICE_TOKEN, $process_request);
                switch ($response['status'])
                {
                    case 'processing':
                        time_sleep_until(time()+3);
                        continue 2;
                        break;
                    case 'internal_error':
                    case 'transcoding_error':
                    case 'recognition_error':                       
                        $messages_param = array
                        (
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
                        if($message != "")
                        {
                            $activity_params = array
                            (
                                'peer_id' => $chat_id,
                                'group_id' => VK_BOT_GROUP_ID,
                                'type' => 'typing'
                            );
                            $messages -> setActivity(VK_API_ACCESS_TOKEN, $activity_params);
                            time_sleep_until(time() + 2);
                            $message_params = array
                            (
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

function uploadFile($url, $filename)
{

  $file = curl_file_create($filename,'audio/mp3',$filename);
  $ch = curl_init($url);
  $meta = array
  (
    'file' => $file
  );
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER,array
    (
      'User-Agent: Opera/9.80 (Windows NT 6.2; Win64; x64) Presto/2.12.388 Version/12.15',
      'Referer: http://shirik78.hostingem.ru','Content-Type: multipart/form-data'
    ));
  curl_setopt($ch, CURLOPT_POSTFIELDS, $meta);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

   $result = curl_exec ($ch);
  if (curl_errno($ch)) 
  {
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
function safe_send_message(array $user_ids, $message, $keyboard = null, $attachment = null)
{
    global $messages;
    global $object;
    $allowed_users = [];
    $disallowed_users = [];
    foreach($user_ids as $user_id)
    {
        $params_for_check = array
        (
            'user_id' => $user_id,
            'group_id' => VK_BOT_GROUP_ID
        );
        $response = $messages -> isMessagesFromGroupAllowed(VK_API_ACCESS_TOKEN, $params_for_check);
      
        if($response['is_allowed'] == 1)
        {
            array_push($allowed_users, $user_id);
        } else 
        {
            array_push($disallowed_users, "@id" . $user_id);
        }
        time_sleep_until(time() + 2);
    }
  
    if(count($allowed_users) > 0)
    {
        $request_params = array
        (
            'peer_ids' => implode(",", $allowed_users),
            'keyboard' => $keyboard,
            'random_id' => $object['date'],
            'message' => $message,
            'attachment' => $attachment
        );
        $messages -> send(VK_API_ACCESS_TOKEN, $request_params);
    }
    if(count($disallowed_users) > 0)
    {
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

function handleWallReply(array $text, $attachment, $peer_id)
{
    global $messages;
    $mess_is_sended = false;
    $name = array_shift($text);
    $inforgs = getInforgs();
    foreach($inforgs as $inforg)
    {
        $i_name = explode(" ", $inforg['name']);
        if($name == mb_strtolower($i_name[0]) || $name == mb_strtolower($i_name[1]))
        {
            if($inforg['vk_id'] != 100)
            {
                $params = array(
                    'random_id'=>'0',
                    'peer_id'=>$inforg['vk_id'],
                    'message'=>implode(" ", $text),
                    'attachment'=>$attachment
                );  
            } 
            else
            {
                $params = array
                (
                    'random_id'=>'0',
                    'peer_id'=>'181655184',
                    'message'=>implode(" ", $text),
                    'attachment'=>$attachment
                );
            }
            if($result = $messages->send(VK_API_ACCESS_TOKEN, $params))
            {
                $mess_is_sended = true;
                break 1;
            }
            
        } 
        else 
        {
            continue;
        }
    }

    if($mess_is_sended)
    {
        $message = "переслано удачно";
    } else 
    {
        $message = "Инфорг с таким именем не найден";
    }
    $parameters = array
    (
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

function getInforgs()
{
    $inforgs = json_decode(sortJSONArrayByName(file_get_contents("api/inforgs.json")),true);
    for ($i=0; $i<count($inforgs); $i++)
    {
        $inforg = $inforgs[$i];
        if(str_contains($inforg['name'], "ё"))
        {
            $new_name = str_replace("ё", "е", $inforg['name']);
            $new_phones = $inforg['phones'];
            $new_id  = $inforg['vk_id'];
            $new_inforg = array
            (
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
    if(count($user_ids)>0)
    {
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

function searchDistrict($district, $text)
{
    global $object;
    if(str_contains($text, $district))
    {
        sendToAuthor("Ваш район '{$district}' есть в долгах!");
    } else 
    {
        sendToAuthor("Вашего района '{$district}' в долгах нет");
    }
}

/**
 * отправляет сообщение мне в ВК
 * @param $text string
 */

function sendToAuthor($text)
{
    global $messages;
    $params = array
    (
        'peer_id'=>BOT_AUTHOR,
        'random_id'=>0,
        'message'=> $text
    );
    $messages -> send(VK_API_ACCESS_TOKEN, $params);
}

/**
 * возвращает название месяца в родительном падеже
 * @param $month string - название месяца в именительном падеже
 */
function monthRename($month)
{
    $letters = ["ь","й"];
    $lastChar = mb_substr($month, -1);
    if($lastChar == "ь" || $lastChar == "й")
    {
        return str_replace($letters,"я", $month);
    }
    return $month . 'а';
}

/**
 * void function
 * edit VK message with
 * @param $chatId int - chat id,
 * @param  $messageId int - message id,
 * @param $message string - text
 */
function editMyMessage($chatId, $messageId, $message, $attachment)
{
    global $messages;
    $params = array
    (
        'peer_id'=>$chatId,
        'message'=>$message,
        'attachment'=> $attachment,
        'message_id'=>$messageId,
        'dont_parse_links'=>0,
        'keep_snippets'=>1
    );
    $response = $messages->edit(VK_API_ACCESS_TOKEN, $params);
}

function getTelegramPost($familyName, $chatId)
{
    global $messages;
    $headers = ['Content-Type: application/json'];
    $url = 'https://europe-west3-lizaalert-bot-01.cloudfunctions.net/api_get_active_searches';
    $headers = ['Content-Type: application/json']; 
    $data = array
    (
        'app_id' => 'Antonio_Krot',
        'depth_days' => 120,
        'forum_folder_id_list' => [120],
    );
    $data_json = json_encode($data); 
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_VERBOSE, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    $result = curl_exec($curl);
    curl_close($curl);
    $enc_result = json_decode($result, true);
    $zagotovka = "Если вы располагаете какой-либо информацией, просьба сообщить по телефонам ПСО \"ЛизаАлерт\":";
    $hotLineNumber  = "Горячая линия: 88007005452";
    $hashTag  = "#пропалчеловек";
    $foundResults =  0;
    foreach ($enc_result["searches"] as $search) 
    {
        if($search["family_name"]  == mb_ucfirst($familyName))
        {
            $foundResults++;
            if($search["search_status"] != "Ищем")
            {
                return "Статус поиска ".$search['family_name'].": ".$search['search_status'];
            }
            $strings = explode("<br>", $search["content"]);
            $imageUrl  = getForumImageUrl($search["search_id"]);
            #send imageUrl before  return text message
            $params = array
            (
                'peer_id'=>$chatId,
                'random_id'=>time(),
                'message'=>$imageUrl
            );
            $messages->send(VK_API_ACCESS_TOKEN, $params);
            
            $inforg  = array_pop($strings);
            $minusa  =  array_pop($strings);
            $text = implode("\n", [$hashTag, implode("\n",$strings), $zagotovka, $hotLineNumber, $inforg]);
            return  $text;
        }
            continue;
    }
    if($foundResults == 0)
    {
        return "Фамилии " . mb_ucfirst($familyName) . " в активных поисках не найдено";
    }
}

function getForumImageUrl($search_id)
{
    $url = "https://lizaalert.org/forum/viewtopic.php?f=120&t=";
    $pageUrl = $url . $search_id;
    $html = file_get_contents($pageUrl);
    $dom  = new DOMDocument();
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $postImage = $xpath->query('.//img[contains(@class, "postimage")]')->item(0);
    $attr = $postImage->attributes;
    $imageUrl = $attr[0]->value;
    return $imageUrl;
}

function readArrears($chat_id, $user_id){
    global $board;
    global $messages;
     $params = array(
        'group_id' => VK_BOT_GROUP_ID,
        'topic_id'=> DOLG_TOPIC_ID
    );

    $dolgResponse = $board -> getComments(VK_API_SERVICE_TOKEN, $params);

    $board -> getComments(VK_API_SERVICE_TOKEN, $params);
    $comments = $dolgResponse['items'];
    $first_comment = array_shift($comments);
    $message = "";
    if ($chat_id != CHAT_BOLTALKA && $chat_id != EDUCATION_CHAT && $chat_id != WORK_CHAT 
                                                                        && $chat_id != CHAT_TEST)
        {
            foreach($comments  as $comment)
            {
                $params =  array(
                    "random_id"=>0,
                    "peer_id"=>$chat_id,
                    "message"=> $comment['text']
                );
                $messages->send(VK_API_ACCESS_TOKEN, $params);
            }
        } else if ($chat_id == CHAT_BOLTALKA)
        {
            $message ="Долги смотрим в [club198797031|личке]";
        } else if ($chat_id == WORK_CHAT)
        {
            $params = array(
                'random_id'=>0,
                'peer_id'=>$user_id,
                'message'=>"Из рабочего чата удали команду, пожалуйста.\nИ напиши ее мне))"
                );
                $messages -> send(VK_API_ACCESS_TOKEN, $params);
        } else 
        {
            $message = "в чате обучения эта команда не работает &#128566;";
        }
    return $message;
}

function mb_ucfirst($string) {
    return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
}
