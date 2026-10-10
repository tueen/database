---
name: php-84-standards
description: >-
  Rules, architectural standards, performance optimizations, and best practices for PHP 8.4 features:
  Property Hooks (virtual, backed, interfaces, abstract, parent delegation, array gotchas),
  Asymmetric Visibility (omitting redundant 'public'), Native #[\Deprecated] migration patterns,
  Method chaining on new without parentheses, new array_* utilities, and production runtime hardening.
---

# PHP 8.4 Standards, Architecture & Performance Guide

This skill defines the official coding conventions, architectural patterns, and performance guidelines for **PHP 8.4+** within the `tueen/telegram` codebase. It synthesizes language specifications with real-world production engineering best practices from Zend, core RFCs, and modern optimization benchmarks.

---

## 🪝 1. Property Hooks (`get` & `set`)

Property hooks provide first-class support for computed, validated, and normalized properties directly within the class definition, eliminating repetitive getter/setter boilerplate while keeping properties fully type-safe and transparent to static analysis.

### A. Virtual vs. Backed Properties

#### 1. Virtual Properties (No Backing Storage)
A property is **virtual** when no defined hook references `$this->propertyName`. It does not consume dedicated memory storage per instance and computes its value on demand:

```php
// Virtual read-only property (derived from context or internal state)
public ?int $chatId {
    get => $this->context->resolveChatId();
}

public string $fullName {
    get => trim("{$this->firstName} {$this->lastName}");
}

// Virtual write-only or split property
public string $fullName {
    get => "{$this->first} {$this->last}";
    set {
        [$this->first, $this->last] = explode(' ', $value, 2);
    }
}
```

> [!WARNING]
> **Virtual Property Rules:**
> 1. Virtual properties **CANNOT** have default values (`public string $foo = 'bar' { get => ... }` is a compile error).
> 2. Virtual properties with only a `get` hook are **natively read-only**; attempting to write throws an `Error`.

#### 2. Backed Properties (With Internal Storage)
A property is **backed** when it stores raw data in object state and intercepts access:

```php
public string $username {
    // Normalization on write
    set (string $value) => ltrim($value, '@');
}

public string $countryCode {
    // Validation on write with backing assignment
    set (string $value) {
        if (strlen($value) !== 2) {
            throw new \InvalidArgumentException('Country code must be ISO 3166-1 alpha-2.');
        }
        $this->countryCode = strtoupper($value);
    }
}
```

> [!IMPORTANT]
> **Default Value Gotcha:**
> If a backed property defines a default value (`public string $role = 'guest' { set => ... }`), the default value is assigned **directly** and **does NOT execute the `set` hook**. Always ensure default values are pre-validated.

---

### B. Interface & Abstract Class Property Hooks

In PHP 8.4, interfaces and abstract classes can declare required public properties and specify which operations (`get`, `set`, or both) must be supported:

```php
interface HasUpdateContextInterface
{
    // Implementer MUST provide a publicly readable chatId (set is unrestricted)
    public ?int $chatId { get; }

    // Implementer MUST provide a publicly readable & writable tag
    public string $tag { get; set; }
}

abstract class AbstractBotHandler
{
    // Concrete children must implement the virtual/backed hook
    abstract public ?int $userId { get; }
}
```

---

### C. Hook Inheritance & Parent Delegation

Child classes can override individual hooks, lock them down with `final`, or delegate to parent hooks:

```php
class BaseHandler
{
    public string $token {
        set (string $value) => trim($value);
    }
}

class HardenedHandler extends BaseHandler
{
    public string $token {
        final set (string $value) {
            if (empty($value)) {
                throw new \InvalidArgumentException('Token cannot be empty.');
            }
            // Delegate to parent's set hook via ::set syntax
            parent::$token::set($value);
        }
    }
}
```

---

### D. Critical Property Hook Gotchas & Anti-Patterns

