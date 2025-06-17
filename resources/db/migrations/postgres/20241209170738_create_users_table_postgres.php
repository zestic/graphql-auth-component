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

        // Function should already exist from earlier migrations, but create if not exists
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
            ->addColumn('additional_data', 'jsonb', ['null' => true])
            ->addColumn('display_name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('system_id', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('verified_at', 'timestamp', ['null' => true, 'timezone' => true])
            ->addTimestamps()
            ->addIndex(['id'], ['unique' => true])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['system_id'], ['unique' => true])
            ->create();

        $this->execute('CREATE TRIGGER update_users_updated_at
            BEFORE UPDATE ON ' . $schema . '.users
            FOR EACH ROW
            EXECUTE FUNCTION ' . $schema . '.update_updated_at_column();');
    }

    public function down()
    {
        $schema = $this->getAdapter()->getOption('schema');
        $this->table('users', ['schema' => $schema])->drop()->save();
    }
}
