Feature: Test MU-Migration export commands.

    Scenario: MU-Migration is able to export the users of a single site
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
    Scenario: MU-Migration is able to export users of a subsite in multisite
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
    Scenario: MU-Migration is able to export tables for subsites in Multisite
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content

        When I run `wp db prefix --url=example.com/site-3`
        And save STDOUT as {DB_PREFIX}

        When I run `wp mu-migration export tables tables-subsite.sql --blog_id=3`
        Then the tables-subsite.sql file should exist
        Then the tables-subsite.sql file should contain:
        """
        CREATE TABLE `{DB_PREFIX}posts`                 |AND|
        CREATE TABLE `{DB_PREFIX}postmeta`              |AND|
        CREATE TABLE `{DB_PREFIX}terms`                 |AND|
        CREATE TABLE `{DB_PREFIX}termmeta`              |AND|
        CREATE TABLE `{DB_PREFIX}options`               |AND|
        CREATE TABLE `{DB_PREFIX}comments`              |AND|
        CREATE TABLE `{DB_PREFIX}commentmeta`           |AND|
        CREATE TABLE `{DB_PREFIX}term_taxonomy`         |AND|
        CREATE TABLE `{DB_PREFIX}term_relationships`    |AND|
        """
        Then the tables-subsite.sql file should not contain:
        """
        CREATE TABLE `{DB_PREFIX}users`             |AND|
        CREATE TABLE `{DB_PREFIX}usermeta`          |AND|
        CREATE TABLE `{DB_PREFIX}blog_versions`     |AND|
        CREATE TABLE `{DB_PREFIX}blogs`             |AND|
        CREATE TABLE `{DB_PREFIX}site`              |AND|
        CREATE TABLE `{DB_PREFIX}sitemeta`          |AND|
        CREATE TABLE `{DB_PREFIX}registration_log`  |AND|
        CREATE TABLE `{DB_PREFIX}signups`
        """
        Then STDOUT should be:
        """
        Success: The export is now complete
        """

        When I run `wp mu-migration export tables tables-subsite1.sql --tables={DB_PREFIX}posts --blog_id=3`
        Then the tables-subsite1.sql file should contain:
        """
        CREATE TABLE `{DB_PREFIX}posts`
        """
        Then the tables-subsite1.sql file should not contain:
        """
        CREATE TABLE `{DB_PREFIX}postmeta`          |AND|
        CREATE TABLE `{DB_PREFIX}terms`             |AND|
        CREATE TABLE `{DB_PREFIX}termmeta`          |AND|
        CREATE TABLE `{DB_PREFIX}options`           |AND|
        CREATE TABLE `{DB_PREFIX}comments`          |AND|
        CREATE TABLE `{DB_PREFIX}commentmeta`       |AND|
        CREATE TABLE `{DB_PREFIX}term_taxonomy`     |AND|
        CREATE TABLE `{DB_PREFIX}term_relationships`
        """
        When I run `wp db query "CREATE TABLE {DB_PREFIX}custom_table (ID int, text longtext)"`
        And I run `wp db query "CREATE TABLE custom_table_no_prefix (ID int, text longtext)"`
        And I run `wp mu-migration export tables tables-subsite2.sql --non-default-tables={DB_PREFIX}custom_table,custom_table_no_prefix`
        Then the tables-subsite2.sql file should contain:
        """
        CREATE TABLE `{DB_PREFIX}custom_table`  |AND|
        CREATE TABLE `custom_table_no_prefix`   |AND|
        """

    Scenario: MU-Migration is able to export a single site into a zip package without themes,plugins and uploads
        Given a WP install
        
        When I run `wp db prefix`
        And save STDOUT as {DB_PREFIX}
        And I run `wp plugin install jetpack --activate`
        
        When I run `wp mu-migration export all single-site.zip`
        Then STDOUT should be:
        """
        Exporting site meta data...
        Exporting users...
        Exporting tables
        Zipping files....
        Success: A zip file named single-site.zip has been created
        """
        
        When I run `unzip single-site.zip -d temp_folder`
        And I run `ls temp_folder/ | grep .csv`
        And save STDOUT as {MU_CSV_FILE}
        And I run `ls temp_folder/ | grep .json`
        And save STDOUT as {MU_JSON_FILE}
        And I run `ls temp_folder/ | grep .sql`
        And save STDOUT as {MU_SQL_FILE}
        Then the single-site.zip file should exist
        Then the temp_folder/{MU_CSV_FILE} file should exist
        Then the temp_folder/{MU_JSON_FILE} file should exist
        Then the temp_folder/{MU_SQL_FILE} file should exist
        Then the temp_folder/{MU_SQL_FILE} file should contain:
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

        When I run `wp eval-file {SRC_DIR}/features/tests/csv_matches_user.php temp_folder/{MU_CSV_FILE}`
        Then STDOUT should be:
        """
        Success
        """
        When I run `cat temp_folder/{MU_JSON_FILE}`
        Then STDOUT should be JSON containing:
        """
        {
            "url": "http:\/\/example.com",
            "name": "WP CLI Site",
            "admin_email":"admin@example.com",
            "site_language":"en-US",
            "db_prefix": "{DB_PREFIX}",
            "blog_id": 1,
            "blog_plugins": ["jetpack\/jetpack.php"],
            "network_plugins": []
        }
        """
    Scenario: MU-Migration is able to export a single site into a zip package with themes,plugins and uploads
        Given a WP install
        
        When I run `wp db prefix`
        And save STDOUT as {DB_PREFIX}
        And I run `wp plugin install jetpack --activate`
        And I run `wp mu-migration export all single-site.zip --themes --plugins --uploads`
        Then the single-site.zip file should exist
    
        When I run `unzip single-site.zip -d temp_folder`
        And I run `ls temp_folder/ | grep .csv`
        And save STDOUT as {MU_CSV_FILE}
        And I run `ls temp_folder/ | grep .json`
        And save STDOUT as {MU_JSON_FILE}
        And I run `ls temp_folder/ | grep .sql`
        And save STDOUT as {MU_SQL_FILE}
        Then the temp_folder/{MU_CSV_FILE} file should exist
        Then the temp_folder/{MU_JSON_FILE} file should exist
        Then the temp_folder/{MU_SQL_FILE} file should exist
        Then the temp_folder/wp-content directory should exist
        Then the temp_folder/wp-content/themes directory should exist
        Then the temp_folder/wp-content/plugins directory should exist
        Then the temp_folder/wp-content/plugins/jetpack directory should exist
        Then the temp_folder/wp-content/uploads directory should exist

    Scenario: MU-Migration is able to export a subsite without themes, plugins and uploads
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content
        
        When I run `wp db prefix --url=example.com/site-2`
        And save STDOUT as {DB_PREFIX}
        And I run `wp plugin install jetpack shortcode-ui --activate --url=example.com/site-2`
        
        When I run `wp mu-migration export all subsite-2.zip --blog_id=2`
        Then STDOUT should be:
        """
        Exporting site meta data...
        Exporting users...
        Exporting tables
        Zipping files....
        Success: A zip file named subsite-2.zip has been created
        """
        
        When I run `unzip subsite-2.zip -d temp_folder_subsite`
        And I run `ls temp_folder_subsite/ | grep .csv`
        And save STDOUT as {MU_CSV_FILE}
        And I run `ls temp_folder_subsite/ | grep .json`
        And save STDOUT as {MU_JSON_FILE}
        And I run `ls temp_folder_subsite/ | grep .sql`
        And save STDOUT as {MU_SQL_FILE}
        Then the subsite-2.zip file should exist
        Then the temp_folder_subsite/{MU_CSV_FILE} file should exist
        Then the temp_folder_subsite/{MU_JSON_FILE} file should exist
        Then the temp_folder_subsite/{MU_SQL_FILE} file should exist
        Then the temp_folder_subsite/{MU_SQL_FILE} file should contain:
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

        When I run `wp eval-file {SRC_DIR}/features/tests/csv_matches_user.php temp_folder_subsite/{MU_CSV_FILE} --url=example.com/site-2`
        Then STDOUT should be:
        """
        Success
        """
        When I run `cat temp_folder_subsite/{MU_JSON_FILE}`
        Then STDOUT should be JSON containing:
        """
        {
            "url": "http:\/\/example.com/site-2",
            "name": "Site 2",
            "admin_email":"admin@example.com",
            "site_language":"en-US",
            "db_prefix": "{DB_PREFIX}",
            "blog_id": 2,
            "blog_plugins": ["jetpack\/jetpack.php", "shortcode-ui\/shortcode-ui.php"],
            "network_plugins": []
        }
        """
    Scenario: MU-Migration is able to export a subsite into a zip package with themes,plugins and uploads
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content
        
        When I run `wp db prefix`
        And save STDOUT as {DB_PREFIX}
        And I run `wp plugin install jetpack --activate --url=example.com/site-2`
        And I run `wp media import {SRC_DIR}/features/data/images/*.jpg --url=example.com/site-2`
        And I run `wp mu-migration export all subsite-2.zip --themes --plugins --uploads`
        Then the subsite-2.zip file should exist
    
        When I run `unzip subsite-2.zip -d temp_folder_subsite`
        And I run `ls temp_folder_subsite/ | grep .csv`
        And save STDOUT as {MU_CSV_FILE}
        And I run `ls temp_folder_subsite/ | grep .json`
        And save STDOUT as {MU_JSON_FILE}
        And I run `ls temp_folder_subsite/ | grep .sql`
        And save STDOUT as {MU_SQL_FILE}
        Then the temp_folder_subsite/{MU_CSV_FILE} file should exist
        Then the temp_folder_subsite/{MU_JSON_FILE} file should exist
        Then the temp_folder_subsite/{MU_SQL_FILE} file should exist
        Then the temp_folder_subsite/wp-content directory should exist
        Then the temp_folder_subsite/wp-content/themes directory should exist
        Then the temp_folder_subsite/wp-content/plugins directory should exist
        Then the temp_folder_subsite/wp-content/plugins/jetpack directory should exist
        Then the temp_folder_subsite/wp-content/uploads directory should exist
        Then the temp_folder_subsite/wp-content/uploads/sites/2 directory should exist
 
