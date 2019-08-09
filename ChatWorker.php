<?php

// Подключаем библиотеку Workerman
require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Lib\Timer;
use Workerman\Worker;

$connections = []; // сюда будем складывать все подключения

// Стартуем WebSocket-сервер на порту 27800
$worker = new Worker("websocket://0.0.0.0:27800");

$worker->onConnect = function($connection) use(&$connections)
{
    // Эта функция выполняется при подключении пользователя к WebSocket-серверу
    $connection->onWebSocketConnect = function($connection) use (&$connections)
    {
        // Достаём имя пользователя, если оно было указано
        if (isset($_GET['userName'])) {
            $originalUserName = preg_replace('/[^a-zA-Zа-яА-ЯёЁ0-9\-\_ ]/u', '', trim($_GET['userName']));
        }
        else {
            $originalUserName = 'Инкогнито';
        }
        
        // Половая принадлежность, если указана
        // 0 - Неизвестный пол
        // 1 - М
        // 2 - Ж
        if (isset($_GET['gender'])) {
            $gender = (int) $_GET['gender'];
        }
        else {
            $gender = 0;
        }
        
        if ($gender != 0 && $gender != 1 && $gender != 2) 
            $gender = 0;
        
        // Цвет пользователя
        if (isset($_GET['userColor'])) {
            $userColor = $_GET['userColor'];
        }
        else {
            $userColor = "#000000";
        }
                
        // Проверяем уникальность имени в чате
        $userName = $originalUserName;
        
        $num = 2;
        do {
            $duplicate = false;
            foreach ($connections as $c) {
                if ($c->userName == $userName) {
                    $userName = "$originalUserName ($num)";
                    $num++;
                    $duplicate = true;
                    break;
                }
            }
        } 
        while($duplicate);
        
        // Добавляем соединение в список
        $connection->userName = $userName;
        $connection->gender = $gender;
        $connection->userColor = $userColor;
        $connection->pingWithoutResponseCount = 0; // счетчик безответных пингов
        
        $connections[$connection->id] = $connection;
        
        // Собираем список всех пользователей
        $users = [];
        foreach ($connections as $c) {
            $users[] = [
                'userId' => $c->id,
                'userName' => $c->userName, 
                'gender' => $c->gender,
                'userColor' => $c->userColor
            ];
        }
        
        // Отправляем пользователю данные авторизации
        $messageData = [
            'action' => 'Authorized',
            'userId' => $connection->id,
            'userName' => $connection->userName,
            'gender' => $connection->gender,
            'userColor' => $connection->userColor,
            'users' => $users
        ];
        $connection->send(json_encode($messageData));
        
        // Оповещаем всех пользователей о новом участнике в чате
        $messageData = [
            'action' => 'Connected',
            'userId' => $connection->id,
            'userName' => $connection->userName,
            'gender' => $connection->gender,
            'userColor' => $connection->userColor
        ];
        $message = json_encode($messageData);
        
        foreach ($connections as $c) {
            $c->send($message);
        }
    };
};

Worker::runAll();