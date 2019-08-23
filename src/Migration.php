<?php

namespace Exolnet\WPMigration;

class Migration
{
    /**
     * @var string
     */
    public $user = '';

    /**
     * @var string
     */
    public $environment = 'production';

    public function __construct()
    {
        if (!empty($this->user)) {
            $user = get_user_by('login', $this->user);
            wp_set_current_user($user->ID, $this->user);
        }
    }
}
