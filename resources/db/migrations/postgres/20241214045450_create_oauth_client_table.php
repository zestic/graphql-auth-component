<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOauthClientTablePostgres extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('CREATE SCHEMA IF NOT EXISTS graphql_auth_test;');

        $this->table('oauth_clients', [
            'schema' => 'graphql_auth_test',
            'id' => false,
            'primary_key' => ['client_id'],
            'collation' => 'default'
        ])
            ->addColumn('client_id', 'uuid', [
                'null' => false,
                'default' => new \Phinx\Util\Literal('uuid_generate_v4()')
            ])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('redirect_uri', 'jsonb', ['null' => true])
            ->addColumn('is_confidential', 'boolean', ['default' => false])
            ->addTimestamps()
            ->addColumn('deleted_at', 'timestamp', ['null' => true, 'timezone' => true])
            ->create();

        $this->execute('CREATE TRIGGER update_oauth_clients_updated_at
            BEFORE UPDATE ON graphql_auth_test.oauth_clients
            FOR EACH ROW
            EXECUTE FUNCTION graphql_auth_test.update_updated_at_column();');
    }

    public function down(): void
    {
        $this->table('oauth_clients', ['schema' => 'graphql_auth_test'])->drop()->save();
    }
}
