<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;
use App\Validator;

use function Symfony\Component\String\s;
use Symfony\Component\VarDumper\VarDumper;

session_start();


$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("this : {$id}");
});

$app->get('/courses/{courseId}/lessons/{id}', function ($request, $response, array $args) {
    $courseId = $args['courseId'];
    $id = $args['id'];
    return $response->write("Course id: {$courseId}")
        ->write("<br/>  Lesson id: {$id}");
});
//////////////////////////////////////////////////////////////////////
///                      СПИСОК ПОЛЬЗОВАТЕЛЕЙ                       //
//////////////////////////////////////////////////////////////////////
$app->get('/users', function ($request, $response) use ($router) {
    $userData = $_SESSION['user'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $messages = $this->get('flash')->getMessages();
    $term = $request->getQueryParam('term'); // достаем поисковые данные

    $result = collect($users)->filter(
        fn ($user) => str_contains($user['name'], $term) ? $user['name'] : false
    ); //фильтруем данные по запросу

    $params = ['users' => $users, 'flash' => $messages, 'user' => $userData];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('get-users');
//////////////////////////////////////////////////////////////////////
///                 СОЗДАНИЕ НОВОГО ПОЛЬЗОВАТЕЛЯ                    //
//////////////////////////////////////////////////////////////////////
$app->post('/users', function ($request, $response) use ($router) {

    $id = rand(1, 1000); // генерируем id
    $validator = new Validator();
    $user = $request->getParsedBodyParam('user');
    $errors = $validator->validate($user);
    // запись файла
    if (count($errors) === 0) {

        $userCookey = json_decode($request->getCookieParam('users', json_encode([])), true);
        $userCookey[] = ['name' => $user['name'], 'email' => $user['email'], 'id' => $id];
        $this->get('flash')->addMessage('success', 'Пользователь был добавлен'); // добавляем сообщение при регистрации
        $userUncode = json_encode($userCookey);
        $url = $router->urlFor('get-users');
        return $response->withHeader('Set-Cookie', "users={$userUncode}")
            ->withRedirect($url);
    }
    $params = ['user' => $user, 'errors' => $errors];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
})->setName('post-users');
//////////////////////////////////////////////////////////////////////
///              ФОРМА СОЗДАНИЯ НОВОГО ПОЛЬЗОВАТЕЛЯ                 //
//////////////////////////////////////////////////////////////////////
$app->get('/users/new', function ($request, $response) {
    $params = ['user' => ['name' => '', 'email' => '']];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
})->setName('users-new');
//////////////////////////////////////////////////////////////////////
///                       ПРОФИЛЬ ПОЛЬЗОВАТЕЛЯ                      //
//////////////////////////////////////////////////////////////////////
$app->get('/users/{id}', function ($request, $response, $args) {

    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    foreach ($users as $user) {
        if (in_array($args['id'], $user) === true) {
            $params = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']];
            return $this->get('renderer')->render($response, 'users/show.phtml', $params);
        }
    }
    return $response->withStatus(404);
})->setName('usersID');
///////////////////////////////////////////////////////////////////////////////////
///                   ФОРМА ОБНОВЛЕНИЯ ДАННЫХ ПОЛЬЗОВАТЕЛЯ                      ///
///////////////////////////////////////////////////////////////////////////////////

$app->get('/users/{id}/edit', function ($request, $response, array $args) {
    $id = $args['id']; // id пользователя
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $user = collect($users)->firstWhere('id', $id);

    $params = [
        'user' => $user,
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('editUser');

///////////////////////////////////////////////////////////////////////////////////
///                       ОБНОВЛЕНИЕ ДАННЫХ ПОЛЬЗОВАТЕЛЯ                        ///
///////////////////////////////////////////////////////////////////////////////////
$app->patch('/users/{id}', function ($request, $response, array $args) use ($router) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $updateUser = $request->getParsedBodyParam('user'); //новые данные 
    $validator = new Validator();
    $errors = $validator->validate($updateUser);

    if (count($errors) === 0) {
        $userUp = collect($users)->map(function ($item, $key) use ($id, $updateUser) {
            if ($id == $item['id']) {
                $updateUser['id'] = $id;
                return $updateUser;
            }
            return $item;
        }); //меняем старые данные на новые

        $this->get('flash')->addMessage('success', 'User has been updated');

        $url = $router->urlFor('get-users');
        $userUncode = json_encode($userUp);
        return $response->withHeader('Set-Cookie', "users={$userUncode}")
            ->withRedirect($url);
    }

    $params = [
        'errors' => $errors,
        'data' => $updateUser,
        'userID' => $id
    ];

    return $this->get('renderer')
        ->render($response->withStatus(422), 'users/edit.phtml', $params);
});

///////////////////////////////////////////////////////////////////////////////////
///                           УДАЛЕНИЕ ПОЛЬЗОВАТЕЛЯ                             ///
///////////////////////////////////////////////////////////////////////////////////
$app->delete('/users/{id}', function ($request, $response, array $args) use ($router) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $delete = collect($users)->map(function ($item, $key) use ($id) {
        if ($id == $item['id']) {
            unset($item);
        }
        return $item;
    })->filter()->toArray();
    $url = $router->urlFor('get-users');
    $this->get('flash')->addMessage('success', 'User has been deleted');
    $userUncode = json_encode($delete);
    return $response->withHeader('Set-Cookie', "users={$userUncode}")
        ->withRedirect($url);
});

///////////////////////////////////////////////////////////////////////////////////
///                               АВТОРИЗАЦИЯ                                   ///
///////////////////////////////////////////////////////////////////////////////////

$app->get('/', function ($request, $response) {
    $flash = $this->get('flash')->getMessages();

    $users = json_decode($request->getCookieParam('users', json_encode([])), true);


    $params = [
        'use' => $users,
        'currentUser' => $_SESSION['user'] ?? null,
        'flash' => $flash
    ];
    return $this->get('renderer')->render($response, 'users/authentification.phtml', $params);
})->setName('get-/');

$app->post('/session', function ($request, $response) use ($router) {
    $userEmail = $request->getParsedBodyParam('user');
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);

    $user = collect($users)->first(function ($user) use ($userEmail) {
        return $user['email'] === $userEmail['email'];
    });

    if ($user) {
        $_SESSION['user'] = $user;
    } else {
        $this->get('flash')->addMessage('error', 'Wrong password or name');
    }
    return $response->withRedirect('/users');
});

$app->delete('/session', function ($request, $response) {
    $_SESSION = [];
    session_destroy();
    return $response->withRedirect('/users');
});









///////////////////////////////////////////////////////////////////////////////////
///                                  TEST                                       ///
///////////////////////////////////////////////////////////////////////////////////
$app->get('/test/users', function ($request, $response) use ($router) {
    $id = rand(1, 1000);

    $users = json_decode($request->getCookieParam('user', json_encode([])), true);
    $params = [
        'users' => $users,
        'user' => ['name' => '', 'email' => '', 'id' => $id], 'errors' => []
    ];

    return $this->get('renderer')->render($response, 'users/testUsers.phtml', $params);
})->setName('get-testUser');


$app->get('/test', function ($request, $response) use ($router) {
    $id = rand(1, 1000);
    $users = json_decode($request->getCookieParam('user', json_encode([])), true);
    $params = ['user' => ['name' => '', 'email' => '', 'id' => $id], 'errors' => []];
    return $this->get('renderer')->render($response, 'users/test.phtml', $params);
})->setName('get-test');


$app->post('/test', function ($request, $response) use ($router) {

    $id = rand(1, 1000); // генерируем id
    $validator = new Validator();
    $user = $request->getParsedBodyParam('user');
    $errors = $validator->validate($user);
    // запись файла
    if (count($errors) === 0) {

        $userCookey = json_decode($request->getCookieParam('user', json_encode([])), true);
        $userCookey[] = ['name' => $user['name'], 'email' => $user['email'], 'id' => $id];
        $this->get('flash')->addMessage('success', 'Пользователь был добавлен'); // добавляем сообщение при регистрации
        $userUncode = json_encode($userCookey);
        print_r($userUncode);
        return $response->withHeader('Set-Cookie', "user={$userUncode}")
            ->withRedirect('/test/users');
    }
    $params = ['user' => $user, 'errors' => $errors];
    return $this->get('renderer')->render($response, 'users/test.phtml', $params);
})->setName('post-test');

$app->delete('/test', function ($request, $response) {
    $users = json_decode($request->getCookieParam('user', json_encode([])), true);
    $id = 685;
    $delete = collect($users)->map(function ($item, $key) use ($id) {
        if ($id == $item['id']) {
            unset($item);
        }
        return $item;
    })->filter()->toArray();
    return $response->withHeader('Set-Cookie', "user={$delete}")
        ->withRedirect('/test/users');
});





$app->run();
