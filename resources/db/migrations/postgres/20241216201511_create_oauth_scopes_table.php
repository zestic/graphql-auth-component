<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use RuntimeException;

final class CreateOauthScopesTable extends AbstractMigration
{
    public function change(): void
    {
        $schema = $this->getAdapter()->getOption('schema');
        if (empty($schema)) {
            throw new RuntimeException('Schema must be explicitly set in the Phinx configuration');
        }
        $this->execute(sprintf('CREATE SCHEMA IF NOT EXISTS %s;', $schema));
        $this->table('oauth_scopes', [
            'schema' => $schema,
            'id' => false,
            'primary_key' => ['id']
        ])
            ->addColumn('id', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('description', 'string', ['limit' => 255])
            ->addIndex(['id'], ['unique' => true])
            ->addTimestamps()
            ->create();
    }
}
