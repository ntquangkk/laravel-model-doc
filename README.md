# Laravel Model Doc Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/triquang/laravel-model-doc.svg?style=flat-square)](https://packagist.org/packages/triquang/laravel-model-doc)
[![Total Downloads](https://img.shields.io/packagist/dt/triquang/laravel-model-doc.svg?style=flat-square)](https://packagist.org/packages/triquang/laravel-model-doc)
[![License](https://img.shields.io/packagist/l/triquang/laravel-model-doc.svg?style=flat-square)](https://github.com/ntquangkk/laravel-model-doc?tab=MIT-1-ov-file)

A Laravel Artisan command that generates PHPDoc blocks for your Eloquent models based on your database schema and relationships.

> Example:
```php
/**
 * @table quizzes
 * @property  int     $id           bigint     No description
 * @property  string  $description  text       No description
 * @property  string  $level        enum       {easy, medium, difficult}
 * @property  string  $title        varchar    No description
 * @property  Carbon  $created_at   timestamp  No description
 * @property  Carbon  $updated_at   timestamp  No description
 * @property-read Collection|QuizQuestion[] $questions
 */
```

---

## 🚀 Features

- Generate `@property` based on SQL column types.
- Detect `@property-read` from relationships.
- Support models in:
  - `app/Models`
  - `Modules/*/app/Models` (modular structure)
- Sort by:
  - PHP type (default)
  - Property name
  - DB type
- Supports multiple DB drivers: MySQL, PostgreSQL, SQLite, SQL Server, Oracle.

---

## 📦 Installation

This package is intended for development only.  
Please install it using the `--dev` flag:

Install via Composer:

```bash
composer require triquang/laravel-model-doc --dev
```

---

## ⚙️ Usage

Run the Artisan command:

```bash
php artisan gen:model-doc [options]
```

### Options

| Option         | Description |
|----------------|-------------|
| `--model`      | Only process a specific model (FQCN). Example: `App\\Models\\User`.  |
| `--dry-run`    | Show output to screen without modifying files.                       |
| `--sort`       | Sort by: `type` (PHP type), `name`, or `db` (PHP + DB + name).       |
| `--ns`         | Select namespaces to scan. Example: `Modules\\Domain\\Models`.       |

---

## 🧠 How It Works

- Reads model classes from `app/Models` and `Modules/*/app/Models` or from namespaces
- Uses DB schema to map SQL types → PHP types
- Detects Eloquent relationships (e.g., `hasMany`, `belongsTo`)
- Writes `/** ... */` docblock directly under `<?php` in model file

---

## 🧪 Examples

#### Generate all model docs:

```bash
php artisan gen:model-doc
```

#### Dry-run mode (preview only):

```bash
php artisan gen:model-doc --dry-run --sort=db
```

#### Generate for a specific model:

```bash
php artisan gen:model-doc --model=App\\Models\\User
```

#### Scan custom namespaces only:

```bash
php artisan gen:model-doc --ns=App\\Models,Modules\\Quiz\\Models
```

> 💡 Note: Namespaces must be mapped in `composer.json` using `psr-4` autoloading. This also applies to `Modules/*/composer.json`.

---

## 📄 Example Output

```php
/**
 * @table books
 * @property  int     $id             bigint      No description
 * @property  string  $title          varchar     The title of the book
 * @property  string  $description    text        A detailed description of the book
 * @property  string  $status         enum        {draft, published, archived}
 * @property  Carbon  $publish_date   date        No description
 * @property  Carbon  $created_at     timestamp   No description
 * @property  Carbon  $updated_at     timestamp   No description
 * @property-read Author $author
 * @property-read Collection|Tag[] $tags
 * @property-read Collection|Review[] $reviews
 */
```

---

## ✅ Requirements

- PHP >= 8.0
- Laravel 11 / 12
- Composer

---

## 📄 License

MIT © [Nguyễn Trí Quang](mailto:ntquangkk@gmail.com)

---

## 🙌 Contributing

PRs are welcome! Feel free to improve functionality or report issues via GitHub Issues.

---

## 📬 Contact

- GitHub: [github.com/ntquangkk](https://github.com/ntquangkk)
- Email: [ntquangkk@gmail.com](mailto:ntquangkk@gmail.com)