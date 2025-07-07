<?php

use Phinx\Migration\AbstractMigration;

class CreateOauthAuthCodesTablePostgres extends AbstractMigration
{
    public function up()
    {
        $schema = $this->getAdapter()->getOption('schema');
        if (empty($schema)) {
            throw new RuntimeException('Schema must be explicitly set in the Phinx configuration');
        }

        // Check if table already exists (from previous migration)
        if (!$this->hasTable('oauth_auth_codes')) {
            $this->table('oauth_auth_codes', [
                'schema' => $schema,
                'id' => false,
                'primary_key' => ['id'],
                'collation' => 'default'
            ])
                ->addColumn('id', 'uuid', ['null' => false])
                ->addColumn('user_id', 'uuid', ['null' => true])
                ->addColumn('client_id', 'uuid', ['null' => false])
                ->addColumn('scopes', 'jsonb', ['null' => true])
                ->addColumn('redirect_uri', 'text', ['null' => false])
                ->addColumn('code_challenge', 'string', ['limit' => 128, 'null' => true])
                ->addColumn('code_challenge_method', 'string', ['limit' => 10, 'null' => true])
                ->addColumn('revoked', 'boolean', ['default' => false])
                ->addColumn('expires_at', 'timestamp', ['null' => false, 'timezone' => true])
                ->addTimestamps()
                ->addIndex('id', ['unique' => true])
                ->addForeignKey('client_id', $schema . '.oauth_clients', 'client_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('user_id', $schema . '.users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();

            $this->execute('CREATE TRIGGER update_oauth_auth_codes_updated_at
                BEFORE UPDATE ON ' . $schema . '.oauth_auth_codes
                FOR EACH ROW
                EXECUTE FUNCTION ' . $schema . '.update_updated_at_column();');
        } else {
            // Table exists, add missing PKCE columns if they don't exist
            $table = $this->table('oauth_auth_codes', ['schema' => $schema]);

            if (!$table->hasColumn('code_challenge')) {
                $table->addColumn('code_challenge', 'string', ['limit' => 128, 'null' => true]);
            }

            if (!$table->hasColumn('code_challenge_method')) {
                $table->addColumn('code_challenge_method', 'string', ['limit' => 10, 'null' => true]);
            }

            $table->save();
        }
    }

    public function down()
    {
        $schema = $this->getAdapter()->getOption('schema');
        $this->table('oauth_auth_codes', ['schema' => $schema])->drop()->save();
    }
}
