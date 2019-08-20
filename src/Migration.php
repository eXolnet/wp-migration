<?php


namespace Exolnet\WPMigration;


class Migration
{
    public $user = '';

    public function __construct()
    {
        if (!empty($this->user)){
            $user = get_user_by( 'login', $this->user );
            wp_set_current_user($user->ID, $this->user);
        }
    }
}
