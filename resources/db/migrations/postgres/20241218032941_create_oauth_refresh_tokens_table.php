<?php

declare(strict_types=1);

namespace Migrations\Postgres;

use Phinx\Migration\AbstractMigration;
use RuntimeException;

final class CreateOauthRefreshTokensTable extends AbstractMigration
{
    public function up(): void
    {
        $schema = $this->getAdapter()->getOption('schema');
        if (empty($schema)) {
            throw new RuntimeException('Schema must be explicitly set in the Phinx configuration');
        }
        $this->execute(sprintf('CREATE SCHEMA IF NOT EXISTS %s;', $schema));
        $this->table('oauth_refresh_tokens', [
            'schema' => $schema,
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
            ->addForeignKey('access_token_id', $schema . '.oauth_access_tokens', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('client_id', $schema . '.oauth_clients', 'client_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', $schema . '.users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->execute('CREATE TRIGGER update_oauth_refresh_tokens_updated_at
            BEFORE UPDATE ON ' . $schema . '.oauth_refresh_tokens
            FOR EACH ROW
            EXECUTE FUNCTION ' . $schema . '.update_updated_at_column();');
    }

    public function down(): void
    {
        $schema = $this->getAdapter()->getOption('schema');
        $this->table('oauth_refresh_tokens', ['schema' => $schema])->drop()->save();
    }
}
