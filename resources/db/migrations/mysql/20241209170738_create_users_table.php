<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function change()
    {
        $this->table('users', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('additional_data', 'json', ['null' => false])
            ->addColumn('display_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('system_id', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('verified_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex(['id'], ['unique' => true])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['system_id'], ['unique' => true])
            ->create();
    }
}
