# osCommerce V4 Architecture

## Purpose

osCommerce V4 is a Yii2-based multi-platform e-commerce suite. Unlike earlier osCommerce versions, V4 is a full-stack MVC application with a proper ORM (Yii2 ActiveRecord), dependency injection, and a console layer.

## Directory Structure

```
osCommerce-V4/
├── admin/                          # Legacy admin entry points / plugins
│   └── plugins/ckeditor/           # Rich-text editor integration
├── lib/
│   ├── common/
│   │   ├── models/                 # Yii2 ActiveRecord models (Customers, Orders, Products…)
│   │   ├── classes/                # Shared service classes (platform, mailer, qrcode…)
│   │   ├── extensions/             # Optional feature extensions (Subscribers, SplitAddresses…)
│   │   ├── config/                 # Application config files (params.php, main-local.php)
│   │   └── api/                    # REST/XML API models and structure definitions
│   ├── frontend/                   # Storefront application (Yii2 web app)
│   │   └── themes/                 # Theme wrappers and templates
│   └── console/                    # Console application (migrations, CLI tasks)
│       └── views/migration.php
├── ext/                            # Payment module extensions
└── lib/vendor/                     # Composer-managed dependencies (Yii2, spout, etc.)
```

## Key Design Decisions

- **Yii2 framework**: All models extend `yii\db\ActiveRecord`. Relations use `hasOne()`/`hasMany()`. Query classes (e.g. `CustomersQuery`) provide scoped finders.
- **Multi-platform**: A `platform_id` column on most tables allows a single database to serve multiple storefronts. `\common\classes\platform::currentId()` provides the current context.
- **Extension system**: Feature extensions (e.g. `Subscribers`, `DealersMultiCustomers`) are checked via `\common\helpers\Acl::checkExtensionAllowed()`. Extensions live under `lib/common/extensions/`.
- **Status constants**: `Customers::STATUS_ACTIVE = 1` / `STATUS_DISABLE = 0` are integer constants — candidates for PHP 8.1 backed enums.
- **`beforeSave()` auto-defaults**: The `Customers` model's `beforeSave()` iterates column schema to fill in defaults for non-nullable columns on insert, avoiding DB constraint errors.
- **Access control list**: `Access_Control_List` and `Access_Levels` models manage admin permission matrices.

## Extension Points

- Add a model: create `lib/common/models/{Name}.php` extending `ActiveRecord`.
- Add an extension: create `lib/common/extensions/{Name}/` with an `Extension.php` entry point and register it in the ACL config.
- Add a console command: create a controller under `lib/console/controllers/`.

## Dependency Flow

```
Yii2 bootstrap (index.php / console entry)
  → Application config (params.php, main-local.php)
  → Module/component initialization
  → Controller action
  → ActiveRecord models ↔ Database
  → View / API response
```
