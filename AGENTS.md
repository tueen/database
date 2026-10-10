# Tueen Database — AI Agent Engineering Guide (`AGENTS.md`)

Welcome, AI Agent or Pair-Programmer! This guide governs the architectural laws, design invariants, coding conventions, and verification workflows for `tueen/database`.

---

## 🏛️ 1. Project Mission & Invariants

**Tueen Database** is the high-performance, zero-config relational database component for the **Tueen Telegram Bot Framework** (and can also be used as a standalone library in any PHP 8.4+ project).

### Core Invariants:
1. **PHP 8.4+ First:**
   - Always leverage **Property Hooks** (`get => ...`, `set => ...`) for validation, normalization, and computed properties.
   - Always use **Asymmetric Visibility** (`public private(set)`) for entity IDs and immutable timestamps instead of boilerplates.
   - Always declare backed **Enums** for finite states (`ChatType`, `OrderStatus`, `BroadcastStatus`, etc.).
   - Never use parentheses when chaining new instances: `new DateTimeImmutable()->modify(...)`.
2. **Declarative Zero-Config Schema Auto-Sync:**
   - The engine automatically inspects and synchronizes schema diffs on boot/first access via `AutoMigrator` and `SchemaSynchronizer`.
   - End-users must **never** be forced to run manual CLI migration commands to get a bot up and running.
   - When introducing a new table or altering a schema:
     - Update or create the Doctrine Entity attributes.
     - Add a forward migration in `migrations/` for traditional environments.
     - Ensure `SchemaSynchronizer` safely resolves the diff.
3. **Telegram Domain Requirements:**
   - **Chat IDs are signed 64-bit BigInts:** Telegram user IDs are positive integers, but group, supergroup, and channel IDs are negative numbers (e.g. `-1001234567890`). Never use unsigned for `chat_id`!
   - **Usernames:** Automatically strip leading `@` using backed property hooks (`set (?string $value) => $value !== null ? ltrim(trim($value), '@') : null;`).
   - **Text & Names:** Use `utf8mb4` encoding to fully support international characters and emojis.
4. **Ledger & Arbitrary-Precision Math:**
   - All monetary, token, crypto, and wallet balances are stored as strings using `DECIMAL(36, 18)` or `DECIMAL(40, 18)`.
   - Never perform floating-point math on currencies. Always use `bcadd()`, `bcsub()`, `bccomp()`, `bcmul()`, and `bcdiv()`.
5. **Multi-Database Agnostic:**
   - Must run flawlessly on SQLite (`:memory:` and file), MySQL 8+, MariaDB, and PostgreSQL.

---

## 🛠️ 2. Workflow for Adding New Features

Whenever you or an agent is asked to add a new entity, table, or feature, you **MUST** follow this exact progression:

### Step 1: Define the Entity (`src/Entity/`)
- Annotate with `#[ORM\Entity(repositoryClass: ...)]`, `#[ORM\Table(name: '...')]`, and indexes.
- Use `public private(set)` for primary keys and creation dates.
- Use Property Hooks for derived states (e.g. `public bool $isActive { get => ...; }`).
- Include validation attributes (`#[Assert\NotBlank]`, `#[Assert\Length]`).

### Step 2: Create or Update the Repository (`src/Repository/`)
- Extend `Doctrine\ORM\EntityRepository`.
- Provide high-level domain methods (e.g. `findOrCreate(...)`, `credit(...)`, `qualify(...)`).
- Wrap multi-step mutations inside `$this->getEntityManager()->wrapInTransaction(...)`.

### Step 3: Expose via `DatabaseManager` (`src/DatabaseManager.php`)
- Add a fluent accessor method (e.g. `public function myFeature(): MyFeatureRepository`).
- Call `$this->ensureMigrated()` prior to returning the repository.

### Step 4: Write Tests
- Unit test property hooks and domain rules in `tests/Unit/`.
- Functional integration test on SQLite in `tests/Functional/`.

### Step 5: Verification Pipeline
Execute the full quality assurance suite:
```bash
# 1. Run PHPUnit test suite
vendor/bin/phpunit

# 2. Run PHPStan static analysis at Level 8
vendor/bin/phpstan analyse -c phpstan.neon

# 3. Format and verify code standards with PHP CS Fixer
vendor/bin/php-cs-fixer fix

# 4. Validate CLI tool
php bin/tueen-db tueen:db:validate
```

### Step 6: Update Documentation & Skills
- Document new entities and methods in `docs/`.
- If introducing an architectural pattern, update `.agents/skills/tueen-database/SKILL.md`.
- Verify the VitePress build: `npm run docs:build` in `docs/`.

---

## 📚 3. Architectural Map

```
Tueen\Database
├── Command/          # Symfony Console commands (tueen:db:sync, diff, status, validate, cache:clear)
├── Config/           # ConnectionConfig and DatabaseConfig (immutable, type-safe)
├── Connection/       # DBAL ConnectionFactory with SQLite PRAGMAs & platform hardening
├── DependencyInjection/ # Symfony ContainerFactory & services definitions
├── Entity/           # 22+ Doctrine entities with PHP 8.4 hooks & attributes
├── Enum/             # Backed Enums (ChatType, OrderStatus, BroadcastStatus, etc.)
├── Event/            # Symfony EventDispatcher domain events
├── Exception/        # Typed exceptions (RecordNotFoundException, InsufficientBalanceException)
├── Migration/        # AutoMigrator, SchemaSynchronizer (declarative safe diff sync), Registry
├── Repository/       # Domain repositories with fluent transactional operations
├── Security/         # CredentialManager (.env) & PasswordHasherService
└── DatabaseManager   # Main entry point and facade for the entire database engine
```

---

## ⚡ 4. Slogan
> **"Tueen Database: The Zero-Config, High-Throughput Relational Engine for Modern Telegram Bots."**
