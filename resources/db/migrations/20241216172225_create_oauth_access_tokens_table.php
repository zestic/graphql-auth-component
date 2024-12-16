<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOauthAccessTokensTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('oauth_access_tokens')
            ->addColumn('user_id', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('client_id', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('scopes', 'text', ['null' => true])
            ->addColumn('revoked', 'boolean', ['default' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addTimestamps()
            ->addIndex(['user_id'])
            ->addIndex(['client_id'])
            ->create();
    }
}
