<?php

use Phinx\Migration\AbstractMigration;

class CreateUsersTablePostgres extends AbstractMigration
{
    public function up()
    {
        $schema = $this->getAdapter()->getOption('schema');
        if (empty($schema)) {
            throw new \RuntimeException('Schema must be explicitly set in the Phinx configuration');
        }
        // $this->execute('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');

        $this->execute('CREATE OR REPLACE FUNCTION ' . $schema . '.update_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ language plpgsql;');

        $this->table('users', [
            'schema' => $schema,
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'default'
        ])
            ->addColumn('id', 'uuid', [
                'null' => false,
            ])
            ->addColumn('display_name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('is_verified', 'boolean', ['default' => false])
            ->addColumn('verified_at', 'timestamp', ['null' => true, 'timezone' => true])
            ->addColumn('additional_data', 'jsonb', ['null' => true])
            ->addTimestamps()
            ->addIndex(['id'], ['unique' => true])
            ->addIndex(['email'], ['unique' => true])
            ->create();

        $this->execute('CREATE TRIGGER update_users_updated_at
            BEFORE UPDATE ON ' . $schema . '.users
            FOR EACH ROW
            EXECUTE FUNCTION ' . $schema . '.update_updated_at_column();');
    }
}
