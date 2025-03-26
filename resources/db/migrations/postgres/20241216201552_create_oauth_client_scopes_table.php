<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOauthClientScopesTable extends AbstractMigration
{
    public function change(): void
    {
        $schema = $this->getAdapter()->getOption('schema');
        if (empty($schema)) {
            throw new RuntimeException('Schema must be explicitly set in the Phinx configuration');
        }
        $this->execute(sprintf('CREATE SCHEMA IF NOT EXISTS %s;', $schema));
        $this->table('oauth_client_scopes', ['schema' => $schema])
            ->addColumn('client_id', 'uuid', ['null' => false])
            ->addColumn('scope', 'string', ['limit' => 100])
            ->addTimestamps()
            ->addIndex(['client_id', 'scope'], ['unique' => true])
            ->addForeignKey('client_id', $schema . '.oauth_clients', 'client_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('scope', $schema . '.oauth_scopes', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
