<?php

use Phinx\Migration\AbstractMigration;

class CreateOauthClientTablePostgres extends AbstractMigration
{
    public function up()
    {
        $schema = $this->getAdapter()->getOption('schema');
        if (empty($schema)) {
            throw new \RuntimeException('Schema must be explicitly set in the Phinx configuration');
        }

        $this->execute('CREATE OR REPLACE FUNCTION ' . $schema . '.update_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ language plpgsql;');

        $this->table('oauth_clients', [
            'schema' => $schema,
            'id' => false,
            'primary_key' => ['client_id'],
            'collation' => 'default'
        ])
            ->addColumn('client_id', 'uuid', [
                'null' => false,
            ])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('redirect_uri', 'jsonb', ['null' => true])
            ->addColumn('is_confidential', 'boolean', ['default' => false])
            ->addTimestamps()
            ->addIndex('client_id', ['unique' => true])
            ->addColumn('deleted_at', 'timestamp', ['null' => true, 'timezone' => true])
            ->create();

        $this->execute('CREATE TRIGGER update_oauth_clients_updated_at
            BEFORE UPDATE ON ' . $schema . '.oauth_clients
            FOR EACH ROW
            EXECUTE FUNCTION ' . $schema . '.update_updated_at_column();');
    }

    public function down()
    {
        $schema = $this->getAdapter()->getOption('schema');
        $this->table('oauth_clients', ['schema' => $schema])->drop()->save();
    }
}
