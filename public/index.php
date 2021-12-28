<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Validator;

use function Symfony\Component\String\s;

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

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

$app->get('/usersid/{id}', function ($request, $response, $args){
    $params = ['id' => $args['id'], 'nickname' => 'user-' . $args['id']];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});
/////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////
$app->get('/users', function ($request, $response) {
    $file = file_get_contents('src/save.php'); // открываем файл
    $users = json_decode($file, true); // данные из файла превращаем в массив 
    
    $term = $request->getQueryParam('term'); // достаем поисковые данные
    $result = collect($users)->filter(
        fn ($user) => str_contains($user['name'], $term) ? $user['name'] : false
    ); //фильтруем данные по запросу
    $params = ['users' => $result, 'term' => $term];
    return $this->get('renderer')->render($response, 'users/users.phtml', $params);
});


$app->post('/users', function ($request, $response) {

    $validator = new Validator();
    $user = $request->getParsedBodyParam('user');
    $errors = $validator->validate($user);
    // запись файла
    if (count($errors) === 0){
        $path = 'src/save.php';
        $file = file_get_contents($path); // открываем файл
        $fileArray = json_decode($file, true); // данные из файла превращаем в массив
        $data = ['name' => $user['name'], 'email' => $user['email'], 'id' => $user['id']];
        $fileArray[] = $data; // добавляем в массив данные нового пользователя
        file_put_contents($path, json_encode($fileArray)); // созраняем массив в json формате 
        return $response->withRedirect('/users');
    }
    $params = ['user' => $user, 'errors' => $errors];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
});

$app->get('/users/new', function ($request, $response) {
    $id = rand();
    $params = ['user' => ['name' => '', 'email' => '', 'id' => $id], 'errors' => []];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
});


/////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////
$app->run();
