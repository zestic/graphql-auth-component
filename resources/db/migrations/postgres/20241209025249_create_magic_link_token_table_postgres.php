<?php

use Phinx\Migration\AbstractMigration;

class CreateMagicLinkTokenTablePostgres extends AbstractMigration
{
    public function up()
    {
        $schema = $this->getAdapter()->getOption('schema');
        if (empty($schema)) {
            throw new \RuntimeException('Schema must be explicitly set in the Phinx configuration');
        }

        $this->table('magic_link_tokens', [
            'schema' => $schema,
            'id' => 'id',
            'primary_key' => ['id'],
            'collation' => 'default'
        ])
            ->addColumn('client_id', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('code_challenge', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('code_challenge_method', 'string', ['limit' => 10, 'null' => false])
            ->addColumn('redirect_uri', 'string', ['limit' => 500, 'null' => false])
            ->addColumn('state', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('expiration', 'timestamp', ['null' => false])
            ->addColumn('token', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('token_type', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('user_id', 'uuid', ['null' => false])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('user_agent', 'string', ['limit' => 500, 'null' => true])
            ->addTimestamps()
            ->addIndex(['token'], ['unique' => true])
            ->create();
    }

    public function down()
    {
        $schema = $this->getAdapter()->getOption('schema');
        $this->table('magic_link_tokens', ['schema' => $schema])->drop()->save();
    }
}
