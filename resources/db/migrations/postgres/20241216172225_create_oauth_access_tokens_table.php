<?php

declare(strict_types=1);

namespace Migrations\Postgres;

use Phinx\Migration\AbstractMigration;
use RuntimeException;

final class CreateOauthAccessTokensTable extends AbstractMigration
{
    public function up(): void
    {
        $schema = $this->getAdapter()->getOption('schema');
        if (empty($schema)) {
            throw new RuntimeException('Schema must be explicitly set in the Phinx configuration');
        }
        $this->execute(sprintf('CREATE SCHEMA IF NOT EXISTS %s;', $schema));

        $this->table('oauth_access_tokens', [
            'schema' => $schema,
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
            ->addForeignKey('client_id', $schema . '.oauth_clients', 'client_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', $schema . '.users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->execute('CREATE TRIGGER update_oauth_access_tokens_updated_at
            BEFORE UPDATE ON ' . $schema . '.oauth_access_tokens
            FOR EACH ROW
            EXECUTE FUNCTION ' . $schema . '.update_updated_at_column();');
    }

    public function down(): void
    {
        $this->table('oauth_access_tokens', ['schema' => 'graphql_auth_test'])->drop()->save();
    }
}
