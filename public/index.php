<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);

$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

session_start();

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'index.phtml');
});

$app->get('/users', function ($request, $response) {
    $flash = $this->get('flash')->getMessages();
    $users = readFromFile('users');
    $term = $request->getQueryParam('term');
    if ($term !== null) {
        $filteredUsers = array_filter($users, fn($user) => stripos($user['name'], $term) !== false);
    } else {
        $filteredUsers = $users;
    }
    $params = [
        'users' => $filteredUsers,
        'flash' => $flash
    ];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['name' => '', 'email' => '', 'id' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
})->setName('newUser');

$app->post('/users', function ($request, $response) use ($router) {
    $user = $request->getParsedBodyParam('user');
    $errors = validate($user);
    $users = readFromFile('users');
    $userIDExists = in_array($user['id'], array_column($users, 'id'));
    if (count($errors) === 0 && !$userIDExists) {
        saveInFile($user);
        $this->get('flash')->addMessage('success', 'User profile has been created.');
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
    $flash = $this->get('flash')->getMessages();
    $id = htmlspecialchars($args['id']);
    $users = readFromFile('users');
    $filteredUser = array_values(array_filter($users, fn($user) => $user['id'] === $id));
    if (empty($filteredUser)) {
        return $response->write('Page not found')->withStatus(404);
    }
    [$user] = $filteredUser;
    $params = [
        'user' => $user,
        'flash' => $flash
    ];
    return $this->get('renderer')->render($response, 'users/user.phtml', $params);
})->setName('userProfile');

$app->get('/users/{id}/edit', function ($request, $response, $args) {
    $id = $args['id'];
    $users = readFromFile('users');
    $filteredUser = array_values(array_filter($users, fn($user) => $user['id'] === $id));
    if (empty($filteredUser)) {
        return $response->write('Page not found')->withStatus(404);
    }
    [$user] = $filteredUser;
    $params = [
        'user' => $user,
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('editUser');

$app->patch('/users/{id}', function ($request, $response, $args) use ($router) {
    $id = $args['id'];
    $users = readFromFile('users');
    $filteredUser = array_filter($users, fn($user) => $user['id'] === $id);
    $key = array_key_first($filteredUser);
    $updatedUser = $request->getParsedBodyParam('user');
    $errors = validate($updatedUser);

    if (count($errors) === 0) {
        $users[$key]['id'] = $updatedUser['id'];
        $users[$key]['name'] = $updatedUser['name'];
        $users[$key]['email'] = $updatedUser['email'];

        $this->get('flash')->addMessage('success', 'User profile has been updated');
        rewriteFile($users);
        
        $url = $router->urlFor('userProfile', ['id' => $users[$key]['id']]);
        return $response->withRedirect($url);
    }
    
    $params = [
        'user' => $filteredUser,
        'errors' => $errors
    ];
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->delete("/users/{id}", function ($request, $response, $args) use ($router) {
    $id = $args['id'];
    $users = readFromFile('users');
    $filteredUser = array_values(array_filter($users, fn($user) => $user['id'] === $id));
    $users = deleteUser($users, $filteredUser);
    rewriteFile($users);
    $this->get('flash')->addMessage('success', 'User profile has been deleted');
    $url = $router->urlFor('users');
    return $response->withRedirect($url);
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

function saveInFile(array $user): void
{
    file_put_contents('users', json_encode($user) . '/', FILE_APPEND);
}

function rewriteFile(array $users): void
{
    file_put_contents('users', '');
    foreach ($users as $user) {
        file_put_contents('users', json_encode($user) . '/', FILE_APPEND);
    }
}

function readFromFile(string $path)
{
    $users = [];
    $fileData = explode('/', file_get_contents($path));
    foreach ($fileData as $value) {
        if ($value !== '') {
            $users[] = json_decode($value, JSON_OBJECT_AS_ARRAY);
        }
    }
    uasort($users, fn($a, $b) => strcmp($a['name'], $b['name']));
    return array_values($users);
}

function updateUser(array $users, array $user)
{
    foreach ($users as $key => $u) {
        if ($u['id'] === $user['id']) {
            $users[$key]['name'] = $user['name'];
            $users[$key]['body'] = $user['body'];
            break;
        }
    }
    return $users;
}

function deleteUser(array $users, array $user)
{
    foreach ($users as $key => $u) {
        if ($u['id'] === $user[0]['id']) {
            unset($users[$key]);
            break;
        }
    }
    return array_values($users);
}

$app->run();
