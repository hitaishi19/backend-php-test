<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addGlobal('user', $app['session']->get('user'));

    return $twig;
}));


$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html', [
        'readme' => file_get_contents('README.md'),
    ]);
});


$app->match('/login', function (Request $request) use ($app) {
    $username = $request->get('username');
    $password = $request->get('password');

    if ($username) {
        $sql = "SELECT * FROM users WHERE username = '$username' and password = '$password'";
        $user = $app['db']->fetchAssoc($sql);

        if ($user){
            $app['session']->set('user', $user);
            return $app->redirect('/todo');
        }
    }

    return $app['twig']->render('login.html', array());
});


$app->get('/logout', function () use ($app) {
    $app['session']->set('user', null);
    return $app->redirect('/');
});


$app->get('/todo/{id}', function ($id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    if ($id){
        $sql = "SELECT * FROM todos WHERE id = '$id'";
        $todo = $app['db']->fetchAssoc($sql);

        return $app['twig']->render('todo.html', [
            'todo' => $todo,
        ]);
    } else {
        $page = $app['request']->query->get('page', 1); // Get the page query parameter, default to 1 if not provided

        $limit = 3; // Number of todos per page
        $offset = ($page - 1) * $limit;

        // Fetch the todos for the current page
        $sql = "SELECT * FROM todos WHERE user_id = '${user['id']}' LIMIT $limit OFFSET $offset";
        $todos = $app['db']->fetchAll($sql);

        // Count the total number of todos
        $countSql = "SELECT COUNT(*) AS count FROM todos WHERE user_id = '${user['id']}'";
        $totalCount = $app['db']->fetchAssoc($countSql)['count'];

        // Calculate the total number of pages
        $totalPages = ceil($totalCount / $limit);

        return $app['twig']->render('todos.html', [
            'todos' => $todos,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }
})
->value('id', null);


// Set up the route and controller for adding a todo
$app->post('/todo/add', function (Request $request) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }
    try{
        $response = [];
        $description = $request->get('description');
        //check if description is empty or not
        if (empty($description)) {
            // Set the warning message
            $response['error'] = 'Todo description is required';
        } else if(strlen($description) > 100) {
            // Set the warning message
            $response['error'] = 'Todo description should be less than 100 characters';
        } else {
            $user_id = $user['id'];
            $sql = "INSERT INTO todos (user_id, description) VALUES ('$user_id', '$description')";
            $app['db']->executeUpdate($sql);

            // Set the success message
            $response['message'] = 'Todo is added';
        }

        // Return the JSON response
        return $app->json($response);
    } catch (Exception $e) {
        // Handle the exception
        $error = $e->getMessage();

        // Return the error JSON response
        return $app->json(['error' => $error], 500);
    }
});


$app->match('/todo/delete/{id}', function ($id) use ($app) {
    try{
        $response = [];

        $sql = "DELETE FROM todos WHERE id = '$id'";
        $app['db']->executeUpdate($sql);

        $app['session']->getFlashBag()->add('message', 'Todo is removed');

        // Set the success message
        $response['message'] = 'Todo is removed';

        // Return the JSON response
        return $app->json($response);
    } catch (Exception $e) {
        // Handle the exception
        $error = $e->getMessage();

        // Return the error JSON response
        return $app->json(['error' => $error], 500);
    }
});

// Set up the route and controller for marking todo as complete
$app->post('/todo/{id}/complete', function ($id) use ($app) {
    try {
        if (null === $app['session']->get('user')) {
            return $app->redirect('/login');
        }

        if ($id){
            $sql = "UPDATE todos SET complete = 1 WHERE id = '$id' ";
            $app['db']->executeUpdate($sql);

            $sql = "SELECT * FROM todos WHERE id = '$id'";
            $todo = $app['db']->fetchAssoc($sql);

            // Set the success message
            $message = 'Todo marked as completed';

            // Return the JSON response
            return $app->json(['message' => $message, 'todo' => $todo]);
        }

        // Return the ID not found JSON response
        return $app->json(['error' => 'Please provide Todo ID'], 500);
    } catch (Exception $e) {
        // Handle the exception
        $error = $e->getMessage();

        // Return the error JSON response
        return $app->json(['error' => $error], 500);
    }
});


// Set up the route and controller for fetching given todo in JSON format
$app->get('/todo/{id}/json', function ($id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    if ($id){
        $sql = "SELECT * FROM todos WHERE id = '$id'";
        $todo = $app['db']->fetchAssoc($sql);

        //return json format of the query response
        return json_encode($todo);
    }
    return null;
});

$app->get('/fetchAjaxData', function () use ($app) {
    try{
        $response = [];
        if (null === $user = $app['session']->get('user')) {
            return $app->redirect('/login');
        }

        $page = $app['request']->query->get('page', 1); // Get the page query parameter, default to 1 if not provided

        $limit = 3; // Number of todos per page
        $offset = ($page - 1) * $limit;

        // Fetch the todos for the current page
        $sql = "SELECT * FROM todos WHERE user_id = '${user['id']}' LIMIT $limit OFFSET $offset";
        $todos = $app['db']->fetchAll($sql);

        // Count the total number of todos
        $countSql = "SELECT COUNT(*) AS count FROM todos WHERE user_id = '${user['id']}'";
        $totalCount = $app['db']->fetchAssoc($countSql)['count'];

        // Calculate the total number of pages
        $totalPages = ceil($totalCount / $limit);

        $response['html'] = $app['twig']->render('table.html', [
            'todos' => $todos,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);

        // Return the JSON response
        return $app->json($response);
    } catch (Exception $e) {
        // Handle the exception
        $error = $e->getMessage();

        // Return the error JSON response
        return $app->json(['error' => $error], 500);
    }
});