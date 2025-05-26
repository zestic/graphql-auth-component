<?php

use Phinx\Migration\AbstractMigration;

class CreateOauthRefreshTokensTablePostgres extends AbstractMigration
{
    public function up()
    {
        $schema = $this->getAdapter()->getOption('schema');
        if (empty($schema)) {
            throw new \RuntimeException('Schema must be explicitly set in the Phinx configuration');
        }

        $this->table('oauth_refresh_tokens', [
            'schema' => $schema,
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'default'
        ])
            ->addColumn('id', 'uuid', [
                'null' => false
            ])
            ->addColumn('access_token_id', 'uuid', ['null' => false])
            ->addColumn('client_id', 'uuid', ['null' => false])
            ->addColumn('user_id', 'uuid', ['null' => false])
            ->addColumn('revoked', 'boolean', ['default' => false])
            ->addColumn('expires_at', 'timestamp', ['null' => false, 'timezone' => true])
            ->addTimestamps()
            ->addIndex('id', ['unique' => true])
            ->addForeignKey('access_token_id', $schema . '.oauth_access_tokens', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('client_id', $schema . '.oauth_clients', 'client_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', $schema . '.users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->execute('CREATE OR REPLACE FUNCTION ' . $schema . '.update_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ language plpgsql;');

        $this->execute('CREATE TRIGGER update_oauth_refresh_tokens_updated_at
            BEFORE UPDATE ON ' . $schema . '.oauth_refresh_tokens
            FOR EACH ROW
            EXECUTE FUNCTION ' . $schema . '.update_updated_at_column();');
    }

    public function down()
    {
        $schema = $this->getAdapter()->getOption('schema');
        $this->table('oauth_refresh_tokens', ['schema' => $schema])->drop()->save();
    }
}
