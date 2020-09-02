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
    return $this->get('renderer')->render($response, 'index.phtml');
});

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['nickname' => '', 'email' => '', 'id' => ''],
        'errors' => []
    ];

    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
});

//$repo = new App\UserRepository();

$app->post('/users', function ($request, $response) {
    $user = $request->getParsedBodyParam('user');
    $errors = validate($user);
    if (count($errors) === 0) {
        saveInFile($user);
        return $response->withRedirect('/users', 302);
    }
    $params = [
        'user' => $user,
        'errors' => $errors
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->get('/users', function ($request, $response) use ($users) {
    $users = readFromFile('users');
    $term = $request->getQueryParam('term');
    if ($term !== null) {
        $filteredUsers = array_filter($users, fn($user) => stripos($user['nickname'], $term) !== false);
    } else {
        $filteredUsers = $users;
    }
    $params = ['users' => $filteredUsers];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
});

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = htmlspecialchars($args['id']);
    headers($request);
    return $response->write("Course id: {$id}");
});

function headers($request)
{
    $headers = $request->getHeaders();
    $uri = $request->getUri();
    $method = $request->getMethod();
    echo "<p>{$method} {$uri}</p>"; 
    foreach ($headers as $name => $values) {
        echo "<p>" . $name . ": " . implode(", ", $values) . "</p>";
    }
}

function validate($user)
{
    $errors = [];
    if (empty($user['nickname'])) {
        $errors['nickname'] = "Can't be blank";
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
    return $users;
}

$app->run();
