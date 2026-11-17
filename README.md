## Laravel Patcher

_A (migration like) patcher for a smoldering production update._ <br>

[![Total Downloads](https://poser.pugx.org/dentro/laravel-patcher/downloads)](https://packagist.org/packages/dentro/laravel-patcher)
![GitHub Workflow Status](https://github.com/digital-entropy/laravel-patcher/workflows/tests/badge.svg)

#### Requirements:

- PHP : 8.3+
- Laravel: 9._ / 10._ / 11._ / 12._ / 13.\*

> Compatibility note: starting from `v1.5.0`, this package only supports PHP `8.3` and newer.

### Installation

Do either of these methods below.

- via shell

```shell script
composer require dentro/laravel-patcher
```

- adding `"dentro/laravel-patcher": "^1.5"` to `composer.json`

```json
{
    "require": {
        "dentro/laravel-patcher": "^1.5"
    }
}
```

### Post Installation

> this process is optional, you can skip it though.

patches table creation.

```shell script
 php artisan patcher:install
```

### Usage

#### Create New Patch

for creating a new patch, you need to run these following command

```shell script
php artisan make:patch what_do_you_want_to_patch
```

After run that command, you will see new file in `patches` folder.
That file will be like:

```php
<?php

use Dentro\Patcher\Patch;

return new class extends Patch
{
    /**
     * Run patch script.
     *
     * @return void
     */
    public function patch(): void
    {
        //
    }
};
```

Method `patch` on this file will be filled with your logic.
in `Dentro\Patcher\Patch` there is some useful properties
that you can use for supporting your patch such as:

1. `$container: \Illuminate\Container\Container`
2. `$command: \Illuminate\Console\Command`

    > we frequently used `$command` property to print process that we're doing.
    > example:
    >
    > ```php
    > $this->command->warn('i patch something danger!');
    > $this->command->confirm('do you wish to continue?');
    > ```
    >
    > you can learn more about `\Illuminate\Console\Command` [here](https://laravel.com/api/9.x/Illuminate/Console/Command.html).

3. `$logger: \Illuminate\Log\Logger`

    > `$logger` will store log in `storage/logs/patches.log`. if you want to change it, add this line below in your `config/logging.php` in channels section.
    >
    > ```php
    > [
    >     'channels' => [
    >         'patcher' => [
    >              'driver' => 'patcher', // you can change me if you want
    >              'path' => storage_path('logs/patches.log'), // change me
    >          ],
    >     ],
    > ];
    > ```
    >
    > you can learn more about `\Illuminate\Log\Logger` [here](https://laravel.com/api/8.x/Illuminate/Log/Logger.html)

#### Show Patch Status

```shell script
php artisan patcher:status
```

Example:

```shell script
➜ php artisan patcher:status

  Patcher name ................................................ Batch / Status
  2020_09_29_190531_fix_double_sections .............................. [1] Ran
  2020_10_09_124616_add_attachment_beep .............................. Pending
```

Use `--pending` to only list pending patches, and `--database=` to choose the connection.

#### Run Pending Patch(es)

```shell script
php artisan patcher:run
```

Example:

```shell script
➜ php artisan patcher:run
Patches table created successfully.

  Patching: 2020_09_29_190531_fix_double_sections .................... RUNNING
  Patching: 2020_09_29_190531_fix_double_sections .............. 31.02 ms DONE
  Patching: 2020_10_09_124616_add_attachment_beep .................... RUNNING
  Patching: 2020_10_09_124616_add_attachment_beep .............. 20.67 ms DONE
```

#### Conditional Patch

You might need to skip single patch when run `php artisan patcher:run`.
Due to patch is unnecessary or patch is not eligible to run in your environment.
Here you can add the `eligible` method to your patch class to evaluate the condition
before running the `patch` method.

```php
<?php

use Dentro\Patcher\Patch;
use App\Models\User;

return new class extends Patch
{
    public function eligible(): bool
    {
        return User::query()->where('id', 331)->exists();
    }

    public function patch(): void
    {
        $user = User::query()->find(331);
        // do something with user.
    }
};
```

then the output of `php artisan patcher:run` will be:

```shell script
➜ php artisan patcher:run

  Patching: 2020_09_29_190531_fix_double_sections .................... RUNNING
  Patching: 2020_09_29_190531_fix_double_sections is not eligible to run in current condition  SKIPPED
  Patching: 2020_10_09_124616_add_attachment_beep .................... RUNNING
  Patching: 2020_10_09_124616_add_attachment_beep .............. 20.67 ms DONE
```

> A skipped patch is still recorded in the `patches` table, so it will not be evaluated again on the next run.

#### Perpetual Patch

In some cases you might also want to run patches script indefinitely, you can change `isPerpetual`
property on your patch file to `true`

```php
return new class extends Patch
{
    public bool $isPerpetual = true;

    public function patch(): void
    {
        //
    }
};
```

A perpetual patch is never recorded in the `patches` table, and `patcher:status` shows it as `Perpetual`.

#### Grouping Patches

When an application has been running for years, the `patches` folder can hold hundreds of files.
You can keep them in one folder per year or per month instead.

Publish the config file:

```shell script
php artisan vendor:publish --tag=patcher-config
```

Then set `grouping` in `config/patcher.php`, or set `PATCHER_GROUPING` in your `.env`:

| Value            | New patches are created in | Example                                          |
| ---------------- | -------------------------- | ------------------------------------------------ |
| `none` (default) | `patches/`                 | `patches/2024_03_05_101500_fix_totals.php`       |
| `year`           | `patches/YYYY/`            | `patches/2024/2024_03_05_101500_fix_totals.php`  |
| `month`          | `patches/YYYY/MM/`         | `patches/2024/03/2024_03_05_101500_fix_totals.php` |

`grouping` only decides where `make:patch` puts new files. `patcher:run` and `patcher:status` always read
`patches/`, `patches/YYYY/` and `patches/YYYY/MM/`, whatever the setting is, so a patch never stops running
because of the folder it is in. Any other subfolder (for example `patches/support/`) is ignored.

> **Upgrading:** older versions only read `patches/` itself. If you already keep files in folders named
> like a year (`patches/2019/`) or a year and month (`patches/2019/05/`), they are read from now on.
> Any `*_*.php` file there that is not in the `patches` table yet will run on the next `patcher:run`.
> Run `php artisan patcher:status --pending` after upgrading to check, and move such files elsewhere if they are not patches.

To move your existing patches into the configured layout, preview the move first, then run it:

```shell script
php artisan patcher:organize --dry-run
php artisan patcher:organize
```

Commit the moved files afterwards. Moving a patch never makes it run again, because only its file name
is recorded in the `patches` table. Setting `grouping` back to `none` and running `patcher:organize`
moves everything back into `patches/`.

> Patch file names must be unique across all folders. If the same name exists in two folders,
> `patcher:run`, `patcher:status` and `patcher:organize` stop with an error that lists both files.
> Files without a `YYYY_MM_DD_HHMMSS_` prefix are left where they are and reported by `patcher:organize`.
