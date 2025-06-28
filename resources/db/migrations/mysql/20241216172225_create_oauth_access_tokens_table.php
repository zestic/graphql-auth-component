<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOauthAccessTokensTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('oauth_access_tokens', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('user_id', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('client_id', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('scopes', 'json', ['null' => true])
            ->addColumn('revoked', 'boolean', ['default' => false])
            ->addColumn('expires_at', 'timestamp', ['null' => false])
            ->addTimestamps()
            ->addIndex(['id'], ['unique' => true])
            ->addForeignKey('client_id', 'oauth_clients', 'client_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // Create oauth_auth_codes table for PKCE support
        $this->table('oauth_auth_codes', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('user_id', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('client_id', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('scopes', 'json', ['null' => true])
            ->addColumn('redirect_uri', 'text', ['null' => false])
            ->addColumn('revoked', 'boolean', ['default' => false])
            ->addColumn('expires_at', 'timestamp', ['null' => false])
            ->addTimestamps()
            ->addIndex(['id'], ['unique' => true])
            ->addForeignKey('client_id', 'oauth_clients', 'client_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
