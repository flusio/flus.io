<?php

namespace Website\admin;

class AuthTest extends \PHPUnit\Framework\TestCase
{
    use \tests\LoginHelper;
    use \Minz\Tests\InitializerHelper;
    use \Minz\Tests\ApplicationHelper;
    use \Minz\Tests\ResponseAsserts;

    public function testLogin()
    {
        $request = new \Minz\Request('GET', '/admin/login');

        $response = self::$application->run($request);

        $this->assertResponse($response, 200);
    }

    public function testLoginWhenConnected()
    {
        $this->login();
        $request = new \Minz\Request('GET', '/admin/login');

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, null, ['Location' => '/admin']);
    }

    public function testLoginWithFromParameter()
    {
        $request = new \Minz\Request('GET', '/admin/login', [
            'from' => 'home',
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 200);
        $variables = $response->output()->variables();
        $this->assertArrayHasKey('from', $variables);
        $this->assertSame('home', $variables['from']);
    }

    public function testCreateSession()
    {
        $request = new \Minz\Request('POST', '/admin/login', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'password' => 'secret',
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, null, [
            'Location' => '/admin?status=connected'
        ]);
        $this->assertTrue($_SESSION['connected']);
    }

    public function testCreateSessionWhenConnected()
    {
        $this->login();
        $request = new \Minz\Request('POST', '/admin/login', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'password' => 'secret',
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, null, ['Location' => '/admin']);
    }

    public function testCreateSessionWithFromParameter()
    {
        $request = new \Minz\Request('POST', '/admin/login', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'password' => 'secret',
            'from' => urlencode('home'),
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, null, [
            'Location' => '/?status=connected'
        ]);
        $this->assertTrue($_SESSION['connected']);
    }

    public function testCreateSessionFailsIfCsrfIsWrong()
    {
        (new \Minz\CSRF())->generateToken();
        $request = new \Minz\Request('POST', '/admin/login', [
            'csrf' => 'not the token',
            'password' => 'secret',
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 400);
        $this->assertArrayNotHasKey('connected', $_SESSION);
        $variables = $response->output()->variables();
        $this->assertNotEmpty($variables['error']);
    }

    public function testCreateSessionFailsIfPasswordIsInvalid()
    {
        $request = new \Minz\Request('POST', '/admin/login', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'password' => 'not the secret',
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 400);
        $this->assertArrayNotHasKey('connected', $_SESSION);
        $variables = $response->output()->variables();
        $this->assertNotEmpty($variables['error']);
    }

    public function testDeleteSession()
    {
        $this->login();
        $request = new \Minz\Request('POST', '/admin/logout', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, null, ['Location' => '/']);
        $this->assertArrayNotHasKey('connected', $_SESSION);
    }

    public function testDeleteSessionWhenUnconnected()
    {
        $request = new \Minz\Request('POST', '/admin/logout', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, null, ['Location' => '/']);
    }

    public function testDeleteSessionFailsIfCsrfIsWrong()
    {
        $this->login();
        (new \Minz\CSRF())->generateToken();
        $request = new \Minz\Request('POST', '/admin/logout', [
            'csrf' => 'not the token',
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, null, ['Location' => '/']);
        $this->assertArrayHasKey('connected', $_SESSION);
    }
}
