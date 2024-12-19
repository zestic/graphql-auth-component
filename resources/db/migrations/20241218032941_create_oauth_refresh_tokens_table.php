<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOauthRefreshTokensTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('oauth_refresh_tokens', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('user_id', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('client_id', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('access_token_id', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('scopes', 'text', ['null' => true])
            ->addColumn('revoked', 'boolean', ['default' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addTimestamps()
            ->addIndex(['id'], ['unique' => true])
            ->addForeignKey('access_token_id', 'oauth_access_tokens', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('client_id', 'oauth_clients', 'client_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}