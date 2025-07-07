<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOauthAuthCodesTable extends AbstractMigration
{
    public function change(): void
    {
        // Check if table already exists (from previous migration)
        if (!$this->hasTable('oauth_auth_codes')) {
            $this->table('oauth_auth_codes', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'string', ['limit' => 100, 'null' => false])
                ->addColumn('user_id', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('client_id', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('scopes', 'json', ['null' => true])
                ->addColumn('redirect_uri', 'text', ['null' => false])
                ->addColumn('code_challenge', 'string', ['limit' => 128, 'null' => true])
                ->addColumn('code_challenge_method', 'string', ['limit' => 10, 'null' => true])
                ->addColumn('revoked', 'boolean', ['default' => false])
                ->addColumn('expires_at', 'timestamp', ['null' => false])
                ->addTimestamps()
                ->addIndex(['id'], ['unique' => true])
                ->addForeignKey('client_id', 'oauth_clients', 'client_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        } else {
            // Table exists, add missing PKCE columns if they don't exist
            $table = $this->table('oauth_auth_codes');
            
            if (!$table->hasColumn('code_challenge')) {
                $table->addColumn('code_challenge', 'string', ['limit' => 128, 'null' => true]);
            }
            
            if (!$table->hasColumn('code_challenge_method')) {
                $table->addColumn('code_challenge_method', 'string', ['limit' => 10, 'null' => true]);
            }
            
            $table->save();
        }
    }
}
