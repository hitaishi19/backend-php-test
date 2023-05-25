<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
}
