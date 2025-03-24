<?php

use Phinx\Migration\AbstractMigration;

class CreateEmailTokenTable extends AbstractMigration
{
    public function up()
    {
        $this->execute('CREATE SCHEMA IF NOT EXISTS auth;');
        
        $this->table('email_tokens', [
            'schema' => 'auth',
            'id' => 'id',
            'primary_key' => ['id'],
            'collation' => 'default'
        ])
            ->addColumn('expiration', 'timestamp', ['null' => false])
            ->addColumn('token', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('token_type', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('user_id', 'uuid', ['null' => false])
            ->addTimestamps()
            ->addIndex(['token'], ['unique' => true])
            ->create();
    }

    public function down()
    {
        $this->table('email_tokens', ['schema' => 'auth'])->drop()->save();
    }
}
