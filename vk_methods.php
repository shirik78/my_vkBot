<?php
    require_once 'constants.php';

    /**
    * @param user_id Integer user id
    * @param fields String, delimiter ","
    * return JSONObject
    **/
    function getUserInfo($user_id,$fields=null){  
        $parames = array(
            'user_ids'     => $user_id,
            'fields'       => $fields,
            'lang'         => VK_API_LANGUAGE,
            'access_token' => VK_API_ACCESS_TOKEN,
            'v'            => VK_API_VERSION
        );
        $req_url = http_build_query($parames);
        $user_info = file_get_contents('https://api.vk.com/method/users.get?'.$req_url);
        return $user_info;
    }

    /**
    * void func
    * @param message String text
    * @param peer_id Integer VK id
    * @param attachment array or String (like 'photo<photo_owner_id>_<photo_id>') default - null
    * Send message to vk user from bot
    **/
    function sendMessage($message,$peer_id,$attachment=null){
        $rid = strtotime('now');
        $request = array(
            'message'      => $message,
            'peer_id'      => $peer_id,
            'attachment'   => $attachment,
            'random_id'    => $rid,
            'access_token' => VK_API_ACCESS_TOKEN,
            'v'            => VK_API_VERSION
        );
        $params = http_build_query($request);
        file_get_contents('https://api.vk.com/method/messages.send?'. $params);
    }

    /**
    * void func
    * @param peer_id Integer user id or chat id
    * @param date Unix Timestamp time of requested message
    * @param type String default = 'typing', can be 'audiomessage'
    * set typing status in chat
    **/

    function setActivity($peer_id,$date, $type = 'typing'){
        $req = array(
            'type'         => $type,
            'peer_id'      => $peer_id,
            'group_id'     => $group_id,
            'access_token' => VK_API_ACCESS_TOKEN,
            'v'            => VK_API_VERSION
        );
        $params = http_build_query($req);
        file_get_contents('https://api.vk.com/method/messages.setActivity?'.$params);
        time_sleep_until(time()+3);
    }

    /**
    * void func
    * @param $peer_id Integer user id or chat id
    * @param message_id Integer id of message to set as readed
    **/
    function markAsRead($peer_id,$message_id){
        $param = array(
            'peer_id'                   => $peer_id,
            'start_message_id'          => $message_id,
            'group_id'                  => $group_id,
            'mark_conversation_as_read' => '1',
            'access_token'              => VK_API_ACCESS_TOKEN,
            'v'                         => VK_API_VERSION
        );
        $request = http_build_query($param);
        file_get_contents('https://api.vk.com/method/messages.markAsRead?'.$request);
    }

    /**
    * void func
    * @param peer_id Integer user id or chat id
    * set message as answered
    **/

    function markAsAnswered($peer_id){
        $request = array(
            'peer_id'      => $peer_id,
            'answered'     => '1',
            'group_id'     => $group_id,
            'access_token' => VK_API_ACCESS_TOKEN,
            'v'            => VK_API_VERSION
        );
        $m_param = http_build_query($request);
        file_get_contents('https://api.vk.com/method/messages.markAsAnsweredConversation?'.$m_param);
    }

    /**
    * writing 
    * @param data String
    * to 
    * @param filename String
    **/

    function writeFile($filename,$data){
        $result = file_put_contents($filename,$data);
        return $result;
    }

    /**
    * reading from 
    * @param filename String
    **/
    function read_file($filename){
        $result = file_get_contents($filename);
        return $result;
    }

    /**
    * @param peer_id Integer user id or chat id
    * @param type String can be 'doc' or 'audio_message'
    * return String url
    **/

    function getMessagesUploadServer($peer_id, $type) {
        $params =  array(
            'peer_id'       => $peer_id,
            'type'          => $type,
            'access_token'  => VK_API_ACCESS_TOKEN,
            'v'             => VK_API_VERSION
        );
        $request = http_build_query($params);
        $url = 'https://api.vk.com/method/docs.getMessagesUploadServer?';
        $curl = createCurlRequest($url, $params);
        $result = curl_exec($curl);
        return $result -> upload_url;
    } 

    /**
    * init loading
    **/

    function createCurlRequest($url, $query) {
        $curl = curl_init();
        if (!$curl) {
            return false;
        }
        $configured = curl_setopt_array($curl, [
            CURLOPT_URL => $url . $query,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true
        ]);
        if (!$configured) {
            return false;
        }
        return $curl;
    }

?>