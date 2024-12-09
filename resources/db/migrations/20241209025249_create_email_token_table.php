<?php

use Phinx\Migration\AbstractMigration;

class CreateEmailTokenTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('email_tokens');
        $table
            ->addColumn('expiration', 'datetime', ['null' => false])
            ->addColumn('token', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('token_type', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('user_agent', 'json', ['null' => false])
            ->addColumn('user_id', 'string', ['limit' => 255, 'null' => false])
            ->addTimestamps()
            ->addIndex(['token'], ['unique' => true])
            ->create();
    }
}
