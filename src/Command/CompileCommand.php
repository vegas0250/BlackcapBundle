<?php

namespace Vegas0250\BlackcapBundle\Command;

use App\Kernel;
use AppendIterator;
use ArrayIterator;
use FilesystemIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Yaml\Yaml;

class CompileCommand extends Command
{
    const LIST_SYMFONY_DIRS = [
        'assets',
        'config',
        'public',
        'src',
        'templates',
        'translations',
        'tests',
    ];

    const TASK_COPY_PUBLIC_DIRS = 'copy-public-dirs';
    const TASK_EXPAND_PSR4 = 'expand-psr-4';
    const TASK_EXPAND_ROUTES = 'expand-routes';
    const TASK_EXPAND_SERVICES = 'expand-services';

    private array $tasks = [
        self::TASK_COPY_PUBLIC_DIRS => [],
        self::TASK_EXPAND_PSR4 => [
            'App\\' => 'src/'
        ],
        self::TASK_EXPAND_SERVICES => [
            'services' => [
                '_defaults' => [
                    'autowire' => true,
                    'autoconfigure' => true,
                ]
            ]
        ],
        self::TASK_EXPAND_ROUTES => [],
    ];

    private $baseComponentName = 'app';
    private $kernel;
    private $filesystem;

    public function __construct(Kernel $kernel, Filesystem $filesystem)
    {
        $this->kernel = $kernel;
        $this->filesystem = $filesystem;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('blackcap:compile')
            ->addArgument('base-dir-name', InputArgument::OPTIONAL, 'Base component directory name', 'app')
        ;
    }

    # dir = это название директории
    # file = это название файла
    # path = это путь до файла или папки
    # relativePath = относительный
    # absolutePath = абсолютный путь

    public function scan($absolutePath = null) {
        if (!is_dir($absolutePath)) {
            echo 'Folder ' . $absolutePath . ' not found'.PHP_EOL;
            exit;
        }

        $rootIterator = new ArrayIterator([new SplFileInfo($absolutePath)]);

        $filter = new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator(
                $absolutePath,
                FilesystemIterator::SKIP_DOTS
            ),
            function ($current) {
                return $current->isDir() && !in_array($current->getFilename(), self::LIST_SYMFONY_DIRS) && !str_starts_with($current->getFilename(), '_');
            }
        );

        $iterator = new \RecursiveIteratorIterator(
            $filter,
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $combined = new AppendIterator();
        $combined->append($rootIterator);
        $combined->append($iterator);

        foreach($combined as $item) {
            $breadcrumbs = explode(DIRECTORY_SEPARATOR, str_replace($this->kernel->getProjectDir().DIRECTORY_SEPARATOR, '', $item->getPathname()));

            $symfonyDirs = array_intersect(scandir($item->getPathname()), self::LIST_SYMFONY_DIRS);

            if (count($symfonyDirs)) {
                foreach($symfonyDirs as $symfonyDir) {
                    /*
                    'assets',
                    'config',
                    'public',
                    'src',
                    'templates',
                    'translations',
                    'tests',
                    */

                    if ($symfonyDir == 'public') {
                        $this->tasks[self::TASK_COPY_PUBLIC_DIRS][$item->getPathname().DIRECTORY_SEPARATOR.$symfonyDir] = implode(DIRECTORY_SEPARATOR, array_merge(
                            [$this->kernel->getProjectDir(), 'public', $this->baseComponentName,],
                            $breadcrumbs
                        ));
                    }

                    if ($symfonyDir == 'src') {
                        $namespace = implode('\\', array_map(function ($item) {

                            return implode('\\', array_map(function ($item) {
                                return implode('\\', array_map(function ($item) {
                                    return (new UnicodeString($item))->camel()->title(true)->toString();
                                }, explode('-', $item)));
                            }, explode(DIRECTORY_SEPARATOR, $item)));

                        }, array_merge($breadcrumbs, [''])));

                        $this->tasks[self::TASK_EXPAND_PSR4][$namespace] = implode('/', [implode('/', $breadcrumbs), 'src', '']);

                        $this->tasks[self::TASK_EXPAND_SERVICES]['services'][$namespace]['resource'] = '%kernel.project_dir%/'.implode('/', $breadcrumbs).'/src/';

                        if (is_dir($item->getPathname() . DIRECTORY_SEPARATOR . $symfonyDir . DIRECTORY_SEPARATOR . 'Entity')) {
                            $this->tasks[self::TASK_EXPAND_SERVICES]['doctrine']['orm']['mappings'][$item->getFilename()] = [
                                'type' => $this->getSupportedType(),
                                'is_bundle' => false,
                                'dir' => '%kernel.project_dir%/'.implode('/', $breadcrumbs).'/src/Entity',
                                'prefix' => $namespace.'Entity',
                                'alias' => $item->getFilename(),
                            ];
                        }

                        if (is_dir($item->getPathname() . DIRECTORY_SEPARATOR . $symfonyDir . DIRECTORY_SEPARATOR . 'Controller')) {
                            $this->tasks[self::TASK_EXPAND_ROUTES][$item->getFilename()] = [
                                'resource' => [
                                    'path' => '../../'.implode('/', $breadcrumbs).'/src/Controller/',
                                    'namespace' => $namespace.'Controller'
                                ],
                                'type' => 'attribute'
                            ];
                        }
                    }

                    if ($symfonyDir == 'templates') {
                        $this->tasks[self::TASK_EXPAND_SERVICES]['twig']['paths'][implode(DIRECTORY_SEPARATOR, array_merge(['%kernel.project_dir%'], $breadcrumbs, ['templates']))] = implode('-', $breadcrumbs);
                    }

                    if ($symfonyDir == 'translations') {
                        $this->tasks[self::TASK_EXPAND_SERVICES]['framework']['translator']['paths'][] = implode(DIRECTORY_SEPARATOR, array_merge(['%kernel.project_dir%'], $breadcrumbs, ['translations']));
                    }
                }
            }
        }
    }

