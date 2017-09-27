Feature: Test an MU-Migration export.

    Scenario: MU-Migration is able to export the users on a single site
        Given a WP install

        When I run `wp user list --format=count`
        And save STDOUT as {USERS_COUNT}

        When I run `wp mu-migration export users users.csv`
        Then the users.csv file should exist
        Then STDOUT should be:
        """
        Success: {USERS_COUNT} users have been exported
        """

        When I run `cat users.csv`
        Then STDOUT should be CSV containing:
            | ID | user_login | user_email        | role          |
            | 1  | admin      | admin@example.com | administrator |

        When I run `wp eval-file {SRC_DIR}/features/tests/csv_matches_user.php users.csv`
        Then STDOUT should be:
        """
        Success
        """
    Scenario: MU-Migration is able to export users on a multisite
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content

        When I run `wp user list --format=count --url=example.com/site-2`
        And save STDOUT as {USERS_COUNT}

        When I run `wp mu-migration export users users-subsite.csv --blog_id=2`
        Then the users-subsite.csv file should exist
        Then STDOUT should be:
        """
        Success: {USERS_COUNT} users have been exported
        """

        When I run `cat  users-subsite.csv`
        Then STDOUT should be CSV containing:
            | ID | user_login | user_email        | role          |
            | 1  | admin      | admin@example.com | administrator |

        When I run `wp eval-file {SRC_DIR}/features/tests/csv_matches_user.php users-subsite.csv --url=example.com/site-2`
        Then STDOUT should be:
        """
        Success
        """

    Scenario: MU-Migration is able to export tables of a single site
        Given a WP install

        When I run `wp db prefix`
        And save STDOUT as {DB_PREFIX}

        When I run `wp mu-migration export tables tables.sql`
        Then the tables.sql file should exist
        Then the tables.sql file should contain:
        """
        CREATE TABLE `{DB_PREFIX}posts`     |AND|
        CREATE TABLE `{DB_PREFIX}postmeta`  |AND|
        CREATE TABLE `{DB_PREFIX}terms`     |AND|
        CREATE TABLE `{DB_PREFIX}termmeta`  |AND|
        CREATE TABLE `{DB_PREFIX}options`   |AND|
        CREATE TABLE `{DB_PREFIX}comments`  |AND|
        CREATE TABLE `{DB_PREFIX}commentmeta` |AND|
        CREATE TABLE `{DB_PREFIX}term_taxonomy` |AND|
        CREATE TABLE `{DB_PREFIX}term_relationships`
        """
        Then the tables.sql file should not contain:
        """
        CREATE TABLE `{DB_PREFIX}users`     |AND|
        CREATE TABLE `{DB_PREFIX}usermeta`
        """
        Then STDOUT should be:
        """
        Success: The export is now complete
        """

        When I run `wp mu-migration export tables tables1.sql --tables={DB_PREFIX}posts`
        Then the tables1.sql file should contain:
        """
        CREATE TABLE `{DB_PREFIX}posts`
        """
        Then the tables1.sql file should not contain:
        """
        CREATE TABLE `{DB_PREFIX}postmeta`  |AND|
        CREATE TABLE `{DB_PREFIX}terms`     |AND|
        CREATE TABLE `{DB_PREFIX}termmeta`  |AND|
        CREATE TABLE `{DB_PREFIX}options`   |AND|
        CREATE TABLE `{DB_PREFIX}comments`  |AND|
        CREATE TABLE `{DB_PREFIX}commentmeta` |AND|
        CREATE TABLE `{DB_PREFIX}term_taxonomy` |AND|
        CREATE TABLE `{DB_PREFIX}term_relationships`
        """

        When I run `wp db query "CREATE TABLE {DB_PREFIX}custom_table (ID int, text longtext)"`
        And I run `wp db query "CREATE TABLE custom_table_no_prefix (ID int, text longtext)"`
        And I run `wp mu-migration export tables tables2.sql --non-default-tables={DB_PREFIX}custom_table,custom_table_no_prefix`
        Then the tables2.sql file should contain:
        """
        CREATE TABLE `{DB_PREFIX}custom_table`  |AND|
        CREATE TABLE `custom_table_no_prefix`   |AND|
        """

        
 
