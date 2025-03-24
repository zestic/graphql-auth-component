<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOauthClientScopesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('oauth_client_scopes', ['schema' => 'graphql_auth_test'])
            ->addColumn('client_id', 'uuid', ['null' => false])
            ->addColumn('scope', 'string', ['limit' => 100])
            ->addTimestamps()
            ->addIndex(['client_id', 'scope'], ['unique' => true])
            ->addForeignKey('client_id', 'graphql_auth_test.oauth_clients', 'client_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('scope', 'graphql_auth_test.oauth_scopes', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
