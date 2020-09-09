<?php

/**
 * @see       https://github.com/mezzio/mezzio-skeleton for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-skeleton/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-skeleton/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioInstallerTest;

use Aura\Di\Container as AuraDiContainer;
use Chubbyphp\Container\Container as ChubbyphpContainer;
use DI\Container as PhpDIContainer;
use Generator;
use Laminas\ServiceManager\ServiceManager as LaminasServiceManagerContainer;
use Mezzio\LaminasView\ConfigProvider as LaminasViewRendererConfigProvider;
use Mezzio\LaminasView\LaminasViewRenderer;
use Mezzio\Plates\ConfigProvider as PlatesRendererConfigProvider;
use Mezzio\Plates\PlatesRenderer;
use Mezzio\Router\AuraRouter;
use Mezzio\Router\AuraRouter\ConfigProvider as AuraRouterConfigProvider;
use Mezzio\Router\FastRouteRouter;
use Mezzio\Router\FastRouteRouter\ConfigProvider as FastRouteRouterConfigProvider;
use Mezzio\Router\LaminasRouter;
use Mezzio\Router\LaminasRouter\ConfigProvider as LaminasRouterConfigProvider;
use Mezzio\Twig\ConfigProvider as TwigRendererConfigProvider;
use Mezzio\Twig\TwigRenderer;
use MezzioInstaller\OptionalPackages;
use Northwoods\Container\InjectorContainer as AurynContainer;
use Pimple\Psr11\Container as PimpleContainer;
use Symfony\Component\DependencyInjection\ContainerBuilder as SfContainerBuilder;

use function chdir;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function json_decode;
use function preg_replace;

class HomePageResponseTest extends OptionalPackagesTestCase
{
    use ProjectSandboxTrait;

    /** @var OptionalPackages */
    private $installer;

    private $routerConfigProviders = [
        AuraRouter::class      => AuraRouterConfigProvider::class,
        FastRouteRouter::class => FastRouteRouterConfigProvider::class,
        LaminasRouter::class   => LaminasRouterConfigProvider::class,
    ];

    private $rendererConfigProviders = [
        PlatesRenderer::class      => PlatesRendererConfigProvider::class,
        TwigRenderer::class        => TwigRendererConfigProvider::class,
        LaminasViewRenderer::class => LaminasViewRendererConfigProvider::class,
    ];

    // $intallType, $intallType
    private $intallTypes = [
        OptionalPackages::INSTALL_FLAT    => OptionalPackages::INSTALL_FLAT,
        OptionalPackages::INSTALL_MODULAR => OptionalPackages::INSTALL_MODULAR,
    ];

    // $rendererOption, $rendererClass
    private $rendererTypes = [
        'plates'       => [1, PlatesRenderer::class],
        'twig'         => [2, TwigRenderer::class],
        'laminas-view' => [3, LaminasViewRenderer::class],
    ];

    // $routerOption, $routerClass
    private $routerTypes = [
        'aura-router'    => [1, AuraRouter::class],
        'fastroute'      => [2, FastRouteRouter::class],
        'laminas-router' => [3, LaminasRouter::class],
    ];

    private $expectedRouterAttributes = [
        AuraRouter::class      => [
            'routerName' => 'Aura.Router',
            'routerDocs' => 'http://auraphp.com/packages/3.x/Router/',
        ],
        FastRouteRouter::class => [
            'routerName' => 'FastRoute',
            'routerDocs' => 'https://github.com/nikic/FastRoute',
        ],
        LaminasRouter::class   => [
            'routerName' => 'Laminas Router',
            'routerDocs' => 'https://docs.laminas.dev/laminas-router/',
        ],
    ];

    // $containerOption, $containerClass
    private $containerTypes = [
        'aura-di'                => [1, AuraDiContainer::class],
        'pimple'                 => [2, PimpleContainer::class],
        'laminas-servicemanager' => [3, LaminasServiceManagerContainer::class],
        'auryn'                  => [4, AurynContainer::class],
        'sf-di'                  => [5, SfContainerBuilder::class],
        'php-di'                 => [6, PhpDIContainer::class],
        'chubbyphp-container'    => [7, ChubbyphpContainer::class],
    ];

    private $expectedContainerAttributes = [
        AuraDiContainer::class                => [
            'containerName' => 'Aura.Di',
            'containerDocs' => 'http://auraphp.com/packages/4.x/Di/',
        ],
        PimpleContainer::class                => [
            'containerName' => 'Pimple',
            'containerDocs' => 'https://pimple.symfony.com/',
        ],
        LaminasServiceManagerContainer::class => [
            'containerName' => 'Laminas Servicemanager',
            'containerDocs' => 'https://docs.laminas.dev/laminas-servicemanager/',
        ],
        AurynContainer::class                 => [
            'containerName' => 'Auryn',
            'containerDocs' => 'https://github.com/rdlowrey/Auryn',
        ],
        SfContainerBuilder::class             => [
            'containerName' => 'Symfony DI Container',
            'containerDocs' => 'https://symfony.com/doc/current/service_container.html',
        ],
        PhpDIContainer::class                 => [
            'containerName' => 'PHP-DI',
            'containerDocs' => 'http://php-di.org',
        ],
        ChubbyphpContainer::class             => [
            'containerName' => 'Chubbyphp Container',
            'containerDocs' => 'https://github.com/chubbyphp/chubbyphp-container',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectRoot = $this->copyProjectFilesToTempFilesystem();
        $this->installer   = $this->createOptionalPackages($this->projectRoot);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        chdir($this->packageRoot);
        $this->recursiveDelete($this->projectRoot);
        $this->tearDownAlternateAutoloader();
    }

    /**
     * @runInSeparateProcess
     * @dataProvider installCasesProvider
     */
    public function testHomePageHtmlResponseContainsExpectedInfo(
        string $installType,
        int $containerOption,
        int $rendererOption,
        string $rendererClass,
        string $containerName,
        string $containerDocs
    ) {
        $this->prepareSandboxForInstallType($installType, $this->installer);

        // Install container
        $config          = $this->getInstallerConfig($this->installer);
        $containerResult = $this->installer->processAnswer(
            $config['questions']['container'],
            $containerOption
        );
        $this->assertTrue($containerResult);

        // Install router
        $routerResult     = $this->installer->processAnswer(
            $config['questions']['router'],
            $routerOption = 2 // FastRoute, use assignment for clarity
        );
        $this->assertTrue($routerResult);
        $this->injectRouterConfigProvider(FastRouteRouter::class);

        // Install template engine
        $templateEngineResult = $this->installer->processAnswer(
            $config['questions']['template-engine'],
            $rendererOption
        );
        $this->assertTrue($templateEngineResult);
        $this->injectRendererConfigProvider($rendererClass);

        // Test home page response
        $response = $this->getAppResponse('/', true);
        $this->assertEquals(200, $response->getStatusCode());

        // Test response content
        $html = (string) $response->getBody()->getContents();

        $this->assertStringContainsString("Get started with {$containerName}", $html);
        $this->assertStringContainsString("href=\"{$containerDocs}\"", $html);
    }

    public function installCasesProvider(): Generator
    {
        // Execute a test case for each container, renderer and non minimal install type
        foreach ($this->containerTypes as $containerId => $containerType) {
            $containerOption = $containerType[0];
            $containerClass  = $containerType[1];

            $containerName = $this->expectedContainerAttributes[$containerClass]['containerName'];
            $containerDocs = $this->expectedContainerAttributes[$containerClass]['containerDocs'];

            foreach ($this->rendererTypes as $rendererId => $rendererType) {
                $rendererOption = $rendererType[0];
                $rendererClass  = $rendererType[1];

                // skip laminas-view / non laminas-servicemanager combinations
                if (3 === $rendererOption && 3 !== $containerOption) {
                    continue;
                }

                foreach ($this->intallTypes as $intallType) {
                    $name = implode('--', [$containerId, $rendererId, $intallType]);
                    $args = [
                        $intallType,
                        $containerOption,
                        $rendererOption,
                        $rendererClass,
                        $containerName,
                        $containerDocs,
                    ];

                    yield $name => $args;
                }
            }
        }
    }

    /**
     * @runInSeparateProcess
     * @dataProvider rendererlessInstallCasesProvider
     */
    public function testHomePageJsonResponseContainsExpectedInfo(
        string $installType,
        int $containerOption,
        string $containerName,
        string $containerDocs,
        int $routerOption,
        string $routerClass,
        string $routerName,
        string $routerDocs
    ) {
        $this->prepareSandboxForInstallType($installType, $this->installer);

        // Install container
        $config          = $this->getInstallerConfig($this->installer);
        $containerResult = $this->installer->processAnswer(
            $config['questions']['container'],
            $containerOption
        );
        $this->assertTrue($containerResult);

        // Install router
        $routerResult = $this->installer->processAnswer(
            $config['questions']['router'],
            $routerOption
        );
        $this->assertTrue($routerResult);
        $this->injectRouterConfigProvider($routerClass);

        // Test home page response
        $response = $this->getAppResponse('/', true);
        $this->assertEquals(200, $response->getStatusCode());

        // Test response content
        $json = (string) $response->getBody()->getContents();
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('containerName', $data);
        $this->assertArrayHasKey('containerDocs', $data);
        $this->assertEquals($containerName, $data['containerName']);
        $this->assertEquals($containerDocs, $data['containerDocs']);
        $this->assertArrayHasKey('routerName', $data);
        $this->assertArrayHasKey('routerDocs', $data);
        $this->assertEquals($routerName, $data['routerName']);
        $this->assertEquals($routerDocs, $data['routerDocs']);
    }

    public function rendererlessInstallCasesProvider(): Generator
    {
        // Execute a test case for each install type and container, without any renderer
        foreach ($this->containerTypes as $containerId => $containerType) {
            // auryn psr-wrapper : issue with invokable services
            if ($containerId === 'auryn') {
                continue;
            }

            $containerOption = $containerType[0];
            $containerClass  = $containerType[1];

            $containerName = $this->expectedContainerAttributes[$containerClass]['containerName'];
            $containerDocs = $this->expectedContainerAttributes[$containerClass]['containerDocs'];

            foreach ($this->routerTypes as $routerId => $routerType) {
                $routerOption = $routerType[0];
                $routerClass  = $routerType[1];
                $routerName   = $this->expectedRouterAttributes[$routerClass]['routerName'];
                $routerDocs   = $this->expectedRouterAttributes[$routerClass]['routerDocs'];

                foreach ($this->intallTypes as $intallType) {
                    $name = implode('--', [$containerId, $routerId, $intallType]);
                    $args = [
                        $intallType,
                        $containerOption,
                        $containerName,
                        $containerDocs,
                        $routerOption,
                        $routerClass,
                        $routerName,
                        $routerDocs,
                    ];

                    yield $name => $args;
                }
            }
        }
    }

    public function injectRouterConfigProvider(string $routerClass)
    {
        $configFile = $this->projectRoot . '/config/config.php';
        $contents   = file_get_contents($configFile);
        $contents   = preg_replace(
            '/(new ConfigAggregator\(\[)/s',
            '$1' . "\n    " . $this->routerConfigProviders[$routerClass] . "::class,\n",
            $contents
        );
        file_put_contents($configFile, $contents);
    }

    public function injectRendererConfigProvider(string $rendererClass)
    {
        $configFile = $this->projectRoot . '/config/config.php';
        $contents   = file_get_contents($configFile);
        $contents   = preg_replace(
            '/(new ConfigAggregator\(\[)/s',
            '$1' . "\n    " . $this->rendererConfigProviders[$rendererClass] . "::class,\n",
            $contents
        );
        file_put_contents($configFile, $contents);
    }
}
