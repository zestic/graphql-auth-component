<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOauthClientTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('oauth_clients', ['id' => false, 'primary_key' => ['client_id']])
            ->addColumn('client_id', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('client_secret', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('redirect_uri', 'json', ['null' => true])
            ->addColumn('is_confidential', 'boolean', ['default' => 0])
            ->addIndex(['client_id'], ['unique' => true])
            ->addTimestamps()
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->create();
    }
}
