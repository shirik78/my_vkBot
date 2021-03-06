<?php
require_once 'constants.php';
require_once 'oFile.php';
include 'vk_methods.php';
require_once 'districts.php';
require_once 'carousel.php';
date_default_timezone_set("Europe/Moscow");

$districts = "https://vk.cc/cbklQu";
$dezhur_tab = "https://vk.cc/c1pCx2";

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
            $set_of_text = explode(" ", mb_strtolower($text,"utf-8"));
            $date = $incomMessage->date;
            $message_id = $incomMessage->id;
            $chat_id = $incomMessage->peer_id;
            $user_id = $data->object->message->from_id;
            $random_id = $data->object->message->id;
            
            $user_info = getUserInfo($user_id);
            $user_name = '@id'.$user_id.'('.$user_info["first_name"].')';
            $message = '';
            $q = '';
            $action = $incomMessage->action;
            $ac_type = $action->type;
            $text=mb_strtolower($text,"UTF-8");
            $dog = "@";
            $hash = "#";
            $hasDog = strpos($text,$dog,0);
            $has_hash = strpos($text,$hash,0);
            if($incomMessage->payload){
                $payload = json_decode($incomMessage->payload);
                $command = $payload->command;
                if(is_array($command = explode(" ",$command))){
                    $command = mb_strtolower($command[0]);
                } else{
                    $command = mb_strtolower($command);
                }
                
                $message = getMessage($command,$chat_id,$date,$user_name);
            } else{
                if($hasDog === false){
                    if($has_hash === false){
                        $message = getMessage($set_of_text,$chat_id,$date,$user_name);
                    } else{
                    $obj =  json_decode(getPost(implode(" ",$set_of_text)));
                    $message = $obj->message;
                    $attach = $obj->attachment;
                    }
                } else {    
                    if(strpos($set_of_text[0],'club198797031',0)>-1){
                        $message = getMessage($set_of_text[1],$chat_id,$date,$user_name);
                    } else{
                        $districtes = json_decode(file_get_contents('district_urls.json'));
                        $text = str_replace('@', "", $text);
                        $text = str_replace("/","", $text);
                        $message = $districtes -> $text;  
                    }       
                }   
            }

            markAsRead($chat_id,$message_id);
            if($message!=""){
                setActivity($chat_id);    
            }      
            sendMessage($message,$chat_id,$attach);
            markAsAnswered($chat_id);
            header("HTTP/1.1 200 OK");
            echo 'ok';
            break;

        case 'group_join':
            $user_id=$data->object->user_id;
            $fields = 'sex';
            $user_info = getUserInfo($user_id,$fields);
            $user_name = $user_info["first_name"];
            $user_sex=$user_info["sex"] ? $user_info["sex"] : "0";
            switch($user_sex){
                case '1':
                $join ='???????????????????????????? ?? ??????!';
                break;
                case '2':
                $join ='?????????????????????????? ?? ??????!';
                break;
                case "0":
                $join = "???????????? ?? ????????????!";
                break;
            }
            $message="@id{$user_id}({$user_name}) {$join}";
            sendMessage($message,'6070557');
            header("HTTP/1.1 200 OK");
            echo 'ok';
            break;
        
        case 'board_post_new':
            $topic = $data->object->topic_id;
            if ($topic == DOLG_TOPIC_ID){
                $dezh = readTable();
                $text = $data->object->text;
                $dolg = writeFile('dolg.txt',$text);
                $answer = sendMessage($dezh."\n\n".$text, WORK_CHAT);
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
    } else {
        $gr = $data->group_id;
        sendMessage("message from $gr see Callback API in group",BOT_AUTHOR);
    }
   /**
    * @param string text 
    * search post with #text
    * return formatted post for GOO from VK LA_Piter group
    **/ 
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
            $message = "?? ?????????? ?????????????? ???? ?????????? &#128530;";
        
            $ar = array(
                'message'=>$message,
                'attachment'=>null
         );
            $result=json_encode($ar);
        } else{
            $inf="????????????";
            $infs = "??????????????";
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
                if (strpos($post_text,"??????????",0)>0){
                    for ($i=0;$i<count($post_strings)-2;$i++){
                        if($i==1){
                        continue;
                    }
                        $message = $message.$post_strings[$i]."\n";
                    }
                        $message = $message."\n"."https://vk.com/photo".$owner_id."_".$id;
                } else {
                    $message = $post_strings[0]."\n"."???????????????????????? ????????????, ????????????????????! \n".$inf_str."\nhttps://vk.com/photo".$owner_id."_".$id.PHP_EOL."???????????? ???? ????????:".PHP_EOL."https://vk.com/wall".$owner_id."_".$post_id;
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
  /**
   * read Google Table for value
   **/
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
    $str_month = $table[$month+24];    //???????????? ?? 2020, ?????????????? +24
    $dezh = trim($str_month[$day_num+1]);
    
    $param = array(
        'name' =>$dezh
    );
    $query=http_build_query($param);
    $url = "/vkmethods/user_names.php?";
    $user_id = file_get_contents(HOME.$url.$query);
    $today = date('d.m.Y',time());
    $text = "????????????, ?????????????? $today, ?????????????? &#129418; @{$user_id}({$dezh})";
    return $text;
  }

/**
 * @param jsonArray JSONArray
 * sort JSONArray with Inforgs alphabetical
 **/
  function sortJSONArrayByName($jsonArray){
      $inforgs = json_decode($jsonArray,true)['inforgs'];
      usort($inforgs, function($a, $b){
          return $a['name'] <=> $b['name'];
      });
    return json_encode($inforgs);
  }

  function isUser($user_id){
    $users = array();
    $params  = array(
        'group_id'     => VK_BOT_GROUP_ID,
        'access_token' => VK_API_ACCESS_TOKEN,
        'v'            => VK_API_VERSION
    );
    $query = http_build_query($params);
    $response = json_decode(file_get_contents(VK_API_URL.'groups.getMembers?'.$query));
    $user_ids = $response -> response -> items;
    return in_array($user_id,$user_ids,true);
  }

  /**
  * @param String name
  * @param String phone
  * @param int id
  * return String 'answer'
  **/

  function addInforg($name,$phone,$id){
      $file_name = 'inforgs_spb.json';
      $json = json_decode(file_get_contents($file_name),true);
      $inforgs= $json["inforgs"];
      $new_inforg = array(
          'name'  => $name,
          'phone' => $phone,
          'vk_id' => $id
      );
      if(!in_array($new_inforg,$inforgs,true)){
        array_push($inforgs,$new_inforg);
        $inforgs = array(
            'inforgs' => $inforgs
        );
        $json_inforgs = json_encode($inforgs);
        $rewrite = writeFile($file_name, $json_inforgs);
        return "???????????? ".$name."(".$phone.", ".$id.") ???????????????? ?? ????????????"; 
      } else {
          return "?????????? ???????????? ?????? ????????";
      }       
  }

 /**
 * @param String name
 * return String 'answer'
 **/

  function delInforg($name){
      $file_name = 'inforgs_spb.json';
      $json = json_decode(file_get_contents($file_name));
      $inforgs = $json->inforgs;
      $names = [];
      for ($u=0;$u<count($inforgs);$u++){
          $u_name = $inforgs[$u]->name;
          array_push($names,$u_name);
      }
      if(!in_array($name,$names,true)){
          return "???????????? ?????????????? ??????";
      } else {
        for($i=0;$i<count($inforgs);$i++){
            if($inforgs[$i]->name == $name){
                array_splice($inforgs,$i,1);
            }
        }
      
        $inforgs = array(
            'inforgs'=>$inforgs
        );
        $new_json = json_encode($inforgs);
        $rewrite = writeFile($file_name,$new_json);
        return "???????????? $name ?????????????? ????????????";
      }
  }

  /**
  * @param Array set_of text
  * @param int chat_id
  * @param UnixDate date
  * @param String user_name
  * return String message
  **/

  function getMessage($set_of_text,$chat_id,$date,$user_name){
    include 'districts.php';
    global $vkrequest;
    global $districts;
    global $dezhur_tab;
    if(!is_array($set_of_text)){
        $command = $set_of_text;
    } else {
        if(mb_strpos($set_of_text[0],'club198797031',0)>-1 || mb_strpos($set_of_text[0],'?????? ??????',0)>-1){
            $ar_command = explode("]",$set_of_text[0]);
            $command = trim($ar_command[1]);
        } else{
            $command = $set_of_text[0];
        }
    }
    switch($command){
        case '????????????':   
            $button_names = array(
                '0'=>'??????????????',
                '1'=>'??????????????????',
                '2'=>'??????'
            );
           $template = createCarousel("?????? ?????? ?? ????????","???????????????? ?????????????? ?????? ????????????",$button_names);

            $message = '????????????!';
            
            $params = array(
                'message' => $message,
                'template' => $template,
                'random_id' =>$date,
                'peer_id'=> $chat_id
            );
            $response = $vkrequest -> post("messages.send",VK_API_ACCESS_TOKEN,$params);
            unset($message);    //clear
            break;
        case '??????':
            $button_names = array(
                '0'=>'????????????',
                '1'=>'??????????',
                '2'=>'???????????? ????????????, ???? ??????)'
            );
            $template = createCarousel("?????? ?????? ?? ????????","??????????????????????",$button_names);
            $message = "????????????????????";
            $params = array(
                'message' => $message,
                'template' => $template,
                'random_id' =>$date,
                'peer_id'=> $chat_id
            );
            $response = $vkrequest -> post("messages.send",VK_API_ACCESS_TOKEN,$params);
            unset($message);
            break;
        case 'del_inforg':
            if(count($set_of_text)>1){
                if($set_of_text[1]){
                    if($set_of_text[2]){
                        $name = $set_of_text[1]." ".$set_of_text[2];
                        $message = delInforg($name);
                    } else {
                        $name = $set_of_text[1];
                        $message = delInforg($name);
                    }
                } else {
                    $message = "???? ???? ?????????? ??????";
                }
            } else {
                $message = "????????-???? ???? ??????????????)";
            }
            break;
        case 'add_inforg':
            if(count($set_of_text)>1){
                if($set_of_text[1]){
                    if($set_of_text[2]){
                        if($set_of_text[3]){
                            $message = addInforg($set_of_text[1],$set_of_text[2],$set_of_text[3]);
                        } else{
                            $message = "???? ?????????????? id";
                        }
                    } else {
                        $message = "???? ?????????????? ???????????????? ?? id";
                    }
                } else{
                    $message = "???? ???? ?????????? ????????????";
                }
            } else {
                $message = "???? ???? ?????????? ????????????";
            }
        break;

        case '??????????????':
            $inforgs = json_decode(sortJSONArrayByName(file_get_contents('inforgs_spb.json'))); 
            $names = array();
            $phones = array();
            for ($j=0;$j<count($inforgs);$j++){
                $names[$j]=$inforgs[$j]->name;
                $phones[$j]= $inforgs[$j]->phone;
            }
            for($q=0;$q<count($names);$q++){
                $message = $message.$names[$q]." - ".$phones[$q].PHP_EOL;
            }
            break;
        
        case '????????????':
            $message = "?? ???? ???????????? ???? ????????????????! &#128553;";
            break;

        case '????????????':
            $message = "???????????? ???? ???????? ?? ???????????????? ?????? - ".$districts;
            break;

        case '??????????????????':
            $message = "{$user_name}, ?????????? ???????????? ???? ?????????????? ???????????????? ".PHP_EOL.$dezhur_tab;
            break;

        case '????????????????????????????':
            $message = $admiral;
            break;

        case '????????????????????????????????':
            $message = $vaska;
            break;

        case '????????????????????':
            if($set_of_text[1]){
                $message = $vyborgLO;
            } else {
                $message= $vyborgsk;
            }
            break;

        case '??????????????????????':
            $message = $kalin;
            break;

        case '??????????????????':
            if($set_of_text[1]){
                $message = $kirovLo;
            } else {
                $message = $kirovSpb;
            }
            break;
        case '????????????????????':
            $message = $kolpin;
            break;

        case '??????????????????????????????????':
            $message = $krasnogv;
            break;

        case '????????????????????????????':
            $message = $krasnosel;
            break;

        case '??????????????????????????':
            $message = $kronstadt;
            break;

        case '??????????????????':
            $message=$kurort;
            break;

        case '????????????????????':
            $message = $moskov;
            break;

        case '??????????????':
            $message = $nevsk;
            break;

        case '??????????????????????????':
            $message = $petrogr;
            break;

        case '????????????????????????????':
            $message = $petrodvor;
            break;

        case '????????????????????':
            $message=$primor;
            break;

        case '????????????????????':
            $message = $pushkin;
            break;

        case '??????????????????????':
            $message=$frunze;
            break;

        case '??????????????????????':
            $message = $centr;
            break;

        case '????????????':
            $message = $murino;
            break;

        case '??????????????':
            $message = $kudrovo;
            break;

        case '????????????????????????????':
            $message = $sosnovobor;
            break;

        case '????????????????????':
            $message = $tosno;
            break;

        case '????????????????????':
            $message= $tichwin;
            break;

        case '??????????????????????':
            $message= $slanez;
            break;

        case '??????????????????????':
            $message= $priozer;
            break;

        case '????????????????????????':
            $message= $podporozh;
            break;

        case '??????????????':
            $message= $luga;
            break;

        case '??????????????????????????':
            $message= $lomonos;
            break;

        case '??????????????????????????????':
            $message= $lodeyka;
            break;

        case '??????????????????':
            $message= $kirishi;
            break;

        case '??????????????????????????':
            $message= $kingisepp;
            break;

        case '????????????????????':
            $message= $gatchina;
            break;

        case '????????????????????????':
            $message= $vsevolozh;
            break;

        case '????????????????????':
            $message= $volchv;
            break;

        case '????????????????????????????':
            $message= $boxit;
            break;

        case '??????????????????????':
            $message= $volosovo;
            break;
        
        case '??????????':
            $dolg = read_file('dolg.txt');
                if ($chat_id != CHAT_BOLTALKA && $chat_id != EDUCATION_CHAT){
            $message = $dolg;
                } else if ($chat_id == CHAT_BOLTALKA){
                    $message ="?????????? ?????????????? ?? [club198797031|??????????]";
                } else {
                    $message = "?? ???????? ???????????????? ?????? ?????????????? ???? ???????????????? &#128566;";
                }
            break;
        default:
            $message="";
    }
      return $message;
  }

?>
