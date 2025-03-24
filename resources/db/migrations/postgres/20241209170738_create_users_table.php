<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function up()
    {
        $this->execute('CREATE SCHEMA IF NOT EXISTS graphql_auth_test;');
        $this->execute('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');

        $this->execute('CREATE OR REPLACE FUNCTION graphql_auth_test.update_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ language plpgsql;');

        $this->table('users', [
            'schema' => 'graphql_auth_test',
            'id' => false,
            'primary_key' => ['id']
        ])
            ->addColumn('id', 'uuid', [
                'null' => false,
                'default' => new \Phinx\Util\Literal('uuid_generate_v4()')
            ])
            ->addColumn('additional_data', 'jsonb', ['null' => false])
            ->addColumn('display_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('verified_at', 'timestamp', ['null' => true, 'default' => null, 'timezone' => true])
            ->addTimestamps()
            ->addIndex(['email'], ['unique' => true])
            ->create();

        $this->execute('CREATE TRIGGER update_users_updated_at
            BEFORE UPDATE ON graphql_auth_test.users
            FOR EACH ROW
            EXECUTE FUNCTION graphql_auth_test.update_updated_at_column();');
    }

    public function down()
    {
        $this->table('users', ['schema' => 'graphql_auth_test'])->drop()->save();
    }
}
