[![Build Status](https://travis-ci.org/bugadani/Annotiny.svg?branch=master)](https://travis-ci.org/bugadani/Annotiny)

Annotiny
========
Annotiny is a small library designed to read and parse documentation comments.

Usage
--------
To create the AnnotationReader object a suitable parser must be supplied.

```
$parser = new \Annotiny\AnnotationParser(new \Annotiny\AnnotationContainer);
$annotationReader = new \Annotiny\AnnotationReader($parser, $factory);
```

Annotation has four public methods: `readClass($class)`, `readFunction($function)`, `readMethod($class, $method)` and `readProperty($class, $property)`. These functions work as one would expect, e.g. `readMethod` reads and parses the documentation comment of a class or object method.
### The Comment object
The resulting data structure of a `read*` method is a Comment instance. Comments hold the description and the parsed tags of the documentation comment.

Comment provides several useful methods to work with the annotation tags.
 * `has($tag)` checks if a tag is present
 * `get($tag)` retrieves the tag value
 * `equals($tag, $value)` checks if the value of `$tag` equals to `$value`
 * `contains($tag, $value)` checks if `$value` is present in the argument list of the annotation
 * `containsAll($tag, $value_array)` checks if each member of `$value_array` is present
 * `getDescription()` returns the description part of the comment

If a tag is not found, an `OutOfBoundsException` is thrown.

Comment syntax
--------
A documentation comment is directly (e.g. no blank lines between them) above the documented class, function, method or property. The comment begins with `/**` and end with `*/`. Every line should optionally begin with an asterisk (*).

The comment begins with the description part that is terminated by a `@tag`.

### Examples:

    /** This is a one-liner */

    /**
     * Multiple lines
     *
     * @annotation
     */

Annotation syntax
--------
Annotation tags are preceded with an at-sign (@) and they start on a new line. The tag name can consist of letters, numbers, dash (-) and underscode (_) signs but must begin with at least one letter. The following are examples of invalid tag names: `@1tag`, `@-tag`.

There are three types of annotations:
 * Simple annotations that do not have values (`@tag`)
 * Annotations that are followed by their values (`@tag that has a value`).
 * Annotations that are followed by parameter list (`@tag(value, 'other value')`.

The values are strings delimited by commas. In the secound case only one value is allowed, commas and the following text are discarded. The third case allowes multiple values that are separated by commas. To allow commas and newlines in the value, simply enclose it in ' or ".

If multiple annotations are present with the same name, their values will be aggregated in an array. In this case, annotations without values will be ignored.

Note: named parameters are supported in the parameter list syntax. In this case an `=` sign separates the name and the value.

### Examples:

 * `@tag`
 * `@tag value`
 * `@tag "some value"`
 * `@tag()`
 * `@tag(simple value)`
 * `@tag(multiple, values)`
 * `@tag(name='value')`
