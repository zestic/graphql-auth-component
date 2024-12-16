<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOauthClientTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('oauth_clients')
            ->addColumn('client_id', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('redirect_uri', 'json', ['null' => true])
            ->addColumn('is_confidential', 'boolean', ['default' => false])
            ->addTimestamps()
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->create();
    }
}
