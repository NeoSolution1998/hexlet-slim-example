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

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
});

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
    $file = file_get_contents('src/save.php'); // открываем файл
    $users = json_decode($file, true); // данные из файла превращаем в массив 
    $messages = $this->get('flash')->getMessages();
    $term = $request->getQueryParam('term'); // достаем поисковые данные
    $result = collect($users)->filter(
        fn ($user) => str_contains($user['name'], $term) ? $user['name'] : false
    ); //фильтруем данные по запросу
    $params = ['users' => $result, 'term' => $term, 'flash' => $messages];
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
        $path = 'src/save.php';
        $this->get('flash')->addMessage('success', 'Пользователь был добавлен');// добавляем сообщение при регистрации
        $file = file_get_contents($path); // открываем файл
        $fileArray = json_decode($file, true); // данные из файла превращаем в массив
        $data = ['name' => $user['name'], 'email' => $user['email'], 'id' => $id];
        $fileArray[] = $data; // добавляем в массив данные нового пользователя
        file_put_contents($path, json_encode($fileArray)); // сохраняем массив в json формате
         
        return $response->withRedirect($router->urlFor('get-users'));
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
    $file = file_get_contents('src/save.php'); // открываем файл
    $users = json_decode($file, true); // данные из файла превращаем в массив

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
    $file = file_get_contents('src/save.php'); // открываем файл
    $users = json_decode($file, true); // данные из файла превращаем в массив
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
$app->patch('/users/{id}', function ($request, $response, array $args) use ($router)  {
    $id = $args['id'];
    $file = file_get_contents('src/save.php'); // открываем файл
    $users = json_decode($file, true); // данные из файла превращаем в массив

    $updateUser = $request->getParsedBodyParam('user');//новые данные 
    $validator = new Validator();
    $errors = $validator->validate($updateUser);

    if (count($errors) === 0) {
        $userUp = collect($users)->map(function ($item, $key) use ($updateUser) {
            if($updateUser['id'] == $item['id']){
                return $updateUser;
            } 
            return $item;
        });//меняем старые данные на новые
        file_put_contents('src/save.php', json_encode($userUp)); // сохраняем изменения
        $this->get('flash')->addMessage('success', 'User has been updated');
        $url = $router->urlFor('get-users');
        return $response->withRedirect($url);
    }

    $params = [
        'errors' => $errors,
        'data' => $updateUser,
    ];

    return $this->get('renderer')
                ->render($response->withStatus(422), 'users/edit.phtml', $params);
});

///////////////////////////////////////////////////////////////////////////////////
///                           УДАЛЕНИЕ ПОЛЬЗОВАТЕЛЯ                             ///
///////////////////////////////////////////////////////////////////////////////////
$app->delete('/users/{id}', function ($request, $response, array $args) use ($router) {
    $id = $args['id'];
    $file = file_get_contents('src/save.php'); // открываем файл
    $users = json_decode($file, true); // данные из файла превращаем в массив

    $delete = collect($users)->map(function ($item, $key) use ($id) {
        if($id == $item['id']){
            unset($item);
        } 
        return $item;
    })->filter()->toArray();
    file_put_contents('src/save.php', json_encode($delete)); // сохраняем изменения
    $this->get('flash')->addMessage('success', 'User has been deleted');
    return $response->withRedirect($router->urlFor('get-users'));
});

///////////////////////////////////////////////////////////////////////////////////
///                                  TEST                                       ///
///////////////////////////////////////////////////////////////////////////////////

$app->get('/test', function ($request, $response) use ($router) {
    $id = rand(1, 1000);
    $params = ['user' => ['name' => '', 'email' => '', 'id' => $id], 'errors' => []];
    return $this->get('renderer')->render($response, 'users/test.phtml', $params);
})->setName('test');

$app->run();
