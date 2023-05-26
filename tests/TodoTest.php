<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class TodoTest extends TestCase
{
    public function testAddTodoWithoutDescription()
    {
        // Create a mock Silex application
        $app = new Silex\Application();

        // Mock the necessary dependencies
        $app['twig'] = new \Twig\Environment(new \Twig\Loader\ArrayLoader());
        $app['session'] = $this->createMock(\Symfony\Component\HttpFoundation\Session\SessionInterface::class);
        $app['db'] = $this->createMock(\Doctrine\DBAL\Connection::class);

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
        // Create a mock Silex application
        $app = new Silex\Application();

        // Mock the necessary dependencies
        $app['twig'] = new \Twig\Environment(new \Twig\Loader\ArrayLoader());
        $app['session'] = $this->createMock(Session::class);
        $app['db'] = $this->createMock(\Doctrine\DBAL\Connection::class);

        // Mock the FlashBagInterface
        $flashBag = $this->createMock(FlashBagInterface::class);
        $flashBag->expects($this->once())
            ->method('add')
            ->with('message', 'Todo marked as completed');

        // Set the FlashBagInterface on the session mock
        $app['session']->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBag);

        // Set up the default values in the mock database
        $defaultTodo = ['id' => 3, 'user_id' => 1, 'description' => 'Test', 'complete' => 0];
        $app['db']->expects($this->once())
            ->method('executeUpdate')
            ->willReturn(1); // Return the number of affected rows
        $app['db']->expects($this->once())
            ->method('fetchAssoc')
            ->willReturn($defaultTodo);

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
        $request = Request::create('/todo/3/complete', 'POST');

        // Send the request to the application
        $response = $app->handle($request);

        // Assert that the response contains the todos and has a successful status code
        $this->assertStringContainsString('Todo marked as completed', $response->getContent(), 'success');
        $this->assertEquals(200, $response->getStatusCode());
    }
}
