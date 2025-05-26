<?php

use Phinx\Migration\AbstractMigration;

class CreateEmailTokenTablePostgres extends AbstractMigration
{
    public function up()
    {
        xdebug_break();
        $schema = $this->getAdapter()->getOption('schema');
        if (empty($schema)) {
            throw new \RuntimeException('Schema must be explicitly set in the Phinx configuration');
        }

        $this->table('email_tokens', [
            'schema' => $schema,
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
}