    private function getSupportedType() : string {
        return (version_compare(Kernel::VERSION, '5.2.0', '>=')) ? 'attribute' : 'annotation';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->baseComponentName = $input->getArgument('base-dir-name');

        $projectConfigDir = $this->kernel->getProjectDir() . DIRECTORY_SEPARATOR . 'config';
        $projectComposerFile = $this->kernel->getProjectDir() . DIRECTORY_SEPARATOR . 'composer.json';
        $projectBlackcapServicesFile = $projectConfigDir . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'blackcap_services.yaml';
        $projectBlackcapRoutesFile = $projectConfigDir . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'blackcap_routes.yaml';

        $this->scan($this->kernel->getProjectDir().DIRECTORY_SEPARATOR.$this->baseComponentName);

        $table = new Table($output->section());
        $table
            ->setHeaders(['task', 'time taken'])
        ;

        if (count($this->tasks[self::TASK_COPY_PUBLIC_DIRS])) {
            $startTime = microtime(true);

            foreach ($this->tasks[self::TASK_COPY_PUBLIC_DIRS] as $srcDir => $destDir) {
                $this->filesystem->mirror($srcDir, $destDir);
            }

            $table->addRow([
                self::TASK_COPY_PUBLIC_DIRS,
                microtime(true) - $startTime,
            ]);
        }

        if (count($this->tasks[self::TASK_EXPAND_PSR4])) {
            $startTime = microtime(true);

            $projectComposerFileJSONData = json_decode(file_get_contents($projectComposerFile), true);

            $projectComposerFileJSONData['autoload']['psr-4'] = $this->tasks[self::TASK_EXPAND_PSR4];

            file_put_contents($projectComposerFile, json_encode($projectComposerFileJSONData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $table->addRow([
                self::TASK_EXPAND_PSR4,
                microtime(true) - $startTime,
            ]);
        }

        if (count($this->tasks[self::TASK_EXPAND_ROUTES])) {
            $startTime = microtime(true);

            file_put_contents($projectBlackcapRoutesFile, Yaml::dump($this->tasks[self::TASK_EXPAND_ROUTES]));

            $table->addRow([
                self::TASK_EXPAND_ROUTES,
                microtime(true) - $startTime,
            ]);
        }

        if (count($this->tasks[self::TASK_EXPAND_SERVICES])) {
            $startTime = microtime(true);

            file_put_contents($projectBlackcapServicesFile, Yaml::dump($this->tasks[self::TASK_EXPAND_SERVICES]));

            $table->addRow([
                self::TASK_EXPAND_SERVICES,
                microtime(true) - $startTime,
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
