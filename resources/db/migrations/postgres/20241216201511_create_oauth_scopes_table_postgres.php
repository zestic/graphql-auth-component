<?php

use Phinx\Migration\AbstractMigration;

class CreateOauthScopesTablePostgres extends AbstractMigration
{
    public function up()
    {
        $schema = $this->getAdapter()->getOption('schema');
        if (empty($schema)) {
            throw new \RuntimeException('Schema must be explicitly set in the Phinx configuration');
        }
        $this->execute(sprintf('CREATE SCHEMA IF NOT EXISTS %s;', $schema));

        $this->table('oauth_scopes', [
            'schema' => $schema,
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'default'
        ])
            ->addColumn('id', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('description', 'string', ['limit' => 255])
            ->addIndex(['id'], ['unique' => true])
            ->addTimestamps()
            ->create();
    }

    public function down()
    {
        $schema = $this->getAdapter()->getOption('schema');
        $this->table('oauth_scopes', ['schema' => $schema])->drop()->save();
    }
}
