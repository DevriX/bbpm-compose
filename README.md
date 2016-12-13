# bbPM Compose
bbPress Messages Compose Helper.

Helps you easily compose and send messages to bbPress users with [bbPress Messages](https://samelh.com/wordpress-plugins/bbpress-messages/) plugin.

With AJAX, you can search and select recipients, or select all users with specific role, or even select all site users.

This was originally suggested by Pascal in [the support forums](https://support.samelh.com/forums/topic/send-message-to-all-users/#post-544).

To limit this feature, please use the `bbpmc_disabled` filter (return true|false). Here's an example:

**Restriciting compose to moderators and site admins only:**

```php
add_filter('bbpmc_disabled', function(){
    // roles to allow
    $allowRoles = array( 'bbp_moderator', 'bbp_keymaster' );
    if ( !$allowRoles ) return true; 
    global $current_user;
    if ( !$current_user->ID || empty( $current_user->roles ) ) {
        return true;
    }
    foreach ( $allowRoles as $role ) {
        if ( in_array($role, (array) $current_user->roles) ) {
            return false;
        } continue;
    }
    return true;
});
```

In the example we included the custom roles for admin (`bbp_keymaster`) and moderator (`bbp_moderator`) in line 3, in case you wanted to include more roles or restrict to a single role. The code should be added to a custom plugin or to your child theme's functions file.

Note: This plugin comes with no admin GUI, in case of confusion.

Thank You!
