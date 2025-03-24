<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOauthRefreshTokensTable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('oauth_refresh_tokens', [
            'schema' => 'graphql_auth_test',
            'id' => false,
            'primary_key' => ['id']
        ])
            ->addColumn('id', 'uuid', [
                'null' => false,
                'default' => new \Phinx\Util\Literal('uuid_generate_v4()')
            ])
            ->addColumn('user_id', 'uuid', ['null' => true])
            ->addColumn('client_id', 'uuid', ['null' => false])
            ->addColumn('access_token_id', 'uuid', ['null' => false])
            ->addColumn('scopes', 'json', ['null' => true])
            ->addColumn('revoked', 'boolean', ['default' => false])
            ->addColumn('expires_at', 'timestamp', ['null' => false])
            ->addTimestamps()
            ->addIndex(['id'], ['unique' => true])
            ->addForeignKey('access_token_id', 'graphql_auth_test.oauth_access_tokens', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('client_id', 'graphql_auth_test.oauth_clients', 'client_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'graphql_auth_test.users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->execute('CREATE TRIGGER update_oauth_refresh_tokens_updated_at
            BEFORE UPDATE ON graphql_auth_test.oauth_refresh_tokens
            FOR EACH ROW
            EXECUTE FUNCTION graphql_auth_test.update_updated_at_column();');
    }

    public function down(): void
    {
        $this->table('oauth_refresh_tokens', ['schema' => 'graphql_auth_test'])->drop()->save();
    }
}
