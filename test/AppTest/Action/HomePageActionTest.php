<?php

declare(strict_types=1);

namespace AppTest\Action;

use App\Action\HomePageAction;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HomePageActionTest extends TestCase
{
    /** @var RouterInterface */
    protected $router;

    protected function setUp()
    {
        $this->router = $this->prophesize(RouterInterface::class);
    }

    public function testReturnsJsonResponseWhenNoTemplateRendererProvided()
    {
        $homePage = new HomePageAction($this->router->reveal(), null);
        $response = $homePage->process(
            $this->prophesize(ServerRequestInterface::class)->reveal(),
            $this->prophesize(RequestHandlerInterface::class)->reveal()
        );

        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    public function testReturnsHtmlResponseWhenTemplateRendererProvided()
    {
        $renderer = $this->prophesize(TemplateRendererInterface::class);
        $renderer
            ->render('app::home-page', Argument::type('array'))
            ->willReturn('');

        $homePage = new HomePageAction($this->router->reveal(), $renderer->reveal());

        $response = $homePage->process(
            $this->prophesize(ServerRequestInterface::class)->reveal(),
            $this->prophesize(RequestHandlerInterface::class)->reveal()
        );

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }
}
