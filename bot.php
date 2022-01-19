<?php
require_once 'constants.php';
include 'vk_methods.php';
date_default_timezone_set("Europe/Moscow");

$data = json_decode(file_get_contents('php://input'));
$type = $data->type;
$event_id=$data->event_id;
if ($data->group_id == VK_BOT_GROUP_ID){
    switch ($type){
        case 'confirmation':
            echo CONFIRM_STRING;
            break;

        case 'message_new':
            $incomMessage = $data->object->message;
            $text = str_replace("/","",$incomMessage->text);
            $date = $incomMessage->date;
            $message_id = $incomMessage->id;
            $chat_id = $incomMessage->peer_id;
            $user_id = $data->object->message->from_id;
            $random_id = $data->object->message->id;
            $user_info = json_decode(getUserInfo($user_id));
            $user_name = '@id'.$user_id.'('.$user_info->response[0]->first_name.')';
            $message = '';
            $q = '';
            $action = $incomMessage->action;
            $ac_type = $action->type;
            $text=mb_strtolower($text,"UTF-8");
            $dog = "@";
            $hash = "#";
            $hasDog = strpos($text,$dog,0);
            $has_hash = strpos($text,$hash,0);
            $districts = "https://vk.cc/c1pC84";
            $dezhur_tab = "https://vk.cc/c1pCx2";
            $inforgs = json_decode(sortJSONArrayByName(file_get_contents('inforgs_spb.json'))); 
            $names = array();
            $phones = array();
            for ($j=0;$j<count($inforgs);$j++){
                $names[$j]=$inforgs[$j]->name;
                $phones[$j]= $inforgs[$j]->phone;
            }
            $instructors = [6070557,220750491,281086353,2967410,1293938,609471569,831877,1881105];
            $is_instructor = in_array($user_id, $instructors,true);
            include 'districts.php';

           if($hasDog === false){
               if($has_hash === false){
                    switch($text){
                        case 'команды':
                            
                            $commands = json_decode(file_get_contents('bot_commands.json'));
                            
                                if ($is_instructor){
                                    $message = $commands->origin->text;
                                } else{
                                    $message = $commands->users->text;
                                }
                            break;
                        case 'я':
                            $message = $user_name;
                            break;

                        case 'инфорги':
                            for($q=0;$q<count($names);$q++){
                                $message = $message.$names[$q]." - ".$phones[$q].PHP_EOL;
                            }
                            break;

                        case 'районы':
                            $message = "ссылка на файл с районами ГОО - ".$districts;
                            break;

                        case 'дежурство':
                            $message = "{$user_name}, держи ссылку на таблицу дежурств ".$dezhur_tab;
                            break;

                        case 'адмиралтейский':
                            $message = $admiral;
                            break;

                        case 'василеостровский':
                            $message = $vaska;
                            break;

                        case 'выборгский':
                            $message= $vyborgsk;
                            break;

                        case 'калининский':
                            $message = $kalin;
                            break;

                        case 'кировский':
                            $message = $kirovSpb;
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

                        case 'кировский ло':
                            $message= $kirovLo;
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

                        case 'выборгский ло':
                            $message= $vyborgLO;
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
                            $dolg = read_file('dolg.txt');
								if ($chat_id != CHAT_BOLTALKA && $chat_id != EDUCATION_CHAT){
                            $message = $dolg;
								} else if ($chat_id == CHAT_BOLTALKA){
									$message ="Долги смотрим в [club198797031|личке]";
								} else {
                                    $message = "в чате обучения эта команда не работает &#128566;";
                                }
                            break;

                        case 'дежурный':
                                if ($is_instructor){
                                    $message = readTable();
                                } else {
                                    $message = 'Команда недоступна';
                                }
                            break;

                        default:
                            $message="";
                    }
                } else{
                   $obj =  json_decode(getPost($text));
                   $message = $obj->message;
                   $attach = $obj->attachment;
                }
            } else {
                $districts = json_decode(file_get_contents('district_urls.json'));
                $text = str_replace('@', "", $text);
                $text = str_replace("/","", $text);
                $message = $districts -> $text;            
            }   

            markAsRead($chat_id,$message_id);
            if($message!=""){
                setActivity($chat_id, $date);    
            }      
            sendMessage($message,$chat_id,$attach);
            markAsAnswered($chat_id);
            header("HTTP/1.1 200 OK");
            echo 'ok';
            break;

        case 'group_join':
            $user_id=$data->object->user_id;
            $fields = 'sex';
            $user_info = json_decode(getUserInfo($user_id,$fields));
            $user_name = $user_info->response[0]->first_name;
            $user_sex=$user_info->response[0]->sex;
            switch($user_sex){
                case '1':
                $join ='присоединилась';
                break;
                case '2':
                $join ='присоединился';
                break;
            }
            $message="@id{$user_id}({$user_name}) {$join} к нам!";
            sendMessage($message,'6070557');
            header("HTTP/1.1 200 OK");
            echo 'ok';
            break;
        
        case 'board_post_new':
            $topic = $data->object->topic_id;
            if ($topic == DOLG_TOPIC_ID){
                $text = $data->object->text;
                $dolg = writeFile('dolg.txt',$text);
                $answer = sendMessage($text, WORK_CHAT);
                sendMessage($answer, '6070557');
            }
        
            header("HTTP/1.1 200 OK");
            echo 'ok';
            break;

        case 'board_post_edit':
            $topic = $data->object->topic_id;
            if ($topic == DOLG_TOPIC_ID){
                $text = $data->object->text;
                $dolg = writeFile('dolg.txt',$text);
            }
            header("HTTP/1.1 200 OK");
            echo 'ok';
            break;
        
        case 'message_reply':
            header("HTTP/1.1 200 OK");
            echo 'ok';
            break;
        }
    }
   /*
    * @param text String 
    * search post with #text
    * return formatted post for GOO from VK LA_Piter group
    */ 
 function getPost($text)
    {
        $request = array
        (
            'domain'=>'lizaalert_piter',
            'query'=>substr($text,0),
            'count'=>'1',
            'v'=>VK_API_VERSION,
            'access_token'=>VK_API_SERVICE_TOKEN
        );           
        $params = http_build_query($request);
        $res = json_decode(file_get_contents('https://api.vk.com/method/wall.search?'.$params));
        $response=$res->response;
        $list=$response->items;
        if(!$list){
            $message = "Я такой фамилии не нашел &#128530;";
        
            $ar = array(
                'message'=>$message,
                'attachment'=>null
         );
            $result=json_encode($ar);
        } else{
            $inf="Инфорг";
            $infs = "Инфорги";
                $post_text = $list[0]->text;
                $owner_id = $list[0]->owner_id;
                $post_id = $list[0]->id;
                $photo = $list[0]->attachments[0]->photo;
                $id = $photo->id;
                $key = $photo->access_key; 
                $post_strings = explode("\n",$post_text);
                $first_string = $post_strings[0];
                $inforg = strpos($post_text,$inf,0);
                $inf_str = substr($post_text,$inforg);
                $inforges = strpos($post_text,$infs,0);
                $infs_str = substr($post_text,$inforges);

                $message="";
                if (strpos($post_text,"ВЫЕЗД",0)>0){
                    for ($i=0;$i<count($post_strings)-2;$i++){
                        if($i==1){
                        continue;
                    }
                        $message = $message.$post_strings[$i]."\n";
                    }
                        $message = $message."\n"."https://vk.com/photo".$owner_id."_".$id;
                } else {
                    $message = $post_strings[0]."\n"."Максимальный репост, пожалуйста! \n".$inf_str."\nhttps://vk.com/photo".$owner_id."_".$id;
                }
                $attach = "photo".$owner_id."_".$id."_".$key;
            

                $ar = array(
                    'message'=>$message,
                    'attachment'=>$attach
                );
                $result = json_encode($ar);
        }
        return $result;
    }
  /*
   * read Google Table for value
   */
  function readTable(){
    define("HOME","http://shirik78.hostingem.ru");
    $month = date('m', time());
    $day_num = date('d', time());
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
    $str_month = $table[$month+24];    //начало с 2020, поэтому +24
    $dezh = trim($str_month[$day_num+1]);
    
    $param = array(
        'name' =>$dezh
    );
    $query=http_build_query($param);
    $url = "/vkmethods/user_names.php?";
    $user_id = file_get_contents(HOME.$url.$query);
    $today = date('d.m.Y',time());
    $text = "Доброе утро, сегодня $today, дежурит &#129418; @{$user_id}({$dezh})";
    return $text;
  }

/*
 * @param jsonArray JSONArray
 * sort JSONArray with Inforgs alphabetical
 */
  function sortJSONArrayByName($jsonArray){
      $inforgs = json_decode($jsonArray,true)['inforgs'];
      usort($inforgs, function($a, $b){
          return $a['name'] <=> $b['name'];
      });
    return json_encode($inforgs);
  }





?>