<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOauthScopesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('oauth_scopes', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('description', 'string', ['limit' => 255])
            ->addIndex(['id'], ['unique' => true])
            ->addTimestamps()
            ->create();
    }
}
