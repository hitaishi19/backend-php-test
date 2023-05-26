<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class TodoTest extends TestCase
{
    protected $app;
    protected $flashBag;

    protected function setUp() : void
    {
        // Create a mock Silex application
        $this->app = new Silex\Application();

        // Mock the necessary dependencies
        $this->app['twig'] = new \Twig\Environment(new \Twig\Loader\ArrayLoader());
        $this->app['session'] = $this->createMock(Session::class);
        $this->app['db'] = $this->createMock(\Doctrine\DBAL\Connection::class);
        // Mock the FlashBagInterface
        $this->flashBag = $this->createMock(FlashBagInterface::class);
    }

    public function testAddTodoWithoutDescription()
    {
        $app = $this->app;
        // Set up the route and controller for adding a todo
        $app->post('/todo/add', function (Request $request) use ($app) {
            if (null === $user = $app['session']->get('user')) {
                return $app->redirect('/login');
            }

            $description = $request->get('description');

            if (empty($description)) {
                return new Response('Todo description is required.', 400);
            }

            $user_id = $user['id'];
            $sql = "INSERT INTO todos (user_id, description) VALUES ('$user_id', '$description')";
            $app['db']->executeUpdate($sql);

            return $app->redirect('/todo');
        });

        // Create a mock user and set it in the session
        $user = ['id' => 1];
        $app['session']->expects($this->once())
            ->method('get')
            ->with('user')
            ->willReturn($user);

        // Create a mock request without a description
        $request = Request::create('/todo/add', 'POST');

        // Send the request to the application
        $response = $app->handle($request);

        // Assert that the response contains the error message and has a 400 status code
        $this->assertEquals('Todo description is required.', $response->getContent());
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testMarkTodoAsCompleted()
    {
        $app = $this->app;
        $this->flashBag->expects($this->once())
            ->method('add')
            ->with('message', 'Todo marked as completed');

        // Set the FlashBagInterface on the session mock
        $app['session']->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($this->flashBag);

        // Mock the database query and result
        $todoId = 3;
        $todoData = [
            'id' => $todoId,
            'title' => 'Sample Todo',
            'completed' => false,
        ];
        $app['db']->expects($this->once())
            ->method('fetchAssoc')
            ->with("SELECT * FROM todos WHERE id = '$todoId'")
            ->willReturn($todoData);

        $app->post('/todo/{id}/complete', function ($id) use ($app) {
            if (null === $app['session']->get('user')) {
                return $app->redirect('/login');
            }

            if ($id){
                $sql = "UPDATE todos SET complete = 1 WHERE id = '$id' ";
                $app['db']->executeUpdate($sql);

                $sql = "SELECT * FROM todos WHERE id = '$id'";
                $todo = $app['db']->fetchAssoc($sql);
        
                $app['session']->getFlashBag()->add('message', 'Todo marked as completed');
        
                return $app['twig']->render('todo.html', [
                    'todo' => $todo,
                ]);
            }
        
            return $app->redirect('/todo');
        });

        // Create a mock user and set it in the session
        $user = ['id' => 1];
        $app['session']->expects($this->once())
            ->method('get')
            ->with('user')
            ->willReturn($user);

        // Create a mock request without a description
        $request = Request::create('/todo/'.$todoId.'/complete', 'POST');

        // Send the request to the application
        $response = $app->handle($request);

        // Assert that the response contains the todos and has a successful status code
        $this->assertStringContainsString('Todo marked as completed', $response->getContent(), 'success');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testViewTodoAsJson()
    {
        $app = $this->app;

        // Mock the database query and result
        $todoId = 1;
        $todoData = [
            'id' => $todoId,
            'title' => 'Sample Todo',
            'completed' => false,
        ];
        $app['db']->expects($this->once())
            ->method('fetchAssoc')
            ->with("SELECT * FROM todos WHERE id = '$todoId'")
            ->willReturn($todoData);

        $app->get('/todo/{id}/json', function ($id) use ($app) {
            if (null === $user = $app['session']->get('user')) {
                return $app->redirect('/login');
            }
            if ($id){
                $sql = "SELECT * FROM todos WHERE id = '$id'";
                $todo = $app['db']->fetchAssoc($sql);
                return json_encode($todo);
            }
            return null;
        });

        // Create a mock user and set it in the session
        $user = ['id' => 1];
        $app['session']->expects($this->once())
            ->method('get')
            ->with('user')
            ->willReturn($user);

        // Create a mock request without a description
        $request = Request::create('/todo/'.$todoId.'/json', 'GET');

        // Send the request to the application
        $response = $app->handle($request);

        // Assert that the response is a JSON response with the expected todo data
        $expectedResponse = json_encode($todoData);
        $this->assertJsonStringEqualsJsonString($expectedResponse, $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
    }
}
