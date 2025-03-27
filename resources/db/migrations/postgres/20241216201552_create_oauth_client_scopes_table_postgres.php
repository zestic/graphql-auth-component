<?php

use Phinx\Migration\AbstractMigration;

class CreateOauthClientScopesTablePostgres extends AbstractMigration
{
    public function up()
    {
        $schema = $this->getAdapter()->getOption('schema');
        if (empty($schema)) {
            throw new \RuntimeException('Schema must be explicitly set in the Phinx configuration');
        }
        $this->execute(sprintf('CREATE SCHEMA IF NOT EXISTS %s;', $schema));

        $this->table('oauth_client_scopes', [
            'schema' => $schema,
            'id' => false,
            'primary_key' => ['client_id', 'scope'],
            'collation' => 'default'
        ])
            ->addColumn('client_id', 'uuid', ['null' => false])
            ->addColumn('scope', 'string', ['limit' => 100])
            ->addTimestamps()
            ->addIndex(['client_id', 'scope'], ['unique' => true])
            ->addForeignKey('client_id', $schema . '.oauth_clients', 'client_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('scope', $schema . '.oauth_scopes', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->execute('CREATE TRIGGER update_oauth_client_scopes_updated_at
            BEFORE UPDATE ON ' . $schema . '.oauth_client_scopes
            FOR EACH ROW
            EXECUTE FUNCTION ' . $schema . '.update_updated_at_column();');
    }

    public function down()
    {
        $schema = $this->getAdapter()->getOption('schema');
        $this->table('oauth_client_scopes', ['schema' => $schema])->drop()->save();
    }
}
