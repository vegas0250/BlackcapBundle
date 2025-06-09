<?php

namespace Vegas0250\BlackcapBundle\Command;

use App\Kernel;
use FilesystemIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'blackcap:compile')]
class CompileCommand extends Command
{
    const LIST_RESERVED_DIRS = [
        'components',
        'modules',
        'elements',
        'pieces',
        'segments',
    ];

    const LIST_SYMFONY_DIRS = [
        'assets',
        'config',
        'public',
        'src',
        'templates',
        'translations',
        'tests',
    ];

    const PHOTON_ROOT_DIR = 'components';

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

    public function __construct(
        private readonly Kernel     $kernel,
        private readonly Filesystem $filesystem
    ){
        parent::__construct();
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

        foreach($iterator as $item) {
            $breadcrumbs = explode(DIRECTORY_SEPARATOR, str_replace($absolutePath.DIRECTORY_SEPARATOR, '', $item->getPathname()));

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
                            [$this->kernel->getProjectDir(), 'public', self::PHOTON_ROOT_DIR,],
                            $breadcrumbs
                        ));
                    }

                    if ($symfonyDir == 'src') {
                        $namespace = implode('\\', array_map(function ($item) {

                            return implode('\\', array_map(function ($item) {

                                return implode('\\', array_map(function ($item) {
                                    return (new UnicodeString($item))->camel()->title(true)->toString();
                                }, explode('-', $item)));

                                # return str_replace('-', '\\', $item);

                            }, explode(DIRECTORY_SEPARATOR, $item)));

                            #dump([
                           #     'breadcrumbs' => $breadcrumbs,
                                # 'unicode breadcrumbs' => (new UnicodeString($breadcrumbs))->camel()->title(true)->toString(),
                            #]);

                            #return (new UnicodeString($breadcrumbs))->camel()->title(true)->toString();
                        }, array_merge(['Component'], $breadcrumbs, [''])));

                        #dump($namespace);

                        $this->tasks[self::TASK_EXPAND_PSR4][$namespace] = implode('/', [self::PHOTON_ROOT_DIR, implode('/', $breadcrumbs), 'src', '']);

                        $this->tasks[self::TASK_EXPAND_SERVICES]['services'][$namespace]['resource'] = '%kernel.project_dir%/'.self::PHOTON_ROOT_DIR.'/'.implode('/', $breadcrumbs).'/src/';

                        if (is_dir($item->getPathname() . DIRECTORY_SEPARATOR . $symfonyDir . DIRECTORY_SEPARATOR . 'Entity')) {
                            $this->tasks[self::TASK_EXPAND_SERVICES]['doctrine']['orm']['mappings'][$item->getFilename()] = [
                                'type' => 'attribute',
                                'is_bundle' => false,
                                'dir' => '%kernel.project_dir%/'.self::PHOTON_ROOT_DIR.'/'.implode('/', $breadcrumbs).'/src/Entity',
                                'prefix' => $namespace.'Entity',
                                'alias' => $item->getFilename(),
                            ];
                        }

                        if (is_dir($item->getPathname() . DIRECTORY_SEPARATOR . $symfonyDir . DIRECTORY_SEPARATOR . 'Controller')) {
                            $this->tasks[self::TASK_EXPAND_ROUTES][$item->getFilename()] = [
                                'resource' => [
                                    'path' => '../../'.self::PHOTON_ROOT_DIR.'/'.implode('/', $breadcrumbs).'/src/Controller/',
                                    'namespace' => $namespace.'Controller'
                                ],
                                'type' => 'attribute'
                            ];
                        }
                    }

                    if ($symfonyDir == 'templates') {
                        $this->tasks[self::TASK_EXPAND_SERVICES]['twig']['paths'][implode(DIRECTORY_SEPARATOR, array_merge(['%kernel.project_dir%', self::PHOTON_ROOT_DIR], $breadcrumbs, ['templates']))] = implode('-', $breadcrumbs);
                    }

                    if ($symfonyDir == 'translations') {
                        $this->tasks[self::TASK_EXPAND_SERVICES]['framework']['translator']['paths'][] = implode(DIRECTORY_SEPARATOR, array_merge(['%kernel.project_dir%', self::PHOTON_ROOT_DIR], $breadcrumbs, ['translations']));
                    }
                }
            }
        }
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $projectConfigDir = $this->kernel->getProjectDir() . DIRECTORY_SEPARATOR . 'config';
        $projectComposerFile = $this->kernel->getProjectDir() . DIRECTORY_SEPARATOR . 'composer.json';
        $projectPhotonServicesFile = $projectConfigDir . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'photon_services.yaml';
        $projectPhotonRoutesFile = $projectConfigDir . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'photon_routes.yaml';

        $this->scan($this->kernel->getProjectDir().DIRECTORY_SEPARATOR.self::PHOTON_ROOT_DIR);

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

            file_put_contents($projectPhotonRoutesFile, Yaml::dump($this->tasks[self::TASK_EXPAND_ROUTES]));

            $table->addRow([
                self::TASK_EXPAND_ROUTES,
                microtime(true) - $startTime,
            ]);
        }

        if (count($this->tasks[self::TASK_EXPAND_SERVICES])) {
            $startTime = microtime(true);

            file_put_contents($projectPhotonServicesFile, Yaml::dump($this->tasks[self::TASK_EXPAND_SERVICES]));

            $table->addRow([
                self::TASK_EXPAND_SERVICES,
                microtime(true) - $startTime,
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
