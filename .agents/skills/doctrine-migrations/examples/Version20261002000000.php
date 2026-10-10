<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Production Migration Example: Schema creation, index tuning, and reversible rollback.
 */
final class Version20261002000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop_customers and customer_orders tables with foreign keys and status index';
    }

    public function up(Schema $schema): void
    {
        // 1. Create customers table
        $this->addSql('
            CREATE TABLE shop_customers (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(180) NOT NULL,
                status VARCHAR(32) DEFAULT \'active\' NOT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX uniq_customer_email (email),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // 2. Create customer orders table
        $this->addSql('
            CREATE TABLE customer_orders (
                id INT AUTO_INCREMENT NOT NULL,
                customer_id INT NOT NULL,
                amount NUMERIC(10, 2) NOT NULL,
                status VARCHAR(32) DEFAULT \'pending\' NOT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX idx_order_customer (customer_id),
                INDEX idx_order_created (created_at),
                PRIMARY KEY(id),
                CONSTRAINT fk_customer_orders_customer FOREIGN KEY (customer_id) 
                    REFERENCES shop_customers (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        // Symmetrical, safe teardown
        $this->addSql('DROP TABLE customer_orders');
        $this->addSql('DROP TABLE shop_customers');
    }
}
