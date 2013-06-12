# jessphp

`jessphp` extends Javascript functionality with the Javascript "jess" object.

This class reads a .jess file (which is a javascript file with extra jess specific elements, or not), searches for jess method calls and compiles them threw php to the same Javascript files as you wrote only with the output of the jess method calls.

## jess.require
At this time the only supported method is `jess.require`. With the jess.require method it is possible to require other jess files. A required file becomes a Javascript module you can use several times. This means a required file has to be return something.

You can use it in normal Javascript syntax.

```javascript
jess.require('include.jess');			//includes the contents of include.jess in the same directory

jess.require('inc/functions.jess');		//includes the contents of include.jess in from the "inc" directory

jess.require('include');				//the jess extension is not required
```

## Example
Imagine you have the followin two JESS files:

### handy.jess
```javascript
/**
 * A Handy Module
 */
var handy = "This module is Handy";

return handy;
```

### app.jess
```javascript
var handy = jess.require('handy.jess');

alert(handy);
```

`app.jess` requires the "Handy" module that is defined inside `handy.jess`.

The jess compiler defines the module and adds the module to the defined module list, like this:

```javascript
jess.define('handy.jess', function() {
/**
 * A Handy Module
 */
	var handy = "This module is Handy";

	return handy;
});
```

In this example when `jess.require('handy.jess')` is called, the define function will be triggerd and the output will be returned, in this case `handy`. When the define function has already been called the direct instance will be returned. So there can not be any recursion failures.

## How to use in your PHP project

The `compile` method compiles a string of JS/JESS code to Javascript.

```php
<?php
require "jessc.php";

$jess = new JessCompiler();
echo $jess->compile("...");
```

The `compileFile` method reads and compiles a file. It will either return the result or write it to the path specified by an optional second argument.
```php
<?php
echo $jess->compileFile("main.jess");
```

The `cachedCompile` method reads and compiles a file/cached file. It will either return the result of a cache or compile if necessary.
```php
<?php
echo $jess->cachedCompile("...");
```

## License
GNU General Public License, version 3 (GPL-3.0)
http://opensource.org/licenses/GPL-3.0
