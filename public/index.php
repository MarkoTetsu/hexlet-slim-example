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

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['name' => '', 'email' => '', 'id' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
})->setName('newUser');

$app->get('/users', function ($request, $response) {
    $users = readFromFile('users');
    $term = $request->getQueryParam('term');
    if ($term !== null) {
        $filteredUsers = array_filter($users, fn($user) => stripos($user['name'], $term) !== false);
    } else {
        $filteredUsers = $users;
    }
    $params = ['users' => $filteredUsers];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->post('/users', function ($request, $response) use ($router) {
    $user = $request->getParsedBodyParam('user');
    $errors = validate($user);
    $users = readFromFile('users');
    $userIDExists = in_array($user['id'], array_column($users, 'id'));
    if (count($errors) === 0 && !$userIDExists) {
        saveInFile($user);
        $url = $router->urlFor('users');
        return $response->withRedirect($url, 302);
    }
    if ($userIDExists) {
        $errors['idExists'] = 'User id already exists!';
    }
    $params = [
        'user' => $user,
        'errors' => $errors
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->get('/users/{id}', function ($request, $response, $args) {
    $id = htmlspecialchars($args['id']);
    $users = readFromFile('users');
    $filteredUser = array_values(array_filter($users, fn($user) => stripos($user['id'], $id) !== false));
    if (empty($filteredUser)) {
        return $response->write('Page not found')->withStatus(404);
    }
    [$user] = $filteredUser;
    $params = ['user' => $user];
    return $this->get('renderer')->render($response, 'users/user.phtml', $params);
});

$app->get('/', function ($request, $response) use ($router) {
    return $this->get('renderer')->render($response, 'index.phtml');
});

function validate($user)
{
    $errors = [];
    if (empty($user['name'])) {
        $errors['name'] = "Can't be blank";
    }
    if (empty($user['email'])) {
        $errors['email'] = "Can't be blank";
    }
    if (empty($user['id'])) {
        $errors['id'] = "Can't be blank";
    }
    return $errors;
}

function saveInFile($user): void
{
    file_put_contents('users', json_encode($user) . '/', FILE_APPEND);
}

function readFromFile($path)
{
    $users = [];
    $fileData = explode('/', file_get_contents($path));
    foreach ($fileData as $value) {
        if ($value !== '') {
            $users[] = json_decode($value, JSON_OBJECT_AS_ARRAY);
        }
    }
    uasort($users, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $users;
}

$app->run();