| Anti-Pattern / Pitfall | Why It Fails | Idiomatic PHP 8.4 Solution |
| :--- | :--- | :--- |
| **In-place array mutations** (`$obj->items[] = $x;`) | **`set` hook is NOT triggered** on array push or key modification (`$obj->items['k'] = $v`). | Use a narrow method contract (`$obj->addItem($x)`) or dedicated Collection object. |
| **Heavy computations in `get` hooks** | Virtual `get` runs on **every access**. In tight loops (e.g. 10,000 updates), this degrades throughput. | Memoize expensive lookups into an internal property or cache. |
| **References on Hooked Properties** (`$ref = &$obj->prop;`) | Requires explicit `&get` and causes compile errors on backed properties with `set` hooks. | Avoid references entirely; pass values or immutable value objects. |

---

## 🔒 2. Asymmetric Visibility (`private(set)` & `protected(set)`)

Asymmetric visibility allows public read access while restricting write permissions, completely eliminating boilerplate getters for immutable or read-dominated response models.

### The Redundancy Rule (CRITICAL)
In PHP 8.4, if no explicit read visibility is defined, **it defaults to `public`**.
Writing `public private(set)` is an anti-pattern that triggers IDE inspection warnings (*"Redundant visibility modifier"*).

```php
// ✅ CORRECT (Idiomatic PHP 8.4+):
private(set) int $id;
private(set) ?User $user = null;
protected(set) int $offset = 0;

// ❌ INCORRECT (Anti-pattern - triggers inspection warning):
public private(set) int $id;
public protected(set) int $offset = 0;
```

### Guidelines for `tueen/telegram`:
1. **API Response Types (`src/Types/`):**
   - Every deserialized response field must use `private(set)` directly without `public`:
     ```php
     #[Field('file_id', required: true)]
     private(set) string $fileId;
     ```
2. **Generator Tooling (`tools/generator/CodeGenerator.php`):**
   - Always emit `private(set)` or `protected(set)` cleanly.
3. **Internal State:**
   - Use `protected(set)` when subclass extension requires internal state mutations, while keeping public callers read-only.

---

## 🔄 3. Migration & Smooth Backward Compatibility

When modernizing existing zero-argument getter methods (e.g. `$bot->chatId()`, `$context->message()`) to Property Hooks, preserve 100% backward compatibility using PHP's separate method and property symbol tables.

### Coexistence Pattern with `#[\Deprecated]`
Because PHP resolves `$obj->prop` (property) and `$obj->prop()` (method) independently, you can introduce property hooks alongside deprecated fallback methods:

```php
// 1. Modern Property Hook (Primary API)
public ?int $chatId {
    get => $this->context->resolveChatId();
}

// 2. Backward-Compatible Method Fallback
#[\Deprecated(message: 'Use property $bot->chatId instead', since: '1.0.0')]
public function chatId(): ?int
{
    return $this->chatId;
}
```

- **Clean Developer Experience:** Callers using `$bot->chatId` get instant property access without empty parentheses.
- **Zero Breaking Changes:** Existing callers of `$bot->chatId()` continue functioning while receiving IDE deprecation warnings.

---

## 🔍 4. Modern `array_*()` Utilities

PHP 8.4 introduces native functional search helpers that short-circuit immediately upon finding a match, replacing bulky `foreach` loops:

| Function | Return Type | Behavior |
| :--- | :--- | :--- |
| `array_find(array $arr, callable $cb)` | `mixed` | Returns the first element satisfying predicate, or `null`. |
| `array_find_key(array $arr, callable $cb)` | `int\|string\|null` | Returns the key of first matching element, or `null`. |
| `array_any(array $arr, callable $cb)` | `bool` | Returns `true` if at least one element satisfies predicate. |
| `array_all(array $arr, callable $cb)` | `bool` | Returns `true` only if **all** elements satisfy predicate. |

