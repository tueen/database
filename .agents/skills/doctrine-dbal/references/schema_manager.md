# Doctrine DBAL SchemaManager Reference

The SchemaManager provides programmatic inspection and modification of relational database schemas across different database engines.

---

## 1. Creating the Schema Manager

In modern DBAL:
```php
$schemaManager = $conn->createSchemaManager();
```

---

## 2. Inspecting Existing Databases

```php
// List all table names
$tableNames = $schemaManager->listTableNames();

// Introspect specific table details
$table = $schemaManager->introspectTable('users');

// Inspect columns
foreach ($table->getColumns() as $column) {
    $colName = $column->getName();
    $colType = $column->getType()->getName();
    $nullable = !$column->getNotnull();
}

// Inspect indexes
foreach ($table->getIndexes() as $index) {
    $isUnique = $index->isUnique();
    $columns  = $index->getColumns();
}

// Inspect foreign keys
foreach ($table->getForeignKeys() as $fk) {
    $foreignTable = $fk->getForeignTableName();
    $localCols    = $fk->getLocalColumns();
    $foreignCols  = $fk->getForeignColumns();
}
```

---

## 3. Creating & Altering Tables Programmatically

```php
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

// Define a new table
$table = new Table('orders');
$table->addColumn('id', Types::INTEGER, [
    'autoincrement' => true,
    'unsigned'      => true,
]);
$table->addColumn('user_id', Types::INTEGER, ['unsigned' => true]);
$table->addColumn('total_amount', Types::DECIMAL, [
    'precision' => 10,
    'scale'     => 2,
]);
$table->addColumn('status', Types::STRING, ['length' => 32, 'default' => 'pending']);
$table->addColumn('created_at', Types::DATETIME_IMMUTABLE);

// Constraints
$table->setPrimaryKey(['id']);
$table->addIndex(['user_id'], 'idx_orders_user_id');
$table->addForeignKeyConstraint('users', ['user_id'], ['id'], [
    'onDelete' => 'CASCADE',
    'onUpdate' => 'CASCADE',
], 'fk_orders_user');

// Execute DDL on server
$schemaManager->createTable($table);
```

---

## 4. Comparing Schemas & Generating DDL Diff

You can calculate the exact SQL needed to migrate one schema state to another using `Comparator`:

```php
use Doctrine\DBAL\Schema\Comparator;

$currentSchema = $schemaManager->introspectSchema();
$targetSchema  = clone $currentSchema;

// Modify target schema
$targetTable = $targetSchema->getTable('users');
$targetTable->addColumn('two_factor_secret', Types::STRING, ['length' => 128, 'notnull' => false]);

// Compute diff
$comparator = $schemaManager->createComparator();
$schemaDiff = $comparator->compareSchemas($currentSchema, $targetSchema);

// Generate migration SQL queries for active platform
$queries = $conn->getDatabasePlatform()->getAlterSchemaSQL($schemaDiff);

foreach ($queries as $sql) {
    $conn->executeStatement($sql);
}
```
