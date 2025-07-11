# BlackcapBundle

🇺🇸 [English](README.md) | 🇷🇺 Русский

Древовидная модульная архитектура в стиле bundle-подхода

(в разработке)

### Что это такое?

Это структура c иерархией модулей, каждый из которых имеет структуру Symfony Bundle, но не регистрируется как полноценный бандл, а через команду blackcap:compile интегрируется в проект.

- Вся структура начинается в app/,
- Модули могут включать друг друга рекурсивно (бесконечная вложенность),
- services.yaml, routes.yaml, twig-пути, psr-4, и т.д. автоматически регистрируются в основной config.

То есть рекурсивно подключаемая модульная архитектура с элементами bundle-структуры, но с кастомной логикой регистрации.

Ещё проще, каждый модуль это мини-"symfony проект" с произвольной вложенностью и зарегистрированными ключевыми папками такими как:
 - /assets
 - /config
 - /public
 - /src
 - /templates
 - /translations
 - /tests

Консольная команда интеграции читает все папки которые зарегистрированны в модульной иерархии и просто с помощью конфигурации и прописыванья psr-4 в composer.json интегрирует все в базовый symfony project.

### Базовая структура и именование папок
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
│   │   │   └── base.html.twig
│   │   └── pages
│   │       └── default
│   │           └── index.html.twig
│   └── translations
├── bin
│   ├── console
│   └── phpunit
├── config
│   ├── bundles.php
│   ├── packages
│   │   ├── cache.yaml
│   │   ├── debug.yaml
│   │   ├── doctrine_migrations.yaml
│   │   ├── doctrine.yaml
│   │   ├── framework.yaml
│   │   ├── mailer.yaml
│   │   ├── messenger.yaml
│   │   ├── monolog.yaml
│   │   ├── notifier.yaml
│   │   ├── routing.yaml
│   │   ├── security.yaml
│   │   ├── translation.yaml
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