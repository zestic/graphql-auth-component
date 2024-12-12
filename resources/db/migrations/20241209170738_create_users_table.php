<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('users');
        $table
            ->addColumn('additional_data', 'json', ['null' => false])
            ->addColumn('display_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('verified_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex(['email'], ['unique' => true])
            ->create();
    }
}
