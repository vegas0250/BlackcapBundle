# BlackcapBundle

🇺🇸 English | [🇷🇺 Русский](README.ru.md)

A tree-like modular architecture in the style of the bundle approach

(in development)

### What is it?

This is a structure with a hierarchy of modules, each of which has the structure of a Symfony Bundle, but is not registered as a full-fledged bundle, but is integrated into the project via the blackcap:compile command.

They:
- Are located in app/,
- Can include each other recursively (nested modules),
- services.yaml, routes.yaml, twig-paths, psr-4, etc. are automatically registered in the main project.

That is, a recursively pluggable modular architecture with elements of the bundle structure, but with custom registration logic.

Even simpler, each module is a mini-"symfony project" with arbitrary nesting and registered key folders such as:
- /assets
- /config
- /public
- /src
- /templates
- /translations
- /tests

The console integration command reads all folders that are registered in the module hierarchy and simply integrates everything into the basic symfony project using configuration and writing psr-4 in composer.json.

### Folder structure and naming
```
├── app
│   ├── migrations
│   ├── public
│   ├── src
│   │   ├── Controller
│   │   │   └── DefaultController.php
│   │   ├── Entity
│   │   ├── Repository
│   │   └── Kernel.php
│   ├── templates
│   │   ├── layouts
│   │   │   └── base.html.twig
│   │   └── pages
│   │   └── default
│   │   └── index.html.twig
│   └── translations
├──bin
│   ├── console
│   └── phpunit
├──config
│   ├── bundles.php
│   ├── packages
│   │   ├── cache.yaml
│   │   ├── debug.yaml
│   │   ├── doctrine_migrations.yaml
│   │   ├── doctrine.yaml
│   │   ├── framework.yaml
│   │   ├── mailer.yaml
│   │   ├── messenger.yaml
│   │   ├── monolog.yaml
│   │   ├── notifier.yaml
│   │   ├── routing.yaml
│   │   ├── security.yaml
│   │   ├── translation.yaml
│   │   ├── twig.yaml
│   │   ├── validator.yaml
│   │   └── web_profiler.yaml
│   ├── preload.php
│   ├── routes
│   │   ├── annotations.yaml
│   │   ├── framework.yaml
│   │   └── web_profiler.yaml
│   ├── routes.yaml
│   └── services.yaml
├── public
│   └── index.php
├── var
├── vendor
├── phpunit.xml.dist
├── composer.json
├── composer.lock
└── symfony.lock
```