<?php

declare(strict_types=1);

namespace Migrations\Postgres;

use Phinx\Migration\AbstractMigration;
use RuntimeException;

final class CreateOauthClientTable extends AbstractMigration
{
    public function up(): void
    {
        $schema = $this->getAdapter()->getOption('schema');
        if (empty($schema)) {
            throw new RuntimeException('Schema must be explicitly set in the Phinx configuration');
        }
        $this->execute(sprintf('CREATE SCHEMA IF NOT EXISTS %s;', $schema));

        $this->table('oauth_clients', [
            'schema' => $schema,
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
            BEFORE UPDATE ON ' . $schema . '.oauth_clients
            FOR EACH ROW
            EXECUTE FUNCTION ' . $schema . '.update_updated_at_column();');
    }

    public function down(): void
    {
        $this->table('oauth_clients', ['schema' => 'graphql_auth_test'])->drop()->save();
    }
}
