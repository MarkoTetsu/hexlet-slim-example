<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    headers($request);
    return $response;
    // Благодаря пакету slim/http этот же код можно записать короче
    // return $response->write('Welcome to Slim!');
});

$app->get('/users/{id}', function ($request, $response, $args) {
    headers($request);
    $params = [
        'id' => htmlspecialchars($args['id']),
        'nickname' => htmlspecialchars('user-' . $args['id'])
    ];
    // Указанный путь считается относительно базовой директории для шаблонов, заданной на этапе конфигурации
    // $this доступен внутри анонимной функции благодаря https://php.net/manual/ru/closure.bindto.php
    // $this в Slim это контейнер зависимостей
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});

$app->get('/users', function ($request, $response) use ($users) {
    $term = $request->getQueryParam('term');
    if ($term !== null) {
        $filteredUsers = array_filter($users, fn($user) => strpos($user, $term) !== false);
    } else {
        $filteredUsers = $users;
    }
    $params = ['users' => $filteredUsers];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
});

$app->post('/users', function ($request, $response) {
    headers($request);
    return $response->withStatus(302);
});

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = htmlspecialchars($args['id']);
    headers($request);
    return $response->write("Course id: {$id}");
});

function headers($request) {
    $headers = $request->getHeaders();
    $uri = $request->getUri();
    $method = $request->getMethod();
    echo "<p>{$method} {$uri}</p>"; 
    foreach ($headers as $name => $values) {
        echo "<p>" . $name . ": " . implode(", ", $values) . "</p>";
    }
}

$app->run();
