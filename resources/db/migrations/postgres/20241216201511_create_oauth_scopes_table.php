<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOauthScopesTablePostgres extends AbstractMigration
{
    public function change(): void
    {
        $this->table('oauth_scopes', [
            'schema' => 'graphql_auth_test',
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
