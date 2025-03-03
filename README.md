Laravel Patcher
--
*A (migration like) patcher for a smoldering production update.* <br>

[![Total Downloads](https://poser.pugx.org/dentro/laravel-patcher/downloads)](https://packagist.org/packages/dentro/yalr)
![GitHub Workflow Status](https://github.com/digital-entropy/laravel-patcher/workflows/tests/badge.svg)

#### Requirements:
* PHP : 8.\*
* Laravel: 9.\* / 10.\* / 11.\* / 12.\*

### Installation
Do either of these methods below.
* via shell 
```shell script
composer require dentro/laravel-patcher
``` 
* adding `"dentro/laravel-patcher": "^1.0"` to `composer.json`
```json
{
  "require": {
    "dentro/laravel-patcher": "^1.0"
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

class WhatDoYouWantToPatch extends Patch
{
    public function patch()
    {
        // 
    }
}
```
Method `patch` on this file will be filled with your logic. 
in ```Dentro\Patcher\Patch``` there is some useful properties 
that you can use for supporting your patch such as: 
1. `$container: \Illuminate\Container\Container`
2. `$command: \Illuminate\Console\Command`

    > we frequently used `$command` property to print process that we're doing.
    example: 
    > ```php
    > $this->command->warn('i patch something danger!');
    > $this->command->confirm('do you wish to continue?');
    > ```
    > you can learn more about `\Illuminate\Console\Command` [here](https://laravel.com/api/9.x/Illuminate/Console/Command.html).

3. `$logger: \Illuminate\Log\Logger`

    > `$logger` will store log in `storage/logs/patches.log`. if you want to change it, add this line below in your `config/logging.php` in channels section.  
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
    > you can learn more about `\Illuminate\Log\Logger` [here](https://laravel.com/api/8.x/Illuminate/Log/Logger.html)
#### Show Patch Status
```shell script
php artisan patcher:status
```
Example: 
```shell script
➜ php artisan patcher:status
+------+---------------------------------------+-------+
| Ran? | Patch                                 | Batch |
+------+---------------------------------------+-------+
| Yes  | 2020_09_29_190531_fix_double_sections | 1     |
| Yes  | 2020_10_09_124616_add_attachment_beep | 1     |
+------+---------------------------------------+-------+
```

#### Run Pending Patch(es)
```shell script
php artisan patcher:run
```
Example:
```shell script
➜ php artisan patcher:run
Patches table created successfully.
Patching: 2020_09_29_190531_fix_double_sections
Patched:  2020_09_29_190531_fix_double_sections (0.03 seconds)
Patching: 2020_10_09_124616_add_attachment_beep
Patched:  2020_10_09_124616_add_attachment_beep (0.06 seconds)
```

#### Conditional Patch
You might need to skip single patch when run ```php artisan patcher:run```. 
Due to patch is unnecessary or patch is not eligible to run in your environment. 
Here you can add the ```eligible``` method to your patch class to evaluate the condition 
before running the ```patch``` method.   

```php
<?php

use Dentro\Patcher\Patch;
use App\Models\User;

class WhatDoYouWantToPatch extends Patch
{
    public function eligible(): bool
    {
        return User::query()->where('id', 331)->exists();
    }
    
    public function patch()
    {
        $user = User::query()->find(331);
        // do something with user.
    }
}
```
then the output of ```php artisan patcher:run``` will be:
```shell script
➜ php artisan patcher:run
Patching: 2020_09_29_190531_fix_double_sections
Skipped:  2020_09_29_190531_fix_double_sections is not eligible to run in current condition.
Patching: 2020_10_09_124616_add_attachment_beep
Patched:  2020_10_09_124616_add_attachment_beep (0.06 seconds)
```

#### Perpetual Patch
In some cases you might also want to run patches script indefinitely, you can change `isPerpetual`
property on your patch file to `true`

```php
class WhatDoYouWantToPatch extends Patch
{
    public bool $isPerpetual = true;
}
```