```php
// Check if updates contain any photo message
$hasPhoto = array_any($updates, fn(Update $u) => $u->message?->photo !== null);

// Find first command message
$firstCommand = array_find($messages, fn(Message $m) => $m->isCommand);
```

---

## ⛓️ 5. Method Chaining on `new` Without Parentheses

PHP 8.4 removes the syntactic requirement to wrap `new` in parentheses before calling methods or accessing properties:

```php
// ✅ Modern PHP 8.4+:
$request = new Request('POST', $url)->withHeader('Accept', 'application/json');
$handler = new UpdateDispatcher()->registerRoute($route);

// ❌ Pre-8.4 boilerplate:
$request = (new Request('POST', $url))->withHeader('Accept', 'application/json');
```

---

## 🏷️ 6. Native `#[\Deprecated]` Attribute

Always use the native engine `#[\Deprecated]` attribute instead of relying solely on `@deprecated` docblocks:

```php
#[\Deprecated(message: 'Use $message->textOrCaption property instead', since: '1.0.0')]
public function getTextOrCaption(): ?string
{
    return $this->textOrCaption;
}
```

---

## ⚡ 7. PHP 8.4 Performance Optimization & Runtime Hardening

Optimizing PHP 8.4 applications requires an empirical, reproducible workflow: **Baseline → Profile → Fix Hotspots → Harden Runtime → Re-measure**.

### A. High-Resolution Benchmarking (`hrtime`)
Always benchmark micro-optimizations using high-resolution monotonic time:

```php
function bench(callable $work, int $iterations = 50): void {
    $runs = [];
    for ($i = 0; $i < $iterations; $i++) {
        $t0 = hrtime(true);
        $work();
        $runs[] = (hrtime(true) - $t0) / 1e6; // ms
    }
    sort($runs);
    $p95 = $runs[(int) floor(0.95 * count($runs))] ?? end($runs);
    printf("Min: %.2fms | Median: %.2fms | P95: %.2fms | Max: %.2fms\n",
        min($runs), $runs[(int) floor(count($runs) / 2)], $p95, max($runs)
    );
}
```

### B. Production OPcache & JIT Configuration
In PHP 8.4 production environments (Docker, FPM, CLI runners), apply these tuned OPcache settings:

```ini
; -------- OPcache Optimization --------
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.max_accelerated_files=60000
opcache.validate_timestamps=0
opcache.interned_strings_buffer=16

; -------- JIT (Evaluate for CPU-bound tasks) --------
opcache.jit=tracing
opcache.jit_buffer_size=64M

; -------- Realpath Cache --------
realpath_cache_size=4096K
realpath_cache_ttl=300
```

### C. Composer Autoloader Hardening
Eliminate filesystem stat lookups and dynamic file path resolutions:

```bash
# Production deployment command
composer install --no-dev --classmap-authoritative --apcu-autoloader

# Or dump authoritative classmap with APCu
composer dump-autoload -a --apcu
```

---

## 🛠️ Verification Checklist for Agents

When implementing or reviewing PHP 8.4 code:

1. [ ] **No Redundant `public private(set)`:** Verify using:
   ```powershell
   Get-ChildItem -Path "src", "tests" -Recurse -Filter "*.php" | Select-String -Pattern "public (?:private|protected)\(set\)"
   ```
2. [ ] **Virtual Property Hooks Have No Defaults:** Ensure no virtual property has `= default;`.
3. [ ] **No In-Place Array Hooks:** Ensure properties backed by arrays do not expect `$obj->arr[] = $x` to trigger `set`.
4. [ ] **BC Fallbacks Present When Deprecating:** When replacing `$bot->method()` with `$bot->prop`, provide the `#[\Deprecated]` method fallback.
5. [ ] **Syntax & Token Linting:** Verify all files parse cleanly:
   ```bash
   composer lint
   ```
6. [ ] **Test Suite Green:** Ensure full unit test execution passes:
   ```bash
   composer test
   ```
