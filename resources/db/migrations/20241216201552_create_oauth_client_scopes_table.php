<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOauthClientScopesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('oauth_client_scopes')
            ->addColumn('client_id', 'string', ['limit' => 255])
            ->addColumn('scope', 'string', ['limit' => 100])
            ->addTimestamps()
            ->addIndex(['client_id', 'scope'], ['unique' => true])
            ->addForeignKey('client_id', 'oauth_clients', 'client_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('scope', 'oauth_scopes', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
