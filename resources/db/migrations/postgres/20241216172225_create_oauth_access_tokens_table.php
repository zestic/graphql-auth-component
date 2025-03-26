<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOauthAccessTokensTablePostgres extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('CREATE SCHEMA IF NOT EXISTS graphql_auth_test;');

        $this->table('oauth_access_tokens', [
            'schema' => 'graphql_auth_test',
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'default'
        ])
            ->addColumn('id', 'uuid', [
                'null' => false
            ])
            ->addColumn('user_id', 'uuid', ['null' => true])
            ->addColumn('client_id', 'uuid', ['null' => false])
            ->addColumn('scopes', 'jsonb', ['null' => true])
            ->addColumn('revoked', 'boolean', ['default' => false])
            ->addColumn('expires_at', 'timestamp', ['null' => false, 'timezone' => true])
            ->addTimestamps()
            ->addForeignKey('client_id', 'graphql_auth_test.oauth_clients', 'client_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'graphql_auth_test.users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->execute('CREATE TRIGGER update_oauth_access_tokens_updated_at
            BEFORE UPDATE ON graphql_auth_test.oauth_access_tokens
            FOR EACH ROW
            EXECUTE FUNCTION graphql_auth_test.update_updated_at_column();');
    }

    public function down(): void
    {
        $this->table('oauth_access_tokens', ['schema' => 'graphql_auth_test'])->drop()->save();
    }
}
