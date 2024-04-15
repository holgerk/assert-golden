# assertGolden Assertion

![GitHub Release](https://img.shields.io/github/v/release/holgerk/assert-golden)
![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/holgerk/assert-golden/tests.yml)
![Packagist Downloads](https://img.shields.io/packagist/dt/holgerk/assert-golden)



Same as `toEqual`, but when `null` is given as argument, the test file is automatically edited and `null` 
is substituted with the actual value

Given the following code:
```php
assertGolden(
    null,                 // <- expectation value
    ['color' => 'golden'] // <- actual value
);
```
...during the first execution `null` replaced with the actual value:
```php
assertGolden(
    [
        'color' => 'golden',
    ],
    ['color' => 'golden']
);
```

In principle, it's about saving oneself the recurring work of writing, updating and copying
an expectation.


## Installation

You can install the package via composer:

```bash
composer require holgerk/assert-golden --dev
```


## Usage

Just pass `null` to the `toEqualGolden` expectation and `null` will be automatically replaced during the
first test run.

### Trait Usage

```php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

use Holgerk\AssertGolden\AssertGolden;

class ExampleTest extends TestCase
{
    use AssertGolden;

    #[Test]
    public function test(): void
    {
        // via method call...
        $this->assertGolden(
            null,
            ['a' => 1, 'b' => 2]
        );
        
        // ...or static call
        self::assertGolden(
            null,
            ['a' => 1, 'b' => 2]
        );
    }
}
```

### Function Usage

```php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

use function Holgerk\AssertGolden\assertGolden;

class ExampleTest extends TestCase
{
    #[Test]
    public function test(): void
    {
        assertGolden(
            null,
            ['a' => 1, 'b' => 2]
        );
    }
}
```

Later you can edit the expectation by hand or insert `null` again to have it automatically replaced.


### Regenerate all expectations 
 
If you want to regenerate all expectations at once you can add the argument: `--update-golden` to your phpunit
invocation.

```bash
# regenerate all expectations at once from their actual values
./vendor/bin/phpunit --update-golden
```

## Limitation
It is not possible to have more than one assertGolden call on one line. Because the automatic replacement is based on the `debug_backtrace` function, which gives us the line number and file of the assertGolden caller, and the composer package `nikic/php-parser`, which is used to get the exact start and end position of the expectation argument. So if there are more than one assertGolden call it is not possible to detect a distinct position.


## See Also

- [phpunit-snapshot-assertions](https://github.com/spatie/phpunit-snapshot-assertions)  
  This plugin also facilitates the automatic generation of expectations from the actual value, but it
  will store the generated expectation in separate files.
- [pest-plugin-equal-golden](https://packagist.org/packages/holgerk/pest-plugin-equal-golden)  
  Same thing for [pestphp](https://pestphp.com/) 



## Credits

- [nikic/php-parser](https://packagist.org/packages/nikic/php-parser)  


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
