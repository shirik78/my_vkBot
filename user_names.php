<?php
    $name = mb_strtolower(urldecode($_GET["name"]), 'UTF-8');
    switch ($name){
        case 'оля и.':
            $id = 'news4olga';
            break;
        case 'оля п.':
            $id =  'steel_io';
            break;
        case 'фокси':
            $id = 'id1018235';
            break;
        case 'янс':
            $id = 'natashkamiroo';
            break;
        case 'наташа':
            $id = 'natashkamiroo';
            break;
        case 'оля в.':
            $id = 'olgaveritas';
            break;
        case 'веритас':
            $id = 'olgaveritas';
            break;
        case 'юля н.':
            $id = 'arhiewik';
            break;
        case 'маша в.':
            $id = 'mariaverzun';
            break;
        case 'мария верзун':
            $id = 'mariaverzun';
            break;
        case 'маша верзун':
            $id = 'mariaverzun';
            break;
        case 'верба':
            $id = 'maria_natocheeva';
            break;
        case 'лариса':
            $id = 'id2386624';
            break;
        case 'дру':
            $id = 'lena_dru';
            break;
        case 'кабыздох':
            $id = 'id409301278';
            break;
        case 'михалыч':
            $id = 'mihalich05';
            break;
        case 'Анна Б.':
            $id = 'mihalich05';
            break;
        case 'крот':
            $id = 'shirik78';
                break;
        case 'невидимка':
            $id = 'azaryshka';
            break;
        case 'юльчи':
            $id = 'idiidiv188901';
            break;
        case 'илина':
            $id = 'ilinka89';
            break;
    }
        $vk_id = getId($id);
        echo '@id'.$vk_id;

    function getId($uid){

        $response = json_decode(file_get_contents('http://shirik78.hostingem.ru/vkmethods/resolveScreenName.php?screen_name='.$uid));
        $id = $response->response->object_id;
        return $id;
    }


?>
