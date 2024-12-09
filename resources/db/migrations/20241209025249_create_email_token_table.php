<?php

use Phinx\Migration\AbstractMigration;

class CreateEmailTokenTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('email_tokens');
        $table
            ->addColumn('expiration', 'datetime')
            ->addColumn('token', 'string', ['limit' => 255])
            ->addColumn('token_type', 'string', ['limit' => 20])
            ->addColumn('user_agent', 'json')
            ->addColumn('user_id', 'string', ['limit' => 255])
            ->addTimestamps()
            ->addIndex(['token'], ['unique' => true])
            ->create();
    }
}
